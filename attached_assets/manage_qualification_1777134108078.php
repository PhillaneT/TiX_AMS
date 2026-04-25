<?php
// This file is part of Moodle - http://moodle.org/
//
// local/poeexport/manage_qualification.php
// Manage SAQA qualification data: fetch from SAQA and map modules to activities.

require_once(__DIR__ . '/../../config.php');
require_once __DIR__ . '/poe_theme_init.php';

$courseid = required_param('courseid', PARAM_INT);
$action   = optional_param('action', '', PARAM_ALPHANUMEXT);

$course  = get_course($courseid);
$context = context_course::instance($courseid);

require_login($course);
require_capability('local/poeexport:coursesettings', $context);

$baseurl      = new moodle_url('/local/poeexport/manage_qualification.php', ['courseid' => $courseid]);
$settingsurl  = new moodle_url('/local/poeexport/course_settings.php',      ['courseid' => $courseid]);
$streamsurl   = new moodle_url('/local/poeexport/manage_streams.php',       ['courseid' => $courseid]);
$exporturl    = new moodle_url('/local/poeexport/view.php',                 ['courseid' => $courseid]);

$PAGE->set_url($baseurl);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title('Manage Qualification — ' . format_string($course->shortname));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add('POE Export', $exporturl);
$PAGE->navbar->add('Course Settings', $settingsurl);
$PAGE->navbar->add('Manage Qualification');

global $DB, $OUTPUT;

// ---------------------------------------------------------------
// POST: action=fetch — pull qualification from SAQA and save
// ---------------------------------------------------------------
if ($action === 'fetch' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    $saqa_id = optional_param('saqa_id', '', PARAM_TEXT);
    $saqa_id = trim($saqa_id);

    if (empty($saqa_id)) {
        redirect($baseurl, 'Please enter a SAQA ID.', null, \core\output\notification::NOTIFY_ERROR);
    }

    $result = \local_poeexport\saqa_fetcher::fetch($saqa_id);

    if (!$result['ok']) {
        redirect($baseurl, 'SAQA fetch failed: ' . $result['error'], null, \core\output\notification::NOTIFY_ERROR);
    }

    $data = $result['data'];
    $now  = time();

    // Delete existing qualification (and modules via cascade logic below) for this course
    $oldqual = $DB->get_record('local_poeexport_qualifications', ['courseid' => $courseid], '*', IGNORE_MISSING);
    if ($oldqual) {
        $DB->delete_records('local_poeexport_qual_modules', ['qualid' => $oldqual->id]);
        $DB->delete_records('local_poeexport_qualifications', ['id' => $oldqual->id]);
    }

    // Sanitise parsed values before DB write
    $nqf_raw     = trim($data['nqf_level'] ?? '');
    $credits_raw = trim((string)($data['total_credits'] ?? ''));

    // NQF level: extract first run of digits; accept values 1–10 (handles "NQF Level 05" → "5")
    if (preg_match('/(\d+)/', $nqf_raw, $nm) && (int)$nm[1] >= 1 && (int)$nm[1] <= 10) {
        $nqf_clean = (string)(int)$nm[1]; // normalise "05" → "5"
    } else {
        $nqf_clean = core_text::substr($nqf_raw, 0, 20); // keep raw but truncate safely
    }

    // Credits: extract digits; discard if non-numeric string snuck through
    $credits_clean = is_numeric($credits_raw) ? (int)$credits_raw : 0;

    // Insert new qualification
    $qualrec = (object)[
        'courseid'      => $courseid,
        'saqa_id'       => $saqa_id,
        'title'         => $data['title'] ?? '',
        'nqf_level'     => $nqf_clean,
        'total_credits' => $credits_clean,
        'raw_data'      => json_encode($data),
        'timecreated'   => $now,
        'timemodified'  => $now,
    ];
    $qualid = $DB->insert_record('local_poeexport_qualifications', $qualrec);

    // Insert modules
    if (!empty($data['modules']) && is_array($data['modules'])) {
        foreach ($data['modules'] as $mod) {
            $modrec = (object)[
                'qualid'      => $qualid,
                'module_type' => $mod['module_type'] ?? 'KM',
                'module_code' => $mod['module_code'] ?? '',
                'title'       => $mod['title'] ?? '',
                'nqf_level'   => $mod['nqf_level'] ?? '',
                'credits'     => (int)($mod['credits'] ?? 0),
                'cmid'        => 0,
                'sortorder'   => (int)($mod['sortorder'] ?? 0),
            ];
            $DB->insert_record('local_poeexport_qual_modules', $modrec);
        }
    }

    $modcount = count($data['modules'] ?? []);
    redirect($baseurl, "Qualification fetched successfully: \"{$data['title']}\" with {$modcount} module(s).", null, \core\output\notification::NOTIFY_SUCCESS);
}

