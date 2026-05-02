<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::create('subscriptions', function (Blueprint $table) {
    $table->id();

    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('plan_id')->constrained()->cascadeOnDelete();

    $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();

    $table->enum('status', [
        'trial','active','expired','cancelled','past_due'
    ])->default('trial');

    $table->timestamp('trial_started_at')->nullable();
    $table->timestamp('trial_ends_at')->nullable();

    $table->timestamp('starts_at')->nullable();
    $table->timestamp('ends_at')->nullable();

    $table->timestamp('renewal_at')->nullable();

    $table->string('provider')->default('razorpay');
    $table->string('provider_subscription_id')->nullable()->unique();

    $table->timestamps();

    $table->index('status');
    $table->index('renewal_at');
});