<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mf_schemes', function (Blueprint $table) {
            $table->string('scheme_code', 16)->primary();
            // Not unique: AMFI lists the same ISIN on more than one scheme code.
            $table->string('isin_growth', 12)->nullable()->index();
            $table->string('isin_reinvest', 12)->nullable()->index();
            $table->string('scheme_name');
            $table->string('normalized_name')->index();
            $table->string('former_name_normalized')->nullable()->index();
            $table->string('amc')->nullable();
            $table->string('scheme_type', 16);
            $table->string('broad_category')->nullable();
            $table->string('sub_category')->nullable();
            $table->decimal('latest_nav', 18, 4)->nullable();
            $table->date('nav_date')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mf_schemes');
    }
};
