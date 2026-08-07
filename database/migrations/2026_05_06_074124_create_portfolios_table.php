<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolios', function (Blueprint $table) {

            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name');

            $table->decimal('total_value', 15, 2)
                ->default(0);

            $table->decimal('risk_score', 5, 2)
                ->default(0);

            $table->enum('risk_level', [
                'LOW',
                'MEDIUM',
                'HIGH',
            ])->default('LOW');

            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolios');
    }
};
