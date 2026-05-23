<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portfolio_files', function (Blueprint $table) {

            /*
            |--------------------------------------------------------------
            | Every upload-history query is scoped to user_id.
            | Without this index every query does a full table scan.
            |--------------------------------------------------------------
            */
            $table->index('user_id', 'portfolio_files_user_id_index');

            /*
            |--------------------------------------------------------------
            | Files are also frequently queried by portfolio.
            |--------------------------------------------------------------
            */
            $table->index('portfolio_id', 'portfolio_files_portfolio_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('portfolio_files', function (Blueprint $table) {

            $table->dropIndex('portfolio_files_user_id_index');
            $table->dropIndex('portfolio_files_portfolio_id_index');
        });
    }
};
