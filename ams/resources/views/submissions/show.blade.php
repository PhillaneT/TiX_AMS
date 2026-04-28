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

{{-- Flash --}}
@if(session('success'))
<div class="mb-4 px-4 py-3 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm">{{ session('success') }}</div>
@endif
@if(session('info'))
<div class="mb-4 px-4 py-3 rounded-lg bg-blue-50 border border-blue-200 text-blue-800 text-sm">{{ session('info') }}</div>
@endif
@if($errors->any())
<div class="mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">
    @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
</div>
@endif

{{-- Summary card (full width) --}}
<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-6">
    <div class="flex items-start justify-between gap-4 flex-wrap">
        <div>
            <h1 class="text-xl font-bold text-gray-900">{{ $submission->assignment->name }}</h1>
            <div class="mt-1 text-sm text-gray-500 flex flex-wrap gap-3">
                <span>Learner: <strong class="text-gray-700">{{ $learner->full_name }}</strong></span>
                <span>&bull; File: <strong class="text-gray-700">{{ $submission->original_filename }}</strong></span>
                <span>&bull; Uploaded: {{ $submission->created_at->format('d M Y H:i') }}</span>
            </div>
        </div>

        @if($result)
        @php $rec = $result->ai_recommendation; $isC = $rec === 'COMPETENT'; @endphp
        <div class="text-center px-5 py-3 rounded-xl border-2 {{ $isC ? 'bg-green-50 border-green-300' : 'bg-red-50 border-red-300' }}">
            <div class="text-xs font-semibold {{ $isC ? 'text-green-600' : 'text-red-600' }} mb-0.5">AI Recommendation</div>
            <div class="text-lg font-black {{ $isC ? 'text-green-700' : 'text-red-700' }}">
                {{ $isC ? 'COMPETENT' : 'NOT YET COMPETENT' }}
            </div>
            <div class="text-xs {{ $isC ? 'text-green-500' : 'text-red-500' }} mt-0.5">
                Confidence: {{ $result->confidence }}
                @if($result->mock_mode) &bull; <span class="font-semibold">MOCK</span> @endif
            </div>
        </div>
        @endif
    </div>
</div>

@php
    $isPdf      = strtolower(pathinfo($submission->original_filename, PATHINFO_EXTENSION)) === 'pdf';
    $fileUrl    = route('qualifications.cohorts.learners.submissions.file',   [$qualification, $cohort, $learner, $submission]);
    $saveAnnUrl = route('qualifications.cohorts.learners.submissions.annotations', [$qualification, $cohort, $learner, $submission]);
    $initialAnnotations = $result?->annotations_json ?? [];
@endphp

{{-- ═══════════════════════════════════════════════════════════════
     PDF ANNOTATION VIEWER (PDF submissions only, only after marking)
     ═══════════════════════════════════════════════════════════════ --}}
