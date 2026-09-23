<?php

declare(strict_types=1);

namespace App\Services\IpoRisk\Scoring;

use App\Services\IpoRisk\DTOs\GateResult;
use InvalidArgumentException;

/**
 * Hard gates clamp. They never average.
 *
 *     final = max(weighted_score, highest fired gate floor)
 *
 * A gate exists precisely because some observations must not be survivable by
 * scoring well elsewhere. Going concern doubt does not become a 40 because the
 * balance sheet is tidy; it stays at or above 90. Feeding a gate into the
 * weighted mean as a high-scoring factor would let the other 90% of the weight
 * dilute it, which is the exact failure the gate is there to prevent.
 *
 * Note the asymmetry: a gate raises, never lowers. A weighted 90 against a
 * floor of 70 stays 90, because the floor says "at least this bad", not
 * "exactly this bad".
 */
final class GateClamp
{
    /**
     * @param  array<int, GateResult>  $gates  the gates that fired
     */
    public function apply(?int $weightedScore, array $gates): ?int
    {
        $floor = $this->highestFloor($gates);

        if ($floor === null) {
            return $weightedScore;
        }

        // A gate that fired is direct evidence, and it stands whether or not
        // any weighted factor could be populated. The engine only reaches
        // this branch when an evaluator found something in the filing.
        if ($weightedScore === null) {
            return $floor;
        }

        return max($weightedScore, $floor);
    }

    /**
     * @param  array<int, GateResult>  $gates
     */
    public function highestFloor(array $gates): ?int
    {
        return $this->highest($gates)?->floor;
    }

    /**
     * The gate doing the clamping — the one worth naming in the output.
     *
     * @param  array<int, GateResult>  $gates
     */
    public function highest(array $gates): ?GateResult
    {
        $highest = null;

        foreach ($gates as $gate) {
            if (! $gate instanceof GateResult) {
                throw new InvalidArgumentException('GateClamp expects GateResult instances.');
            }

            if ($highest === null || $gate->floor > $highest->floor) {
                $highest = $gate;
            }
        }

        return $highest;
    }
}
