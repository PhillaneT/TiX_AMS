<?php
// This file is part of the ZEAL local plugin for Moodle.
//
// Entry point for AI marking.
//
// Accepted query-string parameters:
//   submissionid  int     required  — assign_submission.id to mark
//   action        string  optional  — 'mark' (default) | 'signoff'
//
// Flow:
//   GET  ?submissionid=X            → trigger marking engine, render results
//   POST ?submissionid=X&action=signoff → save assessor sign-off, redirect to grading

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

use local_ajananova\ai\marking_engine;
use local_ajananova\output\marking_review;
use local_ajananova\billing\credit_manager;

// -------------------------------------------------------------------------
// Parameter validation
// -------------------------------------------------------------------------
$submissionid = required_param('submissionid', PARAM_INT);
$action       = optional_param('action', 'mark', PARAM_ALPHA);
$cmid         = optional_param('cmid', 0, PARAM_INT);

// Guard against missing submissionid (e.g. arriving via gear menu without one).
// Redirect back to the assignment submissions page if cmid is known, otherwise home.
if ($submissionid === 0) {
    $fallback = $cmid
        ? new moodle_url('/mod/assign/view.php', ['id' => $cmid, 'action' => 'grading'])
        : new moodle_url('/');
    redirect(
        $fallback,
        get_string('ajananova_select_learner_first', 'local_ajananova'),
        5,
        \core\output\notification::NOTIFY_WARNING
    );
}

// Load submission → assignment → course module → context.
$submission = $DB->get_record('assign_submission', ['id' => $submissionid], '*', MUST_EXIST);
$cm         = get_coursemodule_from_instance('assign', $submission->assignment, 0, false, MUST_EXIST);
$course     = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context    = context_module::instance($cm->id);

// -------------------------------------------------------------------------
// Auth + capability check
// -------------------------------------------------------------------------
require_login($course, false, $cm);
require_capability('mod/assign:grade', $context);

