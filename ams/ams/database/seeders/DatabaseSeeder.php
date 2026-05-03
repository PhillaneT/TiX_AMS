<?php

namespace Database\Seeders;

use App\Models\BillingAccount;
use App\Models\User;
use App\Services\Billing\BillingService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Plans must exist before any user/account work.
        $this->call(PlansSeeder::class);

        // Then the seeded assessor (creates a solo account + trial credits).
        $this->call(AssessorSeeder::class);

        // Safety net: any other pre-existing user without a billing account
        // gets one auto-created with 3 trial credits.
        $service = app(BillingService::class);
        User::query()
            ->whereNull('billing_account_id')
            ->get()
            ->each(fn (User $u) => $service->createSoloAccountForUser($u, 3));
    }
}
