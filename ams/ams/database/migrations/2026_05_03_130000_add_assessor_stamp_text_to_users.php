<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Static per-assessor content for the auto-generated rubber stamp.
            $table->string('stamp_org_top', 60)->nullable()->after('stamp_path');
            $table->string('stamp_org_bottom', 60)->nullable()->after('stamp_org_top');
            $table->string('stamp_role', 40)->nullable()->after('stamp_org_bottom');
            $table->string('stamp_holder_name', 60)->nullable()->after('stamp_role');
            // When true, every PDF uses the generated stamp; the uploaded image
            // (if any) is kept as a fallback.
            $table->boolean('stamp_use_generated')->default(false)->after('stamp_holder_name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'stamp_org_top', 'stamp_org_bottom', 'stamp_role',
                'stamp_holder_name', 'stamp_use_generated',
            ]);
        });
    }
};
