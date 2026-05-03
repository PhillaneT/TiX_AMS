<?php

namespace App\Services\Billing;

use App\Exceptions\InsufficientCreditsException;
use App\Models\BillingAccount;
use App\Models\CreditLedger;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class BillingService
{
    public const REASON_TRIAL = 'trial';
    public const REASON_SUBSCRIPTION = 'subscription_grant';
    public const REASON_TOPUP = 'topup';
    public const REASON_AI_MARK = 'ai_mark';
    public const REASON_ADMIN = 'admin_adjust';
    public const REASON_REFUND = 'refund';

    /**
     * Add credits to an account. Always succeeds (no negative grants — use deduct()).
     */
    public function grant(
        BillingAccount $account,
        int $credits,
        string $reason,
        ?Model $reference = null,
        ?User $byUser = null,
    ): CreditLedger {
        if ($credits <= 0) {
            throw new \InvalidArgumentException("grant() requires a positive credit amount, got {$credits}.");
        }

        return DB::transaction(function () use ($account, $credits, $reason, $reference, $byUser) {
            /** @var BillingAccount $locked */
            $locked = BillingAccount::query()->lockForUpdate()->findOrFail($account->id);

            $newBalance = $locked->balance + $credits;
            $locked->update(['balance' => $newBalance]);

            return CreditLedger::create([
                'billing_account_id' => $locked->id,
                'delta'              => $credits,
                'reason'             => $reason,
                'reference_type'     => $reference ? class_basename($reference) : null,
                'reference_id'       => $reference?->getKey(),
                'balance_after'      => $newBalance,
                'created_by_user_id' => $byUser?->id ?? auth()->id(),
            ]);
        });
    }

    /**
     * Deduct credits. Throws InsufficientCreditsException when balance < credits.
     */
    public function deduct(
        BillingAccount $account,
        int $credits,
        string $reason,
        ?Model $reference = null,
    ): CreditLedger {
        if ($credits <= 0) {
            throw new \InvalidArgumentException("deduct() requires a positive credit amount, got {$credits}.");
        }

        return DB::transaction(function () use ($account, $credits, $reason, $reference) {
            /** @var BillingAccount $locked */
            $locked = BillingAccount::query()->lockForUpdate()->findOrFail($account->id);

            if ($locked->balance < $credits) {
                throw new InsufficientCreditsException($locked->balance, $credits);
            }

            $newBalance = $locked->balance - $credits;
            $locked->update(['balance' => $newBalance]);

            return CreditLedger::create([
                'billing_account_id' => $locked->id,
                'delta'              => -$credits,
                'reason'             => $reason,
                'reference_type'     => $reference ? class_basename($reference) : null,
                'reference_id'       => $reference?->getKey(),
                'balance_after'      => $newBalance,
                'created_by_user_id' => auth()->id(),
            ]);
        });
    }

    /**
     * Spin up a fresh solo billing account for a brand-new user, grant trial credits.
     */
    public function createSoloAccountForUser(User $user, int $trialCredits = 3): BillingAccount
    {
        $account = BillingAccount::create([
            'name'           => $user->name,
            'type'           => 'solo',
            'owner_user_id'  => $user->id,
            'plan_code'      => null,
            'status'         => 'trialing',
            'balance'        => 0,
            'billing_email'  => $user->email,
            'trial_credits_granted_at' => now(),
        ]);

        $user->update(['billing_account_id' => $account->id]);

        if ($trialCredits > 0) {
            $this->grant($account, $trialCredits, self::REASON_TRIAL, null, $user);
        }

        return $account->fresh();
    }
}
