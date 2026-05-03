<?php
// This file is part of the ZEAL local plugin for Moodle.
// Settings are stored under the 'local_ajananova' component.

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {

    $settings = new admin_settingpage('local_ajananova', get_string('pluginname', 'local_ajananova'));

    // -------------------------------------------------------------------------
    // Mock / Test mode
    // -------------------------------------------------------------------------
    $settings->add(new admin_setting_configcheckbox(
        'local_ajananova/mock_mode',
        get_string('setting_mock_mode', 'local_ajananova'),
        get_string('setting_mock_mode_desc', 'local_ajananova'),
        1   // default ON — safe until real API key is entered
    ));

    // -------------------------------------------------------------------------
    // Anthropic API key
    // -------------------------------------------------------------------------
    $settings->add(new admin_setting_configpasswordunmask(
        'local_ajananova/anthropic_api_key',
        get_string('setting_anthropic_api_key', 'local_ajananova'),
        get_string('setting_anthropic_api_key_desc', 'local_ajananova'),
        ''
    ));

    // -------------------------------------------------------------------------
    // ZEAL Central Platform URL
    // -------------------------------------------------------------------------
    $settings->add(new admin_setting_configtext(
        'local_ajananova/central_platform_url',
        get_string('setting_central_platform_url', 'local_ajananova'),
        get_string('setting_central_platform_url_desc', 'local_ajananova'),
        'https://platform.ajananova.co.za',
        PARAM_URL
    ));

    // -------------------------------------------------------------------------
    // Licence key for this Moodle instance
    // -------------------------------------------------------------------------
    $settings->add(new admin_setting_configtext(
        'local_ajananova/licence_key',
        get_string('setting_licence_key', 'local_ajananova'),
        get_string('setting_licence_key_desc', 'local_ajananova'),
        '',
        PARAM_ALPHANUM
    ));

    // -------------------------------------------------------------------------
    // Credits consumed per AI marking event
    // -------------------------------------------------------------------------
    $settings->add(new admin_setting_configtext(
        'local_ajananova/ai_mark_cost_credits',
        get_string('setting_ai_mark_cost_credits', 'local_ajananova'),
        get_string('setting_ai_mark_cost_credits_desc', 'local_ajananova'),
        1,
        PARAM_INT
    ));

    // -------------------------------------------------------------------------
    // Credits consumed per POE export
    // -------------------------------------------------------------------------
    $settings->add(new admin_setting_configtext(
        'local_ajananova/poe_cost_credits',
        get_string('setting_poe_cost_credits', 'local_ajananova'),
        get_string('setting_poe_cost_credits_desc', 'local_ajananova'),
        1,
        PARAM_INT
    ));

    $ADMIN->add('localplugins', $settings);
}
