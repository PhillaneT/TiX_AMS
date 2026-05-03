@extends('layouts.app')

@section('title', 'Marking Result — ' . $submission->assignment->name)
@section('heading', $submission->assignment->name)
@section('breadcrumbs')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-800 transition-colors">Dashboard</a>
    <span class="text-gray-300 mx-0.5">›</span>
    <a href="{{ route('qualifications.index') }}" class="hover:text-gray-800 transition-colors">Qualifications</a>
    <span class="text-gray-300 mx-0.5">›</span>
    <a href="{{ route('qualifications.show', $qualification) }}" class="hover:text-gray-800 transition-colors">{{ $qualification->name }}</a>
    <span class="text-gray-300 mx-0.5">›</span>
    <a href="{{ route('qualifications.cohorts.show', [$qualification, $cohort]) }}" class="hover:text-gray-800 transition-colors">{{ $cohort->name }}</a>
    <span class="text-gray-300 mx-0.5">›</span>
    <a href="{{ route('qualifications.cohorts.learners.poe', [$qualification, $cohort, $learner]) }}" class="hover:text-gray-800 transition-colors">{{ $learner->full_name }}</a>
    <span class="text-gray-300 mx-0.5">›</span>
    <span class="text-gray-700 font-medium">{{ $submission->assignment->name }}</span>
@endsection

@section('content')

@php
    $effectiveInstructions = trim($submission->assignment->ai_instructions ?? '')
        ?: 'Use the marking memo as a guiding framework only, not a rigid answer key. Credit any response that demonstrates genuine understanding of the core concept, even if the wording differs. Only assess within the scope of the module — do not penalise for knowledge gaps from other modules. Prioritise practical application over verbatim theory recall.';
    $mappedModules = $submission->assignment->qualificationModules ?? collect();
@endphp

{{-- Page-specific flash (success + errors are handled by the layout) --}}
@if(session('info'))
<div class="mb-4 px-4 py-3 rounded-lg bg-blue-50 border border-blue-200 text-blue-800 text-sm">{{ session('info') }}</div>
@endif
@if(session('pdf_bake_error'))
<div class="mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm font-mono">
    <strong>PDF generation error:</strong> {{ session('pdf_bake_error') }}
</div>
@endif
@if(session('push_debug'))
<div class="mb-4 px-4 py-2 rounded-lg bg-gray-50 border border-gray-200 text-gray-600 text-xs font-mono">
    <strong>Moodle push detail:</strong> {{ session('push_debug') }}
</div>
@endif

@php
    // ── Status pill colours ─────────────────────────────────────────────
    $stMap = [
        'uploaded'        => ['bg-gray-50 border-gray-300',   'text-gray-500',  'text-gray-700',  'UPLOADED'],
        'queued'          => ['bg-blue-50 border-blue-300',    'text-blue-500',  'text-blue-700',  'QUEUED'],
        'marking'         => ['bg-blue-50 border-blue-300',    'text-blue-500',  'text-blue-700',  'MARKING…'],
        'review_required' => ['bg-amber-50 border-amber-300',  'text-amber-600', 'text-amber-700', 'REVIEW REQUIRED'],
        'signed_off'      => ($result?->final_verdict === 'COMPETENT')
                              ? ['bg-green-50 border-green-300','text-green-600','text-green-700','SIGNED OFF']
                              : ['bg-red-50 border-red-300',    'text-red-600',  'text-red-700',  'SIGNED OFF'],
    ];
    [$stBox, $stSub, $stMain, $stLabel] = $stMap[$submission->status] ?? $stMap['uploaded'];

    $hasCustomRules = trim($submission->assignment->ai_instructions ?? '') !== '';
    $isFromMoodle   = $submission->isFromMoodle();
    $isSignedOff    = $submission->status === 'signed_off' && $result;
    // Bar 2 (actions) only renders once signed off — PDFs, re-open and Moodle push
    // all require a signed-off result. The "From Moodle" pill in the title row
    // already cues unsigned Moodle submissions.
    $hasActions     = $isSignedOff;
@endphp

{{-- ╔══════════════════════════════════════════════════════════════════╗
     ║  HEADER PANEL — title/meta + Status bar + Actions bar             ║
     ║  Two stacked sub-bars with consistent visual language so the      ║
     ║  page reads as one cohesive header instead of three strips.       ║
     ╚══════════════════════════════════════════════════════════════════╝ --}}
