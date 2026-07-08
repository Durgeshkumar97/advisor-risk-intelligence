<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('admin_logs', function (Blueprint $table) {
            // sha256 of the raw impersonation token — never the raw token
            // itself. Lets a impersonation_link_minted row be joined to the
            // impersonation_link_used row it actually produced, instead of
            // only being correlatable by target_user_id + time proximity.
            $table->string('token_hash', 64)->nullable()->after('target_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admin_logs', function (Blueprint $table) {
            $table->dropColumn('token_hash');
        });
    }
};
