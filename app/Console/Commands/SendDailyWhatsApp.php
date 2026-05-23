<?php

namespace App\Console\Commands;

use App\Jobs\SendWhatsAppMessage;
use App\Models\RiskScore;
use App\Models\Subscription;
use App\Models\User;
use App\Services\WhatsApp\WhatsAppService;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * SendDailyWhatsApp
 *
 * Runs daily at 4:30 PM and dispatches WhatsApp risk-signal messages
 * to every active subscriber who has a phone number and at least one
 * risk score on record.
 *
 * The risk score used is the most recent one — typically generated at
 * 8:00 AM by the risk:generate command, but falls back to the last
 * known score if a user uploaded their portfolio after 8 AM.
 *
 * USAGE
 * ─────
 *   php artisan whatsapp:signal          # normal run
 *   php artisan whatsapp:signal --dry-run # print without sending
 */
class SendDailyWhatsApp extends Command
{
    protected $signature   = 'whatsapp:signal {--dry-run : Print what would be sent without dispatching jobs}';
    protected $description = 'Send daily risk signal via WhatsApp to active subscribers at 4:30 PM';

    public function handle(WhatsAppService $whatsapp): int
    {
        $this->info('── whatsapp:signal ────────────────────────────');

        /*
        |----------------------------------------------------------------------
        | SERVICE HEALTH CHECK
        |----------------------------------------------------------------------
        */

        if (!$whatsapp->isConfigured() && !config('services.whatsapp.test_mode')) {
            $this->warn('WhatsApp not configured. Set WHATSAPP_TOKEN and WHATSAPP_PHONE_NUMBER_ID in .env');
            $this->warn('Or set WHATSAPP_TEST_MODE=true to run in test mode (logs only).');
            return Command::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('DRY RUN — no jobs will be dispatched.');
        }

        /*
        |----------------------------------------------------------------------
        | LOAD ACTIVE SUBSCRIBERS WITH PHONE NUMBERS
        |----------------------------------------------------------------------
        */

        $activeUserIds = Subscription::query()
            ->where(function ($q) {
                $q->where('status', 'active')
                    ->where('ends_at', '>', now());
            })
            ->orWhere(function ($q) {
                $q->where('status', 'trial')
                    ->where('trial_ends_at', '>', now());
            })
            ->pluck('user_id')
            ->unique();

        if ($activeUserIds->isEmpty()) {
            $this->info('No active subscribers. Exiting.');
            return Command::SUCCESS;
        }

        // Only users with a phone number — others get email only
        $users = User::whereIn('id', $activeUserIds)
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->get();

        $this->info("Active subscribers with phone: {$users->count()}");

        if ($users->isEmpty()) {
            $this->info('No users have phone numbers yet. Exiting.');
            return Command::SUCCESS;
        }

        $dispatched = 0;
        $skipped    = 0;

        foreach ($users as $user) {

            /*
            |------------------------------------------------------------------
            | LOAD LATEST RISK SCORE
            |------------------------------------------------------------------
            |
            | Use the most recent risk score for this user — not limited to
            | today's date, because new subscribers may not have an 8 AM score.
            |
            */

            $riskScore = RiskScore::where('user_id', $user->id)
                ->orderByDesc('generated_at')
                ->first();

            if (!$riskScore) {
                $skipped++;
                $this->line("  – {$user->email} — no risk score yet, skipped.");
                Log::info('whatsapp:signal — skipped (no risk score).', [
                    'user_id' => $user->id,
                    'email'   => $user->email,
                ]);
                continue;
            }

            $score      = (int) round($riskScore->score);
            $riskLevel  = $riskScore->meta['risk_level'] ?? 'MEDIUM';
            $nextAction = $riskScore->meta['next_action'] ?? 'View your full report on the dashboard.';

            if ($dryRun) {
                $this->line(sprintf(
                    '  [DRY] %s — %s — Score: %d (%s)',
                    $user->email,
                    $user->phone,
                    $score,
                    $riskLevel
                ));
                $dispatched++;
                continue;
            }

            /*
            |------------------------------------------------------------------
            | DISPATCH JOB
            |------------------------------------------------------------------
            */

            SendWhatsAppMessage::dispatch(
                type:       'risk_signal',
                phone:      $user->phone,
                userName:   $user->name,
                score:      $score,
                riskLevel:  $riskLevel,
                nextAction: $nextAction,
            )->onQueue('whatsapp');

            $dispatched++;

            $this->line(sprintf(
                '  ✓ %s — Score: %d (%s) — job dispatched.',
                $user->email,
                $score,
                $riskLevel
            ));

            Log::info('whatsapp:signal — dispatched.', [
                'user_id'    => $user->id,
                'email'      => $user->email,
                'score'      => $score,
                'risk_level' => $riskLevel,
            ]);
        }

        $this->info("Done. Dispatched: {$dispatched} | Skipped (no score): {$skipped}.");

        Log::info('whatsapp:signal completed.', [
            'dispatched' => $dispatched,
            'skipped'    => $skipped,
            'total'      => $users->count(),
        ]);

        return Command::SUCCESS;
    }
}
