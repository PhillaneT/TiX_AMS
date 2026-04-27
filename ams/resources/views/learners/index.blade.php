@extends('layouts.app')

@section('title', 'Learners — ' . $cohort->name)
@section('heading', 'Learners — ' . $cohort->name)
@section('breadcrumb', $qualification->name . ' → ' . $cohort->name . ' → Learners')

@section('page-actions')
    <a href="{{ route('learners.template') }}"
        class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        Download Template
    </a>
    <a href="{{ route('qualifications.cohorts.learners.import', [$qualification, $cohort]) }}"
        class="inline-flex items-center gap-2 px-4 py-2 hover:bg-orange-700 bg-[#e3b64d] text-white text-sm font-medium rounded-lg transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
        Import CSV
    </a>
@endsection

@section('content')
<div class="mt-2">
@if($learners->isEmpty())
    <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
        <svg class="w-10 h-10 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
        </svg>
        <p class="text-sm text-gray-500 mb-2">No learners in this cohort yet.</p>
        <p class="text-xs text-gray-400 mb-4">Download the template, fill it in, then import it.</p>
        <div class="flex justify-center gap-3">
            <a href="{{ route('learners.template') }}" class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50">
                Download Template
            </a>
            <a href="{{ route('qualifications.cohorts.learners.import', [$qualification, $cohort]) }}"
                class="inline-flex items-center gap-2 px-4 py-2 bg-orange-600 text-white text-sm font-medium rounded-lg hover:bg-orange-700">
                Import CSV
            </a>
        </div>
    </div>
@else
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between bg-gray-50">
            <span class="text-xs text-gray-500 font-medium">{{ $learners->count() }} learner{{ $learners->count() !== 1 ? 's' : '' }}</span>
        </div>
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 text-left">
                    <th class="px-5 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wide">Name</th>
                    <th class="px-5 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wide">Email</th>
                    <th class="px-5 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wide">Ref</th>
                    <th class="px-5 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wide">Status</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($learners as $learner)
                @php $poeUrl = route('qualifications.cohorts.learners.poe', [$qualification, $cohort, $learner]); @endphp
                <tr class="hover:bg-orange-50 transition-colors cursor-pointer" onclick="window.location='{{ $poeUrl }}'">
                    <td class="px-5 py-3 font-medium text-orange-700">
                        <a href="{{ $poeUrl }}" onclick="event.stopPropagation()">{{ $learner->full_name }}</a>
                    </td>
                    <td class="px-5 py-3 text-gray-500">{{ $learner->email ?? '—' }}</td>
                    <td class="px-5 py-3 text-gray-500 font-mono text-xs">{{ $learner->external_ref ?? '—' }}</td>
                    <td class="px-5 py-3">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                            {{ $learner->status === 'active' ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                            {{ ucfirst($learner->status) }}
                        </span>
                    </td>
                    <td class="px-5 py-3 text-right" onclick="event.stopPropagation()">
                        <div class="flex items-center justify-end gap-3">
                            <a href="{{ $poeUrl }}"
                               class="text-xs text-blue-600 hover:text-blue-800 font-medium">
                                POE →
                            </a>
                            <form method="POST" action="{{ route('qualifications.cohorts.learners.destroy', [$qualification, $cohort, $learner]) }}"
                                onsubmit="return confirm('Remove {{ addslashes($learner->full_name) }}?')" class="inline">
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
@endsection
