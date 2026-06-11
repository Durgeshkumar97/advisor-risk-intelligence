<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PurgeDeletedUsers extends Command
{
    protected $signature = 'users:purge
                            {--dry-run : List affected users and related records without deleting anything}';

    protected $description = 'Permanently delete users soft-deleted more than 30 days ago, plus their related records.';

    /**
     * Accounts soft-deleted on or before this many days ago are purged.
     */
    private const RETENTION_DAYS = 30;

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subDays(self::RETENTION_DAYS);

        $users = User::onlyTrashed()
            ->where('deleted_at', '<=', $cutoff)
            ->get();

        if ($users->isEmpty()) {
            $this->info('No users past the 30-day retention window. Nothing to purge.');

            return Command::SUCCESS;
        }

        $this->info(sprintf(
            '%s %d user(s) deleted on or before %s.',
            $dryRun ? '[DRY RUN] Found' : 'Purging',
            $users->count(),
            $cutoff->toDateTimeString(),
        ));

        $purged = 0;

        foreach ($users as $user) {
            if ($dryRun) {
                $this->reportDryRun($user);

                continue;
            }

            try {
                // One transaction per user: a partial failure rolls back this
                // user's deletions entirely rather than leaving orphaned records.
                DB::transaction(function () use ($user): void {
                    $this->purgeUser($user);
                });

                // Hash the email — never log PII in plain text.
                Log::info('PurgeDeletedUsers: purged user', [
                    'id'         => $user->id,
                    'email_hash' => hash('sha256', $user->email),
                ]);

                $purged++;
            } catch (Throwable $e) {
                Log::error('PurgeDeletedUsers: failed to purge user', [
                    'id'         => $user->id,
                    'email_hash' => hash('sha256', $user->email),
                    'error'      => $e->getMessage(),
                ]);

                $this->error("Failed to purge user #{$user->id}: {$e->getMessage()}");
            }
        }

        if ($dryRun) {
            $this->info(sprintf('[DRY RUN] Would purge %d user(s) past 30-day retention window.', $users->count()));

            return Command::SUCCESS;
        }

        $this->info("Purged {$purged} user(s) past 30-day retention window.");

        return Command::SUCCESS;
    }

    /**
     * Hard-delete a single user and every record tied to it.
     *
     * Order = children before parents, so we never trip an FK constraint
     * regardless of each FK's on-delete rule. payments and risk_scores are
     * nullOnDelete (a bare forceDelete would orphan them), and client_intakes /
     * password_reset_tokens are linked by email with no FK at all — so all four
     * must be deleted explicitly here.
     */
    private function purgeUser(User $user): void
    {
        $email        = $user->email;
        $portfolioIds = DB::table('portfolios')->where('user_id', $user->id)->pluck('id');

        // 1. portfolio_files (FK user_id cascades, but delete explicitly)
        DB::table('portfolio_files')->where('user_id', $user->id)->delete();

        // 2. portfolio_assets (FK portfolio_id cascades from portfolios; explicit for safety)
        if ($portfolioIds->isNotEmpty()) {
            DB::table('portfolio_assets')->whereIn('portfolio_id', $portfolioIds)->delete();
        }

        // 3. portfolios
        DB::table('portfolios')->where('user_id', $user->id)->delete();

        // 4. risk_scores (nullOnDelete — must delete manually)
        DB::table('risk_scores')->where('user_id', $user->id)->delete();

        // 5. subscriptions
        DB::table('subscriptions')->where('user_id', $user->id)->delete();

        // 6. payments (nullOnDelete — delete by user_id OR email so rows whose
        //    user_id was never linked are still purged, for full PII cleanup)
        DB::table('payments')
            ->where('user_id', $user->id)
            ->orWhere('email', $email)
            ->delete();

        // 7. password_reset_tokens (keyed by email, no FK)
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        // 8. client_intakes (keyed by email, no FK)
        DB::table('client_intakes')->where('email', $email)->delete();

        // 9. the user row itself
        $user->forceDelete();
    }

    /**
     * Report, without deleting, exactly what this user's purge would remove.
     */
    private function reportDryRun(User $user): void
    {
        $portfolioIds = DB::table('portfolios')->where('user_id', $user->id)->pluck('id');

        $counts = [
            'portfolio_files'       => DB::table('portfolio_files')->where('user_id', $user->id)->count(),
            'portfolio_assets'      => $portfolioIds->isNotEmpty()
                ? DB::table('portfolio_assets')->whereIn('portfolio_id', $portfolioIds)->count()
                : 0,
            'portfolios'            => $portfolioIds->count(),
            'risk_scores'           => DB::table('risk_scores')->where('user_id', $user->id)->count(),
            'subscriptions'         => DB::table('subscriptions')->where('user_id', $user->id)->count(),
            'payments'              => DB::table('payments')
                ->where('user_id', $user->id)
                ->orWhere('email', $user->email)
                ->count(),
            'password_reset_tokens' => DB::table('password_reset_tokens')->where('email', $user->email)->count(),
            'client_intakes'        => DB::table('client_intakes')->where('email', $user->email)->count(),
        ];

        $summary = collect($counts)
            ->map(fn ($count, $table) => "{$table}={$count}")
            ->implode(', ');

        $this->line("  user #{$user->id} (deleted_at {$user->deleted_at->toDateTimeString()}): {$summary}");

        Log::info('PurgeDeletedUsers: [DRY RUN] would purge user', [
            'id'         => $user->id,
            'email_hash' => hash('sha256', $user->email),
            'related'    => $counts,
        ]);
    }
}
