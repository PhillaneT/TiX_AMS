<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qualification_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qualification_id')->constrained()->cascadeOnDelete();
            $table->string('module_type', 10)->default('KM');
            $table->string('module_code', 100);
            $table->text('title');
            $table->string('nqf_level', 10)->default('');
            $table->unsignedInteger('credits')->default(0);
            $table->unsignedInteger('sortorder')->default(0);
            $table->timestamps();

            $table->index(['qualification_id', 'sortorder']);
        });

        Schema::table('qualifications', function (Blueprint $table) {
            $table->json('saqa_raw_data')->nullable()->after('notes');
            $table->timestamp('saqa_fetched_at')->nullable()->after('saqa_raw_data');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qualification_modules');
        Schema::table('qualifications', function (Blueprint $table) {
            $table->dropColumn(['saqa_raw_data', 'saqa_fetched_at']);
        });
    }
};
