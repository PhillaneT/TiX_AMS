@extends('layouts.app')

@section('title', 'Assignments — ' . $qualification->name)
@section('heading', 'Assignments')
@section('breadcrumb', $qualification->name . ' → Assignments')

@section('page-actions')
    <a href="{{ route('qualifications.show', $qualification) }}"
        class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
        ← Qualification
    </a>
    <a href="{{ route('qualifications.assignments.create', $qualification) }}"
        class="inline-flex items-center gap-2 px-4 py-2 hover:bg-orange-700 text-white text-sm font-medium rounded-lg transition-colors bg-[#1e3a5f]">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        New Assignment
    </a>
@endsection

@section('content')
<div class="mt-2 space-y-4">

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 text-sm rounded-lg px-4 py-3">{{ session('success') }}</div>
    @endif

    {{-- Context strip --}}
    <div class="bg-white rounded-xl border border-gray-200 px-5 py-3 flex items-center gap-4 text-sm text-gray-500">
        <span class="font-medium text-gray-700">{{ $qualification->name }}</span>
        <span>·</span>
        <span>{{ $qualification->seta }} · NQF {{ $qualification->nqf_level }}</span>
        <span class="ml-auto">
            <a href="{{ route('qualifications.modules.index', $qualification) }}"
               class="text-blue-600 hover:underline text-xs">Map to modules →</a>
        </span>
    </div>

    @if($assignments->isEmpty())
        <div class="bg-white rounded-xl border border-gray-200 p-10 text-center">
            <svg class="w-10 h-10 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
            </svg>
            <p class="text-sm text-gray-400 mb-4">No assignments yet for this qualification.</p>
            <a href="{{ route('qualifications.assignments.create', $qualification) }}"
                class="inline-flex items-center gap-2 px-4 py-2 hover:bg-orange-700 bg-[#e3b64d] text-white text-sm font-medium rounded-lg">
                Create your first assignment
            </a>
        </div>
    @else
        {{-- Group by type --}}
        @foreach(['formative' => 'Formative Assignments', 'summative' => 'Summative Assignments'] as $type => $label)
            @php $group = $assignments->where('type', $type); @endphp
            @if($group->isNotEmpty())
            <div>
                <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2 px-1">{{ $label }}</h2>
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-100 text-left">
                                <th class="px-5 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wide">Assignment</th>
                                <th class="px-5 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wide">Marks</th>
                                <th class="px-5 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wide">Memo</th>
                                <th class="px-5 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wide">Mapped Modules</th>
                                <th class="px-5 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($group as $asgn)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-5 py-3">
                                    <a href="{{ route('qualifications.assignments.show', [$qualification, $asgn]) }}"
                                        class="font-medium text-gray-900 hover:text-orange-600">
                                        {{ $asgn->name }}
                                    </a>
                                    @if($asgn->description)
                                        <p class="text-xs text-gray-400 mt-0.5 truncate max-w-xs">{{ $asgn->description }}</p>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-gray-600">{{ $asgn->total_marks ?? '—' }}</td>
                                <td class="px-5 py-3">
                                    @if($asgn->memo_type === 'pdf' && $asgn->memo_path)
                                        <a href="{{ route('qualifications.assignments.memo', [$qualification, $asgn]) }}"
                                            class="inline-flex items-center gap-1 text-xs text-red-600 hover:text-red-800">
                                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/></svg>
                                            PDF Memo
                                        </a>
                                    @elseif($asgn->memo_type === 'text' && $asgn->memo_text)
                                        <span class="text-xs text-gray-400">Text memo</span>
                                    @else
                                        <span class="text-xs text-gray-300">None</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    @if($asgn->qualification_modules_count > 0)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700">
                                            {{ $asgn->qualification_modules_count }} module(s)
                                        </span>
                                    @else
                                        <span class="text-xs text-amber-500">Not mapped</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right space-x-3">
                                    <a href="{{ route('qualifications.assignments.edit', [$qualification, $asgn]) }}"
                                        class="text-xs text-gray-400 hover:text-gray-700">Edit</a>
                                    <form method="POST"
                                          action="{{ route('qualifications.assignments.destroy', [$qualification, $asgn]) }}"
                                          class="inline"
                                          onsubmit="return confirm('Delete this assignment?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-xs text-red-400 hover:text-red-600">Delete</button>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
        @endforeach
    @endif

    {{-- Tip --}}
    <div class="bg-blue-50 border border-blue-100 rounded-xl px-5 py-3 text-xs text-blue-700">
        <strong>Next step:</strong> Once you've added all assignments, go to
        <a href="{{ route('qualifications.modules.index', $qualification) }}" class="underline font-semibold">Modules</a>
        to map each assignment to the qualification module(s) it covers. This drives the per-learner POE tracking.
    </div>
</div>
@endsection
