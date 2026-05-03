<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->timestamp('lms_grade_pushed_at')->nullable()->after('lms_pushed_at');
            $table->timestamp('lms_feedback_text_pushed_at')->nullable()->after('lms_grade_pushed_at');
            $table->timestamp('lms_feedback_file_pushed_at')->nullable()->after('lms_feedback_text_pushed_at');
            $table->text('lms_last_push_error')->nullable()->after('lms_feedback_file_pushed_at');
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->string('lms_course_id')->nullable()->after('lms_cmid');
            $table->json('lms_preflight_json')->nullable()->after('lms_course_id');
            $table->timestamp('lms_preflight_checked_at')->nullable()->after('lms_preflight_json');
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn([
                'lms_grade_pushed_at',
                'lms_feedback_text_pushed_at',
                'lms_feedback_file_pushed_at',
                'lms_last_push_error',
            ]);
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn([
                'lms_course_id',
                'lms_preflight_json',
                'lms_preflight_checked_at',
            ]);
        });
    }
};
