<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarkingResult extends Model
{
    protected $fillable = [
        'submission_id',
        'user_id',
        'ai_recommendation',
        'confidence',
        'questions_json',
        'moderation_notes',
        'mock_mode',
        'assessor_override',
        'final_verdict',
        'assessor_name',
        'annotated_pdf_path',
        'cover_pdf_path',
        'pdf_hash',
        'signed_off_at',
    ];

    protected $casts = [
        'questions_json'   => 'array',
        'mock_mode'        => 'boolean',
        'assessor_override' => 'boolean',
        'signed_off_at'    => 'datetime',
    ];

    public function submission()
    {
        return $this->belongsTo(Submission::class);
    }

    public function assessor()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
