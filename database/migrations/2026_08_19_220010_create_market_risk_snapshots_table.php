<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_risk_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('market_date')->unique();
            $table->decimal('score', 5, 2);
            $table->decimal('score_smooth', 5, 2);
            $table->string('label');
            $table->string('vol_regime');
            $table->string('dd_regime');
            $table->string('market_regime');
            $table->string('warning_severity');
            $table->text('warning_text');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_risk_snapshots');
    }
};