@if($isPdf && $result)
<div class="mb-6 bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden" id="viewer-panel">

    {{-- Toggle header --}}
    <button type="button" id="viewer-toggle"
            class="w-full flex items-center justify-between px-5 py-3.5 bg-gray-50 border-b border-gray-200 hover:bg-gray-100 transition text-left">
        <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0121 9.414V19a2 2 0 01-2 2z"/>
            </svg>
            <span class="font-semibold text-gray-800 text-sm">PDF Annotation Preview</span>
            <span class="text-xs text-gray-400">— toggle stamps on/off, move or add/remove marks before sign-off</span>
        </div>
        <svg id="viewer-chevron" class="w-4 h-4 text-gray-400 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    {{-- Viewer body (hidden by default, toggled by JS) --}}
    <div id="viewer-body" class="hidden">

        {{-- Toolbar --}}
        <div class="flex flex-wrap items-center gap-2 px-4 py-3 bg-gray-50 border-b border-gray-100 sticky top-0 z-10">
            {{-- Tool buttons --}}
            <div class="flex rounded-lg border border-gray-200 overflow-hidden text-xs font-semibold">
                <button type="button" data-tool="select" id="tool-select"
                        class="tool-btn active-tool px-3 py-1.5 bg-white text-gray-700 hover:bg-gray-100 border-r border-gray-200 flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5"/></svg>
                    Select
                </button>
                <button type="button" data-tool="tick" id="tool-tick"
                        class="tool-btn px-3 py-1.5 bg-white text-green-700 hover:bg-green-50 border-r border-gray-200 flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Add Tick
                </button>
                <button type="button" data-tool="cross" id="tool-cross"
                        class="tool-btn px-3 py-1.5 bg-white text-red-600 hover:bg-red-50 flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    Add Cross
                </button>
            </div>

            {{-- Delete selected --}}
            <button type="button" id="btn-delete" disabled
                    class="px-3 py-1.5 rounded-lg border border-gray-200 text-xs font-semibold text-gray-400
                           disabled:opacity-40 disabled:cursor-not-allowed
                           enabled:text-red-600 enabled:border-red-200 enabled:hover:bg-red-50 transition flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                Delete
            </button>

            {{-- Toggle stamp visibility --}}
            <button type="button" id="btn-toggle-stamps"
                    class="px-3 py-1.5 rounded-lg border border-gray-200 text-xs font-semibold text-gray-600 hover:bg-gray-50 transition flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                <span id="toggle-stamp-label">Hide Stamps</span>
            </button>

            <div class="flex-1"></div>

            {{-- Save --}}
            <button type="button" id="btn-save-ann"
                    class="px-4 py-1.5 rounded-lg text-xs font-semibold text-white bg-orange-500 hover:bg-orange-600 transition flex items-center gap-1.5 opacity-60"
                    title="Save annotation changes">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                <span id="save-ann-label">Save Annotations</span>
            </button>

            {{-- Status indicator --}}
            <div id="ann-status" class="text-xs text-gray-400 hidden"></div>
        </div>

        {{-- Hint bar --}}
        <div class="px-4 py-1.5 bg-amber-50 border-b border-amber-100 text-xs text-amber-700">
            <strong>Select</strong> a stamp then click <strong>Delete</strong> to remove it &bull;
            <strong>Double-click</strong> a stamp to flip tick ↔ cross &bull;
            Switch to <strong>Add Tick / Add Cross</strong> then click anywhere on the PDF to place a stamp
        </div>

        {{-- PDF rendering area --}}
        <div id="pdf-loading" class="flex items-center justify-center py-16 text-sm text-gray-400 gap-2">
            <svg class="animate-spin w-5 h-5 text-orange-400" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
            </svg>
            Loading PDF…
        </div>

        <div id="pdf-container" class="overflow-x-auto p-4 bg-gray-100 hidden"></div>

        <div id="pdf-error" class="hidden px-4 py-8 text-sm text-red-600 text-center">
            Could not load the PDF preview. The file may not be a standard PDF.
        </div>
    </div>
</div>
@endif

