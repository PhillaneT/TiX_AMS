<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Qualification;
use App\Models\QualificationModule;
use Illuminate\Http\Request;

class QualificationController extends Controller
{
    public function index()
    {
        $qualifications = Qualification::withCount('cohorts')
            ->orderBy('name')
            ->get();

        return view('qualifications.index', compact('qualifications'));
    }

    public function create()
    {
        return view('qualifications.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'                    => ['required', 'string', 'max:255'],
            'saqa_id'                 => ['nullable', 'string', 'max:50'],
            'nqf_level'               => ['required', 'integer', 'min:1', 'max:10'],
            'track'                   => ['required', 'in:legacy_seta,qcto_occupational'],
            'credits'                 => ['nullable', 'integer', 'min:1'],
            'seta'                    => ['required', 'string', 'max:50'],
            'seta_registration_number' => ['nullable', 'string', 'max:100'],
            'notes'                   => ['nullable', 'string'],
        ]);

        $qual = Qualification::create($data);
        AuditLog::record('qualification.created', $qual);

        // Import modules if SAQA fetch was done on the create form
        $modulesJson = $request->input('_fetched_modules');
        $moduleCount = 0;
        if ($modulesJson) {
            $modules = json_decode($modulesJson, true);
            if (is_array($modules) && count($modules)) {
                foreach ($modules as $i => $mod) {
                    QualificationModule::create([
                        'qualification_id' => $qual->id,
                        'module_type'      => $mod['module_type'] ?? 'KM',
                        'module_code'      => $mod['module_code'] ?? '',
                        'title'            => $mod['title'] ?? '',
                        'nqf_level'        => $mod['nqf_level'] ?? '',
                        'credits'          => (int)($mod['credits'] ?? 0),
                        'sortorder'        => (int)($mod['sortorder'] ?? $i),
                    ]);
                }
                $moduleCount = count($modules);
                $qual->update(['saqa_fetched_at' => now()]);
            }
        }

        $message = $moduleCount
            ? "Qualification created with {$moduleCount} module(s) imported from SAQA."
            : 'Qualification created.';

        return redirect()->route('qualifications.show', $qual)
            ->with('success', $message);
    }

    public function show(Qualification $qualification)
    {
        $qualification->load(['cohorts' => fn ($q) => $q->withCount('learners')->orderBy('name')]);

        return view('qualifications.show', compact('qualification'));
    }

    public function edit(Qualification $qualification)
    {
        return view('qualifications.edit', compact('qualification'));
    }

    public function update(Request $request, Qualification $qualification)
    {
        $data = $request->validate([
            'name'                    => ['required', 'string', 'max:255'],
            'saqa_id'                 => ['nullable', 'string', 'max:50'],
            'nqf_level'               => ['required', 'integer', 'min:1', 'max:10'],
            'track'                   => ['required', 'in:legacy_seta,qcto_occupational'],
            'credits'                 => ['nullable', 'integer', 'min:1'],
            'seta'                    => ['required', 'string', 'max:50'],
            'seta_registration_number' => ['nullable', 'string', 'max:100'],
            'status'                  => ['required', 'in:active,archived'],
            'notes'                   => ['nullable', 'string'],
        ]);

        $qualification->update($data);
        AuditLog::record('qualification.updated', $qualification, $data);

        return redirect()->route('qualifications.show', $qualification)
            ->with('success', 'Qualification updated.');
    }

    public function destroy(Qualification $qualification)
    {
        $qualification->delete();
        AuditLog::record('qualification.deleted', $qualification);

        return redirect()->route('qualifications.index')
            ->with('success', 'Qualification archived.');
    }
}
