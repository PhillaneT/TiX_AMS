@extends('layouts.app')

@section('title', 'New Assignment — ' . $qualification->name)
@section('heading', 'New Assignment')
@section('breadcrumb', $qualification->name . ' → Assignments → New')

@section('page-actions')
    <a href="{{ route('qualifications.assignments.index', $qualification) }}"
        class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50">
        ← Back
    </a>
@endsection

@section('content')
<div class="max-w-2xl mt-2">
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <form method="POST"
              action="{{ route('qualifications.assignments.store', $qualification) }}"
              enctype="multipart/form-data"
              class="space-y-5">
            @csrf

            @if($errors->any())
                <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm text-red-700 space-y-1">
                    @foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach
                </div>
            @endif

            <div class="grid grid-cols-2 gap-4">

                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Assignment name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500"
                        placeholder="e.g. Assignment 1: Database Design">
                </div>

                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Description</label>
                    <textarea name="description" rows="2"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500"
                        placeholder="Brief description of what this assignment covers...">{{ old('description') }}</textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Type <span class="text-red-500">*</span></label>
                    <select name="type" required
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                        <option value="formative" {{ old('type', 'formative') === 'formative' ? 'selected' : '' }}>Formative</option>
                        <option value="summative" {{ old('type') === 'summative' ? 'selected' : '' }}>Summative</option>
                    </select>
                    <p class="text-xs text-gray-400 mt-1">Formative = ongoing practice; Summative = final assessment.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Total marks</label>
                    <input type="number" name="total_marks" value="{{ old('total_marks') }}" min="1"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500"
                        placeholder="e.g. 100">
                </div>

                {{-- AI Grading Instructions --}}
                <div class="col-span-2 pt-2 border-t border-gray-100">
                    <label for="ai_instructions" class="block text-sm font-semibold text-gray-700 mb-1">
                        AI Grading Instructions
                        <span class="ml-1 text-xs font-normal text-gray-400">(optional)</span>
                    </label>
                    <p class="text-xs text-gray-500 mb-2">
                        Tell the AI <em>how</em> to grade — scope, flexibility, what to credit or ignore. Leave blank to use the system default.
                    </p>
                    <textarea id="ai_instructions" name="ai_instructions" rows="4"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500"
                        placeholder="e.g. Use the memo as a guiding framework only, not a rigid answer key. Credit any answer that demonstrates understanding of the core concept, even if worded differently. Only assess within the scope of this module — do not penalise for knowledge from other modules. Prioritise practical application over theory recall.">{{ old('ai_instructions') }}</textarea>
                    <p class="text-xs text-gray-400 mt-1">These instructions override the system default for this assignment only. Max 3 000 characters.</p>
                </div>

                {{-- Memo section --}}
                <div class="col-span-2 pt-2 border-t border-gray-100">
                    <label class="block text-sm font-semibold text-gray-700 mb-3">Marking Memo</label>

                    <div class="flex gap-4 mb-4">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="memo_type" value="text" id="mt_text"
                                {{ old('memo_type', 'text') === 'text' ? 'checked' : '' }}
                                class="text-orange-600 focus:ring-orange-500"
                                onchange="toggleMemoType()">
                            <span class="text-sm text-gray-700">Text memo</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="memo_type" value="pdf" id="mt_pdf"
                                {{ old('memo_type') === 'pdf' ? 'checked' : '' }}
                                class="text-orange-600 focus:ring-orange-500"
                                onchange="toggleMemoType()">
                            <span class="text-sm text-gray-700">Upload PDF memo</span>
                        </label>
                    </div>

                    <div id="memo_text_area">
                        <textarea name="memo_text" rows="6"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 font-mono"
                            placeholder="Paste the marking memo / model answers here. The AI marker will use this to evaluate learner submissions.">{{ old('memo_text') }}</textarea>
                        <p class="text-xs text-gray-400 mt-1">This is used by the AI marker. Be as detailed as possible — include acceptable alternatives and mark allocations per question.</p>
                    </div>

                    <div id="memo_file_area" class="hidden">
                        <div class="border-2 border-dashed border-gray-300 rounded-lg px-6 py-8 text-center hover:border-orange-400 transition-colors">
                            <svg class="w-8 h-8 text-gray-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                            </svg>
                            <label class="cursor-pointer">
                                <span class="text-sm font-medium text-orange-600 hover:text-orange-700">Click to upload PDF memo</span>
                                <input type="file" name="memo_file" accept=".pdf" class="hidden" onchange="showFileName(this)">
                            </label>
                            <p class="text-xs text-gray-400 mt-1">PDF only · Max 20 MB</p>
                            <p id="file_name" class="text-xs text-green-700 font-medium mt-2 hidden"></p>
                        </div>
                    </div>
                </div>

            </div>

            <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
                <button type="submit"
                    class="px-5 py-2.5 hover:bg-orange-700 bg-[#e3b64d] text-white text-sm font-medium rounded-lg transition-colors">
                    Create Assignment
                </button>
                <a href="{{ route('qualifications.assignments.index', $qualification) }}"
                    class="px-5 py-2.5 text-sm text-gray-600 hover:text-gray-900">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
function toggleMemoType() {
    const isText = document.getElementById('mt_text').checked;
    document.getElementById('memo_text_area').classList.toggle('hidden', !isText);
    document.getElementById('memo_file_area').classList.toggle('hidden', isText);
}

function showFileName(input) {
    const p = document.getElementById('file_name');
    if (input.files && input.files[0]) {
        p.textContent = '✓ ' + input.files[0].name;
        p.classList.remove('hidden');
    }
}

// Init on load
toggleMemoType();
</script>
@endsection
