@extends('layouts.app')

@section('title', 'Marking Result — ' . $submission->assignment->name)

@section('content')

{{-- Breadcrumb --}}
<div class="mb-6 flex flex-wrap items-center gap-2 text-sm">
    <a href="{{ route('qualifications.show', $qualification) }}" class="text-gray-500 hover:text-gray-700">
        {{ $qualification->name }}
    </a>
    <span class="text-gray-300">/</span>
    <a href="{{ route('qualifications.cohorts.show', [$qualification, $cohort]) }}" class="text-gray-500 hover:text-gray-700">
        {{ $cohort->name }}
    </a>
    <span class="text-gray-300">/</span>
    <a href="{{ route('qualifications.cohorts.learners.poe', [$qualification, $cohort, $learner]) }}" class="text-gray-500 hover:text-gray-700">
        POE: {{ $learner->full_name }}
    </a>
    <span class="text-gray-300">/</span>
    <span class="font-semibold text-gray-800">{{ $submission->assignment->name }}</span>
</div>

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

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Left: Marking result --}}
    <div class="lg:col-span-2 space-y-6">

        {{-- Summary card --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <h1 class="text-xl font-bold text-gray-900">{{ $submission->assignment->name }}</h1>
                    <div class="mt-1 text-sm text-gray-500 flex flex-wrap gap-3">
                        <span>Learner: <strong class="text-gray-700">{{ $learner->full_name }}</strong></span>
                        <span>&bull; File: <strong class="text-gray-700">{{ $submission->original_filename }}</strong></span>
                        <span>&bull; Uploaded: {{ $submission->created_at->format('d M Y H:i') }}</span>
                    </div>
                </div>

                {{-- AI Verdict badge --}}
                @if($result)
                @php
                    $rec = $result->ai_recommendation;
                    $isC = $rec === 'COMPETENT';
                @endphp
                <div class="text-center px-5 py-3 rounded-xl border-2 {{ $isC ? 'bg-green-50 border-green-300' : 'bg-red-50 border-red-300' }}">
                    <div class="text-xs font-semibold {{ $isC ? 'text-green-600' : 'text-red-600' }} mb-0.5">AI Recommendation</div>
                    <div class="text-lg font-black {{ $isC ? 'text-green-700' : 'text-red-700' }}">
                        {{ $isC ? 'COMPETENT' : 'NOT YET COMPETENT' }}
                    </div>
                    <div class="text-xs {{ $isC ? 'text-green-500' : 'text-red-500' }} mt-0.5">
                        Confidence: {{ $result->confidence }}
                        @if($result->mock_mode)
                            &bull; <span class="font-semibold">MOCK</span>
                        @endif
                    </div>
                </div>
                @endif
            </div>
        </div>

        {{-- Per-criterion marking table --}}
        @if($result && ! empty($result->questions_json))
        @php
            $questions   = $result->questions_json;
            $totalMax    = collect($questions)->sum('max_marks');
            $totalAwarded = collect($questions)->sum('awarded');
            $pct         = $totalMax > 0 ? round($totalAwarded / $totalMax * 100) : 0;
        @endphp
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                <h2 class="font-semibold text-gray-800 text-sm">Criterion-by-Criterion Breakdown</h2>
                <span class="text-sm font-bold {{ $pct >= 50 ? 'text-green-700' : 'text-red-700' }}">
                    {{ $totalAwarded }} / {{ $totalMax }} ({{ $pct }}%)
                </span>
            </div>

            {{-- Score bar --}}
            <div class="px-5 pt-3 pb-1">
                <div class="w-full bg-gray-100 rounded-full h-2.5">
                    <div class="h-2.5 rounded-full transition-all {{ $pct >= 50 ? 'bg-green-500' : 'bg-red-500' }}"
                         style="width: {{ min(100, $pct) }}%"></div>
                </div>
                <div class="flex justify-between text-xs text-gray-400 mt-1">
                    <span>0</span>
                    <span class="text-gray-500 font-medium">Pass mark: 50%</span>
                    <span>{{ $totalMax }}</span>
                </div>
            </div>

            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                        <th class="px-4 py-2 text-left">#</th>
                        <th class="px-4 py-2 text-left">Criterion / Question</th>
                        <th class="px-4 py-2 text-center w-24">Marks</th>
                        <th class="px-4 py-2 text-left">AI Comment</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($questions as $i => $q)
                    @php
                        $qPct = $q['max_marks'] > 0 ? $q['awarded'] / $q['max_marks'] : 0;
                        $rowCls = $qPct >= 0.5 ? 'text-green-700' : 'text-red-600';
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2.5 text-gray-400 text-xs">{{ $i + 1 }}</td>
                        <td class="px-4 py-2.5 text-gray-800">{{ $q['criterion'] ?? $q['question'] ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-center font-semibold {{ $rowCls }}">
                            {{ $q['awarded'] }} / {{ $q['max_marks'] }}
                        </td>
                        <td class="px-4 py-2.5 text-gray-500 text-xs">{{ $q['comment'] ?? '' }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-gray-200 bg-gray-50">
                        <td colspan="2" class="px-4 py-2.5 text-sm font-bold text-gray-700">Total</td>
                        <td class="px-4 py-2.5 text-center text-sm font-black {{ $pct >= 50 ? 'text-green-700' : 'text-red-700' }}">
                            {{ $totalAwarded }} / {{ $totalMax }}
                        </td>
                        <td class="px-4 py-2.5 text-xs text-gray-400">{{ $pct }}%</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        @elseif(! $result)
            <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-5 text-sm text-yellow-800">
                No marking result yet. Go back to the POE page and click "Run AI Marking".
            </div>
        @endif

    </div>{{-- /left col --}}

    {{-- Right: Sign-off panel --}}
    <div class="space-y-4">

        {{-- Grading instructions used --}}
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

        {{-- Status card --}}
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
        </div>

        {{-- Sign-off form (only when review_required) --}}
        @if($submission->status === 'review_required' && $result)
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
            <h3 class="text-sm font-semibold text-gray-800 mb-3">Assessor Sign-Off</h3>
            <form method="POST"
                  action="{{ route('qualifications.cohorts.learners.submissions.signoff', [$qualification, $cohort, $learner, $submission]) }}">
                @csrf

                {{-- Verdict selector --}}
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
                    @if($result->ai_recommendation !== 'COMPETENT' && $result->ai_recommendation !== 'NOT_YET_COMPETENT')
                    <p class="text-xs text-gray-400 mt-1">Please select a verdict before signing off.</p>
                    @endif
                </fieldset>

                {{-- Moderation notes --}}
                <div class="mb-4">
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Moderation Notes <span class="font-normal text-gray-400">(optional)</span></label>
                    <textarea name="moderation_notes" rows="3"
                              class="w-full rounded border border-gray-300 text-xs px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-400 resize-none"
                              placeholder="Override reasons, observations, etc.">{{ old('moderation_notes') }}</textarea>
                </div>

                {{-- Assessor name (pre-filled, read-only) --}}
                <div class="mb-4 text-xs text-gray-500">
                    Signing off as: <strong class="text-gray-700">{{ auth()->user()->name }}</strong>
                </div>

                <button type="submit"
                        class="w-full bg-orange-600 hover:bg-orange-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition">
                    Sign Off Result
                </button>
            </form>
        </div>
        @endif

        {{-- Final verdict (signed off) --}}
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

            {{-- Re-open --}}
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

        {{-- Back link --}}
        <a href="{{ route('qualifications.cohorts.learners.poe', [$qualification, $cohort, $learner]) }}"
           class="block text-center text-sm text-gray-500 hover:text-orange-700 hover:underline">
            ← Back to POE
        </a>

    </div>{{-- /right col --}}

</div>

@endsection
