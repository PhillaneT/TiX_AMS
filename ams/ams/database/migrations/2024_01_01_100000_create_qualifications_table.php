<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qualifications', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('saqa_id')->nullable();
            $table->unsignedTinyInteger('nqf_level')->default(4);
            $table->enum('track', ['legacy_seta', 'qcto_occupational'])->default('qcto_occupational');
            $table->unsignedInteger('credits')->nullable();
            $table->string('seta')->default('MICT');
            $table->string('seta_registration_number')->nullable();
            $table->enum('status', ['active', 'archived'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qualifications');
    }
};
