<?php
// This file is part of the ZEAL local plugin for Moodle.

namespace local_ajananova\output;

defined('MOODLE_INTERNAL') || die();

use local_ajananova\billing\credit_manager;

/**
 * Builds the template context for marking_review.mustache.
 *
 * Implements renderable + templatable so it can be passed directly to
 * $OUTPUT->render_from_template() in mark.php.
 */
class marking_review implements \renderable, \templatable {

    /** @var array  The raw result array from marking_engine::run(). */
    private array $result;

    /** @var int  Moodle assign_submission.id */
    private int $submissionid;

    /** @var string  URL of the sign-off handler (mark.php?action=signoff). */
    private string $signoffurl;

    public function __construct(array $result, int $submissionid, string $signoffurl) {
        $this->result       = $result;
        $this->submissionid = $submissionid;
        $this->signoffurl   = $signoffurl;
    }

    /**
     * Returns the context array consumed by marking_review.mustache.
     *
     * @param  \renderer_base $output  Unused but required by the interface.
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {
        global $USER;

        $result   = $this->result;
        $mockmode = (bool) get_config('local_ajananova', 'mock_mode');

        $creditmgr = new credit_manager();
        $balance   = $creditmgr->get_balance();

        $questions = $this->build_questions($result['questions'] ?? []);

        $totalaawarded   = array_sum(array_column($result['questions'] ?? [], 'marks_awarded'));
        $totalavailable  = array_sum(array_column($result['questions'] ?? [], 'marks_available'));
        $scorepercentage = $totalavailable > 0
            ? round(($totalaawarded / $totalavailable) * 100, 1)
            : 0;

        $overall    = $result['overall_recommendation'] ?? '';
        $confidence = $result['confidence'] ?? '';

        $confidencebadge = match($confidence) {
            'HIGH'   => 'badge bg-success text-white',
            'MEDIUM' => 'badge bg-warning text-dark',
            'LOW'    => 'badge bg-danger text-white',
            default  => 'badge bg-secondary text-white',
        };

        return [
            'mock_mode'              => $mockmode,
            'learner_name'           => $result['_learner_name']    ?? '',
            'module_title'           => $result['_module_title']    ?? '',
            'submission_date'        => $result['_submission_date'] ?? '',
            'overall_recommendation' => $overall,
            'overall_is_competent'   => $overall === 'COMPETENT',
            'overall_is_nyc'         => $overall === 'NOT_YET_COMPETENT',
            'overall_is_review'      => $overall === 'ASSESSOR_REVIEW_REQUIRED',
            'confidence'             => $confidence,
            'confidence_badge_class' => $confidencebadge,
            'total_awarded'          => $totalaawarded,
            'total_available'        => $totalavailable,
            'score_percentage'       => $scorepercentage,
            'questions'              => $questions,
            'annotated_pdf_url'      => $result['_annotated_pdf_url'] ?? null,
            'from_cache'             => !empty($result['_from_cache']),
            'rerun_url'              => (new \moodle_url('/local/ajananova/mark.php', [
                                            'submissionid' => $this->submissionid,
                                            'force'        => 1,
                                        ]))->out(false),
            'submissionid'           => $this->submissionid,
            'sign_off_url'           => $this->signoffurl,
            'sesskey'                => sesskey(),
            'credits_remaining'      => (int) $balance->credits_remaining,
            'assessor_name'          => fullname($USER),
        ];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Adds Mustache-friendly boolean helpers to each question array.
     *
     * Mustache has no comparison operators, so we pre-compute is_competent,
     * is_nyc, and is_flagged for each question here in PHP.
     */
    private function build_questions(array $questions): array {
        return array_map(function (array $q): array {
            $verdict = $q['verdict'] ?? '';
            return $q + [
                'is_competent' => $verdict === 'COMPETENT',
                'is_nyc'       => $verdict === 'NOT_YET_COMPETENT' || $verdict === 'PARTIAL',
                'is_flagged'   => $verdict === 'FLAGGED',
            ];
        }, $questions);
    }
}
