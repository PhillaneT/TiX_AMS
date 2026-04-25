<?php

namespace App\Http\Controllers;

use App\Models\AiUsage;
use App\Models\AuditLog;
use App\Models\Cohort;
use App\Models\Learner;
use App\Models\MarkingResult;
use App\Models\Qualification;
use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SubmissionController extends Controller
{
    // -------------------------------------------------------
    // Upload a learner's submission file
    // -------------------------------------------------------
    public function store(Request $request, Qualification $qualification, Cohort $cohort, Learner $learner)
    {
        abort_if($learner->cohort_id !== $cohort->id, 404);

        $request->validate([
            'assignment_id' => ['required', 'integer', 'exists:assignments,id'],
            'submission_file' => ['required', 'file', 'max:20480',
                'mimes:pdf,doc,docx,txt,png,jpg,jpeg,zip,odt'],
        ]);

        $assignmentId = $request->integer('assignment_id');

        // One submission per learner per assignment — find even soft-deleted rows and wipe them
        $existing = Submission::withTrashed()
            ->where('assignment_id', $assignmentId)
            ->where('learner_id', $learner->id)
            ->first();

        if ($existing) {
            Storage::delete($existing->file_path);
            $existing->markingResult?->forceDelete();
            $existing->forceDelete();
        }

        $file = $request->file('submission_file');
        $safeName = now()->format('YmdHis') . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
        $path = $file->storeAs(
            "private/submissions/{$learner->id}/{$assignmentId}",
            $safeName
        );

        $submission = Submission::create([
            'assignment_id'     => $assignmentId,
            'learner_id'        => $learner->id,
            'user_id'           => auth()->id(),
            'original_filename' => $file->getClientOriginalName(),
            'file_path'         => $path,
            'status'            => 'uploaded',
        ]);

        AuditLog::record('submission.uploaded', $submission, [
            'learner_id'    => $learner->id,
            'assignment_id' => $assignmentId,
        ]);

        return redirect()
            ->route('qualifications.cohorts.learners.poe', [$qualification, $cohort, $learner])
            ->with('success', 'Submission uploaded: ' . $file->getClientOriginalName());
    }

    // -------------------------------------------------------
    // Run AI marking (mock mode by default)
    // -------------------------------------------------------
    public function mark(Request $request, Qualification $qualification, Cohort $cohort, Learner $learner, Submission $submission)
    {
        abort_if($submission->learner_id !== $learner->id, 404);
        abort_if(! in_array($submission->status, ['uploaded', 'queued']), 422);

        $submission->update(['status' => 'marking']);

        $mockMode = true; // Always mock for now
        $assignment = $submission->assignment;

        // Generate mock marking result
        $marking = $this->runMockMarking($assignment);

        // Determine confidence label
        $variance = $this->scoreVariance($marking['questions']);
        $confidence = match (true) {
            $variance < 0.05 => 'HIGH',
            $variance < 0.15 => 'MEDIUM',
            default          => 'LOW',
        };

        $result = MarkingResult::create([
            'submission_id'    => $submission->id,
            'user_id'          => auth()->id(),
            'ai_recommendation'=> $marking['verdict'],
            'confidence'       => $confidence,
            'questions_json'   => $marking['questions'],
            'mock_mode'        => $mockMode,
            'assessor_override'=> false,
            'final_verdict'    => $marking['verdict'],
            'assessor_name'    => auth()->user()->name,
        ]);

        $submission->update([
            'status'    => 'review_required',
            'marked_at' => now(),
        ]);

        // Log AI usage record (mock)
        AiUsage::create([
            'submission_id'   => $submission->id,
            'user_id'         => auth()->id(),
            'tokens_input'    => rand(800, 1500),
            'tokens_output'   => rand(300, 600),
            'credits_charged' => 0,
            'mock_mode'       => true,
            'status'          => 'success',
        ]);

        AuditLog::record('submission.marked', $submission, [
            'verdict'   => $marking['verdict'],
            'mock_mode' => true,
        ]);

        return redirect()
            ->route('qualifications.cohorts.learners.submissions.show', [$qualification, $cohort, $learner, $submission])
            ->with('success', 'Mock AI marking complete. Please review and sign off.');
    }

    // -------------------------------------------------------
    // View marking result
    // -------------------------------------------------------
    public function show(Qualification $qualification, Cohort $cohort, Learner $learner, Submission $submission)
    {
        abort_if($submission->learner_id !== $learner->id, 404);

        $submission->load(['assignment', 'markingResult', 'assessor']);
        $result = $submission->markingResult;

        return view('submissions.show', compact('qualification', 'cohort', 'learner', 'submission', 'result'));
    }

    // -------------------------------------------------------
    // Assessor sign-off
    // -------------------------------------------------------
    public function signOff(Request $request, Qualification $qualification, Cohort $cohort, Learner $learner, Submission $submission)
    {
        abort_if($submission->learner_id !== $learner->id, 404);
        abort_if($submission->status !== 'review_required', 422);

        $request->validate([
            'final_verdict'    => ['required', 'in:COMPETENT,NOT_YET_COMPETENT'],
            'moderation_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $override = $request->input('final_verdict') !== $submission->markingResult?->ai_recommendation;

        $submission->markingResult?->update([
            'final_verdict'    => $request->input('final_verdict'),
            'assessor_override'=> $override,
            'assessor_name'    => auth()->user()->name,
            'moderation_notes' => $request->input('moderation_notes'),
            'signed_off_at'    => now(),
        ]);

        $submission->update([
            'status'        => 'signed_off',
            'signed_off_at' => now(),
        ]);

        AuditLog::record('submission.signed_off', $submission, [
            'verdict'  => $request->input('final_verdict'),
            'override' => $override,
        ]);

        return redirect()
            ->route('qualifications.cohorts.learners.poe', [$qualification, $cohort, $learner])
            ->with('success', 'Result signed off successfully.');
    }

    // -------------------------------------------------------
    // Re-open a signed-off submission for re-assessment
    // -------------------------------------------------------
    public function reopen(Request $request, Qualification $qualification, Cohort $cohort, Learner $learner, Submission $submission)
    {
        abort_if($submission->learner_id !== $learner->id, 404);
        abort_if($submission->status !== 'signed_off', 422);

        $submission->markingResult?->update([
            'final_verdict'  => null,
            'signed_off_at'  => null,
            'assessor_override' => false,
        ]);

        $submission->update(['status' => 'review_required', 'signed_off_at' => null]);

        AuditLog::record('submission.reopened', $submission);

        return redirect()
            ->route('qualifications.cohorts.learners.submissions.show', [$qualification, $cohort, $learner, $submission])
            ->with('info', 'Submission re-opened for review.');
    }

    // -------------------------------------------------------
    // Delete a submission
    // -------------------------------------------------------
    public function destroy(Qualification $qualification, Cohort $cohort, Learner $learner, Submission $submission)
    {
        abort_if($submission->learner_id !== $learner->id, 404);

        Storage::delete($submission->file_path);
        $submission->delete();

        AuditLog::record('submission.deleted', null, ['submission_id' => $submission->id]);

        return redirect()
            ->route('qualifications.cohorts.learners.poe', [$qualification, $cohort, $learner])
            ->with('success', 'Submission deleted.');
    }

    // -------------------------------------------------------
    // MOCK AI MARKING ENGINE
    // -------------------------------------------------------
    private function runMockMarking(\App\Models\Assignment $assignment): array
    {
        $memoText   = $assignment->memo_text ?? '';
        $totalMarks = max(1, (int) ($assignment->total_marks ?? 100));

        $criteria = $this->parseMemo($memoText, $totalMarks);

        $questions    = [];
        $totalAwarded = 0;

        foreach ($criteria as $crit) {
            // Bias toward passing (70 % of the time award 60-100 %)
            $pct     = rand(1, 10) <= 7
                ? rand(60, 100) / 100
                : rand(20, 59)  / 100;
            $awarded = (int) round($crit['max_marks'] * $pct);
            $totalAwarded += $awarded;

            $questions[] = [
                'criterion' => $crit['text'],
                'max_marks' => $crit['max_marks'],
                'awarded'   => $awarded,
                'comment'   => $this->mockComment($pct),
            ];
        }

        $pctTotal = $totalMarks > 0 ? ($totalAwarded / $totalMarks) : 0;
        $verdict  = $pctTotal >= 0.5 ? 'COMPETENT' : 'NOT_YET_COMPETENT';

        return [
            'questions'     => $questions,
            'verdict'       => $verdict,
            'total_awarded' => $totalAwarded,
            'total_marks'   => $totalMarks,
        ];
    }

    private function parseMemo(string $text, int $totalMarks): array
    {
        if (trim($text) === '') {
            return [['text' => 'General competency assessment', 'max_marks' => $totalMarks]];
        }

        // Try to find numbered lines: "1.", "1)", "Q1.", etc.
        preg_match_all('/(?:^|\n)\s*(?:\d+[\.\)]|Q\d+\.?)\s*(.+)/i', $text, $matches);
        $lines = array_filter(array_map('trim', $matches[1] ?? []));

        // Fall back to non-empty lines
        if (count($lines) < 2) {
            $lines = array_filter(array_map('trim', explode("\n", $text)));
        }

        $lines = array_values(array_slice($lines, 0, 10)); // max 10 criteria

        if (empty($lines)) {
            return [['text' => 'General competency assessment', 'max_marks' => $totalMarks]];
        }

        // Distribute marks as evenly as possible
        $n    = count($lines);
        $base = (int) floor($totalMarks / $n);
        $rem  = $totalMarks - ($base * $n);

        $criteria = [];
        foreach ($lines as $i => $line) {
            $criteria[] = [
                'text'      => $line,
                'max_marks' => $base + ($i === 0 ? $rem : 0),
            ];
        }

        return $criteria;
    }

    private function mockComment(float $pct): string
    {
        if ($pct >= 0.85) {
            $pool = [
                'Excellent response — demonstrates thorough understanding.',
                'Comprehensive answer with accurate application of concepts.',
                'Strong evidence of competency; all key points addressed.',
            ];
        } elseif ($pct >= 0.60) {
            $pool = [
                'Satisfactory — key criteria met with minor gaps.',
                'Adequate response; core concepts demonstrated.',
                'Meets the minimum standard; some detail could be expanded.',
            ];
        } else {
            $pool = [
                'Insufficient evidence of competency for this criterion.',
                'Key criteria not adequately addressed.',
                'Response does not demonstrate the required standard.',
            ];
        }

        return $pool[array_rand($pool)];
    }

    private function scoreVariance(array $questions): float
    {
        if (empty($questions)) return 0.0;
        $pcts = array_map(fn($q) => $q['max_marks'] > 0 ? $q['awarded'] / $q['max_marks'] : 0, $questions);
        $mean = array_sum($pcts) / count($pcts);
        $var  = array_sum(array_map(fn($p) => ($p - $mean) ** 2, $pcts)) / count($pcts);
        return $var;
    }
}
