<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\LmsConnection;
use App\Models\Qualification;
use App\Services\MoodleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AssignmentController extends Controller
{
    public function index(Qualification $qualification)
    {
        $assignments = $qualification->assignments()
            ->withCount('qualificationModules')
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        return view('assignments.index', compact('qualification', 'assignments'));
    }

    public function create(Qualification $qualification)
    {
        return view('assignments.create', compact('qualification'));
    }

    public function store(Request $request, Qualification $qualification)
    {
        $data = $request->validate([
            'name'            => ['required', 'string', 'max:255'],
            'description'     => ['nullable', 'string'],
            'type'            => ['required', 'in:formative,summative'],
            'total_marks'     => ['nullable', 'integer', 'min:1'],
            'memo_type'       => ['required', 'in:text,pdf,questions,rubric'],
            'memo_text'       => ['nullable', 'string', 'required_if:memo_type,text'],
            'memo_file'       => ['nullable', 'file', 'mimes:pdf', 'max:20480', 'required_if:memo_type,pdf'],
            'rubric_json'     => ['nullable', 'string'],
            'ai_instructions' => ['nullable', 'string', 'max:3000'],
        ]);

        $memoPath = null;
        if ($request->hasFile('memo_file') && $data['memo_type'] === 'pdf') {
            $filename  = Str::uuid() . '_' . Str::slug($data['name']) . '.pdf';
            $memoPath  = $request->file('memo_file')->storeAs('memos', $filename, 'local');
        }

        $rubricJson = null;
        if ($data['memo_type'] === 'rubric' && ! empty($data['rubric_json'])) {
            $decoded = json_decode($data['rubric_json'], true);
            $rubricJson = is_array($decoded) ? $decoded : null;
        }

        $assignment = $qualification->assignments()->create([
            'name'            => $data['name'],
            'description'     => $data['description'] ?? null,
            'type'            => $data['type'],
            'total_marks'     => $data['total_marks'] ?? null,
            'memo_type'       => $data['memo_type'],
            'memo_text'       => $data['memo_type'] === 'text' ? ($data['memo_text'] ?? null) : null,
            'memo_path'       => $memoPath,
            'rubric_json'     => $rubricJson,
            'ai_instructions' => $data['ai_instructions'] ?? null,
        ]);

        AuditLog::record('assignment.created', $assignment, ['qualification_id' => $qualification->id]);

        return redirect()->route('qualifications.assignments.show', [$qualification, $assignment])
            ->with('success', "Assignment \"{$assignment->name}\" created.");
    }

    public function show(Qualification $qualification, Assignment $assignment)
    {
        abort_if($assignment->qualification_id !== $qualification->id, 403);
        $assignment->load('qualificationModules', 'questions');
        return view('assignments.show', compact('qualification', 'assignment'));
    }

    public function edit(Qualification $qualification, Assignment $assignment)
    {
        abort_if($assignment->qualification_id !== $qualification->id, 403);
        return view('assignments.edit', compact('qualification', 'assignment'));
    }

    public function update(Request $request, Qualification $qualification, Assignment $assignment)
    {
        abort_if($assignment->qualification_id !== $qualification->id, 403);

        $data = $request->validate([
            'name'            => ['required', 'string', 'max:255'],
            'description'     => ['nullable', 'string'],
            'type'            => ['required', 'in:formative,summative'],
            'total_marks'     => ['nullable', 'integer', 'min:1'],
            'memo_type'       => ['required', 'in:text,pdf,questions,rubric'],
            'memo_text'       => ['nullable', 'string'],
            'memo_file'       => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
            'rubric_json'     => ['nullable', 'string'],
            'ai_instructions' => ['nullable', 'string', 'max:3000'],
        ]);

        $memoPath = $assignment->memo_path;
        if ($request->hasFile('memo_file') && $data['memo_type'] === 'pdf') {
            if ($memoPath && Storage::disk('local')->exists($memoPath)) {
                Storage::disk('local')->delete($memoPath);
            }
            $filename = Str::uuid() . '_' . Str::slug($data['name']) . '.pdf';
            $memoPath = $request->file('memo_file')->storeAs('memos', $filename, 'local');
        }

        $rubricJson = $assignment->rubric_json;
        if ($data['memo_type'] === 'rubric' && ! empty($data['rubric_json'])) {
            $decoded = json_decode($data['rubric_json'], true);
            $rubricJson = is_array($decoded) ? $decoded : $rubricJson;
        } elseif ($data['memo_type'] !== 'rubric') {
            $rubricJson = null;
        }

        $assignment->update([
            'name'            => $data['name'],
            'description'     => $data['description'] ?? null,
            'type'            => $data['type'],
            'total_marks'     => $data['total_marks'] ?? null,
            'memo_type'       => $data['memo_type'],
            'memo_text'       => $data['memo_type'] === 'text' ? ($data['memo_text'] ?? null) : null,
            'memo_path'       => $data['memo_type'] === 'pdf' ? $memoPath : null,
            'rubric_json'     => $rubricJson,
            'ai_instructions' => $data['ai_instructions'] ?? null,
        ]);

        AuditLog::record('assignment.updated', $assignment);

        return redirect()->route('qualifications.assignments.show', [$qualification, $assignment])
            ->with('success', 'Assignment updated.');
    }

    public function destroy(Qualification $qualification, Assignment $assignment)
    {
        abort_if($assignment->qualification_id !== $qualification->id, 403);
        $assignment->qualificationModules()->detach();
        $assignment->delete();
        AuditLog::record('assignment.deleted', $assignment);

        return redirect()->route('qualifications.assignments.index', $qualification)
            ->with('success', 'Assignment deleted.');
    }

    public function downloadMemo(Qualification $qualification, Assignment $assignment)
    {
        abort_if($assignment->qualification_id !== $qualification->id, 403);
        abort_if(!$assignment->memo_path || !Storage::disk('local')->exists($assignment->memo_path), 404);

        return Storage::disk('local')->download(
            $assignment->memo_path,
            Str::slug($assignment->name) . '_memo.pdf'
        );
    }

    /**
     * AJAX: Import rubric definition from Moodle for this assignment.
     * Returns JSON { ok: bool, criteria: [...], error: string|null }
     */
    public function importRubricFromMoodle(Request $request, Qualification $qualification, Assignment $assignment)
    {
        abort_if($assignment->qualification_id !== $qualification->id, 403);

        if (! $assignment->lms_connection_id || ! $assignment->lms_cmid) {
            return response()->json([
                'ok'    => false,
                'error' => 'This assignment is not linked to Moodle or has no course module ID (cmid). Run a Moodle sync first.',
            ]);
        }

        $connection = LmsConnection::find($assignment->lms_connection_id);
        if (! $connection) {
            return response()->json(['ok' => false, 'error' => 'Moodle connection not found.']);
        }

        try {
            $service = new MoodleService($connection);
            $result  = $service->getGradingDefinition((int) $assignment->lms_cmid);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()]);
        }

        if (! $result['ok']) {
            return response()->json(['ok' => false, 'error' => $result['error']]);
        }

        return response()->json(['ok' => true, 'criteria' => $result['criteria']]);
    }
}
