<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add missing columns to the leads table.
 *
 * The Lead model and AdminIntakeController reference utm_source,
 * utm_campaign, utm_medium, contacted_at, and notes — none of which
 * existed in the original migration. This migration adds them safely
 * with guard checks so it is re-run safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {

            if (!Schema::hasColumn('leads', 'notes')) {
                $table->text('notes')->nullable()->after('status');
            }

            if (!Schema::hasColumn('leads', 'contacted_at')) {
                $table->timestamp('contacted_at')->nullable()->after('notes');
            }

            if (!Schema::hasColumn('leads', 'utm_source')) {
                $table->string('utm_source', 100)->nullable()->after('contacted_at');
            }

            if (!Schema::hasColumn('leads', 'utm_campaign')) {
                $table->string('utm_campaign', 100)->nullable()->after('utm_source');
            }

            if (!Schema::hasColumn('leads', 'utm_medium')) {
                $table->string('utm_medium', 100)->nullable()->after('utm_campaign');
            }
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn([
                'notes',
                'contacted_at',
                'utm_source',
                'utm_campaign',
                'utm_medium',
            ]);
        });
    }
};
