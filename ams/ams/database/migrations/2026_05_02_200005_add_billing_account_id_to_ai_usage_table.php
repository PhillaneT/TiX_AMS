<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_usage', function (Blueprint $table) {
            $table->foreignId('billing_account_id')
                ->nullable()
                ->after('user_id')
                ->constrained('billing_accounts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ai_usage', function (Blueprint $table) {
            $table->dropConstrainedForeignId('billing_account_id');
        });
    }
};
