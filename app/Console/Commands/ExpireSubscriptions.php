<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Subscription;
use Illuminate\Support\Facades\Log;

class ExpireSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire';

    protected $description = 'Expire active and trial subscriptions that have reached their end date';

    public function handle(): int
    {
        $this->info('Starting subscription expiry check...');

        /*
        |--------------------------------------------------------------------------
        | EXPIRE ACTIVE SUBSCRIPTIONS
        |--------------------------------------------------------------------------
        */

        $expiredActive = Subscription::where('status', 'active')
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->update([
                'status'     => 'expired',
                'updated_at' => now(),
            ]);

        /*
        |--------------------------------------------------------------------------
        | EXPIRE TRIAL SUBSCRIPTIONS
        |--------------------------------------------------------------------------
        */

        $expiredTrials = Subscription::where('status', 'trial')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', now())
            ->update([
                'status'     => 'expired',
                'updated_at' => now(),
            ]);

        $total = $expiredActive + $expiredTrials;

        Log::info("subscriptions:expire — expired {$expiredActive} active, {$expiredTrials} trials ({$total} total).");

        $this->info("Expired {$expiredActive} active subscriptions, {$expiredTrials} trial subscriptions ({$total} total).");

        return Command::SUCCESS;
    }
}
