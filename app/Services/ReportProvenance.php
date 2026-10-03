<?php

namespace App\Services;

use App\DTOs\FxQuote;
use App\Services\RiskEngine\AssetRiskScorer;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * ReportProvenance
 *
 * The sentences a risk report prints about how its figures were arrived at:
 * which exchange rate converted US-dollar holdings, which holdings are carried
 * at cost, which of a client's files the portfolio was built from, and how
 * each holding's risk score was arrived at.
 *
 * A report is forwarded on its own, without the upload page beside it, so
 * anything that makes it partial or approximate has to be said on the report.
 * Every line is absent when it does not apply: an all-rupee, single-file
 * report has none of them.
 */
class ReportProvenance
{
    /** Scored from a risk_service classification of that stock. */
    public const SCORE_FROM_DATA = 'data';

    /** A fund's category default, moved by a keyword in its name. */
    public const SCORE_FROM_NAME = 'name';

    /** The flat default for the holding's asset category. */
    public const SCORE_FROM_CATEGORY = 'category';

    private const SCORE_BASIS_LABELS = [
        self::SCORE_FROM_DATA => 'data',
        self::SCORE_FROM_NAME => 'by name',
        self::SCORE_FROM_CATEGORY => 'by type',
    ];

    /**
     * @param  Collection  $assets  PortfolioAsset models of the report
     * @param  array{included?: list<string>, skipped?: array<string, string>}|null  $clientSources
     *                                                                                               a client folder's files; null for a single-file upload
     * @return array{currency: list<string>, staleness: ?string, valuation: ?string, sources: ?string, skipped: list<string>, scores: ?string}
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
            'scores' => self::scoresLine($assets),
        ];
    }

    /**
     * How one holding's risk score was arrived at. Worked out from what is
     * already stored — the stock's classification source, or the holding's
     * type and name — so it applies to every holding, old or new.
     */
    public static function scoreBasis($asset): string
    {
        if (($asset->meta['stock_risk']['source'] ?? null) === AssetRiskScorer::SOURCE_LIVE) {
            return self::SCORE_FROM_DATA;
        }

        return (new AssetRiskScorer)->nameAdjustsScore((string) $asset->asset_type, (string) $asset->name)
            ? self::SCORE_FROM_NAME
            : self::SCORE_FROM_CATEGORY;
    }

    /** The words printed under a holding's score. */
    public static function scoreBasisLabel($asset): string
    {
        return self::SCORE_BASIS_LABELS[self::scoreBasis($asset)];
    }

    /**
     * "Risk scores: 2 from market data, 1 estimated from the fund's name, 3 by
     * asset category." A count of zero is left out; the whole line is left
     * out when every score is from market data.
     */
    private static function scoresLine(Collection $assets): ?string
    {
        $counts = $assets->countBy(fn ($asset) => self::scoreBasis($asset));

        $data = (int) ($counts[self::SCORE_FROM_DATA] ?? 0);
        $name = (int) ($counts[self::SCORE_FROM_NAME] ?? 0);
        $category = (int) ($counts[self::SCORE_FROM_CATEGORY] ?? 0);

        if ($name + $category === 0) {
            return null;
        }

        $parts = array_filter([
            $data > 0 ? $data.' from market data' : null,
            $name > 0 ? $name." estimated from the fund's name" : null,
            $category > 0 ? $category.' by asset category' : null,
        ]);

        return 'Risk scores: '.implode(', ', $parts).'.';
    }

    /** 95.985 stays 95.985; 96 becomes 96.00. */
    private static function formatRate(float $rate): string
    {
        $text = rtrim(rtrim(number_format($rate, 6, '.', ''), '0'), '.');

        return strlen(substr(strrchr($text, '.') ?: '.', 1)) < 2 ? number_format($rate, 2, '.', '') : $text;
    }
}