<div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-5">

    {{-- ── Title row (with page-level navigation buttons on the right) ── --}}
    <div class="px-5 py-4 flex items-start justify-between gap-4 flex-wrap">
        <div class="min-w-0">
            <h1 class="text-xl font-bold text-gray-900">{{ $submission->assignment->name }}</h1>
            <div class="mt-1 text-sm text-gray-500 flex flex-wrap gap-x-3 gap-y-1 items-center">
                <span>Learner: <strong class="text-gray-700">{{ $learner->full_name }}</strong></span>
                <span class="text-gray-300">&bull;</span>
                <span>File: <strong class="text-gray-700">{{ $submission->original_filename }}</strong></span>
                <span class="text-gray-300">&bull;</span>
                <span>Uploaded {{ $submission->created_at->format('d M Y H:i') }}</span>
                @if($isFromMoodle)
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-orange-50 border border-orange-200 text-orange-700 text-xs font-semibold">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    From Moodle
                </span>
                @endif
            </div>
        </div>

        {{-- Page-level navigation buttons --}}
        <div class="flex items-center gap-2 flex-wrap shrink-0">
            <a href="{{ route('qualifications.cohorts.learners.poe', [$qualification, $cohort, $learner]) }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-300 bg-white
                      text-xs font-semibold text-gray-600 hover:bg-gray-50 hover:border-gray-400 transition shadow-sm">
                ← Back to POE
            </a>

            @if($submission->status === 'review_required' && $result)
            <a href="#signoff-form"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-amber-300 bg-amber-50
                      text-xs font-semibold text-amber-700 hover:bg-amber-100 transition shadow-sm"
               title="Jump to sign-off form">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Sign Off ↓
            </a>
            @endif

            <button id="btn-fullscreen" type="button"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-300 bg-white
                           text-xs font-semibold text-gray-600 hover:bg-gray-50 hover:border-gray-400 transition shadow-sm"
                    title="Enter fullscreen marking mode">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M8 3H5a2 2 0 00-2 2v3m18 0V5a2 2 0 00-2-2h-3m0 18h3a2 2 0 002-2v-3M3 16v3a2 2 0 002 2h3"/>
                </svg>
                Fullscreen
            </button>
        </div>
    </div>

    {{-- ── BAR 1: STATUS — one tidy row of equal-height pills ──────────
         items-stretch forces every pill to the height of the tallest one.
         The Grading Rules dropdown body is position:absolute, so opening
         it does NOT push siblings taller. ──────────────────────────── --}}
    <div class="px-5 py-3 border-t border-gray-100 bg-gray-50/40 flex flex-wrap items-stretch gap-2">

        {{-- Submission Status --}}
        <div class="inline-flex flex-col justify-center px-3.5 py-2 rounded-lg border-2 {{ $stBox }} min-w-[140px]">
            <span class="text-[10px] uppercase tracking-wide font-semibold {{ $stSub }}">Status</span>
            <span class="text-sm font-bold {{ $stMain }} leading-tight">{{ $stLabel }}</span>
            @if($submission->signed_off_at)
            <span class="text-[10px] {{ $stSub }} leading-tight">{{ $submission->signed_off_at->format('d M Y') }}</span>
            @endif
        </div>

        {{-- AI Recommendation --}}
        @if($result)
        @php $rec = $result->ai_recommendation; $isC = $rec === 'COMPETENT'; @endphp
        <div class="inline-flex flex-col justify-center px-3.5 py-2 rounded-lg border-2 {{ $isC ? 'bg-green-50 border-green-300' : 'bg-red-50 border-red-300' }} min-w-[170px]">
            <span class="text-[10px] uppercase tracking-wide font-semibold {{ $isC ? 'text-green-600' : 'text-red-600' }}">AI Recommendation</span>
            <span class="text-sm font-bold {{ $isC ? 'text-green-700' : 'text-red-700' }} leading-tight">
                {{ $isC ? '✓ COMPETENT' : '✗ NOT YET COMPETENT' }}
            </span>
            <span class="text-[10px] {{ $isC ? 'text-green-600/70' : 'text-red-600/70' }} leading-tight">
                Confidence: {{ $result->confidence }}@if($result->mock_mode) · <span class="font-semibold">MOCK</span>@endif
            </span>
        </div>
        @endif

        {{-- Final Verdict (signed off only — emphasised with ring) --}}
        @if($isSignedOff)
        @php $fc = $result->final_verdict === 'COMPETENT'; @endphp
        <div class="inline-flex flex-col justify-center px-3.5 py-2 rounded-lg border-2 {{ $fc ? 'bg-green-100 border-green-400' : 'bg-red-100 border-red-400' }} ring-2 ring-offset-1 {{ $fc ? 'ring-green-200' : 'ring-red-200' }} min-w-[170px]">
            <span class="text-[10px] uppercase tracking-wide font-bold {{ $fc ? 'text-green-700' : 'text-red-700' }}">★ Final Verdict</span>
            <span class="text-sm font-black {{ $fc ? 'text-green-800' : 'text-red-800' }} leading-tight">
                {{ $fc ? '✓ COMPETENT' : '✗ NOT YET COMPETENT' }}
            </span>
            <span class="text-[10px] {{ $fc ? 'text-green-700/70' : 'text-red-700/70' }} leading-tight">
                By {{ $result->assessor_name }}
            </span>
        </div>
        @endif

        {{-- Assessor override pill (small, only when present) --}}
        @if($isSignedOff && $result->assessor_override)
        <div class="inline-flex items-center px-2.5 py-2 rounded-lg border border-yellow-300 bg-yellow-50 text-[11px] font-semibold text-yellow-800" title="Assessor verdict differs from AI recommendation">
            ⚠ Override
        </div>
        @endif

        {{-- Grading Rules (floating dropdown — does not affect bar layout) --}}
        <details class="ml-auto relative grading-rules-card">
            <summary class="px-3.5 py-2 rounded-lg border-2 {{ $hasCustomRules ? 'border-blue-300 bg-blue-50 hover:bg-blue-100' : 'border-gray-300 bg-gray-50 hover:bg-gray-100' }} cursor-pointer select-none list-none flex flex-col justify-center transition">
                <span class="text-[10px] uppercase tracking-wide font-semibold {{ $hasCustomRules ? 'text-blue-600' : 'text-gray-500' }}">Grading Rules</span>
                <span class="text-sm font-bold {{ $hasCustomRules ? 'text-blue-700' : 'text-gray-700' }} flex items-center gap-1 leading-tight">
                    @if($hasCustomRules)
                        <svg class="w-3 h-3 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                        Custom
                    @else
                        Default
                    @endif
                    <svg class="w-3 h-3 rules-chevron text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                    </svg>
                </span>
            </summary>
            <div class="absolute right-0 top-full mt-1 w-80 z-30 rounded-lg border-2 {{ $hasCustomRules ? 'border-blue-300 bg-blue-50' : 'border-gray-300 bg-white' }} shadow-lg p-3.5">
                @if($mappedModules->isNotEmpty())
                <div class="mb-2">
                    <span class="text-xs {{ $hasCustomRules ? 'text-blue-600' : 'text-gray-600' }} font-semibold">Module Scope: </span>
                    @foreach($mappedModules as $m)
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-bold mr-1
                            {{ ['KM'=>'bg-blue-100 text-blue-800','PM'=>'bg-green-100 text-green-800','WM'=>'bg-orange-100 text-orange-800','US'=>'bg-purple-100 text-purple-800'][strtoupper($m->module_type)] ?? 'bg-gray-100 text-gray-700' }}">
                            {{ strtoupper($m->module_type) }}
                        </span>
                        <span class="text-xs {{ $hasCustomRules ? 'text-blue-700' : 'text-gray-700' }}">{{ $m->title }}</span>
                    @endforeach
                </div>
                <hr class="my-2 {{ $hasCustomRules ? 'border-blue-200' : 'border-gray-200' }}">
                @endif
                @if($hasCustomRules)
                    <p class="text-[10px] uppercase tracking-wide font-semibold text-green-700 mb-1">Custom rules from assignment</p>
                    <p class="text-xs text-blue-700 leading-relaxed whitespace-pre-line">{{ $submission->assignment->ai_instructions }}</p>
                @else
                    <p class="text-[10px] uppercase tracking-wide font-semibold text-gray-500 mb-1">System default (no custom rules on this assignment)</p>
                    <p class="text-xs text-gray-700 leading-relaxed">{{ $effectiveInstructions }}</p>
                    <a href="{{ route('qualifications.assignments.edit', [$qualification, $submission->assignment]) }}"
                       class="inline-block mt-2 text-xs text-orange-600 hover:text-orange-700 font-medium">
                        + Add custom grading rules to this assignment
                    </a>
                @endif
            </div>
        </details>
    </div>

    {{-- ── BAR 2: ACTIONS — clean toolbar, only when actions exist ── --}}
    @if($hasActions)
    <div class="px-5 py-3 border-t border-gray-100 flex flex-wrap items-center gap-2">
        <span class="text-[10px] uppercase tracking-wide font-semibold text-gray-500 mr-1">Actions</span>

        {{-- Sign-off PDF actions --}}
        @if($isSignedOff)
            @if($result->cover_pdf_path)
            <a href="{{ route('qualifications.cohorts.learners.submissions.declaration', [$qualification, $cohort, $learner, $submission]) }}"
               target="_blank"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-[#1e3a5f] hover:bg-[#162d4a] text-white text-xs font-semibold transition shadow-sm"
               title="Declaration cover + marked PDF (return to learner)">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0121 9.414V19a2 2 0 01-2 2z"/></svg>
                Declaration PDF
            </a>
            @endif

            @if($result->annotated_pdf_path)
            <a href="{{ route('qualifications.cohorts.learners.submissions.annotated', [$qualification, $cohort, $learner, $submission]) }}"
               target="_blank"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-xs font-semibold transition shadow-sm"
               title="Annotated submission only (no cover)">
                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0121 9.414V19a2 2 0 01-2 2z"/></svg>
                Annotated PDF
            </a>
            @endif

            @if(! $result->cover_pdf_path && ! $result->annotated_pdf_path)
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-dashed border-gray-300 bg-white text-xs text-gray-500 italic"
                  title="Submission may not have been a PDF, or no stamps were placed.">
                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                No PDF generated
            </span>
            @endif

            <form method="POST"
                  action="{{ route('qualifications.cohorts.learners.submissions.reopen', [$qualification, $cohort, $learner, $submission]) }}"
                  onsubmit="return confirm('Re-open this submission for re-assessment?')">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-300 bg-white text-xs font-semibold text-gray-600 hover:text-orange-700 hover:border-orange-300 transition shadow-sm">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Re-open
                </button>
            </form>
        @endif

        {{-- Moodle action group — pushed to right when present --}}
        @if($isFromMoodle && $isSignedOff)
        <div class="ml-auto flex items-center gap-2">
            @if($submission->lms_pushed_at)
            <span class="inline-flex items-center gap-1 text-xs text-green-700 font-medium" title="Last pushed to Moodle">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Pushed {{ $submission->lms_pushed_at->format('d M H:i') }}
            </span>
            @endif
            <form method="POST" action="{{ route('integrations.push', [$submission->lms_connection_id, $submission]) }}">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-orange-600 hover:bg-orange-700 text-white text-xs font-semibold transition shadow-sm">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    {{ $submission->lms_pushed_at ? 'Re-push to Moodle' : 'Push to Moodle' }}
                </button>
            </form>
        </div>
        @endif
    </div>
    @endif

</div>

@php
    $isPdfExt   = strtolower(pathinfo($submission->original_filename, PATHINFO_EXTENSION)) === 'pdf';
    $fileOnDisk = $submission->file_path && Storage::exists($submission->file_path);
    $isPdf      = $isPdfExt && $fileOnDisk;
    $fileUrl    = route('qualifications.cohorts.learners.submissions.file',        [$qualification, $cohort, $learner, $submission]);
    $saveAnnUrl = route('qualifications.cohorts.learners.submissions.annotations', [$qualification, $cohort, $learner, $submission]);
    $initialAnnotations = $result?->annotations_json ?? [];
@endphp

{{-- ═══════════════════════════════════════════════════════
     MAIN SIDE-BY-SIDE LAYOUT
     Left: criteria + sign-off panels (scrolls with page)
     Right: sticky PDF annotation viewer
     ═══════════════════════════════════════════════════════ --}}

{{-- Fullscreen wrapper --}}
<div id="review-area">

    {{-- Floating Exit Fullscreen button — only visible while fullscreen is active.
         Lives inside #review-area so it remains in the DOM during fullscreen.
         Esc also exits natively. --}}
    <button id="btn-exit-fullscreen" type="button"
            class="hidden fixed top-3 right-3 z-50 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-orange-300
                   bg-orange-50 text-xs font-semibold text-orange-700 hover:bg-orange-100 transition shadow-sm"
            title="Exit fullscreen (Esc)">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M8 3v3a2 2 0 01-2 2H3m18 0h-3a2 2 0 01-2-2V3m0 18v-3a2 2 0 012-2h3M3 16h3a2 2 0 012 2v3"/>
        </svg>
        Exit Fullscreen
    </button>


<div class="review-columns flex gap-5 items-start">

    {{-- ─── LEFT COLUMN ─────────────────────────────────────────────────── --}}
    <div class="flex-1 min-w-0 sticky top-4 overflow-y-auto" style="height:calc(100vh - 5rem)">

        {{-- Criteria table with AI reasoning --}}
        @if($result && ! empty($result->questions_json))
        @php
            $questions    = $result->questions_json;
            $totalMax     = collect($questions)->sum('max_marks');
            $totalAwarded = collect($questions)->sum('awarded');
            $pct          = $totalMax > 0 ? round($totalAwarded / $totalMax * 100) : 0;

            // Rubric support: build a map of criterion index → rubric levels
            $isRubric   = ($submission->assignment->memo_type === 'rubric');
            $rubricMap  = [];
            if ($isRubric && ! empty($submission->assignment->rubric_json)) {
                // Index rubric criteria by lowercased title for fuzzy matching
                $rubricByTitle = [];
                foreach ($submission->assignment->rubric_json as $rc) {
                    $rubricByTitle[strtolower(trim($rc['title'] ?? ''))] = $rc['levels'] ?? [];
                }
                foreach ($questions as $qi => $q) {
                    $key = strtolower(trim($q['criterion'] ?? $q['question'] ?? ''));
                    // strip "[LABEL] " prefix if present
                    $key = preg_replace('/^\[[^\]]+\]\s*/', '', $key);
                    $key = preg_replace('/^[A-Z]{2,5}-\d{2,3}-[A-Z\d.]+\s*:\s*/', '', $key);
                    $key = trim($key);
                    if (isset($rubricByTitle[$key])) {
                        $rubricMap[$qi] = $rubricByTitle[$key];
                    } else {
                        // Partial / word-overlap match fallback
                        foreach ($rubricByTitle as $title => $levels) {
                            if (str_contains($key, $title) || str_contains($title, $key)) {
                                $rubricMap[$qi] = $levels;
                                break;
                            }
                        }
                        // If still not matched, just use index order
                        if (! isset($rubricMap[$qi])) {
                            $rbArr = array_values($submission->assignment->rubric_json);
                            if (isset($rbArr[$qi])) {
                                $rubricMap[$qi] = $rbArr[$qi]['levels'] ?? [];
                            }
                        }
                    }
                }
            }
        @endphp
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden flex flex-col h-full" id="criteria-panel">

            {{-- ── Fixed header ────────────────────────────────────────── --}}
            <div class="flex-shrink-0 px-5 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                <div>
                    <h2 class="font-semibold text-gray-800 text-sm">AI Feedback &amp; Criterion Breakdown</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Edit marks or comments, then save. Click <strong>View AI Reasoning</strong> on any row to see why marks were awarded.</p>
                </div>
                <span id="total-display" class="text-sm font-bold {{ $pct >= 50 ? 'text-green-700' : 'text-red-700' }}">
                    {{ $totalAwarded }} / {{ $totalMax }} ({{ $pct }}%)
                </span>
            </div>

            {{-- ── Fixed score bar ──────────────────────────────────────── --}}
            <div class="flex-shrink-0 px-5 pt-3 pb-1">
                <div class="w-full bg-gray-100 rounded-full h-2.5">
                    <div id="score-bar" class="h-2.5 rounded-full transition-all {{ $pct >= 50 ? 'bg-green-500' : 'bg-red-500' }}"
                         style="width: {{ min(100, $pct) }}%"></div>
                </div>
                <div class="flex justify-between text-xs text-gray-400 mt-1">
                    <span>0</span>
                    <span class="text-gray-500 font-medium">Pass mark: 50%</span>
                    <span>{{ $totalMax }}</span>
                </div>
            </div>

            {{-- ── Scrollable body area ─────────────────────────────────── --}}
            <div class="flex-1 overflow-y-auto">

            @if($isRubric)
            {{-- ════════════════════════════════════════════════════════════
                 RUBRIC GRID — criterion rows × level columns
                 (mirrors the column-format rubric in the reference image)
                 ════════════════════════════════════════════════════════════ --}}
            @php
                // Collect all unique level count across criteria so we can
                // build a consistent header. Use the widest criterion as guide.
                $maxLevelCount = 1;
                foreach ($rubricMap as $levels) {
                    $maxLevelCount = max($maxLevelCount, count($levels));
                }
            @endphp

            <div id="rubric-grid">
                @foreach($questions as $i => $q)
                @php
                    $qPct    = $q['max_marks'] > 0 ? $q['awarded'] / $q['max_marks'] : 0;
                    $stampBg = $qPct >= 0.5 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600';
                    $stamp   = $qPct >= 0.5 ? '✓' : '✗';
                    $hasReasoning = ! empty($q['expected_answer']) || ! empty($q['ai_grading_notes']) || ! empty($q['comment']);
                    $rawCrit = $q['criterion'] ?? $q['question'] ?? '—';
                    $critLabel = null; $critText = $rawCrit;
                    if (preg_match('/^\[([^\]]{1,30})\]\s*(.+)/su', $rawCrit, $cm)) {
                        $critLabel = $cm[1]; $critText = trim($cm[2]);
                    } elseif (preg_match('/^([A-Z]{2,5}-[\d]{2,3}-[A-Z]{2,5}[\d]*(?:[\.\d]*)?)\s*:\s*(.+)/su', $rawCrit, $cm)) {
                        $critLabel = $cm[1]; $critText = trim($cm[2]);
                    }
                    $levels = isset($rubricMap[$i])
                        ? collect($rubricMap[$i])->sortBy('score')->values()->toArray()
                        : [];
                    $maxLvlScore = collect($levels)->max('score') ?: 1;
                @endphp

                {{-- ── Criterion card ─────────────────────────────────── --}}
                <div class="border-b border-gray-200 last:border-b-0" data-row="{{ $i }}">

                    {{-- Level row: criterion label + level cells side-by-side --}}
                    <div class="flex min-h-[5rem]">

                        {{-- Left: criterion name cell (dark header column) --}}
                        <div class="flex-shrink-0 flex flex-col justify-between bg-[#2d3748] text-white p-3"
                             style="width:11rem">
                            <div>
                                @if($critLabel)
                                <span class="inline-block mb-1 px-1.5 py-0.5 rounded text-[10px] font-mono font-semibold bg-white/20 text-white/80">{{ $critLabel }}</span>
                                @endif
                                <p class="text-xs font-semibold leading-snug">{{ $critText }}</p>
                            </div>
                            <div class="flex items-center gap-1.5 mt-2">
                                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-bold {{ $stampBg }}"
                                      id="row-stamp-{{ $i }}">{{ $stamp }}</span>
                                <span class="text-[10px] text-white/60">/ {{ $q['max_marks'] }} pts</span>
                            </div>
                        </div>

                        {{-- Right: level cells --}}
                        <div class="flex flex-1 divide-x divide-gray-200">
                            @if(! empty($levels))
                                @foreach($levels as $lvl)
                                @php
                                    $lvlScore   = (float) ($lvl['score'] ?? 0);
                                    $lvlScoreInt = (int) $lvlScore;
                                    $awardedVal = (float) $q['awarded'];
                                    $isSelected = abs($awardedVal - $lvlScore) < 0.01;
                                    $lvlPct     = $maxLvlScore > 0 ? $lvlScore / $maxLvlScore : 0;
                                    // Selected: green highlight (pass) or red highlight (fail)
                                    // Unselected: white with subtle hover
                                    $cellBg = $isSelected
                                        ? ($lvlPct >= 0.5 ? 'bg-green-50 ring-2 ring-inset ring-green-500' : 'bg-red-50 ring-2 ring-inset ring-red-400')
                                        : 'bg-white hover:bg-gray-50';
                                    $scoreCls = $lvlPct >= 0.5 ? 'text-green-700' : 'text-red-600';
                                    $selectedIcon = $isSelected ? ($lvlPct >= 0.5 ? '✓' : '✗') : '';
                                @endphp
                                <button type="button"
                                        class="rubric-level-btn flex-1 text-left p-2.5 text-xs transition relative {{ $cellBg }}"
                                        data-idx="{{ $i }}"
                                        data-score="{{ $lvlScore }}"
                                        data-max="{{ $q['max_marks'] }}">
                                    @if($isSelected)
                                    <span class="absolute top-1.5 right-1.5 inline-flex items-center justify-center w-4 h-4 rounded-full text-[9px] font-black {{ $lvlPct >= 0.5 ? 'bg-green-500 text-white' : 'bg-red-500 text-white' }}">{{ $selectedIcon }}</span>
                                    @endif
                                    <p class="text-gray-700 leading-snug pr-4">{{ $lvl['description'] ?? '' }}</p>
                                    <p class="mt-2 font-bold italic {{ $scoreCls }}">{{ $lvlScoreInt }} points</p>
                                </button>
                                @endforeach
                            @else
                                {{-- Fallback if no rubric levels mapped: show plain number input --}}
                                <div class="flex-1 flex items-center justify-center">
                                    <input type="number"
                                           id="awarded-{{ $i }}"
                                           data-idx="{{ $i }}"
                                           data-max="{{ $q['max_marks'] }}"
                                           value="{{ $q['awarded'] }}"
                                           min="0" max="{{ $q['max_marks'] }}"
                                           class="criteria-mark w-16 text-center text-sm font-bold border border-gray-200 rounded px-1 py-1 focus:outline-none focus:ring-2 focus:ring-orange-300">
                                    <span class="ml-1 text-xs text-gray-400">/ {{ $q['max_marks'] }}</span>
                                </div>
                            @endif
                        </div>

                    </div>{{-- /level row --}}

                    {{-- Hidden criteria-mark input (keeps save/dirty/total JS working) --}}
                    @if(! empty($levels))
                    <input type="number"
                           id="awarded-{{ $i }}"
                           data-idx="{{ $i }}"
                           data-max="{{ $q['max_marks'] }}"
                           value="{{ $q['awarded'] }}"
                           min="0" max="{{ $q['max_marks'] }}"
                           class="criteria-mark sr-only">
                    @endif

                    {{-- Assessor comment row --}}
                    <div class="px-3 py-2 bg-gray-50 border-t border-gray-100 flex items-start gap-2">
                        <svg class="w-3.5 h-3.5 text-orange-400 mt-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>
                        <textarea id="comment-{{ $i }}"
                                  data-idx="{{ $i }}"
                                  rows="1"
                                  maxlength="500"
                                  placeholder="Assessor comment…"
                                  class="criteria-comment flex-1 text-xs border-0 bg-transparent focus:outline-none focus:ring-0 text-gray-700 resize-none leading-snug">{{ $q['comment'] ?? '' }}</textarea>
                        @if($hasReasoning)
                        <button type="button"
                                class="ai-reasoning-toggle flex-shrink-0 text-xs text-blue-500 hover:text-blue-700 flex items-center gap-1 whitespace-nowrap"
                                data-target="reasoning-{{ $i }}">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span class="toggle-label">AI Reasoning</span>
                        </button>
                        @endif
                    </div>

                    {{-- AI Reasoning expandable --}}
                    @if($hasReasoning)
                    <div id="reasoning-{{ $i }}" class="hidden border-t border-blue-100 bg-blue-50 px-3 py-3 text-xs space-y-2">
                        @if(! empty($q['comment']))
                        <div class="flex gap-2">
                            <span class="font-semibold text-blue-700 whitespace-nowrap">AI Comment:</span>
                            <span class="text-blue-800">{{ $q['comment'] }}</span>
                        </div>
                        @endif
                        @if(! empty($q['expected_answer']))
                        <div class="flex gap-2">
                            <span class="font-semibold text-indigo-700 whitespace-nowrap">Expected Answer:</span>
                            <span class="text-indigo-800">{{ $q['expected_answer'] }}</span>
                        </div>
                        @endif
                        @if(! empty($q['ai_grading_notes']))
                        <div class="flex gap-2">
                            <span class="font-semibold text-purple-700 whitespace-nowrap">Grading Notes:</span>
                            <span class="text-purple-800">{{ $q['ai_grading_notes'] }}</span>
                        </div>
                        @endif
                    </div>
                    @endif

                </div>{{-- /criterion card --}}
                @endforeach

                {{-- Total row --}}
                <div class="border-t-2 border-gray-200 bg-gray-50 flex items-center justify-between px-4 py-2">
                    <span class="text-sm font-bold text-gray-700">
                        Total <span id="pct-cell" class="ml-2 text-xs font-normal text-gray-400">{{ $pct }}%</span>
                    </span>
                    <span class="text-sm font-black" id="total-cell">
                        <span class="{{ $pct >= 50 ? 'text-green-700' : 'text-red-700' }}">{{ $totalAwarded }} / {{ $totalMax }}</span>
                    </span>
                </div>
            </div>{{-- /rubric-grid --}}

            @else
            {{-- ════════════════════════════════════════════════════════════
                 STANDARD QUESTION TABLE (non-rubric assignments)
                 ════════════════════════════════════════════════════════════ --}}
            <table class="w-full text-sm" id="criteria-table">
                <thead>
                    <tr class="bg-gray-50 border-y border-gray-200 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                        <th class="px-3 py-2 text-left" style="width:2rem">#</th>
                        <th class="px-3 py-2 text-left">Question &amp; Assessor Feedback</th>
                        <th class="px-3 py-2 text-center" style="width:7rem">Marks</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($questions as $i => $q)
                    @php
                        $qPct    = $q['max_marks'] > 0 ? $q['awarded'] / $q['max_marks'] : 0;
                        $stampBg = $qPct >= 0.5 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600';
                        $stamp   = $qPct >= 0.5 ? '✓' : '✗';
                        $hasReasoning = ! empty($q['expected_answer']) || ! empty($q['ai_grading_notes']) || ! empty($q['comment']);
                        $rawCrit = $q['criterion'] ?? $q['question'] ?? '—';
                        $critLabel = null; $critText = $rawCrit;
                        if (preg_match('/^\[([^\]]{1,30})\]\s*(.+)/su', $rawCrit, $cm)) {
                            $critLabel = $cm[1]; $critText = trim($cm[2]);
                        } elseif (preg_match('/^([A-Z]{2,5}-[\d]{2,3}-[A-Z]{2,5}[\d]*(?:[\.\d]*)?)\s*:\s*(.+)/su', $rawCrit, $cm)) {
                            $critLabel = $cm[1]; $critText = trim($cm[2]);
                        }
                    @endphp
                    <tr class="border-t border-gray-100 hover:bg-gray-50 align-top" data-row="{{ $i }}">
                        <td class="px-3 pt-3 pb-2">
                            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-bold {{ $stampBg }}"
                                  id="row-stamp-{{ $i }}">{{ $stamp }}</span>
                        </td>
                        <td class="px-3 pt-3 pb-2">
                            @if($critLabel)
                            <span class="inline-block mb-1 px-1.5 py-0.5 rounded text-xs font-mono font-semibold bg-gray-100 text-gray-500">{{ $critLabel }}</span>
                            @endif
                            <p class="text-sm font-medium text-gray-800 leading-snug mb-2">{{ $critText }}</p>
                            <textarea id="comment-{{ $i }}"
                                      data-idx="{{ $i }}"
                                      rows="2"
                                      maxlength="500"
                                      placeholder="Assessor feedback / comment…"
                                      class="criteria-comment w-full text-sm border-l-4 border-orange-300 bg-orange-50 rounded-r px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:bg-white text-gray-700 resize-none transition-colors leading-snug">{{ $q['comment'] ?? '' }}</textarea>
                            @if($hasReasoning)
                            <button type="button"
                                    class="ai-reasoning-toggle mt-1 text-xs text-blue-500 hover:text-blue-700 flex items-center gap-1"
                                    data-target="reasoning-{{ $i }}">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span class="toggle-label">View AI Reasoning</span>
                            </button>
                            @endif
                        </td>
                        <td class="px-3 pt-3 pb-2 text-center">
                            <input type="number"
                                   id="awarded-{{ $i }}"
                                   data-idx="{{ $i }}"
                                   data-max="{{ $q['max_marks'] }}"
                                   value="{{ $q['awarded'] }}"
                                   min="0" max="{{ $q['max_marks'] }}"
                                   class="criteria-mark w-14 text-center text-sm font-bold border border-gray-200 rounded px-1 py-1 focus:outline-none focus:ring-2 focus:ring-orange-300 {{ $qPct >= 0.5 ? 'text-green-700' : 'text-red-600' }}">
                            <div class="text-xs text-gray-400 mt-0.5">/ {{ $q['max_marks'] }}</div>
                        </td>
                    </tr>
                    @if($hasReasoning)
                    <tr id="reasoning-{{ $i }}" class="hidden border-t border-blue-100 bg-blue-50">
                        <td></td>
                        <td colspan="2" class="px-3 py-3 text-xs space-y-2">
                            @if(! empty($q['comment']))
                            <div class="flex gap-2"><span class="font-semibold text-blue-700 whitespace-nowrap">AI Comment:</span><span class="text-blue-800">{{ $q['comment'] }}</span></div>
                            @endif
                            @if(! empty($q['expected_answer']))
                            <div class="flex gap-2"><span class="font-semibold text-indigo-700 whitespace-nowrap">Expected Answer:</span><span class="text-indigo-800">{{ $q['expected_answer'] }}</span></div>
                            @endif
                            @if(! empty($q['ai_grading_notes']))
                            <div class="flex gap-2"><span class="font-semibold text-purple-700 whitespace-nowrap">Grading Notes:</span><span class="text-purple-800">{{ $q['ai_grading_notes'] }}</span></div>
                            @endif
                        </td>
                    </tr>
                    @endif
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-gray-200 bg-gray-50">
                        <td colspan="2" class="px-3 py-2 text-sm font-bold text-gray-700">
                            Total <span id="pct-cell" class="ml-2 text-xs font-normal text-gray-400">{{ $pct }}%</span>
                        </td>
                        <td class="px-3 py-2 text-center text-sm font-black" id="total-cell">
                            <span class="{{ $pct >= 50 ? 'text-green-700' : 'text-red-700' }}">{{ $totalAwarded }} / {{ $totalMax }}</span>
                        </td>
                    </tr>
                </tfoot>
            </table>
            @endif

            </div>{{-- /scrollable body area --}}

            {{-- ── Fixed save button (always visible at bottom) ─────────── --}}
            <div class="flex-shrink-0 px-5 py-3 border-t border-gray-100 flex items-center gap-3 bg-white">
                <button type="button" id="btn-save-criteria"
                        class="px-4 py-1.5 rounded-lg text-sm font-semibold text-white bg-orange-500 hover:bg-orange-600 transition opacity-60"
                        disabled>
                    Save Mark Changes
                </button>
                <span id="criteria-status" class="text-xs text-gray-400"></span>
            </div>
        </div>
        @elseif(! $result)
        <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-5 text-sm text-yellow-800">
            No marking result yet. Go back to the POE page and click "Run AI Marking".
        </div>
        @endif


    </div>{{-- /left column --}}


    {{-- ─── RIGHT COLUMN — PDF Annotation Viewer (sticky, 50 %) ──────── --}}
    @if($isPdfExt && !$fileOnDisk && $result)
    <div class="pdf-right-col hidden xl:flex flex-1 min-w-0 sticky top-4 items-start justify-center" style="height:calc(100vh - 5rem)">
        <div class="mt-16 flex flex-col items-center gap-3 text-center px-8">
            <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0121 9.414V19a2 2 0 01-2 2z"/>
            </svg>
            <p class="text-sm font-semibold text-gray-500">PDF file not on disk</p>
            <p class="text-xs text-gray-400 leading-relaxed">
                The original submission file (<span class="font-mono">{{ $submission->original_filename }}</span>)
                was not found in storage. This can happen when binary files are not preserved across environment resets.
                @if($submission->isFromMoodle())
                Re-sync this submission from Moodle to restore the file.
                @else
                Ask the learner to re-upload, or upload a replacement file via the assignment page.
                @endif
            </p>
        </div>
    </div>
    @elseif($isPdf && $result)
    <div class="pdf-right-col hidden xl:block flex-1 min-w-0 sticky top-4 overflow-y-auto" style="height:calc(100vh - 5rem)">
            {{-- Panel card fills the column height; sign-off scrolls below it --}}
            <div class="flex flex-col rounded-xl border border-gray-200 shadow-sm bg-white overflow-hidden" id="viewer-panel" style="height:calc(100vh - 5rem); flex-shrink:0">

                {{-- ── Panel header (fixed, never scrolls) ───────────────── --}}
                <div class="flex-shrink-0 flex items-center justify-between px-4 py-2.5 bg-gray-50 border-b border-gray-200">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0121 9.414V19a2 2 0 01-2 2z"/>
                        </svg>
                        <span class="font-semibold text-gray-800 text-sm">PDF Annotation Preview</span>
                    </div>
                    <button type="button" id="viewer-toggle"
                            class="p-1 rounded text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition"
                            title="Toggle viewer">
                        <svg id="viewer-chevron" class="w-4 h-4 transition-transform duration-200 rotate-180"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                </div>

                {{-- ── Body: vertical toolbar + scrollable PDF (flex row) ── --}}
                <div id="viewer-body" class="flex flex-1 overflow-hidden">

                    {{-- Vertical toolbar — never scrolls, always visible --}}
                    <div class="flex-shrink-0 w-12 flex flex-col items-center gap-1 py-2 px-1 bg-gray-50 border-r border-gray-100">

                        {{-- Select --}}
                        <button type="button" data-tool="select" id="tool-select"
                                class="tool-btn active-tool vtool" title="Select / move stamp">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5"/>
                            </svg>
                            <span class="vtool-label">Select</span>
                        </button>

                        {{-- Tick --}}
                        <button type="button" data-tool="tick" id="tool-tick"
                                class="tool-btn vtool" title="Place tick stamp">
                            <img src="{{ asset('pix/tick.png') }}" class="w-5 h-5 object-contain" alt="">
                            <span class="vtool-label text-green-700">Tick</span>
                        </button>

                        {{-- Cross --}}
                        <button type="button" data-tool="cross" id="tool-cross"
                                class="tool-btn vtool" title="Place cross stamp">
                            <img src="{{ asset('pix/cross.png') }}" class="w-5 h-5 object-contain" alt="">
                            <span class="vtool-label text-red-600">Cross</span>
                        </button>

                        <div class="vtool-sep"></div>

                        {{-- Delete selected --}}
                        <button type="button" id="btn-delete" disabled
                                class="vtool vtool-delete" title="Delete selected stamp">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            <span class="vtool-label">Delete</span>
                        </button>

                        <div class="vtool-sep"></div>

                        {{-- Show / hide stamps --}}
                        <button type="button" id="btn-toggle-stamps"
                                class="vtool text-gray-500 hover:bg-gray-200" title="Show / hide stamps">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <span id="toggle-stamp-label" class="vtool-label">Hide</span>
                        </button>

                        <div class="vtool-sep"></div>

                        {{-- Save --}}
                        <div class="relative w-full flex flex-col items-center">
                            <button type="button" id="btn-save-ann"
                                    class="vtool text-white bg-orange-500 hover:bg-orange-600 opacity-60"
                                    title="Save annotations">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                                </svg>
                                <span id="save-ann-label" class="vtool-label">Save</span>
                            </button>
                            {{-- Yellow dot when unsaved changes exist --}}
                            <span id="dirty-dot"
                                  class="hidden absolute top-0 right-1.5 w-2 h-2 bg-yellow-400 rounded-full border border-white pointer-events-none"></span>
                        </div>

                        {{-- Save feedback --}}
                        <div id="ann-status" class="text-center text-gray-400 leading-tight px-0.5" style="font-size:9px"></div>
                    </div>

                    {{-- Scrollable PDF area --}}
                    <div class="flex-1 overflow-y-auto overflow-x-auto bg-gray-100" id="pdf-scroll-area">
                        <div id="pdf-loading" class="flex items-center justify-center py-16 text-sm text-gray-400 gap-2">
                            <svg class="animate-spin w-5 h-5 text-orange-400" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                            </svg>
                            Loading PDF…
                        </div>
                        <div id="pdf-container" class="hidden p-3"></div>
                        <div id="pdf-error" class="hidden px-4 py-8 text-sm text-red-600 text-center">
                            Could not load the PDF preview.
                        </div>
                    </div>

                </div>{{-- /viewer-body --}}
            </div>{{-- /panel card --}}

        {{-- ── Sign-off form (scrolls below PDF within column) ──
             Note: signed-off Final Verdict and Moodle Sync blocks have been
             promoted to the action bar above the review columns. Only the
             multi-field sign-off form remains here. ── --}}
        <div class="mt-4 space-y-4 pb-4">

        @if($submission->status === 'review_required' && $result)
        <div id="signoff-form" class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 scroll-mt-4">
            <h3 class="text-sm font-semibold text-gray-800 mb-1">Assessor Sign-Off</h3>
            <p class="text-xs text-gray-400 mb-4">Save mark &amp; annotation changes first. An Assessor Declaration cover page will be prepended to the returned PDF.</p>

            <form method="POST"
                  action="{{ route('qualifications.cohorts.learners.submissions.signoff', [$qualification, $cohort, $learner, $submission]) }}">
                @csrf
                <fieldset class="mb-4">
                    <legend class="text-xs font-semibold text-gray-600 mb-2">Final Verdict</legend>
                    <label class="flex items-center gap-2 mb-2 cursor-pointer">
                        <input type="radio" name="final_verdict" value="COMPETENT"
                               {{ $result->ai_recommendation === 'COMPETENT' ? 'checked' : '' }}
                               class="accent-green-600">
                        <span class="text-sm font-semibold text-green-700">Competent</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="final_verdict" value="NOT_YET_COMPETENT"
                               {{ $result->ai_recommendation === 'NOT_YET_COMPETENT' ? 'checked' : '' }}
                               class="accent-red-600">
                        <span class="text-sm font-semibold text-red-700">Not Yet Competent</span>
                    </label>
                </fieldset>

                <div class="mb-3 p-3 rounded-lg bg-gray-50 border border-gray-200 space-y-3">
                    <p class="text-xs font-semibold text-gray-600">Declaration Details <span class="font-normal text-gray-400">(printed on cover page)</span></p>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">ETQA Registration Number</label>
                        <input type="text" name="etqa_registration"
                               value="{{ old('etqa_registration', $result->etqa_registration) }}"
                               placeholder="e.g. 12345"
                               class="w-full rounded border border-gray-300 text-xs px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-orange-400">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Assessment Site / Provider</label>
                        <input type="text" name="assessment_provider"
                               value="{{ old('assessment_provider', $result->assessment_provider ?? ($qualification->seta ? $qualification->name . ' | ' . $qualification->seta : '')) }}"
                               placeholder="e.g. Praesignis PTY LTD | MICT SETA"
                               class="w-full rounded border border-gray-300 text-xs px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-orange-400">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Moderation Notes <span class="font-normal text-gray-400">(optional)</span></label>
                    <textarea name="moderation_notes" rows="2"
                              class="w-full rounded border border-gray-300 text-xs px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-400 resize-none"
                              placeholder="Override reasons, observations…">{{ old('moderation_notes') }}</textarea>
                </div>

                <div class="mb-3 text-xs text-gray-500">
                    Signing off as: <strong class="text-gray-700">{{ auth()->user()->name }}</strong>
                </div>

                {{-- Hidden carriers — JS fills these just before submit --}}
                <input type="hidden" name="annotations_json" id="signoff-annotations" value="[]">
                <input type="hidden" name="questions_json"   id="signoff-questions"   value="{}">

                <button type="submit" id="btn-signoff"
                        class="w-full bg-[#e3b64d] hover:bg-[#d4a43e] text-white text-sm font-semibold px-4 py-2.5 rounded-lg transition flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Sign Off &amp; Generate Declaration PDF
                </button>
            </form>
        </div>
        @endif

        {{-- Moderation notes (kept here when signed off — extra detail too long for the action bar) --}}
        @if($submission->status === 'signed_off' && $result && ($result->moderation_notes || $result->etqa_registration))
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 text-xs text-gray-600 space-y-2">
            @if($result->etqa_registration)
                <div><strong class="text-gray-700">ETQA Registration:</strong> {{ $result->etqa_registration }}</div>
            @endif
            @if($result->moderation_notes)
                <div class="bg-gray-50 border border-gray-200 rounded p-2">
                    <strong class="text-gray-700">Moderation Notes:</strong> {{ $result->moderation_notes }}
                </div>
            @endif
        </div>
        @endif

        </div>{{-- /below-pdf blocks --}}
    </div>
    @endif
    {{-- /right column --}}

</div>{{-- /review-columns --}}
</div>{{-- /review-area --}}

@endsection

@push('scripts')
@if($isPdf && $result)
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
// ─── Server data ──────────────────────────────────────────────────────────────
const PDF_URL         = @json($fileUrl);
const SAVE_URL        = @json($saveAnnUrl);
const CSRF_TOKEN      = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
const TOTAL_MAX_MARKS = {{ collect($result->questions_json ?? [])->sum('max_marks') }};
const STAMP_SIZE = 16; // half-width in px — increase to make stamps bigger

let annotations = @json($initialAnnotations);

// ─── State ────────────────────────────────────────────────────────────────────
const state = {
    activeTool:    'select',
    selectedIdx:   null,
    stampsVisible: true,
    dirty:         false,
    pages:         {},
    pdfDoc:        null,
    viewerOpen:    true,
};

// ─── Drag state ───────────────────────────────────────────────────────────────
const drag = {
    active:   false,
    idx:      null,
    pageNum:  null,
    startX:   0,
    startY:   0,
    origXpct: 0,
    origYpct: 0,
    moved:    false,   // true once pointer moves beyond the 3 px threshold
};

function onDragMove(clientX, clientY) {
    if (!drag.active || drag.idx === null) return;

    const p = state.pages[drag.pageNum];
    if (!p) return;

    const dx = clientX - drag.startX;
    const dy = clientY - drag.startY;

    if (Math.abs(dx) > 3 || Math.abs(dy) > 3) drag.moved = true;
    if (!drag.moved) return;

    const newXpct = Math.max(0, Math.min(1, drag.origXpct + dx / p.viewport.width));
    const newYpct = Math.max(0, Math.min(1, drag.origYpct + dy / p.viewport.height));

    annotations[drag.idx].x_pct = parseFloat(newXpct.toFixed(4));
    annotations[drag.idx].y_pct = parseFloat(newYpct.toFixed(4));

    // Translate the SVG element directly — no full re-render needed
    const el = p.svg.querySelector(`.stamp[data-idx="${drag.idx}"]`);
    if (el) el.setAttribute('transform',
        `translate(${newXpct * p.viewport.width},${newYpct * p.viewport.height})`);

    document.body.style.cursor = 'grabbing';
}

function onDragEnd() {
    if (!drag.active) return;
    drag.active = false;
    document.body.style.cursor = '';

    if (drag.moved) {
        state.selectedIdx = drag.idx;
        document.getElementById('btn-delete').disabled = false;
        markDirty();
    }
    drag.idx  = null;
    drag.moved = false;
}

document.addEventListener('mousemove', e => onDragMove(e.clientX, e.clientY));
document.addEventListener('mouseup',   onDragEnd);
document.addEventListener('touchmove', e => {
    if (drag.active) e.preventDefault();
    onDragMove(e.touches[0].clientX, e.touches[0].clientY);
}, { passive: false });
document.addEventListener('touchend', onDragEnd);

// ─── Viewer toggle ────────────────────────────────────────────────────────────
document.getElementById('viewer-toggle').addEventListener('click', () => {
    const body    = document.getElementById('viewer-body');
    const chevron = document.getElementById('viewer-chevron');

    if (!state.viewerOpen) {
        body.style.display = 'flex';
        chevron.classList.add('rotate-180');
        state.viewerOpen = true;
        if (!state.pdfDoc) initViewer();
    } else {
        body.style.display = 'none';
        chevron.classList.remove('rotate-180');
        state.viewerOpen = false;
    }
});

// Start loading immediately (panel is open by default)
window.addEventListener('DOMContentLoaded', () => initViewer());

// ─── Tool buttons ─────────────────────────────────────────────────────────────
document.querySelectorAll('.tool-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        state.activeTool  = btn.dataset.tool;
        state.selectedIdx = null;
        document.getElementById('btn-delete').disabled = true;
        document.querySelectorAll('.tool-btn').forEach(b => b.classList.remove('active-tool'));
        btn.classList.add('active-tool');
        updateCursors();
    });
});

