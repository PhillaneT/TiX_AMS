@extends('layouts.app')

@section('title', 'POE — ' . $learner->full_name)

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
    <a href="{{ route('qualifications.cohorts.learners.index', [$qualification, $cohort]) }}" class="text-gray-500 hover:text-gray-700">
        Learners
    </a>
    <span class="text-gray-300">/</span>
    <span class="font-semibold text-gray-800">POE: {{ $learner->full_name }}</span>
</div>

{{-- Header card --}}
<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $learner->full_name }}</h1>
            <div class="mt-1 flex flex-wrap gap-3 text-sm text-gray-500">
                @if($learner->email)<span>{{ $learner->email }}</span>@endif
                @if($learner->external_ref)<span>&bull; Ref: {{ $learner->external_ref }}</span>@endif
                <span>&bull; Cohort: {{ $cohort->name }} ({{ $cohort->year }})</span>
            </div>
            <div class="mt-2 text-sm text-gray-600">
                <span class="font-medium">Qualification:</span>
                {{ $qualification->name }}
                @if($qualification->saqa_id)
                    &mdash; SAQA {{ $qualification->saqa_id }}
                @endif
                @if($qualification->nqf_level)
                    &mdash; NQF Level {{ $qualification->nqf_level }}
                @endif
            </div>
        </div>

        {{-- Overall summary badges --}}
        @php
            $cCount      = count(array_filter($moduleStatuses, fn($s) => $s['status'] === 'C'));
            $nycCount    = count(array_filter($moduleStatuses, fn($s) => $s['status'] === 'NYC'));
            $totalMapped = count(array_filter($moduleStatuses, fn($s) => $s['status'] !== 'unmapped'));
        @endphp
        <div class="flex gap-3 flex-wrap">
            <div class="text-center px-4 py-2 rounded-lg bg-green-50 border border-green-200">
                <div class="text-2xl font-bold text-green-700">{{ $cCount }}</div>
                <div class="text-xs text-green-600 font-medium">Competent</div>
            </div>
            <div class="text-center px-4 py-2 rounded-lg bg-red-50 border border-red-200">
                <div class="text-2xl font-bold text-red-700">{{ $nycCount }}</div>
                <div class="text-xs text-red-600 font-medium">NYC</div>
            </div>
            <div class="text-center px-4 py-2 rounded-lg bg-gray-50 border border-gray-200">
                <div class="text-2xl font-bold text-gray-700">{{ $totalMapped - $cCount - $nycCount }}</div>
                <div class="text-xs text-gray-500 font-medium">Pending</div>
            </div>
        </div>
    </div>

    {{-- Print / Export --}}
    <div class="mt-4 pt-4 border-t border-gray-100 flex gap-3">
        <button onclick="window.print()"
                class="text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold px-4 py-1.5 rounded-lg transition">
            Print / Export PDF
        </button>
        <span class="text-xs text-gray-400 self-center">Generated: {{ now()->format('d M Y H:i') }}</span>
    </div>
</div>

@if($modules->isEmpty())
    <div class="bg-white rounded-xl border border-dashed border-gray-300 p-12 text-center">
        <div class="text-3xl mb-3">📋</div>
        <p class="text-gray-500 text-sm">No qualification modules defined yet.</p>
        <a href="{{ route('qualifications.modules.index', $qualification) }}"
           class="mt-3 inline-block text-sm text-orange-600 hover:underline">
            Set up qualification modules →
        </a>
    </div>
@else