{{-- ═══════════════════════════════════════════════════════════════
     MAIN TWO-COLUMN LAYOUT
     ═══════════════════════════════════════════════════════════════ --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Left: editable criteria table --}}
    <div class="lg:col-span-2 space-y-6">

        @if($result && ! empty($result->questions_json))
        @php
            $questions    = $result->questions_json;
            $totalMax     = collect($questions)->sum('max_marks');
            $totalAwarded = collect($questions)->sum('awarded');
            $pct          = $totalMax > 0 ? round($totalAwarded / $totalMax * 100) : 0;
        @endphp
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden" id="criteria-panel">
            <div class="px-5 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                <h2 class="font-semibold text-gray-800 text-sm">Criterion-by-Criterion Breakdown
                    <span class="ml-2 text-xs font-normal text-gray-400">(edit marks or comments, then save)</span>
                </h2>
                <span id="total-display" class="text-sm font-bold {{ $pct >= 50 ? 'text-green-700' : 'text-red-700' }}">
                    {{ $totalAwarded }} / {{ $totalMax }} ({{ $pct }}%)
                </span>
            </div>

            {{-- Score bar --}}
            <div class="px-5 pt-3 pb-1">
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

            <table class="w-full text-sm" id="criteria-table">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                        <th class="px-3 py-2 text-left w-6">#</th>
                        <th class="px-3 py-2 text-left">Criterion / Question</th>
                        <th class="px-3 py-2 text-center w-28">Marks</th>
                        <th class="px-3 py-2 text-left">Comment</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($questions as $i => $q)
                    @php
                        $qPct    = $q['max_marks'] > 0 ? $q['awarded'] / $q['max_marks'] : 0;
                        $rowCls  = $qPct >= 0.5 ? 'text-green-700' : 'text-red-600';
                        $stamp   = $qPct >= 0.5 ? '✓' : '✗';
                        $stampBg = $qPct >= 0.5 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600';
                    @endphp
                    <tr class="hover:bg-gray-50" data-row="{{ $i }}">
                        <td class="px-3 py-2 text-gray-400 text-xs">
                            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-bold {{ $stampBg }}" id="row-stamp-{{ $i }}">
                                {{ $stamp }}
                            </span>
                        </td>
                        <td class="px-3 py-2 text-gray-800 text-xs leading-snug">{{ $q['criterion'] ?? $q['question'] ?? '—' }}</td>
                        <td class="px-3 py-2 text-center">
                            <div class="flex items-center justify-center gap-1">
                                <input type="number"
                                       id="awarded-{{ $i }}"
                                       data-idx="{{ $i }}"
                                       data-max="{{ $q['max_marks'] }}"
                                       value="{{ $q['awarded'] }}"
                                       min="0" max="{{ $q['max_marks'] }}"
                                       class="criteria-mark w-14 text-center text-xs font-semibold border border-gray-200 rounded px-1 py-0.5 focus:outline-none focus:ring-2 focus:ring-orange-300 {{ $rowCls }}">
                                <span class="text-xs text-gray-400">/ {{ $q['max_marks'] }}</span>
                            </div>
                        </td>
                        <td class="px-3 py-2">
                            <input type="text"
                                   id="comment-{{ $i }}"
                                   data-idx="{{ $i }}"
                                   value="{{ $q['comment'] ?? '' }}"
                                   maxlength="500"
                                   class="criteria-comment w-full text-xs border border-gray-200 rounded px-2 py-0.5 focus:outline-none focus:ring-2 focus:ring-orange-300 text-gray-600">
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-gray-200 bg-gray-50">
                        <td colspan="2" class="px-3 py-2 text-sm font-bold text-gray-700">Total</td>
                        <td class="px-3 py-2 text-center text-sm font-black" id="total-cell">
                            <span class="{{ $pct >= 50 ? 'text-green-700' : 'text-red-700' }}">{{ $totalAwarded }} / {{ $totalMax }}</span>
                        </td>
                        <td class="px-3 py-2 text-xs text-gray-400" id="pct-cell">{{ $pct }}%</td>
                    </tr>
                </tfoot>
            </table>

            {{-- Save criteria button --}}
            <div class="px-5 py-3 border-t border-gray-100 flex items-center gap-3">
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
    </div>

    {{-- Right: sign-off panel (unchanged structure) --}}
    <div class="space-y-4">

        @php
            $effectiveInstructions = trim($submission->assignment->ai_instructions ?? '')
                ?: 'Use the marking memo as a guiding framework only, not a rigid answer key. Credit any response that demonstrates genuine understanding of the core concept, even if the wording differs. Only assess within the scope of the module — do not penalise for knowledge gaps from other modules. Prioritise practical application over verbatim theory recall.';
            $mappedModules = $submission->assignment->qualificationModules ?? collect();
        @endphp
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
            <h3 class="text-xs font-semibold text-blue-800 mb-2 flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Grading Rules Applied
            </h3>
            @if($mappedModules->isNotEmpty())
            <div class="mb-2">
                <span class="text-xs text-blue-600 font-semibold">Module Scope: </span>
                @foreach($mappedModules as $m)
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-bold mr-1
                        {{ ['KM'=>'bg-blue-100 text-blue-800','PM'=>'bg-green-100 text-green-800','WM'=>'bg-orange-100 text-orange-800','US'=>'bg-purple-100 text-purple-800'][strtoupper($m->module_type)] ?? 'bg-gray-100 text-gray-700' }}">
                        {{ strtoupper($m->module_type) }}
                    </span>
                    <span class="text-xs text-blue-700">{{ $m->title }}</span>
                @endforeach
            </div>
            @endif
            <p class="text-xs text-blue-700 leading-relaxed">{{ $effectiveInstructions }}</p>
            @if(! trim($submission->assignment->ai_instructions ?? ''))
                <p class="text-xs text-blue-400 mt-1 italic">System default — set assignment-specific instructions in the assignment edit screen.</p>
            @endif
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
            <h3 class="text-sm font-semibold text-gray-800 mb-3">Submission Status</h3>
            @php $badge = $submission->statusBadge(); @endphp
            <span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold {{ $badge['class'] }}">
                {{ $badge['label'] }}
            </span>
            @if($submission->marked_at)
                <p class="mt-2 text-xs text-gray-400">Marked: {{ $submission->marked_at->format('d M Y H:i') }}</p>
            @endif
            @if($submission->signed_off_at)
                <p class="text-xs text-gray-400">Signed off: {{ $submission->signed_off_at->format('d M Y H:i') }}</p>
            @endif
            @if($result?->mock_mode)
                <div class="mt-3 px-2.5 py-1.5 rounded bg-orange-50 border border-orange-200 text-xs text-orange-700 font-semibold">
                    MOCK MODE — results are simulated, not AI-generated.
                </div>
            @endif
            @if($result?->annotated_pdf_path)
                <p class="mt-2 text-xs text-green-600 font-semibold">✓ Annotated PDF generated</p>
            @endif
        </div>

        @if($submission->status === 'review_required' && $result)
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
            <h3 class="text-sm font-semibold text-gray-800 mb-1">Assessor Sign-Off</h3>
            <p class="text-xs text-gray-400 mb-3">Save any annotation or mark changes first, then sign off.</p>
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

                <div class="mb-4">
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Moderation Notes <span class="font-normal text-gray-400">(optional)</span></label>
                    <textarea name="moderation_notes" rows="3"
                              class="w-full rounded border border-gray-300 text-xs px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-400 resize-none"
                              placeholder="Override reasons, observations, etc.">{{ old('moderation_notes') }}</textarea>
                </div>

                <div class="mb-4 text-xs text-gray-500">
                    Signing off as: <strong class="text-gray-700">{{ auth()->user()->name }}</strong>
                </div>

                <button type="submit"
                        class="w-full bg-[#e3b64d] hover:bg-[#d4a43e] text-white text-sm font-semibold px-4 py-2 rounded-lg transition">
                    Sign Off &amp; Generate Locked PDF
                </button>
            </form>
        </div>
        @endif

        @if($submission->status === 'signed_off' && $result)
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
            <h3 class="text-sm font-semibold text-gray-800 mb-3">Final Verdict</h3>
            @php $fc = $result->final_verdict === 'COMPETENT'; @endphp
            <div class="text-center py-3">
                <div class="text-2xl font-black {{ $fc ? 'text-green-700' : 'text-red-700' }}">
                    {{ $fc ? '✓ COMPETENT' : '✗ NOT YET COMPETENT' }}
                </div>
                <div class="text-xs text-gray-500 mt-1">
                    Signed off by {{ $result->assessor_name }}
                    @if($result->signed_off_at) on {{ $result->signed_off_at->format('d M Y') }}@endif
                </div>
                @if($result->assessor_override)
                    <div class="mt-2 text-xs px-2 py-1 rounded bg-yellow-50 text-yellow-700 border border-yellow-200">
                        Assessor override — verdict differs from AI recommendation.
                    </div>
                @endif
                @if($result->moderation_notes)
                    <div class="mt-2 text-xs text-left bg-gray-50 border border-gray-200 rounded p-2 text-gray-600">
                        <strong>Notes:</strong> {{ $result->moderation_notes }}
                    </div>
                @endif
            </div>

            <form method="POST" class="mt-3"
                  action="{{ route('qualifications.cohorts.learners.submissions.reopen', [$qualification, $cohort, $learner, $submission]) }}"
                  onsubmit="return confirm('Re-open this submission for re-assessment?')">
                @csrf
                <button type="submit"
                        class="w-full text-xs text-gray-500 hover:text-orange-700 border border-gray-200 hover:border-orange-300 px-4 py-1.5 rounded-lg transition">
                    Re-open for Re-assessment
                </button>
            </form>
        </div>
        @endif

        @if($submission->isFromMoodle())
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
            <div class="flex items-center gap-2 mb-3">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-orange-100 text-orange-700 border border-orange-200">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    From Moodle
                </span>
                <h3 class="text-sm font-semibold text-gray-800">Moodle Sync</h3>
            </div>
            @if($submission->lms_pushed_at)
            <div class="mb-3 px-3 py-2 rounded-lg bg-green-50 border border-green-200 text-xs text-green-700">
                Pushed to Moodle on {{ $submission->lms_pushed_at->format('d M Y H:i') }}
            </div>
            @endif
            @if($submission->status === 'signed_off')
            <form method="POST" action="{{ route('integrations.push', [$submission->lms_connection_id, $submission]) }}">
                @csrf
                <button type="submit"
                        class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-orange-600 hover:bg-orange-700 rounded-lg transition">
                    Push to Moodle
                </button>
            </form>
            @else
            <p class="text-xs text-gray-500">Sign off first to enable pushing to Moodle.</p>
            @endif
        </div>
        @endif

        <a href="{{ route('qualifications.cohorts.learners.poe', [$qualification, $cohort, $learner]) }}"
           class="block text-center text-sm text-gray-500 hover:text-orange-700 hover:underline">
            ← Back to POE
        </a>
    </div>