// ---------------------------------------------------------------
// POST: action=savemapping — save cmid mappings per module
// ---------------------------------------------------------------
if ($action === 'savemapping' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    $qual = $DB->get_record('local_poeexport_qualifications', ['courseid' => $courseid], '*', IGNORE_MISSING);
    if (!$qual) {
        redirect($baseurl, 'No qualification configured for this course.', null, \core\output\notification::NOTIFY_ERROR);
    }

    // cmid is now a nested array: cmid[MODULE_ID][] = [cmid1, cmid2, ...]
    $raw_cmid_map = (isset($_POST['cmid']) && is_array($_POST['cmid'])) ? $_POST['cmid'] : [];

    foreach ($raw_cmid_map as $module_id => $cmid_values) {
        $module_id = (int)$module_id;
        if ($module_id <= 0) { continue; }

        // Verify ownership
        $mod = $DB->get_record('local_poeexport_qual_modules', ['id' => $module_id, 'qualid' => $qual->id], 'id', IGNORE_MISSING);
        if (!$mod) { continue; }

        // Replace all existing mappings for this module
        $DB->delete_records('local_poeexport_module_cms', ['moduleid' => $module_id]);

        $sort = 0;
        foreach ((array)$cmid_values as $cmid_value) {
            $cmid_value = (int)$cmid_value;
            if ($cmid_value <= 0) { continue; }
            $DB->insert_record('local_poeexport_module_cms', (object)[
                'moduleid'  => $module_id,
                'cmid'      => $cmid_value,
                'sortorder' => $sort++,
            ]);
        }
    }

    redirect($baseurl, 'Module mappings saved.', null, \core\output\notification::NOTIFY_SUCCESS);
}

// ---------------------------------------------------------------
// Load data for display
// ---------------------------------------------------------------
$qual    = $DB->get_record('local_poeexport_qualifications', ['courseid' => $courseid], '*', IGNORE_MISSING);
$modules = [];
$module_cmids = []; // [ moduleid => [cmid, cmid, ...] ]

if ($qual) {
    $modules = $DB->get_records('local_poeexport_qual_modules', ['qualid' => $qual->id], 'sortorder ASC');

    if (!empty($modules)) {
        $modids = array_keys($modules);
        list($insql, $inparams) = $DB->get_in_or_equal($modids);
        $mappings = $DB->get_records_select('local_poeexport_module_cms', "moduleid $insql", $inparams, 'moduleid, sortorder');
        foreach ($mappings as $m) {
            $module_cmids[(int)$m->moduleid][] = (int)$m->cmid;
        }
    }
}

// ---------------------------------------------------------------
// Build activity list from get_fast_modinfo (for mapping dropdowns)
// ---------------------------------------------------------------
$skip_modnames  = ['label', 'url', 'resource', 'page'];
$modinfo        = get_fast_modinfo($course);
$course_activities = []; // [ cmid => display_name ] in course display order

// Iterate sections in order, then activities within each section in order —
// this matches the sequence the course displays them to users.
foreach ($modinfo->get_sections() as $section_cms) {
    foreach ($section_cms as $cmid) {
        $cm = $modinfo->get_cm($cmid);
        if ($cm->deletioninprogress) { continue; }
        if (in_array($cm->modname, $skip_modnames, true)) { continue; }
        $course_activities[(int)$cm->id] = format_string($cm->name) . ' [' . $cm->modname . ']';
    }
}

