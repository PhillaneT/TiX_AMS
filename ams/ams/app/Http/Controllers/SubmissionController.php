<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientCreditsException;
use App\Models\AiUsage;
use App\Models\AuditLog;
use App\Models\BillingAccount;
use App\Models\Cohort;
use App\Models\Learner;
use App\Models\MarkingResult;
use App\Models\Qualification;
use App\Models\Submission;
use App\Services\Billing\BillingService;
use App\Services\Pdf\Annotator;
use App\Services\Pdf\AssessorDeclarationGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
    public function mark(Request $request, Qualification $qualification, Cohort $cohort, Learner $learner, Submission $submission, BillingService $billing)
    {
        abort_if($submission->learner_id !== $learner->id, 404);

        // ── Credit check & deduction (single source of truth for billing) ───
        $user = $request->user();
        $account = $user?->billing_account_id
            ? BillingAccount::find($user->billing_account_id)
            : null;

        if (! $account) {
            return back()->with('error',
                'No billing account is linked to your user. Please contact support.');
        }

        try {
            $billing->deduct(
                $account,
                1,
                BillingService::REASON_AI_MARK,
                $submission,
            );
        } catch (InsufficientCreditsException $e) {
            return redirect()
                ->route('billing.topup')
                ->with('error',
                    'You\'re out of AI marks. Top up or upgrade to keep using AI marking. Manual marking still works.');
        }

        $isRemark = in_array($submission->status, ['review_required', 'signed_off', 'marking', 'queued']);

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

        // updateOrCreate so re-marking overwrites the existing result cleanly
        $result = MarkingResult::updateOrCreate(
            ['submission_id' => $submission->id],
            [
                'user_id'          => auth()->id(),
                'ai_recommendation'=> $marking['verdict'],
                'confidence'       => $confidence,
                'questions_json'   => $marking['questions'],
                'annotations_json' => [],
                'mock_mode'        => $mockMode,
                'assessor_override'=> false,
                'final_verdict'    => $marking['verdict'],
                'assessor_name'    => auth()->user()->name,
                // Clear any previous sign-off fields on re-mark
                'signed_off_at'    => null,
                'etqa_registration'=> null,
                'assessment_provider' => null,
                'moderation_notes' => null,
                'cover_pdf_path'   => null,
            ]
        );

        $submission->update([
            'status'        => 'review_required',
            'marked_at'     => now(),
            'signed_off_at' => null,
            'lms_pushed_at' => null,
        ]);

        // Log AI usage record (mock — but credits ARE charged so the balance flow
        // can be exercised end-to-end before real Anthropic calls are wired up)
        AiUsage::create([
            'submission_id'      => $submission->id,
            'user_id'            => auth()->id(),
            'billing_account_id' => $account->id,
            'tokens_input'       => rand(800, 1500),
            'tokens_output'      => rand(300, 600),
            'credits_charged'    => 1,
            'mock_mode'          => true,
            'status'             => 'success',
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
            'final_verdict'      => ['required', 'in:COMPETENT,NOT_YET_COMPETENT'],
            'moderation_notes'   => ['nullable', 'string', 'max:2000'],
            'etqa_registration'  => ['nullable', 'string', 'max:100'],
            'assessment_provider'=> ['nullable', 'string', 'max:200'],
        ]);

        $override = $request->input('final_verdict') !== $submission->markingResult?->ai_recommendation;

        // ── Persist stamps + mark edits sent from the sign-off form ──────────
        // The view injects the current in-memory state into hidden fields so we
        // always have the very latest data regardless of whether "Save" was clicked.
        $result = $submission->markingResult;
        if ($result) {
            $resultUpdates = [
                'final_verdict'      => $request->input('final_verdict'),
                'assessor_override'  => $override,
                'assessor_name'      => auth()->user()->name,
                'etqa_registration'  => $request->input('etqa_registration'),
                'assessment_provider'=> $request->input('assessment_provider'),
                'moderation_notes'   => $request->input('moderation_notes'),
                'signed_off_at'      => now(),
            ];

            // Merge in annotations (stamps) from hidden field
            if ($request->filled('annotations_json')) {
                $stamps = json_decode($request->input('annotations_json'), true);
                if (is_array($stamps)) {
                    $resultUpdates['annotations_json'] = array_map(fn($s) => [
                        'page'            => (int)   ($s['page']   ?? 1),
                        'x_pct'           => round((float) ($s['x_pct'] ?? 0), 4),
                        'y_pct'           => round((float) ($s['y_pct'] ?? 0), 4),
                        'type'            => in_array($s['type'] ?? '', ['tick','cross']) ? $s['type'] : 'tick',
                        'criterion_index' => isset($s['criterion_index']) ? (int) $s['criterion_index'] : null,
                        'criterion'       => mb_substr((string) ($s['criterion'] ?? ''), 0, 100),
                    ], $stamps);
                }
            }

            // Merge in per-criterion mark/comment edits from hidden field
            if ($request->filled('questions_json')) {
                $edits    = json_decode($request->input('questions_json'), true);
                $existing = $result->questions_json ?? [];
                if (is_array($edits) && is_array($existing)) {
                    foreach ($edits as $idx => $q) {
                        if (!isset($existing[$idx])) continue;
                        if (isset($q['awarded'])) {
                            $existing[$idx]['awarded'] = max(0, min(
                                (int) ($existing[$idx]['max_marks'] ?? 0),
                                (int) $q['awarded']
                            ));
                        }
                        if (array_key_exists('comment', $q)) {
                            $existing[$idx]['comment'] = mb_substr((string) $q['comment'], 0, 500);
                        }
                    }
                    $resultUpdates['questions_json'] = $existing;
                }
            }

            $result->update($resultUpdates);
        }

        $submission->update([
            'status'        => 'signed_off',
            'signed_off_at' => now(),
        ]);

        // Bake final annotations into a locked PDF, then prepend Declaration + Marking Report
        $this->bakeAnnotatedPdf($submission);
        $this->bakeAssessorDeclaration($submission);

        AuditLog::record('submission.signed_off', $submission, [
            'verdict'  => $request->input('final_verdict'),
            'override' => $override,
        ]);

        return redirect()
            ->route('qualifications.cohorts.learners.submissions.show', [$qualification, $cohort, $learner, $submission])
            ->with('success', 'Result signed off. Annotated PDF generated.');
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

        // --- Build criteria from marking method ---

        // Priority 1: per-question structured memo
        $structuredQuestions = $assignment->questions()->get();

        // Priority 2: rubric criteria
        $rubricCriteria = ($assignment->memo_type === 'rubric' && !empty($assignment->rubric_json))
            ? $assignment->rubric_json
            : null;

        if ($structuredQuestions->isNotEmpty()) {
            $criteria = $structuredQuestions->map(fn($q) => [
                'text'      => ($q->label ? "[{$q->label}] " : '') . $q->question_text,
                'max_marks' => max(1, (int) $q->marks),
                'expected_answer'  => $q->expected_answer,
                'ai_grading_notes' => $q->ai_grading_notes,
            ])->toArray();
            $totalMarks = max(1, $structuredQuestions->sum('marks'));
        } elseif ($rubricCriteria) {
            $criteria = [];
            foreach ($rubricCriteria as $rc) {
                $maxScore = 0;
                foreach ($rc['levels'] ?? [] as $level) {
                    $maxScore = max($maxScore, (float) ($level['score'] ?? 0));
                }
                $criteria[] = [
                    'text'      => $rc['title'] ?? 'Criterion',
                    'max_marks' => max(1, (int) ceil($maxScore)),
                    'expected_answer'  => $rc['description'] ?? null,
                    'ai_grading_notes' => 'Performance levels: ' . implode(' | ', array_map(
                        fn($l) => ($l['score'] ?? 0) . 'pts — ' . ($l['description'] ?? ''),
                        $rc['levels'] ?? []
                    )),
                ];
            }
            $totalMarks = max(1, array_sum(array_column($criteria, 'max_marks')));
        } else {
            $memoText   = $assignment->memo_text ?? '';
            $totalMarks = max(1, (int) ($assignment->total_marks ?? 100));
            $criteria   = $this->parseMemo($memoText, $totalMarks);
        }

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
                'criterion'        => $crit['text'],
                'max_marks'        => $crit['max_marks'],
                'awarded'          => $awarded,
                'comment'          => $this->mockComment($pct, $moduleContext, $isLenient),
                'expected_answer'  => $crit['expected_answer'] ?? null,
                'ai_grading_notes' => $crit['ai_grading_notes'] ?? null,
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

        // ------------------------------------------------------------------
        // FORMAT 1 — Moodle Marking Guide
        // Detected by the presence of "Maximum score:" lines.
        // Block structure (repeats per criterion):
        //   <Criterion name>
        //   Description for students        ← optional structural header
        //   <student-facing text>           ← ignored by parser
        //   Description for Markers         ← optional structural header
        //   <marker answer text>            ← ignored by parser
        //   Maximum score: N                ← marks for this criterion
        // ------------------------------------------------------------------
        if (preg_match('/^\s*maximum score\s*:?\s*\d/im', $text)) {
            $parsed = $this->parseMoodleMarkingGuide($text, $totalMarks);
            if (! empty($parsed)) return $parsed;
        }

        // ------------------------------------------------------------------
        // FORMAT 2 — Inline marks on the same line as the question
        // Supports: trailing (N), [N], /N, – N marks, "N marks", leading [N], (N)
        // ------------------------------------------------------------------
        $rawLines = array_values(array_filter(
            array_map('trim', explode("\n", $text)),
            fn($l) => $l !== ''
        ));

        if (empty($rawLines)) {
            return [['text' => 'General competency assessment', 'max_marks' => $totalMarks]];
        }

        $marksPatterns = [
            '/\(\s*(\d+(?:\.\d+)?)\s*(?:marks?)?\s*\)\s*$/i',
            '/\[\s*(\d+(?:\.\d+)?)\s*(?:marks?)?\s*\]\s*$/i',
            '/\/\s*(\d+(?:\.\d+)?)\s*$/i',
            '/[-–—]\s*(\d+(?:\.\d+)?)\s*(?:marks?)?\s*$/i',
            '/\b(\d+(?:\.\d+)?)\s*(?:marks?)\s*$/i',
            '/^\s*\[\s*(\d+(?:\.\d+)?)\s*\]/i',
            '/^\s*\(\s*(\d+(?:\.\d+)?)\s*\)/i',
        ];

        $stripPrefix = fn(string $line): string =>
            preg_replace('/^\s*(?:Q\s*)?\d+\s*[\.\)\:]\s*/i', '', $line);

        // Detects lines that are pure section headings with no real question content.
        // Examples to skip: "KM-01-KT01:", "Section 1:", "Part A — Knowledge"
        // Examples to keep: "KM-01-KT01: What is data science?" (has substantive text after the code)
        $isHeadingOnly = function (string $clean): bool {
            // Pure criterion code with nothing after it: KM-01-KT01 or KM-01-KT01:
            if (preg_match('/^[A-Z]{2,5}-\d{2,3}-[A-Z]{2,5}\d*(?:[.\d]*)?:?\s*$/i', $clean)) {
                return true;
            }
            // Short line ending with colon and no question mark — almost certainly a heading
            if (mb_strlen($clean) <= 60 && str_ends_with(rtrim($clean), ':') && ! str_contains($clean, '?')) {
                // Only skip if nothing substantive follows the last colon
                $afterColon = trim((string) substr($clean, (int) strrpos($clean, ':') + 1));
                if (mb_strlen($afterColon) < 8) {
                    return true;
                }
            }
            return false;
        };

        // Extract a leading criterion-code prefix (e.g. "KM-01-KT01:") and return
        // [label|null, question_text]. Keeps display text clean.
        $extractLabel = function (string $clean): array {
            if (preg_match('/^([A-Z]{2,5}-\d{2,3}-[A-Z]{2,5}\d*(?:[.\d]*)?)\s*:\s*(.+)/su', $clean, $m)) {
                return ['[' . $m[1] . '] ' . trim($m[2])];
            }
            return [$clean];
        };

        $criteria    = [];
        $parsedMarks = [];
        $marksSum    = 0;

        foreach ($rawLines as $line) {
            $marks = null;
            $clean = $line;

            foreach ($marksPatterns as $pattern) {
                if (preg_match($pattern, $clean, $m)) {
                    $marks = (float) $m[1];
                    $clean = preg_replace($pattern, '', $clean);
                    break;
                }
            }

            $clean = trim($stripPrefix($clean));
            if ($clean === '' || $isHeadingOnly($clean)) continue;

            // Normalise: wrap criterion-code prefix in [brackets] so the view can
            // render it as a small badge separate from the actual question text.
            [$clean] = $extractLabel($clean);

            $criteria[]    = ['text' => $clean, 'raw_marks' => $marks];
            $parsedMarks[] = $marks;
            if ($marks !== null) $marksSum += $marks;
        }

        if (empty($criteria)) {
            return [['text' => 'General competency assessment', 'max_marks' => $totalMarks]];
        }

        $parsedCount = count(array_filter($parsedMarks, fn($v) => $v !== null));
        $allParsed   = $parsedCount === count($criteria);

        if ($allParsed && $marksSum > 0) {
            $scale    = ($marksSum != $totalMarks) ? ($totalMarks / $marksSum) : 1.0;
            $result   = [];
            $assigned = 0;
            $last     = count($criteria) - 1;

            foreach ($criteria as $i => $crit) {
                $m = ($i === $last)
                    ? $totalMarks - $assigned
                    : (int) round($crit['raw_marks'] * $scale);
                $assigned += $m;
                $result[] = ['text' => $crit['text'], 'max_marks' => max(1, $m)];
            }

            return $result;
        }

        // ------------------------------------------------------------------
        // FORMAT 3 — Plain list, no mark annotations: distribute evenly
        // ------------------------------------------------------------------
        $n    = count($criteria);
        $base = (int) floor($totalMarks / $n);
        $rem  = $totalMarks - ($base * $n);

        return array_map(fn($crit, $i) => [
            'text'      => $crit['text'],
            'max_marks' => $base + ($i === 0 ? $rem : 0),
        ], $criteria, array_keys($criteria));
    }

    /**
     * Parse a Moodle Marking Guide memo into criterion blocks.
     *
     * Each block looks like:
     *   <Criterion name line>
     *   Description for students          <- structural header, skip
     *   <student description text>        <- skip
     *   Description for Markers           <- structural header, skip
     *   <marker description text>         <- skip
     *   Maximum score: N                  <- end of block, extract N
     *
     * "Maximum score: N" values are used as relative weights and scaled
     * to the assignment's totalMarks.
     */
    private function parseMoodleMarkingGuide(string $text, int $totalMarks): array
    {
        $lines = array_map('trim', explode("\n", $text));

        // Lines to skip entirely (Moodle structural headers and noise)
        $skipPatterns = [
            '/^description for students/i',
            '/^description for markers?/i',
        ];

        $criteria     = [];
        $currentName  = null;
        $inDescBlock  = false;   // true while inside a student/marker description

        foreach ($lines as $line) {
            if ($line === '') continue;

            // ---- "Maximum score: N" → end of current criterion block ----
            if (preg_match('/^maximum score\s*:?\s*(\d+(?:\.\d+)?)/i', $line, $m)) {
                if ($currentName !== null) {
                    $criteria[] = [
                        'text'      => $currentName,
                        'raw_marks' => (float) $m[1],
                    ];
                }
                $currentName = null;
                $inDescBlock = false;
                continue;
            }

            // ---- Structural headers ("Description for …") ----
            $isHeader = false;
            foreach ($skipPatterns as $pat) {
                if (preg_match($pat, $line)) {
                    $isHeader    = true;
                    $inDescBlock = true;   // everything until "Maximum score:" is descriptive
                    break;
                }
            }
            if ($isHeader) continue;

            // ---- Inside a description block → skip content lines ----
            if ($inDescBlock) continue;

            // ---- Otherwise: first non-empty, non-header line is the criterion name ----
            if ($currentName === null) {
                // Strip leading question numbers ("1.", "Q1.", "1)", "Q1:")
                $currentName = trim(preg_replace('/^\s*(?:Q\s*)?\d+\s*[\.\)\:]\s*/i', '', $line));
            }
            // Additional lines before any header appear → keep only the first (the name)
        }

        // Handle a trailing block with no final "Maximum score:" line
        // (edge case: last criterion might be incomplete — ignore it)

        if (empty($criteria)) return [];

        // Scale Moodle's internal scores (often 0-N per criterion) to the
        // assignment's totalMarks, preserving relative weight.
        $moodleTotal = array_sum(array_column($criteria, 'raw_marks'));

        if ($moodleTotal <= 0) {
            // All scores are zero — distribute evenly
            $n    = count($criteria);
            $base = (int) floor($totalMarks / $n);
            $rem  = $totalMarks - ($base * $n);

            return array_map(fn($crit, $i) => [
                'text'      => $crit['text'],
                'max_marks' => $base + ($i === 0 ? $rem : 0),
            ], $criteria, array_keys($criteria));
        }

        $scale    = $totalMarks / $moodleTotal;
        $result   = [];
        $assigned = 0;
        $last     = count($criteria) - 1;

        foreach ($criteria as $i => $crit) {
            $m = ($i === $last)
                ? $totalMarks - $assigned
                : (int) round($crit['raw_marks'] * $scale);
            $assigned += $m;
            $result[] = ['text' => $crit['text'], 'max_marks' => max(1, $m)];
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

    // -------------------------------------------------------
    // Serve the original submission PDF to the browser
    // (files are stored in private/ storage — not public)
    // -------------------------------------------------------
    public function serveFile(
        Qualification $qualification, Cohort $cohort,
        Learner $learner, Submission $submission
    ) {
        abort_if($submission->learner_id !== $learner->id, 404);

        $absolutePath = Storage::path($submission->file_path);

        abort_unless(file_exists($absolutePath), 404, 'Submission file not found on disk.');

        return response()->file($absolutePath, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . rawurlencode($submission->original_filename) . '"',
            'Cache-Control'       => 'private, no-store',
        ]);
    }

    // -------------------------------------------------------
    // Serve the final annotated PDF (stamps baked in, no cover page)
    // -------------------------------------------------------
    public function serveAnnotated(
        Qualification $qualification, Cohort $cohort,
        Learner $learner, Submission $submission
    ) {
        abort_if($submission->learner_id !== $learner->id, 404);
        $path = Storage::path($submission->markingResult?->annotated_pdf_path ?? '');
        abort_unless($submission->markingResult?->annotated_pdf_path && file_exists($path), 404, 'Annotated PDF not yet generated.');
        return response()->file($path, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="annotated_' . rawurlencode($submission->original_filename) . '"',
            'Cache-Control'       => 'private, no-store',
        ]);
    }

    // -------------------------------------------------------
    // Serve the combined Declaration + annotated PDF (return-to-learner copy)
    // -------------------------------------------------------
    public function serveDeclaration(
        Qualification $qualification, Cohort $cohort,
        Learner $learner, Submission $submission
    ) {
        abort_if($submission->learner_id !== $learner->id, 404);
        $path = Storage::path($submission->markingResult?->cover_pdf_path ?? '');
        abort_unless($submission->markingResult?->cover_pdf_path && file_exists($path), 404, 'Declaration PDF not yet generated.');
        return response()->file($path, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="declaration_' . rawurlencode($submission->original_filename) . '"',
            'Cache-Control'       => 'private, no-store',
        ]);
    }

    // -------------------------------------------------------
    // Save assessor-edited annotations + per-criterion marks
    // Called via fetch() from the PDF annotation viewer
    // -------------------------------------------------------
    public function saveAnnotations(
        Request $request,
        Qualification $qualification, Cohort $cohort,
        Learner $learner, Submission $submission
    ) {
        abort_if($submission->learner_id !== $learner->id, 404);

        $data = $request->validate([
            'annotations'          => ['present', 'array'],   // 'present' allows empty []
            'annotations.*.page'   => ['required', 'integer', 'min:1'],
            'annotations.*.x_pct'  => ['required', 'numeric', 'min:0', 'max:1'],
            'annotations.*.y_pct'  => ['required', 'numeric', 'min:0', 'max:1'],
            'annotations.*.type'   => ['required', 'in:tick,cross'],
            'questions'            => ['nullable', 'array'],
        ]);

        $stamps = array_map(fn($s) => [
            'page'            => (int) $s['page'],
            'x_pct'           => round((float) $s['x_pct'], 4),
            'y_pct'           => round((float) $s['y_pct'], 4),
            'type'            => $s['type'],
            'criterion_index' => isset($s['criterion_index']) ? (int) $s['criterion_index'] : null,
            'criterion'       => mb_substr((string) ($s['criterion'] ?? ''), 0, 100),
        ], $data['annotations']);

        $result = $submission->markingResult;
        abort_unless($result, 422);

        $updates = ['annotations_json' => $stamps];

        // Also persist any per-criterion mark / comment edits
        if (!empty($data['questions'])) {
            $existing = $result->questions_json ?? [];
            foreach ($data['questions'] as $idx => $q) {
                if (!isset($existing[$idx])) continue;
                if (array_key_exists('awarded', $q)) {
                    $existing[$idx]['awarded'] = max(0, min(
                        (int) $existing[$idx]['max_marks'],
                        (int) $q['awarded']
                    ));
                }
                if (array_key_exists('comment', $q)) {
                    $existing[$idx]['comment'] = mb_substr((string) $q['comment'], 0, 500);
                }
            }
            $updates['questions_json'] = $existing;
        }

        $result->update($updates);

        AuditLog::record('submission.annotations_saved', $submission, [
            'stamp_count' => count($stamps),
        ]);

        return response()->json(['ok' => true, 'count' => count($stamps)]);
    }

    // -------------------------------------------------------
    // PRIVATE HELPERS
    // -------------------------------------------------------

    private function bakeAnnotatedPdf(Submission $submission): void
    {
        $result = $submission->markingResult;
        if (!$result) return;

        $ext = strtolower(pathinfo($submission->original_filename, PATHINFO_EXTENSION));
        if ($ext !== 'pdf') return;

        try {
            $sourcePath = Storage::path($submission->file_path);
            $outDir     = "private/annotated/{$submission->learner_id}/{$submission->id}";
            $outFile    = $outDir . '/annotated_' . $submission->original_filename;
            $outAbs     = Storage::path($outFile);

            if (!is_dir(dirname($outAbs))) {
                mkdir(dirname($outAbs), 0755, true);
            }

            $stamps = $result->annotations_json ?? [];

            // Always produce the annotated file — even with no stamps — so
            // bakeAssessorDeclaration always has a concrete PDF to prepend to.
            (new Annotator())->annotate($sourcePath, $stamps, $outAbs);

            $result->update([
                'annotated_pdf_path' => $outFile,
                'pdf_hash'           => hash_file('sha256', $outAbs),
            ]);
        } catch (\Throwable $e) {
            Log::error('bakeAnnotatedPdf failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            session()->flash('pdf_bake_error', 'Annotated PDF: ' . $e->getMessage());
        }
    }

    private function bakeAssessorDeclaration(Submission $submission): void
    {
        $result = $submission->markingResult;
        if (!$result) return;

        $ext = strtolower(pathinfo($submission->original_filename, PATHINFO_EXTENSION));
        if ($ext !== 'pdf') return;

        try {
            $learner       = $submission->learner;
            $qualification = $submission->assignment->qualification;
            $generator     = new AssessorDeclarationGenerator();

            // ── 1. Assessor Declaration cover page ──────────────────────────
            // Pull the assessor's saved signature + stamp from their profile so
            // every Declaration page (and Marking Report) is signed and stamped
            // automatically. Nullsafe in case this runs without an authed user.
            $assessor = $submission->assessor ?: auth()->user();
            $sigAbs   = ($assessor?->signature_path && \Storage::exists($assessor->signature_path))
                ? \Storage::path($assessor->signature_path) : null;
            $stampAbs = ($assessor?->stamp_path && \Storage::exists($assessor->stamp_path))
                ? \Storage::path($assessor->stamp_path) : null;

            // Built-in rubber-stamp payload (static per assessor; date is dynamic).
            $effectiveEtqa  = $result->etqa_registration ?: ($assessor?->etqa_registration);
            $stampGenerated = ($assessor?->stamp_use_generated && $assessor?->stamp_holder_name)
                ? [
                    'org_top'           => $assessor->stamp_org_top,
                    'org_bottom'        => $assessor->stamp_org_bottom,
                    'role'              => $assessor->stamp_role ?: 'ASSESSOR',
                    'holder_name'       => $assessor->stamp_holder_name,
                    'etqa_registration' => $effectiveEtqa,
                ]
                : null;

            $declData = [
                'qualification_name' => $qualification->name,
                'assignment_name'    => $submission->assignment->name,
                'saqa_id'            => $qualification->saqa_id,
                'seta'               => $qualification->seta,
                'learner_name'       => $learner->full_name,
                'student_no'         => $learner->external_ref ?: $learner->email,
                'assessor_name'      => $result->assessor_name,
                'etqa_registration'  => $result->etqa_registration ?: ($assessor?->etqa_registration),
                'assessment_provider'=> $result->assessment_provider ?: config('app.name'),
                'verdict'            => $result->final_verdict,
                'date'               => $result->signed_off_at,
                'signature_path'     => $sigAbs,
                'stamp_path'         => $stampAbs,
                'stamp_generated'    => $stampGenerated,
            ];
            // ── 2. Marking Report data ───────────────────────────────────────
            $reportData = [
                'assignment_name'   => $submission->assignment->name,
                'learner_name'      => $learner->full_name,
                'student_no'        => $learner->external_ref ?: $learner->email,
                'assessor_name'     => $result->assessor_name,
                'etqa_registration' => $result->etqa_registration ?: ($assessor?->etqa_registration),
                'date'              => $result->signed_off_at,
                'questions'         => $result->questions_json ?? [],
                'verdict'           => $result->final_verdict,
                'signature_path'    => $sigAbs,
                'stamp_path'        => $stampAbs,
                'stamp_generated'   => $stampGenerated,
            ];

            // ── 3. Build final PDF — front pages drawn natively, submission via FPDI ──
            $outDir  = "private/annotated/{$submission->learner_id}/{$submission->id}";
            $outFile = $outDir . '/cover_' . $submission->original_filename;
            $outAbs  = Storage::path($outFile);

            $generator->buildFinalPdfWithStamps(
                $declData,
                $reportData,
                Storage::path($submission->file_path),
                $result->annotations_json ?? [],
                $outAbs
            );

            $result->update(['cover_pdf_path' => $outFile]);
        } catch (\Throwable $e) {
            Log::error('bakeAssessorDeclaration failed: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());
            session()->flash('pdf_bake_error',
                'Declaration PDF: ' . $e->getMessage()
                . ' — ' . basename($e->getFile()) . ':' . $e->getLine()
            );
        }
    }
}
