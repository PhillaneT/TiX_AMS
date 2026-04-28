<?php

namespace App\Http\Controllers;

use App\Models\ActiveContext;
use App\Models\Cohort;
use App\Models\Qualification;
use App\Models\Submission;

class DashboardController extends Controller
{
    public function index()
    {
        $context = ActiveContext::with(['qualification', 'cohort'])
            ->where('user_id', auth()->id())
            ->first();

        $stats = [];

        if ($context?->cohort_id) {
            $stats['learners'] = $context->cohort->learners()->count();
            $submissionsInCohort = Submission::whereHas('learner', fn ($q) =>
                $q->where('cohort_id', $context->cohort_id)
            );
            $stats['submissions']    = $submissionsInCohort->count();
            $stats['pending_review'] = (clone $submissionsInCohort)->where('status', 'review_required')->count();
            $stats['signed_off']     = (clone $submissionsInCohort)->where('status', 'signed_off')->count();
            $stats['emailed']        = (clone $submissionsInCohort)->where('status', 'emailed')->count();

            $recent = Submission::with(['learner', 'assignment'])
                ->whereHas('learner', fn ($q) =>
                    $q->where('cohort_id', $context->cohort_id)
                )
                ->latest()
                ->limit(5)
                ->get();
        } else {
            $recent = collect();
        }

        $qualifications = Qualification::where('status', 'active')->orderBy('name')->get();
        $cohorts        = $context?->qualification_id
            ? Cohort::where('qualification_id', $context->qualification_id)
                ->where('status', 'active')
                ->orderBy('name')
                ->get()
            : collect();

        return view('dashboard', compact('context', 'stats', 'recent', 'qualifications', 'cohorts'));
    }
}
