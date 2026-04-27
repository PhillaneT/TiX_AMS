@extends('layouts.app')

@section('title', 'New Qualification — AjanaNova AMS')
@section('heading', 'New Qualification')
@section('breadcrumb', 'Qualifications → New')

@section('content')
<div class="max-w-2xl mt-2 space-y-4">

    {{-- SAQA Fetch Panel --}}
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-5">
        <h3 class="text-sm font-semibold text-blue-800 mb-1">Auto-fill from SAQA</h3>
        <p class="text-xs text-blue-600 mb-3">Enter a SAQA qualification ID and click Fetch — this pre-fills the form and imports all modules automatically when you save.</p>
        <div class="flex gap-2">
            <input type="text" id="saqa_lookup_input" placeholder="e.g. 48573"
                class="flex-1 rounded-lg border border-blue-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
                maxlength="20">
            <button type="button" id="saqa_fetch_btn"
                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors flex items-center gap-2">
                <svg id="saqa_spinner" class="hidden animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                </svg>
                <span id="saqa_fetch_label">Fetch from SAQA</span>
            </button>
        </div>
        <p id="saqa_error" class="hidden mt-2 text-xs text-red-600 font-medium"></p>

        {{-- Module preview shown after successful fetch --}}
        <div id="saqa_preview" class="hidden mt-4 border-t border-blue-200 pt-3">
            <p class="text-xs font-semibold text-blue-800 mb-2" id="saqa_preview_heading"></p>
            <div id="saqa_module_list" class="space-y-1 max-h-48 overflow-y-auto text-xs text-blue-700"></div>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <form method="POST" action="{{ route('qualifications.store') }}" class="space-y-5">
            @csrf
            <input type="hidden" name="_fetched_modules" id="fetched_modules_input">

            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Qualification name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" id="field_name" value="{{ old('name') }}" required
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500"
                        placeholder="e.g. National Certificate: IT: Systems Support">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">SAQA ID</label>
                    <input type="text" name="saqa_id" id="field_saqa_id" value="{{ old('saqa_id') }}"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500"
                        placeholder="e.g. 48573">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">NQF Level <span class="text-red-500">*</span></label>
                    <select name="nqf_level" id="field_nqf_level" required
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                        @for($i = 1; $i <= 10; $i++)
                            <option value="{{ $i }}" {{ old('nqf_level', 4) == $i ? 'selected' : '' }}>Level {{ $i }}</option>
                        @endfor
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Track <span class="text-red-500">*</span></label>
                    <select name="track" id="field_track" required
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                        <option value="qcto_occupational" {{ old('track', 'qcto_occupational') === 'qcto_occupational' ? 'selected' : '' }}>QCTO Occupational</option>
                        <option value="legacy_seta" {{ old('track') === 'legacy_seta' ? 'selected' : '' }}>Legacy SETA</option>
                    </select>
                    <p class="text-xs text-gray-400 mt-1">Legacy SETA programmes enrolled before 30 June 2024.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Credits</label>
                    <input type="number" name="credits" id="field_credits" value="{{ old('credits') }}" min="1"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500"
                        placeholder="e.g. 120">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">SETA <span class="text-red-500">*</span></label>
                    <select name="seta" id="field_seta" required
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                        @foreach(['MICT', 'ETDP', 'SERVICES', 'BANKSETA', 'CATHSSETA', 'CETA', 'CHIETA', 'EWSETA', 'FASSET', 'FIETA', 'FoodBev', 'HWSETA', 'INSETA', 'LGSETA', 'MAPPP', 'MQA', 'MERSETA', 'POSHEITA', 'PSETA', 'RCL', 'SASSETA', 'TETA', 'W&RSETA'] as $s)
                            <option value="{{ $s }}" {{ old('seta', 'MICT') === $s ? 'selected' : '' }}>{{ $s }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">SETA Registration Number</label>
                    <input type="text" name="seta_registration_number" value="{{ old('seta_registration_number') }}"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500"
                        placeholder="Your SDP accreditation / registration number">
                </div>

                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Notes</label>
                    <textarea name="notes" rows="3"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500"
                        placeholder="Any internal notes about this qualification...">{{ old('notes') }}</textarea>
                </div>
            </div>

            <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
                <button type="submit"
                    class="px-5 py-2.5 hover:bg-orange-700 bg-[#e3b64d] text-white text-sm font-medium rounded-lg transition-colors">
                    Create Qualification
                </button>
                <a href="{{ route('qualifications.index') }}" class="px-5 py-2.5 text-sm text-gray-600 hover:text-gray-900">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const btn        = document.getElementById('saqa_fetch_btn');
    const input      = document.getElementById('saqa_lookup_input');
    const spinner    = document.getElementById('saqa_spinner');
    const fetchLabel = document.getElementById('saqa_fetch_label');
    const errEl      = document.getElementById('saqa_error');
    const preview    = document.getElementById('saqa_preview');
    const heading    = document.getElementById('saqa_preview_heading');
    const modList    = document.getElementById('saqa_module_list');
    const hiddenMod  = document.getElementById('fetched_modules_input');

    const typeColors = {
        KM:  'bg-purple-100 text-purple-700',
        PM:  'bg-blue-100 text-blue-700',
        WM:  'bg-green-100 text-green-700',
        US:  'bg-orange-100 text-orange-700',
        MOD: 'bg-gray-100 text-gray-700'
    };

    btn.addEventListener('click', async function () {
        const saqaId = input.value.trim();
        if (!saqaId || !/^\d+$/.test(saqaId)) {
            showError('Please enter a numeric SAQA ID (numbers only).');
            return;
        }

        setLoading(true);
        errEl.classList.add('hidden');
        preview.classList.add('hidden');
        hiddenMod.value = '';

        try {
            const res  = await fetch('/api/saqa-lookup?saqa_id=' + encodeURIComponent(saqaId), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });
            const json = await res.json();

            if (!json.ok) {
                showError(json.error || 'SAQA fetch failed. Check the ID and try again.');
                return;
            }

            const d       = json.data;
            const modules = d.modules || [];

            // Pre-fill form fields
            document.getElementById('field_name').value    = d.title || '';
            document.getElementById('field_saqa_id').value = saqaId;

            if (d.nqf_level) {
                const lvl = parseInt(String(d.nqf_level).replace(/\D/g, ''));
                if (lvl >= 1 && lvl <= 10) {
                    document.getElementById('field_nqf_level').value = lvl;
                }
            }
            if (d.credits) {
                const cr = parseInt(d.credits);
                if (cr > 0) document.getElementById('field_credits').value = cr;
            }

            // Guess track from module types
            const hasQcto = modules.some(m => ['KM','PM','WM'].includes(m.module_type));
            const hasUs   = modules.some(m => m.module_type === 'US');
            document.getElementById('field_track').value =
                (hasUs && !hasQcto) ? 'legacy_seta' : 'qcto_occupational';

            // Store fetched modules in hidden field
            hiddenMod.value = JSON.stringify(modules);

            // Show module preview
            if (modules.length > 0) {
                heading.textContent = modules.length + ' module(s) will be imported:';
                modList.innerHTML = modules.map(function (m) {
                    const badge = typeColors[m.module_type] || typeColors['MOD'];
                    return '<div class="flex items-start gap-2 py-0.5">'
                        + '<span class="inline-flex px-1.5 py-0.5 rounded text-xs font-bold shrink-0 ' + badge + '">' + esc(m.module_type) + '</span>'
                        + '<span class="leading-tight">' + esc(m.module_code) + ' — ' + esc(m.title) + '</span>'
                        + '</div>';
                }).join('');
                preview.classList.remove('hidden');
            } else {
                heading.textContent = 'No modules found — you can add them manually after saving.';
                modList.innerHTML = '';
                preview.classList.remove('hidden');
            }

        } catch (e) {
            showError('Network error — please check your connection and try again.');
        } finally {
            setLoading(false);
        }
    });

    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); btn.click(); }
    });

    function setLoading(on) {
        btn.disabled = on;
        spinner.classList.toggle('hidden', !on);
        fetchLabel.textContent = on ? 'Fetching…' : 'Fetch from SAQA';
    }

    function showError(msg) {
        errEl.textContent = msg;
        errEl.classList.remove('hidden');
    }

    function esc(str) {
        return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }
})();
</script>
@endsection
