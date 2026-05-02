<?php

namespace App\Services;

use App\Models\LmsConnection;
use App\Models\Submission;
use Illuminate\Support\Facades\Storage;

/**
 * MoodlePushService — isolated push-back layer.
 *
 * Responsibilities:
 *   - Orchestrate a full grade push via pushToMoodle()
 *   - Attempt advanced (criterion-level) grading first (marking guide / rubric)
 *   - Fall back to simple numeric grade + text feedback if advanced grading unavailable
 *   - Upload the annotated PDF as a Moodle feedback file
 *
 * Does NOT touch: submission status, audit logging, or UI concerns.
 * Those stay in LmsSyncController so this service stays reversible/testable.
 */
class MoodlePushService
{
    private LmsConnection $connection;
    private MoodleService $moodle;
    private int $timeout = 30;

    public function __construct(LmsConnection $connection)
    {
        $this->connection = $connection;
        $this->moodle     = new MoodleService($connection);
    }

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Main orchestration method.
     *
     * Sequence:
     *   1. Validate preconditions (signed-off, Moodle IDs present)
     *   2. Compute grade, feedback text, resolve best PDF
     *   3. Try advanced criterion-level push (marking guide or rubric)
     *   4. Fall back to simple grade + text feedback + PDF on advanced failure
     *
     * @return array{ok: bool, error: string|null, method: string|null}
     *   method values: 'advanced_guide' | 'advanced_rubric' | 'simple'
     */
    public function pushToMoodle(Submission $submission): array
    {
        $result = $submission->markingResult;

        if (! $result || ! $result->final_verdict) {
            return ['ok' => false, 'error' => 'Submission must be signed off before pushing to Moodle.', 'method' => null];
        }

        $moodleAssignId = (int) ($submission->assignment->lms_assignment_id ?? 0);
        if (! $moodleAssignId) {
            return ['ok' => false, 'error' => 'Assignment has no Moodle assignment ID.', 'method' => null];
        }

        $moodleUserId = $this->extractMoodleUserId($submission);
        if (! $moodleUserId) {
            return [
                'ok'     => false,
                'error'  => 'Learner has no Moodle user ID. Learner must have been imported via Moodle sync (external_ref = moodle_{id}).',
                'method' => null,
            ];
        }

        $grade        = $this->verdictToGrade($result);
        $feedbackText = $this->buildFeedbackText($result);
        [$pdfContents, $pdfFilename] = $this->resolveGradedPdf($submission, $result);

        // ── Step 1: attempt advanced criterion-level grading ──────────────────
        $advanced = $this->tryPushAdvancedGrade(
            $moodleAssignId,
            $moodleUserId,
            $grade,
            $feedbackText,
            $result->questions_json ?? [],
            $pdfContents,
            $pdfFilename
        );

        if ($advanced['ok']) {
            return [
                'ok'     => true,
                'error'  => null,
                'method' => 'advanced_' . ($advanced['grading_method'] ?? 'unknown'),
            ];
        }

        // ── Step 2: fall back to simple grade + feedback + PDF ────────────────
        // Use the existing MoodleService::pushGrade() which already handles
        // the grade payload, feedback comment, and draft-file upload.
        $simple = $this->moodle->pushGrade(
            $moodleAssignId,
            $moodleUserId,
            $grade,
            $feedbackText,
            $pdfContents,
            $pdfFilename
        );

        if (! $simple['ok']) {
            return ['ok' => false, 'error' => $simple['error'], 'method' => null];
        }

        return ['ok' => true, 'error' => null, 'method' => 'simple'];
    }

    // =========================================================================
    // Advanced grading (criterion-level)
    // =========================================================================

