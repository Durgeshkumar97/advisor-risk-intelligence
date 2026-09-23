<?php

declare(strict_types=1);

namespace App\Services\IpoRisk\Scoring;

/**
 * The weighted mean, taken over what was actually populated.
 *
 * The whole point of this class is the thing it refuses to do: an absent
 * input does not become 50, does not become 0, and does not drag the mean
 * toward the middle. It is removed, and the remaining weights are
 * renormalised over themselves.
 *
 * So {A: weight 30, score 100} with {B: weight 70, score null} is 100 — the
 * only evidence there is says 100. It is not 30 (treating B as 0) and not 65
 * (treating B as an average issue). Whether 100-on-thin-evidence should be
 * shown at all is not this class's call; that is what coverage_score and
 * score_suppressed are for.
 */
final class WeightedAggregator
{
    /**
     * @param  array<string, int|float>  $weights  key => weight
     * @param  array<string, int|null>  $scores  key => score, or null when absent
     * @return int|null 0-100, or null when no weighted input was populated
     */
    public function aggregate(array $weights, array $scores): ?int
    {
        $weightedTotal = 0.0;
        $weightTotal = 0.0;

        foreach ($weights as $key => $weight) {
            $weight = (float) $weight;

            if ($weight <= 0.0) {
                continue;
            }

            $score = $scores[$key] ?? null;

            if ($score === null) {
                continue;
            }

            $weightedTotal += $weight * $this->clamp((float) $score);
            $weightTotal += $weight;
        }

        if ($weightTotal <= 0.0) {
            return null;
        }

        return (int) round($this->clamp($weightedTotal / $weightTotal));
    }

    /**
     * Weight that carried a populated score — the numerator of coverage.
     *
     * @param  array<string, int|float>  $weights
     * @param  array<string, int|null>  $scores
     */
    public function populatedWeight(array $weights, array $scores): float
    {
        $total = 0.0;

        foreach ($weights as $key => $weight) {
            $weight = (float) $weight;

            if ($weight > 0.0 && ($scores[$key] ?? null) !== null) {
                $total += $weight;
            }
        }

        return $total;
    }

    /**
     * Weight that could have carried a score — the denominator of coverage.
     *
     * @param  array<string, int|float>  $weights
     */
    public function applicableWeight(array $weights): float
    {
        $total = 0.0;

        foreach ($weights as $weight) {
            $weight = (float) $weight;

            if ($weight > 0.0) {
                $total += $weight;
            }
        }

        return $total;
    }

    /**
     * I11 — a score leaving here is always an integer 0-100.
     *
     * Clamping belongs here rather than in FactorResult: a scorer handing
     * back 120 is a bug and FactorResult rejects it, but a ratio-derived
     * intermediate landing at 100.4 is ordinary arithmetic.
     */
    private function clamp(float $score): float
    {
        return max(0.0, min(100.0, $score));
    }
}
