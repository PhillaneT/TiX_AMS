@extends('layouts.app')

@section('title', 'Admin — LMS Diagnostics')
@section('heading', 'Admin · LMS diagnostics')

@section('content')
@php
    // The 7 wsfunctions AMS depends on (display order matches the docs).
    $expectedFns = [
        'core_webservice_get_site_info'   => 'Connection sanity + token user',
        'core_enrol_get_users_courses'    => 'Lists courses the token sees',
        'mod_assign_get_assignments'      => 'Lists assignments per course',
        'mod_assign_get_submissions'      => 'Pulls learner submissions',
        'core_user_get_users_by_field'    => 'Resolves learner names + emails',
        'core_grading_get_definitions'    => 'Reads rubric / marking-guide criteria',
        'mod_assign_save_grade'           => 'Pushes grade + feedback (write)',
        'webservice/upload.php'           => 'Uploads feedback PDF to draft area',
    ];

    $statusBadge = function ($check) {
        if (! is_array($check)) return ['class' => 'bg-gray-100 text-gray-600', 'label' => '—'];
        if ($check['ok'] === null) return ['class' => 'bg-gray-100 text-gray-600', 'label' => 'Not probed'];
        if ($check['ok'] === true) return ['class' => 'bg-green-100 text-green-700', 'label' => 'OK'];
        if ($check['reachable'] === false) return ['class' => 'bg-red-100 text-red-700', 'label' => 'Access denied'];
        return ['class' => 'bg-amber-100 text-amber-700', 'label' => 'Reachable'];
    };
@endphp

