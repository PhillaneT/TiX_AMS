<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditLedger extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'credit_ledger';

    protected $fillable = [
        'billing_account_id',
        'delta',
        'reason',
        'reference_type',
        'reference_id',
        'balance_after',
        'created_by_user_id',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(BillingAccount::class, 'billing_account_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
