<?php

namespace App\Services;

use App\Models\LmsConnection;

class MoodleService
{
    private string $baseUrl;
    private string $token;
    private int $timeout = 30;

    public function __construct(LmsConnection $connection)
    {
        $this->baseUrl = $connection->getBaseUrl();
        $this->token   = $connection->getApiToken();
    }

    /**
     * Test connectivity — fetches the site info endpoint.
     * Returns ['ok' => bool, 'error' => string|null, 'data' => array|null]
     */
    public function testConnection(): array
    {
        $result = $this->call('core_webservice_get_site_info', []);

        if (! $result['ok']) {
            return $result;
        }

        return [
            'ok'    => true,
            'error' => null,
            'data'  => [
                'sitename' => $result['data']['sitename'] ?? 'Unknown',
                'username'  => $result['data']['username'] ?? 'Unknown',
            ],
        ];
    }

    /**
     * Fetch courses the token has access to.
     * Returns array of course objects or throws on failure.
     */
    public function getCourses(): array
    {
        $userId = $this->getUserId();

        $result = $this->call('core_enrol_get_users_courses', [
            'userid' => $userId,
        ]);

        if (! $result['ok']) {
            throw new \RuntimeException(
                'Moodle course fetch failed: ' . $result['error']
            );
        }

        if (! is_array($result['data'])) {
            throw new \RuntimeException(
                'Unexpected Moodle response when fetching courses.'
            );
        }

        return $result['data'];
    }

    /**
     * Fetch assignments within specified course IDs.
     */
    public function getAssignments(array $courseIds): array
    {
        $params = [];
        foreach ($courseIds as $i => $id) {
            $params["courseids[{$i}]"] = $id;
        }

        $result = $this->call('mod_assign_get_assignments', $params);

        if (! $result['ok']) {
            throw new \RuntimeException($result['error']);
        }

        $assignments     = [];
        $warningMessages = $result['warnings'] ?? [];

        foreach ($result['data']['courses'] ?? [] as $course) {
            foreach ($course['assignments'] ?? [] as $assignment) {
                $assignment['course_id']   = $course['id'];
                $assignment['course_name'] = $course['fullname'];
                $assignments[]             = $assignment;
            }
        }

        return ['assignments' => $assignments, 'warnings' => $warningMessages];
    }

    /**
     * Fetch a Moodle user's profile by their user ID.
     * Returns ['id', 'fullname', 'firstname', 'lastname', 'email'] or throws on failure.
     */
    public function getUser(int $userId): array
    {
        $result = $this->call('core_user_get_users_by_field', [
            'field'     => 'id',
            'values[0]' => $userId,
        ]);

        if (! $result['ok']) {
            throw new \RuntimeException('Moodle user fetch failed: ' . $result['error']);
        }

        $users = $result['data'];
        if (empty($users[0])) {
            throw new \RuntimeException("Moodle user #{$userId} not found.");
        }

        return $users[0];
    }

    /**
     * Fetch submissions for a specific assignment.
     */    
    public function getSubmissions(int $assignmentId): array
    {
        $result = $this->call('mod_assign_get_submissions', [
            'assignmentids[0]' => $assignmentId,
            'status'           => 'submitted',
        ]);

        if (! $result['ok']) {
            throw new \RuntimeException('Moodle submission fetch failed: ' . $result['error']);
        }

        $submissions = $result['data']['assignments'][0]['submissions'] ?? [];

        if (! is_array($submissions)) {
            return [];
        }

        return $submissions;
    }

    /**
     * Download a submitted file and return its contents.
     */
    public function downloadFile(string $fileUrl): string|false
    {
        $separator = str_contains($fileUrl, '?') ? '&' : '?';
        $url       = $fileUrl . $separator . 'token=' . urlencode($this->token);

        return $this->curlGet($url);
    }