document.getElementById('btn-delete').addEventListener('click', deleteSelected);

document.getElementById('btn-toggle-stamps').addEventListener('click', () => {
    state.stampsVisible = !state.stampsVisible;
    document.getElementById('toggle-stamp-label').textContent = state.stampsVisible ? 'Hide' : 'Show';
    Object.values(state.pages).forEach(({ svg }) => {
        svg.style.display = state.stampsVisible ? '' : 'none';
    });
});

document.getElementById('btn-save-ann').addEventListener('click', saveAnnotations);

// ─── Inject current stamps + marks into sign-off form before submit ──────────
// Synchronous — no AJAX, no timing risk. The hidden fields travel with the form
// POST so the controller has the latest in-memory state regardless of whether
// the assessor clicked "Save Annotations" or "Save Mark Changes" first.
(function () {
    const signOffForm = document.querySelector('form[action*="signoff"]');
    if (!signOffForm) return;

    signOffForm.addEventListener('submit', function () {
        // Annotations (stamps placed on PDF)
        const annField = document.getElementById('signoff-annotations');
        if (annField && typeof annotations !== 'undefined') {
            annField.value = JSON.stringify(annotations);
        }

        // Per-criterion marks + comments
        const qField = document.getElementById('signoff-questions');
        if (qField) {
            const questions = {};
            document.querySelectorAll('.criteria-mark').forEach(inp => {
                const idx = parseInt(inp.dataset.idx);
                questions[idx] = { awarded: parseInt(inp.value) || 0 };
            });
            document.querySelectorAll('.criteria-comment').forEach(inp => {
                const idx = parseInt(inp.dataset.idx);
                if (!questions[idx]) questions[idx] = {};
                questions[idx].comment = inp.value;
            });
            qField.value = JSON.stringify(questions);
        }
    });
})();

