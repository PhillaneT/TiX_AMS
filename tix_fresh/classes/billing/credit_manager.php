<?php
// This file is part of the ZEAL local plugin for Moodle.

namespace local_ajananova\billing;

defined('MOODLE_INTERNAL') || die();

/**
 * Manages the AI marking credit balance for this Moodle instance.
 *
 * Credits are stored locally in ajananova_client_credits (one row per licence key)
 * AND mirrored on the ZEAL Central Platform.  The local record is the source
 * of truth for real-time gate-keeping; the central platform is authoritative
 * for billing.
 *
 * In mock mode, marking_engine never calls this class for deduction, but
 * has_credits() is still called so assessors see an accurate balance.
 */
class credit_manager {

    /** @var string Licence key for this Moodle instance. */
    private string $licencekey;

    public function __construct() {
        $this->licencekey = (string) get_config('local_ajananova', 'licence_key');
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Returns true if at least one credit is available.
     *
     * Creates a zero-balance row on first call so the table is never empty.
     */
    public function has_credits(): bool {
        $record = $this->get_or_create_record();
        return $record->credits_remaining > 0;
    }

    /**
     * Returns the current credit balance object.
     *
     * @return \stdClass  Row from ajananova_client_credits.
     */
    public function get_balance(): \stdClass {
        return $this->get_or_create_record();
    }

    /**
     * Deducts one credit (or the configured cost) from the local balance.
     *
     * Called by marking_engine after a successful real API call.
     * Must NOT be called in mock mode.
     */
    public function deduct_credit(): void {
        global $DB;

        $cost   = max(1, (int) get_config('local_ajananova', 'ai_mark_cost_credits'));
        $record = $this->get_or_create_record();

        $record->credits_used      = $record->credits_used + $cost;
        $record->credits_remaining = max(0, $record->credits_remaining - $cost);
        $record->timeupdated       = time();

        $DB->update_record('ajananova_client_credits', $record);
    }

    /**
     * Sets the balance from a value returned by the central platform.
     *
     * Called by usage_logger after a successful phone-home so the local
     * record stays in sync with the authoritative central balance.
     *
     * @param  int $remaining  Credits remaining according to central platform.
     */
    public function sync_from_central(int $remaining): void {
        global $DB;

        $record = $this->get_or_create_record();

        $record->credits_remaining = max(0, $remaining);
        $record->timeupdated       = time();

        $DB->update_record('ajananova_client_credits', $record);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Fetches the credit record for this licence key, creating it if absent.
     */
    private function get_or_create_record(): \stdClass {
        global $DB;

        $record = $DB->get_record('ajananova_client_credits',
            ['licence_key' => $this->licencekey]);

        if (!$record) {
            $record = (object) [
                'licence_key'       => $this->licencekey,
                'credits_total'     => 0,
                'credits_used'      => 0,
                'credits_remaining' => 0,
                'reset_date'        => null,
                'timeupdated'       => time(),
            ];
            $record->id = $DB->insert_record('ajananova_client_credits', $record);
        }

        return $record;
    }
}
