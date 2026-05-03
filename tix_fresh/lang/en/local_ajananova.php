<?php
// This file is part of the AjanaNova Grader local plugin for Moodle.
//
// All user-facing strings for the English locale.
// Keys must match every get_string() / {{#str}} call in the plugin.

defined('MOODLE_INTERNAL') || die();

// -------------------------------------------------------------------------
// Plugin identity
// -------------------------------------------------------------------------
$string['pluginname'] = 'AjanaNova Grader';

// -------------------------------------------------------------------------
// Admin settings (settings.php)
// -------------------------------------------------------------------------
$string['setting_mock_mode']                  = 'Mock / test mode';
$string['setting_mock_mode_desc']             = 'When enabled, AI marking returns a simulated response. No API calls are made and no credits are consumed. Turn this OFF only when you are ready to use the real Anthropic API.';

$string['setting_anthropic_api_key']          = 'Anthropic API key';
$string['setting_anthropic_api_key_desc']     = 'Your secret Anthropic API key. Stored encrypted. Leave blank while testing in mock mode.';

$string['setting_central_platform_url']       = 'AjanaNova Central Platform URL';
$string['setting_central_platform_url_desc']  = 'Base URL of the AjanaNova Central Platform. Usage events are posted here.';

$string['setting_licence_key']                = 'Licence key';
$string['setting_licence_key_desc']           = 'The licence key issued to this Moodle instance by AjanaNova. Used to identify the client on the central platform.';

$string['setting_ai_mark_cost_credits']       = 'Credits per AI marking event';
$string['setting_ai_mark_cost_credits_desc']  = 'Number of credits deducted each time an assessor triggers AI marking. Default: 1.';

$string['setting_poe_cost_credits']           = 'Credits per POE export';
$string['setting_poe_cost_credits_desc']      = 'Number of credits deducted each time a POE is generated. Default: 1.';

// -------------------------------------------------------------------------
// Marking review UI (marking_review.mustache)
// -------------------------------------------------------------------------
$string['ai_marking_results']       = 'AI Marking Results';
$string['ai_recommendation']        = 'AI Recommendation';

// Confidence badge CSS classes (used as dynamic string keys in mustache)
$string['confidence_badge_class_HIGH']   = 'badge-success';
$string['confidence_badge_class_MEDIUM'] = 'badge-warning';
$string['confidence_badge_class_LOW']    = 'badge-danger';

// Table column headers
$string['col_q']          = 'Q';
$string['col_question']   = 'Question';
$string['col_verdict']    = 'Verdict';
$string['col_marks']      = 'Marks';
$string['col_ai_comment'] = 'AI Comment';
$string['col_override']   = 'Assessor Override';

// Verdict labels
$string['competent']         = 'Competent';
$string['not_yet_competent'] = 'Not Yet Competent';
$string['flagged']           = 'Flagged — Assessor Review';
$string['no_override']       = '— No override —';

// Sign-off form
$string['final_decision']        = 'Final decision';
$string['assessor_name']         = 'Assessor name';
$string['sign_off_and_download'] = 'Sign off &amp; download annotated PDF';
$string['sign_off_confirm']      = 'Confirm sign-off &amp; save';
$string['credits_remaining']     = 'Credits remaining';

// -------------------------------------------------------------------------
// Status / error messages
// -------------------------------------------------------------------------
$string['ajananova_no_api_key']    = 'No Anthropic API key is configured. Please add your API key in Site administration → AjanaNova Grader settings.';
$string['ajananova_api_error']     = 'The Anthropic API returned an error: {$a}';
$string['ajananova_parse_error']   = 'Could not parse the AI response as JSON: {$a}';
$string['no_credits_heading']      = 'AI credits exhausted';
$string['no_credits_body']         = 'This client has no AI marking credits remaining. Manual marking is still available. Contact AjanaNova to top up your credits.';
$string['continue_manual_grading'] = 'Continue with manual grading';
$string['signoff_saved']           = 'Sign-off saved. Returning to grading page.';
$string['signoff_verdict_required'] = 'Please select a final verdict (Competent or Not Yet Competent) before signing off.';

// -------------------------------------------------------------------------
// Gear menu navigation links
// -------------------------------------------------------------------------
$string['nav_upload_memo']  = 'AjanaNova: Upload marking guide';
$string['nav_mark_with_ai'] = 'AjanaNova: Mark with AI';

// -------------------------------------------------------------------------
// Memo upload page (memo.php)
// -------------------------------------------------------------------------
$string['memo_page_title']         = 'AjanaNova — Marking Guide';
$string['marking_guide_text']      = 'Marking criteria text (paste here — recommended)';
$string['marking_guide_upload']    = 'Marking guide / memo (PDF)';
$string['marking_guide_upload_help'] = 'Upload the marking guide or memo that the AI should mark against. Leave blank if this assignment already has a Moodle rubric or marking guide configured — those are used automatically and take priority.';
$string['marking_guide_upload_note'] = 'Only PDF files accepted. One file per assignment. Leave blank if a Moodle rubric or marking guide is configured — it will be used automatically.';
$string['memo_save_button']        = 'Save marking guide';
$string['memo_saved']              = 'Marking guide saved successfully.';
$string['memo_rubric_active_desc'] = 'This assignment has a Moodle {$a} configured. It will be used automatically as the marking criteria — no PDF upload is needed. You can still upload a supplementary memo below if required.';
$string['memo_no_rubric']          = 'No Moodle rubric or marking guide is configured for this assignment. Upload a marking guide PDF below, or configure a rubric/marking guide on the assignment to enable AI marking.';

// -------------------------------------------------------------------------
// Marking engine errors (real mode)
// -------------------------------------------------------------------------
$string['ajananova_no_submission_file'] = 'No submission file was found for this learner. AI marking requires a PDF submission.';
$string['ajananova_select_learner_first'] = 'No learner selected. To mark with AI: click the learner\'s name in the submissions table to open their grading page, then select "AjanaNova: Mark with AI" from the gear menu.';
$string['ajananova_no_criteria']        = 'No marking criteria found for this assignment. Please configure a Moodle rubric or marking guide, or go to the assignment gear menu → "AjanaNova: Upload marking guide" to upload a memo PDF.';

// -------------------------------------------------------------------------
// PDF / OCR errors
// -------------------------------------------------------------------------
$string['ajananova_pdf_unreadable']    = 'The PDF file could not be read: {$a}';
$string['ajananova_ocr_failed']        = 'OCR processing failed. No text could be extracted from the scanned PDF.';
$string['ajananova_poor_scan_quality'] = 'PDF scan quality is too low for AI marking. Please resubmit a clearer scan or use manual marking.';
$string['ajananova_composer_missing']  = 'Composer dependencies are not installed: {$a}';
$string['ajananova_fpdi_missing']      = 'The FPDI PDF library is missing: {$a}';
