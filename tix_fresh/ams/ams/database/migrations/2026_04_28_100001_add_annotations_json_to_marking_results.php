<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marking_results', function (Blueprint $table) {
            $table->json('annotations_json')->nullable()->after('questions_json');
        });
    }

    public function down(): void
    {
        Schema::table('marking_results', function (Blueprint $table) {
            $table->dropColumn('annotations_json');
        });
    }
};
