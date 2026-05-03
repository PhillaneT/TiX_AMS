<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AssessorSeeder extends Seeder
{
    public function run(): void
    {
        \App\Models\User::firstOrCreate(
            ['email' => 'assessor@ajananova.co.za'],
            [
                'name'              => 'AjanaNova Assessor',
                'email'             => 'assessor@ajananova.co.za',
                'password'          => Hash::make('ajananova2025'),
                'email_verified_at' => now(),
            ]
        );
    }
}
