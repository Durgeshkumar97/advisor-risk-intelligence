<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * warning_severity and warning_text become optional.
     *
     * They were created NOT NULL on the assumption that the producer emits
     * them. It never has — FinAdvisorAI's weekly_update.py writes neither
     * column. Production evidence (2026-09-23): market_risk_snapshots is empty,
     * the default drop directory does not exist, and MARKET_RISK_CSV_PATH is
     * not overridden, so no snapshot has ever been stored and every portfolio
     * score has used the config fallback multiplier. Two optional annotations
     * were blocking the seven columns that actually drive scoring.
     */
    public function up(): void
    {
        Schema::table('market_risk_snapshots', function (Blueprint $table) {
            $table->string('warning_severity')->nullable()->change();
            $table->text('warning_text')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Rows synced while the columns were nullable carry NULLs, and NOT NULL
        // cannot be restored over them — the rollback would fail on exactly the
        // data this migration made possible. Backfill to '' first.
        DB::table('market_risk_snapshots')
            ->whereNull('warning_severity')
            ->update(['warning_severity' => '']);

        DB::table('market_risk_snapshots')
            ->whereNull('warning_text')
            ->update(['warning_text' => '']);

        Schema::table('market_risk_snapshots', function (Blueprint $table) {
            $table->string('warning_severity')->nullable(false)->change();
            $table->text('warning_text')->nullable(false)->change();
        });
    }
};