    /**
     * Attempt to push criterion-level scores via Moodle's advanced grading API.
     *
     * Flow:
     *   1. Call core_grading_get_definitions to get criterion IDs + method
     *   2. Match our stored criteria (questions_json) to Moodle criteria by name
     *   3. Build mod_assign_save_grade params with per-criterion scores
     *   4. Upload PDF as feedback file
     *   5. Call mod_assign_save_grade
     *
     * Returns ['ok' => false] (no side-effects) when advanced grading is
     * unavailable — caller falls back to simple push.
     */
    private function tryPushAdvancedGrade(
        int $assignmentId,
        int $userId,
        float $grade,
        string $feedbackText,
        array $questions,
        ?string $pdfContents,
        ?string $pdfFilename
    ): array {
        if (empty($questions)) {
            return ['ok' => false, 'error' => 'No criteria available for advanced grading.'];
        }

        $definition = $this->fetchGradingDefinition($assignmentId);
        if (! $definition) {
            // No advanced grading configured — silent fallback expected
            return ['ok' => false, 'error' => 'No advanced grading definition found for this assignment.'];
        }

        $gradingMethod  = $definition['method'];   // 'guide' or 'rubric'
        $moodleCriteria = $definition['criteria'];

        if (empty($moodleCriteria)) {
            return ['ok' => false, 'error' => 'Advanced grading definition has no criteria.'];
        }

        // Build the criterion param block appropriate to the grading method
        $criterionParams = match ($gradingMethod) {
            'guide'  => $this->buildMarkingGuideParams($moodleCriteria, $questions),
            'rubric' => $this->buildRubricParams($moodleCriteria, $questions),
            default  => [],
        };

        if (empty($criterionParams)) {
            return ['ok' => false, 'error' => "Could not match any criteria to the '{$gradingMethod}' grading definition."];
        }

        // Base grade payload (identical to simple push)
        $params = [
            'assignmentid'  => $assignmentId,
            'userid'        => $userId,
            'grade'         => $grade,
            'attemptnumber' => -1,
            'addattempt'    => 0,
            'workflowstate' => 'released',
            'applytoall'    => 0,
            'plugindata[assignfeedbackcomments_editor][text]'   => $feedbackText,
            'plugindata[assignfeedbackcomments_editor][format]' => 1,
        ];

        // Inject criterion-level params into the payload
        foreach ($criterionParams as $key => $value) {
            $params[$key] = $value;
        }

        // Attach annotated PDF as a Moodle draft file
        if ($pdfContents && $pdfFilename) {
            $draftId = $this->uploadFeedbackFile($pdfContents, $pdfFilename);
            if ($draftId) {
                $params['plugindata[assignfeedback_file_filemanager]'] = $draftId;
            }
        }

        $callResult = $this->apiCall('mod_assign_save_grade', $params);

        if ($callResult['ok']) {
            $callResult['grading_method'] = $gradingMethod;
        }

        return $callResult;
    }

    /**
     * Fetch the grading definition for an assignment (identified by cmid).
     * Returns ['method' => 'guide'|'rubric', 'criteria' => [...]] or null when
     * no advanced grading is configured.
     */
    private function fetchGradingDefinition(int $cmid): ?array
    {
        $result = $this->apiCall('core_grading_get_definitions', [
            'cmids[0]' => $cmid,
            'includes' => 'all',
        ]);

        if (! $result['ok']) return null;

        $areas = $result['data']['areas'] ?? [];
        if (empty($areas)) return null;

        $area   = $areas[0];
        $method = $area['method'] ?? null;

        // Only handle marking guide and rubric — everything else falls back
        if (! in_array($method, ['guide', 'rubric'], true)) return null;

        $definitions = $area['definitions'] ?? [];
        if (empty($definitions)) return null;

        $def      = $definitions[0];
        $criteria = match ($method) {
            'guide'  => $def['guide']['criteria']  ?? [],
            'rubric' => $def['rubric']['criteria'] ?? [],
            default  => [],
        };

        return ['method' => $method, 'criteria' => $criteria];
    }

