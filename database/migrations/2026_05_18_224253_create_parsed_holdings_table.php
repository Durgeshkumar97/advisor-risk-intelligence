<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parsed_holdings', function (Blueprint $table) {

            $table->id();

            $table->foreignId('uploaded_file_id')
                ->constrained('portfolio_files')
                ->cascadeOnDelete();

            $table->enum('asset_type', [
                'stock',
                'mutual_fund',
                'etf',
                'bond',
                'commodity',
                'foreign_stock',
                'crypto',
                'cash',
            ]);

            $table->string('symbol')->nullable();

            $table->string('name');

            $table->decimal('quantity', 15, 4)
                ->default(0);

            $table->decimal('buy_price', 15, 2)
                ->default(0);

            $table->decimal('current_price', 15, 2)
                ->default(0);

            $table->decimal('invested_value', 15, 2)
                ->default(0);

            $table->decimal('current_value', 15, 2)
                ->default(0);

            $table->json('meta')->nullable();

            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parsed_holdings');
    }
};
