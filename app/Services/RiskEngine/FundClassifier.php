<?php

namespace App\Services\RiskEngine;

use App\Models\MfScheme;
use App\Services\Amfi\SchemeNameNormalizer;

/**
 * FundClassifier
 *
 * Turns a mutual fund or ETF into a category slug, and the slug into an asset
 * class, a risk score and an equity share — all read from
 * config/fund_categories.php, which holds the reasoning and the sources.
 *
 * |--- WHY TWO ROUTES -------------------------------------------------------
 * | classifyScheme(): the holding matched an AMFI scheme, so AMFI's own
 * |   category header decides (exact lookup, no guessing). Where the header
 * |   does not say what the fund holds (index funds, "other" ETFs, FoFs) the
 * |   scheme's name decides, limited to what the header allows.
 * | classifyName(): no usable AMFI match, so only the holding's name is left.
 * |   First name rule wins; no rule means equity_other.
 * |---------------------------------------------------------------------------
 *
 * |--- WHY A SCOPE ------------------------------------------------------------
 * | An "Index Funds - Equity Funds" scheme called "... Gilt ..." is still an
 * | equity fund. A name rule may only answer with a slug the category allows;
 * | otherwise the scan continues, and failing that the scope's default is used.
 * |---------------------------------------------------------------------------
 */
class FundClassifier
{
    public function __construct(private readonly SchemeNameNormalizer $normalizer) {}

    /**
     * The config key for an AMFI category header: "<type>: <broad>" plus
     * " - <sub>", curly quotes straightened, '*' removed, whitespace
     * collapsed, lowercased.
     */
    public static function categoryKey(?string $type, ?string $broad, ?string $sub): string
    {
        $key = $type.': '.$broad.($sub !== null ? ' - '.$sub : '');
        $key = str_replace(["\u{2019}", "\u{2018}", '*'], ["'", "'", ''], $key);

        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $key)));
    }

    public function isKnownCategory(string $key): bool
    {
        return array_key_exists($key, config('fund_categories.categories', []));
    }

    /** @return array{category: string, asset_class: string, score: float, equity_share: float} */
    public function classifyScheme(MfScheme $scheme): array
    {
        $key = self::categoryKey($scheme->scheme_type, $scheme->broad_category, $scheme->sub_category);
        $target = config('fund_categories.categories')[$key] ?? 'other';

        if (str_starts_with($target, 'by_name:')) {
            $target = $this->slugFromName((string) $scheme->scheme_name, substr($target, 8));
        }

        return $this->describe($target);
    }

    /** @return array{category: string, asset_class: string, score: float, equity_share: float} */
    public function classifyName(string $name): array
    {
        $normalized = $this->normalizer->normalize($name);

        foreach (config('fund_categories.name_rules') as [$pattern, $slug]) {
            if (preg_match($pattern, $normalized)) {
                return $this->describe($slug);
            }
        }

        return $this->describe('equity_other');
    }

    private function slugFromName(string $name, string $scope): string
    {
        $normalized = $this->normalizer->normalize($name);
        $scopes = config('fund_categories.scopes');
        $allowed = $scopes[$scope] ?? $scopes['any'];
        $classes = config('fund_categories.classes');

        foreach (config('fund_categories.name_rules') as [$pattern, $slug]) {
            if (! preg_match($pattern, $normalized)) {
                continue;
            }

            $class = $classes[$slug]['asset_class'] ?? null;
            if (in_array($class, $allowed['classes'] ?? [], true) || in_array($slug, $allowed['slugs'] ?? [], true)) {
                return $slug;
            }
        }

        return $allowed['default'];
    }

    /** @return array{category: string, asset_class: string, score: float, equity_share: float} */
    private function describe(string $slug): array
    {
        $class = config('fund_categories.classes')[$slug];

        return [
            'category' => $slug,
            'asset_class' => $class['asset_class'],
            'score' => (float) $class['score'],
            'equity_share' => (float) $class['equity_share'],
        ];
    }
}
