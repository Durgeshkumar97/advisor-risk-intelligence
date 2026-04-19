<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();

            $table->string('name'); // Starter / Pro / Team
            $table->string('slug')->unique();

            $table->decimal('price', 10, 2);

            $table->integer('portfolio_limit')->default(1);

            $table->boolean('daily_reports')->default(true);
            $table->boolean('client_script')->default(true);
            $table->boolean('whatsapp_delivery')->default(true);
            $table->boolean('branded_pdf')->default(false);
            $table->boolean('priority_support')->default(false);
            $table->boolean('multi_advisor')->default(false);

            $table->integer('trial_days')->default(7);

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};