// ─── AI Reasoning row toggles ─────────────────────────────────────────────────
document.querySelectorAll('.ai-reasoning-toggle').forEach(btn => {
    btn.addEventListener('click', () => {
        const target = document.getElementById(btn.dataset.target);
        if (!target) return;
        const isHidden = target.classList.contains('hidden');
        target.classList.toggle('hidden', !isHidden);
        const label = btn.querySelector('.toggle-label');
        if (label) label.textContent = isHidden ? 'Hide AI Reasoning' : 'AI Reasoning';
    });
});

// ─── PDF.js init ──────────────────────────────────────────────────────────────
async function initViewer() {
    try {
        const pdfjsLib = window.pdfjsLib;
        if (!pdfjsLib) throw new Error('PDF.js not loaded');
        pdfjsLib.GlobalWorkerOptions.workerSrc =
            'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

        const pdfDoc = await pdfjsLib.getDocument(PDF_URL).promise;
        state.pdfDoc = pdfDoc;

        document.getElementById('pdf-loading').classList.add('hidden');
        const container = document.getElementById('pdf-container');
        container.classList.remove('hidden');

        for (let i = 1; i <= pdfDoc.numPages; i++) {
            await renderPage(pdfDoc, i);
        }
        renderAllStamps();
    } catch (err) {
        document.getElementById('pdf-loading').classList.add('hidden');
        const errEl = document.getElementById('pdf-error');
        errEl.classList.remove('hidden');
        errEl.textContent = 'Could not load PDF preview: ' + (err?.message || String(err));
        console.error('PDF viewer error:', err);
    }
}

