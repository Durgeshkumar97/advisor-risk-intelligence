<?php

namespace App\Console\Commands;

use App\Contracts\FxRateProvider;
use App\Models\FxRate;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Enter the USD → INR rate used to value US-dollar holdings.
 *
 *   php artisan fx:set-usd 95.985 --as-of=2026-09-29 --source="Operator entry — bank reference rate"
 *
 * The source text is printed on every report that uses the rate, so it must
 * say truthfully where the number came from.
 */
class SetUsdRate extends Command
{
    private const MIN_RATE = 50.0;

    private const MAX_RATE = 200.0;

    /** A rate this far from the previous one is more likely a typo than a market move. */
    private const MAX_CHANGE = 0.10;

    protected $signature = 'fx:set-usd
        {rate : Rupees per 1 US dollar, e.g. 95.985}
        {--as-of= : The date the rate is for (YYYY-MM-DD)}
        {--source= : Where the rate came from; shown on reports}
        {--by= : Who is entering it (defaults to the system user)}
        {--force : Accept a rate more than 10% away from the previous one}';

    protected $description = 'Set the USD to INR exchange rate used to value US-dollar holdings';

    public function handle(FxRateProvider $rates): int
    {
        $rate = $this->argument('rate');
        $source = trim((string) $this->option('source'));

        if (! is_numeric($rate) || (float) $rate < self::MIN_RATE || (float) $rate > self::MAX_RATE) {
            $this->error(sprintf('Rate must be a number between %d and %d rupees per US dollar.', self::MIN_RATE, self::MAX_RATE));

            return self::FAILURE;
        }

        $rate = (float) $rate;
        $asOf = $this->parseDate((string) $this->option('as-of'));

        if ($asOf === null) {
            $this->error('Give the date the rate is for: --as-of=YYYY-MM-DD.');

            return self::FAILURE;
        }

        if ($asOf->isAfter(now()->startOfDay())) {
            $this->error('The --as-of date cannot be in the future.');

            return self::FAILURE;
        }

        if ($source === '') {
            $this->error('Say where the rate came from: --source="…". It is shown on reports.');

            return self::FAILURE;
        }

        $previous = $rates->current('USD');

        if ($previous !== null && ! $this->option('force')
            && abs($rate - $previous->rate) / $previous->rate > self::MAX_CHANGE) {
            $this->error(sprintf(
                'Rate %s is more than 10%% away from the current rate %s (as of %s). If it is correct, run again with --force.',
                $this->formatRate($rate),
                $this->formatRate($previous->rate),
                $previous->asOf->toDateString(),
            ));

            return self::FAILURE;
        }

        $row = FxRate::create([
            'currency' => 'USD',
            'rate' => $rate,
            'as_of' => $asOf->toDateString(),
            'source' => $source,
            'entered_by' => trim((string) $this->option('by')) ?: (get_current_user() ?: 'console'),
            'entered_at' => now(),
        ]);

        $this->info('Stored. This is now one of '.FxRate::where('currency', 'USD')->count().' USD rates on file.');
        $this->table(
            ['id', 'currency', 'rate (INR per USD)', 'as of', 'source', 'entered by', 'entered at'],
            [[$row->id, $row->currency, $this->formatRate((float) $row->rate), $row->as_of->toDateString(), $row->source, $row->entered_by, $row->entered_at->toDateTimeString()]],
        );

        $inForce = $rates->current('USD');

        if ($inForce !== null && $inForce->asOf->toDateString() !== $asOf->toDateString()) {
            $this->warn('A rate with a later date ('.$inForce->asOf->toDateString().') is on file and stays in force.');
        }

        return self::SUCCESS;
    }

    private function parseDate(string $value): ?Carbon
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('!Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }

        return $date !== false && $date->toDateString() === $value ? $date : null;
    }

    private function formatRate(float $rate): string
    {
        return rtrim(rtrim(number_format($rate, 6, '.', ''), '0'), '.');
    }
}
