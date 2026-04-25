<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\Qualification;
use App\Models\QualificationModule;
use App\Services\SaqaFetcher;
use Illuminate\Http\Request;

class QualificationModuleController extends Controller
{
    public function index(Qualification $qualification)
    {
        $modules     = $qualification->modules()->with('assignments')->get();
        $assignments = $qualification->assignments()->orderBy('name')->get();

        // Build mapping: module_id => [assignment_id, ...]
        $mapping = [];
        foreach ($modules as $mod) {
            $mapping[$mod->id] = $mod->assignments->pluck('id')->toArray();
        }

        return view('qualifications.modules', compact('qualification', 'modules', 'assignments', 'mapping'));
    }

    public function fetchSaqa(Request $request, Qualification $qualification)
    {
        $request->validate([
            'saqa_id' => ['required', 'string', 'regex:/^\d+$/'],
        ]);

        $saqaId = trim($request->saqa_id);
        $result = SaqaFetcher::fetch($saqaId);

        if (!$result['ok']) {
            return back()->withErrors(['saqa_id' => 'SAQA fetch failed: ' . $result['error']]);
        }

        $data = $result['data'];

        // Save raw data and update qualification metadata
        $qualification->update([
            'saqa_id'        => $saqaId,
            'saqa_raw_data'  => $data,
            'saqa_fetched_at' => now(),
        ]);

        // Replace all existing modules
        $qualification->modules()->delete();

        $modules = $data['modules'] ?? [];
        foreach ($modules as $mod) {
            QualificationModule::create([
                'qualification_id' => $qualification->id,
                'module_type'      => $mod['module_type'] ?? 'KM',
                'module_code'      => $mod['module_code'] ?? '',
                'title'            => $mod['title'] ?? '',
                'nqf_level'        => $mod['nqf_level'] ?? '',
                'credits'          => (int)($mod['credits'] ?? 0),
                'sortorder'        => (int)($mod['sortorder'] ?? 0),
            ]);
        }

        AuditLog::record('qualification.saqa_fetched', $qualification, [
            'saqa_id'      => $saqaId,
            'module_count' => count($modules),
        ]);

        $count = count($modules);
        return redirect()->route('qualifications.modules.index', $qualification)
            ->with('success', "Fetched \"{$data['title']}\" — {$count} module(s) imported from SAQA.");
    }

    public function saveMapping(Request $request, Qualification $qualification)
    {
        $request->validate([
            'mapping'   => ['nullable', 'array'],
            'mapping.*' => ['nullable', 'array'],
        ]);

        $mappingInput = $request->input('mapping', []);

        // Load all module IDs for this qualification (for security check)
        $moduleIds = $qualification->modules()->pluck('id')->toArray();

        foreach ($moduleIds as $moduleId) {
            $module = QualificationModule::find($moduleId);
            if (!$module) {
                continue;
            }

            $assignmentIds = array_filter(
                (array)($mappingInput[$moduleId] ?? []),
                fn ($v) => is_numeric($v) && (int)$v > 0
            );

            // Only allow assignments belonging to this qualification
            $validIds = Assignment::where('qualification_id', $qualification->id)
                ->whereIn('id', $assignmentIds)
                ->pluck('id')
                ->toArray();

            $module->assignments()->sync($validIds);
        }

        AuditLog::record('qualification.module_mapping_saved', $qualification);

        return redirect()->route('qualifications.modules.index', $qualification)
            ->with('success', 'Module-to-assignment mappings saved.');
    }

    public function addModule(Request $request, Qualification $qualification)
    {
        $data = $request->validate([
            'module_type' => ['required', 'in:KM,PM,WM,US,MOD'],
            'module_code' => ['required', 'string', 'max:100'],
            'title'       => ['required', 'string', 'max:500'],
            'nqf_level'   => ['nullable', 'string', 'max:10'],
            'credits'     => ['nullable', 'integer', 'min:0'],
        ]);

        $maxOrder = $qualification->modules()->max('sortorder') ?? 0;

        QualificationModule::create(array_merge($data, [
            'qualification_id' => $qualification->id,
            'sortorder'        => $maxOrder + 1,
        ]));

        return redirect()->route('qualifications.modules.index', $qualification)
            ->with('success', 'Module added.');
    }

    public function destroyModule(Qualification $qualification, QualificationModule $module)
    {
        abort_if($module->qualification_id !== $qualification->id, 403);
        $module->assignments()->detach();
        $module->delete();

        return redirect()->route('qualifications.modules.index', $qualification)
            ->with('success', 'Module removed.');
    }
}
