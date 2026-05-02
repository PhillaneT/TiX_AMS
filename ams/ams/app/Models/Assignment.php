<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Assignment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'qualification_id',
        'lms_connection_id',
        'lms_assignment_id',
        'lms_cmid',
        'name',
        'description',
        'type',
        'total_marks',
        'memo_type',
        'memo_text',
        'memo_path',
        'ai_instructions',
    ];

    public function qualification()
    {
        return $this->belongsTo(Qualification::class);
    }

    public function submissions()
    {
        return $this->hasMany(Submission::class);
    }

    public function qualificationModules()
    {
        return $this->belongsToMany(QualificationModule::class, 'assignment_modules');
    }

    public function questions()
    {
        return $this->hasMany(Question::class)->orderBy('order')->orderBy('id');
    }
}
