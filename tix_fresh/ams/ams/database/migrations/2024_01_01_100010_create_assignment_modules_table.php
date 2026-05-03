<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignment_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('qualification_module_id')
                ->constrained('qualification_modules')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['assignment_id', 'qualification_module_id'], 'asgn_mod_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_modules');
    }
};