</div>

@endsection

@push('scripts')
@if($isPdf && $result)
<script>
// ─── Data from server ────────────────────────────────────────────────────────
const PDF_URL         = @json($fileUrl);
const SAVE_URL        = @json($saveAnnUrl);
const CSRF_TOKEN      = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
const TOTAL_MAX_MARKS = {{ collect($result->questions_json ?? [])->sum('max_marks') }};

let annotations = @json($initialAnnotations);

// ─── Viewer state ─────────────────────────────────────────────────────────────
const state = {
    activeTool:     'select',   // 'select' | 'tick' | 'cross'
    selectedIdx:    null,
    stampsVisible:  true,
    dirty:          false,
    pages:          {},         // pageNum → {canvas, svg, viewport}
    pdfDoc:         null,
    viewerOpen:     false,
};

// ─── Toggle panel ─────────────────────────────────────────────────────────────
document.getElementById('viewer-toggle').addEventListener('click', () => {
    const body    = document.getElementById('viewer-body');
    const chevron = document.getElementById('viewer-chevron');

    if (!state.viewerOpen) {
        body.classList.remove('hidden');
        chevron.style.transform = 'rotate(180deg)';
        state.viewerOpen = true;
        if (!state.pdfDoc) initViewer();
    } else {
        body.classList.add('hidden');
        chevron.style.transform = '';
        state.viewerOpen = false;
    }
});

