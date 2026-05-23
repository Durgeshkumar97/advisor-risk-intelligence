<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('risk_scores', function (Blueprint $table) {

            // Most common query: WHERE user_id = ? ORDER BY created_at DESC LIMIT 1
            $table->index(['user_id', 'created_at'], 'risk_scores_user_id_created_at_index');

            // Secondary: per-portfolio scores
            $table->index('portfolio_id', 'risk_scores_portfolio_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('risk_scores', function (Blueprint $table) {

            $table->dropIndex('risk_scores_user_id_created_at_index');
            $table->dropIndex('risk_scores_portfolio_id_index');
        });
    }
};
