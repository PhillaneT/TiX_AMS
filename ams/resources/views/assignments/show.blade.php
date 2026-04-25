@extends('layouts.app')

@section('title', $assignment->name . ' — ' . $qualification->name)
@section('heading', $assignment->name)
@section('breadcrumb', $qualification->name . ' → Assignments → ' . $assignment->name)

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
                <p class="text-sm font-semibold text-gray-800 mt-1">{{ $assignment->total_marks ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium">Memo</p>
                <p class="text-sm font-semibold text-gray-800 mt-1">
                    @if($assignment->memo_type === 'pdf' && $assignment->memo_path)
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

    {{-- Text memo --}}
    @if($assignment->memo_type === 'text' && $assignment->memo_text)
    <div>
        <h2 class="text-sm font-semibold text-gray-900 mb-2">Marking Memo</h2>
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <pre class="text-xs text-gray-700 whitespace-pre-wrap font-mono leading-relaxed">{{ $assignment->memo_text }}</pre>
        </div>
    </div>
    @endif

</div>
@endsection