async function renderPage(pdfDoc, pageNum) {
    const page     = await pdfDoc.getPage(pageNum);
    const panelW   = document.getElementById('pdf-scroll-area').clientWidth || 520;
    const scale    = Math.min(1.5, (panelW - 24) / page.getViewport({ scale: 1 }).width);
    const viewport = page.getViewport({ scale });

    const wrap = document.createElement('div');
    wrap.className = 'relative inline-block mb-3 shadow align-top';
    wrap.style.width  = viewport.width  + 'px';
    wrap.style.height = viewport.height + 'px';

    const canvas = document.createElement('canvas');
    canvas.width  = viewport.width;
    canvas.height = viewport.height;
    canvas.style.display = 'block';

    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('width',  viewport.width);
    svg.setAttribute('height', viewport.height);
    svg.style.cssText = 'position:absolute;top:0;left:0;overflow:visible';

    wrap.appendChild(canvas);
    wrap.appendChild(svg);
    document.getElementById('pdf-container').appendChild(wrap);

    await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;

    state.pages[pageNum] = { canvas, svg, viewport };
    svg.addEventListener('click', e => handleSvgClick(e, pageNum, viewport));
}

// ─── Stamp rendering ──────────────────────────────────────────────────────────
function renderAllStamps() {
    Object.values(state.pages).forEach(({ svg }) => {
        svg.querySelectorAll('.stamp').forEach(n => n.remove());
    });

    annotations.forEach((stamp, idx) => {
        const p = state.pages[stamp.page];
        if (!p) return;
        const x = stamp.x_pct * p.viewport.width;
        const y = stamp.y_pct * p.viewport.height;
        const g = buildStampEl(stamp.type, idx, x, y);
        p.svg.appendChild(g);
        if (idx === state.selectedIdx) highlightStamp(g, true);
    });

    if (!state.stampsVisible) {
        Object.values(state.pages).forEach(({ svg }) => svg.style.display = 'none');
    }
}

