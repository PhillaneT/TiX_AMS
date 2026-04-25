<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiUsage extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'ai_usage';

    protected $fillable = [
        'submission_id',
        'user_id',
        'tokens_input',
        'tokens_output',
        'credits_charged',
        'mock_mode',
        'api_response_id',
        'status',
        'error_message',
    ];

    protected $casts = [
        'mock_mode' => 'boolean',
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
