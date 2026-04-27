@extends('layouts.app')

@section('title', 'Edit Connection — ' . $integration->label)
@section('heading', 'Edit LMS Connection')
@section('breadcrumbs')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-800 transition-colors">Dashboard</a>
    <span class="text-gray-300 mx-0.5">›</span>
    <a href="{{ route('integrations.index') }}" class="hover:text-gray-800 transition-colors">LMS Integrations</a>
    <span class="text-gray-300 mx-0.5">›</span>
    <span class="text-gray-700 font-medium">{{ $integration->label }}</span>
@endsection

@section('page-actions')
    <a href="{{ route('integrations.index') }}"
       class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50">
        ← Back
    </a>
@endsection

@section('content')
<div class="max-w-xl mt-2">
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">

        @if($errors->any())
        <div class="mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">
            @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
        @endif

        <form method="POST" action="{{ route('integrations.update', $integration) }}" class="space-y-5">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Connection Label <span class="text-red-500">*</span>
                </label>
                <input type="text" name="label" value="{{ old('label', $integration->label) }}" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Moodle Site URL <span class="text-red-500">*</span>
                </label>
                <input type="url" name="base_url" value="{{ old('base_url', $integration->base_url) }}" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">API Token</label>
                <input type="password" name="api_token" autocomplete="off"
                       placeholder="Leave blank to keep existing token"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 font-mono">
                <p class="mt-1 text-xs text-gray-400">Leave blank to keep the existing token. Enter a new value to replace it.</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Course IDs</label>
                <input type="text" name="course_ids" value="{{ old('course_ids', $courseIdsStr) }}"
                       placeholder="e.g. 12, 15, 23"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 font-mono">
                <p class="mt-1 text-xs text-gray-400">Comma-separated Moodle course IDs to sync.</p>
            </div>

            <div class="pt-2 flex gap-3">
                <button type="submit"
                        class="px-5 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold rounded-lg transition">
                    Save Changes
                </button>
                <a href="{{ route('integrations.index') }}"
                   class="px-5 py-2 border border-gray-300 text-gray-600 text-sm rounded-lg hover:bg-gray-50 transition">
                    Cancel
                </a>
            </div>
        </form>
    </div>

    {{-- Sync status --}}
    <div class="mt-4 bg-gray-50 border border-gray-200 rounded-xl p-4 text-sm text-gray-600">
        <p class="font-semibold text-gray-700 mb-2">Sync Status</p>
        <div class="space-y-1 text-xs">
            <div>Last synced: <span class="text-gray-800">{{ $integration->last_synced_at ? $integration->last_synced_at->format('d M Y H:i') : 'Never' }}</span></div>
            @if($integration->last_error)
            <div class="mt-2 bg-red-50 border border-red-200 rounded px-3 py-2 text-red-700">
                <span class="font-semibold">Last error:</span> {{ $integration->last_error }}
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
