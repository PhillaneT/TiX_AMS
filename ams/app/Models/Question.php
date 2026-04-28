<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    use HasFactory;

    protected $fillable = [
        'assignment_id',
        'order',
        'label',
        'question_text',
        'expected_answer',
        'ai_grading_notes',
        'marks',
    ];

    public function assignment()
    {
        return $this->belongsTo(Assignment::class);
    }
}
