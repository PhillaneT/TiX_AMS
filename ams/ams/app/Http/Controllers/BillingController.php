<?php

namespace App\Http\Controllers;

use App\Models\BillingAccount;
use App\Models\CreditLedger;
use App\Models\Plan;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function index(Request $request)
    {
        $account = $this->resolveAccount($request);

        $plan = $account->plan_code
            ? Plan::where('code', $account->plan_code)->first()
            : null;

        $ledger = CreditLedger::where('billing_account_id', $account->id)
            ->latest('created_at')
            ->limit(20)
            ->get();

        return view('billing.index', compact('account', 'plan', 'ledger'));
    }

    public function topup(Request $request)
    {
        $account = $this->resolveAccount($request);
        return view('billing.topup', compact('account'));
    }

    private function resolveAccount(Request $request): BillingAccount
    {
        $user = $request->user();
        abort_unless($user && $user->billing_account_id, 403,
            'No billing account is linked to this user.');

        return BillingAccount::findOrFail($user->billing_account_id);
    }
}
