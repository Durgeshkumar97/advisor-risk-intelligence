<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amfi_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('source');
            $table->string('status', 16);
            $table->unsignedInteger('rows_parsed')->nullable();
            $table->date('file_date')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amfi_sync_runs');
    }
};
