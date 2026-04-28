<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Cohort;
use App\Models\Learner;
use App\Models\Qualification;
use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LearnerController extends Controller
{
    public function index(Qualification $qualification, Cohort $cohort)
    {
        $learners = $cohort->learners()->orderBy('last_name')->get();

        return view('learners.index', compact('qualification', 'cohort', 'learners'));
    }

    public function downloadTemplate()
    {
        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="ajananova_learner_template.csv"',
        ];

        $callback = function () {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['first_name', 'last_name', 'email', 'external_ref']);
            fputcsv($file, ['Jane', 'Dlamini', 'jane.dlamini@example.com', 'EMP001']);
            fputcsv($file, ['Sipho', 'Nkosi', 'sipho.nkosi@example.com', 'EMP002']);
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function importForm(Qualification $qualification, Cohort $cohort)
    {
        return view('learners.import', compact('qualification', 'cohort'));
    }

    public function import(Request $request, Qualification $qualification, Cohort $cohort)
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $file = $request->file('csv_file');
        $handle = fopen($file->getRealPath(), 'r');

        $header  = fgetcsv($handle);
        $header  = array_map('strtolower', array_map('trim', $header));
        $required = ['first_name', 'last_name'];

        foreach ($required as $col) {
            if (! in_array($col, $header)) {
                return back()->withErrors(['csv_file' => "CSV is missing required column: {$col}"]);
            }
        }

        $rows    = [];
        $errors  = [];
        $lineNum = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $lineNum++;
            $data = array_combine($header, array_pad($row, count($header), ''));

            $validator = Validator::make($data, [
                'first_name' => ['required', 'string', 'max:100'],
                'last_name'  => ['required', 'string', 'max:100'],
                'email'      => ['nullable', 'email', 'max:255'],
                'external_ref' => ['nullable', 'string', 'max:100'],
            ]);

            if ($validator->fails()) {
                $errors[] = "Row {$lineNum}: " . implode(', ', $validator->errors()->all());
                continue;
            }

            $rows[] = [
                'cohort_id'    => $cohort->id,
                'first_name'   => trim($data['first_name']),
                'last_name'    => trim($data['last_name']),
                'email'        => $data['email'] ? trim($data['email']) : null,
                'external_ref' => $data['external_ref'] ? trim($data['external_ref']) : null,
                'status'       => 'active',
                'created_at'   => now(),
                'updated_at'   => now(),
            ];
        }

        fclose($handle);

        if ($errors && ! $rows) {
            return back()->withErrors(['csv_file' => $errors])->with('parse_errors', $errors);
        }

        $inserted = 0;
        foreach ($rows as $row) {
            Learner::firstOrCreate(
                ['cohort_id' => $cohort->id, 'first_name' => $row['first_name'], 'last_name' => $row['last_name']],
                $row
            );
            $inserted++;
        }

        AuditLog::record('learner.import', $cohort, ['inserted' => $inserted, 'errors' => count($errors)]);

        $msg = "Imported {$inserted} learner(s).";
        if ($errors) {
            $msg .= ' ' . count($errors) . ' row(s) had errors and were skipped.';
        }

        return redirect()
            ->route('qualifications.cohorts.learners.index', [$qualification, $cohort])
            ->with('success', $msg);
    }

    public function poe(Qualification $qualification, Cohort $cohort, Learner $learner)
    {
        abort_if($learner->cohort_id !== $cohort->id, 404);

        // Load all modules for this qualification with their assignments
        $modules = $qualification->modules()
            ->with(['assignments' => fn($q) => $q->withCount('submissions')])
            ->get();

        // Load all of this learner's submissions for this qualification's assignments
        $assignmentIds = $qualification->assignments()->pluck('id');
        $submissionsByAssignment = Submission::with('markingResult')
            ->where('learner_id', $learner->id)
            ->whereIn('assignment_id', $assignmentIds)
            ->get()
            ->keyBy('assignment_id');

        // Build per-module status
        $moduleStatuses = [];
        foreach ($modules as $mod) {
            $assignedAssignments = $mod->assignments;
            if ($assignedAssignments->isEmpty()) {
                $moduleStatuses[$mod->id] = [
                    'status'      => 'unmapped',
                    'label'       => 'Not mapped',
                    'assignments' => [],
                ];
                continue;
            }

            $verdicts    = [];
            $assignments = [];
            foreach ($assignedAssignments as $asgn) {
                $sub = $submissionsByAssignment[$asgn->id] ?? null;
                $verdict = null;
                $submissionStatus = null;
                if ($sub) {
                    $submissionStatus = $sub->status;
                    $verdict = $sub->markingResult?->final_verdict;
                }
                $assignments[] = [
                    'assignment'       => $asgn,
                    'submission'       => $sub,
                    'submission_status' => $submissionStatus,
                    'verdict'          => $verdict,
                ];
                if ($verdict) {
                    $verdicts[] = $verdict;
                }
            }

            $totalRequired = $assignedAssignments->count();
            $competentCount = count(array_filter($verdicts, fn($v) => $v === 'COMPETENT'));
            $nycCount       = count(array_filter($verdicts, fn($v) => $v === 'NOT_YET_COMPETENT'));

            if ($nycCount > 0) {
                $status = 'NYC';
                $label  = 'Not Yet Competent';
            } elseif ($competentCount === $totalRequired) {
                $status = 'C';
                $label  = 'Competent';
            } elseif ($competentCount > 0) {
                $status = 'partial';
                $label  = "Partial ({$competentCount}/{$totalRequired})";
            } else {
                $status = 'pending';
                $label  = 'Pending';
            }

            $moduleStatuses[$mod->id] = [
                'status'      => $status,
                'label'       => $label,
                'assignments' => $assignments,
            ];
        }

        AuditLog::record('learner.poe_viewed', $learner, ['qualification_id' => $qualification->id]);

        return view('learners.poe', compact(
            'qualification', 'cohort', 'learner', 'modules', 'moduleStatuses'
        ));
    }

    public function destroy(Qualification $qualification, Cohort $cohort, Learner $learner)
    {
        $learner->delete();
        AuditLog::record('learner.removed', $learner);

        return back()->with('success', 'Learner removed.');
    }
}
