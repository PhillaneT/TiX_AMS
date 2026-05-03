<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\BillingAccount;
use App\Services\Billing\BillingService;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function __construct(private BillingService $billing) {}

    public function accounts()
    {
        $accounts = BillingAccount::with('owner')
            ->orderByDesc('created_at')
            ->get();

        return view('admin.accounts', compact('accounts'));
    }

    public function grantCredits(Request $request, BillingAccount $account)
    {
        $data = $request->validate([
            'credits' => ['required', 'integer', 'min:1', 'max:1000'],
            'note'    => ['nullable', 'string', 'max:255'],
        ]);

        $this->billing->grant(
            $account,
            $data['credits'],
            BillingService::REASON_ADMIN,
            null,
            $request->user(),
        );

        AuditLog::record('billing.admin_grant', $account, [
            'credits' => $data['credits'],
            'note'    => $data['note'] ?? null,
        ]);

        return back()->with('success',
            "Granted {$data['credits']} credit(s) to {$account->name}.");
    }
}