{{-- Module tracking table --}}
<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">

    <div class="px-5 py-3 bg-gray-50 border-b border-gray-200">
        <h2 class="font-semibold text-gray-800 text-sm">Portfolio of Evidence — Module Tracking</h2>
        <p class="text-xs text-gray-500 mt-0.5">Per QCTO/SAQA requirements — Competency per module/unit standard</p>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                    <th class="px-4 py-3 text-left w-8">#</th>
                    <th class="px-4 py-3 text-left w-16">Type</th>
                    <th class="px-4 py-3 text-left">Code</th>
                    <th class="px-4 py-3 text-left">Module / Unit Standard</th>
                    <th class="px-4 py-3 text-center w-16">NQF</th>
                    <th class="px-4 py-3 text-center w-16">Credits</th>
                    <th class="px-4 py-3 text-left">Assignment(s)</th>
                    <th class="px-4 py-3 text-center w-32">Result</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($modules as $mod)
                @php
                    $modStatus = $moduleStatuses[$mod->id] ?? ['status' => 'unmapped', 'label' => 'Not mapped', 'assignments' => []];
                    $colors = [
                        'KM'  => 'bg-blue-100 text-blue-800',
                        'PM'  => 'bg-green-100 text-green-800',
                        'WM'  => 'bg-orange-100 text-orange-800',
                        'US'  => 'bg-purple-100 text-purple-800',
                        'MOD' => 'bg-gray-100 text-gray-700',
                    ];
                    $badgeCls = $colors[strtoupper($mod->module_type)] ?? 'bg-gray-100 text-gray-700';
                    $resultCls = match($modStatus['status']) {
                        'C'       => 'bg-green-100 text-green-800 font-bold',
                        'NYC'     => 'bg-red-100 text-red-800 font-bold',
                        'partial' => 'bg-yellow-100 text-yellow-800',
                        'unmapped'=> 'bg-gray-50 text-gray-400 italic',
                        default   => 'bg-gray-100 text-gray-600',
                    };
                    $resultLabel = match($modStatus['status']) {
                        'C'       => 'C — Competent',
                        'NYC'     => 'NYC',
                        'partial' => $modStatus['label'],
                        'unmapped'=> '—',
                        default   => 'Pending',
                    };
                @endphp
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-4 py-3 text-gray-400 text-xs">{{ $mod->sortorder }}</td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold {{ $badgeCls }}">
                            {{ strtoupper($mod->module_type) }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <code class="text-xs text-gray-500">{{ $mod->module_code }}</code>
                    </td>
                    <td class="px-4 py-3 font-medium text-gray-800">{{ $mod->title }}</td>
                    <td class="px-4 py-3 text-center text-gray-500">{{ $mod->nqf_level ?: '—' }}</td>
                    <td class="px-4 py-3 text-center text-gray-500">{{ $mod->credits ?: '—' }}</td>
                    <td class="px-4 py-3">
                        @if(empty($modStatus['assignments']))
                            <span class="text-xs text-gray-400 italic">Not mapped to any assignment</span>
                        @else
                            <div class="space-y-1">
                                @foreach($modStatus['assignments'] as $item)
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-gray-700">{{ $item['assignment']->name }}</span>
                                    @if($item['verdict'] === 'COMPETENT')
                                        <span class="text-xs text-green-700 font-medium">✓ C</span>
                                    @elseif($item['verdict'] === 'NOT_YET_COMPETENT')
                                        <span class="text-xs text-red-700 font-medium">✗ NYC</span>
                                    @elseif($item['submission'])
                                        <span class="text-xs text-gray-400">({{ ucfirst(str_replace('_', ' ', $item['submission_status'])) }})</span>
                                    @else
                                        <span class="text-xs text-gray-300">Not submitted</span>
                                    @endif
                                </div>
                                @endforeach
                            </div>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-flex items-center justify-center px-3 py-1 rounded-full text-xs {{ $resultCls }}">
                            {{ $resultLabel }}
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Footer totals --}}
    <div class="px-5 py-3 bg-gray-50 border-t border-gray-200 flex flex-wrap items-center justify-between gap-2">
        <div class="text-xs text-gray-500">
            Total: <strong>{{ $modules->count() }}</strong> modules &bull;
            <strong>{{ $modules->sum('credits') }}</strong> credits
        </div>
        <div class="flex gap-4 text-xs">
            <span class="text-green-700 font-semibold">{{ $cCount }} Competent</span>
            <span class="text-red-700 font-semibold">{{ $nycCount }} NYC</span>
            <span class="text-gray-500">{{ $totalMapped - $cCount - $nycCount }} Pending</span>
        </div>
    </div>
</div>

{{-- Assessor declaration block (for printing) --}}
<div class="mt-6 bg-white rounded-xl border border-gray-200 shadow-sm p-5 print:block">
    <h3 class="font-semibold text-gray-800 mb-3">Assessor Declaration</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm text-gray-700">
        <div>
            <div class="border-b border-gray-300 pb-1 mb-1 text-xs text-gray-400">Assessor Name</div>
            <div class="h-6">{{ auth()->user()->name }}</div>
        </div>
        <div>
            <div class="border-b border-gray-300 pb-1 mb-1 text-xs text-gray-400">Date</div>
            <div class="h-6">{{ now()->format('d F Y') }}</div>
        </div>
        <div>
            <div class="border-b border-gray-300 pb-1 mb-1 text-xs text-gray-400">Assessor Signature</div>
            <div class="h-10"></div>
        </div>
        <div>
            <div class="border-b border-gray-300 pb-1 mb-1 text-xs text-gray-400">Moderator Signature (if applicable)</div>
            <div class="h-10"></div>
        </div>
    </div>
    <p class="mt-4 text-xs text-gray-400">
        I confirm that the above-named learner has been assessed against the stated qualification modules and that the results recorded are a true and fair reflection of the learner's competence. This record is maintained for the mandatory 5-year retention period in accordance with SAQA/QCTO requirements.
    </p>
</div>

@endif

<style>
@media print {
    nav, aside, .no-print, button { display: none !important; }
    body { font-size: 11pt; }
}
</style>
@endsection
