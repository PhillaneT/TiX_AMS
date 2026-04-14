<?php
// This file is part of the AjanaNova Grader local plugin for Moodle.
//
// lib.php is the first file Moodle loads for any local plugin.
// It must exist even if all logic lives in classes/.
//
// Functions here follow the Moodle callback naming convention:
//   local_ajananova_<hook_name>

defined('MOODLE_INTERNAL') || die();

/**
 * Adds AjanaNova navigation nodes to the Moodle navigation tree.
 *
 * @param  \navigation_node $nav  The global navigation node.
 * @return void
 */
function local_ajananova_extend_navigation(\navigation_node $nav): void {
    // Navigation extension is handled per-context in extend_settings_navigation.
}

/**
 * Injects two AjanaNova links into the assignment gear (⚙) menu:
 *   • "AjanaNova: Upload marking guide" — memo.php
 *   • "AjanaNova: Mark with AI"        — mark.php (only shown if a submission
 *                                         context is available)
 *
 * Both links are shown only to users with mod/assign:grade in the current
 * context, and only on assignment activities.
 *
 * @param  \settings_navigation $settingsnav
 * @param  \context             $context
 * @return void
 */
function local_ajananova_extend_settings_navigation(
    \settings_navigation $settingsnav,
    \context $context
): void {
    global $PAGE, $DB;

    // Only inject on assignment module pages.
    if (!$PAGE->cm || $PAGE->cm->modname !== 'assign') {
        return;
    }

    if (!has_capability('mod/assign:grade', $context)) {
        return;
    }

    $cmid = $PAGE->cm->id;

    // Resolve submission from userid in URL (present on grader view).
    $gradeduserid = optional_param('userid', 0, PARAM_INT);
    $markurl      = null;
    if ($gradeduserid) {
        $submissions = $DB->get_records(
            'assign_submission',
            ['assignment' => $PAGE->cm->instance, 'userid' => $gradeduserid],
            'attemptnumber DESC',
            'id',
            0, 1
        );
        $sub = $submissions ? reset($submissions) : null;
        if ($sub) {
            $markurl = new \moodle_url('/local/ajananova/mark.php', ['submissionid' => $sub->id]);
        }
    }
    if (!$markurl) {
        $markurl = new \moodle_url('/local/ajananova/mark.php', [
            'submissionid' => 0,
            'cmid'         => $cmid,
        ]);
    }

    // -------------------------------------------------------------------------
    // Gear menu links — visible where settings nav shows.
    // The floating button is handled by hook_listener::before_footer() via
    // db/hooks.php — more reliable across all grading sub-pages.
    // -------------------------------------------------------------------------
    $assignnode = $settingsnav->find('modulesettings', \navigation_node::TYPE_SETTING);
    if (!$assignnode) {
        return;
    }

    // Link 1 — Upload marking guide / memo.
    $memourl = new \moodle_url('/local/ajananova/memo.php', ['cmid' => $cmid]);
    $assignnode->add(
        get_string('nav_upload_memo', 'local_ajananova'),
        $memourl,
        \navigation_node::TYPE_SETTING,
        null,
        'ajananova_memo',
        new \pix_icon('i/settings', '')
    );

    // Link 2 — Mark with AI (gear menu entry).
    $assignnode->add(
        get_string('nav_mark_with_ai', 'local_ajananova'),
        $markurl,
        \navigation_node::TYPE_SETTING,
        null,
        'ajananova_mark',
        new \pix_icon('i/grade_partiallycorrect', '')
    );
}
