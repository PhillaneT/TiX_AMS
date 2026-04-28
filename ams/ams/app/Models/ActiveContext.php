<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActiveContext extends Model
{
    protected $fillable = [
        'user_id',
        'qualification_id',
        'cohort_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function qualification()
    {
        return $this->belongsTo(Qualification::class)->withTrashed();
    }

    public function cohort()
    {
        return $this->belongsTo(Cohort::class)->withTrashed();
    }
}
