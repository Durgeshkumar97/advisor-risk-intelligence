<?php

declare(strict_types=1);

namespace App\Services\IpoRisk\Scoring;

use InvalidArgumentException;

/**
 * How much of the applicable input weight was actually populated, 0-100.
 *
 * This is a statement about the evidence, not about the issue. It answers
 * "how much of what matters did we actually read?" — so a thin filing scores
 * low here no matter how good or bad the company looks.
 *
 * Deliberately NOT called confidence. The portfolio engine already has a
 * per-asset `confidence` float (0-1) meaning an ML model's confidence in one
 * prediction, sourced from risk_service. Two different things sharing a word
 * in one product is how a 0.85 ends up rendered as 85%-of-inputs-read.
 *
 * Penalties then dock the base for known weaknesses in the sources
 * themselves — OCR'd text, a secondary source, an unbuildable peer set. They
 * are the reason coverage is not simply a percentage: 100% of the weight
 * populated from photographs of a scanned prospectus is not full coverage.
 */
final class CoverageCalculator
{
    public function __construct(
        private readonly WeightedAggregator $aggregator = new WeightedAggregator,
    ) {}

    /**
     * @param  array<string, int|float>  $weights
     * @param  array<string, int|null>  $scores
     * @param  array<string, int>  $penaltyCounts  penalty key => occurrences
     */
    public function calculate(array $weights, array $scores, array $penaltyCounts = []): int
    {
        $coverage = $this->baseCoverage($weights, $scores) + $this->penaltyTotal($penaltyCounts);

        return (int) max(0, min(100, $coverage));
    }

    /**
     * @param  array<string, int|float>  $weights
     * @param  array<string, int|null>  $scores
     */
    public function baseCoverage(array $weights, array $scores): int
    {
        $applicable = $this->aggregator->applicableWeight($weights);

        if ($applicable <= 0.0) {
            return 0;
        }

        $populated = $this->aggregator->populatedWeight($weights, $scores);

        return (int) round(100.0 * $populated / $applicable);
    }

    /**
     * @param  array<string, int>  $penaltyCounts
     * @return int always <= 0
     */
    public function penaltyTotal(array $penaltyCounts): int
    {
        $penalties = (array) config('ipo_risk.coverage.penalties', []);
        $caps = (array) config('ipo_risk.coverage.penalty_caps', []);

        $total = 0;

        foreach ($penaltyCounts as $key => $occurrences) {
            if (! array_key_exists($key, $penalties)) {
                throw new InvalidArgumentException("Unknown coverage penalty '{$key}' — not in ipo_risk.coverage.penalties.");
            }

            if ($occurrences < 0) {
                throw new InvalidArgumentException("Coverage penalty '{$key}' cannot occur a negative number of times.");
            }

            $amount = (int) $penalties[$key] * $occurrences;

            // Both sides are negative, so the cap is the larger number.
            if (array_key_exists($key, $caps)) {
                $amount = max($amount, (int) $caps[$key]);
            }

            $total += $amount;
        }

        return $total;
    }

    /**
     * I8 — below the threshold the score exists but is not presented as a
     * verdict. A SEVERE-looking number off a fifth of the inputs describes
     * the document set, not the company.
     */
    public function isSuppressed(int $coverageScore): bool
    {
        return $coverageScore < (int) config('ipo_risk.coverage.suppression_threshold', 40);
    }
}
