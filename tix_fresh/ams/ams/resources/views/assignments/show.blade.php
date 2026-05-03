@extends('layouts.app')

@section('title', $assignment->name . ' — ' . $qualification->name)
@section('heading', $assignment->name)
@section('breadcrumbs')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-800 transition-colors">Dashboard</a>
    <span class="text-gray-300 mx-0.5">›</span>
    <a href="{{ route('qualifications.index') }}" class="hover:text-gray-800 transition-colors">Qualifications</a>
    <span class="text-gray-300 mx-0.5">›</span>
    <a href="{{ route('qualifications.show', $qualification) }}" class="hover:text-gray-800 transition-colors">{{ $qualification->name }}</a>
    <span class="text-gray-300 mx-0.5">›</span>
    <a href="{{ route('qualifications.assignments.index', $qualification) }}" class="hover:text-gray-800 transition-colors">Assignments</a>
    <span class="text-gray-300 mx-0.5">›</span>
    <span class="text-gray-700 font-medium">{{ $assignment->name }}</span>
@endsection

@section('page-actions')
    <a href="{{ route('qualifications.assignments.index', $qualification) }}"
        class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50">
        ← Assignments
    </a>
    <a href="{{ route('qualifications.assignments.edit', [$qualification, $assignment]) }}"
        class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50">
        Edit
    </a>
@endsection

