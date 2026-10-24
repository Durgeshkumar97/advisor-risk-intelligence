<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which AMFI scheme a fund holding was matched to, how, and how surely
 * (App\Services\Amfi\SchemeMatcher). All nullable: a stock is never matched,
 * and holdings stored before this migration keep NULL — they are not
 * re-matched or re-scored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portfolio_assets', function (Blueprint $table) {
            $table->string('scheme_code', 16)->nullable()->index();
            $table->string('match_method', 8)->nullable();          // isin | exact | fuzzy | none
            $table->decimal('match_confidence', 4, 3)->nullable();  // 0–1
        });
    }

    public function down(): void
    {
        Schema::table('portfolio_assets', function (Blueprint $table) {
            $table->dropIndex(['scheme_code']);
            $table->dropColumn(['scheme_code', 'match_method', 'match_confidence']);
        });
    }
};
