<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_intakes', function (Blueprint $table) {
            $table->id();

            // BASIC USER
            $table->uuid('submission_uuid')->unique();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('whatsapp')->nullable();

            // BUSINESS (TRIAL + PLAN)
            $table->string('plan')->default('pro');
            $table->timestamp('trial_started_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->string('status')->default('trial'); // trial / active / expired

            // INTAKE DATA (YOUR ORIGINAL)
            $table->string('portfolio_value')->nullable();
            $table->string('objective')->nullable();
            $table->string('horizon')->nullable();
            $table->string('archetype')->nullable();
            $table->string('concern')->nullable();
            $table->text('notes')->nullable();

            // AI SYSTEM
            $table->string('lead_score')->default('Normal');
            $table->string('ai_status')->default('pending');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_intakes');
    }
}; 