// ─── Tool buttons ─────────────────────────────────────────────────────────────
document.querySelectorAll('.tool-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        state.activeTool   = btn.dataset.tool;
        state.selectedIdx  = null;
        document.getElementById('btn-delete').disabled = true;
        document.querySelectorAll('.tool-btn').forEach(b => b.classList.remove('active-tool'));
        btn.classList.add('active-tool');
        updateCursors();
    });
});

document.getElementById('btn-delete').addEventListener('click', deleteSelected);

document.getElementById('btn-toggle-stamps').addEventListener('click', () => {
    state.stampsVisible = !state.stampsVisible;
    document.getElementById('toggle-stamp-label').textContent =
        state.stampsVisible ? 'Hide Stamps' : 'Show Stamps';
    Object.values(state.pages).forEach(({ svg }) => {
        svg.style.display = state.stampsVisible ? '' : 'none';
    });
});

document.getElementById('btn-save-ann').addEventListener('click', saveAnnotations);

// ─── PDF.js init ──────────────────────────────────────────────────────────────
async function initViewer() {
    try {
        const pdfjsLib = window['pdfjs-dist/build/pdf'];
        pdfjsLib.GlobalWorkerOptions.workerSrc =
            'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

        const loadingTask = pdfjsLib.getDocument(PDF_URL);
        const pdfDoc = await loadingTask.promise;
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
        document.getElementById('pdf-error').classList.remove('hidden');
        console.error('PDF viewer error:', err);
    }
}

