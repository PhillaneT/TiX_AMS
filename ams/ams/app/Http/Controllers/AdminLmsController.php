<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\LmsConnection;
use App\Services\MoodleService;

class AdminLmsController extends Controller
{
    /**
     * Admin · LMS diagnostics page.
     * Lists every Moodle connection with a self-test button and every
     * Moodle-linked assignment with its cached pre-flight warnings.
     */
    public function index()
    {
        $userId = auth()->id();

        $connections = LmsConnection::where('user_id', $userId)
            ->orderBy('label')
            ->get();

        $assignments = Assignment::whereNotNull('lms_assignment_id')
            ->whereIn('lms_connection_id', $connections->pluck('id'))
            ->with(['qualification:id,name', 'lmsConnection:id,label'])
            ->orderBy('name')
            ->get();

        $diagnostics = session('lms_diagnostics', []);

        return view('admin.lms', compact('connections', 'assignments', 'diagnostics'));
    }

    /**
     * Run the wsfunction probe on a connection. Result is flashed into the
     * session so the diagnostics page renders it on the next request.
     */
    public function diagnose(LmsConnection $integration)
    {
        abort_if($integration->user_id !== auth()->id(), 403);

        try {
            $checks = (new MoodleService($integration))->diagnose();
        } catch (\Throwable $e) {
            return back()->with('error', 'Diagnose failed: ' . $e->getMessage());
        }

        AuditLog::record('lms.diagnose', null, [
            'connection_id' => $integration->id,
            'wsfunctions'   => array_keys($checks),
        ]);

        return back()
            ->with('lms_diagnostics', [$integration->id => $checks])
            ->with('success', 'Diagnostics complete for "' . $integration->label . '".');
    }

    /**
     * Run pre-flight on a single assignment and persist the warnings.
     */
    public function preflight(Assignment $assignment)
    {
        $conn = $assignment->lmsConnection;
        abort_if(! $conn || $conn->user_id !== auth()->id(), 403);

        try {
            $result = (new MoodleService($conn))->preflightAssignment($assignment);
        } catch (\Throwable $e) {
            return back()->with('error', 'Pre-flight failed: ' . $e->getMessage());
        }

        $assignment->update([
            'lms_preflight_json'       => $result,
            'lms_preflight_checked_at' => now(),
        ]);

        $count = count($result['warnings'] ?? []);
        $msg   = $count
            ? "Pre-flight for \"{$assignment->name}\" found {$count} warning(s)."
            : "Pre-flight for \"{$assignment->name}\" passed — no warnings.";

        return back()->with($count ? 'warning' : 'success', $msg);
    }

    /**
     * Run pre-flight on every Moodle-linked assignment for a connection.
     */
    public function preflightAll(LmsConnection $integration)
    {
        abort_if($integration->user_id !== auth()->id(), 403);

        $service       = new MoodleService($integration);
        $assignments   = Assignment::where('lms_connection_id', $integration->id)
            ->whereNotNull('lms_assignment_id')
            ->get();

        $checked  = 0;
        $warnings = 0;

        foreach ($assignments as $a) {
            try {
                $result = $service->preflightAssignment($a);
            } catch (\Throwable) {
                continue;
            }
            $a->update([
                'lms_preflight_json'       => $result,
                'lms_preflight_checked_at' => now(),
            ]);
            $checked++;
            $warnings += count($result['warnings'] ?? []);
        }

        return back()->with('success',
            "Pre-flight checked {$checked} assignment(s), found {$warnings} warning(s) total.");
    }
}
