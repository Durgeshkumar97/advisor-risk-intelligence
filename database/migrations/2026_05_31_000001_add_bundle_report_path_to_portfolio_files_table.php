<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portfolio_files', function (Blueprint $table) {
            $table->string('bundle_report_path')->nullable()->after('report_path');
        });
    }

    public function down(): void
    {
        Schema::table('portfolio_files', function (Blueprint $table) {
            $table->dropColumn('bundle_report_path');
        });
    }
};
