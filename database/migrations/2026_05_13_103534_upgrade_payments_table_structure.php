<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run migrations.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {

            /*
            |--------------------------------------------------------------------------
            | RELATIONS
            |--------------------------------------------------------------------------
            */

            $table->foreignId('plan_id')
                ->nullable()
                ->after('user_id')
                ->constrained()
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | CUSTOMER
            |--------------------------------------------------------------------------
            */

            $table->string('name')
                ->nullable()
                ->after('plan_id');

            /*
            |--------------------------------------------------------------------------
            | PAYMENT DETAILS
            |--------------------------------------------------------------------------
            */

            $table->string('gateway')
                ->default('razorpay')
                ->after('phone');

            $table->string('currency', 10)
                ->default('INR')
                ->after('amount');

            $table->string('signature')
                ->nullable()
                ->after('payment_id');

            /*
            |--------------------------------------------------------------------------
            | METADATA
            |--------------------------------------------------------------------------
            */

            $table->json('gateway_response')
                ->nullable()
                ->after('status');

            $table->json('meta')
                ->nullable()
                ->change();

            /*
            |--------------------------------------------------------------------------
            | SECURITY / TRACKING
            |--------------------------------------------------------------------------
            */

            $table->ipAddress('ip_address')
                ->nullable()
                ->after('gateway_response');

            $table->text('user_agent')
                ->nullable()
                ->after('ip_address');

            /*
            |--------------------------------------------------------------------------
            | PAYMENT TIMING
            |--------------------------------------------------------------------------
            */

            $table->timestamp('paid_at')
                ->nullable()
                ->after('user_agent');
        });
    }

    /**
     * Reverse migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {

            $table->dropForeign(['plan_id']);

            $table->dropColumn([
                'plan_id',
                'name',
                'gateway',
                'currency',
                'signature',
                'gateway_response',
                'ip_address',
                'user_agent',
                'paid_at',
            ]);
        });
    }
};
