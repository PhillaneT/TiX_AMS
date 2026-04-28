<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ✅ SQLite cannot alter constraints or enums
        if (DB::getDriverName() === 'sqlite') {
            // Optional: adjust default or data manually if needed
            DB::statement("
                UPDATE assignments
                SET memo_type = 'questions'
                WHERE memo_type IS NULL
            ");

            return;
        }

        // ✅ PostgreSQL / MySQL
        DB::statement("
            ALTER TABLE assignments
            DROP CONSTRAINT IF EXISTS assignments_memo_type_check
        ");

        DB::statement("
            ALTER TABLE assignments
            ADD CONSTRAINT assignments_memo_type_check
            CHECK (memo_type IN ('text', 'pdf', 'questions'))
        ");

        DB::statement("
            ALTER TABLE assignments
            ALTER COLUMN memo_type SET DEFAULT 'questions'
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement("
                UPDATE assignments
                SET memo_type = 'text'
                WHERE memo_type = 'questions'
            ");

            return;
        }

        DB::statement("
            UPDATE assignments
            SET memo_type = 'text'
            WHERE memo_type = 'questions'
        ");

        DB::statement("
            ALTER TABLE assignments
            DROP CONSTRAINT IF EXISTS assignments_memo_type_check
        ");

        DB::statement("
            ALTER TABLE assignments
            ADD CONSTRAINT assignments_memo_type_check
            CHECK (memo_type IN ('text', 'pdf'))
        ");

        DB::statement("
            ALTER TABLE assignments
            ALTER COLUMN memo_type SET DEFAULT 'text'
        ");
    }
};
