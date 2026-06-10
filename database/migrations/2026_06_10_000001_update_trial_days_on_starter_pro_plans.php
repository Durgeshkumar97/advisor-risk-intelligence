<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('plans')
            ->whereIn('slug', ['starter', 'pro'])
            ->update(['trial_days' => 14]);
    }

    public function down(): void
    {
        DB::table('plans')
            ->whereIn('slug', ['starter', 'pro'])
            ->update(['trial_days' => 7]);
    }
};
