<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Cohort;
use App\Models\Learner;
use App\Models\Qualification;
use App\Models\Submission;
use App\Services\Pdf\TrackingDocumentGenerator;
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
        [$modules, $moduleStatuses] = $this->buildPoeMatrix($qualification, $learner);
        AuditLog::record('learner.poe_viewed', $learner, ['qualification_id' => $qualification->id]);
        return view('learners.poe', compact('qualification', 'cohort', 'learner', 'modules', 'moduleStatuses'));
    }

    private function buildPoeMatrix(Qualification $qualification, Learner $learner): array
    {
        $modules = $qualification->modules()
            ->with(['assignments' => fn($q) => $q->withCount('submissions')])
            ->get();

        $assignmentIds = $qualification->assignments()->pluck('id');
        $submissionsByAssignment = Submission::with('markingResult')
            ->where('learner_id', $learner->id)
            ->whereIn('assignment_id', $assignmentIds)
            ->get()
            ->keyBy('assignment_id');

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

        return [$modules, $moduleStatuses];
    }

    public function poePdf(Qualification $qualification, Cohort $cohort, Learner $learner, TrackingDocumentGenerator $gen)
    {
        abort_if($learner->cohort_id !== $cohort->id, 404);

        [$modules, $moduleStatuses] = $this->buildPoeMatrix($qualification, $learner);
        $assessor = auth()->user();

        // Shape modules for the generator
        $modulesPayload = [];
        foreach ($modules as $mod) {
            $st = $moduleStatuses[$mod->id] ?? ['status' => 'unmapped', 'label' => '—', 'assignments' => []];
            $activities = [];
            foreach ($st['assignments'] as $item) {
                $asgn = $item['assignment'];
                $sub  = $item['submission'];
                $mr   = $sub?->markingResult;
                $grade = null; $percent = null;
                if ($mr) {
                    $score = 0; $max = 0;
                    foreach ((array) ($mr->questions_json ?? []) as $q) {
                        $score += (float) ($q['awarded']   ?? $q['score']     ?? 0);
                        $max   += (float) ($q['max_marks'] ?? $q['max_score'] ?? $q['max'] ?? 0);
                    }
                    if ($max <= 0) { $max = (float) ($asgn->total_marks ?? 0); }
                    // Scale the rubric score to the assignment's stated total when they differ
                    // (e.g. one criterion out of 100 → assignment is out of 11)
                    if ($max > 0 && $asgn->total_marks > 0 && (float)$asgn->total_marks !== $max) {
                        $score = $score * ((float)$asgn->total_marks / $max);
                        $max   = (float) $asgn->total_marks;
                    }
                    if ($max > 0) {
                        $fmt = fn($n) => rtrim(rtrim(number_format($n, 1), '0'), '.');
                        $grade   = $fmt($score) . '/' . $fmt($max);
                        $percent = round(($score / $max) * 100, 1);
                    }
                }
                $activities[] = [
                    'name'    => $asgn->name,
                    'grade'   => $grade,
                    'percent' => $percent,
                    'result'  => $item['verdict'],
                ];
            }
            $modulesPayload[] = [
                'code'         => $mod->module_code,
                'type'         => $mod->module_type,
                'title'        => $mod->title,
                'nqf_level'    => $mod->nqf_level ? ('L' . ltrim((string)$mod->nqf_level, 'L')) : '—',
                'credits'      => $mod->credits,
                'activities'   => $activities,
                'status'       => $st['status'],
                'status_label' => $st['label'],
            ];
        }

        $path = $gen->generate([
            'qualification' => [
                'name'      => $qualification->name,
                'saqa_id'   => $qualification->saqa_id,
                'nqf_level' => $qualification->nqf_level,
                'credits'   => $qualification->credits,
            ],
            'learner' => [
                'full_name'  => $learner->full_name ?: '—',
                'student_no' => $learner->external_ref ?: ('#' . $learner->id),
            ],
            'modules'           => $modulesPayload,
            'date'              => now(),
            'assessor_name'     => $assessor?->name ?? '',
            'etqa_registration' => $assessor?->etqa_registration ?? '',
            'signature_path'    => $assessor?->signature_path ?? null,
            'stamp_path'        => $assessor?->stamp_path ?? null,
            'stamp_generated'   => ($assessor && $assessor->stamp_use_generated) ? [
                'org_top'           => $assessor->stamp_org_top ?? '',
                'org_bottom'        => $assessor->stamp_org_bottom ?? '',
                'role'              => $assessor->stamp_role ?? '',
                'holder_name'       => $assessor->stamp_holder_name ?? '',
                'etqa_registration' => $assessor->etqa_registration ?? '',
            ] : null,
        ]);

        AuditLog::record('learner.poe_pdf_exported', $learner, ['qualification_id' => $qualification->id]);

        $filename = 'CompetencyTracking_' . preg_replace('/[^A-Za-z0-9_-]/', '_',
            ($learner->external_ref ?: $learner->id) . '_' . ($qualification->saqa_id ?: $qualification->id)) . '.pdf';

        return response()->file($path, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ])->deleteFileAfterSend(true);
    }

    public function destroy(Qualification $qualification, Cohort $cohort, Learner $learner)
    {
        $learner->delete();
        AuditLog::record('learner.removed', $learner);

        return back()->with('success', 'Learner removed.');
    }
}
