@extends('layouts.app')

@section('title', $cohort->name . ' — AjanaNova AMS')
@section('heading', $cohort->name)
@section('breadcrumbs')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-800 transition-colors">Dashboard</a>
    <span class="text-gray-300 mx-0.5">›</span>
    <a href="{{ route('qualifications.index') }}" class="hover:text-gray-800 transition-colors">Qualifications</a>
    <span class="text-gray-300 mx-0.5">›</span>
    <a href="{{ route('qualifications.show', $qualification) }}" class="hover:text-gray-800 transition-colors">{{ $qualification->name }}</a>
    <span class="text-gray-300 mx-0.5">›</span>
    <span class="text-gray-700 font-medium">{{ $cohort->name }}</span>
@endsection

@section('page-actions')
    <a href="{{ route('qualifications.cohorts.learners.import', [$qualification, $cohort]) }}"
        class="inline-flex items-center gap-2 px-4 py-2 hover:bg-[#e3b64d] hover:text-[#1e3a5f] text-white text-sm font-medium rounded-lg transition-colors bg-[#1e3a5f]">
        Import Learners
    </a>
    <a href="{{ route('qualifications.cohorts.edit', [$qualification, $cohort]) }}"
        class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
        Edit
    </a>
@endsection

@section('content')
<div class="mt-2 space-y-6">
    {{-- Cohort details strip --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex flex-wrap gap-6">
            <div><p class="text-xs text-gray-500">Year</p><p class="text-sm font-semibold mt-0.5">{{ $cohort->year ?? '—' }}</p></div>
            <div><p class="text-xs text-gray-500">Venue</p><p class="text-sm font-semibold mt-0.5">{{ $cohort->venue ?? '—' }}</p></div>
            <div><p class="text-xs text-gray-500">Facilitator</p><p class="text-sm font-semibold mt-0.5">{{ $cohort->facilitator ?? '—' }}</p></div>
            <div><p class="text-xs text-gray-500">Start</p><p class="text-sm font-semibold mt-0.5">{{ $cohort->start_date?->format('d M Y') ?? '—' }}</p></div>
            <div><p class="text-xs text-gray-500">End</p><p class="text-sm font-semibold mt-0.5">{{ $cohort->end_date?->format('d M Y') ?? '—' }}</p></div>
            <div>
                <p class="text-xs text-gray-500">Status</p>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium mt-0.5 {{ $cohort->status === 'active' ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                    {{ ucfirst($cohort->status) }}
                </span>
            </div>
        </div>
    </div>

    {{-- Learners --}}
    <div>
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-sm font-semibold text-gray-900">Learners ({{ $cohort->learners->count() }})</h2>
            <a href="{{ route('qualifications.cohorts.learners.import', [$qualification, $cohort]) }}"
                class="text-xs text-orange-600 hover:underline font-medium">Import CSV →</a>
        </div>

        @if($cohort->learners->isEmpty())
            <div class="bg-white rounded-xl border border-gray-200 p-8 text-center">
                <p class="text-sm text-gray-400 mb-3">No learners in this cohort yet.</p>
                <a href="{{ route('qualifications.cohorts.learners.import', [$qualification, $cohort]) }}"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-[#1e3a5f] text-white text-sm font-medium rounded-lg hover:bg-[#e3b64d] hover:text-[#1e3a5f]">
                    Import learners from CSV
                </a>
            </div>
        @else
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50 text-left">
                            <th class="px-5 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wide">Name</th>
                            <th class="px-5 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wide">Email</th>
                            <th class="px-5 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wide">Ref</th>
                            <th class="px-5 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wide">Status</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($cohort->learners->sortBy('last_name') as $learner)
                        @php $poeUrl = route('qualifications.cohorts.learners.poe', [$qualification, $cohort, $learner]); @endphp
                        <tr class="hover:bg-orange-50 transition-colors cursor-pointer" onclick="window.location='{{ $poeUrl }}'">
                            <td class="px-5 py-3 font-medium text-orange-700 hover:underline">
                                <a href="{{ $poeUrl }}" onclick="event.stopPropagation()">{{ $learner->full_name }}</a>
                            </td>
                            <td class="px-5 py-3 text-gray-500">{{ $learner->email ?? '—' }}</td>
                            <td class="px-5 py-3 text-gray-500">{{ $learner->external_ref ?? '—' }}</td>
                            <td class="px-5 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $learner->status === 'active' ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                    {{ ucfirst($learner->status) }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-right" onclick="event.stopPropagation()">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ $poeUrl }}" class="text-xs text-blue-600 hover:text-blue-800 font-medium">POE →</a>
                                    <form method="POST" action="{{ route('qualifications.cohorts.learners.destroy', [$qualification, $cohort, $learner]) }}"
                                        onsubmit="return confirm('Remove {{ addslashes($learner->full_name) }} from this cohort?')" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs text-red-400 hover:text-red-600">Remove</button>
                                    </form>
                                </div>
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
