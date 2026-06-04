<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedInteger('monthly_client_limit')->nullable()->after('portfolio_limit');
        });

        DB::table('plans')->where('slug', 'starter')->update(['monthly_client_limit' => 50]);
        DB::table('plans')->where('slug', 'pro')->update(['monthly_client_limit' => 250]);
        DB::table('plans')->where('slug', 'team')->update(['monthly_client_limit' => 1000]);
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('monthly_client_limit');
        });
    }
};
