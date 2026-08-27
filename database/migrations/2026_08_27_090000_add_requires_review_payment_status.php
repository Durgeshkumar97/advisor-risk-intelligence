<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the 'requires_review' payment status.
 *
 * ProcessSuccessfulPayment now marks a payment 'requires_review' from its
 * failed() handler — the money was captured but fulfilment exhausted its
 * retries, so a human has to finish it. Without this migration that write
 * would be rejected by MySQL's ENUM in production while passing silently on
 * the SQLite used locally and in tests.
 *
 * Deliberately NOT 'failed': the charge succeeded. Reusing 'failed' would
 * conflate it with the stale-pending sweep in bootstrap/app.php and with
 * genuinely declined payments, and would hide captured-but-unfulfilled money
 * in a bucket nobody audits.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('status', 50)->default('pending')->change();
            });

            return;
        }

        DB::statement("
            ALTER TABLE payments
            MODIFY status ENUM(
                'pending',
                'processing',
                'paid',
                'failed',
                'refunded',
                'requires_review'
            )
            NOT NULL
            DEFAULT 'pending'
        ");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('status', 50)->default('pending')->change();
            });

            return;
        }

        // Any row still sitting in the removed state would be truncated to ''
        // by the ALTER, so park it in 'processing' first — that is where these
        // payments were before failed() moved them, and it keeps them out of
        // the 'paid' revenue sum.
        DB::table('payments')
            ->where('status', 'requires_review')
            ->update(['status' => 'processing']);

        DB::statement("
            ALTER TABLE payments
            MODIFY status ENUM(
                'pending',
                'processing',
                'paid',
                'failed',
                'refunded'
            )
            NOT NULL
            DEFAULT 'pending'
        ");
    }
};
