<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add phone column to users table.
 *
 * Used for WhatsApp delivery of daily risk signals.
 * Stored as a raw string (e.g. "9876543210" or "+919876543210").
 * WhatsAppService normalises to international format before sending.
 *
 * Column is nullable — users without a phone get email-only delivery.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Guard: skip if column already exists (safe for re-runs)
        if (Schema::hasColumn('users', 'phone')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone');
        });
    }
};
