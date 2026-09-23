<?php

declare(strict_types=1);

namespace App\Services\IpoRisk\DTOs;

use App\Models\RiskScore;
use App\Services\IpoRisk\Enums\Actionability;
use App\Services\IpoRisk\Enums\CoverageBand;
use App\Services\IpoRisk\Enums\IpoState;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * The module's whole output contract.
 *
 * Two numbers, never one. A risk score without a coverage score is a claim
 * about an issue made without saying how much of the issue was actually read,
 * so the constructor refuses it outright.
 *
 * Everything derivable is derived here rather than accepted as an argument —
 * actionability from state, risk_level from risk_score, coverage_band and
 * score_suppressed from coverage_score. A caller cannot hand in a result whose
 * label disagrees with its number, because a caller cannot hand in a label.
 */
final readonly class IpoRiskResult
{
    public Actionability $actionability;

    /** LOW | MEDIUM | HIGH, from RiskScore::levelFromScore(). Null when unscored. */
    public ?string $riskLevel;

    public CoverageBand $coverageBand;

    public bool $scoreSuppressed;

    public SentimentPanel $sentiment;

    public DateTimeImmutable $generatedAt;

    /**
     * @param  array<int, FactorResult>  $factors
     * @param  array<int, GateResult>  $gates
     */
    public function __construct(
        public IpoState $state,
        public ?int $riskScore,
        public ?int $coverageScore,
        public array $factors = [],
        public array $gates = [],
        ?SentimentPanel $sentiment = null,
        public string $rulesetHash = '',
        ?DateTimeImmutable $generatedAt = null,
    ) {
        // I4 — the two scores travel together. The reverse is legal and
        // expected: coverage 0 with no risk score is what an unreadable
        // document set looks like.
        if ($riskScore !== null && $coverageScore === null) {
            throw new InvalidArgumentException('IpoRiskResult cannot carry a risk_score without a coverage_score.');
        }

        if ($riskScore !== null && ($riskScore < 0 || $riskScore > 100)) {
            throw new InvalidArgumentException("IpoRiskResult risk_score must be 0-100, got {$riskScore}.");
        }

        if ($coverageScore !== null && ($coverageScore < 0 || $coverageScore > 100)) {
            throw new InvalidArgumentException("IpoRiskResult coverage_score must be 0-100, got {$coverageScore}.");
        }

        foreach ($factors as $factor) {
            if (! $factor instanceof FactorResult) {
                throw new InvalidArgumentException('IpoRiskResult factors must all be FactorResult instances.');
            }
        }

        foreach ($gates as $gate) {
            if (! $gate instanceof GateResult) {
                throw new InvalidArgumentException('IpoRiskResult gates must all be GateResult instances.');
            }
        }

        $this->actionability = $state->actionability();

        $this->riskLevel = $riskScore === null
            ? null
            : RiskScore::levelFromScore((float) $riskScore);

        $this->coverageBand = CoverageBand::fromScore($coverageScore ?? 0);

        // I8 — thin evidence does not get to look like a confident verdict.
        $this->scoreSuppressed = ($coverageScore ?? 0)
            < (int) config('ipo_risk.coverage.suppression_threshold', 40);

        $this->sentiment = $sentiment ?? SentimentPanel::empty();
        $this->generatedAt = $generatedAt ?? new DateTimeImmutable;
    }

    /*
    |--------------------------------------------------------------------------
    | FACTORY — nothing could be read
    |--------------------------------------------------------------------------
    */

    public static function unscored(IpoState $state, string $rulesetHash = ''): self
    {
        return new self(
            state: $state,
            riskScore: null,
            coverageScore: 0,
            rulesetHash: $rulesetHash,
        );
    }

    public function isScored(): bool
    {
        return $this->riskScore !== null;
    }

    /**
     * @return array<int, FactorResult>
     */
    public function populatedFactors(): array
    {
        return array_values(array_filter(
            $this->factors,
            static fn (FactorResult $factor): bool => $factor->isPopulated(),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'state' => $this->state->value,
            'actionability' => $this->actionability->value,
            'risk_score' => $this->riskScore,
            'risk_level' => $this->riskLevel,
            'coverage_score' => $this->coverageScore,
            'coverage_band' => $this->coverageBand->value,
            'score_suppressed' => $this->scoreSuppressed,
            'factors' => array_map(static fn (FactorResult $f): array => $f->toArray(), $this->factors),
            'gates' => array_map(static fn (GateResult $g): array => $g->toArray(), $this->gates),
            'sentiment' => $this->sentiment->toArray(),
            'ruleset_hash' => $this->rulesetHash,
            'generated_at' => $this->generatedAt->format(DATE_ATOM),
        ];
    }
}
