@extends('layouts.app')

@section('title', 'LMS Integrations')
@section('heading', 'LMS Integrations')
@section('breadcrumbs')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-800 transition-colors">Dashboard</a>
    <span class="text-gray-300 mx-0.5">›</span>
    <span class="text-gray-700 font-medium">LMS Integrations</span>
@endsection

@section('page-actions')
    <a href="{{ route('integrations.create') }}"
       class="inline-flex items-center gap-2 px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold rounded-lg transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Add Connection
    </a>
@endsection

@section('content')

@if(session('success'))
<div class="mb-4 px-4 py-3 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm">{{ session('success') }}</div>
@endif
@if(session('error'))
<div class="mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">{{ session('error') }}</div>
@endif
@if(session('info'))
<div class="mb-4 px-4 py-3 rounded-lg bg-blue-50 border border-blue-200 text-blue-800 text-sm">{{ session('info') }}</div>
@endif

<div class="mb-6 bg-blue-50 border border-blue-200 rounded-xl p-4 text-sm text-blue-800 flex gap-3">
    <svg class="w-5 h-5 flex-shrink-0 mt-0.5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    <div>
        <p class="font-semibold">Moodle LMS Integration</p>
        <p class="mt-1 text-blue-700">Connect your Moodle site to fetch assignments and learner submissions directly into AMS, then push graded results back to Moodle in one click. You will need your Moodle site URL and a Web Services API token.</p>
    </div>
</div>

@if($connections->isEmpty())
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-10 text-center">
        <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
        </div>
        <h3 class="text-base font-semibold text-gray-800 mb-1">No LMS connections yet</h3>
        <p class="text-sm text-gray-500 mb-4">Add your first Moodle connection to start syncing.</p>
        <a href="{{ route('integrations.create') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold rounded-lg transition">
            Add Moodle Connection
        </a>
    </div>
