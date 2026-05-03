<?php

namespace Database\Seeders;

use App\Models\BillingAccount;
use App\Models\User;
use App\Services\Billing\BillingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AssessorSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'assessor@ajananova.co.za'],
            [
                'name'              => 'AjanaNova Assessor',
                'password'          => Hash::make('ajananova2025'),
                'email_verified_at' => now(),
                'is_admin'          => true,
            ]
        );

        // Make sure the existing user is admin even if we re-seed.
        if (! $user->is_admin) {
            $user->update(['is_admin' => true]);
        }

        // Back-fill a billing account if this user doesn't have one yet.
        if (! $user->billing_account_id) {
            app(BillingService::class)->createSoloAccountForUser($user, trialCredits: 3);
        }
    }
}
