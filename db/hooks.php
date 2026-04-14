<?php
// This file is part of the AjanaNova Grader local plugin for Moodle.
//
// Registers hook listeners for Moodle 4.3+ hook system.
// The before_footer_html_generation hook fires on every full page render,
// making it the most reliable place to inject the floating Mark with AI button.

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook'     => \core\hook\output\before_footer_html_generation::class,
        'callback' => [\local_ajananova\hook_listener::class, 'before_footer'],
        'priority' => 100,
    ],
];
