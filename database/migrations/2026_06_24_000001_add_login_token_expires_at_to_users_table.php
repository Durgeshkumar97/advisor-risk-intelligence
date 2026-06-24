<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('login_token_expires_at')->nullable()->after('login_token');
        });

        // Invalidate all pre-existing magic-login tokens issued before expiry was enforced
        DB::table('users')->whereNotNull('login_token')->update(['login_token' => null]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('login_token_expires_at');
        });
    }
};