    /**
     * Build marking guide criterion params for mod_assign_save_grade.
     *
     * Moodle expects (per criterion i):
     *   plugindata[marking guide][criteria][i][criterionid]   = <int>
     *   plugindata[marking guide][criteria][i][score]         = <float>
     *   plugindata[marking guide][criteria][i][remark]        = <string>
     *   plugindata[marking guide][criteria][i][remarkformat]  = 0
     *
     * Score is scaled from our max_marks to Moodle's maxscore so the
     * resulting numeric grade matches across different scale denominators.
     */
    private function buildMarkingGuideParams(array $moodleCriteria, array $questions): array
    {
        $params = [];
        $i      = 0;

        foreach ($moodleCriteria as $moodleCrit) {
            $criterionId = $moodleCrit['id']       ?? null;
            $maxScore    = (float) ($moodleCrit['maxscore'] ?? 0);

            if (! $criterionId || $maxScore <= 0) continue;

            $question = $this->matchCriterion(
                $moodleCrit['shortname'] ?? $moodleCrit['description'] ?? '',
                $questions
            );

            if (! $question) continue;

            // Scale our awarded/max_marks ratio to Moodle's maxscore
            $ourMax   = (int) ($question['max_marks'] ?? 0);
            $awarded  = (int) ($question['awarded']   ?? 0);
            $score    = $ourMax > 0
                ? round(($awarded / $ourMax) * $maxScore, 2)
                : 0.0;
            // Clamp to Moodle's declared maximum (never exceed)
            $score = min($score, $maxScore);

            $prefix = "plugindata[marking guide][criteria][{$i}]";
            $params["{$prefix}[criterionid]"]  = $criterionId;
            $params["{$prefix}[score]"]         = $score;
            $params["{$prefix}[remark]"]        = $question['comment'] ?? '';
            $params["{$prefix}[remarkformat]"]  = 0;
            $i++;
        }

        return $params;
    }

    /**
     * Build rubric criterion params for mod_assign_save_grade.
     *
     * Moodle expects (per criterion i):
     *   plugindata[rubric][criteria][i][criterionid]  = <int>
     *   plugindata[rubric][criteria][i][levelid]      = <int>
     *   plugindata[rubric][criteria][i][remark]       = <string>
     *   plugindata[rubric][criteria][i][remarkformat] = 0
     *
     * levelid is chosen as the rubric level whose score (as a % of the
     * criterion's maximum level score) is closest to the assessor's awarded %.
     */
    private function buildRubricParams(array $moodleCriteria, array $questions): array
    {
        $params = [];
        $i      = 0;

        foreach ($moodleCriteria as $moodleCrit) {
            $criterionId = $moodleCrit['id']     ?? null;
            $levels      = $moodleCrit['levels'] ?? [];

            if (! $criterionId || empty($levels)) continue;

            $question = $this->matchCriterion(
                $moodleCrit['description'] ?? '',
                $questions
            );

            if (! $question) continue;

            $ourMax  = (int) ($question['max_marks'] ?? 0);
            $awarded = (int) ($question['awarded']   ?? 0);
            $pct     = $ourMax > 0 ? ($awarded / $ourMax) : 0.0;

            $levelId = $this->closestRubricLevelId($levels, $pct);
            if (! $levelId) continue;

            $prefix = "plugindata[rubric][criteria][{$i}]";
            $params["{$prefix}[criterionid]"] = $criterionId;
            $params["{$prefix}[levelid]"]     = $levelId;
            $params["{$prefix}[remark]"]      = $question['comment'] ?? '';
            $params["{$prefix}[remarkformat]"] = 0;
            $i++;
        }

        return $params;
    }

    /**
     * Select the rubric level ID whose score (normalised to 0–1) is closest
     * to the assessor's achieved percentage on this criterion.
     */
    private function closestRubricLevelId(array $levels, float $targetPct): ?int
    {
        $maxLevelScore = collect($levels)->max('score');

        if (! $maxLevelScore) {
            // All levels have score 0 — just pick the first one
            return isset($levels[0]['id']) ? (int) $levels[0]['id'] : null;
        }

        $bestId   = null;
        $bestDiff = PHP_FLOAT_MAX;

        foreach ($levels as $level) {
            $levelPct = (float) $level['score'] / $maxLevelScore;
            $diff     = abs($levelPct - $targetPct);

            if ($diff < $bestDiff) {
                $bestDiff = $diff;
                $bestId   = (int) $level['id'];
            }
        }

        return $bestId;
    }

