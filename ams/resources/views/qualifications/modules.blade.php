@extends('layouts.app')

@section('title', 'Qualification Modules — ' . $qualification->name)

@section('content')
<div class="mb-6 flex items-center gap-3">
    <a href="{{ route('qualifications.show', $qualification) }}"
       class="text-sm text-gray-500 hover:text-gray-700">&#8592; {{ $qualification->name }}</a>
    <span class="text-gray-300">/</span>
    <h1 class="text-xl font-bold text-gray-800">Qualification Modules</h1>
</div>

@if(session('success'))
    <div class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-green-800 text-sm">
        {{ session('success') }}
    </div>
@endif

@if($errors->any())
    <div class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-red-800 text-sm">
        {{ $errors->first() }}
    </div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Left: SAQA Fetch + Manual Add --}}
    <div class="lg:col-span-1 space-y-6">

        {{-- SAQA Fetch --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <h2 class="font-semibold text-gray-800 mb-1">Fetch from SAQA</h2>
            <p class="text-xs text-gray-500 mb-4">Pulls all KM/PM/WM or Unit Standards from the SAQA register and replaces the current module list.</p>
            <form method="POST" action="{{ route('qualifications.modules.fetch-saqa', $qualification) }}">
                @csrf
                <label class="block text-xs font-medium text-gray-600 mb-1">SAQA Qualification ID</label>
                <input type="text" name="saqa_id"
                       value="{{ old('saqa_id', $qualification->saqa_id) }}"
                       placeholder="e.g. 118708"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mb-3 focus:outline-none focus:ring-2 focus:ring-orange-400">
                <button type="submit"
                        class="w-full bg-orange-600 hover:bg-orange-700 text-white text-sm font-semibold py-2 rounded-lg transition">
                    Fetch from SAQA
                </button>
            </form>
            @if($qualification->saqa_fetched_at)
                <p class="mt-2 text-xs text-gray-400">Last fetched: {{ $qualification->saqa_fetched_at->diffForHumans() }}</p>
            @endif
        </div>

        {{-- Manual add module --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <h2 class="font-semibold text-gray-800 mb-1">Add Module Manually</h2>
            <p class="text-xs text-gray-500 mb-4">For qualifications not on SAQA, or to add extra modules.</p>
            <form method="POST" action="{{ route('qualifications.modules.add', $qualification) }}">
                @csrf
                <div class="space-y-2">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Type</label>
                        <select name="module_type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
                            <option value="KM">KM — Knowledge Module</option>
                            <option value="PM">PM — Practical Module</option>
                            <option value="WM">WM — Workplace Module</option>
                            <option value="US">US — Unit Standard</option>
                            <option value="MOD">MOD — Generic Module</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Module Code</label>
                        <input type="text" name="module_code" placeholder="e.g. 251102-001-00-KM-01"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Title</label>
                        <input type="text" name="title" placeholder="Module title"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">NQF Level</label>
                            <input type="text" name="nqf_level" placeholder="4"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Credits</label>
                            <input type="number" name="credits" placeholder="10" min="0"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
                        </div>
                    </div>
                    <button type="submit"
                            class="w-full bg-gray-700 hover:bg-gray-800 text-white text-sm font-semibold py-2 rounded-lg transition mt-1">
                        Add Module
                    </button>
                </div>
            </form>
        </div>

        {{-- Module summary --}}
        @if($modules->count())
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm text-sm">
            <h2 class="font-semibold text-gray-800 mb-3">Summary</h2>
            @php
                $byType = $modules->groupBy('module_type');
                $totalCredits = $modules->sum('credits');
            @endphp
            @foreach($byType as $type => $mods)
            <div class="flex justify-between py-1 border-b border-gray-50 last:border-0">
                <span class="font-medium text-gray-600">{{ $type }} ({{ $mods->count() }})</span>
                <span class="text-gray-500">{{ $mods->sum('credits') }} credits</span>
            </div>
            @endforeach
            <div class="flex justify-between pt-2 font-semibold text-gray-800">
                <span>Total ({{ $modules->count() }})</span>
                <span>{{ $totalCredits }} credits</span>
            </div>
        </div>
        @endif

    </div>

    {{-- Right: Module list + assignment mapping --}}
    <div class="lg:col-span-2">
        @if($modules->isEmpty())
            <div class="bg-white rounded-xl border border-dashed border-gray-300 p-12 text-center">
                <div class="text-3xl mb-3">📋</div>
                <p class="text-gray-500 text-sm">No modules yet. Fetch from SAQA or add manually.</p>
            </div>
        @else
            <form method="POST" action="{{ route('qualifications.modules.save-mapping', $qualification) }}">
                @csrf
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-3 bg-gray-50 border-b border-gray-200">
                        <h2 class="font-semibold text-gray-800 text-sm">
                            {{ $modules->count() }} Module(s) — Map to Assignments
                        </h2>
                        <button type="submit"
                                class="bg-orange-600 hover:bg-orange-700 text-white text-xs font-semibold px-4 py-1.5 rounded-lg transition">
                            Save Mappings
                        </button>
                    </div>

                    @if($assignments->isEmpty())
                        <div class="px-5 py-4 text-xs text-amber-700 bg-amber-50 border-b border-amber-100">
                            No assignments exist for this qualification yet. Create assignments first, then map them here.
                        </div>
                    @endif

                    <div class="divide-y divide-gray-100">
                        @foreach($modules as $mod)
                        @php
                            $colors = [
                                'KM'  => 'bg-blue-100 text-blue-800',
                                'PM'  => 'bg-green-100 text-green-800',
                                'WM'  => 'bg-orange-100 text-orange-800',
                                'US'  => 'bg-purple-100 text-purple-800',
                                'MOD' => 'bg-gray-100 text-gray-700',
                            ];
                            $badgeCls = $colors[strtoupper($mod->module_type)] ?? 'bg-gray-100 text-gray-700';
                            $mapped = $mapping[$mod->id] ?? [];
                        @endphp
                        <div class="px-5 py-4">
                            <div class="flex items-start gap-3 mb-2">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold {{ $badgeCls }} shrink-0 mt-0.5">
                                    {{ strtoupper($mod->module_type) }}
                                </span>
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-medium text-gray-800 leading-tight">{{ $mod->title }}</div>
                                    <div class="text-xs text-gray-400 mt-0.5">
                                        <code>{{ $mod->module_code }}</code>
                                        @if($mod->nqf_level) &bull; NQF {{ $mod->nqf_level }} @endif
                                        @if($mod->credits) &bull; {{ $mod->credits }} credits @endif
                                    </div>
                                </div>
                                <form method="POST"
                                      action="{{ route('qualifications.modules.destroy', [$qualification, $mod]) }}"
                                      onsubmit="return confirm('Remove this module?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-xs text-red-400 hover:text-red-600 shrink-0">Remove</button>
                                </form>
                            </div>

                            @if($assignments->isNotEmpty())
                            <div class="ml-10">
                                <label class="block text-xs font-medium text-gray-500 mb-1">Assessed by assignment(s):</label>
                                <div class="space-y-1" id="map-{{ $mod->id }}">
                                    @foreach($assignments as $asgn)
                                    <label class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer">
                                        <input type="checkbox"
                                               name="mapping[{{ $mod->id }}][]"
                                               value="{{ $asgn->id }}"
                                               {{ in_array($asgn->id, $mapped) ? 'checked' : '' }}
                                               class="rounded border-gray-300 text-orange-600 focus:ring-orange-400">
                                        {{ $asgn->name }}
                                        <span class="text-gray-400">({{ ucfirst($asgn->type) }})</span>
                                    </label>
                                    @endforeach
                                </div>
                            </div>
                            @endif
                        </div>
                        @endforeach
                    </div>

                    @if($modules->count() > 3)
                    <div class="px-5 py-3 bg-gray-50 border-t border-gray-200 flex justify-end">
                        <button type="submit"
                                class="bg-orange-600 hover:bg-orange-700 text-white text-xs font-semibold px-4 py-1.5 rounded-lg transition">
                            Save Mappings
                        </button>
                    </div>
                    @endif
                </div>
            </form>
        @endif
    </div>
</div>
@endsection
