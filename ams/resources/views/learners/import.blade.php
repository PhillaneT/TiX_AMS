@extends('layouts.app')

@section('title', 'Import Learners — AjanaNova AMS')
@section('heading', 'Import Learners')
@section('breadcrumbs')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-800 transition-colors">Dashboard</a>
    <span class="text-gray-300 mx-0.5">›</span>
    <a href="{{ route('qualifications.index') }}" class="hover:text-gray-800 transition-colors">Qualifications</a>
    <span class="text-gray-300 mx-0.5">›</span>
    <a href="{{ route('qualifications.show', $qualification) }}" class="hover:text-gray-800 transition-colors">{{ $qualification->name }}</a>
    <span class="text-gray-300 mx-0.5">›</span>
    <a href="{{ route('qualifications.cohorts.show', [$qualification, $cohort]) }}" class="hover:text-gray-800 transition-colors">{{ $cohort->name }}</a>
    <span class="text-gray-300 mx-0.5">›</span>
    <a href="{{ route('qualifications.cohorts.learners.index', [$qualification, $cohort]) }}" class="hover:text-gray-800 transition-colors">Learners</a>
    <span class="text-gray-300 mx-0.5">›</span>
    <span class="text-gray-700 font-medium">Import CSV</span>
@endsection

@section('content')
<div class="max-w-2xl mt-2 space-y-5">

    {{-- Instructions --}}
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-5 text-sm text-blue-800">
        <p class="font-semibold mb-2">How to import learners</p>
        <ol class="list-decimal list-inside space-y-1 text-blue-700">
            <li>Download the CSV template below.</li>
            <li>Fill in each learner: first name, last name, email (optional), and an internal reference code (optional).</li>
            <li>Save the file and upload it here.</li>
            <li>Duplicate names are skipped automatically — safe to re-import.</li>
        </ol>
        <div class="mt-3">
            <a href="{{ route('learners.template') }}"
                class="inline-flex items-center gap-2 px-3 py-1.5 bg-blue-100 hover:bg-blue-200 text-blue-800 text-xs font-medium rounded-lg transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Download ajananova_learner_template.csv
            </a>
        </div>
    </div>

    {{-- Upload form --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <form method="POST" action="{{ route('qualifications.cohorts.learners.import.store', [$qualification, $cohort]) }}"
            enctype="multipart/form-data" class="space-y-5">
            @csrf

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    CSV file <span class="text-red-500">*</span>
                </label>
                <div class="border-2 border-dashed border-gray-300 rounded-xl p-6 text-center hover:border-orange-400 transition-colors"
                    onclick="document.getElementById('csv_file').click()" style="cursor:pointer">
                    <svg class="w-8 h-8 text-gray-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    <p class="text-sm text-gray-500">Click to choose your CSV file</p>
                    <p class="text-xs text-gray-400 mt-1">or drag and drop</p>
                    <p id="file-name" class="text-xs text-orange-600 font-medium mt-2 hidden"></p>
                </div>
                <input type="file" id="csv_file" name="csv_file" accept=".csv,.txt" class="hidden"
                    onchange="document.getElementById('file-name').textContent = this.files[0]?.name; document.getElementById('file-name').classList.remove('hidden')">
            </div>

            <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
                <button type="submit" class="px-5 py-2.5 hover:bg-[#e3b64d] hover:text-[#1e3a5f] text-white text-sm font-medium rounded-lg transition-colors bg-[#1e3a5f]">
                    Import Learners
                </button>
                <a href="{{ route('qualifications.cohorts.learners.index', [$qualification, $cohort]) }}"
                    class="px-5 py-2.5 text-sm text-gray-600 hover:text-gray-900">Cancel</a>
            </div>
        </form>
    </div>

    {{-- CSV format example --}}
    <div class="bg-gray-50 rounded-xl border border-gray-200 p-4">
        <p class="text-xs font-semibold text-gray-600 mb-2 uppercase tracking-wide">Expected CSV format</p>
        <pre class="text-xs text-gray-500 font-mono leading-relaxed">first_name,last_name,email,external_ref
Jane,Dlamini,jane.dlamini@example.com,EMP001
Sipho,Nkosi,sipho.nkosi@example.com,EMP002
Thabo,Molefe,,EMP003</pre>
    </div>
</div>
@endsection
