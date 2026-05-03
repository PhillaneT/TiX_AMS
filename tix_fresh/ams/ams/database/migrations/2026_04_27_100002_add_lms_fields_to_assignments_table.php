<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->unsignedBigInteger('lms_connection_id')->nullable()->after('id');
            $table->string('lms_assignment_id')->nullable()->after('lms_connection_id');
            $table->foreign('lms_connection_id')->references('id')->on('lms_connections')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropForeign(['lms_connection_id']);
            $table->dropColumn(['lms_connection_id', 'lms_assignment_id']);
        });
    }
};