    /**
     * Match a Moodle criterion name to one of our stored questions_json entries.
     *
     * Priority:
     *   1. Exact match (case-insensitive)
     *   2. Either string is a substring of the other
     *   3. Word overlap ≥ 50 % of Moodle criterion words (min 3-char words)
     *
     * Returns null when no confident match found — that criterion is skipped.
     */
    private function matchCriterion(string $moodleName, array $questions): ?array
    {
        $needle = strtolower(trim($moodleName));
        if ($needle === '') return null;

        // 1. Exact match
        foreach ($questions as $q) {
            if (strtolower(trim($q['criterion'] ?? '')) === $needle) {
                return $q;
            }
        }

        // 2. Substring match (either direction)
        foreach ($questions as $q) {
            $hay = strtolower($q['criterion'] ?? '');
            if (str_contains($hay, $needle) || str_contains($needle, $hay)) {
                return $q;
            }
        }

        // 3. Word overlap (≥ 50 % of Moodle's significant words found in ours)
        $needleWords = array_filter(
            explode(' ', preg_replace('/[^\w\s]/', '', $needle)),
            fn($w) => strlen($w) >= 3
        );

        if (empty($needleWords)) return null;

        $best      = null;
        $bestScore = 0.0;

        foreach ($questions as $q) {
            $hay   = strtolower($q['criterion'] ?? '');
            $hits  = 0;

            foreach ($needleWords as $word) {
                if (str_contains($hay, $word)) $hits++;
            }

            $score = $hits / count($needleWords);

            if ($score >= 0.5 && $score > $bestScore) {
                $best      = $q;
                $bestScore = $score;
            }
        }

        return $best;
    }

    // =========================================================================
    // File upload
    // =========================================================================

