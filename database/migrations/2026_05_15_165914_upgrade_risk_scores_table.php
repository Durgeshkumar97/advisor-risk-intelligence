<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
    |--------------------------------------------------------------------------
    | UP
    |--------------------------------------------------------------------------
    */

    public function up(): void
    {
        Schema::table('risk_scores', function (Blueprint $table) {

            /*
            |--------------------------------------------------------------------------
            | RELATIONS
            |--------------------------------------------------------------------------
            */

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('portfolio_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | RISK METRICS
            |--------------------------------------------------------------------------
            */

            $table->decimal('score', 8, 2)
                ->default(0);

            $table->decimal('volatility', 8, 2)
                ->nullable();

            $table->decimal('drawdown', 8, 2)
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | TIMESTAMP
            |--------------------------------------------------------------------------
            */

            $table->timestamp('generated_at')
                ->nullable();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | DOWN
    |--------------------------------------------------------------------------
    */

    public function down(): void
    {
        Schema::table('risk_scores', function (Blueprint $table) {

            /*
            |--------------------------------------------------------------------------
            | DROP FOREIGN KEYS
            |--------------------------------------------------------------------------
            */

            $table->dropForeign(['user_id']);

            $table->dropForeign(['portfolio_id']);

            /*
            |--------------------------------------------------------------------------
            | DROP COLUMNS
            |--------------------------------------------------------------------------
            */

            $table->dropColumn([
                'user_id',
                'portfolio_id',
                'score',
                'volatility',
                'drawdown',
                'generated_at',
            ]);
        });
    }
};
