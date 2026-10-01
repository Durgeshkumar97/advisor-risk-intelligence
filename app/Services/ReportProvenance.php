<?php

namespace App\Services;

use App\DTOs\FxQuote;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * ReportProvenance
 *
 * The sentences a risk report prints about how its figures were arrived at:
 * which exchange rate converted US-dollar holdings, which holdings are carried
 * at cost, and which of a client's files the portfolio was built from.
 *
 * A report is forwarded on its own, without the upload page beside it, so
 * anything that makes it partial or approximate has to be said on the report.
 * Every line is absent when it does not apply: an all-rupee, single-file
 * report has none of them.
 */
class ReportProvenance
{
    /**
     * @param  Collection  $assets  PortfolioAsset models of the report
     * @param  array{included?: list<string>, skipped?: array<string, string>}|null  $clientSources
     *                                                                                               a client folder's files; null for a single-file upload
     * @return array{currency: list<string>, staleness: ?string, valuation: ?string, sources: ?string, skipped: list<string>}
     */
    public static function for(Collection $assets, ?array $clientSources = null, ?CarbonInterface $on = null): array
    {
        $converted = $assets->filter(fn ($asset) => ! empty($asset->meta['fx_rate']));

        $rates = $converted
            ->map(fn ($asset) => [
                'rate' => (float) $asset->meta['fx_rate'],
                'as_of' => (string) ($asset->meta['fx_as_of'] ?? ''),
                'source' => (string) ($asset->meta['fx_source'] ?? ''),
            ])
            ->unique()
            ->values();

        $currency = $rates->map(fn (array $rate) => sprintf(
            'US-dollar holdings converted at ₹%s per USD (%s, as of %s).',
            self::formatRate($rate['rate']),
            $rate['source'],
            Carbon::parse($rate['as_of'])->format('j M Y'),
        ))->all();

        $stale = $rates->contains(fn (array $rate) => (new FxQuote('USD', $rate['rate'], Carbon::parse($rate['as_of']), $rate['source']))->isStale($on));

        $atCost = $assets->filter(fn ($asset) => ($asset->meta['value_basis'] ?? 'market') === 'cost')->count();

        return [
            'currency' => $currency,
            'staleness' => $stale
                ? 'This exchange rate is more than '.FxQuote::STALE_AFTER_DAYS.' days old.'
                : null,
            'valuation' => match (true) {
                $atCost === 0 => null,
                $atCost === 1 => '1 holding is valued at cost — its source export has no current market price — and is excluded from the gain/loss figure.',
                default => $atCost.' holdings are valued at cost — their source export has no current market price — and are excluded from the gain/loss figure.',
            },
            'sources' => $clientSources === null ? null : sprintf(
                'Built from %d of %d %s.',
                count($clientSources['included'] ?? []),
                count($clientSources['included'] ?? []) + count($clientSources['skipped'] ?? []),
                Str::plural('file', count($clientSources['included'] ?? []) + count($clientSources['skipped'] ?? [])),
            ),
            'skipped' => collect($clientSources['skipped'] ?? [])
                ->map(fn ($reason, $entry) => ZipClientLayout::labelWithinClient((string) $entry).' — '.$reason)
                ->values()
                ->all(),
        ];
    }

    /** 95.985 stays 95.985; 96 becomes 96.00. */
    private static function formatRate(float $rate): string
    {
        $text = rtrim(rtrim(number_format($rate, 6, '.', ''), '0'), '.');

        return strlen(substr(strrchr($text, '.') ?: '.', 1)) < 2 ? number_format($rate, 2, '.', '') : $text;
    }
}