    /**
     * Upload a PDF to the Moodle user's draft file area.
     * Returns the draft item ID (needed for plugindata[files_filemanager]),
     * or null if the upload failed (non-fatal — grade push continues without file).
     */
    public function uploadFeedbackFile(string $contents, string $filename): ?int
    {
        $url       = $this->connection->getBaseUrl() . '/webservice/upload.php';
        $token     = $this->connection->getApiToken();
        $verifySsl = (bool) config('services.moodle.verify_ssl', true);
        $tmpPath   = $this->writeTempFile($contents, $filename);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => $verifySsl,
            CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => [
                'token'    => $token,
                'filearea' => 'draft',
                'file_1'   => new \CURLFile($tmpPath, 'application/pdf', $filename),
            ],
        ]);

        $response = curl_exec($ch);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        @unlink($tmpPath); // always clean up temp file

        if (! $response || $curlErr) return null;

        $data = json_decode($response, true);
        if (! is_array($data) || empty($data)) return null;

        $itemId = (int) ($data[0]['itemid'] ?? 0);
        return $itemId > 0 ? $itemId : null;
    }

    // =========================================================================
    // Grade + feedback computation
    // =========================================================================

    /**
     * Convert COMPETENT/NOT_YET_COMPETENT to a numeric grade (0–100).
     * COMPETENT: actual percentage from criterion scores, or 75 if no breakdown.
     * NOT_YET_COMPETENT: 30 (below typical pass threshold).
     */
    private function verdictToGrade($result): float
    {
        if ($result->final_verdict === 'COMPETENT') {
            $questions = $result->questions_json ?? [];
            $totalMax  = collect($questions)->sum('max_marks');
            $awarded   = collect($questions)->sum('awarded');

            return $totalMax > 0
                ? round(($awarded / $totalMax) * 100, 2)
                : 75.0;
        }

        return 30.0;
    }

    /**
     * Build the assessor feedback text block sent to Moodle.
     * Includes: verdict, override flag, moderation notes, per-criterion breakdown, assessor name.
     */
    private function buildFeedbackText($result): string
    {
        $lines   = [];
        $lines[] = 'Final Verdict: ' . ($result->final_verdict === 'COMPETENT' ? 'Competent' : 'Not Yet Competent');

        if ($result->assessor_override) {
            $lines[] = '(Assessor override applied)';
        }

        if ($result->moderation_notes) {
            $lines[] = '';
            $lines[] = 'Assessor Notes:';
            $lines[] = $result->moderation_notes;
        }

        $questions = $result->questions_json ?? [];
        if (! empty($questions)) {
            $lines[] = '';
            $lines[] = 'Criterion Breakdown:';

            foreach ($questions as $idx => $q) {
                $criterion = $q['criterion'] ?? $q['question'] ?? ('Question ' . ($idx + 1));
                $awarded   = $q['awarded']   ?? 0;
                $maxMarks  = $q['max_marks'] ?? 0;
                $comment   = $q['comment']   ?? '';

                $lines[] = sprintf('- %s: %s/%s', $criterion, $awarded, $maxMarks);
                if ($comment !== '') {
                    $lines[] = '  ' . $comment;
                }
            }
        }

        $lines[] = '';
        $lines[] = 'Assessed by: ' . ($result->assessor_name ?? 'Assessor');

        return implode("\n", $lines);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Resolve the best available PDF to attach as feedback.
     * Priority: annotated graded PDF → cover/declaration PDF → raw submission (PDF only).
     */
    private function resolveGradedPdf(Submission $submission, $result): array
    {
        $candidates = [
            [$result->annotated_pdf_path ?? null, 'graded_' . basename($submission->original_filename)],
            [$result->cover_pdf_path     ?? null, 'feedback_' . basename($submission->original_filename)],
        ];

        foreach ($candidates as [$path, $name]) {
            if ($path && Storage::exists($path)) {
                return [Storage::get($path), $name];
            }
        }

        // Raw submission fallback — only safe to attach if it is already a PDF
        if ($submission->file_path && Storage::exists($submission->file_path)) {
            if (strtolower(pathinfo($submission->file_path, PATHINFO_EXTENSION)) === 'pdf') {
                return [Storage::get($submission->file_path), $submission->original_filename];
            }
        }

        return [null, null];
    }

    /**
     * Extract the Moodle numeric user ID from the learner's external_ref.
     * external_ref format: 'moodle_{id}' (set during sync import).
     */
    private function extractMoodleUserId(Submission $submission): int
    {
        $ref = $submission->learner->external_ref ?? '';

        if (preg_match('/^moodle_(\d+)$/', $ref, $m)) {
            return (int) $m[1];
        }

        return 0;
    }

    /** Write binary content to a uniquely named temp file and return its path. */
    private function writeTempFile(string $contents, string $filename): string
    {
        $path = sys_get_temp_dir() . '/' . uniqid('tix_push_') . '_' . basename($filename);
        file_put_contents($path, $contents);
        return $path;
    }

    /**
     * Make a Moodle REST API call directly (does not go through MoodleService
     * to keep this service fully self-contained and independently testable).
     */
    private function apiCall(string $wsfunction, array $params): array
    {
        $url       = $this->connection->getBaseUrl() . '/webservice/rest/server.php';
        $token     = $this->connection->getApiToken();
        $verifySsl = (bool) config('services.moodle.verify_ssl', true);

        $body = http_build_query(array_merge([
            'wstoken'            => $token,
            'wsfunction'         => $wsfunction,
            'moodlewsrestformat' => 'json',
        ], $params));

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_SSL_VERIFYPEER => $verifySsl,
            CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
            CURLOPT_USERAGENT      => 'TiX-AMS/1.0',
        ]);

        $raw     = curl_exec($ch);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $curlErr) {
            return ['ok' => false, 'error' => 'cURL error: ' . $curlErr, 'data' => null];
        }

        $data = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['ok' => false, 'error' => 'Invalid JSON response from Moodle API.', 'data' => null];
        }

        if (isset($data['exception'])) {
            return ['ok' => false, 'error' => $data['message'] ?? ($data['exception'] ?? 'Moodle API exception.'), 'data' => null];
        }

        if (isset($data['errorcode'])) {
            return ['ok' => false, 'error' => $data['message'] ?? $data['errorcode'], 'data' => null];
        }

        return ['ok' => true, 'error' => null, 'data' => $data];
    }
}
