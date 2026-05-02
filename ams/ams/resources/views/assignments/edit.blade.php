@extends('layouts.app')

@section('title', 'Edit — ' . $assignment->name)
@section('heading', 'Edit Assignment')
@section('breadcrumbs')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-800 transition-colors">Dashboard</a>
    <span class="text-gray-300 mx-0.5">›</span>
    <a href="{{ route('qualifications.index') }}" class="hover:text-gray-800 transition-colors">Qualifications</a>
    <span class="text-gray-300 mx-0.5">›</span>
    <a href="{{ route('qualifications.show', $qualification) }}" class="hover:text-gray-800 transition-colors">{{ $qualification->name }}</a>
    <span class="text-gray-300 mx-0.5">›</span>
    <a href="{{ route('qualifications.assignments.index', $qualification) }}" class="hover:text-gray-800 transition-colors">Assignments</a>
    <span class="text-gray-300 mx-0.5">›</span>
    <a href="{{ route('qualifications.assignments.show', [$qualification, $assignment]) }}" class="hover:text-gray-800 transition-colors">{{ $assignment->name }}</a>
    <span class="text-gray-300 mx-0.5">›</span>
    <span class="text-gray-700 font-medium">Edit</span>
@endsection

@section('page-actions')
    <a href="{{ route('qualifications.assignments.show', [$qualification, $assignment]) }}"
        class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50">
        ← Back
    </a>
@endsection

