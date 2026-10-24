<?php

namespace App\Console\Commands;

use App\Exceptions\AmfiSyncFailed;
use App\Models\AmfiSyncRun;
use App\Models\MfScheme;
use App\Services\Amfi\NavAllParser;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Refreshes the mf_schemes scheme master from AMFI's NAVAll.txt.
 *
 * The file is parsed in memory and only the parsed rows are stored — AMFI's
 * file itself is never written to disk, so it is not redistributed.
 *
 * Every sanity gate runs BEFORE the first write: a truncated download, an
 * error page or a stale file must leave the existing scheme master untouched,
 * because holdings are matched against it. Rows absent from a newer file are
 * kept (merged or wound-up schemes still match old holdings).
 */
class SyncAmfiSchemes extends Command
{
    protected $signature = 'amfi:sync {--source= : URL or local file path; overrides AMFI_NAV_SOURCE}';

    protected $description = 'Download AMFI NAVAll.txt and upsert the mutual-fund scheme master';

    private const COLUMNS = [
        'isin_growth', 'isin_reinvest', 'scheme_name', 'normalized_name', 'former_name_normalized',
        'amc', 'scheme_type', 'broad_category', 'sub_category', 'latest_nav', 'nav_date', 'updated_at',
    ];

    private ?int $rowsParsed = null;

    private ?CarbonImmutable $fileDate = null;

    public function handle(NavAllParser $parser): int
    {
        $source = (string) ($this->option('source') ?: config('amfi.source'));

        try {
            $contents = $this->read($source);
            $parsed = $parser->parse($contents);
        } catch (AmfiSyncFailed $e) {
            return $this->failRun($source, $e->getMessage());
        }

        $rows = $parsed['rows'];
        $this->rowsParsed = count($rows);
        $this->fileDate = $parsed['file_date'];

        $minRows = (int) config('amfi.min_rows');
        if ($this->rowsParsed < $minRows) {
            return $this->failRun($source, "AMFI file has {$this->rowsParsed} rows, fewer than the minimum of {$minRows}.");
        }

        $last = AmfiSyncRun::lastSuccessful();
        if ($last !== null && $last->rows_parsed !== null
            && $this->rowsParsed < (1 - (float) config('amfi.max_row_drop')) * $last->rows_parsed) {
            return $this->failRun($source, "AMFI file has {$this->rowsParsed} rows, a drop of more than ".((float) config('amfi.max_row_drop') * 100)."% from the last successful run's {$last->rows_parsed}.");
        }

        $oldest = now()->startOfDay()->subDays((int) config('amfi.max_age_days'));
        if ($this->fileDate === null || $this->fileDate->lt($oldest)) {
            $date = $this->fileDate?->toDateString() ?? 'unknown';

            return $this->failRun($source, "AMFI file is stale: its NAV date is {$date}, older than ".config('amfi.max_age_days').' days.');
        }

        $now = now();
        foreach ($rows as &$row) {
            $row['created_at'] = $now;
            $row['updated_at'] = $now;
        }
        unset($row);

        DB::transaction(function () use ($rows) {
            foreach (array_chunk($rows, 500) as $chunk) {
                MfScheme::upsert($chunk, ['scheme_code'], self::COLUMNS);
            }
        });

        AmfiSyncRun::create([
            'source' => $source,
            'status' => 'success',
            'rows_parsed' => $this->rowsParsed,
            'file_date' => $this->fileDate,
        ]);

        $this->info("AMFI scheme master synced: {$this->rowsParsed} schemes, NAV date {$this->fileDate->toDateString()}.");

        return self::SUCCESS;
    }

    /** The file's contents, held in memory only. */
    private function read(string $source): string
    {
        if (preg_match('#^https?://#i', $source)) {
            try {
                $response = Http::timeout((int) config('amfi.timeout'))->retry(2, 2000, throw: false)->get($source);
            } catch (\Throwable $e) {
                throw new AmfiSyncFailed("HTTP error fetching {$source}: {$e->getMessage()}");
            }

            if (! $response->successful()) {
                throw new AmfiSyncFailed("HTTP {$response->status()} fetching {$source}");
            }

            return $response->body();
        }

        $contents = is_file($source) && is_readable($source) ? file_get_contents($source) : false;
        if ($contents === false) {
            throw new AmfiSyncFailed("AMFI source is not a readable file: {$source}");
        }

        return $contents;
    }

    /** Records the failure, sends it to Sentry via report(), and leaves mf_schemes untouched. */
    private function failRun(string $source, string $message): int
    {
        AmfiSyncRun::create([
            'source' => $source,
            'status' => 'failed',
            'rows_parsed' => $this->rowsParsed,
            'file_date' => $this->fileDate,
            'error' => $message,
        ]);

        report(new AmfiSyncFailed($message));
        $this->error($message);

        return self::FAILURE;
    }
}