function buildStampEl(type, idx, x, y) {
    const ns = 'http://www.w3.org/2000/svg';
    const s  = STAMP_SIZE;
    const sw = Math.max(3, s * 0.22); // stroke width scales with stamp size

    const g = document.createElementNS(ns, 'g');
    g.setAttribute('transform', `translate(${x},${y})`);
    g.setAttribute('class', 'stamp');
    g.setAttribute('data-idx', idx);
    g.style.cursor = state.activeTool === 'select' ? 'grab' : 'crosshair';

    // Transparent hit area so small stamps are still easy to click
    const hit = document.createElementNS(ns, 'circle');
    hit.setAttribute('r', s + 6);
    hit.setAttribute('fill', 'transparent');

    // Dashed selection ring — shown only when stamp is selected
    const ring = document.createElementNS(ns, 'circle');
    ring.setAttribute('r', s + 8);
    ring.setAttribute('fill', 'none');
    ring.setAttribute('stroke', 'transparent');
    ring.setAttribute('stroke-width', '2');
    ring.setAttribute('stroke-dasharray', '4,3');
    ring.setAttribute('class', 'stamp-ring');

    // Pure SVG vector symbol — crisp at any zoom level
    const path = document.createElementNS(ns, 'path');
    if (type === 'tick') {
        // Checkmark: two-segment stroke
        path.setAttribute('d',
            `M${-s * 0.55} ${s * 0.06} L${-s * 0.06} ${s * 0.55} L${s * 0.62} ${-s * 0.5}`);
        path.setAttribute('stroke', '#dc2626');
    } else {
        // X: two diagonal lines
        path.setAttribute('d',
            `M${-s * 0.5} ${-s * 0.5} L${s * 0.5} ${s * 0.5} M${s * 0.5} ${-s * 0.5} L${-s * 0.5} ${s * 0.5}`);
        path.setAttribute('stroke', '#dc2626');
    }
    path.setAttribute('fill', 'none');
    path.setAttribute('stroke-width', sw);
    path.setAttribute('stroke-linecap', 'round');
    path.setAttribute('stroke-linejoin', 'round');
    path.setAttribute('pointer-events', 'none');

    g.appendChild(ring);
    g.appendChild(hit);
    g.appendChild(path);

    // ── Mouse drag ──────────────────────────────────────────────────────────────
    g.addEventListener('mousedown', e => {
        if (state.activeTool !== 'select') return;
        e.stopPropagation();
        e.preventDefault();
        drag.active   = true;
        drag.moved    = false;
        drag.idx      = idx;
        drag.pageNum  = annotations[idx].page;
        drag.startX   = e.clientX;
        drag.startY   = e.clientY;
        drag.origXpct = annotations[idx].x_pct;
        drag.origYpct = annotations[idx].y_pct;
        // Select immediately on mousedown so Delete is ready
        state.selectedIdx = idx;
        renderAllStamps();
        const el = state.pages[drag.pageNum]?.svg.querySelector(`.stamp[data-idx="${idx}"]`);
        if (el) highlightStamp(el, true);
        g.style.cursor = 'grabbing';
    });

    // ── Touch drag ──────────────────────────────────────────────────────────────
    g.addEventListener('touchstart', e => {
        if (state.activeTool !== 'select') return;
        const t = e.touches[0];
        drag.active   = true;
        drag.moved    = false;
        drag.idx      = idx;
        drag.pageNum  = annotations[idx].page;
        drag.startX   = t.clientX;
        drag.startY   = t.clientY;
        drag.origXpct = annotations[idx].x_pct;
        drag.origYpct = annotations[idx].y_pct;
    }, { passive: true });

    // ── Click: only fires when it wasn't a drag ─────────────────────────────────
    g.addEventListener('click', e => {
        e.stopPropagation();
        if (drag.moved) return;
        handleStampClick(e, idx, g);
    });

    g.addEventListener('dblclick', e => { e.stopPropagation(); toggleStampType(idx); });

    return g;
}

