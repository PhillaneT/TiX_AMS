<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class QualificationModule extends Model
{
    protected $fillable = [
        'qualification_id',
        'module_type',
        'module_code',
        'title',
        'nqf_level',
        'credits',
        'sortorder',
    ];

    protected $casts = [
        'credits'   => 'integer',
        'sortorder' => 'integer',
    ];

    public function qualification(): BelongsTo
    {
        return $this->belongsTo(Qualification::class);
    }

    public function assignments(): BelongsToMany
    {
        return $this->belongsToMany(Assignment::class, 'assignment_modules');
    }

    public function typeBadgeColor(): string
    {
        return match (strtoupper($this->module_type)) {
            'KM'  => 'blue',
            'PM'  => 'green',
            'WM'  => 'orange',
            'US'  => 'purple',
            default => 'gray',
        };
    }
}
