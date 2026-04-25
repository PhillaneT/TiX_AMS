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
        'name',
        'description',
        'type',
        'total_marks',
        'memo_type',
        'memo_text',
        'memo_path',
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
}
