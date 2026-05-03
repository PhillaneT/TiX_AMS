<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cohort extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'qualification_id',
        'name',
        'year',
        'start_date',
        'end_date',
        'venue',
        'facilitator',
        'status',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    public function qualification()
    {
        return $this->belongsTo(Qualification::class);
    }

    public function learners()
    {
        return $this->hasMany(Learner::class);
    }

    public function activeLearners()
    {
        return $this->hasMany(Learner::class)->where('status', 'active');
    }
}
