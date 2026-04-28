<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Submission extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'assignment_id',
        'learner_id',
        'user_id',
        'lms_connection_id',
        'lms_submission_id',
        'original_filename',
        'file_path',
        'status',
        'queued_at',
        'marked_at',
        'signed_off_at',
        'emailed_at',
        'lms_pushed_at',
    ];

    protected $casts = [
        'queued_at'     => 'datetime',
        'marked_at'     => 'datetime',
        'signed_off_at' => 'datetime',
        'emailed_at'    => 'datetime',
        'lms_pushed_at' => 'datetime',
    ];

    public function assignment()
    {
        return $this->belongsTo(Assignment::class);
    }

    public function learner()
    {
        return $this->belongsTo(Learner::class);
    }

    public function assessor()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function markingResult()
    {
        return $this->hasOne(MarkingResult::class);
    }

    public function aiUsage()
    {
        return $this->hasMany(AiUsage::class);
    }

    public function lmsConnection()
    {
        return $this->belongsTo(\App\Models\LmsConnection::class, 'lms_connection_id');
    }

    public function isFromMoodle(): bool
    {
        return $this->lms_connection_id !== null && $this->lms_submission_id !== null;
    }

    public function statusBadge(): array
    {
        return match ($this->status) {
            'uploaded'        => ['label' => 'Uploaded',        'class' => 'bg-gray-100 text-gray-700'],
            'queued'          => ['label' => 'Queued',          'class' => 'bg-yellow-100 text-yellow-700'],
            'marking'         => ['label' => 'Marking...',      'class' => 'bg-blue-100 text-blue-700'],
            'review_required' => ['label' => 'Review Required', 'class' => 'bg-orange-100 text-orange-700'],
            'signed_off'      => ['label' => 'Signed Off',      'class' => 'bg-green-100 text-green-700'],
            'emailed'         => ['label' => 'Emailed',         'class' => 'bg-purple-100 text-purple-700'],
            default           => ['label' => ucfirst($this->status), 'class' => 'bg-gray-100 text-gray-600'],
        };
    }
}