$PAGE->set_url(new moodle_url('/local/ajananova/mark.php', [
    'submissionid' => $submissionid,
]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('ai_marking_results', 'local_ajananova'));
$PAGE->set_heading($course->fullname);

// -------------------------------------------------------------------------
// POST: assessor sign-off
// -------------------------------------------------------------------------
if ($action === 'signoff' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    $finalverdict  = optional_param('final_verdict', '', PARAM_ALPHA);
    $assessorname  = optional_param('assessor_name', fullname($USER), PARAM_TEXT);

    // Guard: verdict must be selected — JS catches this first, but protect server-side too.
    if (!in_array($finalverdict, ['COMPETENT', 'NOT_YET_COMPETENT'], true)) {
        redirect(
            new moodle_url('/local/ajananova/mark.php', ['submissionid' => $submissionid]),
            get_string('signoff_verdict_required', 'local_ajananova'),
            0,
            \core\output\notification::NOTIFY_ERROR
        );
    }
    $overrides     = optional_param_array('override', [], PARAM_ALPHA);

    // Update the existing marking result row.
    $resultrow = $DB->get_record(
        'ajananova_marking_results',
        ['submission_id' => $submissionid],
        '*',
        IGNORE_MULTIPLE   // take the most recent; Phase 2 adds ordering
    );

    if ($resultrow) {
        $resultrow->assessor_override = !empty($overrides) ? 1 : 0;
        $resultrow->final_verdict     = clean_param($finalverdict, PARAM_ALPHA);
        $resultrow->assessor_id       = $USER->id;
        $resultrow->timereviewed      = time();
        $DB->update_record('ajananova_marking_results', $resultrow);
    }

    // Redirect back to the standard Moodle grading page.
    $gradingurl = new moodle_url('/mod/assign/view.php', [
        'id'     => $cm->id,
        'action' => 'grader',
        'userid' => $submission->userid,
    ]);
    redirect($gradingurl, get_string('signoff_saved', 'local_ajananova'), 3);
}

// -------------------------------------------------------------------------
// GET: trigger marking engine and render results
// -------------------------------------------------------------------------

// Check credits first so we can show the exhausted screen without running the engine.
$creditmgr = new credit_manager();
$mockmode  = (bool) get_config('local_ajananova', 'mock_mode');

if (!$mockmode && !$creditmgr->has_credits()) {
    // No credits — show exhausted screen and bail.
    $manualurl = new moodle_url('/mod/assign/view.php', [
        'id'     => $cm->id,
        'action' => 'grader',
        'userid' => $submission->userid,
    ]);

    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('local_ajananova/credits_exhausted', [
        'manual_grading_url' => $manualurl->out(false),
    ]);
    echo $OUTPUT->footer();
    exit;
}

// Load an existing result if one exists — avoid re-running the AI and spending credits.
// Pass ?force=1 in the URL to trigger a fresh marking run.
$force   = optional_param('force', 0, PARAM_INT);
$outcome = null;

if (!$force) {
    $cached = $DB->get_records_sql(
        'SELECT r.* FROM {ajananova_marking_results} r
          WHERE r.submission_id = :sid
          ORDER BY r.timecreated DESC',
        ['sid' => $submissionid],
        0, 1
    );
    $cachedrow = $cached ? reset($cached) : null;
    if ($cachedrow) {
        $outcome = [
            'status' => 'success',
            'result' => [
                'overall_recommendation' => $cachedrow->ai_recommendation,
                'confidence'             => $cachedrow->ai_confidence,
                'questions'              => json_decode($cachedrow->questions_json, true) ?? [],
                'moderation_notes'       => $cachedrow->moderation_notes,
                '_tokens_input'          => 0,
                '_tokens_output'         => 0,
                '_api_id'                => '',
                '_from_cache'            => true,
            ],
        ];
    }
}

if ($outcome === null) {
    $engine  = new marking_engine();
    $outcome = $engine->run($submissionid, $USER->id);
}

echo $OUTPUT->header();

if ($outcome['status'] === 'no_credits') {
    $manualurl = new moodle_url('/mod/assign/view.php', [
        'id'     => $cm->id,
        'action' => 'grader',
        'userid' => $submission->userid,
    ]);
    echo $OUTPUT->render_from_template('local_ajananova/credits_exhausted', [
        'manual_grading_url' => $manualurl->out(false),
    ]);

} else if ($outcome['status'] === 'error') {
    echo $OUTPUT->notification(
        get_string('ajananova_api_error', 'local_ajananova', $outcome['message']),
        'notifyproblem'
    );

} else {
    // Attach display metadata the engine doesn't set (it doesn't know about users).
    $learner    = core_user::get_user($submission->userid);
    $assignment = $DB->get_record('assign', ['id' => $submission->assignment], '*', MUST_EXIST);

    $outcome['result']['_learner_name']    = fullname($learner);
    $outcome['result']['_module_title']    = $assignment->name;
    $outcome['result']['_submission_date'] = date('Y-m-d', $submission->timemodified);

    // Look up the annotated PDF stored by the marking engine, if any.
    $outcome['result']['_annotated_pdf_url'] = null;
    $pdfrows = $DB->get_records_sql(
        'SELECT r.annotated_pdf_fileid
           FROM {ajananova_marking_results} r
          WHERE r.submission_id = :sid
            AND r.annotated_pdf_fileid IS NOT NULL
          ORDER BY r.timecreated DESC',
        ['sid' => $submissionid],
        0, 1
    );
    $pdfrow = $pdfrows ? reset($pdfrows) : null;
    if ($pdfrow) {
        $filerecord = $DB->get_record('files', ['id' => $pdfrow->annotated_pdf_fileid]);
        if ($filerecord) {
            $outcome['result']['_annotated_pdf_url'] = \moodle_url::make_pluginfile_url(
                $filerecord->contextid,
                $filerecord->component,
                $filerecord->filearea,
                $filerecord->itemid,
                $filerecord->filepath,
                $filerecord->filename
            )->out(false);
        }
    }

    $signoffurl = new moodle_url('/local/ajananova/mark.php', [
        'submissionid' => $submissionid,
        'action'       => 'signoff',
    ]);

    $renderable = new marking_review(
        $outcome['result'],
        $submissionid,
        $signoffurl->out(false)
    );

    echo $OUTPUT->render_from_template(
        'local_ajananova/marking_review',
        $renderable->export_for_template($OUTPUT)
    );
}

echo $OUTPUT->footer();
