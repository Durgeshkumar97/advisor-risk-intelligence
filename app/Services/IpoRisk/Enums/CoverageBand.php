<?php

declare(strict_types=1);

namespace App\Services\IpoRisk\Enums;

/**
 * How much of the applicable input weight was actually populated.
 *
 * This describes the evidence, not the issue. A SEVERE-looking risk score on
 * INSUFFICIENT coverage is a statement about a thin document set, which is
 * why it is suppressed rather than displayed.
 *
 * Distinct from the portfolio engine's per-asset `confidence` float, which is
 * an ML model's confidence in one prediction. Different thing, different
 * scale; deliberately a different word.
 */
enum CoverageBand: string
{
    case HIGH = 'high';
    case MODERATE = 'moderate';
    case LIMITED = 'limited';
    case INSUFFICIENT = 'insufficient';

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    public function label(): string
    {
        return match ($this) {
            self::HIGH => 'High coverage',
            self::MODERATE => 'Moderate coverage',
            self::LIMITED => 'Limited coverage',
            self::INSUFFICIENT => 'Insufficient coverage',
        };
    }

    /**
     * Edges come from config so they move with the ruleset hash.
     */
    public static function fromScore(int $coverageScore): self
    {
        $bands = (array) config('ipo_risk.coverage.bands', []);

        return match (true) {
            $coverageScore >= (int) ($bands['high'] ?? 80) => self::HIGH,
            $coverageScore >= (int) ($bands['moderate'] ?? 60) => self::MODERATE,
            $coverageScore >= (int) ($bands['limited'] ?? 40) => self::LIMITED,
            default => self::INSUFFICIENT,
        };
    }
}
