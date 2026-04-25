<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Qualification extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'saqa_id',
        'nqf_level',
        'track',
        'credits',
        'seta',
        'seta_registration_number',
        'status',
        'notes',
        'saqa_raw_data',
        'saqa_fetched_at',
    ];

    protected $casts = [
        'saqa_raw_data'  => 'array',
        'saqa_fetched_at' => 'datetime',
    ];

    public function cohorts()
    {
        return $this->hasMany(Cohort::class);
    }

    public function assignments()
    {
        return $this->hasMany(Assignment::class);
    }

    public function modules()
    {
        return $this->hasMany(QualificationModule::class)->orderBy('sortorder');
    }

    public function trackLabel(): string
    {
        return $this->track === 'legacy_seta' ? 'Legacy SETA' : 'QCTO Occupational';
    }
}
