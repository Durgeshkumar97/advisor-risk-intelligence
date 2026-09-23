<?php

declare(strict_types=1);

namespace App\Services\IpoRisk\Scoring;

use App\Models\RiskScore;
use App\Services\IpoRisk\Enums\CoverageBand;

/**
 * Score to label, for both of the module's numbers.
 *
 * The risk half deliberately owns nothing. It forwards to
 * App\Models\RiskScore::levelFromScore(), whose docblock records that its
 * boundaries were once duplicated and drifted — a score of exactly 30 read as
 * LOW in one place and MEDIUM in another, and the PDF could print a different
 * level than the engine computed. This module declines to repeat that: no
 * band edges in config/ipo_risk.php, no RiskBand enum, one mapping.
 *
 * (RulesetHasher folds risk.low_threshold and risk.high_threshold into the
 * ruleset hash, so borrowing the mapping does not cost reproducibility.)
 *
 * The coverage half does own its edges, because nothing else in the codebase
 * measures evidence completeness.
 */
final class BandMapper
{
    /**
     * LOW | MEDIUM | HIGH, or null for an unscored result.
     */
    public static function riskLevel(?int $riskScore): ?string
    {
        return $riskScore === null
            ? null
            : RiskScore::levelFromScore((float) $riskScore);
    }

    /**
     * An unscored result has read nothing, which is INSUFFICIENT rather than
     * unknown.
     */
    public static function coverageBand(?int $coverageScore): CoverageBand
    {
        return CoverageBand::fromScore($coverageScore ?? 0);
    }
}