    /**
     * Push a grade (and optional feedback) back to Moodle.
     *
     * @param int         $assignmentId   Moodle cmid (course module id)
     * @param int         $userId         Moodle user id
     * @param float       $grade          Numeric grade (0–100 or per assignment scale)
     * @param string      $feedbackText   Plain text feedback
     * @param string|null $pdfContents    Raw PDF bytes (null to skip file attachment)
     * @param string|null $pdfFilename    Filename for the PDF attachment
     */
    public function pushGrade(
        int $assignmentId,
        int $userId,
        float $grade,
        string $feedbackText,
        ?string $pdfContents = null,
        ?string $pdfFilename = null
    ): array {
        $params = [
            'assignmentid'          => $assignmentId,
            'userid'                => $userId,
            'grade'                 => $grade,
            'attemptnumber'         => -1,
            'addattempt'            => 0,
            'workflowstate'         => 'released',
            'applytoall'            => 0,
            'plugindata[assignfeedbackcomments_editor][text]'   => $feedbackText,
            'plugindata[assignfeedbackcomments_editor][format]' => 1,
        ];

        $hadFile = false;
        if ($pdfContents !== null && $pdfFilename !== null) {
            $draftId = $this->uploadFileToDraft($pdfContents, $pdfFilename);
            if ($draftId !== null) {
                // Moodle's assignfeedback_file plugin exposes its draft area under
                // the key `files_filemanager` (NOT `assignfeedback_file_filemanager`).
                $params['plugindata[files_filemanager]'] = $draftId;
                $hadFile = true;
            }
        }

        $result      = $this->call('mod_assign_save_grade', $params);
        $filePushed  = $hadFile && $result['ok'];

        // If the call failed because the file-feedback plugin is not enabled on
        // this assignment, retry without the file attachment (grade + text still push).
        if (! $result['ok'] && $hadFile) {
            $paramsNoFile = $params;
            unset($paramsNoFile['plugindata[files_filemanager]']);
            $result      = $this->call('mod_assign_save_grade', $paramsNoFile);
            $filePushed  = false;
        }

        if (! $result['ok']) {
            return ['ok' => false, 'error' => $result['error'], 'file_pushed' => false];
        }

        return ['ok' => true, 'error' => null, 'file_pushed' => $filePushed];
    }

    // -------------------------------------------------------
    // Diagnostics
    // -------------------------------------------------------

    /**
     * Probe every Moodle wsfunction the AMS push pipeline depends on and
     * report whether the current token is authorised for it.
     *
     * Returns a map of wsfunction => [
     *   'ok'        => bool,   // call succeeded or returned an expected param error
     *   'reachable' => bool,   // token is authorised (i.e. NOT an access exception)
     *   'error'     => string|null,
     *   'note'      => mixed|null,
     * ].
     *
     * mod_assign_save_grade is intentionally NOT probed because the only safe
     * way to test it is via a real push (it writes data).
     */
    public function diagnose(): array
    {
        $checks = [];

        // 1. Connection / token sanity
        $site = $this->call('core_webservice_get_site_info', []);
        if ($site['ok']) {
            $checks['core_webservice_get_site_info'] = [
                'ok' => true, 'reachable' => true, 'error' => null,
                'note' => [
                    'sitename' => $site['data']['sitename'] ?? null,
                    'username' => $site['data']['username'] ?? null,
                    'userid'   => $site['data']['userid']   ?? null,
                ],
            ];
        } else {
            $checks['core_webservice_get_site_info'] = $this->probeReachable($site);
        }

        $userId = (int) ($site['data']['userid'] ?? 0);

        // 2. Courses for this user
        if ($userId) {
            $r = $this->call('core_enrol_get_users_courses', ['userid' => $userId]);
            $note = $r['ok'] && is_array($r['data'] ?? null)
                ? ['courses_visible' => count($r['data'])]
                : null;
            $checks['core_enrol_get_users_courses'] = $this->probeReachable($r, $note);
        } else {
            $checks['core_enrol_get_users_courses'] = [
                'ok' => false, 'reachable' => false,
                'error' => 'Skipped — could not determine token user id from get_site_info.',
            ];
        }

        // 3. Assignments (no filter — returns everything the token can see)
        $r    = $this->call('mod_assign_get_assignments', []);
        $note = null;
        if ($r['ok']) {
            $count = 0;
            foreach ($r['data']['courses'] ?? [] as $course) {
                $count += count($course['assignments'] ?? []);
            }
            $note = ['assignments_visible' => $count];
        }
        $checks['mod_assign_get_assignments'] = $this->probeReachable($r, $note);

        // 4. Submissions — probe with assignmentid=1; "invalidrecord" still means reachable
        $r = $this->call('mod_assign_get_submissions', ['assignmentids[0]' => 1]);
        $checks['mod_assign_get_submissions'] = $this->probeReachable($r);

        // 5. User lookup — probe with the token user
        $r = $this->call('core_user_get_users_by_field', [
            'field'     => 'id',
            'values[0]' => $userId ?: 1,
        ]);
        $checks['core_user_get_users_by_field'] = $this->probeReachable($r);

        // 6. Grading definitions — probe with cmid=1
        $r = $this->call('core_grading_get_definitions', [
            'cmids[0]' => 1,
            'areaname' => 'submissions',
        ]);
        $checks['core_grading_get_definitions'] = $this->probeReachable($r);

        // 7. mod_assign_save_grade — NOT probed (would write). Verified by real push.
        $checks['mod_assign_save_grade'] = [
            'ok'        => null,
            'reachable' => null,
            'error'     => null,
            'note'      => 'Not probed (writes data). Authorisation is confirmed by a real push attempt.',
        ];

        // 8. /webservice/upload.php — quick HEAD check (just confirms the endpoint exists)
        $checks['webservice/upload.php'] = $this->probeUploadEndpoint();

        return $checks;
    }

