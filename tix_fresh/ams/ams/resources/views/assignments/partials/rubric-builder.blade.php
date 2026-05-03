{{--
    Rubric Builder partial
    Variables:
      $existingRubric  — array|null  (the rubric_json value from an existing assignment, or null)
      $importUrl       — string|null (the route for Moodle import AJAX, or null to hide the button)
--}}

<div id="rubric-builder">

    {{-- Header + action bar --}}
    <div class="flex items-center justify-between mb-3">
        <div>
            <p class="text-xs text-gray-500">
                Define the criteria and performance levels. Each level has a score — the AI will map
                learner work to the most appropriate level per criterion.
            </p>
        </div>
        @if(!empty($importUrl))
        <button type="button" id="btn-import-moodle"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium border border-blue-300 text-blue-700 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors shrink-0 ml-4">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            Import from Moodle
        </button>
        @endif
    </div>

    {{-- Import status message --}}
    @if(!empty($importUrl))
    <div id="import-msg" class="hidden mb-3 text-xs rounded-lg px-3 py-2 border"></div>
    @endif

    {{-- Criteria list --}}
    <div id="criteria-list" class="space-y-3">
        {{-- Criteria injected by JS --}}
    </div>

    {{-- Empty state --}}
    <div id="criteria-empty" class="hidden text-center py-8 border-2 border-dashed border-gray-200 rounded-xl">
        <p class="text-sm text-gray-400 mb-1">No criteria yet.</p>
        <p class="text-xs text-gray-300">Click "Add Criterion" to start building your rubric.</p>
    </div>

    {{-- Add criterion button --}}
    <button type="button" id="btn-add-criterion"
        class="mt-3 inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium border border-gray-300 text-gray-700 bg-white hover:bg-gray-50 rounded-lg transition-colors">
        + Add Criterion
    </button>

    {{-- Hidden serialised value submitted with the form --}}
    <input type="hidden" name="rubric_json" id="rubric_json_input">

    {{-- Total marks display --}}
    <p class="text-xs text-gray-400 mt-2" id="rubric-totals"></p>

</div>