@section('content')
<div class="max-w-2xl mt-2">
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <form method="POST"
              action="{{ route('qualifications.assignments.update', [$qualification, $assignment]) }}"
              enctype="multipart/form-data"
              class="space-y-5">
            @csrf @method('PUT')

            @if($errors->any())
                <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm text-red-700 space-y-1">
                    @foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach
                </div>
            @endif

            <div class="grid grid-cols-2 gap-4">

                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Assignment name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $assignment->name) }}" required
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                </div>

                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Description</label>
                    <textarea name="description" rows="2"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">{{ old('description', $assignment->description) }}</textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Type <span class="text-red-500">*</span></label>
                    <select name="type" required
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                        <option value="formative" {{ old('type', $assignment->type) === 'formative' ? 'selected' : '' }}>Formative</option>
                        <option value="summative" {{ old('type', $assignment->type) === 'summative' ? 'selected' : '' }}>Summative</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Total marks</label>
                    <input type="number" name="total_marks" value="{{ old('total_marks', $assignment->total_marks) }}" min="1"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                    <p class="text-xs text-gray-400 mt-1">For rubric assignments, total is derived from the rubric levels.</p>
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
                        placeholder="e.g. Use the memo as a guiding framework only, not a rigid answer key. Credit any answer that demonstrates understanding of the core concept, even if worded differently. Only assess within the scope of this module — do not penalise for knowledge from other modules.">{{ old('ai_instructions', $assignment->ai_instructions) }}</textarea>
                    <p class="text-xs text-gray-400 mt-1">These instructions override the system default for this assignment only. Max 3 000 characters.</p>
                </div>

                {{-- Marking Method --}}
                <div class="col-span-2 pt-2 border-t border-gray-100">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Marking Method</label>
                    <p class="text-xs text-gray-500 mb-3">Per-question is the recommended approach — the AI gets precise, question-level anchors.</p>

                    <div class="flex flex-col gap-2 mb-4">
                        <label class="flex items-start gap-3 cursor-pointer rounded-lg border border-gray-200 px-4 py-3 hover:bg-gray-50 transition-colors has-[:checked]:border-orange-400 has-[:checked]:bg-orange-50">
                            <input type="radio" name="memo_type" value="questions" id="mt_questions"
                                {{ old('memo_type', $assignment->memo_type) === 'questions' ? 'checked' : '' }}
                                class="mt-0.5 text-orange-600 focus:ring-orange-500"
                                onchange="toggleMemoType()">
                            <div>
                                <span class="text-sm font-medium text-gray-800">Per-question memo</span>
                                <span class="ml-2 text-xs font-semibold bg-orange-100 text-orange-700 px-1.5 py-0.5 rounded">Recommended</span>
                                <p class="text-xs text-gray-500 mt-0.5">Manage individual questions with model answers on the assignment page.</p>
                            </div>
                        </label>
                        <label class="flex items-start gap-3 cursor-pointer rounded-lg border border-gray-200 px-4 py-3 hover:bg-gray-50 transition-colors has-[:checked]:border-orange-400 has-[:checked]:bg-orange-50">
                            <input type="radio" name="memo_type" value="rubric" id="mt_rubric"
                                {{ old('memo_type', $assignment->memo_type) === 'rubric' ? 'checked' : '' }}
                                class="mt-0.5 text-orange-600 focus:ring-orange-500"
                                onchange="toggleMemoType()">
                            <div>
                                <span class="text-sm font-medium text-gray-800">Rubric</span>
                                <span class="ml-2 text-xs font-semibold bg-purple-100 text-purple-700 px-1.5 py-0.5 rounded">Structured</span>
                                <p class="text-xs text-gray-500 mt-0.5">Define criteria and performance levels with point values. Import directly from Moodle if this assignment uses a rubric there.</p>
                            </div>
                        </label>
                        <label class="flex items-start gap-3 cursor-pointer rounded-lg border border-gray-200 px-4 py-3 hover:bg-gray-50 transition-colors has-[:checked]:border-orange-400 has-[:checked]:bg-orange-50">
                            <input type="radio" name="memo_type" value="text" id="mt_text"
                                {{ old('memo_type', $assignment->memo_type) === 'text' ? 'checked' : '' }}
                                class="mt-0.5 text-orange-600 focus:ring-orange-500"
                                onchange="toggleMemoType()">
                            <div>
                                <span class="text-sm font-medium text-gray-800">Text memo</span>
                                <p class="text-xs text-gray-500 mt-0.5">Paste a single memo block covering the whole assignment.</p>
                            </div>
                        </label>
                        <label class="flex items-start gap-3 cursor-pointer rounded-lg border border-gray-200 px-4 py-3 hover:bg-gray-50 transition-colors has-[:checked]:border-orange-400 has-[:checked]:bg-orange-50">
                            <input type="radio" name="memo_type" value="pdf" id="mt_pdf"
                                {{ old('memo_type', $assignment->memo_type) === 'pdf' ? 'checked' : '' }}
                                class="mt-0.5 text-orange-600 focus:ring-orange-500"
                                onchange="toggleMemoType()">
                            <div>
                                <span class="text-sm font-medium text-gray-800">Upload PDF memo</span>
                                <p class="text-xs text-gray-500 mt-0.5">Upload a PDF document as the memo.</p>
                            </div>
                        </label>
                    </div>

                    {{-- Per-question area --}}
                    <div id="memo_questions_area">
                        @php $qCount = $assignment->questions()->count(); @endphp
                        @if($qCount > 0)
                            <div class="bg-green-50 border border-green-200 rounded-lg px-4 py-3 text-sm text-green-800 flex items-center gap-3">
                                <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                <span>{{ $qCount }} question{{ $qCount === 1 ? '' : 's' }} defined. <a href="{{ route('qualifications.assignments.show', [$qualification, $assignment]) }}#questions" class="underline font-medium">View &amp; manage questions</a>.</span>
                            </div>
                        @else
                            <div class="bg-orange-50 border border-orange-200 rounded-lg px-4 py-3 text-sm text-orange-800">
                                <p class="font-medium mb-0.5">No questions added yet.</p>
                                <p class="text-xs text-orange-700">Save this assignment, then <a href="{{ route('qualifications.assignments.show', [$qualification, $assignment]) }}" class="underline font-medium">go to the assignment page</a> to add questions.</p>
                            </div>
                        @endif
                    </div>

                    {{-- Rubric builder area --}}
                    <div id="memo_rubric_area" class="{{ old('memo_type', $assignment->memo_type) !== 'rubric' ? 'hidden' : '' }}">
                        @php
                            $existingRubricForEdit = old('memo_type') === 'rubric'
                                ? json_decode(old('rubric_json'), true)
                                : $assignment->rubric_json;
                            $rubricImportUrl = $assignment->lms_cmid
                                ? route('qualifications.assignments.rubric.import', [$qualification, $assignment])
                                : null;
                        @endphp
                        @include('assignments.partials.rubric-builder', [
                            'existingRubric' => $existingRubricForEdit,
                            'importUrl'      => $rubricImportUrl,
                        ])
                    </div>

                    {{-- Text area --}}
                    <div id="memo_text_area" class="hidden">
                        <textarea name="memo_text" rows="6"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 font-mono"
                            placeholder="Paste the marking memo / model answers here...">{{ old('memo_text', $assignment->memo_text) }}</textarea>
                    </div>

                    {{-- PDF area --}}
                    <div id="memo_file_area" class="hidden">
                        @if($assignment->memo_path)
                            <div class="mb-3 flex items-center gap-3 p-3 bg-green-50 border border-green-200 rounded-lg">
                                <svg class="w-4 h-4 text-green-600 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/></svg>
                                <span class="text-sm text-green-800">PDF memo already uploaded.</span>
                                <a href="{{ route('qualifications.assignments.memo', [$qualification, $assignment]) }}"
                                    class="text-xs text-green-700 underline ml-auto">Download</a>
                            </div>
                        @endif
                        <div class="border-2 border-dashed border-gray-300 rounded-lg px-6 py-6 text-center hover:border-orange-400 transition-colors">
                            <label class="cursor-pointer">
                                <span class="text-sm font-medium text-orange-600">{{ $assignment->memo_path ? 'Upload replacement PDF' : 'Click to upload PDF memo' }}</span>
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
                    class="px-5 py-2.5 hover:bg-[#e3b64d] hover:text-[#1e3a5f] text-white text-sm font-medium rounded-lg transition-colors bg-[#1e3a5f]">
                    Save Changes
                </button>
                <a href="{{ route('qualifications.assignments.show', [$qualification, $assignment]) }}"
                    class="px-5 py-2.5 text-sm text-gray-600 hover:text-gray-900">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
function toggleMemoType() {
    const val = document.querySelector('input[name="memo_type"]:checked')?.value;
    document.getElementById('memo_questions_area').classList.toggle('hidden', val !== 'questions');
    document.getElementById('memo_rubric_area').classList.toggle('hidden', val !== 'rubric');
    document.getElementById('memo_text_area').classList.toggle('hidden', val !== 'text');
    document.getElementById('memo_file_area').classList.toggle('hidden', val !== 'pdf');
}
function showFileName(input) {
    const p = document.getElementById('file_name');
    if (input.files && input.files[0]) {
        p.textContent = '✓ ' + input.files[0].name;
        p.classList.remove('hidden');
    }
}
toggleMemoType();
</script>
@endsection