    /**
     * Pre-flight check for one assignment: confirm the Moodle-side config
     * matches what AMS needs to push back successfully.
     *
     * Returns ['warnings' => string[], 'info' => array, 'checked_at' => iso8601].
     */
    public function preflightAssignment(\App\Models\Assignment $assignment): array
    {
        $warnings = [];
        $info     = [];

        if (! $assignment->lms_assignment_id) {
            return [
                'warnings'   => ['Assignment is not linked to a Moodle assignment id — sync first.'],
                'info'       => [],
                'checked_at' => now()->toIso8601String(),
            ];
        }

        // (a) Per-assignment config — feedback plugins enabled?
        $params = [];
        if ($assignment->lms_course_id) {
            $params['courseids[0]'] = (int) $assignment->lms_course_id;
        }
        $r = $this->call('mod_assign_get_assignments', $params);

        if ($r['ok']) {
            $found = null;
            foreach ($r['data']['courses'] ?? [] as $course) {
                foreach ($course['assignments'] ?? [] as $a) {
                    if ((int) ($a['id'] ?? 0) === (int) $assignment->lms_assignment_id) {
                        $found = $a;
                        $info['course_name'] = $course['fullname'] ?? null;
                        break 2;
                    }
                }
            }

            if ($found) {
                $info['name'] = $found['name'] ?? null;
                $fileEnabled     = $this->configEnabled($found['configs'] ?? [], 'assignfeedback', 'file');
                $commentsEnabled = $this->configEnabled($found['configs'] ?? [], 'assignfeedback', 'comments');
                $info['feedback_file_enabled']     = $fileEnabled;
                $info['feedback_comments_enabled'] = $commentsEnabled;

                if (! $fileEnabled) {
                    $warnings[] = 'Feedback files plugin is OFF on this Moodle assignment — annotated PDF will not attach. '
                        . 'Fix: in Moodle, edit the assignment → Feedback types → tick "Feedback files".';
                }
                if (! $commentsEnabled) {
                    $warnings[] = 'Feedback comments plugin is OFF on this Moodle assignment — text feedback will not appear. '
                        . 'Fix: in Moodle, edit the assignment → Feedback types → tick "Feedback comments".';
                }
            } else {
                $warnings[] = 'Could not find this assignment in Moodle (id ' . $assignment->lms_assignment_id . ') — '
                    . 'it may have been deleted or your token has lost access to its course.';
            }
        } else {
            $warnings[] = 'Could not fetch assignment config from Moodle: ' . $r['error'];
        }

        // (b) Grading definition — published rubric / marking guide?
        if ($assignment->lms_cmid) {
            $r2 = $this->call('core_grading_get_definitions', [
                'cmids[0]'   => (int) $assignment->lms_cmid,
                'areaname'   => 'submissions',
                'activeonly' => 1,
            ]);

            if ($r2['ok']) {
                $areas        = $r2['data']['areas'] ?? [];
                $activeMethod = null;
                $hasPublished = false;

                foreach ($areas as $area) {
                    $activeMethod = $area['activemethod'] ?? $activeMethod;
                    foreach ($area['definitions'] ?? [] as $def) {
                        // Moodle gradingform definition status: 20 = ready (published)
                        if ((int) ($def['status'] ?? 0) === 20) {
                            $hasPublished = true;
                        }
                    }
                }

                $info['grading_method']    = $activeMethod ?: 'simple';
                $info['grading_published'] = $hasPublished;

                if ($activeMethod && in_array($activeMethod, ['rubric', 'guide'], true) && ! $hasPublished) {
                    $warnings[] = "A {$activeMethod} is configured on this assignment but is not published — "
                        . 'criterion-level scores will not save. '
                        . 'Fix: in Moodle, edit the grading form and click "Save" (not "Save as draft").';
                }
            } else {
                $info['grading_check_error'] = $r2['error'];
            }
        } else {
            $warnings[] = 'No Moodle course-module ID stored on this assignment (lms_cmid) — re-sync from Moodle to populate it.';
        }

        return [
            'warnings'   => $warnings,
            'info'       => $info,
            'checked_at' => now()->toIso8601String(),
        ];
    }

