@extends('layouts.app')

@section('title', 'Add LMS Connection')
@section('heading', 'Add Moodle Connection')
@section('breadcrumbs')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-800 transition-colors">Dashboard</a>
    <span class="text-gray-300 mx-0.5">›</span>
    <a href="{{ route('integrations.index') }}" class="hover:text-gray-800 transition-colors">LMS Integrations</a>
    <span class="text-gray-300 mx-0.5">›</span>
    <span class="text-gray-700 font-medium">New Connection</span>
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

        <form method="POST" action="{{ route('integrations.store') }}" class="space-y-5">
            @csrf

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Connection Label <span class="text-red-500">*</span>
                </label>
                <input type="text" name="label" value="{{ old('label') }}" required
                       placeholder="e.g. TVET Moodle 2026"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
                <p class="mt-1 text-xs text-gray-400">A friendly name to identify this connection.</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Moodle Site URL <span class="text-red-500">*</span>
                </label>
                <input type="url" name="base_url" value="{{ old('base_url') }}" required
                       placeholder="https://moodle.example.com"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
                <p class="mt-1 text-xs text-gray-400">The full URL of your Moodle site (no trailing slash needed).</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    API Token <span class="text-red-500">*</span>
                </label>
                <input type="password" name="api_token" required autocomplete="off"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 font-mono">
                <p class="mt-1 text-xs text-gray-400">
                    Create a Web Services token in Moodle: <em>Site Administration → Plugins → Web Services → Manage tokens</em>.
                    The token user needs the <strong>mod/assign:grade</strong> capability.
                    The token is stored encrypted.
                </p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Course IDs
                </label>
                <input type="text" name="course_ids" value="{{ old('course_ids') }}"
                       placeholder="e.g. 12, 15, 23"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 font-mono">
                <p class="mt-1 text-xs text-gray-400">
                    Comma-separated Moodle course IDs to sync. You can find a course ID in its URL: <code class="bg-gray-100 px-1 rounded">/course/view.php?id=<strong>12</strong></code>.
                    You can add these later.
                </p>
            </div>

            <div class="pt-2 flex gap-3">
                <button type="submit"
                        class="px-5 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold rounded-lg transition">
                    Save Connection
                </button>
                <a href="{{ route('integrations.index') }}"
                   class="px-5 py-2 border border-gray-300 text-gray-600 text-sm rounded-lg hover:bg-gray-50 transition">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
