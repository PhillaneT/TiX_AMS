<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['solo', 'team'])->default('solo');
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('plan_code')->nullable();
            $table->enum('status', ['trialing', 'active', 'past_due', 'cancelled'])->default('trialing');
            $table->integer('balance')->default(0)->comment('current credit balance');
            $table->timestamp('trial_credits_granted_at')->nullable();
            $table->string('billing_email')->nullable();
            $table->string('vat_number')->nullable();
            $table->text('billing_address')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_accounts');
    }
};
