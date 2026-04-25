<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Qualification;
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

        return redirect()->route('qualifications.show', $qual)
            ->with('success', 'Qualification created.');
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