<div class="max-w-6xl mx-auto pt-4 space-y-8">

    @if(session('warning'))
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-800">
            {{ session('warning') }}
        </div>
    @endif

    {{-- ───────────────────────── Connections + self-test ───────────────────────── --}}
    <section class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-200">
            <h3 class="text-sm font-semibold text-gray-900">Moodle connections</h3>
            <p class="text-xs text-gray-500">Probe each wsfunction the AMS push pipeline depends on. Run a self-test whenever you change the External Service in Moodle.</p>
        </div>

        @forelse($connections as $conn)
        <div class="px-5 py-4 border-b border-gray-100 last:border-b-0">
            <div class="flex items-center justify-between gap-3 mb-2">
                <div>
                    <p class="font-medium text-gray-900">{{ $conn->label }}</p>
                    <p class="text-xs text-gray-500">{{ $conn->getBaseUrl() }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <form method="POST" action="{{ route('admin.lms.preflight-all', $conn) }}">
                        @csrf
                        <button type="submit"
                            class="text-xs font-medium px-3 py-1.5 rounded border border-gray-300 bg-white hover:bg-gray-50">
                            Re-check all assignments
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.lms.diagnose', $conn) }}">
                        @csrf
                        <button type="submit"
                            class="text-xs font-medium px-3 py-1.5 rounded bg-[#e3b64d] text-white hover:opacity-90">
                            Run self-test
                        </button>
                    </form>
                </div>
            </div>

            @php $checks = $diagnostics[$conn->id] ?? null; @endphp
            @if($checks)
                <div class="mt-3 border border-gray-200 rounded-lg overflow-hidden">
                    <table class="w-full text-xs">
                        <thead class="bg-gray-50 text-gray-500 uppercase tracking-wide">
                            <tr>
                                <th class="px-3 py-2 text-left">Endpoint</th>
                                <th class="px-3 py-2 text-left">Purpose</th>
                                <th class="px-3 py-2 text-left">Status</th>
                                <th class="px-3 py-2 text-left">Detail</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                        @foreach($expectedFns as $fn => $purpose)
                            @php
                                $check = $checks[$fn] ?? null;
                                $b = $statusBadge($check);
                            @endphp
                            <tr>
                                <td class="px-3 py-2 font-mono text-[11px] text-gray-800">{{ $fn }}</td>
                                <td class="px-3 py-2 text-gray-600">{{ $purpose }}</td>
                                <td class="px-3 py-2">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-medium {{ $b['class'] }}">{{ $b['label'] }}</span>
                                </td>
                                <td class="px-3 py-2 text-gray-600">
                                    @if($check && ! empty($check['error']))
                                        <span class="text-red-600">{{ $check['error'] }}</span>
                                    @elseif($check && ! empty($check['note']))
                                        @if(is_array($check['note']))
                                            <span class="text-gray-500">
                                                @foreach($check['note'] as $k => $v)
                                                    <span class="font-mono">{{ $k }}={{ $v }}</span>{{ ! $loop->last ? ' · ' : '' }}
                                                @endforeach
                                            </span>
                                        @else
                                            <span class="text-gray-500">{{ $check['note'] }}</span>
                                        @endif
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                @php
                    $denied = collect($checks)->filter(fn($c) => is_array($c) && ($c['reachable'] ?? null) === false)->keys();
                @endphp
                @if($denied->isNotEmpty())
                    <div class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-800">
                        <strong>Action needed:</strong> the token is not authorised for
                        <span class="font-mono">{{ $denied->join(', ') }}</span>.
                        In Moodle: <em>Site administration → Server → Web services → External services → [your AMS service] → Functions → Add functions</em>.
                    </div>
                @endif
            @else
                <p class="text-xs text-gray-400 italic">Click "Run self-test" to probe this connection.</p>
            @endif
        </div>
        @empty
        <p class="px-5 py-8 text-center text-sm text-gray-500">No Moodle connections yet — add one under LMS Integrations.</p>
        @endforelse
    </section>

    {{-- ───────────────────────── Per-assignment pre-flight ───────────────────────── --}}
    <section class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-200">
            <h3 class="text-sm font-semibold text-gray-900">Assignment pre-flight</h3>
            <p class="text-xs text-gray-500">Check each Moodle-linked assignment is configured to accept grade + feedback PDF push-back.</p>
        </div>

        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-5 py-2 text-left">Assignment</th>
                    <th class="px-5 py-2 text-left">Connection</th>
                    <th class="px-5 py-2 text-left">Grading method</th>
                    <th class="px-5 py-2 text-left">Feedback file</th>
                    <th class="px-5 py-2 text-left">Last checked</th>
                    <th class="px-5 py-2 text-left">Warnings</th>
                    <th class="px-5 py-2 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            @forelse($assignments as $a)
                @php
                    $pf = $a->lms_preflight_json ?? null;
                    $info = $pf['info'] ?? [];
                    $warnings = $pf['warnings'] ?? [];
                    $fileEnabled = $info['feedback_file_enabled'] ?? null;
                @endphp
                <tr class="align-top">
                    <td class="px-5 py-3">
                        <p class="font-medium text-gray-900">{{ $a->name }}</p>
                        <p class="text-xs text-gray-500">{{ $a->qualification?->name }} · #moodle{{ $a->lms_assignment_id }}</p>
                    </td>
                    <td class="px-5 py-3 text-gray-700">{{ $a->lmsConnection?->label ?? '—' }}</td>
                    <td class="px-5 py-3 text-gray-700">
                        @if(isset($info['grading_method']))
                            <span class="capitalize">{{ $info['grading_method'] }}</span>
                            @if(isset($info['grading_published']) && ! $info['grading_published'] && in_array($info['grading_method'] ?? '', ['rubric','guide']))
                                <span class="text-[10px] text-amber-700">(unpublished)</span>
                            @endif
                        @else
                            <span class="text-gray-400 italic text-xs">unknown</span>
                        @endif
                    </td>
                    <td class="px-5 py-3">
                        @if($fileEnabled === true)
                            <span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700">Enabled</span>
                        @elseif($fileEnabled === false)
                            <span class="text-xs px-2 py-0.5 rounded-full bg-red-100 text-red-700">Disabled</span>
                        @else
                            <span class="text-xs text-gray-400 italic">—</span>
                        @endif
                    </td>
                    <td class="px-5 py-3 text-xs text-gray-500">
                        {{ $a->lms_preflight_checked_at?->diffForHumans() ?? 'never' }}
                    </td>
                    <td class="px-5 py-3">
                        @if(! empty($warnings))
                            <ul class="text-xs text-amber-800 space-y-1">
                                @foreach($warnings as $w)
                                    <li>• {{ $w }}</li>
                                @endforeach
                            </ul>
                        @elseif($pf)
                            <span class="text-xs text-green-700">No warnings</span>
                        @else
                            <span class="text-xs text-gray-400 italic">not checked</span>
                        @endif
                    </td>
                    <td class="px-5 py-3 text-right">
                        <form method="POST" action="{{ route('admin.lms.preflight', $a) }}" class="inline">
                            @csrf
                            <button type="submit"
                                class="text-xs font-medium px-3 py-1 rounded border border-gray-300 bg-white hover:bg-gray-50">
                                Check
                            </button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-5 py-8 text-center text-sm text-gray-500">No Moodle-linked assignments yet — sync some from your LMS connection first.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>

</div>
@endsection
