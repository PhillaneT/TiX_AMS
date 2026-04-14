<?php
// This file is part of the AjanaNova Grader local plugin for Moodle.

namespace local_ajananova;

defined('MOODLE_INTERNAL') || die();

/**
 * Hook listener for Moodle 4.3+ output hooks.
 */
class hook_listener {

    /**
     * Inject a floating "Mark with AI" button before the page footer.
     *
     * Fires on every full page render. Checks:
     *  - We are on a mod_assign page
     *  - The current user has mod/assign:grade
     *  - A userid is present in the URL (assessor has opened a specific learner)
     *  - That learner has a submission for this assignment
     *
     * The button is rendered as plain HTML — no JS dependency, visible
     * regardless of which grading sub-view (grader, annotate PDF, etc.) is active.
     *
     * @param \core\hook\output\before_footer_html_generation $hook
     */
    public static function before_footer(
        \core\hook\output\before_footer_html_generation $hook
    ): void {
        global $PAGE, $DB;

        // Only on assignment module pages.
        if (!$PAGE->cm || $PAGE->cm->modname !== 'assign') {
            return;
        }

        // Only for users who can grade.
        $context = \context_module::instance($PAGE->cm->id);
        if (!has_capability('mod/assign:grade', $context)) {
            return;
        }

        // Only when a specific learner's page is open (userid in URL).
        $gradeduserid = optional_param('userid', 0, PARAM_INT);
        if (!$gradeduserid) {
            return;
        }

        // Look up the learner's most recent submission.
        $submissions = $DB->get_records(
            'assign_submission',
            ['assignment' => $PAGE->cm->instance, 'userid' => $gradeduserid],
            'attemptnumber DESC',
            'id',
            0, 1
        );
        $sub = $submissions ? reset($submissions) : null;
        if (!$sub) {
            return;
        }

        $markurl = (new \moodle_url(
            '/local/ajananova/mark.php',
            ['submissionid' => $sub->id]
        ))->out(false);

        $label = get_string('nav_mark_with_ai', 'local_ajananova');

        $hook->add_html(
            '<div id="ajananova-float-btn-wrap" style="' .
                'position:fixed;bottom:28px;right:28px;z-index:99999;">' .
            '<a href="' . s($markurl) . '" ' .
               'id="ajananova-float-btn" ' .
               'style="' .
                   'display:inline-block;' .
                   'background:#1a3c6e;' .
                   'color:#ffffff;' .
                   'padding:14px 24px;' .
                   'border-radius:8px;' .
                   'text-decoration:none;' .
                   'font-weight:bold;' .
                   'font-size:14px;' .
                   'box-shadow:0 4px 18px rgba(0,0,0,0.40);' .
                   'border:2px solid rgba(255,255,255,0.20);">' .
               '&#129302;&nbsp;&nbsp;' . s($label) .
            '</a></div>'
        );
    }
}
