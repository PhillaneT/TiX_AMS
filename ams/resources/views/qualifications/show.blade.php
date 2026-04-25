@extends('layouts.app')

@section('title', $qualification->name . ' — AjanaNova AMS')
@section('heading', $qualification->name)
@section('breadcrumb', 'Qualifications → ' . $qualification->name)

@section('page-actions')
    <a href="{{ route('qualifications.assignments.index', $qualification) }}"
        class="inline-flex items-center gap-2 px-4 py-2 border border-orange-300 text-orange-700 bg-orange-50 text-sm font-medium rounded-lg hover:bg-orange-100 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
        Assignments
        @php $aCount = $qualification->assignments()->count(); @endphp
        @if($aCount > 0)
            <span class="text-xs bg-orange-200 text-orange-800 rounded-full px-1.5">{{ $aCount }}</span>
        @endif
    </a>
    <a href="{{ route('qualifications.modules.index', $qualification) }}"
        class="inline-flex items-center gap-2 px-4 py-2 border border-blue-300 text-blue-700 bg-blue-50 text-sm font-medium rounded-lg hover:bg-blue-100 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
        Modules
        @php $mCount = $qualification->modules()->count(); @endphp
        @if($mCount > 0)
            <span class="text-xs bg-blue-200 text-blue-800 rounded-full px-1.5">{{ $mCount }}</span>
        @endif
    </a>
    <a href="{{ route('qualifications.cohorts.create', $qualification) }}"
        class="inline-flex items-center gap-2 px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white text-sm font-medium rounded-lg transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        New Cohort
    </a>
    <a href="{{ route('qualifications.edit', $qualification) }}"
        class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
        Edit
    </a>
@endsection

@section('content')
<div class="mt-2 space-y-6">

    {{-- Qual info strip --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex flex-wrap gap-4">
            <div>
                <p class="text-xs text-gray-500 font-medium">SAQA ID</p>
                <p class="text-sm font-semibold text-gray-800 mt-0.5">{{ $qualification->saqa_id ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium">NQF Level</p>
                <p class="text-sm font-semibold text-gray-800 mt-0.5">Level {{ $qualification->nqf_level }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium">Track</p>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium mt-0.5 {{ $qualification->track === 'qcto_occupational' ? 'bg-blue-50 text-blue-700' : 'bg-purple-50 text-purple-700' }}">
                    {{ $qualification->trackLabel() }}
                </span>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium">Credits</p>
                <p class="text-sm font-semibold text-gray-800 mt-0.5">{{ $qualification->credits ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium">SETA</p>
                <p class="text-sm font-semibold text-gray-800 mt-0.5">{{ $qualification->seta }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium">Reg. Number</p>
                <p class="text-sm font-semibold text-gray-800 mt-0.5">{{ $qualification->seta_registration_number ?? '—' }}</p>
            </div>
        </div>
        @if($qualification->notes)
            <p class="text-sm text-gray-500 mt-4 pt-4 border-t border-gray-100">{{ $qualification->notes }}</p>
        @endif
    </div>

    {{-- Cohorts --}}
    <div>
        <h2 class="text-sm font-semibold text-gray-900 mb-3">Cohorts / Classes</h2>

        @if($qualification->cohorts->isEmpty())
            <div class="bg-white rounded-xl border border-gray-200 p-8 text-center">
                <p class="text-sm text-gray-400 mb-3">No cohorts yet.</p>
                <a href="{{ route('qualifications.cohorts.create', $qualification) }}"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-orange-600 text-white text-sm font-medium rounded-lg hover:bg-orange-700">
                    Add your first cohort
                </a>
            </div>
        @else
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50 text-left">
                            <th class="px-5 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wide">Cohort</th>
                            <th class="px-5 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wide">Year</th>
                            <th class="px-5 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wide">Venue</th>
                            <th class="px-5 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wide">Learners</th>
                            <th class="px-5 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wide">Status</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($qualification->cohorts as $cohort)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-5 py-3">
                                <a href="{{ route('qualifications.cohorts.show', [$qualification, $cohort]) }}"
                                    class="font-medium text-gray-900 hover:text-orange-600">
                                    {{ $cohort->name }}
                                </a>
                            </td>
                            <td class="px-5 py-3 text-gray-500">{{ $cohort->year ?? '—' }}</td>
                            <td class="px-5 py-3 text-gray-500">{{ $cohort->venue ?? '—' }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ $cohort->learners_count }}</td>
                            <td class="px-5 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                    {{ $cohort->status === 'active' ? 'bg-green-50 text-green-700' : ($cohort->status === 'completed' ? 'bg-blue-50 text-blue-700' : 'bg-gray-100 text-gray-500') }}">
                                    {{ ucfirst($cohort->status) }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-right space-x-3">
                                <a href="{{ route('qualifications.cohorts.learners.index', [$qualification, $cohort]) }}"
                                    class="text-xs text-orange-600 hover:underline">Learners</a>
                                <a href="{{ route('qualifications.cohorts.edit', [$qualification, $cohort]) }}"
                                    class="text-xs text-gray-400 hover:text-gray-700">Edit</a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
