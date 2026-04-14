<?php
// This file is part of the AjanaNova Grader local plugin for Moodle.
//
// memo.php — per-assignment marking guide / memo upload page.
//
// Linked from the assignment gear menu (⚙) for users with mod/assign:grade.
// Assessors upload one PDF per assignment; all learners in that assignment
// are marked against the same memo.
//
// Parameters:
//   cmid  int  required — course module id of the assignment

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

// -------------------------------------------------------------------------
// Moodle form — defined here so memo.php is self-contained (one file upload)
// -------------------------------------------------------------------------
class local_ajananova_memo_form extends moodleform {

    public function definition() {
        $mform = $this->_form;
        $cmid  = $this->_customdata['cmid'];

        $mform->addElement('hidden', 'cmid', $cmid);
        $mform->setType('cmid', PARAM_INT);

        $mform->addElement('header', 'ajananova_memo_header',
            get_string('memo_page_title', 'local_ajananova'));

        // --- Option A: paste marking criteria as plain text (preferred, always works) ---
        $mform->addElement('textarea', 'ajananova_memo_text',
            'Marking criteria text (paste here — recommended)',
            ['rows' => 18, 'cols' => 80, 'style' => 'width:100%;font-family:monospace;font-size:12px;']
        );
        $mform->setType('ajananova_memo_text', PARAM_RAW_TRIMMED);
        $mform->addElement('static', 'ajananova_memo_text_note', '',
            '<span class="text-success fw-bold">&#10003; Recommended for demo and production use.</span> '
            . 'Paste the marking criteria/memo text here. This takes priority over a PDF upload and '
            . 'is always readable by the AI — no PDF parsing needed.');

        // --- Option B: upload a PDF (fallback if text is left blank) ---
        $mform->addElement('filemanager', 'ajananova_memo',
            get_string('marking_guide_upload', 'local_ajananova'), null,
            ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['.pdf']]
        );
        $mform->addHelpButton('ajananova_memo', 'marking_guide_upload', 'local_ajananova');

        $mform->addElement('static', 'ajananova_memo_note', '',
            get_string('marking_guide_upload_note', 'local_ajananova'));

        $this->add_action_buttons(true, get_string('memo_save_button', 'local_ajananova'));
    }
}

// -------------------------------------------------------------------------
// Parameter + context setup
// -------------------------------------------------------------------------
$cmid = required_param('cmid', PARAM_INT);

$cm      = get_coursemodule_from_id('assign', $cmid, 0, false, MUST_EXIST);
$course  = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, false, $cm);
require_capability('mod/assign:grade', $context);

$PAGE->set_url(new moodle_url('/local/ajananova/memo.php', ['cmid' => $cmid]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('memo_page_title', 'local_ajananova'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add($cm->name,
    new moodle_url('/mod/assign/view.php', ['id' => $cmid]));
$PAGE->navbar->add(get_string('memo_page_title', 'local_ajananova'));

$assignid = (int) $cm->instance;

// -------------------------------------------------------------------------
// Prepare draft file area (pre-populate if a memo already exists)
// -------------------------------------------------------------------------
$draftitemid = file_get_submitted_draft_itemid('ajananova_memo');
file_prepare_draft_area(
    $draftitemid,
    $context->id,
    'local_ajananova',
    'memo',
    $assignid,
    ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['.pdf']]
);

// -------------------------------------------------------------------------
// Build and process form
// -------------------------------------------------------------------------
$mform = new local_ajananova_memo_form(
    new moodle_url('/local/ajananova/memo.php', ['cmid' => $cmid]),
    ['cmid' => $cmid]
);
// Pre-populate the text field with any previously saved memo text.
$savedmemotext = (string) get_config('local_ajananova', 'memo_text_' . $assignid);

$mform->set_data([
    'ajananova_memo'      => $draftitemid,
    'ajananova_memo_text' => $savedmemotext,
]);

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/mod/assign/view.php', ['id' => $cmid]));

} else if ($data = $mform->get_data()) {
    // Save the PDF (even if blank — clears any old file).
    file_save_draft_area_files(
        $data->ajananova_memo,
        $context->id,
        'local_ajananova',
        'memo',
        $assignid,
        ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['.pdf']]
    );

    // Save the memo text (may be empty — that's fine, engine falls back to PDF).
    set_config('memo_text_' . $assignid, $data->ajananova_memo_text ?? '', 'local_ajananova');

    redirect(
        new moodle_url('/mod/assign/view.php', ['id' => $cmid, 'action' => 'grading']),
        get_string('memo_saved', 'local_ajananova'),
        3,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// -------------------------------------------------------------------------
// Check if rubric/marking guide is configured (info banner)
// -------------------------------------------------------------------------
$hasadvancedgrading = false;
$gradingmethod      = '';
$gradingarea = $DB->get_record('grading_areas', [
    'contextid' => $context->id,
    'component' => 'mod_assign',
    'areaname'  => 'submissions',
]);
if ($gradingarea && !empty($gradingarea->activemethod)) {
    $hasadvancedgrading = true;
    $gradingmethod      = $gradingarea->activemethod;
}

// -------------------------------------------------------------------------
// Render
// -------------------------------------------------------------------------
echo $OUTPUT->header();

if ($hasadvancedgrading) {
    echo $OUTPUT->notification(
        get_string('memo_rubric_active_desc', 'local_ajananova',
            ucfirst($gradingmethod)),
        \core\output\notification::NOTIFY_SUCCESS
    );
} else {
    echo $OUTPUT->notification(
        get_string('memo_no_rubric', 'local_ajananova'),
        \core\output\notification::NOTIFY_INFO
    );
}

$mform->display();
echo $OUTPUT->footer();