@else
    <div class="space-y-4">
        @foreach($connections as $connection)
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-3 flex-wrap">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-orange-100 text-orange-700">
                                {{ $connection->providerLabel() }}
                            </span>
                            <h3 class="text-base font-semibold text-gray-900">{{ $connection->label }}</h3>
                        </div>
                        @if(! $connection->last_error)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-50 text-green-700 border border-green-200">
                                <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> OK
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-red-50 text-red-700 border border-red-200">
                                <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span> Error
                            </span>
                        @endif
                    </div>

                    <p class="mt-1 text-sm text-gray-500 break-all">{{ $connection->base_url }}</p>

                    @if(! empty($connection->course_ids))
                    <p class="mt-1 text-xs text-gray-400">
                        Course IDs: <span class="font-mono text-gray-600">{{ implode(', ', $connection->course_ids) }}</span>
                    </p>
                    @else
                    <p class="mt-1 text-xs text-yellow-600 font-medium">No course IDs set — add course IDs to enable sync.</p>
                    @endif

                    <div class="mt-2 flex flex-wrap gap-4 text-xs text-gray-400">
                        @if($connection->last_synced_at)
                            <span>Last synced: {{ $connection->last_synced_at->format('d M Y H:i') }}</span>
                        @else
                            <span>Never synced</span>
                        @endif
                        <span>Added: {{ $connection->created_at->format('d M Y') }}</span>
                    </div>

                    @if($connection->last_error)
                    <div class="mt-2 bg-red-50 border border-red-200 rounded-lg px-3 py-2 text-xs text-red-700">
                        <span class="font-semibold">Last error:</span> {{ $connection->last_error }}
                    </div>
                    @endif
                </div>

                <div class="flex flex-wrap gap-2 items-start flex-shrink-0">
                    {{-- Test connection --}}
                    <form method="POST" action="{{ route('integrations.test', $connection) }}">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-600 hover:text-blue-700 bg-gray-100 hover:bg-blue-50 border border-gray-200 hover:border-blue-300 rounded-lg transition">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Test
                        </button>
                    </form>

                    {{-- Fetch accessible courses --}}
                    <form method="POST" action="{{ route('integrations.fetch-courses', $connection) }}">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-600 hover:text-green-700 bg-gray-100 hover:bg-green-50 border border-gray-200 hover:border-green-300 rounded-lg transition">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                            Fetch Courses
                        </button>
                    </form>

                    {{-- Sync from Moodle --}}
                    <button onclick="document.getElementById('sync-modal-{{ $connection->id }}').classList.remove('hidden')"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-white bg-brand-600 hover:bg-brand-700 rounded-lg transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Sync from Moodle
                    </button>

                    {{-- Edit --}}
                    <a href="{{ route('integrations.edit', $connection) }}"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-600 hover:text-gray-900 bg-gray-100 hover:bg-gray-200 border border-gray-200 rounded-lg transition">
                        Edit
                    </a>

                    {{-- Delete --}}
                    <form method="POST" action="{{ route('integrations.destroy', $connection) }}"
                          onsubmit="return confirm('Remove this connection? This cannot be undone.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-red-600 hover:text-red-800 bg-red-50 hover:bg-red-100 border border-red-200 rounded-lg transition">
                            Remove
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Sync Modal --}}
        <div id="sync-modal-{{ $connection->id }}"
            class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40">

            <div class="bg-white rounded-xl shadow-xl border border-gray-200 p-6 w-full max-w-md mx-4">
                <h3 class="text-base font-semibold text-gray-900 mb-1">Sync from Moodle</h3>
                <p class="text-sm text-gray-500 mb-4">
                    Assignments will be imported into the selected qualification.
                    Existing assignments already imported from this connection will be skipped.
                </p>

                <form method="POST" action="{{ route('integrations.sync', $connection) }}">
                    @csrf

                    @php
                        // ✅ THIS is the source of truth
                        $courses = $connection->last_fetched_courses ?? [];
                    @endphp

                    {{-- Qualification selection --}}
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Import into Qualification
                        </label>

                        <select name="qualification_id" required
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm
                                    focus:outline-none focus:ring-2 focus:ring-orange-400">
                            <option value="">— select qualification —</option>
                            @foreach(\App\Models\Qualification::orderBy('name')->get() as $qual)
                                <option value="{{ $qual->id }}">
                                    {{ $qual->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- ✅ Moodle course selection --}}
                    @if(!empty($courses))
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Select Moodle Courses to Import
                            </label>

                            <div class="max-h-48 overflow-y-auto border border-gray-200 rounded-lg p-3 space-y-2">
                                @foreach($courses as $course)
                                    <label class="flex items-start gap-2 text-sm text-gray-700">
                                        <input
                                            type="checkbox"
                                            name="course_ids[]"
                                            value="{{ $course['id'] }}"
                                            class="mt-1 rounded border-gray-300
                                                text-brand-600 focus:ring-brand-500">
                                        <span>
                                            {{ $course['fullname']
                                                ?? $course['shortname']
                                                ?? 'Course '.$course['id'] }}
                                            <span class="text-xs text-gray-500 block">
                                                Moodle ID: {{ $course['id'] }}
                                            </span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <p class="text-sm text-gray-500 mb-4">
                            No Moodle courses fetched yet. Click “Fetch Courses” first.
                        </p>
                    @endif

                    <div class="flex gap-3 justify-end">
                        <button type="button"
                                onclick="document.getElementById('sync-modal-{{ $connection->id }}')
                                        .classList.add('hidden')"
                                class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900
                                    border border-gray-200 rounded-lg transition">
                            Cancel
                        </button>

                        <button type="submit"
                                class="px-4 py-2 text-sm font-semibold text-white
                                    bg-brand-600 hover:bg-brand-700 rounded-lg transition">
                            Start Sync
                        </button>
                    </div>
                </form>
            </div>
        </div>
        @endforeach
    </div>
@endif

@endsection
