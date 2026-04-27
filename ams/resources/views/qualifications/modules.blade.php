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

{{-- Pass data to JavaScript --}}
<script>
const AMS_ASSIGNMENTS = @json($assignments->map(fn($a) => [
    'id'    => $a->id,
    'label' => $a->name . ' (' . ucfirst($a->type) . ')',
])->values());

const AMS_MAPPING = @json($mapping);
</script>

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
                        class="w-full hover:bg-orange-700 bg-[#e3b64d] text-white text-sm font-semibold py-2 rounded-lg transition">
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
            <form method="POST" action="{{ route('qualifications.modules.save-mapping', $qualification) }}" id="mapping-form">
                @csrf
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-3 bg-gray-50 border-b border-gray-200">
                        <h2 class="font-semibold text-gray-800 text-sm">
                            {{ $modules->count() }} Module(s) — Module to Activity Mapping
                        </h2>
                        <button type="submit"
                                class="hover:bg-orange-700 bg-[#e3b64d] text-white text-xs font-semibold px-4 py-1.5 rounded-lg transition">
                            Save Mappings
                        </button>
                    </div>

                    @if($assignments->isEmpty())
                        <div class="px-5 py-4 text-xs text-amber-700 bg-amber-50 border-b border-amber-100">
                            No assignments yet. <a href="{{ route('qualifications.assignments.create', $qualification) }}" class="underline font-semibold">Create assignments first</a>, then map them here.
                        </div>
                    @endif

                    {{-- Table header --}}
                    <div class="grid grid-cols-12 px-5 py-2 bg-gray-50 border-b border-gray-100 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                        <div class="col-span-1">Code</div>
                        <div class="col-span-1">Type</div>
                        <div class="col-span-4">Module Title</div>
                        <div class="col-span-1 text-center">NQF</div>
                        <div class="col-span-1 text-center">Credits</div>
                        <div class="col-span-4">Mapped Activity</div>
                    </div>

                    <div class="divide-y divide-gray-100" id="module-rows">
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
                        <div class="grid grid-cols-12 px-5 py-3 items-start gap-y-1"
                             data-mod-id="{{ $mod->id }}"
                             data-mapped='@json($mapped)'>
                            {{-- Code --}}
                            <div class="col-span-1 pt-0.5">
                                <code class="text-xs text-red-500 break-all leading-tight">{{ $mod->module_code }}</code>
                            </div>
                            {{-- Type badge --}}
                            <div class="col-span-1 pt-0.5">
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-bold {{ $badgeCls }}">
                                    {{ strtoupper($mod->module_type) }}
                                </span>
                            </div>
                            {{-- Title --}}
                            <div class="col-span-4 pt-0.5 pr-2">
                                <div class="text-sm font-medium text-gray-800 leading-tight">{{ $mod->title }}</div>
                            </div>
                            {{-- NQF --}}
                            <div class="col-span-1 pt-0.5 text-center text-sm text-gray-600">
                                {{ $mod->nqf_level ?? '—' }}
                            </div>
                            {{-- Credits --}}
                            <div class="col-span-1 pt-0.5 text-center text-sm text-gray-600">
                                {{ $mod->credits ?: '—' }}
                            </div>
                            {{-- Mapping area --}}
                            <div class="col-span-4">
                                <div class="space-y-1.5 map-rows" id="rows-{{ $mod->id }}">
                                    {{-- Rows injected by JS on load --}}
                                </div>
                                <button type="button"
                                        onclick="addRow({{ $mod->id }})"
                                        class="mt-1.5 text-xs text-blue-600 hover:text-blue-800 font-semibold flex items-center gap-1 add-btn"
                                        id="add-{{ $mod->id }}">
                                    + Add activity
                                </button>
                            </div>
                        </div>
                        @endforeach
                    </div>

                    @if($modules->count() > 3)
                    <div class="px-5 py-3 bg-gray-50 border-t border-gray-200 flex justify-end">
                        <button type="submit"
                                class="hover:bg-orange-700 bg-[#e3b64d] text-white text-xs font-semibold px-4 py-1.5 rounded-lg transition">
                            Save Mappings
                        </button>
                    </div>
                    @endif
                </div>
            </form>
        @endif
    </div>
</div>