    private function configEnabled(array $configs, string $subtype, string $plugin): bool
    {
        foreach ($configs as $cfg) {
            if (($cfg['subtype'] ?? '') === $subtype
                && ($cfg['plugin']  ?? '') === $plugin
                && ($cfg['name']    ?? '') === 'enabled') {
                return ((string) ($cfg['value'] ?? '0')) === '1';
            }
        }
        return false;
    }

    private function probeReachable(array $r, $note = null): array
    {
        if ($r['ok']) {
            return ['ok' => true, 'reachable' => true, 'error' => null, 'note' => $note];
        }
        $denied = $this->isAccessDenied((string) ($r['error'] ?? ''));
        return [
            'ok'        => ! $denied, // reachable but parameter-erroring is still a pass
            'reachable' => ! $denied,
            'error'     => $r['error'],
            'note'      => $note,
        ];
    }

    private function isAccessDenied(string $msg): bool
    {
        $lower   = strtolower($msg);
        $needles = [
            'accessexception',
            'access exception',
            'access control',
            'is not authorised',
            'is not authorized',
            'webservice_access_exception',
            'wsaccessuser',
            'function not included',
        ];
        foreach ($needles as $n) {
            if (str_contains($lower, $n)) return true;
        }
        return false;
    }

    private function probeUploadEndpoint(): array
    {
        $url = $this->baseUrl . '/webservice/upload.php';
        $verifySsl = (bool) config('services.moodle.verify_ssl', true);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_NOBODY         => true,
            CURLOPT_SSL_VERIFYPEER => $verifySsl,
            CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
            CURLOPT_USERAGENT      => 'TiX-AMS/1.0',
        ]);
        curl_exec($ch);
        $status  = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            return ['ok' => false, 'reachable' => false, 'error' => 'cURL: ' . $curlErr, 'note' => null];
        }
        // Any HTTP response (even 4xx) means the endpoint exists. 5xx or 0 means trouble.
        $ok = $status >= 200 && $status < 500;
        return [
            'ok'        => $ok,
            'reachable' => $ok,
            'error'     => $ok ? null : 'HTTP ' . $status,
            'note'      => 'HTTP ' . $status,
        ];
    }

    /**
     * Fetch the rubric / marking-guide definition for a Moodle course module.
     *
     * Returns:
     *   ['ok' => true,  'criteria' => [...]]
     *   ['ok' => false, 'error'    => string]
     *
     * Each criterion: { id, title, description, levels: [{id, score, description}] }
     */
    public function getGradingDefinition(int $cmid): array
    {
        $result = $this->call('core_grading_get_definitions', [
            'cmids[0]' => $cmid,
            'includes' => 'all',
        ]);

        if (! $result['ok']) {
            return ['ok' => false, 'error' => $result['error']];
        }

        $areas = $result['data']['areas'] ?? [];
        if (empty($areas)) {
            return ['ok' => false, 'error' => 'No grading area found for this assignment in Moodle.'];
        }

        $area        = $areas[0];
        $activeMethod = $area['activemethod'] ?? '';
        $definitions = $area['definitions'] ?? [];

        if (empty($definitions)) {
            return ['ok' => false, 'error' => 'No grading definition found. The assignment may not use a rubric or marking guide.'];
        }

        $def = $definitions[0];

        // ---- Parse Rubric ----
        if ($activeMethod === 'rubric' || isset($def['rubric'])) {
            $rawCriteria = $def['rubric']['criteria'] ?? [];
            $criteria    = [];
            foreach ($rawCriteria as $rc) {
                $levels = [];
                foreach ($rc['levels'] ?? [] as $rl) {
                    $levels[] = [
                        'id'          => 'ml_' . ($rl['id'] ?? uniqid()),
                        'score'       => (float) ($rl['score'] ?? 0),
                        'description' => trim($rl['definition'] ?? ''),
                    ];
                }
                usort($levels, fn($a, $b) => $a['score'] <=> $b['score']);
                $criteria[] = [
                    'id'          => 'mc_' . ($rc['id'] ?? uniqid()),
                    'title'       => trim($rc['description'] ?? 'Criterion'),
                    'description' => trim($rc['descriptionmarkers'] ?? ''),
                    'levels'      => $levels,
                ];
            }
            return ['ok' => true, 'criteria' => $criteria];
        }

        // ---- Parse Marking Guide ----
        if ($activeMethod === 'guide' || isset($def['guide'])) {
            $rawCriteria = $def['guide']['criteria'] ?? [];
            $criteria    = [];
            foreach ($rawCriteria as $rc) {
                $maxScore = (float) ($rc['maxscore'] ?? 0);
                $criteria[] = [
                    'id'          => 'mc_' . ($rc['id'] ?? uniqid()),
                    'title'       => trim($rc['shortname'] ?? 'Criterion'),
                    'description' => trim($rc['description'] ?? ''),
                    'levels'      => [
                        ['id' => 'l0_' . ($rc['id'] ?? '0'), 'score' => 0,        'description' => 'Not demonstrated'],
                        ['id' => 'lm_' . ($rc['id'] ?? '0'), 'score' => round($maxScore / 2, 1), 'description' => 'Partially demonstrated'],
                        ['id' => 'lx_' . ($rc['id'] ?? '0'), 'score' => $maxScore, 'description' => 'Fully demonstrated'],
                    ],
                ];
            }
            return ['ok' => true, 'criteria' => $criteria];
        }

        return ['ok' => false, 'error' => "Unsupported grading method \"{$activeMethod}\". Only rubric and marking guide are supported."];
    }

    // -------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------

    private function getUserId(): int
    {
        $result = $this->call('core_webservice_get_site_info', []);

        if (! $result['ok']) {
            throw new \RuntimeException($result['error']);
        }

        return (int) ($result['data']['userid'] ?? 0);
    }

    /**
     * Upload a file to the Moodle user draft area and return the draft item ID.
     */
    private function uploadFileToDraft(string $contents, string $filename): ?int
    {
        $url = $this->baseUrl . '/webservice/upload.php';

        $verifySsl = (bool) config('services.moodle.verify_ssl', true);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => $verifySsl,
            CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => [
                'token'    => $this->token,
                'filearea' => 'draft',
                'file_1'   => new \CURLFile(
                    $this->writeTempFile($contents, $filename),
                    'application/pdf',
                    $filename
                ),
            ],
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        if (! $response) return null;

        $data = json_decode($response, true);
        if (! is_array($data) || empty($data)) return null;

        return (int) ($data[0]['itemid'] ?? 0) ?: null;
    }

    private function writeTempFile(string $contents, string $filename): string
    {
        $path = sys_get_temp_dir() . '/' . uniqid('moodle_') . '_' . basename($filename);
        file_put_contents($path, $contents);
        register_shutdown_function(fn() => @unlink($path));
        return $path;
    }

    /**
     * Make a Moodle Web Services REST call.
     */
    private function call(string $wsfunction, array $params): array
    {
        $url = $this->baseUrl . '/webservice/rest/server.php';

        $body = array_merge([
            'wstoken'       => $this->token,
            'wsfunction'    => $wsfunction,
            'moodlewsrestformat' => 'json',
        ], $params);


        $raw = $this->curlPost($url, $body);

        if (is_array($raw) && isset($raw['error'])) {
            return [
                'ok'    => false,
                'error' => $raw['error'],
                'data'  => null,
            ];
        }

        $data = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['ok' => false, 'error' => 'Invalid response from Moodle API.', 'data' => null];
        }

        if (isset($data['exception'])) {
            $msg = $data['message'] ?? ($data['exception'] ?? 'Moodle API error.');
            return ['ok' => false, 'error' => $msg, 'data' => null];
        }

        if (isset($data['errorcode'])) {
            return ['ok' => false, 'error' => $data['message'] ?? $data['errorcode'], 'data' => null];
        }

        // Collect any non-fatal Moodle warnings for caller awareness
        $warnings = [];
        if (! empty($data['warnings'])) {
            foreach ($data['warnings'] as $w) {
                $warnings[] = ($w['message'] ?? '') ?: ($w['warningcode'] ?? 'unknown warning');
            }
        }

        return ['ok' => true, 'error' => null, 'data' => $data, 'warnings' => $warnings];
    }

        private function curlPost(string $url, array $fields): string|array
        {
            $verifySsl = (bool) config('services.moodle.verify_ssl', true);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => $this->timeout,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => http_build_query($fields),
                CURLOPT_SSL_VERIFYPEER => $verifySsl,
                CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
                CURLOPT_USERAGENT      => 'TiX-AMS/1.0',
            ]);

            $result = curl_exec($ch);

            if ($result === false) {
                $error = curl_error($ch);
                curl_close($ch);
                return ['error' => $error];
            }

            curl_close($ch);
            return $result;
        }

    private function curlGet(string $url): string|false
    {
        $verifySsl = (bool) config('services.moodle.verify_ssl', true);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => $verifySsl,
            CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
            CURLOPT_USERAGENT      => 'TiX-AMS/1.0',
        ]);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }
}