function highlightStamp(g, on) {
    const ring = g.querySelector('.stamp-ring');
    if (!ring) return;
    ring.setAttribute('stroke', on ? '#f59e0b' : 'transparent');
}

// ─── Interactions ─────────────────────────────────────────────────────────────
function handleSvgClick(e, pageNum, viewport) {
    if (state.activeTool === 'select') {
        state.selectedIdx = null;
        renderAllStamps();
        document.getElementById('btn-delete').disabled = true;
        return;
    }
    const rect = e.currentTarget.getBoundingClientRect();
    annotations.push({
        page:            pageNum,
        x_pct:           parseFloat(((e.clientX - rect.left)  / viewport.width).toFixed(4)),
        y_pct:           parseFloat(((e.clientY - rect.top)   / viewport.height).toFixed(4)),
        type:            state.activeTool,
        criterion_index: null,
        criterion:       '',
    });
    renderAllStamps();
    markDirty();
}

function handleStampClick(e, idx, g) {
    if (state.activeTool !== 'select') {
        toggleStampType(idx);
        return;
    }
    const alreadySelected = state.selectedIdx === idx;
    state.selectedIdx = alreadySelected ? null : idx;
    renderAllStamps();
    if (!alreadySelected) {
        const el = document.querySelector(`.stamp[data-idx="${idx}"]`);
        if (el) highlightStamp(el, true);
        document.getElementById('btn-delete').disabled = false;
    } else {
        document.getElementById('btn-delete').disabled = true;
    }
}

function toggleStampType(idx) {
    if (!annotations[idx]) return;
    annotations[idx].type = annotations[idx].type === 'tick' ? 'cross' : 'tick';
    renderAllStamps();
    markDirty();
    const ci = annotations[idx].criterion_index;
    if (ci !== null && ci !== undefined) updateRowStamp(ci);
}

function deleteSelected()   { if (state.selectedIdx !== null) deleteStampAt(state.selectedIdx); }
function deleteStampAt(idx) {
    annotations.splice(idx, 1);
    state.selectedIdx = null;
    document.getElementById('btn-delete').disabled = true;
    renderAllStamps();
    markDirty();
}

function updateCursors() {
    const isSelect = state.activeTool === 'select';
    Object.values(state.pages).forEach(({ svg }) => {
        svg.style.cursor = isSelect ? 'default' : 'crosshair';
        svg.querySelectorAll('.stamp').forEach(s => {
            s.style.cursor = isSelect ? 'grab' : 'crosshair';
        });
    });
}

// ─── Dirty tracking ───────────────────────────────────────────────────────────
function markDirty() {
    state.dirty = true;
    document.getElementById('btn-save-ann').classList.remove('opacity-60');
    document.getElementById('dirty-dot').classList.remove('hidden');
}

// ─── Save annotations ─────────────────────────────────────────────────────────
async function saveAnnotations() {
    const label  = document.getElementById('save-ann-label');
    const status = document.getElementById('ann-status');
    label.textContent = 'Saving…';
    status.textContent = '';
    status.classList.remove('hidden');

    try {
        const resp = await fetch(SAVE_URL, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
            body:    JSON.stringify({ annotations }),
        });
        if (!resp.ok) throw new Error(await resp.text());
        const data = await resp.json();
        label.textContent  = 'Save';
        status.textContent = `✓ ${data.count}`;
        state.dirty = false;
        document.getElementById('btn-save-ann').classList.add('opacity-60');
        document.getElementById('dirty-dot').classList.add('hidden');
        setTimeout(() => { status.textContent = ''; }, 3000);
    } catch (err) {
        label.textContent  = 'Save';
        status.textContent = 'Failed';
        status.classList.add('text-red-500');
        console.error(err);
    }
}

