<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = [
        'code',
        'name',
        'kind',
        'price_cents',
        'monthly_credits',
        'topup_credits',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function priceRand(): string
    {
        return 'R' . number_format($this->price_cents / 100, 2);
    }
}
