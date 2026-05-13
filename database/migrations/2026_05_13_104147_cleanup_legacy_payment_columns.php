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
            | LEGACY COLUMN CLEANUP
            |--------------------------------------------------------------------------
            */

            if (Schema::hasColumn('payments', 'plan')) {
                $table->string('plan')->nullable()->change();
            }
        });
    }

    /**
     * Reverse migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {

            if (Schema::hasColumn('payments', 'plan')) {
                $table->string('plan')->nullable(false)->change();
            }
        });
    }
};
