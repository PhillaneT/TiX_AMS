<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cohort_id')->constrained()->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('external_ref')->nullable();
            $table->enum('status', ['active', 'withdrawn', 'completed'])->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['cohort_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learners');
    }
};
