<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marking_results', function (Blueprint $table) {
            $table->string('etqa_registration')->nullable()->after('assessor_name');
            $table->string('assessment_provider')->nullable()->after('etqa_registration');
        });
    }

    public function down(): void
    {
        Schema::table('marking_results', function (Blueprint $table) {
            $table->dropColumn(['etqa_registration', 'assessment_provider']);
        });
    }
};
