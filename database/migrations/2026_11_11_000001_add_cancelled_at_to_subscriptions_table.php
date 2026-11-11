<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| A user cancelling a plan and a refund revoking it are different events.
| A refund sets status 'cancelled' and backdates ends_at, which takes access
| away. A user's own cancellation keeps access until the plan's end date, as
| the UI promises, so it is recorded here and leaves status alone.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('renewal_at');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('cancelled_at');
        });
    }
};
