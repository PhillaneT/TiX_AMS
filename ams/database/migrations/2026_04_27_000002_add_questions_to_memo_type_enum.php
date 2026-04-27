<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE assignments DROP CONSTRAINT IF EXISTS assignments_memo_type_check");
        DB::statement("ALTER TABLE assignments ADD CONSTRAINT assignments_memo_type_check CHECK (memo_type IN ('text', 'pdf', 'questions'))");
        DB::statement("ALTER TABLE assignments ALTER COLUMN memo_type SET DEFAULT 'questions'");
    }

    public function down(): void
    {
        DB::statement("UPDATE assignments SET memo_type = 'text' WHERE memo_type = 'questions'");
        DB::statement("ALTER TABLE assignments DROP CONSTRAINT IF EXISTS assignments_memo_type_check");
        DB::statement("ALTER TABLE assignments ADD CONSTRAINT assignments_memo_type_check CHECK (memo_type IN ('text', 'pdf'))");
        DB::statement("ALTER TABLE assignments ALTER COLUMN memo_type SET DEFAULT 'text'");
    }
};