async function renderPage(pdfDoc, pageNum) {
    const page     = await pdfDoc.getPage(pageNum);
    const scale    = Math.min(1.5, (window.innerWidth - 80) / page.getViewport({ scale: 1 }).width);
    const viewport = page.getViewport({ scale });

    const wrap = document.createElement('div');
    wrap.className = 'relative inline-block mb-4 shadow-lg align-top';
    wrap.style.width  = viewport.width  + 'px';
    wrap.style.height = viewport.height + 'px';

    const canvas   = document.createElement('canvas');
    canvas.width   = viewport.width;
    canvas.height  = viewport.height;
    canvas.style.display = 'block';

    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('width',  viewport.width);
    svg.setAttribute('height', viewport.height);
    svg.style.cssText = 'position:absolute;top:0;left:0;overflow:visible';

    wrap.appendChild(canvas);
    wrap.appendChild(svg);
    document.getElementById('pdf-container').appendChild(wrap);

    const ctx = canvas.getContext('2d');
    await page.render({ canvasContext: ctx, viewport }).promise;

    state.pages[pageNum] = { canvas, svg, viewport };

    // Click on SVG to place stamp or clear selection
    svg.addEventListener('click', e => handleSvgClick(e, pageNum, viewport));
}

// ─── Stamp rendering ──────────────────────────────────────────────────────────
function renderAllStamps() {
    // Clear existing stamps from all SVG layers
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
        Object.values(state.pages).forEach(({ svg }) => {
            svg.style.display = 'none';
        });
    }
}

function buildStampEl(type, idx, x, y) {
    const ns    = 'http://www.w3.org/2000/svg';
    const color = type === 'tick' ? '#16a34a' : '#dc2626';
    const r     = 14;

    const g = document.createElementNS(ns, 'g');
    g.setAttribute('transform', `translate(${x},${y})`);
    g.setAttribute('class', 'stamp');
    g.setAttribute('data-idx', idx);
    g.style.cursor = 'pointer';

    const circle = document.createElementNS(ns, 'circle');
    circle.setAttribute('r', r);
    circle.setAttribute('fill', color);
    circle.setAttribute('fill-opacity', '0.88');
    circle.setAttribute('stroke', 'white');
    circle.setAttribute('stroke-width', '1.5');

    const text = document.createElementNS(ns, 'text');
    text.setAttribute('text-anchor', 'middle');
    text.setAttribute('dominant-baseline', 'central');
    text.setAttribute('fill', 'white');
    text.setAttribute('font-size', '16');
    text.setAttribute('font-weight', 'bold');
    text.setAttribute('pointer-events', 'none');
    text.textContent = type === 'tick' ? '✓' : '✗';

    g.appendChild(circle);
    g.appendChild(text);

    g.addEventListener('click',    e => { e.stopPropagation(); handleStampClick(e, idx, g); });
    g.addEventListener('dblclick', e => { e.stopPropagation(); toggleStampType(idx); });

    // Long-press on mobile = delete
    let pressTimer;
    g.addEventListener('touchstart', () => { pressTimer = setTimeout(() => { deleteStampAt(idx); }, 600); }, { passive: true });
    g.addEventListener('touchend',   () => clearTimeout(pressTimer));

    return g;
}

function highlightStamp(g, on) {
    const c = g.querySelector('circle');
    if (!c) return;
    c.setAttribute('stroke',       on ? '#f59e0b' : 'white');
    c.setAttribute('stroke-width', on ? '3'       : '1.5');
}

