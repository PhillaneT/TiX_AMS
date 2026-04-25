@extends('layouts.app')

@section('title', 'Qualifications — AjanaNova AMS')
@section('heading', 'Qualifications')
@section('breadcrumb', 'All qualifications registered in the system')

@section('page-actions')
    <a href="{{ route('qualifications.create') }}"
        class="inline-flex items-center gap-2 px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white text-sm font-medium rounded-lg transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        New Qualification
    </a>
@endsection

@section('content')
<div class="mt-2">
@if($qualifications->isEmpty())
    <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
        <svg class="w-10 h-10 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        <p class="text-sm text-gray-500 mb-4">No qualifications yet.</p>
        <a href="{{ route('qualifications.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-orange-600 text-white text-sm font-medium rounded-lg hover:bg-orange-700">
            Add your first qualification
        </a>
    </div>
@else
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 bg-gray-50 text-left">
                    <th class="px-5 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wide">Qualification</th>
                    <th class="px-5 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wide">SAQA ID</th>
                    <th class="px-5 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wide">NQF</th>
                    <th class="px-5 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wide">Track</th>
                    <th class="px-5 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wide">SETA</th>
                    <th class="px-5 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wide">Cohorts</th>
                    <th class="px-5 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wide">Status</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($qualifications as $q)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-5 py-3">
                        <a href="{{ route('qualifications.show', $q) }}" class="font-medium text-gray-900 hover:text-orange-600">
                            {{ $q->name }}
                        </a>
                    </td>
                    <td class="px-5 py-3 text-gray-500">{{ $q->saqa_id ?? '—' }}</td>
                    <td class="px-5 py-3">
                        <span class="inline-flex items-center justify-center w-7 h-7 bg-navy-900 text-white text-xs font-bold rounded-lg" style="background:#1e3a5f">{{ $q->nqf_level }}</span>
                    </td>
                    <td class="px-5 py-3">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $q->track === 'qcto_occupational' ? 'bg-blue-50 text-blue-700' : 'bg-purple-50 text-purple-700' }}">
                            {{ $q->trackLabel() }}
                        </span>
                    </td>
                    <td class="px-5 py-3 text-gray-600">{{ $q->seta }}</td>
                    <td class="px-5 py-3 text-gray-600">{{ $q->cohorts_count }}</td>
                    <td class="px-5 py-3">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $q->status === 'active' ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                            {{ ucfirst($q->status) }}
                        </span>
                    </td>
                    <td class="px-5 py-3 text-right">
                        <a href="{{ route('qualifications.edit', $q) }}" class="text-xs text-gray-400 hover:text-gray-700">Edit</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
</div>
@endsection
