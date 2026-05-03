<?php
// This file is part of the ZEAL local plugin for Moodle.

namespace local_ajananova\ai;

defined('MOODLE_INTERNAL') || die();

use local_ajananova\billing\credit_manager;
use local_ajananova\billing\usage_logger;
use local_ajananova\grading\criteria_reader;
use local_ajananova\pdf\extractor;

/**
 * Orchestrates the full AI marking flow for a single submission.
 *
 * Call order (mirrors the spec in marking_engine.php):
 *  1. Load submission + assignment + learner
 *  2. Credit check (bail early if exhausted)
 *  3. Extract text from submission and memo PDFs
 *  4. Build prompts
 *  5. Call AI client (mock or real)
 *  6. Annotate PDF
 *  7. Persist results to DB
 *  8. Log billable event (real mode only)
 *
 * Returns a status array so the caller (UI layer / adhoc task) can branch on
 * 'success', 'no_credits', or 'error' without catching exceptions.
 */
class marking_engine {

    /**
     * Run the full marking flow.
     *
     * @param  int $submissionid  Moodle assign_submission.id
     * @param  int $assessorid    Moodle user.id of the triggering assessor
     * @return array  ['status' => 'success', 'result' => [...]]
     *              | ['status' => 'no_credits']
     *              | ['status' => 'error', 'message' => string]
     */
    public function run(int $submissionid, int $assessorid): array {
        global $DB;

        try {
            // ------------------------------------------------------------------
            // 1. Load records
            // ------------------------------------------------------------------
            $submission = $DB->get_record('assign_submission',
                ['id' => $submissionid], '*', MUST_EXIST);

            $assignment = $DB->get_record('assign',
                ['id' => $submission->assignment], '*', MUST_EXIST);

            $learner = \core_user::get_user($submission->userid, '*', MUST_EXIST);

            // ------------------------------------------------------------------
            // 2. Credit check (skipped in mock mode — no credits consumed)
            // ------------------------------------------------------------------
            $mockmode = (bool) get_config('local_ajananova', 'mock_mode');
            $creditmgr = new credit_manager();
            if (!$mockmode && !$creditmgr->has_credits()) {
                return ['status' => 'no_credits'];
            }

            // ------------------------------------------------------------------
            // 3. Resolve context + extract text from PDFs (skipped in mock mode)
            // ------------------------------------------------------------------
            $submissiontext = '';
            $memotext       = '';
            if (!$mockmode) {
                $cm      = get_coursemodule_from_instance('assign', $assignment->id, 0, false, MUST_EXIST);
                $context = \context_module::instance($cm->id);
                $fs      = get_file_storage();

                // 3a. Submission text — prefer online text (no PDF parsing needed),
                //     fall back to PDF file extraction.
                $submissionfile = null;
                $pdfextractor   = new extractor();

                $onlinetextrow = $DB->get_record('assignsubmission_onlinetext', [
                    'assignment' => $assignment->id,
                    'submission' => $submission->id,
                ]);

                if ($onlinetextrow && !empty(trim(strip_tags($onlinetextrow->onlinetext ?? '')))) {
                    // Online text submission — strip HTML tags, preserve structure.
                    $submissiontext = html_to_text($onlinetextrow->onlinetext, 0, false);
                } else {
                    // PDF file submission fallback.
                    $submissionfiles = $fs->get_area_files(
                        $context->id,
                        'assignsubmission_file',
                        'submission_files',
                        $submission->id,
                        'itemid, filepath, filename',
                        false
                    );
                    $submissionfile = reset($submissionfiles) ?: null;

                    if (!$submissionfile) {
                        throw new \moodle_exception(
                            'ajananova_no_submission_file', 'local_ajananova'
                        );
                    }

                    $submissiontext = $pdfextractor->extract_from_stored_file($submissionfile);
                }

                // 3b. Memo — priority 1: Moodle rubric or marking guide.
                $criteriareader = new criteria_reader();
                $memotext       = $criteriareader->get_criteria_text($assignment->id, $context->id);

                // 3b-ii. Memo — priority 2: assessor-pasted text (always readable, no PDF parsing).
                if (empty(trim($memotext))) {
                    $savedtext = (string) get_config('local_ajananova', 'memo_text_' . $assignment->id);
                    if (!empty(trim($savedtext))) {
                        $memotext = $savedtext;
                    }
                }

                // 3c. Memo — priority 3: uploaded memo PDF stored against assignment.
                if (empty(trim($memotext))) {
                    $memofiles = $fs->get_area_files(
                        $context->id,
                        'local_ajananova',
                        'memo',
                        $assignment->id,
                        'itemid',
                        false
                    );
                    $memofile = reset($memofiles);
                    if ($memofile) {
                        $memotext = $pdfextractor->extract_from_stored_file($memofile);
                    }
                }

                // 3d. If still no criteria — block and tell the assessor.
                if (empty(trim($memotext))) {
                    throw new \moodle_exception(
                        'ajananova_no_criteria', 'local_ajananova'
                    );
                }
            }

            // ------------------------------------------------------------------
            // 4. Build prompts
            // ------------------------------------------------------------------
            $builder      = new prompt_builder();
            $systemprompt = $builder->build_system_prompt();
            $usermessage  = $builder->build_user_message(
                $memotext,
                $submissiontext,
                fullname($learner),
                $assignment->name,
                date('Y-m-d', $submission->timemodified)
            );

            // ------------------------------------------------------------------
            // 5. Call AI — mock or real
            // ------------------------------------------------------------------
            $client = $mockmode ? new mock_client() : new anthropic_client();
            $result   = $client->mark($systemprompt, $usermessage);

            // ------------------------------------------------------------------
            // 6. Annotate PDF (skipped in mock mode or when submission is online text)
            // ------------------------------------------------------------------
            $annotatedfileid = null;
            if (!$mockmode && $submissionfile !== null) {
                // Copy stored file to a temp path for the annotator.
                $tmpdir = make_temp_directory('local_ajananova_pdf');
                $tmppdf = $tmpdir . '/' . clean_filename($submissionfile->get_filename());
                $submissionfile->copy_content_to($tmppdf);

                try {
                    $annotator     = new \local_ajananova\pdf\annotator();
                    $annotatedpath = $annotator->annotate($tmppdf, $result['questions'] ?? []);

                    $annotatedfileid = $this->save_annotated_pdf(
                        $annotatedpath,
                        $submission,
                        $assessorid,
                        $context
                    );

                    // Save annotated PDF to assignfeedback_file so it appears
                    // as a download link in the grading table (action=grading).
                    $this->save_feedback_file(
                        $annotatedpath,
                        $submission,
                        $assessorid,
                        $context,
                        clean_filename($submissionfile->get_filename())
                    );
                } catch (\Exception $e) {
                    // Annotation failed — log and continue. The AI result and
                    // credit deduction still proceed. Vision API upgrade will
                    // replace the annotator and fix this permanently.
                    debugging('local_ajananova annotator error: ' . $e->getMessage(), DEBUG_DEVELOPER);
                    $annotatedfileid = null;
                }

                // Store per-question AI feedback in assignfeedback_comments so
                // it is visible and editable in the native Moodle grader view
                // (mod/assign/view.php?action=grader&userid=x).
                $this->save_feedback_comment($submission, $assessorid, $result, $context);
            }

            // ------------------------------------------------------------------
            // 7. Persist results
            // ------------------------------------------------------------------
            $usageid = $this->save_usage($submission, $assessorid, $result, $mockmode);
            $this->save_result($usageid, $submissionid, $result, $assessorid, $annotatedfileid);

            // ------------------------------------------------------------------
            // 8. Log billable event (skip in mock mode)
            // ------------------------------------------------------------------
            if (!$mockmode) {
                $logger = new usage_logger();
                $logger->log([
                    'event_type'    => 'ai_mark',
                    'assessor_id'   => $assessorid,
                    'learner_id'    => $submission->userid,
                    'assignment_id' => $submission->assignment,
                    'tokens_input'  => $result['_tokens_input'],
                    'tokens_output' => $result['_tokens_output'],
                    'api_id'        => $result['_api_id'],
                ]);
                $creditmgr->deduct_credit();
            }

            return ['status' => 'success', 'result' => $result];

        } catch (\moodle_exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Inserts a row into ajananova_ai_usage and returns the new id.
     */
    private function save_usage(
        object $submission,
        int $assessorid,
        array $result,
        bool $mockmode
    ): int {
        global $DB;

        $licencekey = (string) get_config('local_ajananova', 'licence_key');

        $record = (object) [
            'licence_key'     => $licencekey,
            'sdp_id'          => 0,             // populated by central platform on sync
            'assessor_id'     => $assessorid,
            'learner_id'      => $submission->userid,
            'assignment_id'   => $submission->assignment,
            'tokens_input'    => $result['_tokens_input'],
            'tokens_output'   => $result['_tokens_output'],
            'credits_charged' => $mockmode ? 0 : (int) get_config('local_ajananova', 'ai_mark_cost_credits'),
            'mock_mode'       => (int) $mockmode,
            'status'          => 'success',
            'api_response_id' => $result['_api_id'],
            'timecreated'     => time(),
        ];

        return $DB->insert_record('ajananova_ai_usage', $record);
    }

    /**
     * Inserts a row into ajananova_marking_results.
     */
    private function save_result(
        int $usageid,
        int $submissionid,
        array $result,
        int $assessorid,
        ?int $annotatedfileid
    ): void {
        global $DB;

        $record = (object) [
            'usage_id'             => $usageid,
            'submission_id'        => $submissionid,
            'ai_recommendation'    => $result['overall_recommendation'] ?? '',
            'ai_confidence'        => $result['confidence'] ?? '',
            'questions_json'       => json_encode($result['questions'] ?? []),
            'moderation_notes'     => $result['moderation_notes'] ?? null,
            'assessor_override'    => 0,
            'final_verdict'        => null,
            'assessor_id'          => $assessorid,
            'annotated_pdf_fileid' => $annotatedfileid,
            'timecreated'          => time(),
            'timereviewed'         => null,
        ];

        $DB->insert_record('ajananova_marking_results', $record);
    }

    /**
     * Saves the annotated PDF into assignfeedback_editpdf / download so the
     * POE export plugin finds it automatically alongside the submission.
     *
     * Gets or creates the assign_grades row for this learner (Moodle requires
     * one before feedback files can be stored against an assignment attempt).
     *
     * @param  string   $annotatedpath  Absolute path to the temp annotated PDF.
     * @param  object   $submission     assign_submission record.
     * @param  int      $assessorid     Moodle user.id of the assessor.
     * @param  object   $context        Module context for this assignment.
     * @return int  The mdl_files.id of the stored annotated PDF.
     */
    private function save_annotated_pdf(
        string $annotatedpath,
        object $submission,
        int $assessorid,
        \context_module $context
    ): int {
        global $DB;

        // Get or create the assign_grades row — required as the file itemid.
        $grade = $DB->get_record('assign_grades', [
            'assignment'    => $submission->assignment,
            'userid'        => $submission->userid,
            'attemptnumber' => $submission->attemptnumber,
        ]);

        if (!$grade) {
            $grade = (object) [
                'assignment'    => $submission->assignment,
                'userid'        => $submission->userid,
                'timecreated'   => time(),
                'timemodified'  => time(),
                'grader'        => $assessorid,
                'grade'         => -1,   // unset — assessor confirms after review
                'attemptnumber' => $submission->attemptnumber,
            ];
            $grade->id = $DB->insert_record('assign_grades', $grade);
        }

        $fs = get_file_storage();

        // Remove any previous annotated PDF for this grade attempt.
        $fs->delete_area_files(
            $context->id,
            'assignfeedback_editpdf',
            'download',
            $grade->id
        );

        $fileinfo = [
            'contextid' => $context->id,
            'component' => 'assignfeedback_editpdf',
            'filearea'  => 'download',
            'itemid'    => $grade->id,
            'filepath'  => '/',
            'filename'  => 'feedback.pdf',
        ];

        $storedfile = $fs->create_file_from_pathname($fileinfo, $annotatedpath);

        return $storedfile->get_id();
    }

    /**
     * Saves the annotated PDF into assignfeedback_file / feedback_files so
     * that a download link appears in the grading table (action=grading).
     *
     * The file is named "AI Marked — [original filename]" so assessors can
     * distinguish it from the original submission in the feedback column.
     */
    private function save_feedback_file(
        string $annotatedpath,
        object $submission,
        int $assessorid,
        \context_module $context,
        string $originalfilename
    ): void {
        global $DB;

        $grade = $DB->get_record('assign_grades', [
            'assignment'    => $submission->assignment,
            'userid'        => $submission->userid,
            'attemptnumber' => $submission->attemptnumber,
        ]);

        if (!$grade) {
            return; // Grade record must exist — save_annotated_pdf creates it first.
        }

        $fs = get_file_storage();

        $feedbackfilename = 'AI Marked - ' . $originalfilename;

        // Remove any previous AI-marked feedback file for this grade attempt.
        $existing = $fs->get_file(
            $context->id,
            'assignfeedback_file',
            'feedback_files',
            $grade->id,
            '/',
            $feedbackfilename
        );
        if ($existing) {
            $existing->delete();
        }

        $fileinfo = [
            'contextid' => $context->id,
            'component' => 'assignfeedback_file',
            'filearea'  => 'feedback_files',
            'itemid'    => $grade->id,
            'filepath'  => '/',
            'filename'  => $feedbackfilename,
        ];

        $fs->create_file_from_pathname($fileinfo, $annotatedpath);
    }

    /**
     * Writes per-question AI feedback as an HTML table into
     * assignfeedback_comments so it appears in the native Moodle grader view
     * (mod/assign/view.php?action=grader&userid=x) and is editable by the
     * assessor — for example after a learner appeal.
     */
    private function save_feedback_comment(
        object $submission,
        int $assessorid,
        array $result,
        \context_module $context
    ): void {
        global $DB;

        $grade = $DB->get_record('assign_grades', [
            'assignment'    => $submission->assignment,
            'userid'        => $submission->userid,
            'attemptnumber' => $submission->attemptnumber,
        ]);

        if (!$grade) {
            return;
        }

        $html = $this->build_feedback_html($result, $submission->id);

        $existing = $DB->get_record('assignfeedback_comments', [
            'assignment' => $submission->assignment,
            'grade'      => $grade->id,
        ]);

        if ($existing) {
            $existing->commenttext   = $html;
            $existing->commentformat = FORMAT_HTML;
            $DB->update_record('assignfeedback_comments', $existing);
        } else {
            $DB->insert_record('assignfeedback_comments', (object) [
                'assignment'    => $submission->assignment,
                'grade'         => $grade->id,
                'commenttext'   => $html,
                'commentformat' => FORMAT_HTML,
            ]);
        }
    }

    /**
     * Builds the short feedback text stored in assignfeedback_comments.
     *
     * Deliberately compact — this text appears in the grading TABLE column
     * and in the grader VIEW sidebar. It shows only the overall result and
     * a link back to mark.php where the assessor can review per-question
     * AI feedback, override verdicts, and sign off.
     *
     * The full per-question AI feedback table lives on mark.php only.
     */
    private function build_feedback_html(array $result, int $submissionid): string {
        $questions      = $result['questions'] ?? [];
        $recommendation = $result['overall_recommendation'] ?? '';
        $confidence     = $result['confidence'] ?? '';
        $mock           = !empty($result['mock']);

        $totalawarded   = 0;
        $totalavailable = 0;
        foreach ($questions as $q) {
            $totalawarded   += (int) ($q['marks_awarded']   ?? 0);
            $totalavailable += (int) ($q['marks_available'] ?? 0);
        }

        $mockflag = $mock ? ' <em>[MOCK MODE]</em>' : '';

        $colour = match ($recommendation) {
            'COMPETENT'               => '#006400',
            'NOT_YET_COMPETENT'       => '#cc0000',
            default                   => '#b35900',
        };

        $markurl = (new \moodle_url('/local/ajananova/mark.php',
            ['submissionid' => $submissionid]))->out(false);

        $html  = '<p style="margin:0;font-size:0.9em">';
        $html .= '<strong>AjanaNova AI Pre-Mark</strong>' . $mockflag . '<br>';
        $html .= 'Result: <strong style="color:' . $colour . '">' . s($recommendation) . '</strong><br>';
        $html .= 'Confidence: ' . s($confidence) . '<br>';
        $html .= 'Score: ' . $totalawarded . '/' . $totalavailable . ' marks<br>';
        $html .= '<a href="' . $markurl . '">View full AI feedback &amp; sign off &rarr;</a>';
        $html .= '</p>';

        return $html;
    }
}
