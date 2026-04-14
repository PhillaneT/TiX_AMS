<?php
// This file is part of the ZEAL local plugin for Moodle.

namespace local_ajananova\billing;

defined('MOODLE_INTERNAL') || die();

/**
 * Logs billable usage events to the local DB and phones home to the
 * ZEAL Central Platform.
 *
 * Only called for real (non-mock) AI marking events.
 * The local insert is always attempted first.  The central platform call is
 * best-effort: a failure is logged to the Moodle error log but does NOT roll
 * back the local record or block the assessor workflow.  A scheduled task
 * (Phase 2) will re-sync any unsynced rows.
 */
class usage_logger {

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Records a billable event.
     *
     * @param  array $data {
     *     @type string $event_type     'ai_mark' | 'poe_generate'
     *     @type int    $assessor_id
     *     @type int    $learner_id
     *     @type int    $assignment_id
     *     @type int    $tokens_input
     *     @type int    $tokens_output
     *     @type string $api_id         Anthropic response ID
     * }
     */
    public function log(array $data): void {
        $usageid = $this->insert_local($data);
        $this->phone_home($usageid, $data);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Writes to ajananova_ai_usage and returns the new row id.
     */
    private function insert_local(array $data): int {
        global $DB;

        $licencekey = (string) get_config('local_ajananova', 'licence_key');
        $cost       = max(1, (int) get_config('local_ajananova', 'ai_mark_cost_credits'));

        $record = (object) [
            'licence_key'     => $licencekey,
            'sdp_id'          => 0,
            'assessor_id'     => (int) ($data['assessor_id']   ?? 0),
            'learner_id'      => (int) ($data['learner_id']    ?? 0),
            'assignment_id'   => (int) ($data['assignment_id'] ?? 0),
            'tokens_input'    => (int) ($data['tokens_input']  ?? 0),
            'tokens_output'   => (int) ($data['tokens_output'] ?? 0),
            'credits_charged' => $cost,
            'mock_mode'       => 0,
            'status'          => 'success',
            'api_response_id' => (string) ($data['api_id'] ?? ''),
            'timecreated'     => time(),
        ];

        return $DB->insert_record('ajananova_ai_usage', $record);
    }

    /**
     * Posts the event to the ZEAL Central Platform.
     *
     * On success, syncs the credit balance returned by the platform back to
     * the local ajananova_client_credits record.
     *
     * On failure, logs to the Moodle error log only — does not throw.
     */
    private function phone_home(int $usageid, array $data): void {
        $centralurl = rtrim((string) get_config('local_ajananova', 'central_platform_url'), '/');
        $licencekey = (string) get_config('local_ajananova', 'licence_key');

        if (empty($centralurl) || empty($licencekey)) {
            // Central platform not configured yet — skip silently.
            return;
        }

        $payload = json_encode([
            'licence_key'    => $licencekey,
            'event_type'     => $data['event_type']  ?? 'ai_mark',
            'domain'         => (string) (new \moodle_url('/'))->out(false),
            'learner_id'     => (string) ($data['learner_id']    ?? ''),
            'assignment_id'  => (string) ($data['assignment_id'] ?? ''),
            'tokens_input'   => (int)    ($data['tokens_input']  ?? 0),
            'tokens_output'  => (int)    ($data['tokens_output'] ?? 0),
            'timestamp'      => time(),
            'signature'      => $this->sign($licencekey, $data),
        ]);

        $curl = new \curl();
        $curl->setHeader([
            'content-type: application/json',
            'accept: application/json',
        ]);

        $response = $curl->post($centralurl . '/api/v1/usage', $payload);
        $httpcode = $curl->get_info()['http_code'] ?? 0;

        if ($httpcode !== 200) {
            // Log and continue — a re-sync task will pick this up later.
            debugging(
                'local_ajananova: central platform usage POST failed (HTTP ' . $httpcode . ')',
                DEBUG_DEVELOPER
            );
            return;
        }

        $result = json_decode($response, true);

        if (isset($result['credits_remaining'])) {
            $creditmgr = new credit_manager();
            $creditmgr->sync_from_central((int) $result['credits_remaining']);
        }
    }

    /**
     * Produces an HMAC-SHA256 signature of the licence key + timestamp.
     *
     * The central platform verifies this to reject spoofed events.
     * The shared secret is the licence key itself (sufficient for V1).
     */
    private function sign(string $licencekey, array $data): string {
        $message = implode('|', [
            $licencekey,
            $data['event_type']  ?? '',
            $data['learner_id']  ?? '',
            $data['assignment_id'] ?? '',
            time(),
        ]);
        return hash_hmac('sha256', $message, $licencekey);
    }
}
