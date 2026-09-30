<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A holding's cost basis can be genuinely unknown (a depository statement has
 * none; a file may omit the column). Stored as 0 it reads as +100% profit and
 * hides real losses, so unknown must be representable as NULL.
 *
 * NO BACKFILL. Existing rows keep invested_value = 0 / profit_loss as stored:
 * "genuinely zero" cannot be told apart from "was unknown" after the fact, and
 * converting them would silently re-score every existing portfolio. Only new
 * uploads write NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portfolio_assets', function (Blueprint $table) {
            $table->decimal('invested_value', 15, 2)->nullable()->default(null)->change();
            $table->decimal('profit_loss', 15, 2)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        DB::table('portfolio_assets')->whereNull('invested_value')->update(['invested_value' => 0]);
        DB::table('portfolio_assets')->whereNull('profit_loss')->update(['profit_loss' => 0]);

        Schema::table('portfolio_assets', function (Blueprint $table) {
            $table->decimal('invested_value', 15, 2)->default(0)->nullable(false)->change();
            $table->decimal('profit_loss', 15, 2)->default(0)->nullable(false)->change();
        });
    }
};
