@extends('layouts.app')

@section('title', 'Dashboard — TiXMark IQ')
@section('heading', 'Dashboard')

@section('page-actions')
@endsection

@section('content')
{{-- Context switcher --}}
<div class="bg-white rounded-xl border border-gray-200 p-5 mb-6 mt-2">
    <h2 class="text-sm font-semibold text-gray-700 mb-4">Set your assessing context</h2>
    <form method="POST" action="{{ route('context.update') }}" class="flex flex-wrap gap-4 items-end">
        @csrf
        <div class="flex-1 min-w-48">
            <label class="block text-xs font-medium text-gray-600 mb-1.5">Qualification</label>
            <select name="qualification_id" id="ctx-qual"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500"
                onchange="this.form.submit()">
                <option value="">— Select qualification —</option>
                @foreach($qualifications as $q)
                    <option value="{{ $q->id }}"
                        {{ $context?->qualification_id == $q->id ? 'selected' : '' }}>
                        {{ $q->name }} (NQF {{ $q->nqf_level }})
                    </option>
                @endforeach
            </select>
        </div>

        @if($cohorts->count())
        <div class="flex-1 min-w-48">
            <label class="block text-xs font-medium text-gray-600 mb-1.5">Cohort / Class</label>
            <select name="cohort_id"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500"
                onchange="this.form.submit()">
                <option value="">— Select cohort —</option>
                @foreach($cohorts as $c)
                    <option value="{{ $c->id }}"
                        {{ $context?->cohort_id == $c->id ? 'selected' : '' }}>
                        {{ $c->name }} {{ $c->year ? "({$c->year})" : '' }}
                    </option>
                @endforeach
            </select>
        </div>
        @endif

        <button type="submit"
            class="px-4 py-2 hover:bg-[#e3b64d] hover:text-[#1e3a5f] text-white text-sm font-medium rounded-lg transition-colors bg-[#1e3a5f]">
            Update Context
        </button>
    </form>

    @if(!$qualifications->count())
        <div class="mt-4 p-4 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800">
            <strong>Welcome!</strong> Start by creating a qualification, then add a cohort and import your learners.
            <a href="{{ route('qualifications.create') }}" class="ml-2 underline font-medium">Create your first qualification →</a>
        </div>
    @endif
</div>

@if($context?->cohort_id)
@php
    $qual  = $context->qualification;
    $cohrt = $context->cohort;
@endphp

{{-- Stats row --}}
<div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">

    {{-- Learners → learners index --}}
    <a href="{{ route('qualifications.cohorts.learners.index', [$qual, $cohrt]) }}"
       class="bg-white rounded-xl border border-gray-200 p-4 hover:border-orange-300 hover:shadow-sm transition group block">
        <p class="text-xs text-gray-500 font-medium group-hover:text-orange-600 transition">Learners</p>
        <p class="text-2xl font-bold text-gray-900 mt-1">{{ $stats['learners'] ?? 0 }}</p>
        <p class="text-xs text-gray-400 mt-0.5 group-hover:text-orange-500 transition">View all →</p>
    </a>

    {{-- Submissions → cohort overview --}}
    <a href="{{ route('qualifications.cohorts.show', [$qual, $cohrt]) }}"
       class="bg-white rounded-xl border border-gray-200 p-4 hover:border-orange-300 hover:shadow-sm transition group block">
        <p class="text-xs text-gray-500 font-medium group-hover:text-orange-600 transition">Submissions</p>
        <p class="text-2xl font-bold text-gray-900 mt-1">{{ $stats['submissions'] ?? 0 }}</p>
        <p class="text-xs text-gray-400 mt-0.5 group-hover:text-orange-500 transition">View cohort →</p>
    </a>

    {{-- Pending Review → learners index (action needed) --}}
    <a href="{{ route('qualifications.cohorts.learners.index', [$qual, $cohrt]) }}"
       class="bg-white rounded-xl border border-gray-200 p-4 hover:border-orange-300 hover:shadow-sm transition group block">
        <p class="text-xs text-gray-500 font-medium group-hover:text-orange-600 transition">Pending Review</p>
        <p class="text-2xl font-bold text-orange-600 mt-1">{{ $stats['pending_review'] ?? 0 }}</p>
        <p class="text-xs text-gray-400 mt-0.5 group-hover:text-orange-500 transition">Needs action →</p>
    </a>

    {{-- Signed Off → learners index --}}
    <a href="{{ route('qualifications.cohorts.learners.index', [$qual, $cohrt]) }}"
       class="bg-white rounded-xl border border-gray-200 p-4 hover:border-green-300 hover:shadow-sm transition group block">
        <p class="text-xs text-gray-500 font-medium group-hover:text-green-700 transition">Signed Off</p>
        <p class="text-2xl font-bold text-green-600 mt-1">{{ $stats['signed_off'] ?? 0 }}</p>
        <p class="text-xs text-gray-400 mt-0.5 group-hover:text-green-600 transition">View learners →</p>
    </a>

    {{-- Emailed → learners index --}}
    <a href="{{ route('qualifications.cohorts.learners.index', [$qual, $cohrt]) }}"
       class="bg-white rounded-xl border border-gray-200 p-4 hover:border-purple-300 hover:shadow-sm transition group block">
        <p class="text-xs text-gray-500 font-medium group-hover:text-purple-700 transition">Emailed</p>
        <p class="text-2xl font-bold text-purple-600 mt-1">{{ $stats['emailed'] ?? 0 }}</p>
        <p class="text-xs text-gray-400 mt-0.5 group-hover:text-purple-600 transition">View learners →</p>
    </a>