// ---------------------------------------------------------------
// Render
// ---------------------------------------------------------------
echo $OUTPUT->header();
?>

<style>
<?php include __DIR__ . '/poe_admin.css.php'; ?>
.poe-qual-card { background:#f8fafc; border:1px solid #e5e7eb; border-radius:8px; padding:16px 20px; margin-bottom:16px; }
.poe-qual-card dl { display:grid; grid-template-columns:130px 1fr; gap:6px 12px; margin:0; }
.poe-qual-card dt { font-weight:600; color:#6b7280; font-size:.88rem; }
.poe-qual-card dd { margin:0; font-size:.95rem; }
.badge-km { background:#1d4ed8; color:#fff; }
.badge-pm { background:#16a34a; color:#fff; }
.badge-wm { background:#c2410c; color:#fff; }
.badge-us  { background:#7c3aed; color:#fff; }
.badge-mod { background:#0891b2; color:#fff; }

/* Multi-activity mapping widget */
.poe-multimap { display:flex; flex-direction:column; gap:5px; }
.poe-multimap__row { display:flex; gap:5px; align-items:center; }
.poe-multimap__row .poe-select { flex:1; min-width:0; }
.poe-multimap__remove {
    flex-shrink:0; width:26px; height:26px; padding:0;
    border:1px solid #e5e7eb; border-radius:6px;
    background:#fff; color:#9ca3af; font-size:.8rem; cursor:pointer;
    line-height:1; transition:background .12s, color .12s, border-color .12s;
}
.poe-multimap__remove:hover { background:#fee2e2; color:#dc2626; border-color:#fca5a5; }
.poe-multimap__add {
    align-self:flex-start; padding:4px 10px;
    font-size:.78rem; font-weight:600; color:#1d4ed8;
    background:#eff6ff; border:1px solid #bfdbfe; border-radius:6px;
    cursor:pointer; transition:background .12s;
}
.poe-multimap__add:hover { background:#dbeafe; }
.poe-map-table { width:100%; border-collapse:separate; border-spacing:0; border:1px solid #e5e7eb; border-radius:8px; overflow:hidden; }
.poe-map-table thead th { background:#f8fafc; font-weight:600; padding:10px 12px; border-bottom:1px solid #e5e7eb; font-size:.85rem; }
.poe-map-table tbody td { padding:9px 12px; border-bottom:1px solid #e5e7eb; font-size:.88rem; vertical-align:middle; }
.poe-map-table tbody tr:last-child td { border-bottom:none; }
.poe-map-table tbody tr:nth-child(even) td { background:#fcfdff; }
</style>
<?php poe_theme_init_script(); ?>

<div class="poe-container">

    <!-- ── Sidebar ────────────────────────────────────────── -->
    <aside class="poe-sidebar">
        <a href="<?= $exporturl->out(false); ?>" class="poe-navlink poe-navlink--back">
            &#8592; POE Export
        </a>

        <div class="poe-nav-group">Configuration</div>
        <a href="<?= $settingsurl->out(false); ?>" class="poe-navlink">
            Course Settings
        </a>
        <a href="<?= $baseurl->out(false); ?>" class="poe-navlink poe-navlink--active">
            Manage Qualification
        </a>
        <a href="<?= $streamsurl->out(false); ?>" class="poe-navlink">
            Manage Streams
        </a>

        <?php if ($qual): ?>
        <div class="poe-nav-group" style="margin-top:20px;">Qualification</div>
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:10px 12px;font-size:.85rem;color:#374151;">
            <div style="font-weight:700;margin-bottom:2px;"><?= s($qual->title); ?></div>
            <div class="text-muted">SAQA <?= s($qual->saqa_id); ?> &bull; NQF <?= s($qual->nqf_level); ?> &bull; <?= (int)$qual->total_credits; ?> credits</div>
            <div class="text-muted"><?= count($modules); ?> module(s)</div>
        </div>
        <?php endif; ?>
    </aside>

    <!-- ── Main ───────────────────────────────────────────── -->
    <main class="poe-main">

        <div class="poe-page-title">Manage Qualification</div>
        <p class="poe-page-sub"><?= format_string($course->fullname); ?></p>

        <!-- ── Fetch from SAQA ────────────────────────────── -->
        <div class="poe-section-title">Fetch from SAQA</div>

        <form method="post" action="<?= $baseurl->out(false); ?>" class="poe-fetch-row">
            <input type="hidden" name="sesskey"  value="<?= sesskey(); ?>">
            <input type="hidden" name="action"   value="fetch">
            <input type="hidden" name="courseid" value="<?= (int)$courseid; ?>">
            <div class="poe-field">
                <label for="saqa_id">SAQA Qualification ID</label>
                <input type="text" id="saqa_id" name="saqa_id"
                       class="poe-input"
                       value="<?= $qual ? s($qual->saqa_id) : ''; ?>"
                       placeholder="e.g. 118708" required>
            </div>
            <button type="submit" class="poe-btn-primary">Fetch from SAQA</button>
        </form>

        <?php if (!$qual): ?>
        <p class="text-muted"><em>No qualification configured. Enter a SAQA ID above and click Fetch.</em></p>
        <?php else: ?>

        <!-- ── Qualification summary ──────────────────────── -->
        <div class="poe-qual-card">
            <dl>
                <dt>SAQA ID</dt>   <dd><?= s($qual->saqa_id); ?></dd>
                <dt>Title</dt>     <dd><?= s($qual->title); ?></dd>
                <dt>NQF Level</dt> <dd><?= s($qual->nqf_level); ?></dd>
                <dt>Credits</dt>   <dd><?= (int)$qual->total_credits; ?></dd>
            </dl>
        </div>

        <?php if (!empty($modules)): ?>
        <!-- ── Module to Activity Mapping ─────────────────── -->
        <div class="poe-section-title">Module to Activity Mapping</div>

        <form method="post" action="<?= $baseurl->out(false); ?>">
            <input type="hidden" name="sesskey"  value="<?= sesskey(); ?>">
            <input type="hidden" name="action"   value="savemapping">
            <input type="hidden" name="courseid" value="<?= (int)$courseid; ?>">

            <div class="table-responsive">
            <table class="poe-map-table">
                <thead>
                    <tr>
                        <th style="width:150px;">Code</th>
                        <th style="width:55px;">Type</th>
                        <th>Module Title</th>
                        <th style="width:50px;">NQF</th>
                        <th style="width:55px;">Credits</th>
                        <th style="width:260px;">Mapped Activity</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($modules as $mod):
                    $type_upper = strtoupper(trim($mod->module_type));
                    $badge_class = 'badge-km';
                    if ($type_upper === 'PM')  { $badge_class = 'badge-pm'; }
                    elseif ($type_upper === 'WM')  { $badge_class = 'badge-wm'; }
                    elseif ($type_upper === 'US')  { $badge_class = 'badge-us'; }
                    elseif ($type_upper === 'MOD') { $badge_class = 'badge-mod'; }
                ?>
                <tr>
                    <td><code style="font-size:.78rem;"><?= s($mod->module_code); ?></code></td>
                    <td><span class="badge <?= $badge_class; ?>"><?= s($type_upper); ?></span></td>
                    <td><?= s($mod->title); ?></td>
                    <td><?= s($mod->nqf_level); ?></td>
                    <td><?= (int)$mod->credits; ?></td>
                    <td>
                        <?php
                        $mapped = $module_cmids[(int)$mod->id] ?? [];
                        // Always show at least one row (empty if unmapped)
                        if (empty($mapped)) { $mapped = [0]; }
                        ?>
                        <div class="poe-multimap" data-modid="<?= (int)$mod->id; ?>">
                            <?php foreach ($mapped as $sel_cmid): ?>
                            <div class="poe-multimap__row">
                                <select name="cmid[<?= (int)$mod->id; ?>][]" class="poe-select poe-multimap__select">
                                    <option value="0"<?= ((int)$sel_cmid === 0) ? ' selected' : ''; ?>>— Not mapped —</option>
                                    <?php foreach ($course_activities as $cmid => $actname): ?>
                                    <option value="<?= (int)$cmid; ?>"<?= ((int)$sel_cmid === $cmid) ? ' selected' : ''; ?>>
                                        <?= s($actname); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="poe-multimap__remove" title="Remove">&#x2715;</button>
                            </div>
                            <?php endforeach; ?>
                            <button type="button" class="poe-multimap__add">+ Add activity</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>

            <div class="poe-actions">
                <button type="submit" class="poe-btn-primary">Save Mappings</button>
            </div>
        </form>
        <?php endif; // modules ?>

        <?php endif; // qual ?>

    </main>
</div>

<script>
(function () {

    // ── Master options list ──────────────────────────────────────────────────
    // Captured once from the first select on the page (all selects have the
    // same course-activity list). Each entry: { value, label }.
    var masterOptions = [];
    var seed = document.querySelector('.poe-multimap__select');
    if (seed) {
        Array.from(seed.options).forEach(function (o) {
            masterOptions.push({ value: o.value, label: o.text });
        });
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    // Returns a Set of all cmid values currently selected across every select
    // on the page, excluding `exceptSelect` itself.
    function usedValues(exceptSelect) {
        var used = new Set();
        document.querySelectorAll('.poe-multimap__select').forEach(function (sel) {
            if (sel !== exceptSelect && sel.value !== '0') {
                used.add(sel.value);
            }
        });
        return used;
    }

    // Rebuild a single select: show only options that are either
    //   (a) "Not mapped" (value 0),
    //   (b) this select's own current value, or
    //   (c) not used by any other select.
    function rebuildSelect(sel) {
        var current = sel.value;
        var used    = usedValues(sel);
        sel.innerHTML = '';
        masterOptions.forEach(function (opt) {
            if (opt.value === '0' || opt.value === current || !used.has(opt.value)) {
                var o = document.createElement('option');
                o.value    = opt.value;
                o.text     = opt.label;
                o.selected = (opt.value === current);
                sel.appendChild(o);
            }
        });
    }

    // Rebuild every select on the page.
    function rebuildAll() {
        document.querySelectorAll('.poe-multimap__select').forEach(rebuildSelect);
    }

    // ── Add-row button ───────────────────────────────────────────────────────
    document.querySelectorAll('.poe-multimap__add').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var container = btn.closest('.poe-multimap');
            var modid     = container.dataset.modid;

            // Build a fresh row from masterOptions — never clone a filtered select
            var newRow = document.createElement('div');
            newRow.className = 'poe-multimap__row';

            var sel = document.createElement('select');
            sel.name      = 'cmid[' + modid + '][]';
            sel.className = 'poe-select poe-multimap__select';
            masterOptions.forEach(function (opt) {
                var o = document.createElement('option');
                o.value = opt.value;
                o.text  = opt.label;
                sel.appendChild(o);
            });
            sel.value = '0';

            var rmBtn = document.createElement('button');
            rmBtn.type      = 'button';
            rmBtn.className = 'poe-multimap__remove';
            rmBtn.title     = 'Remove';
            rmBtn.innerHTML = '&#x2715;';

            newRow.appendChild(sel);
            newRow.appendChild(rmBtn);
            wireRemove(newRow);
            container.insertBefore(newRow, btn);
            rebuildAll(); // now filter with the full master list intact
        });
    });

    // ── Remove button ────────────────────────────────────────────────────────
    document.querySelectorAll('.poe-multimap__row').forEach(wireRemove);

    function wireRemove(row) {
        var btn = row.querySelector('.poe-multimap__remove');
        if (!btn) { return; }
        btn.addEventListener('click', function () {
            var container = btn.closest('.poe-multimap');
            var rows = container.querySelectorAll('.poe-multimap__row');
            if (rows.length === 1) {
                row.querySelector('select').value = '0';
            } else {
                row.remove();
            }
            rebuildAll(); // freed activity reappears elsewhere
        });
    }

    // ── Change event ─────────────────────────────────────────────────────────
    document.addEventListener('change', function (e) {
        if (e.target.classList.contains('poe-multimap__select')) {
            rebuildAll();
        }
    });

    // ── Initial pass ─────────────────────────────────────────────────────────
    rebuildAll();

}());
</script>

<?php
echo $OUTPUT->footer();
