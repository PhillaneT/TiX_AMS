@extends('layouts.app')

@section('title', 'Dashboard — AjanaNova AMS')
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
            class="px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white text-sm font-medium rounded-lg transition-colors">
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
{{-- Stats row --}}
<div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
    @foreach([
        ['label' => 'Learners',        'value' => $stats['learners'] ?? 0,        'color' => 'text-gray-900'],
        ['label' => 'Submissions',     'value' => $stats['submissions'] ?? 0,     'color' => 'text-gray-900'],
        ['label' => 'Pending Review',  'value' => $stats['pending_review'] ?? 0,  'color' => 'text-orange-600'],
        ['label' => 'Signed Off',      'value' => $stats['signed_off'] ?? 0,      'color' => 'text-green-600'],
        ['label' => 'Emailed',         'value' => $stats['emailed'] ?? 0,         'color' => 'text-purple-600'],
    ] as $stat)
    <div class="bg-white rounded-xl border border-gray-200 p-4">
        <p class="text-xs text-gray-500 font-medium">{{ $stat['label'] }}</p>
        <p class="text-2xl font-bold {{ $stat['color'] }} mt-1">{{ $stat['value'] }}</p>
    </div>
    @endforeach
</div>

{{-- Recent activity --}}
@if($recent->count())
<div class="bg-white rounded-xl border border-gray-200">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="text-sm font-semibold text-gray-900">Recent Submissions</h3>
    </div>
    <div class="divide-y divide-gray-100">
        @foreach($recent as $sub)
        <div class="px-5 py-3 flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-900">{{ $sub->learner->full_name }}</p>
                <p class="text-xs text-gray-500">{{ $sub->assignment->name }}</p>
            </div>
            @php $badge = $sub->statusBadge(); @endphp
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badge['class'] }}">
                {{ $badge['label'] }}
            </span>
        </div>
        @endforeach
    </div>
</div>
@else
<div class="bg-white rounded-xl border border-gray-200 p-8 text-center text-gray-400">
    <p class="text-sm">No submissions yet in this cohort.</p>
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
