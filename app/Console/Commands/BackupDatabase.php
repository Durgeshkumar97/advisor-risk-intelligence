<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class BackupDatabase extends Command
{
    protected $signature = 'backup:database
                            {--dry-run : Generate the dump file but skip email delivery and retention cleanup}';

    protected $description = 'Export a gzipped pure-PHP SQL dump, email it to the founder, and prune backups older than 7 days.';

    private const RETENTION_DAYS = 7;

    private const BACKUPS_DIR = 'backups';

    // 15 MB compressed — SMTP base64 adds ~33% overhead, pushing it toward the
    // common 25 MB attachment limit. Flag but don't abort: dump is still kept locally.
    private const EMAIL_SIZE_WARN_BYTES = 15_000_000;

    public function handle(): int
    {
        $filename  = 'backup_' . now()->format('Y-m-d_His') . '.sql.gz';
        $outputDir = storage_path('app/' . self::BACKUPS_DIR);
        $outputPath = $outputDir . DIRECTORY_SEPARATOR . $filename;
        $dryRun    = (bool) $this->option('dry-run');

        $this->ensureDirectory($outputDir);

        try {
            $this->info('Exporting database…');
            $this->writeGzipDump($outputPath);

            $sizeBytes = filesize($outputPath);
            $this->info(sprintf(
                'Dump written: %s (%s KB compressed)',
                $filename,
                round($sizeBytes / 1024, 1),
            ));

            Log::info('BackupDatabase: dump created', [
                'file'       => $filename,
                'size_bytes' => $sizeBytes,
            ]);
        } catch (Throwable $e) {
            Log::error('BackupDatabase: dump generation failed', ['error' => $e->getMessage()]);
            $this->error('Dump failed: ' . $e->getMessage());

            return Command::FAILURE;
        }

        if ($dryRun) {
            $this->info('[DRY RUN] Skipping email delivery and retention cleanup.');

            return Command::SUCCESS;
        }

        $this->deliverByEmail($outputPath, $filename);
        $this->pruneOldBackups($outputDir);

        return Command::SUCCESS;
    }

    // -------------------------------------------------------------------------

    private function ensureDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    /**
     * Stream a complete SQL dump directly into a gzip file.
     *
     * Uses gzopen/gzwrite (PHP zlib) instead of shell tools — required because
     * Hostinger shared hosting disables shell_exec, exec, and proc_open.
     */
    private function writeGzipDump(string $outputPath): void
    {
        if (! function_exists('gzopen')) {
            throw new \RuntimeException(
                'PHP zlib extension is not available — cannot create .gz backup. '
                . 'Enable zlib in php.ini or contact Hostinger support.'
            );
        }

        $gz = gzopen($outputPath, 'wb9');

        if ($gz === false) {
            throw new \RuntimeException("gzopen() failed — cannot write to: {$outputPath}");
        }

        try {
            $pdo    = DB::getPdo();
            $dbName = config('database.connections.mysql.database');

            gzwrite($gz, "-- RiskSignal database backup\n");
            gzwrite($gz, '-- Generated: ' . now()->toDateTimeString() . "\n");
            gzwrite($gz, "-- Database:  {$dbName}\n\n");
            gzwrite($gz, "SET FOREIGN_KEY_CHECKS=0;\n\n");

            $tables = DB::select(
                "SELECT TABLE_NAME FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'
                 ORDER BY TABLE_NAME"
            );

            foreach ($tables as $tableRow) {
                $table = $tableRow->TABLE_NAME;

                $this->dumpTable($gz, $pdo, $table);
            }

            gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n");
        } finally {
            gzclose($gz);
        }
    }

    /**
     * Write DROP + CREATE TABLE + all INSERT rows for one table into the gz handle.
     */
    private function dumpTable(mixed $gz, \PDO $pdo, string $table): void
    {
        // DROP + CREATE
        $createResult = DB::select("SHOW CREATE TABLE `{$table}`");
        $createSql    = $createResult[0]->{'Create Table'};

        gzwrite($gz, "-- Table: {$table}\n");
        gzwrite($gz, "DROP TABLE IF EXISTS `{$table}`;\n");
        gzwrite($gz, $createSql . ";\n\n");

        // Column list from schema — avoids an extra data query
        $columnDefs = DB::select("SHOW COLUMNS FROM `{$table}`");

        if (empty($columnDefs)) {
            return;
        }

        $columnList = implode(', ', array_map(
            fn ($col) => '`' . $col->Field . '`',
            $columnDefs,
        ));

        // Stream rows via cursor — fetches one row at a time, avoids loading
        // the entire table into PHP memory.
        $rowCount = 0;

        foreach (DB::table($table)->cursor() as $rowObj) {
            $values = array_map(
                fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v),
                (array) $rowObj,
            );

            gzwrite($gz, "INSERT INTO `{$table}` ({$columnList}) VALUES (" . implode(', ', $values) . ");\n");
            $rowCount++;
        }

        if ($rowCount > 0) {
            gzwrite($gz, "\n");
        }
    }

    private function deliverByEmail(string $filePath, string $filename): void
    {
        $recipient = env('FOUNDER_EMAIL', 'founder@risksignal.in');
        $sizeBytes = filesize($filePath);

        if ($sizeBytes > self::EMAIL_SIZE_WARN_BYTES) {
            $sizeMb = round($sizeBytes / 1_000_000, 1);
            Log::warning('BackupDatabase: dump exceeds 15 MB — SMTP relay may reject the attachment', [
                'size_bytes' => $sizeBytes,
            ]);
            $this->warn(
                "Dump is {$sizeMb} MB — email delivery may be rejected by the SMTP relay. "
                . 'Consider adding AWS_* credentials to .env to enable S3 offload.'
            );
        }

        try {
            $sizeKb = round($sizeBytes / 1024, 1);
            $body   = implode("\n", [
                'RiskSignal automated database backup.',
                '',
                'Date:     ' . now()->toDateString(),
                'File:     ' . $filename,
                'Size:     ' . $sizeKb . ' KB (gzipped SQL)',
                '',
                'Restore: gunzip backup.sql.gz && mysql -u user -p dbname < backup.sql',
            ]);

            Mail::raw($body, function ($message) use ($recipient, $filePath, $filename): void {
                $message
                    ->to($recipient)
                    ->subject('[RiskSignal] DB Backup — ' . now()->toDateString())
                    ->attach($filePath, [
                        'as'   => $filename,
                        'mime' => 'application/gzip',
                    ]);
            });

            Log::info('BackupDatabase: backup emailed', ['to' => $recipient, 'file' => $filename]);
            $this->info("Backup emailed to {$recipient}.");
        } catch (Throwable $e) {
            // Email failure is non-fatal — the dump is still kept locally.
            Log::error('BackupDatabase: email delivery failed', [
                'error' => $e->getMessage(),
                'file'  => $filePath,
            ]);
            $this->error('Email delivery failed: ' . $e->getMessage());
            $this->warn("Dump retained locally at: {$filePath}");
        }
    }

    private function pruneOldBackups(string $dir): void
    {
        $cutoff = now()->subDays(self::RETENTION_DAYS)->getTimestamp();
        $pruned = 0;

        foreach (glob($dir . DIRECTORY_SEPARATOR . 'backup_*.sql.gz') as $file) {
            if (filemtime($file) < $cutoff) {
                unlink($file);
                $pruned++;
            }
        }

        if ($pruned > 0) {
            Log::info('BackupDatabase: pruned old backups', ['count' => $pruned, 'days' => self::RETENTION_DAYS]);
            $this->info("Pruned {$pruned} backup(s) older than " . self::RETENTION_DAYS . ' days.');
        }
    }
}
