<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_intakes', function (Blueprint $table) {

            $table->string('name')->nullable()->after('submission_uuid');
            $table->string('email')->nullable()->after('name');
            $table->string('whatsapp')->nullable()->after('email');
            $table->string('phone')->nullable()->after('whatsapp');
            $table->string('firm_name')->nullable()->after('phone');

            $table->string('document_path')->nullable()->after('firm_name');

            $table->string('plan')->nullable()->after('document_path');
            $table->string('status')->default('trial')->after('plan');

            $table->timestamp('trial_started_at')->nullable()->after('status');
            $table->timestamp('trial_ends_at')->nullable()->after('trial_started_at');

            $table->decimal('plan_price', 10, 2)->default(0)->after('trial_ends_at');
            $table->decimal('revenue_generated', 10, 2)->default(0)->after('plan_price');

            $table->integer('lead_score')->default(0)->after('revenue_generated');
            $table->string('ai_status')->default('pending')->after('lead_score');
        });
    }

    public function down(): void
    {
        Schema::table('client_intakes', function (Blueprint $table) {

            $table->dropColumn([
                'name',
                'email',
                'whatsapp',
                'phone',
                'firm_name',
                'document_path',
                'plan',
                'status',
                'trial_started_at',
                'trial_ends_at',
                'plan_price',
                'revenue_generated',
                'lead_score',
                'ai_status',
            ]);
        });
    }
};