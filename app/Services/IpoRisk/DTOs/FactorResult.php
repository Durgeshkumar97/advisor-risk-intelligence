<?php

declare(strict_types=1);

namespace App\Services\IpoRisk\DTOs;

use App\Models\RiskScore;
use App\Services\IpoRisk\Enums\FactorFamily;
use InvalidArgumentException;

/**
 * One factor's contribution, populated or not.
 *
 * The type enforces that a factor is in exactly one of two honest states:
 *
 *   populated — a score, and a Provenance saying where it came from
 *   absent    — no score, and a null_reason saying why
 *
 * There is no third state where a number appears with nothing behind it, and
 * no state where an absent factor quietly becomes 50.
 */
final readonly class FactorResult
{
    /** Derived, never passed in — see RiskScore::levelFromScore(). */
    public ?string $riskLevel;

    public function __construct(
        public string $code,
        public FactorFamily $family,
        public mixed $value = null,
        public ?int $score = null,
        public ?Provenance $provenance = null,
        public ?string $nullReason = null,
    ) {
        if (trim($code) === '') {
            throw new InvalidArgumentException('FactorResult requires a non-empty code.');
        }

        if ($score !== null && ($score < 0 || $score > 100)) {
            throw new InvalidArgumentException("FactorResult {$code} score must be 0-100, got {$score}.");
        }

        // I5 — a score without provenance is a hard failure, not a warning.
        if ($score !== null && $provenance === null) {
            throw new InvalidArgumentException("FactorResult {$code} has a score but no provenance.");
        }

        // The mirror rule: an absent factor has to say why it is absent,
        // otherwise a silent extraction bug is indistinguishable from a fact
        // the filing genuinely does not disclose.
        if ($score === null && trim((string) $nullReason) === '') {
            throw new InvalidArgumentException("FactorResult {$code} has no score and no null_reason.");
        }

        if ($score !== null && $nullReason !== null) {
            throw new InvalidArgumentException("FactorResult {$code} has both a score and a null_reason.");
        }

        $this->riskLevel = $score === null
            ? null
            : RiskScore::levelFromScore((float) $score);
    }

    /*
    |--------------------------------------------------------------------------
    | FACTORIES
    |--------------------------------------------------------------------------
    */

    public static function populated(
        string $code,
        FactorFamily $family,
        mixed $value,
        int $score,
        Provenance $provenance,
    ): self {
        return new self(
            code: $code,
            family: $family,
            value: $value,
            score: $score,
            provenance: $provenance,
        );
    }

    public static function absent(string $code, FactorFamily $family, string $nullReason): self
    {
        return new self(
            code: $code,
            family: $family,
            nullReason: $nullReason,
        );
    }

    public function isPopulated(): bool
    {
        return $this->score !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'family' => $this->family->value,
            'value' => $this->value,
            'score' => $this->score,
            'risk_level' => $this->riskLevel,
            'provenance' => $this->provenance?->toArray(),
            'null_reason' => $this->nullReason,
        ];
    }
}
