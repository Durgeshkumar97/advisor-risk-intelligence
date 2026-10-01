<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Exchange rates entered by the operator, used to value foreign-currency
 * holdings in rupees. History is kept: a rate is never updated or deleted,
 * so a report can always say which rate it used.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fx_rates', function (Blueprint $table) {
            $table->id();
            $table->string('currency', 3);          // the foreign currency, e.g. USD
            $table->decimal('rate', 12, 6);         // rupees per 1 unit of it
            $table->date('as_of');                  // the date the rate is for
            $table->string('source');               // free text, shown on reports
            $table->string('entered_by');
            $table->timestamp('entered_at');

            $table->index(['currency', 'as_of']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fx_rates');
    }
};
