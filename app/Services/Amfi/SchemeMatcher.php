<?php

namespace App\Services\Amfi;

use App\DTOs\SchemeMatch;
use App\Models\MfScheme;
use App\Services\RiskEngine\FundClassifier;
use Illuminate\Support\Carbon;

/**
 * SchemeMatcher
 *
 * Finds the AMFI scheme a holding is, so its category comes from AMFI
 * rather than from guessing at words in its name.
 *
 * ORDER
 * ═════
 *   1. ISIN    — exact, against either ISIN column. Confidence 1.0.
 *   2. Exact   — normalised name (or a scheme's normalised former name).
 *                1.0 when every candidate is in one AMFI category, else
 *                EXACT_SPLIT_CONFIDENCE: the name is right but which
 *                category it belongs to is not certain.
 *   3. Fuzzy   — word overlap against schemes of the same AMC, each word
 *                weighted by how rare it is across all scheme names (IDF),
 *                so the AMC's own words ("aditya birla sun life") count for
 *                little and the words that name the scheme count for most.
 *                One typo (a wrong, missing, extra or swapped letter) is
 *                tolerated in words of five letters or more.
 *
 * A fuzzy candidate is rejected outright when the two names differ in a
 * word that names what a fund holds — a number ("Midcap 150" vs "Midcap 50"),
 * "next" ("Nifty 50" vs "Nifty Next 50"), a cap size, a debt duration, gold,
 * a sector. Two names that share every other word but differ there are
 * different funds, and a high overlap score would otherwise say they are
 * the same. A best match with a runner-up in a different class within
 * AMBIGUITY_MARGIN is no match: the name does not settle which it is.
 *
 * "Category" here is the class FundClassifier gives the scheme, not AMFI's
 * header text: AMFI lists one category under two labels while AMCs move to
 * SEBI's 2026 names ("Debt Scheme - Banking and PSU Fund" and "Income/Debt
 * Oriented Schemes - Banking and PSU Debt Fund"), and two schemes under those
 * two labels are not a disagreement about what the holding is.
 *
 * THRESHOLDS  (calibrated on perturbed real AMFI names — see Part B notes)
 * ══════════
 *   ≥ HIGH      used
 *   ≥ PROBABLE  used, and the report says "probable"
 *   below       unmatched — the holding is classified by name instead
 */
class SchemeMatcher
{
    public const HIGH = 0.90;

    public const PROBABLE = 0.80;

    public const AMBIGUITY_MARGIN = 0.05;

    public const EXACT_SPLIT_CONFIDENCE = 0.85;

    /** A scheme with a NAV this recent (vs the newest in the table) is live, and preferred over a dead one of the same name. */
    private const ACTIVE_WITHIN_DAYS = 30;

    /*
    | Words that say what a fund holds. Names that differ in one of these are
    | different funds however many other words they share. "index" and "etf"
    | are deliberately absent: an advisor's file often drops them from an
    | index fund's name without changing which fund it is.
    */
    private const DISCRIMINATORS = [
        'next', 'large', 'mid', 'small', 'micro', 'largecap', 'midcap', 'smallcap', 'microcap',
        'flexicap', 'multicap', 'focused', 'value', 'contra', 'dividendyield', 'elss', 'taxsaver',
        'equity', 'debt', 'hybrid', 'arbitrage', 'savings', 'conservative', 'aggressive', 'balanced',
        'advantage', 'dynamic', 'multi', 'asset', 'allocation', 'liquid', 'overnight', 'money',
        'market', 'ultra', 'short', 'medium', 'long', 'term', 'credit', 'risk', 'corporate', 'bond',
        'banking', 'psu', 'gilt', 'gsec', 'sdl', 'floating', 'gold', 'silver', 'momentum',
        'quality', 'alpha', 'low', 'volatility', 'equal', 'weight', 'bank', 'financial', 'pharma',
        'healthcare', 'technology', 'infrastructure', 'consumption', 'energy', 'auto', 'fmcg',
        'realty', 'metal', 'defence', 'manufacturing', 'nasdaq', 'global', 'international',
        'overseas', 'us', 'fof', 'nifty', 'sensex', 'bse', 'retirement', 'childrens', 'life',
        'cycle', 'segregated',
    ];

    /** @var array<string, list<array{name: string, tokens: list<string>, category: string, code: string, active: bool, nav_date: ?string}>>|null by first word */
    private ?array $blocks = null;

