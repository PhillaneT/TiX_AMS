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
    ];

    public function cohorts()
    {
        return $this->hasMany(Cohort::class);
    }

    public function assignments()
    {
        return $this->hasMany(Assignment::class);
    }

    public function trackLabel(): string
    {
        return $this->track === 'legacy_seta' ? 'Legacy SETA' : 'QCTO Occupational';
    }
}