@section('content')
<div class="mt-2 space-y-5">

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 text-sm rounded-lg px-4 py-3">{{ session('success') }}</div>
    @endif

    {{-- Details card --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex flex-wrap gap-6">
            <div>
                <p class="text-xs text-gray-500 font-medium">Type</p>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold mt-1
                    {{ $assignment->type === 'summative' ? 'bg-purple-50 text-purple-700' : 'bg-blue-50 text-blue-700' }}">
                    {{ ucfirst($assignment->type) }}
                </span>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium">Total Marks</p>
                <p class="text-sm font-semibold text-gray-800 mt-1">
                    @if($assignment->memo_type === 'rubric' && !empty($assignment->rubric_json))
                        @php
                            $rubricTotal = collect($assignment->rubric_json)->sum(function($c) {
                                return collect($c['levels'] ?? [])->max('score') ?? 0;
                            });
                        @endphp
                        {{ $rubricTotal }}
                        <span class="text-xs font-normal text-gray-400 ml-1">(from rubric)</span>
                    @elseif($assignment->questions->isNotEmpty())
                        {{ $assignment->questions->sum('marks') }}
                        <span class="text-xs font-normal text-gray-400 ml-1">(from questions)</span>
                    @else
                        {{ $assignment->total_marks ?? '—' }}
                    @endif
                </p>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium">Marking Method</p>
                <p class="text-sm font-semibold text-gray-800 mt-1">
                    @if($assignment->memo_type === 'questions')
                        <span class="inline-flex items-center gap-1 text-orange-600">Per-question (see below)</span>
                    @elseif($assignment->memo_type === 'rubric')
                        <span class="inline-flex items-center gap-1 text-purple-600">
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM11 13a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                            Rubric (see below)
                        </span>
                    @elseif($assignment->memo_type === 'pdf' && $assignment->memo_path)
                        <a href="{{ route('qualifications.assignments.memo', [$qualification, $assignment]) }}"
                            class="inline-flex items-center gap-1 text-red-600 hover:text-red-800">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/></svg>
                            Download PDF Memo
                        </a>
                    @elseif($assignment->memo_type === 'text')
                        Text (see below)
                    @else
                        No memo uploaded
                    @endif
                </p>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium">Mapped Modules</p>
                <p class="text-sm font-semibold text-gray-800 mt-1">{{ $assignment->qualificationModules->count() }}</p>
            </div>
        </div>
        @if($assignment->description)
            <p class="text-sm text-gray-600 mt-4 pt-4 border-t border-gray-100">{{ $assignment->description }}</p>
        @endif
    </div>

    {{-- Mapped modules --}}
    @if($assignment->qualificationModules->isNotEmpty())
    <div>
        <h2 class="text-sm font-semibold text-gray-900 mb-2">Mapped to Modules</h2>
        <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
            @foreach($assignment->qualificationModules as $mod)
            <div class="px-5 py-3 flex items-center gap-3">
                @php
                    $colors = ['KM'=>'bg-blue-100 text-blue-800','PM'=>'bg-green-100 text-green-800','WM'=>'bg-orange-100 text-orange-800','US'=>'bg-purple-100 text-purple-800','MOD'=>'bg-gray-100 text-gray-700'];
                    $cls = $colors[strtoupper($mod->module_type)] ?? 'bg-gray-100 text-gray-700';
                @endphp
                <span class="inline-flex px-2 py-0.5 rounded text-xs font-bold {{ $cls }}">{{ strtoupper($mod->module_type) }}</span>
                <span class="text-xs text-gray-500 font-mono">{{ $mod->module_code }}</span>
                <span class="text-sm text-gray-800">{{ $mod->title }}</span>
            </div>
            @endforeach
        </div>
        <p class="text-xs text-gray-400 mt-2 px-1">
            Change mappings on the <a href="{{ route('qualifications.modules.index', $qualification) }}" class="text-blue-600 hover:underline">Modules page</a>.
        </p>
    </div>
    @else
    <div class="bg-amber-50 border border-amber-200 rounded-xl px-5 py-4 text-sm text-amber-800">
        <strong>Not mapped yet.</strong> Go to the
        <a href="{{ route('qualifications.modules.index', $qualification) }}" class="underline font-semibold">Modules page</a>
        to map this assignment to the qualification module(s) it covers. This is required for POE tracking.
    </div>
    @endif

    {{-- ===== RUBRIC section ===== --}}
    @if($assignment->memo_type === 'rubric')
    <div id="rubric">
        <div class="flex items-center justify-between mb-2">
            <h2 class="text-sm font-semibold text-gray-900">
                Rubric
                @if(!empty($assignment->rubric_json))
                    <span class="ml-1.5 text-xs font-normal text-gray-400">
                        {{ count($assignment->rubric_json) }} {{ count($assignment->rubric_json) === 1 ? 'criterion' : 'criteria' }}
                    </span>
                @endif
            </h2>
            <a href="{{ route('qualifications.assignments.edit', [$qualification, $assignment]) }}#memo_rubric_area"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                Edit Rubric
            </a>
        </div>

        @if(empty($assignment->rubric_json))
            <div class="bg-white rounded-xl border border-dashed border-gray-300 px-5 py-8 text-center">
                <p class="text-sm text-gray-500 mb-1">No rubric defined yet.</p>
                <p class="text-xs text-gray-400">Go to Edit to build the rubric criteria and performance levels.</p>
                <a href="{{ route('qualifications.assignments.edit', [$qualification, $assignment]) }}"
                    class="inline-flex items-center gap-1.5 mt-4 px-4 py-2 text-sm font-medium bg-[#1e3a5f] text-white rounded-lg hover:bg-[#e3b64d] hover:text-[#1e3a5f] transition-colors">
                    Build Rubric
                </a>
            </div>
        @else
            <div class="space-y-3">
                @foreach($assignment->rubric_json as $ci => $criterion)
                @php
                    $maxScore = collect($criterion['levels'] ?? [])->max('score') ?? 0;
                    $sortedLevels = collect($criterion['levels'] ?? [])->sortBy('score')->values();
                @endphp
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <div class="px-5 py-3 border-b border-gray-100 flex items-center gap-3 bg-gray-50">
                        <span class="w-6 h-6 flex items-center justify-center rounded-full bg-[#1e3a5f] text-white text-xs font-bold shrink-0">{{ $ci + 1 }}</span>
                        <span class="text-sm font-semibold text-gray-800 flex-1">{{ $criterion['title'] ?? 'Criterion ' . ($ci + 1) }}</span>
                        <span class="text-xs text-gray-500 shrink-0">max {{ $maxScore }} pts</span>
                    </div>
                    @if(!empty($criterion['description']))
                        <p class="px-5 py-2 text-xs text-gray-500 border-b border-gray-100">{{ $criterion['description'] }}</p>
                    @endif
                    <div class="divide-y divide-gray-50">
                        @foreach($sortedLevels as $level)
                        <div class="px-5 py-3 flex items-start gap-4">
                            <span class="shrink-0 w-12 text-center">
                                <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-full text-xs font-bold
                                    @if($level['score'] == $maxScore) bg-green-100 text-green-700
                                    @elseif($level['score'] == 0) bg-red-50 text-red-400
                                    @else bg-amber-50 text-amber-600
                                    @endif">
                                    {{ $level['score'] }}
                                </span>
                            </span>
                            <p class="text-xs text-gray-700 leading-relaxed flex-1">
                                {{ $level['description'] ?: '—' }}
                            </p>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
        @endif
    </div>
    @endif

    {{-- ===== Questions section (only for non-rubric assignments) ===== --}}
    @if($assignment->memo_type !== 'rubric')
    <div>
        <div class="flex items-center justify-between mb-2">
            <h2 class="text-sm font-semibold text-gray-900">
                Questions
                @if($assignment->questions->isNotEmpty())
                    <span class="ml-1.5 text-xs font-normal text-gray-400">
                        {{ $assignment->questions->count() }} question{{ $assignment->questions->count() === 1 ? '' : 's' }}
                        · {{ $assignment->questions->sum('marks') }} marks total
                    </span>
                @endif
            </h2>
            <a href="{{ route('qualifications.assignments.questions.create', [$qualification, $assignment]) }}"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium bg-[#1e3a5f] text-white rounded-lg hover:bg-[#e3b64d] hover:text-[#1e3a5f] transition-colors">
                + Add Question
            </a>
        </div>

        @if($assignment->questions->isEmpty())
            <div class="bg-white rounded-xl border border-dashed border-gray-300 px-5 py-8 text-center">
                <p class="text-sm text-gray-500 mb-1">No questions added yet.</p>
                <p class="text-xs text-gray-400">Add structured questions with model answers so the AI grader has precise, per-question anchors when marking submissions.</p>
                <a href="{{ route('qualifications.assignments.questions.create', [$qualification, $assignment]) }}"
                    class="inline-flex items-center gap-1.5 mt-4 px-4 py-2 text-sm font-medium bg-[#1e3a5f] text-white rounded-lg hover:bg-[#e3b64d] hover:text-[#1e3a5f] transition-colors">
                    + Add First Question
                </a>
            </div>
        @else
            <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100" id="questions-list">
                @foreach($assignment->questions as $question)
                <div class="px-5 py-4 flex items-start gap-4" data-question-id="{{ $question->id }}">
                    <div class="mt-0.5 cursor-grab text-gray-300 hover:text-gray-400 shrink-0 drag-handle" title="Drag to reorder">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M7 2a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 2zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 8zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 14zm6-8a2 2 0 1 0-.001-4.001A2 2 0 0 0 13 6zm0 2a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 8zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 14z"/>
                        </svg>
                    </div>
                    <div class="shrink-0 w-16 text-center">
                        @if($question->label)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-orange-50 text-orange-700">{{ $question->label }}</span>
                        @endif
                        <p class="text-xs text-gray-500 mt-1 font-medium">{{ $question->marks }} mk{{ $question->marks === 1 ? '' : 's' }}</p>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-gray-800 font-medium leading-snug">{{ Str::limit($question->question_text, 150) }}</p>
                        @if($question->expected_answer)
                            <details class="mt-2">
                                <summary class="text-xs text-gray-400 cursor-pointer hover:text-gray-600 select-none">Model answer ▸</summary>
                                <pre class="mt-2 text-xs text-gray-600 whitespace-pre-wrap font-mono bg-gray-50 rounded-lg p-3 leading-relaxed">{{ $question->expected_answer }}</pre>
                            </details>
                        @endif
                        @if($question->ai_grading_notes)
                            <details class="mt-1">
                                <summary class="text-xs text-blue-400 cursor-pointer hover:text-blue-600 select-none">AI grading notes ▸</summary>
                                <p class="mt-1 text-xs text-gray-600 bg-blue-50 rounded-lg p-2 leading-relaxed">{{ $question->ai_grading_notes }}</p>
                            </details>
                        @endif
                    </div>
                    <div class="shrink-0 flex items-center gap-2">
                        <a href="{{ route('qualifications.assignments.questions.edit', [$qualification, $assignment, $question]) }}"
                            class="text-xs text-gray-500 hover:text-gray-800 px-2 py-1 rounded hover:bg-gray-100 transition-colors">Edit</a>
                        <form method="POST"
                              action="{{ route('qualifications.assignments.questions.destroy', [$qualification, $assignment, $question]) }}"
                              onsubmit="return confirm('Delete this question?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs text-red-400 hover:text-red-700 px-2 py-1 rounded hover:bg-red-50 transition-colors">Delete</button>
                        </form>
                    </div>
                </div>
                @endforeach
            </div>
            <p class="text-xs text-gray-400 mt-2 px-1">Drag rows to reorder, or set the order number when editing a question.</p>
        @endif
    </div>
    @endif

    {{-- Text memo --}}
    @if($assignment->memo_type === 'text' && $assignment->memo_text)
    <div>
        <h2 class="text-sm font-semibold text-gray-900 mb-2">Assignment-level Marking Memo</h2>
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <pre class="text-xs text-gray-700 whitespace-pre-wrap font-mono leading-relaxed">{{ $assignment->memo_text }}</pre>
        </div>
    </div>
    @endif

</div>

<script>
(function () {
    const list = document.getElementById('questions-list');
    if (!list) return;

    const reorderUrl = @json(route('qualifications.assignments.questions.reorder', [$qualification, $assignment]));
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    let dragging = null;

    list.querySelectorAll('.drag-handle').forEach(handle => {
        const row = handle.closest('[data-question-id]');
        row.draggable = true;

        row.addEventListener('dragstart', e => {
            dragging = row;
            e.dataTransfer.effectAllowed = 'move';
            setTimeout(() => row.classList.add('opacity-40'), 0);
        });

        row.addEventListener('dragend', () => {
            dragging = null;
            row.classList.remove('opacity-40');
            sendReorder();
        });
    });

    list.addEventListener('dragover', e => {
        e.preventDefault();
        const target = e.target.closest('[data-question-id]');
        if (!target || target === dragging) return;
        const rect = target.getBoundingClientRect();
        const insertBefore = e.clientY < rect.top + rect.height / 2;
        list.insertBefore(dragging, insertBefore ? target : target.nextSibling);
    });

    function sendReorder() {
        const ids = [...list.querySelectorAll('[data-question-id]')].map(r => r.dataset.questionId);
        fetch(reorderUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({ order: ids }),
        });
    }
})();
</script>
@endsection
