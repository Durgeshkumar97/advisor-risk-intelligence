<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->string('order_id')->unique();
            $table->string('payment_id')->nullable()->unique();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('email');
            $table->string('phone');

            $table->string('plan');
            $table->integer('amount');

            $table->enum('status', ['created', 'paid', 'failed'])->default('created');

            $table->json('meta')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
