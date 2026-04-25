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

        $submission->load(['assignment.qualificationModules', 'markingResult', 'assessor']);
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

    /**
     * Default system grading philosophy — used when the assessor has not
     * written assignment-specific AI instructions.
     */
    private const DEFAULT_AI_INSTRUCTIONS =
        'Use the marking memo as a guiding framework only, not a rigid answer key. ' .
        'Credit any response that demonstrates genuine understanding of the core concept, ' .
        'even if the wording differs from the memo. ' .
        'Only assess within the scope of the module being marked — do not penalise the ' .
        'learner for knowledge gaps that belong to other modules. ' .
        'Prioritise demonstrated practical application over verbatim theory recall. ' .
        'Where a learner\'s answer is partially correct, award proportional marks.';

    private function runMockMarking(\App\Models\Assignment $assignment): array
    {
        $memoText   = $assignment->memo_text ?? '';
        $totalMarks = max(1, (int) ($assignment->total_marks ?? 100));

        // Resolve effective grading instructions
        $instructions = trim($assignment->ai_instructions ?? '')
            ?: self::DEFAULT_AI_INSTRUCTIONS;

        // Determine whether instructions suggest a lenient (guide-only) approach
        $isLenient = $this->instructionsAreLenient($instructions);

        // Load mapped modules for scope context
        $modules = $assignment->qualificationModules()->get();
        $moduleContext = $modules->map(fn($m) =>
            strtoupper($m->module_type) . ': ' . $m->title
        )->implode(' | ');

        $criteria = $this->parseMemo($memoText, $totalMarks);

        $questions    = [];
        $totalAwarded = 0;

        foreach ($criteria as $crit) {
            // Lenient/guide-only mode: skewed more towards higher marks
            if ($isLenient) {
                $pct = rand(1, 10) <= 8
                    ? rand(65, 100) / 100   // 80 % chance of good marks
                    : rand(35, 64)  / 100;
            } else {
                $pct = rand(1, 10) <= 7
                    ? rand(60, 100) / 100
                    : rand(20, 59)  / 100;
            }

            $awarded = (int) round($crit['max_marks'] * $pct);
            $totalAwarded += $awarded;

            $questions[] = [
                'criterion' => $crit['text'],
                'max_marks' => $crit['max_marks'],
                'awarded'   => $awarded,
                'comment'   => $this->mockComment($pct, $moduleContext, $isLenient),
            ];
        }

        $pctTotal = $totalMarks > 0 ? ($totalAwarded / $totalMarks) : 0;
        $verdict  = $pctTotal >= 0.5 ? 'COMPETENT' : 'NOT_YET_COMPETENT';

        return [
            'questions'      => $questions,
            'verdict'        => $verdict,
            'total_awarded'  => $totalAwarded,
            'total_marks'    => $totalMarks,
            'instructions'   => $instructions,
            'module_context' => $moduleContext,
        ];
    }

    /**
     * Returns true if the instructions signal a flexible / guide-only grading approach.
     */
    private function instructionsAreLenient(string $instructions): bool
    {
        $leniencyKeywords = [
            'guide', 'framework', 'flexible', 'credit', 'alternative',
            'proportional', 'practical', 'application', 'not a rigid',
            'not rigid', 'not penalise', 'not penalize', 'scope only',
        ];
        $lower = strtolower($instructions);
        foreach ($leniencyKeywords as $kw) {
            if (str_contains($lower, $kw)) return true;
        }
        return false;
    }

    private function parseMemo(string $text, int $totalMarks): array
    {
        if (trim($text) === '') {
            return [['text' => 'General competency assessment', 'max_marks' => $totalMarks]];
        }

        $rawLines = array_values(array_filter(
            array_map('trim', explode("\n", $text)),
            fn($l) => $l !== ''
        ));

        if (empty($rawLines)) {
            return [['text' => 'General competency assessment', 'max_marks' => $totalMarks]];
        }

        // -----------------------------------------------------------
        // Try to extract per-criterion marks from common memo formats:
        //   "1. Explain X (5)"          trailing bare number
        //   "1. Explain X (5 marks)"    trailing "(N marks)"
        //   "1. Explain X [5]"          trailing [N]
        //   "1. Explain X /5"           trailing /N
        //   "1. Explain X – 5 marks"    em/en dash
        //   "[5] 1. Explain X"          leading bracket
        //   "(5) 1. Explain X"          leading paren
        // -----------------------------------------------------------
        $marksPatterns = [
            '/\(\s*(\d+(?:\.\d+)?)\s*(?:marks?)?\s*\)\s*$/i',   // trailing (N) or (N marks)
            '/\[\s*(\d+(?:\.\d+)?)\s*(?:marks?)?\s*\]\s*$/i',   // trailing [N]
            '/\/\s*(\d+(?:\.\d+)?)\s*$/i',                       // trailing /N
            '/[-–—]\s*(\d+(?:\.\d+)?)\s*(?:marks?)?\s*$/i',      // trailing – N or – N marks
            '/\b(\d+(?:\.\d+)?)\s*(?:marks?)\s*$/i',             // trailing "N marks"
            '/^\s*\[\s*(\d+(?:\.\d+)?)\s*\]/i',                  // leading [N]
            '/^\s*\(\s*(\d+(?:\.\d+)?)\s*\)/i',                  // leading (N)
        ];

        // Strip common question prefixes ("1.", "1)", "Q1.", "Q1:")
        $stripPrefix = fn(string $line): string =>
            preg_replace('/^\s*(?:Q\s*)?\d+\s*[\.\)\:]\s*/i', '', $line);

        $criteria     = [];
        $parsedMarks  = [];
        $marksSum     = 0;

        foreach ($rawLines as $line) {
            $marks    = null;
            $clean    = $line;

            foreach ($marksPatterns as $pattern) {
                if (preg_match($pattern, $clean, $m)) {
                    $marks = (float) $m[1];
                    // Remove the matched marks token from the display text
                    $clean = preg_replace($pattern, '', $clean);
                    break;
                }
            }

            // Strip question number prefix from display text
            $clean = trim($stripPrefix($clean));

            if ($clean === '') continue;

            $criteria[]   = ['text' => $clean, 'raw_marks' => $marks];
            $parsedMarks[] = $marks;
            if ($marks !== null) $marksSum += $marks;
        }

        if (empty($criteria)) {
            return [['text' => 'General competency assessment', 'max_marks' => $totalMarks]];
        }

        // -----------------------------------------------------------
        // Determine whether we successfully parsed individual marks
        // -----------------------------------------------------------
        $parsedCount = count(array_filter($parsedMarks, fn($v) => $v !== null));
        $allParsed   = $parsedCount === count($criteria);

        if ($allParsed && $marksSum > 0) {
            // Scale parsed marks to match totalMarks if they differ
            $scale = ($marksSum != $totalMarks) ? ($totalMarks / $marksSum) : 1.0;

            $result   = [];
            $assigned = 0;
            $last     = count($criteria) - 1;

            foreach ($criteria as $i => $crit) {
                if ($i === $last) {
                    // Give remainder to last criterion to avoid rounding drift
                    $m = $totalMarks - $assigned;
                } else {
                    $m = (int) round($crit['raw_marks'] * $scale);
                }
                $assigned += $m;
                $result[] = ['text' => $crit['text'], 'max_marks' => max(1, $m)];
            }

            return $result;
        }

        // -----------------------------------------------------------
        // Fallback: distribute marks evenly across all criteria
        // (no cap — show all questions found in the memo)
        // -----------------------------------------------------------
        $n    = count($criteria);
        $base = (int) floor($totalMarks / $n);
        $rem  = $totalMarks - ($base * $n);

        $result = [];
        foreach ($criteria as $i => $crit) {
            $result[] = [
                'text'      => $crit['text'],
                'max_marks' => $base + ($i === 0 ? $rem : 0),
            ];
        }

        return $result;
    }

    private function mockComment(float $pct, string $moduleContext, bool $lenient): string
    {
        $scopeNote = $moduleContext
            ? ' (assessed within module scope: ' . $moduleContext . ')'
            : '';

        if ($pct >= 0.85) {
            $pool = [
                'Excellent response — demonstrates thorough understanding of the concept' . ($moduleContext ? ' as required by this module' : '') . '.',
                'Comprehensive answer with accurate application; all key points addressed.',
                'Strong evidence of competency within the assessed scope.',
            ];
        } elseif ($pct >= 0.60) {
            $pool = $lenient
                ? [
                    'Adequate response — core concept demonstrated; wording differs from memo but understanding is evident.',
                    'Satisfactory practical application; minor gaps do not affect overall competency for this criterion.',
                    'Key idea present; alternative framing accepted as per grading instructions.',
                ]
                : [
                    'Satisfactory — key criteria met with minor gaps.',
                    'Adequate response; core concepts demonstrated.',
                    'Meets the minimum standard; some detail could be expanded.',
                ];
        } else {
            $pool = $lenient
                ? [
                    'Insufficient evidence of understanding within the module scope' . ($moduleContext ? ' (' . $moduleContext . ')' : '') . '.',
                    'Response does not adequately address the criterion, even allowing for alternative phrasing.',
                    'Core concept not demonstrated; marks withheld within assessed scope only.',
                ]
                : [
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
