<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marking_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->comment('assessor who signed off');
            $table->enum('ai_recommendation', [
                'COMPETENT',
                'NOT_YET_COMPETENT',
                'ASSESSOR_REVIEW_REQUIRED',
            ])->nullable();
            $table->enum('confidence', ['HIGH', 'MEDIUM', 'LOW'])->nullable();
            $table->json('questions_json')->nullable();
            $table->text('moderation_notes')->nullable();
            $table->boolean('mock_mode')->default(true);
            $table->boolean('assessor_override')->default(false);
            $table->enum('final_verdict', ['COMPETENT', 'NOT_YET_COMPETENT'])->nullable();
            $table->string('assessor_name')->nullable();
            $table->string('annotated_pdf_path')->nullable();
            $table->string('cover_pdf_path')->nullable();
            $table->string('pdf_hash', 64)->nullable()->comment('SHA-256 of final locked PDF');
            $table->timestamp('signed_off_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marking_results');
    }
};
