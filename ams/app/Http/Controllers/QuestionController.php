<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\Qualification;
use App\Models\Question;
use Illuminate\Http\Request;

class QuestionController extends Controller
{
    public function create(Qualification $qualification, Assignment $assignment)
    {
        abort_if($assignment->qualification_id !== $qualification->id, 403);
        $nextOrder = $assignment->questions()->max('order') + 1;
        return view('questions.create', compact('qualification', 'assignment', 'nextOrder'));
    }

    public function store(Request $request, Qualification $qualification, Assignment $assignment)
    {
        abort_if($assignment->qualification_id !== $qualification->id, 403);

        $data = $request->validate([
            'label'             => ['nullable', 'string', 'max:50'],
            'question_text'     => ['required', 'string'],
            'expected_answer'   => ['nullable', 'string'],
            'ai_grading_notes'  => ['nullable', 'string', 'max:2000'],
            'marks'             => ['required', 'integer', 'min:1'],
            'order'             => ['nullable', 'integer', 'min:0'],
        ]);

        $question = $assignment->questions()->create([
            'label'            => $data['label'] ?? '',
            'question_text'    => $data['question_text'],
            'expected_answer'  => $data['expected_answer'] ?? null,
            'ai_grading_notes' => $data['ai_grading_notes'] ?? null,
            'marks'            => $data['marks'],
            'order'            => $data['order'] ?? ($assignment->questions()->max('order') + 1),
        ]);

        $this->syncTotalMarks($assignment);

        AuditLog::record('question.created', $question, ['assignment_id' => $assignment->id]);

        return redirect()
            ->route('qualifications.assignments.show', [$qualification, $assignment])
            ->with('success', 'Question added.');
    }

    public function edit(Qualification $qualification, Assignment $assignment, Question $question)
    {
        abort_if($assignment->qualification_id !== $qualification->id, 403);
        abort_if($question->assignment_id !== $assignment->id, 403);
        return view('questions.edit', compact('qualification', 'assignment', 'question'));
    }

    public function update(Request $request, Qualification $qualification, Assignment $assignment, Question $question)
    {
        abort_if($assignment->qualification_id !== $qualification->id, 403);
        abort_if($question->assignment_id !== $assignment->id, 403);

        $data = $request->validate([
            'label'            => ['nullable', 'string', 'max:50'],
            'question_text'    => ['required', 'string'],
            'expected_answer'  => ['nullable', 'string'],
            'ai_grading_notes' => ['nullable', 'string', 'max:2000'],
            'marks'            => ['required', 'integer', 'min:1'],
            'order'            => ['nullable', 'integer', 'min:0'],
        ]);

        $question->update([
            'label'            => $data['label'] ?? '',
            'question_text'    => $data['question_text'],
            'expected_answer'  => $data['expected_answer'] ?? null,
            'ai_grading_notes' => $data['ai_grading_notes'] ?? null,
            'marks'            => $data['marks'],
            'order'            => $data['order'] ?? $question->order,
        ]);

        $this->syncTotalMarks($assignment);

        AuditLog::record('question.updated', $question);

        return redirect()
            ->route('qualifications.assignments.show', [$qualification, $assignment])
            ->with('success', 'Question updated.');
    }

    public function destroy(Qualification $qualification, Assignment $assignment, Question $question)
    {
        abort_if($assignment->qualification_id !== $qualification->id, 403);
        abort_if($question->assignment_id !== $assignment->id, 403);

        $question->delete();
        $this->syncTotalMarks($assignment);

        AuditLog::record('question.deleted', null, [
            'assignment_id' => $assignment->id,
            'question_id'   => $question->id,
        ]);

        return redirect()
            ->route('qualifications.assignments.show', [$qualification, $assignment])
            ->with('success', 'Question deleted.');
    }

    public function reorder(Request $request, Qualification $qualification, Assignment $assignment)
    {
        abort_if($assignment->qualification_id !== $qualification->id, 403);

        $request->validate([
            'order'   => ['required', 'array'],
            'order.*' => ['integer'],
        ]);

        foreach ($request->input('order') as $position => $questionId) {
            $assignment->questions()
                ->where('id', $questionId)
                ->update(['order' => $position]);
        }

        AuditLog::record('questions.reordered', $assignment, [
            'order' => $request->input('order'),
        ]);

        return response()->json(['ok' => true]);
    }

    private function syncTotalMarks(Assignment $assignment): void
    {
        $sum = (int) $assignment->questions()->sum('marks');
        $assignment->update(['total_marks' => $sum > 0 ? $sum : null]);
    }
}
