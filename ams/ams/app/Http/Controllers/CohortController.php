<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Cohort;
use App\Models\Qualification;
use Illuminate\Http\Request;

class CohortController extends Controller
{
    public function index(Qualification $qualification)
    {
        $cohorts = $qualification->cohorts()
            ->withCount('learners')
            ->orderBy('name')
            ->get();

        return view('cohorts.index', compact('qualification', 'cohorts'));
    }

    public function create(Qualification $qualification)
    {
        return view('cohorts.create', compact('qualification'));
    }

    public function store(Request $request, Qualification $qualification)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'year'        => ['nullable', 'integer', 'min:2020', 'max:2040'],
            'start_date'  => ['nullable', 'date'],
            'end_date'    => ['nullable', 'date', 'after_or_equal:start_date'],
            'venue'       => ['nullable', 'string', 'max:255'],
            'facilitator' => ['nullable', 'string', 'max:255'],
            'notes'       => ['nullable', 'string'],
        ]);

        $data['qualification_id'] = $qualification->id;
        $cohort = Cohort::create($data);
        AuditLog::record('cohort.created', $cohort);

        return redirect()->route('qualifications.cohorts.learners.index', [$qualification, $cohort])
            ->with('success', 'Cohort created. Import your learners to get started.');
    }

    public function show(Qualification $qualification, Cohort $cohort)
    {
        $cohort->load(['learners' => fn ($q) => $q->orderBy('last_name')]);

        return view('cohorts.show', compact('qualification', 'cohort'));
    }

    public function edit(Qualification $qualification, Cohort $cohort)
    {
        return view('cohorts.edit', compact('qualification', 'cohort'));
    }

    public function update(Request $request, Qualification $qualification, Cohort $cohort)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'year'        => ['nullable', 'integer', 'min:2020', 'max:2040'],
            'start_date'  => ['nullable', 'date'],
            'end_date'    => ['nullable', 'date', 'after_or_equal:start_date'],
            'venue'       => ['nullable', 'string', 'max:255'],
            'facilitator' => ['nullable', 'string', 'max:255'],
            'status'      => ['required', 'in:active,completed,cancelled'],
            'notes'       => ['nullable', 'string'],
        ]);

        $cohort->update($data);
        AuditLog::record('cohort.updated', $cohort);

        return redirect()->route('qualifications.cohorts.show', [$qualification, $cohort])
            ->with('success', 'Cohort updated.');
    }

    public function destroy(Qualification $qualification, Cohort $cohort)
    {
        $cohort->delete();
        AuditLog::record('cohort.deleted', $cohort);

        return redirect()->route('qualifications.cohorts.index', $qualification)
            ->with('success', 'Cohort archived.');
    }
}
