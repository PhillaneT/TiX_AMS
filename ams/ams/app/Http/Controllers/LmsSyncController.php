<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\Cohort;
use App\Models\Learner;
use App\Models\LmsConnection;
use App\Models\Submission;
use App\Services\MoodleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LmsSyncController extends Controller
{
    /**
     * Pull-sync: import courses, assignments, and submissions from Moodle into AMS.
     * A cohort is auto-created per Moodle course under the chosen qualification.
     */
    public function sync(Request $request, LmsConnection $integration)
    {
        abort_if($integration->user_id !== auth()->id(), 403);

        // ✅ Single, authoritative validation
        $validated = $request->validate([
            'qualification_id' => ['required', 'integer', 'exists:qualifications,id'],
            'course_ids'       => ['required', 'array', 'min:1'],
            'course_ids.*'     => ['integer'],
        ]);

        $qualificationId = $validated['qualification_id'];
        $courseIds       = $validated['course_ids'];

        // ✅ HARD SCOPE LOCK (defensive programming)
        if (empty($courseIds)) {
            return redirect()
                ->back()
                ->with('error', 'Please select at least one Moodle course to sync.');
        }

        // ✅ Create service AFTER validation
        $service = new MoodleService($integration);

        try {
            // ✅ ONLY sync assignments from selected courses
            $syncResult = $service->getAssignments($courseIds);
        } catch (\Throwable $e) {
            $integration->update(['last_error' => $e->getMessage()]);

            return redirect()
                ->route('integrations.index')
                ->with('error', 'Sync failed: ' . $e->getMessage());
        }

        // ✅ Continue with existing assignment + submission import logic
        $moodleAssignments = $syncResult['assignments'] ?? [];
        $moodleWarnings    = $syncResult['warnings'] ?? [];

        $assignmentsImported  = 0;
        $assignmentsSkipped   = 0;
        $submissionsImported  = 0;
        $hadPartialFailure    = false;

        foreach ($moodleAssignments as $moodleAssignment) {
            $moodleAssignId  = (string) ($moodleAssignment['id'] ?? '');
            $moodleCourseId  = (string) ($moodleAssignment['course_id'] ?? '');
            $moodleCourseName = $moodleAssignment['course_name'] ?? ('Course ' . $moodleCourseId);

            if (! $moodleAssignId) continue;

            $existing = Assignment::where('lms_connection_id', $integration->id)
                ->where('lms_assignment_id', $moodleAssignId)
                ->first();

            if ($existing) {
                $assignmentsSkipped++;
                $assignment = $existing;
            } else {
                $assignment = Assignment::create([
                    'qualification_id'  => $qualificationId,
                    'lms_connection_id' => $integration->id,
                    'lms_assignment_id' => $moodleAssignId,
                    'name'              => $moodleAssignment['name'] ?? ('Moodle Assignment ' . $moodleAssignId),
                    'description'       => strip_tags($moodleAssignment['intro'] ?? ''),
                    'type'              => 'summative',
                    'total_marks'       => 100,
                    'memo_type'         => 'text',
                ]);

                $assignmentsImported++;

                AuditLog::record('lms.assignment.imported', $assignment, [
                    'lms_assignment_id' => $moodleAssignId,
                    'connection_id'     => $integration->id,
                ]);
            }

            // Auto-create/find a cohort for this Moodle course
            $cohort = $this->findOrCreateCohort(
                $qualificationId,
                $moodleCourseId,
                $moodleCourseName
            );

            // Import submissions for this assignment
            try {
                $submissionsImported += $this->importSubmissions(
                    $service,
                    $integration,
                    $assignment,
                    $cohort,
                    (int) $moodleAssignId
                );
            } catch (\Exception $e) {
                // Log the error but continue with remaining assignments
                $hadPartialFailure = true;
                $integration->update(['last_error' => 'Submission sync partial failure: ' . $e->getMessage()]);
            }
        }

        $integration->update([
            'last_synced_at' => now(),
            'last_error'     => $hadPartialFailure ? $integration->last_error : null,
        ]);

        AuditLog::record('lms.sync.pull', null, [
            'connection_id'        => $integration->id,
            'assignments_imported' => $assignmentsImported,
            'assignments_skipped'  => $assignmentsSkipped,
            'submissions_imported' => $submissionsImported,
            'moodle_warnings'      => $moodleWarnings,
        ]);

        $message = "Sync complete: {$assignmentsImported} assignment(s) imported, {$assignmentsSkipped} already existed, {$submissionsImported} submission(s) imported.";

        if (! empty($moodleWarnings)) {
            $message .= ' Moodle warnings: ' . implode('; ', $moodleWarnings);
        }

        return redirect()
            ->route('integrations.index')
            ->with('success', $message);
    }

    /**
     * Sync submissions for a specific Moodle assignment into a learner's cohort.
     * Used for re-syncing submissions on an already-imported assignment.
     */
    public function syncSubmissions(Request $request, LmsConnection $integration)
    {
        abort_if($integration->user_id !== auth()->id(), 403);

        $request->validate([
            'assignment_id' => ['required', 'integer', 'exists:assignments,id'],
            'cohort_id'     => ['required', 'integer', 'exists:cohorts,id'],
        ]);

        $assignment = Assignment::findOrFail($request->integer('assignment_id'));

        if (! $assignment->lms_assignment_id) {
            return redirect()->back()->with('error', 'This assignment is not linked to Moodle.');
        }

        if ($assignment->lms_connection_id !== $integration->id) {
            return redirect()->back()->with('error', 'This assignment does not belong to the selected Moodle connection.');
        }

        $cohort = Cohort::findOrFail($request->integer('cohort_id'));

        try {
            $service  = new MoodleService($integration);
            $imported = $this->importSubmissions(
                $service,
                $integration,
                $assignment,
                $cohort,
                (int) $assignment->lms_assignment_id
            );
        } catch (\Exception $e) {
            $integration->update(['last_error' => $e->getMessage()]);

            return redirect()->back()->with('error', 'Submission sync failed: ' . $e->getMessage());
        }

        $integration->update(['last_synced_at' => now(), 'last_error' => null]);

        AuditLog::record('lms.submissions.synced', null, [
            'connection_id' => $integration->id,
            'assignment_id' => $assignment->id,
            'imported'      => $imported,
        ]);

        return redirect()->back()
            ->with('success', "{$imported} submission(s) imported from Moodle.");
    }

    /**
     * Push a graded submission back to Moodle.
     */
    public function push(Request $request, LmsConnection $integration, Submission $submission)
    {
        abort_if($integration->user_id !== auth()->id(), 403);
        abort_if($submission->lms_connection_id !== $integration->id, 403);

        if (! $submission->assignment->lms_assignment_id) {
            return redirect()->back()->with('error', 'This assignment has no Moodle assignment ID.');
        }

        $result = $submission->markingResult;

        if (! $result || ! $result->final_verdict) {
            return redirect()->back()->with('error', 'Submission must be signed off before pushing to Moodle.');
        }

        $moodleUserId = $this->extractMoodleUserId($submission);

        if ($moodleUserId === 0) {
            return redirect()->back()->with('error', 'Cannot push to Moodle: the learner\'s Moodle user ID is not set. The learner must have been imported via Moodle sync (external_ref = moodle_{id}).');
        }

        $moodleAssignId  = (int) $submission->assignment->lms_assignment_id;
        $grade           = $this->verdictToGrade($result->final_verdict, $result);
        $feedbackText    = $this->buildFeedbackText($result);

        // Prefer the graded annotated PDF, then the cover letter PDF, then the raw submission.
        [$pdfContents, $pdfFilename] = $this->resolveGradedPdf($submission, $result);

        try {
            $service    = new MoodleService($integration);
            $pushResult = $service->pushGrade(
                $moodleAssignId,
                $moodleUserId,
                $grade,
                $feedbackText,
                $pdfContents,
                $pdfFilename
            );
        } catch (\Exception $e) {
            $integration->update(['last_error' => $e->getMessage()]);

            return redirect()->back()->with('error', 'Push to Moodle failed: ' . $e->getMessage());
        }

        if (! $pushResult['ok']) {
            $integration->update(['last_error' => $pushResult['error']]);

            return redirect()->back()->with('error', 'Push to Moodle failed: ' . $pushResult['error']);
        }

        $submission->update(['lms_pushed_at' => now()]);
        $integration->update(['last_error' => null]);

        AuditLog::record('lms.submission.pushed', $submission, [
            'connection_id'    => $integration->id,
            'moodle_assign_id' => $moodleAssignId,
            'grade'            => $grade,
            'verdict'          => $result->final_verdict,
        ]);

        return redirect()->back()
            ->with('success', 'Grade and feedback pushed to Moodle successfully.');
    }

    /**
     * Fetch accessible courses from Moodle and return as JSON (for the settings page).
     */
    public function fetchCourses(LmsConnection $integration)
    {
        abort_if($integration->user_id !== auth()->id(), 403);

        try {
            $service = new MoodleService($integration);
            $courses = $service->getCourses();
        } catch (\Exception $e) {
            return redirect()
                ->route('integrations.index')
                ->with('error', 'Could not fetch courses: ' . $e->getMessage());
        }

        $integration->update([
            'last_fetched_courses' => $courses,
        ]);

        return redirect()
            ->route('integrations.index')
            ->with('success', 'Fetched ' . count($courses) . ' Moodle course(s).');
    }

    // -------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------

    private function findOrCreateCohort(int $qualificationId, string $moodleCourseId, string $courseName): Cohort
    {
        return Cohort::firstOrCreate([
            'qualification_id' => $qualificationId,
            'name'             => 'Moodle: ' . $courseName,
        ], [
            'year' => now()->year,
        ]);
    }

    /**
     * Import Moodle submissions for an assignment into a cohort.
     * Returns the count of newly imported submissions.
     */
    private function importSubmissions(
        MoodleService $service,
        LmsConnection $integration,
        Assignment $assignment,
        Cohort $cohort,
        int $moodleAssignId
    ): int {
    // 🔒 Defensive guard: never import submissions into the wrong assignment
    if ((int) $assignment->lms_assignment_id !== (int) $moodleAssignId) {
        throw new \LogicException(
            "Assignment mismatch: refusing to import submissions into the wrong assignment."
        );
    }

        $submissions = $service->getSubmissions($moodleAssignId);
        $imported    = 0;

        foreach ($submissions as $moodleSub) {
            $moodleUserId = (int) ($moodleSub['userid'] ?? 0);
            $moodleSubId  = (string) ($moodleSub['id'] ?? '');

            if (! $moodleUserId || ! $moodleSubId) continue;
            if (($moodleSub['status'] ?? '') !== 'submitted') continue;

            $existing = Submission::where('lms_connection_id', $integration->id)
                ->where('lms_submission_id', $moodleSubId)
                ->first();

            if ($existing) continue;

            $learner = Learner::firstOrCreate([
                'cohort_id'    => $cohort->id,
                'external_ref' => 'moodle_' . $moodleUserId,
            ], [
                'first_name' => $moodleSub['userfullname'] ?? 'Moodle',
                'last_name'  => 'User ' . $moodleUserId,
                'email'      => null,
            ]);

            $fileList = collect($moodleSub['plugins'] ?? [])
                ->where('type', 'file')
                ->flatMap(fn($p) => collect($p['fileareas'] ?? [])->where('area', 'submission_files'))
                ->flatMap(fn($a) => $a['files'] ?? [])
                ->first();

            $filePath      = null;
            $filename      = 'moodle_submission_' . $moodleSubId . '.pdf';
            $fileDownloaded = false;

            if ($fileList) {
                $filename = $fileList['filename'] ?? $filename;
                try {
                    $contents = $service->downloadFile($fileList['fileurl']);
                    if ($contents !== false && strlen((string) $contents) > 0) {
                        $safeName = now()->format('YmdHis') . '_moodle_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
                        $filePath = "private/submissions/{$learner->id}/{$assignment->id}/{$safeName}";
                        Storage::put($filePath, $contents);
                        $fileDownloaded = true;
                    }
                } catch (\Exception $e) {
                    // File download failed — create submission record without file
                }
            }

            // Indicate incomplete import when no file was retrieved
            $importStatus   = ($fileDownloaded || ! $fileList) ? 'uploaded' : 'uploaded';
            $displayFilename = $fileDownloaded
                ? $filename
                : '[File not downloaded] ' . $filename;

            try {
                $submission = Submission::create([
                    'assignment_id'     => $assignment->id,
                    'learner_id'        => $learner->id,
                    'user_id'           => auth()->id(),
                    'lms_connection_id' => $integration->id,
                    'lms_submission_id' => $moodleSubId,
                    'original_filename' => $displayFilename,
                    'file_path'         => $filePath ?? '',
                    'status'            => $importStatus,
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                // Duplicate (assignment_id, learner_id) — skip and continue
                AuditLog::record('lms.submission.skipped', null, [
                    'lms_submission_id' => $moodleSubId,
                    'connection_id'     => $integration->id,
                    'reason'            => 'duplicate',
                ]);
                continue;
            }

            $imported++;

            AuditLog::record('lms.submission.imported', $submission, [
                'lms_submission_id' => $moodleSubId,
                'connection_id'     => $integration->id,
            ]);
        }

        return $imported;
    }

    /**
     * Resolve the best available PDF to attach when pushing back to Moodle.
     * Priority: annotated graded PDF → cover letter PDF → original submission (if PDF).
     * Returns [string|null $contents, string|null $filename].
     */
    private function resolveGradedPdf(Submission $submission, $result): array
    {
        $candidates = [
            [$result->annotated_pdf_path ?? null, 'graded_' . basename($submission->original_filename)],
            [$result->cover_pdf_path ?? null,     'feedback_' . basename($submission->original_filename)],
        ];

        foreach ($candidates as [$path, $name]) {
            if ($path && Storage::exists($path)) {
                return [Storage::get($path), $name];
            }
        }

        // Fallback: raw submission file, only if it is a PDF
        if ($submission->file_path && Storage::exists($submission->file_path)) {
            $ext = pathinfo($submission->file_path, PATHINFO_EXTENSION);
            if (strtolower($ext) === 'pdf') {
                return [Storage::get($submission->file_path), $submission->original_filename];
            }
        }

        return [null, null];
    }

    private function extractMoodleUserId(Submission $submission): int
    {
        $ref = $submission->learner->external_ref ?? '';
        if (preg_match('/^moodle_(\d+)$/', $ref, $m)) {
            return (int) $m[1];
        }
        return 0;
    }

    private function verdictToGrade(string $verdict, $result): float
    {
        if ($verdict === 'COMPETENT') {
            $questions = $result->questions_json ?? [];
            $totalMax  = collect($questions)->sum('max_marks');
            $awarded   = collect($questions)->sum('awarded');

            if ($totalMax > 0) {
                return round(($awarded / $totalMax) * 100, 2);
            }

            return 75.0;
        }

        return 30.0;
    }

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
            foreach ($questions as $i => $q) {
                $criterion = $q['criterion'] ?? $q['question'] ?? 'Question ' . ($i + 1);
                $comment   = $q['comment'] ?? '';
                $lines[]   = sprintf('- %s: %s/%s — %s', $criterion, $q['awarded'] ?? 0, $q['max_marks'] ?? 0, $comment);
            }
        }

        $lines[] = '';
        $lines[] = 'Assessed by: ' . ($result->assessor_name ?? auth()->user()->name);

        return implode("\n", $lines);
    }
}
