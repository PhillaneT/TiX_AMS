<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingAccount extends Model
{
    protected $fillable = [
        'name',
        'type',
        'owner_user_id',
        'plan_code',
        'status',
        'balance',
        'trial_credits_granted_at',
        'billing_email',
        'vat_number',
        'billing_address',
    ];

    protected $casts = [
        'trial_credits_granted_at' => 'datetime',
        'balance' => 'integer',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(CreditLedger::class)->latest('created_at');
    }

    public function plan(): ?Plan
    {
        return $this->plan_code
            ? Plan::where('code', $this->plan_code)->first()
            : null;
    }
}
