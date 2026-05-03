<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('learner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->comment('assessor who uploaded');
            $table->string('original_filename');
            $table->string('file_path');
            $table->enum('status', [
                'uploaded',
                'queued',
                'marking',
                'review_required',
                'signed_off',
                'emailed',
            ])->default('uploaded');
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('marked_at')->nullable();
            $table->timestamp('signed_off_at')->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['assignment_id', 'learner_id']);
            $table->index(['status', 'assignment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};
