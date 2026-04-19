<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
    Schema::create('leads', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('phone',20);
        $table->string('email')->nullable();
        $table->string('selected_plan')->nullable();
        $table->string('source')->default('landing_page');
        $table->string('status')->default('new');
        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
