<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE assignments DROP CONSTRAINT IF EXISTS assignments_memo_type_check');
        DB::statement("ALTER TABLE assignments ADD CONSTRAINT assignments_memo_type_check CHECK (memo_type IN ('text','pdf','questions','rubric'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE assignments DROP CONSTRAINT IF EXISTS assignments_memo_type_check');
        DB::statement("ALTER TABLE assignments ADD CONSTRAINT assignments_memo_type_check CHECK (memo_type IN ('text','pdf','questions'))");
    }
};
