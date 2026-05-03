<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('etqa_registration', 100)->nullable()->after('email');
            $table->string('signature_path')->nullable()->after('etqa_registration');
            $table->string('stamp_path')->nullable()->after('signature_path');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['etqa_registration', 'signature_path', 'stamp_path']);
        });
    }
};
