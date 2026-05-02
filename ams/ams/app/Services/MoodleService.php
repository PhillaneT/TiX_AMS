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

        if ($pdfContents !== null && $pdfFilename !== null) {
            $draftId = $this->uploadFileToDraft($pdfContents, $pdfFilename);
            if ($draftId !== null) {
                $params['plugindata[files_filemanager]'] = $draftId;
            }
        }

        $result = $this->call('mod_assign_save_grade', $params);

        if (! $result['ok']) {
            return ['ok' => false, 'error' => $result['error']];
        }

        return ['ok' => true, 'error' => null];
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
