<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * CleanupOrphanedZipTempDirs
 *
 * ProcessPortfolioFile::handleZipExtraction() extracts ZIP uploads into
 * sys_get_temp_dir()/portfolio_zip_{uniqid} and cleans up in a finally block
 * — but a queue timeout kills the worker process with SIGKILL, which cannot
 * be caught and skips finally entirely, leaking the temp directory. This
 * command sweeps for and removes any such directory old enough that it
 * can't still belong to an in-progress job.
 */
class CleanupOrphanedZipTempDirs extends Command
{
    protected $signature = 'portfolio:cleanup-temp-dirs
                            {--dry-run : List directories that would be deleted, without deleting them}';

    protected $description = 'Delete orphaned portfolio_zip_* temp directories left behind by killed/timed-out extraction jobs.';

    // ProcessPortfolioFile's own job timeout is 300s — 1 hour is comfortably
    // longer than timeout + retry/backoff, so this never touches a directory
    // that could still be in active use.
    private const MAX_AGE_SECONDS = 3600;

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = time() - self::MAX_AGE_SECONDS;
        $pattern = sys_get_temp_dir().DIRECTORY_SEPARATOR.'portfolio_zip_*';

        $candidates = glob($pattern, GLOB_ONLYDIR) ?: [];
        $removed = 0;
        $skipped = 0;

        foreach ($candidates as $dir) {
            $mtime = @filemtime($dir);

            if ($mtime === false || $mtime >= $cutoff) {
                $skipped++;

                continue;
            }

            if ($dryRun) {
                $this->info("[DRY RUN] Would remove: {$dir}");
                $removed++;

                continue;
            }

            $this->removeDirectory($dir);
            $this->info("Removed: {$dir}");
            $removed++;
        }

        Log::info('CleanupOrphanedZipTempDirs: completed', [
            'removed' => $removed,
            'skipped' => $skipped,
            'dry_run' => $dryRun,
        ]);

        $this->info("Done. Removed: {$removed}, skipped (too recent): {$skipped}.");

        return Command::SUCCESS;
    }

    private function removeDirectory(string $dir): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileInfo) {
            if ($fileInfo->isDir()) {
                @rmdir($fileInfo->getRealPath());
            } else {
                @unlink($fileInfo->getRealPath());
            }
        }

        @rmdir($dir);
    }
}
