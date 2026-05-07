<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uploaded_files', function (Blueprint $table) {

            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('portfolio_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('original_name');

            $table->string('stored_name');

            $table->string('file_path');

            $table->string('mime_type');

            $table->unsignedBigInteger('file_size');

            $table->enum('status', [
                'uploaded',
                'processing',
                'parsed',
                'failed'
            ])->default('uploaded');

            $table->json('meta')->nullable();

            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uploaded_files');
    }
};