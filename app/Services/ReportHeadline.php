<?php

namespace App\Services;

use App\Services\RiskEngine\PortfolioRiskCalculator as Calculator;
use Illuminate\Support\Collection;

/**
 * ReportHeadline
 *
 * The figures on page one of a risk report that are worked out for display:
 * the largest holding's share, and the gain or loss against cost. Nothing
 * here feeds the score, and nothing here is stored. It also puts the
 * calculator's risk flags into words.
 *
 * These replaced two tiles that printed stored score inputs under names they
 * did not deserve: "Volatility" (the spread of the holdings' own risk scores,
 * not price volatility) and "Max Drawdown" (the loss against cost, shown as
 * 0.00 whenever the portfolio was in profit).
 */
class ReportHeadline
{
    private const NAME_LIMIT = 40;

    /**
     * The largest single holding by current value, as a share of the whole.
     * Holdings valued at cost count, as they do for the score's weighting.
     * Ties go to the name that sorts first.
     *
     * @return array{name: string, share: float}|null null when nothing has a value
     */
    public static function largestHolding(Collection $assets): ?array
    {
        $total = (float) $assets->sum('current_value');

        if ($total <= 0) {
            return null;
        }

        $largest = $assets
            ->sort(fn ($a, $b) => [(float) $b->current_value, (string) $a->name] <=> [(float) $a->current_value, (string) $b->name])
            ->first();

        return [
            'name' => self::shorten((string) $largest->name),
            'share' => (float) $largest->current_value / $total * 100,
        ];
    }

    /**
     * Gain or loss against cost, over the holdings where that can be measured.
     *
     * The subset is the one PortfolioRiskCalculator measures unrealised loss
     * over — cost known, and not a holding valued at its cost — so this
     * figure and the score never disagree about which holdings count.
     *
     * @return array{pct: ?float, counted: int, total: int, invested: float, current: float}
     *                                                                                       pct is null when no holding has a usable cost
     */
    public static function gainLoss(Collection $assets): array
    {
        $measured = $assets->filter(
            fn ($asset) => $asset->invested_value !== null && ($asset->meta['value_basis'] ?? 'market') !== 'cost'
        );

        $invested = (float) $measured->sum('invested_value');
        $current = (float) $measured->sum('current_value');

        return [
            'pct' => $invested > 0 ? ($current - $invested) / $invested * 100 : null,
            'counted' => $invested > 0 ? $measured->count() : 0,
            'total' => $assets->count(),
            'invested' => $invested,
            'current' => $current,
        ];
    }

    /**
     * A risk flag in plain words: what was measured and the line it crossed.
     *
     * Descriptive only — it states a fact about the portfolio and never says
     * what to do about it. Every number is read from the calculator's own
     * threshold, so the sentence cannot drift from the rule that raised the
     * flag. A flag this does not know is shown as its name in ordinary words,
     * never as a blank.
     */
    public static function flagLine(string $flag): string
    {
        $pct = fn (float $ratio): string => rtrim(rtrim(number_format($ratio * 100, 1), '0'), '.');

        return match ($flag) {
            'HIGH_CONCENTRATION' => sprintf('Value is concentrated in a few holdings (concentration index above %d of 100)', Calculator::HIGH_CONCENTRATION_ABOVE),
            'MODERATE_CONCENTRATION' => sprintf('Value is moderately concentrated (concentration index above %d of 100)', Calculator::MODERATE_CONCENTRATION_ABOVE),
            'EQUITY_HEAVY' => sprintf('Equity makes up more than %s%% of the portfolio', $pct(Calculator::EQUITY_HEAVY_ABOVE)),
            'UNDERWEIGHTED_EQUITY' => sprintf('Equity makes up less than %s%% of the portfolio', $pct(Calculator::UNDERWEIGHTED_EQUITY_BELOW)),
            'SIGNIFICANT_DRAWDOWN' => sprintf('Holdings with a known cost are more than %d%% below what was paid', Calculator::SIGNIFICANT_DRAWDOWN_ABOVE),
            'MODERATE_DRAWDOWN' => sprintf('Holdings with a known cost are more than %d%% below what was paid', Calculator::MODERATE_DRAWDOWN_ABOVE),
            'LOW_DIVERSIFICATION' => sprintf('Fewer than %d holdings', Calculator::LOW_DIVERSIFICATION_BELOW),
            'OVER_DIVERSIFICATION' => sprintf('More than %d holdings', Calculator::OVER_DIVERSIFICATION_ABOVE),
            'ELEVATED_OVERALL_RISK' => sprintf('Overall risk score is above %d of 100', Calculator::ELEVATED_OVERALL_RISK_ABOVE),
            'NO_HOLDINGS' => 'No holdings',
            default => ucfirst(strtolower(trim(str_replace('_', ' ', $flag)))),
        };
    }

    /** "+2.1", "−1.6", "0.0" — always signed when not zero, with a true minus sign. */
    public static function signed(float $pct): string
    {
        $rounded = round($pct, 1);

        return ($rounded > 0 ? '+' : ($rounded < 0 ? '−' : '')).number_format(abs($rounded), 1);
    }

    private static function shorten(string $name): string
    {
        return mb_strlen($name) > self::NAME_LIMIT
            ? rtrim(mb_substr($name, 0, self::NAME_LIMIT - 1)).'…'
            : $name;
    }
}