</div>

{{-- Recent activity --}}
@if($recent->count())
<div class="bg-white rounded-xl border border-gray-200">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-900">Recent Submissions</h3>
        <a href="{{ route('qualifications.cohorts.learners.index', [$qual, $cohrt]) }}"
           class="text-xs text-orange-600 hover:underline font-medium">View all learners →</a>
    </div>
    <div class="divide-y divide-gray-100">
        @foreach($recent as $sub)
        @php
            $badge  = $sub->statusBadge();
            $learner = $sub->learner;
            // Link directly to the submission review if marked, otherwise to POE
            $subLink = in_array($sub->status, ['review_required','signed_off','emailed','marking','queued'])
                ? route('qualifications.cohorts.learners.submissions.show', [$qual, $cohrt, $learner, $sub])
                : route('qualifications.cohorts.learners.poe', [$qual, $cohrt, $learner]);
            $poeLink = route('qualifications.cohorts.learners.poe', [$qual, $cohrt, $learner]);
        @endphp
        <div class="px-5 py-3 flex items-center justify-between hover:bg-gray-50 transition">
            <div class="flex-1 min-w-0">
                <a href="{{ $poeLink }}"
                   class="text-sm font-medium text-gray-900 hover:text-orange-600 transition">
                    {{ $learner->full_name }}
                </a>
                <p class="text-xs text-gray-500 truncate">{{ $sub->assignment->name }}</p>
            </div>
            <a href="{{ $subLink }}"
               class="ml-3 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badge['class'] }} hover:opacity-80 transition shrink-0">
                {{ $badge['label'] }}
            </a>
        </div>
        @endforeach
    </div>
</div>
@else
<div class="bg-white rounded-xl border border-gray-200 p-8 text-center text-gray-400">
    <p class="text-sm">No submissions yet in this cohort.</p>
    <a href="{{ route('qualifications.cohorts.learners.index', [$qual, $cohrt]) }}"
       class="mt-2 inline-block text-xs text-orange-600 hover:underline">Go to learners →</a>
</div>
@endif

@else
<div class="bg-white rounded-xl border border-gray-200 p-8 text-center">
    <div class="w-12 h-12 bg-gray-100 rounded-xl flex items-center justify-center mx-auto mb-3">
        <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
        </svg>
    </div>
    <p class="text-sm font-medium text-gray-600">Select a qualification and cohort above to see your dashboard.</p>
</div>
@endif
@endsection
