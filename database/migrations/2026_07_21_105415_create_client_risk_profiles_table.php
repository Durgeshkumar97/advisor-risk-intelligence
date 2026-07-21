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
        Schema::create('client_risk_profiles', function (Blueprint $table) {
            $table->id();

            // One profile per portfolio (a portfolio is one advisor's one
            // client in this codebase's data model) — unique enforces 1:1.
            // cascadeOnDelete because this is operational data scoped to the
            // portfolio's lifecycle, not an audit trail (unlike admin_logs'
            // nullOnDelete convention).
            //
            // Safe only because Portfolio is currently hard-delete. If SoftDeletes
            // is ever added to Portfolio, this cascade will silently stop firing
            // (soft-delete never issues a real SQL DELETE) and client_risk_profiles
            // rows will orphan against the unique portfolio_id constraint — add a
            // deleting model event to clean up manually if that happens.
            $table->foreignId('portfolio_id')->unique()->constrained()->cascadeOnDelete();

            // Raw questionnaire answers (1-based option index per question) —
            // stored alongside the computed score so the scoring formula can
            // change later without losing the underlying answers.
            $table->unsignedTinyInteger('time_horizon');
            $table->unsignedTinyInteger('income_stability');
            $table->unsignedTinyInteger('drawdown_reaction');
            $table->unsignedTinyInteger('emergency_savings');
            $table->unsignedTinyInteger('primary_goal');

            // Computed 0-100 capacity score, same scale as PortfolioRiskCalculator's
            // score, so the two are directly comparable.
            $table->decimal('capacity_score', 5, 2);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_risk_profiles');
    }
};
