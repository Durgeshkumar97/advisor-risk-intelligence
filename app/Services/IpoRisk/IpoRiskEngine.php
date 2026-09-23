<?php

declare(strict_types=1);

namespace App\Services\IpoRisk;

use App\Services\IpoRisk\Contracts\FactorScorer;
use App\Services\IpoRisk\Contracts\GateEvaluator;
use App\Services\IpoRisk\DTOs\FactorResult;
use App\Services\IpoRisk\DTOs\GateResult;
use App\Services\IpoRisk\DTOs\IpoInputs;
use App\Services\IpoRisk\DTOs\IpoRiskResult;
use App\Services\IpoRisk\Enums\FactorFamily;
use App\Services\IpoRisk\Scoring\CoverageCalculator;
use App\Services\IpoRisk\Scoring\GateClamp;
use App\Services\IpoRisk\Scoring\WeightedAggregator;
use InvalidArgumentException;

/**
 * Orchestrates a scoring run. Owns no thresholds of its own.
 *
 * Every number comes from a registered scorer, a registered gate, or config.
 * The engine's job is sequencing and refusal: run the scorers that apply to
 * the state, aggregate over what was populated, let gates clamp the result,
 * measure coverage, and stamp the ruleset hash.
 *
 * Part A registers nothing. An engine with no scorers is a valid engine and
 * returns an honestly unscored result — that is invariant I3, and it is the
 * behaviour every later part has to keep.
 */
final class IpoRiskEngine
{
    /** Codes beginning with this are structurally unscorable. */
    public const UNSCORABLE_CODE_PREFIX = 'GMP';

    /** @var array<string, FactorScorer> */
    private array $scorers = [];

    /** @var array<int, GateEvaluator> */
    private array $gates = [];

    public function __construct(
        private readonly WeightedAggregator $aggregator = new WeightedAggregator,
        private readonly GateClamp $gateClamp = new GateClamp,
        private readonly CoverageCalculator $coverage = new CoverageCalculator,
        private readonly RulesetHasher $hasher = new RulesetHasher,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | REGISTRATION
    |--------------------------------------------------------------------------
    */

    public function registerScorer(FactorScorer $scorer): self
    {
        $code = trim($scorer->code());

        if ($code === '') {
            throw new InvalidArgumentException('A factor scorer must declare a non-empty code.');
        }

        // I7 — grey-market premium is observed, never scored. Enforced at
        // registration rather than by convention, because the cheapest way
        // for GMP to end up in the score is for someone to write a perfectly
        // reasonable-looking GmpTrendScorer.
        if (str_starts_with(strtoupper($code), self::UNSCORABLE_CODE_PREFIX)) {
            throw new InvalidArgumentException(
                "Factor code {$code} is refused: grey-market premium is observational and is never scored."
            );
        }

        if (isset($this->scorers[$code])) {
            throw new InvalidArgumentException("Factor code {$code} is already registered.");
        }

        $this->scorers[$code] = $scorer;

        return $this;
    }

    public function registerGate(GateEvaluator $gate): self
    {
        $this->gates[] = $gate;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function scorerCodes(): array
    {
        return array_keys($this->scorers);
    }

    public function gateCount(): int
    {
        return count($this->gates);
    }

    /*
    |--------------------------------------------------------------------------
    | EVALUATION
    |--------------------------------------------------------------------------
    */

    public function evaluate(IpoInputs $inputs): IpoRiskResult
    {
        $weights = $this->weightsFor($inputs);
        $factors = $this->runScorers($inputs);
        $familyScores = $this->familyScores($weights, $factors);

        $weighted = $this->aggregator->aggregate($weights, $familyScores);
        $gates = $this->runGates($inputs);

        return new IpoRiskResult(
            state: $inputs->state,
            riskScore: $this->gateClamp->apply($weighted, $gates),
            coverageScore: $this->coverage->calculate($weights, $familyScores, $inputs->coveragePenalties),
            factors: $factors,
            gates: $gates,
            sentiment: $inputs->sentiment(),
            rulesetHash: $this->hasher->hash(),
        );
    }

    /**
     * @return array<string, int>
     */
    private function weightsFor(IpoInputs $inputs): array
    {
        $weights = [];

        foreach (FactorFamily::forState($inputs->state) as $family) {
            $weights[$family->value] = $family->weight();
        }

        return $weights;
    }

    /**
     * @return array<int, FactorResult>
     */
    private function runScorers(IpoInputs $inputs): array
    {
        $group = $inputs->state->familyGroup();
        $factors = [];

        foreach ($this->scorers as $code => $scorer) {
            // A listed-set scorer has nothing to say about a pre-issue
            // filing, and vice versa.
            if ($scorer->family()->group() !== $group) {
                continue;
            }

            $result = $scorer->score($inputs);

            if ($result->code !== $code) {
                throw new InvalidArgumentException(
                    "Scorer {$code} returned a result coded {$result->code}."
                );
            }

            if ($result->family !== $scorer->family()) {
                throw new InvalidArgumentException(
                    "Scorer {$code} returned a result in family {$result->family->value}."
                );
            }

            $factors[] = $result;
        }

        return $factors;
    }

    /**
     * Collapses factors to one score per family.
     *
     * PART B REPLACES THIS. Intra-family weighting is a Part B decision; until
     * the factors exist there is nothing to weight them by, so populated
     * factors within a family are averaged equally. A family with no populated
     * factor stays null and is renormalised away — it is never averaged as a
     * zero or a fifty.
     *
     * @param  array<string, int>  $weights
     * @param  array<int, FactorResult>  $factors
     * @return array<string, int|null>
     */
    private function familyScores(array $weights, array $factors): array
    {
        $collected = [];

        foreach ($factors as $factor) {
            if ($factor->isPopulated()) {
                $collected[$factor->family->value][] = $factor->score;
            }
        }

        $scores = [];

        foreach (array_keys($weights) as $family) {
            $populated = $collected[$family] ?? [];

            $scores[$family] = $populated === []
                ? null
                : (int) round(array_sum($populated) / count($populated));
        }

        return $scores;
    }

    /**
     * @return array<int, GateResult>
     */
    private function runGates(IpoInputs $inputs): array
    {
        $fired = [];

        foreach ($this->gates as $evaluator) {
            $gate = $evaluator->evaluate($inputs);

            if ($gate !== null) {
                $fired[] = $gate;
            }
        }

        return $fired;
    }
}
