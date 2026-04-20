<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();

            $table->string('status')->default('trial');
            // trial / active / expired / cancelled / past_due

            $table->timestamp('trial_started_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->timestamp('renewal_at')->nullable();

            $table->string('provider')->nullable(); // razorpay
            $table->string('provider_subscription_id')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('renewal_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};