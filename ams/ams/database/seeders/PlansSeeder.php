<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlansSeeder extends Seeder
{
    public function run(): void
    {
        Plan::updateOrCreate(
            ['code' => 'portal_only'],
            [
                'name'            => 'Portal Only',
                'kind'            => 'subscription',
                'price_cents'     => 19900,
                'monthly_credits' => 10,
                'topup_credits'   => 0,
                'is_active'       => true,
            ]
        );

        Plan::updateOrCreate(
            ['code' => 'topup_25'],
            [
                'name'            => 'Top-up Bundle (25 marks)',
                'kind'            => 'topup',
                'price_cents'     => 9900,
                'monthly_credits' => 0,
                'topup_credits'   => 25,
                'is_active'       => true,
            ]
        );
    }
}