    private ?string $activeSince = null;

    /** @var array<string, float> inverse document frequency per word, over all scheme names */
    private array $idf = [];

    private float $unseenIdf = 1.0;

    public function __construct(
        private SchemeNameNormalizer $normalizer,
        private FundClassifier $classifier,
    ) {}

    /**
     * Whether a parsed holding is a fund to look up: typed as a mutual fund or
     * ETF, or carrying a mutual-fund ISIN (INF…) whatever its type says — an
     * exchange-traded ETF often arrives in an equity holdings file typed as a
     * stock. Stocks without a fund ISIN are out of scope and keep their
     * existing scoring.
     */
    public static function applies(array $holding): bool
    {
        return in_array($holding['asset_type'] ?? null, ['mutual_fund', 'etf'], true)
            || str_starts_with(strtoupper(trim((string) ($holding['isin'] ?? ''))), 'INF');
    }

    public function match(?string $isin, string $name): SchemeMatch
    {
        $isin = strtoupper(trim((string) $isin));

        if (preg_match('/^IN[A-Z0-9]{10}$/', $isin)) {
            $scheme = MfScheme::where('isin_growth', $isin)
                ->orWhere('isin_reinvest', $isin)
                ->orderByDesc('nav_date')
                ->orderBy('scheme_code')
                ->first();

            if ($scheme) {
                return new SchemeMatch($scheme, SchemeMatch::METHOD_ISIN, 1.0);
            }
        }

        $normalized = $this->normalizer->normalize($name);

        if ($normalized === '') {
            return SchemeMatch::none();
        }

        return $this->exact($normalized) ?? $this->fuzzy($normalized);
    }

    private function exact(string $normalized): ?SchemeMatch
    {
        // Plan and option are not in the normalised name, so a scheme's direct,
        // regular and IDCW rows all match; any of them carries the category.
        $candidates = MfScheme::where('normalized_name', $normalized)
            ->orWhere('former_name_normalized', $normalized)
            ->orderByDesc('nav_date')
            ->orderBy('scheme_code')
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        $live = $candidates->filter(fn (MfScheme $s) => $this->isActive($s->nav_date?->toDateString()));
        $pool = $live->isNotEmpty() ? $live : $candidates;

        $categories = $pool->map(fn (MfScheme $s) => $this->classifier->classifyScheme($s)['category'])->unique();

        return new SchemeMatch(
            $pool->first(),
            SchemeMatch::METHOD_EXACT,
            $categories->count() === 1 ? 1.0 : self::EXACT_SPLIT_CONFIDENCE,
        );
    }

    private function fuzzy(string $normalized): SchemeMatch
    {
        $tokens = explode(' ', $normalized);
        $block = $this->blocks()[$tokens[0]] ?? [];

        $best = null;          // ['score', 'active', 'nav_date', 'category', 'code']
        $bestByCategory = [];

        foreach ($block as $candidate) {
            $score = $this->similarity($tokens, $candidate['tokens']);

            if ($score === null) {
                continue;
            }

            $rank = [$score, $candidate['active'] ? 1 : 0, (string) $candidate['nav_date']];

            if (! isset($bestByCategory[$candidate['category']]) || $rank > $bestByCategory[$candidate['category']]['rank']) {
                $bestByCategory[$candidate['category']] = ['rank' => $rank, 'code' => $candidate['code']];
            }
        }

        if ($bestByCategory === []) {
            return SchemeMatch::none();
        }

        uasort($bestByCategory, fn ($a, $b) => $b['rank'] <=> $a['rank']);
        $ranked = array_values($bestByCategory);
        $top = $ranked[0]['rank'][0];
        $runnerUp = $ranked[1]['rank'][0] ?? null;

        if ($top < self::PROBABLE) {
            return SchemeMatch::none(round($top, 3));
        }

        if ($runnerUp !== null && $top - $runnerUp < self::AMBIGUITY_MARGIN) {
            return SchemeMatch::none(round($top, 3));
        }

        return new SchemeMatch(
            MfScheme::find($ranked[0]['code']),
            SchemeMatch::METHOD_FUZZY,
            round($top, 3),
        );
    }

