<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Subscription;
use Illuminate\Support\Facades\Log;

class ExpireSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire';

    protected $description = 'Expire subscriptions that have reached their end date';

    public function handle()
    {
        $this->info("Starting subscription expiry...");

        $expiredCount = Subscription::where('status', 'active')
            ->where('ends_at', '<=', now())
            ->update([
                'status' => 'expired',
                'updated_at' => now()
            ]);

        Log::info("Expired subscriptions count: {$expiredCount}");

        $this->info("Expired {$expiredCount} subscriptions.");

        return Command::SUCCESS;
    }
}             
