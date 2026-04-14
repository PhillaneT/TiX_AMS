<?php
// This file is part of the ZEAL local plugin for Moodle.

namespace local_ajananova\ai;

defined('MOODLE_INTERNAL') || die();

/**
 * Thin wrapper around the Anthropic Messages API.
 *
 * Responsibilities:
 *  - POST the system prompt + user message to /v1/messages
 *  - Parse Claude's JSON text response into a PHP array
 *  - Attach token counts and response ID for billing
 *
 * This class is only instantiated when mock_mode = 0.
 * All network I/O goes through Moodle's curl wrapper so proxy settings,
 * timeouts and SSL certificates are respected automatically.
 */
class anthropic_client {

    private const API_URL      = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION  = '2023-06-01';
    private const MODEL        = 'claude-sonnet-4-20250514';
    private const MAX_TOKENS   = 4096;

    /** @var string Anthropic secret key from plugin settings. */
    private string $apikey;

    public function __construct() {
        $this->apikey = (string) get_config('local_ajananova', 'anthropic_api_key');

        if (empty($this->apikey)) {
            throw new \moodle_exception('ajananova_no_api_key', 'local_ajananova');
        }
    }

    /**
     * Sends the prompts to Claude and returns the parsed marking result.
     *
     * Returned array always includes billing metadata keys:
     *   _tokens_input  int
     *   _tokens_output int
     *   _api_id        string
     *
     * @param  string $systemprompt  Built by prompt_builder::build_system_prompt().
     * @param  string $usermessage   Built by prompt_builder::build_user_message().
     * @return array  Parsed AI marking result.
     * @throws \moodle_exception  On HTTP error or unparseable response.
     */
    public function mark(string $systemprompt, string $usermessage): array {

        $payload = json_encode([
            'model'       => self::MODEL,
            'max_tokens'  => self::MAX_TOKENS,
            'temperature' => 0,
            'system'      => $systemprompt,
            'messages'    => [
                ['role' => 'user', 'content' => $usermessage],
                // Pre-fill the assistant turn with '{' to force JSON-only output.
                ['role' => 'assistant', 'content' => '{'],
            ],
        ]);

        $curl = new \curl();
        $curl->setHeader([
            'x-api-key: ' . $this->apikey,
            'anthropic-version: ' . self::API_VERSION,
            'content-type: application/json',
        ]);

        $response = $curl->post(self::API_URL, $payload);
        $httpcode = $curl->get_info()['http_code'] ?? 0;

        if ($httpcode !== 200) {
            throw new \moodle_exception(
                'ajananova_api_error',
                'local_ajananova',
                '',
                'HTTP ' . $httpcode . ': ' . $response
            );
        }

        $data = json_decode($response, true);

        // Claude returns the JSON inside content[0].text.
        // We prefilled the assistant turn with '{' so prepend it back.
        // Also strip any markdown code fences just in case.
        $rawtext = '{' . ($data['content'][0]['text'] ?? '');
        $rawtext = preg_replace('/^```(?:json)?\s*/i', '', trim($rawtext));
        $rawtext = preg_replace('/\s*```$/', '', $rawtext);
        $result  = json_decode(trim($rawtext), true);

        if (!is_array($result)) {
            throw new \moodle_exception(
                'ajananova_parse_error',
                'local_ajananova',
                '',
                'Could not parse AI response as JSON: ' . substr($rawtext, 0, 200)
            );
        }

        // Attach billing metadata so marking_engine + usage_logger can consume
        // them without re-calling the API.
        $result['_tokens_input']  = (int) ($data['usage']['input_tokens']  ?? 0);
        $result['_tokens_output'] = (int) ($data['usage']['output_tokens'] ?? 0);
        $result['_api_id']        = (string) ($data['id'] ?? '');

        return $result;
    }
}