// ─── Criteria table ───────────────────────────────────────────────────────────
function updateRowStamp(idx) {
    const el    = document.getElementById(`row-stamp-${idx}`);
    const input = document.getElementById(`awarded-${idx}`);
    if (!el || !input) return;
    const isTick = (parseFloat(input.value) || 0) / (parseFloat(input.dataset.max) || 1) >= 0.5;
    el.textContent = isTick ? '✓' : '✗';
    el.className   = 'inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-bold ' +
        (isTick ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600');
}

function recomputeTotal() {
    let total = 0;
    document.querySelectorAll('.criteria-mark').forEach(input => { total += parseFloat(input.value) || 0; });
    const pct = TOTAL_MAX_MARKS > 0 ? Math.round(total / TOTAL_MAX_MARKS * 100) : 0;
    document.getElementById('total-display').textContent = `${total} / ${TOTAL_MAX_MARKS} (${pct}%)`;
    document.getElementById('total-cell').innerHTML =
        `<span class="${pct >= 50 ? 'text-green-700' : 'text-red-700'}">${total} / ${TOTAL_MAX_MARKS}</span>`;
    const pctCell = document.getElementById('pct-cell');
    if (pctCell) pctCell.textContent = `${pct}%`;
    document.getElementById('score-bar').style.width = Math.min(100, pct) + '%';
    document.getElementById('score-bar').className =
        'h-2.5 rounded-full transition-all ' + (pct >= 50 ? 'bg-green-500' : 'bg-red-500');
}

document.querySelectorAll('.criteria-mark').forEach(input => {
    input.addEventListener('input', () => {
        updateRowStamp(parseInt(input.dataset.idx));
        recomputeTotal();
        enableCriteriaSave();
    });
});

document.querySelectorAll('.criteria-comment').forEach(input => {
    input.addEventListener('input', enableCriteriaSave);
});

// Rubric level buttons — clicking a level sets the hidden mark input and
// updates the visual grid highlighting (ring-based, matches the new grid design)
document.querySelectorAll('.rubric-level-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const idx   = parseInt(btn.dataset.idx);
        const score = parseFloat(btn.dataset.score);
        const max   = parseFloat(btn.dataset.max);

        // Update the hidden criteria-mark input → triggers stamp + total recalc
        const inp = document.getElementById('awarded-' + idx);
        if (inp) {
            inp.value = score;
            inp.dispatchEvent(new Event('input'));
        }

        // Re-style every level cell in this criterion (ring highlight for selected)
        document.querySelectorAll(`.rubric-level-btn[data-idx="${idx}"]`).forEach(b => {
            const bScore = parseFloat(b.dataset.score);
            const bMax   = parseFloat(b.dataset.max) || 1;
            const active = Math.abs(bScore - score) < 0.01;
            const bPct   = bScore / bMax;

            // Remove previous state classes
            b.classList.remove(
                'bg-green-50', 'bg-red-50', 'bg-white',
                'ring-2', 'ring-inset', 'ring-green-500', 'ring-red-400',
                'hover:bg-gray-50'
            );
            // Remove existing check badge if any
            b.querySelectorAll('.rubric-sel-badge').forEach(el => el.remove());

            if (active) {
                if (bPct >= 0.5) {
                    b.classList.add('bg-green-50', 'ring-2', 'ring-inset', 'ring-green-500');
                } else {
                    b.classList.add('bg-red-50', 'ring-2', 'ring-inset', 'ring-red-400');
                }
                // Add check badge
                const badge = document.createElement('span');
                badge.className = 'rubric-sel-badge absolute top-1.5 right-1.5 inline-flex items-center justify-center w-4 h-4 rounded-full text-[9px] font-black ' +
                    (bPct >= 0.5 ? 'bg-green-500 text-white' : 'bg-red-500 text-white');
                badge.textContent = bPct >= 0.5 ? '✓' : '✗';
                b.style.position = 'relative';
                b.appendChild(badge);
            } else {
                b.classList.add('bg-white', 'hover:bg-gray-50');
            }
        });

        enableCriteriaSave();
    });
});

function enableCriteriaSave() {
    const btn = document.getElementById('btn-save-criteria');
    btn.disabled = false;
    btn.classList.remove('opacity-60');
    btn.classList.add('opacity-100');
}

document.getElementById('btn-save-criteria')?.addEventListener('click', async () => {
    const btn    = document.getElementById('btn-save-criteria');
    const status = document.getElementById('criteria-status');

    btn.disabled      = true;
    btn.textContent   = 'Saving…';
    status.textContent = '';

    const questions = {};
    document.querySelectorAll('.criteria-mark').forEach(input => {
        const idx = parseInt(input.dataset.idx);
        questions[idx] = { awarded: parseInt(input.value) || 0 };
    });
    document.querySelectorAll('.criteria-comment').forEach(input => {
        const idx = parseInt(input.dataset.idx);
        if (!questions[idx]) questions[idx] = {};
        questions[idx].comment = input.value;
    });

    try {
        const resp = await fetch(SAVE_URL, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
            body:    JSON.stringify({ annotations, questions }),
        });
        if (!resp.ok) throw new Error(await resp.text());
        btn.textContent    = 'Save Mark Changes';
        btn.classList.add('opacity-60');
        status.textContent = 'Saved';
        setTimeout(() => { status.textContent = ''; }, 3000);
    } catch (err) {
        btn.textContent    = 'Save Mark Changes';
        btn.disabled       = false;
        status.textContent = 'Save failed';
        console.error(err);
    }
});
</script>
@endif

{{-- Fullscreen toggle (always added, independent of PDF mode) --}}
<script>
(function () {
    const area    = document.getElementById('review-area');
    const btnIn   = document.getElementById('btn-fullscreen');
    const btnOut  = document.getElementById('btn-exit-fullscreen');
    if (!area || !btnIn || !btnOut) return;

    function enterFS() {
        if (area.requestFullscreen)            area.requestFullscreen();
        else if (area.webkitRequestFullscreen) area.webkitRequestFullscreen();
        else if (area.mozRequestFullScreen)    area.mozRequestFullScreen();
        else if (area.msRequestFullscreen)     area.msRequestFullscreen();
    }

    function exitFS() {
        if (document.exitFullscreen)            document.exitFullscreen();
        else if (document.webkitExitFullscreen) document.webkitExitFullscreen();
        else if (document.mozCancelFullScreen)  document.mozCancelFullScreen();
        else if (document.msExitFullscreen)     document.msExitFullscreen();
    }

    function onFSChange() {
        const inFS = !!(
            document.fullscreenElement       ||
            document.webkitFullscreenElement ||
            document.mozFullScreenElement    ||
            document.msFullscreenElement
        );
        btnIn.classList.toggle('hidden', inFS);
        btnOut.classList.toggle('hidden', !inFS);
    }

    btnIn.addEventListener('click',  enterFS);
    btnOut.addEventListener('click', exitFS);

    document.addEventListener('fullscreenchange',       onFSChange);
    document.addEventListener('webkitfullscreenchange', onFSChange);
    document.addEventListener('mozfullscreenchange',    onFSChange);
    document.addEventListener('MSFullscreenChange',     onFSChange);

    // Esc key is handled natively by the browser — just keep UI in sync
})();
</script>
@endpush

@push('styles')
<style>
/* ── Fullscreen review mode ─────────────────────────────────────── */
#review-area:fullscreen,
#review-area:-webkit-full-screen {
    background: #f3f4f6;
    padding: 0.75rem;
    display: flex !important;
    flex-direction: column;
    overflow: hidden;
    gap: 0;
}

/* Columns fill remaining height */
#review-area:fullscreen .review-columns,
#review-area:-webkit-full-screen .review-columns {
    flex: 1;
    display: flex !important;
    min-height: 0;
    gap: 1.25rem;
}

/* Both columns: fill the remaining height, unstick (fullscreen IS the viewport) */
#review-area:fullscreen .review-columns > div,
#review-area:-webkit-full-screen .review-columns > div {
    position: static !important;
    height: 100% !important;
    overflow-y: auto;
}

/* Criteria panel fills the left column completely */
#review-area:fullscreen #criteria-panel,
#review-area:-webkit-full-screen #criteria-panel {
    height: 100% !important;
}

/* PDF viewer panel fills the right column height in fullscreen */
#review-area:fullscreen #viewer-panel,
#review-area:-webkit-full-screen #viewer-panel {
    height: 100% !important;
}

/* ── Grading rules card */
.grading-rules-card[open] .rules-chevron { transform: rotate(180deg); }
.rules-chevron { transition: transform 0.2s; }

/* Sticky column headers within the scrollable criteria table */
#criteria-table thead tr th { position: sticky; top: 0; z-index: 1; background: #f9fafb; }

/* Vertical toolbar buttons */
.vtool {
    width: 2.5rem; height: 2.5rem;
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    border-radius: 0.5rem;
    transition: background-color 0.15s;
    position: relative;
}
.vtool-label {
    font-size: 9px; line-height: 1;
    margin-top: 2px;
    color: inherit;
}
.vtool-sep {
    width: 1.75rem;
    border-top: 1px solid #e5e7eb;
    margin: 2px 0;
}

/* Active tool highlight */
.tool-btn.active-tool { background-color: #fff7ed; }
.tool-btn.active-tool .vtool-label { color: #c2410c; }
.tool-btn.active-tool svg { stroke: #c2410c; }

/* Delete button states */
#btn-delete { color: #d1d5db; cursor: not-allowed; opacity: 0.5; }
#btn-delete:not(:disabled) { color: #dc2626; cursor: pointer; opacity: 1; }
#btn-delete:not(:disabled):hover { background-color: #fef2f2; }

/* PDF container */
#pdf-container { display: flex; flex-direction: column; align-items: center; }
</style>
@endpush
