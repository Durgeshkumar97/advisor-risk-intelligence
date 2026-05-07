<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolio_assets', function (Blueprint $table) {

            $table->id();

            $table->foreignId('portfolio_id')
                ->constrained()
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Asset Classification
            |--------------------------------------------------------------------------
            */

            $table->enum('asset_type', [
                'stock',
                'mutual_fund',
                'etf',
                'bond',
                'commodity',
                'foreign_stock',
                'crypto',
                'cash'
            ]);

            /*
            |--------------------------------------------------------------------------
            | Asset Identity
            |--------------------------------------------------------------------------
            */

            $table->string('symbol')->nullable();

            $table->string('name');

            $table->string('isin')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Holdings
            |--------------------------------------------------------------------------
            */

            $table->decimal('quantity', 15, 4)
                ->default(0);

            $table->decimal('buy_price', 15, 2)
                ->default(0);

            $table->decimal('current_price', 15, 2)
                ->default(0);

            $table->decimal('invested_value', 15, 2)
                ->default(0);

            $table->decimal('current_value', 15, 2)
                ->default(0);

            $table->decimal('profit_loss', 15, 2)
                ->default(0);

            /*
            |--------------------------------------------------------------------------
            | Risk Engine
            |--------------------------------------------------------------------------
            */

            $table->decimal('risk_score', 5, 2)
                ->default(0);

            $table->enum('risk_level', [
                'LOW',
                'MEDIUM',
                'HIGH'
            ])->default('LOW');

            /*
            |--------------------------------------------------------------------------
            | Metadata
            |--------------------------------------------------------------------------
            */

            $table->json('meta')->nullable();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */

            $table->index('asset_type');
            $table->index('symbol');

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_assets');
    }
};