// ─── Interactions ─────────────────────────────────────────────────────────────
function handleSvgClick(e, pageNum, viewport) {
    if (state.activeTool === 'select') {
        // Clicking empty area in select mode deselects
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
        // In add mode, clicking a stamp toggles it
        toggleStampType(idx);
        return;
    }

    const alreadySelected = state.selectedIdx === idx;
    state.selectedIdx = alreadySelected ? null : idx;

    renderAllStamps();

    if (!alreadySelected) {
        // Re-fetch the element after re-render
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

    // Also flip the row stamp badge in the criteria table (if criterion_index is set)
    const ci = annotations[idx].criterion_index;
    if (ci !== null && ci !== undefined) updateRowStamp(ci);
}

function deleteSelected() {
    if (state.selectedIdx === null) return;
    deleteStampAt(state.selectedIdx);
}

function deleteStampAt(idx) {
    annotations.splice(idx, 1);
    state.selectedIdx = null;
    document.getElementById('btn-delete').disabled = true;
    renderAllStamps();
    markDirty();
}

function updateCursors() {
    Object.values(state.pages).forEach(({ svg }) => {
        svg.style.cursor = state.activeTool === 'select' ? 'default' : 'crosshair';
    });
}

// ─── Dirty tracking ───────────────────────────────────────────────────────────
function markDirty() {
    state.dirty = true;
    const btn = document.getElementById('btn-save-ann');
    btn.classList.remove('opacity-60');
    btn.classList.add('opacity-100');
}

// ─── Save annotations (fetch POST) ───────────────────────────────────────────
async function saveAnnotations() {
    const label  = document.getElementById('save-ann-label');
    const status = document.getElementById('ann-status');

    label.textContent = 'Saving…';
    status.textContent = '';
    status.classList.remove('hidden');

    try {
        const resp = await fetch(SAVE_URL, {
            method:  'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN,
                'Accept':       'application/json',
            },
            body: JSON.stringify({ annotations }),
        });

        if (!resp.ok) throw new Error(await resp.text());

        const data = await resp.json();
        label.textContent    = 'Save Annotations';
        status.textContent   = `Saved ${data.count} stamp${data.count !== 1 ? 's' : ''}`;
        state.dirty = false;

        const btn = document.getElementById('btn-save-ann');
        btn.classList.add('opacity-60');
        btn.classList.remove('opacity-100');

        setTimeout(() => { status.textContent = ''; }, 3000);
    } catch (err) {
        label.textContent  = 'Save Annotations';
        status.textContent = 'Save failed — try again';
        status.classList.add('text-red-500');
        console.error(err);
    }
}

// ─── Editable criteria table ──────────────────────────────────────────────────
function updateRowStamp(idx) {
    const el = document.getElementById(`row-stamp-${idx}`);
    if (!el) return;
    const input = document.getElementById(`awarded-${idx}`);
    if (!input) return;
    const max = parseFloat(input.dataset.max) || 1;
    const val = parseFloat(input.value) || 0;
    const isTick = val / max >= 0.5;
    el.textContent = isTick ? '✓' : '✗';
    el.className   = 'inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-bold ' +
        (isTick ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600');
}

function recomputeTotal() {
    let total = 0;
    document.querySelectorAll('.criteria-mark').forEach(input => {
        total += parseFloat(input.value) || 0;
    });
    const pct = TOTAL_MAX_MARKS > 0 ? Math.round(total / TOTAL_MAX_MARKS * 100) : 0;
    document.getElementById('total-display').textContent = `${total} / ${TOTAL_MAX_MARKS} (${pct}%)`;
    document.getElementById('total-cell').innerHTML =
        `<span class="${pct >= 50 ? 'text-green-700' : 'text-red-700'}">${total} / ${TOTAL_MAX_MARKS}</span>`;
    document.getElementById('pct-cell').textContent = `${pct}%`;
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

    // Build questions payload
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
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN,
                'Accept':       'application/json',
            },
            body: JSON.stringify({ annotations, questions }),
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"
        integrity="sha512-q+4liFwdPC/bNdhUpZx6aXDx/h77yEQtn4I1slHydcbZK34nLaR3cAeYSJshoxIOq3mjEf1TFfUhS+KBaYSLg=="
        crossorigin="anonymous"></script>
@endif
@endpush

@push('styles')
<style>
.tool-btn.active-tool { background-color: #fff7ed; color: #c2410c; }
#pdf-container { display: flex; flex-direction: column; align-items: center; }
</style>
@endpush
