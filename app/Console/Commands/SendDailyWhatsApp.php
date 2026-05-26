<?php

namespace App\Console\Commands;

use App\Jobs\SendWhatsAppMessage;
use App\Models\RiskScore;
use App\Models\Subscription;
use App\Models\User;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendDailyWhatsApp extends Command
{
    protected $signature = 'whatsapp:signal {--dry-run : Print what would be sent without dispatching jobs}';
    protected $description = 'Send daily risk signal via WhatsApp to active subscribers at 4:30 PM';

    public function handle(WhatsAppService $whatsapp): int
    {
        $this->info('── whatsapp:signal ────────────────────────────');

        if (!$whatsapp->isConfigured() && !config('services.whatsapp.test_mode')) {
            $this->warn('WhatsApp not configured');
            return Command::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('DRY RUN — no jobs will be dispatched.');
        }

        $activeUserIds = Subscription::query()
            ->where(function ($q) {
                $q->where('status', 'active')->where('ends_at', '>', now());
            })
            ->orWhere(function ($q) {
                $q->where('status', 'trial')->where('trial_ends_at', '>', now());
            })
            ->pluck('user_id')
            ->unique();

        if ($activeUserIds->isEmpty()) {
            $this->info('No active subscribers.');
            return Command::SUCCESS;
        }

        $users = User::whereIn('id', $activeUserIds)->whereNotNull('phone')->where('phone', '!=', '')->get();
        $this->info("Active subscribers with phone: {$users->count()}");

        if ($users->isEmpty()) {
            $this->info('No users have phone numbers yet.');
            return Command::SUCCESS;
        }

        $dispatched = 0;
        $skipped = 0;

        foreach ($users as $user) {
            $riskScore = RiskScore::where('user_id', $user->id)->orderByDesc('generated_at')->first();

            if (!$riskScore) {
                $skipped++;
                $this->line("  – {$user->email} — no risk score yet.");
                continue;
            }

            $score = (int) round($riskScore->score);
            $riskLevel = $riskScore->meta['risk_level'] ?? 'MEDIUM';
            $nextAction = $riskScore->meta['next_action'] ?? 'View your report.';

            if ($dryRun) {
                $this->line("  [DRY] {$user->email} — Score: {$score} ({$riskLevel})");
                $dispatched++;
                continue;
            }

            SendWhatsAppMessage::dispatch(
                type: 'risk_signal',
                phone: $user->phone,
                userName: $user->name,
                score: $score,
                riskLevel: $riskLevel,
                nextAction: $nextAction,
            )->onQueue('whatsapp');

            $dispatched++;
            $this->line("  ✓ {$user->email} — dispatched.");
        }

        $this->info("Done. Dispatched: {$dispatched} | Skipped: {$skipped}.");
        return Command::SUCCESS;
    }
}