    /**
     * IDF-weighted Dice coefficient over words: twice the weight of the words
     * both names share over the weight of all their words. A word of five
     * letters or more may differ by one typo. A word never seen in any scheme
     * name (a typo, usually) weighs as much as the rarest real word.
     * Null when the names differ in a discriminating word.
     *
     * @param  list<string>  $a
     * @param  list<string>  $b
     */
    private function similarity(array $a, array $b): ?float
    {
        $unmatchedB = $b;
        $matched = 0.0;
        $unmatchedA = [];

        foreach ($a as $word) {
            $at = array_search($word, $unmatchedB, true);

            if ($at === false && strlen($word) >= 5) {
                foreach ($unmatchedB as $i => $other) {
                    if (strlen($other) >= 5 && self::withinOneEdit($word, $other)) {
                        $at = $i;
                        break;
                    }
                }
            }

            if ($at === false) {
                $unmatchedA[] = $word;

                continue;
            }

            $matched += $this->weight($unmatchedB[$at]);
            unset($unmatchedB[$at]);
        }

        foreach ([...$unmatchedA, ...$unmatchedB] as $word) {
            if (self::discriminates($word)) {
                return null;
            }
        }

        $total = array_sum(array_map($this->weight(...), $a)) + array_sum(array_map($this->weight(...), $b));

        return $total > 0 ? 2 * $matched / $total : 0.0;
    }

    private function weight(string $word): float
    {
        return $this->idf[$word] ?? $this->unseenIdf;
    }

    /** Levenshtein ≤ 1, or one swap of adjacent letters ("liuqid" for "liquid"). */
    private static function withinOneEdit(string $a, string $b): bool
    {
        if (levenshtein($a, $b) <= 1) {
            return true;
        }

        if (strlen($a) !== strlen($b)) {
            return false;
        }

        $diff = array_keys(array_diff_assoc(str_split($a), str_split($b)));

        return count($diff) === 2 && $diff[1] === $diff[0] + 1
            && $a[$diff[0]] === $b[$diff[1]] && $a[$diff[1]] === $b[$diff[0]];
    }

    private static function discriminates(string $word): bool
    {
        return preg_match('/\d/', $word) === 1
            || preg_match('/^[ivxl]{2,}$/', $word) === 1          // series numerals: ii, xxvii
            || in_array($word, self::DISCRIMINATORS, true);
    }

    private function isActive(?string $navDate): bool
    {
        $this->activeSince ??= Carbon::parse(MfScheme::max('nav_date') ?? now())
            ->subDays(self::ACTIVE_WITHIN_DAYS)->toDateString();

        return $navDate !== null && $navDate >= $this->activeSince;
    }

    /**
     * Every scheme name, grouped by its first word (in practice the AMC), loaded
     * once per matcher — fuzzy matching only ever compares within an AMC.
     * Former names are entries of their own pointing at the same scheme.
     */
    private function blocks(): array
    {
        if ($this->blocks !== null) {
            return $this->blocks;
        }

        $this->blocks = [];
        $documentFrequency = [];

        MfScheme::query()
            ->select(['scheme_code', 'scheme_name', 'normalized_name', 'former_name_normalized', 'scheme_type', 'broad_category', 'sub_category', 'nav_date'])
            ->orderBy('scheme_code')
            ->chunk(2000, function ($schemes) use (&$documentFrequency) {
                foreach ($schemes as $s) {
                    $navDate = $s->nav_date?->toDateString();
                    $category = $this->classifier->classifyScheme($s)['category'];

                    foreach (array_unique(array_filter([$s->normalized_name, $s->former_name_normalized])) as $name) {
                        $tokens = explode(' ', $name);
                        foreach (array_unique($tokens) as $token) {
                            $documentFrequency[$name][$token] = true;
                        }
                        $this->blocks[$tokens[0]][] = [
                            'tokens' => $tokens,
                            'category' => $category,
                            'code' => (string) $s->scheme_code,
                            'active' => $this->isActive($navDate),
                            'nav_date' => $navDate,
                        ];
                    }
                }
            });

        // Over distinct names: a scheme's direct/regular/IDCW rows are one name, not five.
        $df = [];
        foreach ($documentFrequency as $tokens) {
            foreach ($tokens as $token => $_) {
                $df[$token] = ($df[$token] ?? 0) + 1;
            }
        }
        $names = max(1, count($documentFrequency));
        foreach ($df as $token => $count) {
            $this->idf[$token] = log($names / $count) + 1;
        }
        $this->unseenIdf = log($names) + 1;

        return $this->blocks;
    }
}