<script>
(function () {

    // -------------------------------------------------------
    // Seed data from server (PHP → JS)
    // -------------------------------------------------------
    const seedRubric = @json($existingRubric ?? null);

    // -------------------------------------------------------
    // State
    // -------------------------------------------------------
    let criteria = [];

    function uid() {
        return 'c_' + Math.random().toString(36).slice(2, 9);
    }
    function luid() {
        return 'l_' + Math.random().toString(36).slice(2, 9);
    }

    function emptyLevel() {
        return { id: luid(), score: 0, description: '' };
    }

    function emptycriterion() {
        return {
            id:          uid(),
            title:       '',
            description: '',
            levels:      [
                { id: luid(), score: 0,  description: '' },
                { id: luid(), score: 5,  description: '' },
                { id: luid(), score: 10, description: '' },
            ],
        };
    }

    // -------------------------------------------------------
    // Render
    // -------------------------------------------------------
    function render() {
        const list = document.getElementById('criteria-list');
        const empty = document.getElementById('criteria-empty');
        list.innerHTML = '';

        if (criteria.length === 0) {
            empty.classList.remove('hidden');
            updateTotals();
            serialise();
            return;
        }
        empty.classList.add('hidden');

        criteria.forEach((crit, ci) => {
            const maxScore = crit.levels.reduce((m, l) => Math.max(m, parseFloat(l.score) || 0), 0);

            const card = document.createElement('div');
            card.className = 'rounded-xl border border-gray-200 bg-gray-50 p-4';
            card.dataset.critId = crit.id;

            // ---- Criterion header ----
            const header = document.createElement('div');
            header.className = 'flex items-center gap-2 mb-3';

            const numBadge = document.createElement('span');
            numBadge.className = 'shrink-0 w-6 h-6 flex items-center justify-center rounded-full bg-[#1e3a5f] text-white text-xs font-bold';
            numBadge.textContent = ci + 1;

            const titleInput = document.createElement('input');
            titleInput.type = 'text';
            titleInput.className = 'flex-1 rounded-lg border border-gray-300 px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 bg-white';
            titleInput.placeholder = 'Criterion title, e.g. Content Quality';
            titleInput.value = crit.title;
            titleInput.addEventListener('input', e => { crit.title = e.target.value; serialise(); });

            const maxBadge = document.createElement('span');
            maxBadge.className = 'shrink-0 text-xs text-gray-500 font-medium whitespace-nowrap max-badge';
            maxBadge.textContent = maxScore + ' pts';

            const delBtn = document.createElement('button');
            delBtn.type = 'button';
            delBtn.className = 'shrink-0 text-gray-300 hover:text-red-500 transition-colors ml-1';
            delBtn.title = 'Remove criterion';
            delBtn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>';
            delBtn.addEventListener('click', () => {
                if (!confirm('Remove this criterion?')) return;
                criteria.splice(ci, 1);
                render();
            });

            header.append(numBadge, titleInput, maxBadge, delBtn);

            // ---- Description (collapsible) ----
            const descToggle = document.createElement('button');
            descToggle.type = 'button';
            descToggle.className = 'text-xs text-gray-400 hover:text-gray-600 mb-2 flex items-center gap-1';
            descToggle.innerHTML = (crit.description
                ? '<svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>'
                : '<svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>')
                + '<span>' + (crit.description ? 'Description' : 'Add description') + '</span>';

            const descWrap = document.createElement('div');
            descWrap.className = crit.description ? 'mb-2' : 'hidden mb-2';

            const descInput = document.createElement('textarea');
            descInput.className = 'w-full rounded-lg border border-gray-300 px-2.5 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-orange-500 bg-white';
            descInput.placeholder = 'Optional: describe what this criterion measures...';
            descInput.rows = 2;
            descInput.value = crit.description;
            descInput.addEventListener('input', e => { crit.description = e.target.value; serialise(); });
            descWrap.appendChild(descInput);

            descToggle.addEventListener('click', () => {
                descWrap.classList.toggle('hidden');
            });

            // ---- Levels table ----
            const levelsWrap = document.createElement('div');
            levelsWrap.className = 'space-y-1.5';

            const levelsLabel = document.createElement('p');
            levelsLabel.className = 'text-xs font-medium text-gray-600 mb-1.5';
            levelsLabel.textContent = 'Performance levels';
            levelsWrap.appendChild(levelsLabel);

            crit.levels.forEach((lev, li) => {
                const row = document.createElement('div');
                row.className = 'flex items-start gap-2';

                const scoreWrap = document.createElement('div');
                scoreWrap.className = 'shrink-0 flex flex-col items-center';

                const scoreInput = document.createElement('input');
                scoreInput.type = 'number';
                scoreInput.min = 0;
                scoreInput.step = 0.5;
                scoreInput.className = 'w-16 rounded-lg border border-gray-300 px-2 py-1.5 text-sm text-center focus:outline-none focus:ring-2 focus:ring-orange-500 bg-white';
                scoreInput.placeholder = '0';
                scoreInput.value = lev.score;
                scoreInput.addEventListener('input', e => {
                    lev.score = parseFloat(e.target.value) || 0;
                    const mb = card.querySelector('.max-badge');
                    const ms = crit.levels.reduce((m, l) => Math.max(m, parseFloat(l.score) || 0), 0);
                    mb.textContent = ms + ' pts';
                    updateTotals();
                    serialise();
                });

                const scoreLabel = document.createElement('span');
                scoreLabel.className = 'text-xs text-gray-400 mt-0.5';
                scoreLabel.textContent = 'pts';

                scoreWrap.append(scoreInput, scoreLabel);

                const descLev = document.createElement('textarea');
                descLev.className = 'flex-1 rounded-lg border border-gray-300 px-2.5 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-orange-500 bg-white resize-none';
                descLev.placeholder = 'Describe this performance level...';
                descLev.rows = 2;
                descLev.value = lev.description;
                descLev.addEventListener('input', e => { lev.description = e.target.value; serialise(); });

                const delLevBtn = document.createElement('button');
                delLevBtn.type = 'button';
                delLevBtn.className = 'shrink-0 mt-1.5 text-gray-300 hover:text-red-400 transition-colors';
                delLevBtn.title = 'Remove level';
                delLevBtn.innerHTML = '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>';
                delLevBtn.addEventListener('click', () => {
                    if (crit.levels.length <= 1) return;
                    crit.levels.splice(li, 1);
                    render();
                });

                row.append(scoreWrap, descLev, delLevBtn);
                levelsWrap.appendChild(row);
            });

            const addLevBtn = document.createElement('button');
            addLevBtn.type = 'button';
            addLevBtn.className = 'mt-1.5 text-xs text-gray-500 hover:text-gray-800 flex items-center gap-1';
            addLevBtn.innerHTML = '<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg> Add level';
            addLevBtn.addEventListener('click', () => {
                crit.levels.push(emptyLevel());
                render();
            });
            levelsWrap.appendChild(addLevBtn);

            card.append(header, descToggle, descWrap, levelsWrap);
            list.appendChild(card);
        });

        updateTotals();
        serialise();
    }

    function updateTotals() {
        const totalsEl = document.getElementById('rubric-totals');
        if (!totalsEl) return;
        if (criteria.length === 0) { totalsEl.textContent = ''; return; }
        const total = criteria.reduce((sum, c) => {
            return sum + c.levels.reduce((m, l) => Math.max(m, parseFloat(l.score) || 0), 0);
        }, 0);
        totalsEl.textContent = criteria.length + ' criteria · max ' + total + ' pts total';
    }

    function serialise() {
        const input = document.getElementById('rubric_json_input');
        if (input) input.value = JSON.stringify(criteria);
    }

    // -------------------------------------------------------
    // Init
    // -------------------------------------------------------
    if (Array.isArray(seedRubric) && seedRubric.length > 0) {
        criteria = seedRubric;
    } else {
        criteria = [emptycriterion()];
    }
    render();

    document.getElementById('btn-add-criterion').addEventListener('click', () => {
        criteria.push(emptycriterion());
        render();
    });

    // -------------------------------------------------------
    // Moodle Import
    // -------------------------------------------------------
    @if(!empty($importUrl))
    const importBtn  = document.getElementById('btn-import-moodle');
    const importMsg  = document.getElementById('import-msg');
    const importUrl  = @json($importUrl);
    const csrfToken  = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    function showImportMsg(text, type) {
        importMsg.classList.remove('hidden', 'bg-green-50', 'border-green-200', 'text-green-800',
                                                'bg-red-50',   'border-red-200',   'text-red-800',
                                                'bg-blue-50',  'border-blue-200',  'text-blue-800');
        const map = {
            success: ['bg-green-50','border-green-200','text-green-800'],
            error:   ['bg-red-50',  'border-red-200',  'text-red-800'],
            loading: ['bg-blue-50', 'border-blue-200', 'text-blue-800'],
        };
        (map[type] || map.loading).forEach(c => importMsg.classList.add(c));
        importMsg.textContent = text;
    }

    importBtn.addEventListener('click', async () => {
        if (criteria.length > 0 && !confirm('This will replace your current rubric with the one from Moodle. Continue?')) return;

        importBtn.disabled = true;
        showImportMsg('Fetching rubric from Moodle…', 'loading');

        try {
            const res  = await fetch(importUrl, {
                method:  'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            });
            const data = await res.json();

            if (data.ok && Array.isArray(data.criteria) && data.criteria.length > 0) {
                criteria = data.criteria;
                render();
                showImportMsg('Rubric imported from Moodle — ' + criteria.length + ' criteria loaded. Review and save.', 'success');
            } else {
                showImportMsg(data.error || 'No rubric found in Moodle for this assignment.', 'error');
            }
        } catch (e) {
            showImportMsg('Request failed: ' + e.message, 'error');
        } finally {
            importBtn.disabled = false;
        }
    });
    @endif

    // Ensure JSON is serialised before the enclosing form submits
    const form = document.getElementById('rubric-builder').closest('form');
    if (form) form.addEventListener('submit', serialise);

})();
</script>
