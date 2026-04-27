@extends('layouts.app')

@section('title', 'Add Question — ' . $assignment->name)
@section('heading', 'Add Question')
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
    <span class="text-gray-700 font-medium">Add Question</span>
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
              action="{{ route('qualifications.assignments.questions.store', [$qualification, $assignment]) }}"
              class="space-y-5">
            @csrf

            @if($errors->any())
                <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm text-red-700 space-y-1">
                    @foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach
                </div>
            @endif

            <div class="grid grid-cols-2 gap-4">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        Question label
                        <span class="text-xs text-gray-400 font-normal">(optional)</span>
                    </label>
                    <input type="text" name="label" value="{{ old('label', 'Q' . $nextOrder) }}"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500"
                        placeholder="e.g. Q1, Q2a">
                    <p class="text-xs text-gray-400 mt-1">Short label shown to assessors (e.g. Q1, Part A).</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        Marks <span class="text-red-500">*</span>
                    </label>
                    <input type="number" name="marks" value="{{ old('marks', 1) }}" min="1" required
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500"
                        placeholder="e.g. 10">
                    <p class="text-xs text-gray-400 mt-1">The assignment total will update automatically.</p>
                </div>

                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        Question text <span class="text-red-500">*</span>
                    </label>
                    <textarea name="question_text" rows="4" required
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500"
                        placeholder="Enter the full question as it appears in the assignment paper...">{{ old('question_text') }}</textarea>
                </div>

                <div class="col-span-2 pt-2 border-t border-gray-100">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">
                        Expected answer / model memo
                        <span class="text-xs text-gray-400 font-normal">(optional)</span>
                    </label>
                    <p class="text-xs text-gray-500 mb-2">The correct or model answer for this question. The AI grader uses this as its marking anchor.</p>
                    <textarea name="expected_answer" rows="5"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 font-mono"
                        placeholder="Describe what a correct or good answer looks like. Include key points, acceptable alternatives, and any partial-credit criteria...">{{ old('expected_answer') }}</textarea>
                </div>

                <div class="col-span-2 pt-2 border-t border-gray-100">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">
                        AI grading notes
                        <span class="text-xs text-gray-400 font-normal">(optional)</span>
                    </label>
                    <p class="text-xs text-gray-500 mb-2">Extra hints for the AI — key concepts to look for, common mistakes to flag, or how strictly to mark this question.</p>
                    <textarea name="ai_grading_notes" rows="3"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500"
                        placeholder="e.g. Award full marks if the learner mentions X and Y. Common mistake: confusing A with B. Do not penalise for alternative terminology.">{{ old('ai_grading_notes') }}</textarea>
                    <p class="text-xs text-gray-400 mt-1">Max 2 000 characters.</p>
                </div>

                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Display order</label>
                    <input type="number" name="order" value="{{ old('order', $nextOrder) }}" min="0"
                        class="w-40 rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                    <p class="text-xs text-gray-400 mt-1">Lower numbers appear first. You can also reorder on the assignment page.</p>
                </div>

            </div>

            <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
                <button type="submit"
                    class="px-5 py-2.5 hover:bg-[#e3b64d] hover:text-[#1e3a5f] text-white text-sm font-medium rounded-lg transition-colors bg-[#1e3a5f]">
                    Add Question
                </button>
                <a href="{{ route('qualifications.assignments.show', [$qualification, $assignment]) }}"
                    class="px-5 py-2.5 text-sm text-gray-600 hover:text-gray-900">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
