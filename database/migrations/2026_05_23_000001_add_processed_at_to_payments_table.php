<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
    |--------------------------------------------------------------------------
    | UP
    |--------------------------------------------------------------------------
    |
    | Adds the processed_at timestamp used as an idempotency guard across
    | CompletePaymentAction, ProcessSuccessfulPayment job, and the webhook.
    | Without this column every retry/duplicate call re-activates the
    | subscription.
    |
    */

    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {

            $table->timestamp('processed_at')
                ->nullable()
                ->after('paid_at');
        });
    }

    /*
    |--------------------------------------------------------------------------
    | DOWN
    |--------------------------------------------------------------------------
    */

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {

            $table->dropColumn('processed_at');
        });
    }
};