<script>
(function () {
    // -------------------------------------------------------
    // GLOBAL state: assignmentId (string) → owning modId (int)
    // An assignment can only be owned by ONE module at a time.
    // -------------------------------------------------------
    const globalUsed = new Map(); // string assignmentId → int modId

    // Collect all current selections across every module and rebuild globalUsed
    function syncGlobal() {
        globalUsed.clear();
        document.querySelectorAll('[data-mod-id]').forEach(function (modEl) {
            const modId = parseInt(modEl.dataset.modId);
            modEl.querySelectorAll('.map-row select').forEach(function (sel) {
                if (sel.value) globalUsed.set(String(sel.value), modId);
            });
        });
    }

    // -------------------------------------------------------
    // Build a <select> for (modId, currentVal).
    // Only shows assignments that are:
    //   • not globally used at all, OR
    //   • currently selected by THIS exact row (currentVal owned by this modId)
    // -------------------------------------------------------
    function buildSelect(modId, currentVal) {
        const sel = document.createElement('select');
        sel.className = 'flex-1 rounded border border-gray-300 text-xs px-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-orange-400 bg-white min-w-0';
        sel.name = 'mapping[' + modId + '][]';

        const blank = document.createElement('option');
        blank.value = '';
        blank.textContent = '— select assignment —';
        if (!currentVal) blank.selected = true;
        sel.appendChild(blank);

        AMS_ASSIGNMENTS.forEach(function (a) {
            const id   = String(a.id);
            const owner = globalUsed.get(id);  // modId that owns this assignment, or undefined

            // Show this option only if it is free, OR it is this row's current value
            const isMine = (id === String(currentVal));
            const isFree = (owner === undefined);

            if (!isFree && !isMine) return; // hide completely from this dropdown

            const opt = document.createElement('option');
            opt.value = id;
            opt.textContent = a.label;
            if (isMine) opt.selected = true;
            sel.appendChild(opt);
        });

        sel.addEventListener('change', function () {
            syncGlobal();
            rebuildAll();
        });

        return sel;
    }

    // -------------------------------------------------------
    // Rebuild all dropdowns in a single module to reflect
    // the current global state.
    // -------------------------------------------------------
    function rebuildModule(modId) {
        const container = document.getElementById('rows-' + modId);
        if (!container) return;

        container.querySelectorAll('.map-row').forEach(function (row) {
            const oldSel = row.querySelector('select');
            if (!oldSel) return;
            const currentVal = oldSel.value;
            const newSel = buildSelect(modId, currentVal);
            row.replaceChild(newSel, oldSel);
        });

        updateAddButton(modId);
    }

    // Rebuild ALL modules — called after any selection changes
    function rebuildAll() {
        document.querySelectorAll('[data-mod-id]').forEach(function (modEl) {
            rebuildModule(parseInt(modEl.dataset.modId));
        });
    }

    // -------------------------------------------------------
    // Hide "+ Add activity" when no free assignments remain globally.
    // -------------------------------------------------------
    function updateAddButton(modId) {
        const btn = document.getElementById('add-' + modId);
        if (!btn) return;
        const freeCount = AMS_ASSIGNMENTS.filter(function (a) {
            const owner = globalUsed.get(String(a.id));
            return owner === undefined; // only truly free ones count
        }).length;
        btn.style.display = (AMS_ASSIGNMENTS.length === 0 || freeCount === 0) ? 'none' : '';
    }

    // -------------------------------------------------------
    // Create a row element (select + × button) and append it.
    // -------------------------------------------------------
    function makeRow(modId, assignmentId) {
        const row = document.createElement('div');
        row.className = 'map-row flex items-center gap-1.5';

        const sel = buildSelect(modId, assignmentId ? String(assignmentId) : '');
        row.appendChild(sel);

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'text-gray-400 hover:text-red-500 shrink-0 flex items-center justify-center w-5 h-5 rounded transition-colors';
        removeBtn.title = 'Remove';
        removeBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>';
        removeBtn.addEventListener('click', function () {
            row.remove();
            syncGlobal();
            rebuildAll();
        });
        row.appendChild(removeBtn);
        return row;
    }

    // -------------------------------------------------------
    // Public: called by the "+ Add activity" button.
    // -------------------------------------------------------
    window.addRow = function (modId) {
        const container = document.getElementById('rows-' + modId);
        if (!container) return;
        container.appendChild(makeRow(modId, ''));
        updateAddButton(modId);
    };

    // -------------------------------------------------------
    // Initialise on DOMContentLoaded.
    // -------------------------------------------------------
    function init() {
        // First pass: seed globalUsed from server-side mapping data
        document.querySelectorAll('[data-mod-id]').forEach(function (modEl) {
            const modId  = parseInt(modEl.dataset.modId);
            const mapped = JSON.parse(modEl.dataset.mapped || '[]');
            mapped.forEach(function (id) {
                globalUsed.set(String(id), modId);
            });
        });

        // Second pass: render rows (globalUsed is now fully seeded)
        document.querySelectorAll('[data-mod-id]').forEach(function (modEl) {
            const modId  = parseInt(modEl.dataset.modId);
            const mapped = JSON.parse(modEl.dataset.mapped || '[]');
            const container = document.getElementById('rows-' + modId);
            if (!container) return;

            if (mapped.length === 0) {
                if (AMS_ASSIGNMENTS.length > 0) {
                    container.appendChild(makeRow(modId, ''));
                }
            } else {
                mapped.forEach(function (id) {
                    container.appendChild(makeRow(modId, id));
                });
            }
            updateAddButton(modId);
        });
    }

    document.addEventListener('DOMContentLoaded', init);
})();
</script>

@endsection
