<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
            MODIFY status VARCHAR(50)
            NOT NULL DEFAULT 'pending'
        ");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('status', 50)->change();
            });

            return;
        }

        DB::statement('
            ALTER TABLE payments
            MODIFY status VARCHAR(50)
            NOT NULL
        ');
    }
};
