<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Learner extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'cohort_id',
        'first_name',
        'last_name',
        'email',
        'external_ref',
        'status',
    ];

    public function cohort()
    {
        return $this->belongsTo(Cohort::class);
    }

    public function submissions()
    {
        return $this->hasMany(Submission::class);
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
