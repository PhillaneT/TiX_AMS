<?php
// This file is part of the ZEAL local plugin for Moodle.

namespace local_ajananova\ai;

defined('MOODLE_INTERNAL') || die();

/**
 * Mock AI client — returns a hardcoded response for end-to-end testing.
 *
 * Enabled when local_ajananova | mock_mode = 1.
 * No HTTP calls are made; no credits are consumed; no billing events are logged.
 * A visible banner is shown to the assessor so mock results are never confused
 * with real AI output.
 */
class mock_client {

    /**
     * Returns the mock marking response regardless of the prompts supplied.
     *
     * The method signature deliberately mirrors anthropic_client::mark() so
     * marking_engine can swap clients without any other changes.
     *
     * @param  string $systemprompt  Ignored in mock mode.
     * @param  string $usermessage   Ignored in mock mode.
     * @return array  Structured marking result including mock flag and token stubs.
     */
    public function mark(string $systemprompt, string $usermessage): array {
        return [
            'overall_recommendation'   => 'NOT_YET_COMPETENT',
            'confidence'               => 'HIGH',
            'mock'                     => true,
            'questions' => [
                [
                    'question_number'        => '1',
                    'question_ref'           => 'Define the concept of skills development',
                    'learner_answer_summary' => 'Learner provided a partial definition...',
                    'verdict'                => 'COMPETENT',
                    'marks_awarded'          => 8,
                    'marks_available'        => 10,
                    'ai_comment'             => 'Good understanding shown. Missing reference to NQF alignment.',
                    'assessor_flag'          => false,
                    'flag_reason'            => null,
                ],
                [
                    'question_number'        => '2',
                    'question_ref'           => 'Explain the role of a SETA',
                    'learner_answer_summary' => 'Learner did not address quality assurance function...',
                    'verdict'                => 'NOT_YET_COMPETENT',
                    'marks_awarded'          => 3,
                    'marks_available'        => 10,
                    'ai_comment'             => 'Answer incomplete. SETA quality assurance role not addressed.',
                    'assessor_flag'          => false,
                    'flag_reason'            => null,
                ],
                [
                    'question_number'        => '3',
                    'question_ref'           => 'Describe the POE compilation process',
                    'learner_answer_summary' => 'Response is ambiguous — could be interpreted multiple ways',
                    'verdict'                => 'FLAGGED',
                    'marks_awarded'          => 0,
                    'marks_available'        => 10,
                    'ai_comment'             => 'Answer requires assessor interpretation.',
                    'assessor_flag'          => true,
                    'flag_reason'            => 'Ambiguous response — assessor must make final call',
                ],
            ],
            'moderation_notes'          => 'Mock response: 1 flagged item requires assessor review.',
            'assessor_override_required' => true,

            // Billing stubs — zero so usage_logger can handle mock and real the same way.
            '_tokens_input'  => 0,
            '_tokens_output' => 0,
            '_api_id'        => 'mock',
        ];
    }
}
