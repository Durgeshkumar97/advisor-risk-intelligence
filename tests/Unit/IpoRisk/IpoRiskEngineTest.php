<?php

declare(strict_types=1);

use App\Services\IpoRisk\Contracts\FactorScorer;
use App\Services\IpoRisk\Contracts\GateEvaluator;
use App\Services\IpoRisk\DTOs\FactorResult;
use App\Services\IpoRisk\DTOs\GateResult;
use App\Services\IpoRisk\DTOs\IpoInputs;
use App\Services\IpoRisk\DTOs\Provenance;
use App\Services\IpoRisk\DTOs\SentimentPanel;
use App\Services\IpoRisk\Enums\Actionability;
use App\Services\IpoRisk\Enums\CoverageBand;
use App\Services\IpoRisk\Enums\FactorFamily;
use App\Services\IpoRisk\Enums\IpoState;
use App\Services\IpoRisk\IpoRiskEngine;

uses(\Tests\TestCase::class);

// ---------------------------------------------------------------------------
// Test doubles — Part A ships no real scorers
// ---------------------------------------------------------------------------

function stubScorer(string $code, FactorFamily $family, ?int $score, ?string $nullReason = 'Not disclosed.'): FactorScorer
{
    return new class($code, $family, $score, $nullReason) implements FactorScorer
    {
        public function __construct(
            private string $code,
            private FactorFamily $family,
            private ?int $score,
            private ?string $nullReason,
        ) {}

        public function code(): string
        {
            return $this->code;
        }

        public function family(): FactorFamily
        {
            return $this->family;
        }

        public function score(IpoInputs $inputs): FactorResult
        {
            if ($this->score === null) {
                return FactorResult::absent($this->code, $this->family, (string) $this->nullReason);
            }

            return FactorResult::populated(
                $this->code,
                $this->family,
                null,
                $this->score,
                Provenance::of('RHP', 4, new DateTimeImmutable('2026-03-01T00:00:00+00:00')),
            );
        }
    };
}

function stubGate(?string $code, int $floor = 90): GateEvaluator
{
    return new class($code, $floor) implements GateEvaluator
    {
        public function __construct(private ?string $code, private int $floor) {}

        public function evaluate(IpoInputs $inputs): ?GateResult
        {
            if ($this->code === null) {
                return null;
            }

            return new GateResult(
                code: $this->code,
                floor: $this->floor,
                evidence: 'Stub evidence.',
                provenance: Provenance::of('RHP', 9, new DateTimeImmutable('2026-03-01T00:00:00+00:00')),
            );
        }
    };
}

function preIssue(array $penalties = []): IpoInputs
{
    return new IpoInputs(state: IpoState::PRE_ISSUE, coveragePenalties: $penalties);
}

// ---------------------------------------------------------------------------
// I3 — an engine with nothing registered is honest about it
// ---------------------------------------------------------------------------

it('returns an unscored result when no scorer is registered', function () {
    $result = (new IpoRiskEngine)->evaluate(preIssue());

    expect($result->riskScore)->toBeNull()
        ->and($result->riskLevel)->toBeNull()
        ->and($result->coverageScore)->toBe(0)
        ->and($result->coverageBand)->toBe(CoverageBand::INSUFFICIENT)
        ->and($result->scoreSuppressed)->toBeTrue()
        ->and($result->factors)->toBe([])
        ->and($result->gates)->toBe([]);
});

it('stamps the ruleset hash even on an unscored result', function () {
    expect((new IpoRiskEngine)->evaluate(preIssue())->rulesetHash)->toMatch('/^[0-9a-f]{64}$/');
});

it('derives actionability for every state with nothing registered', function (IpoState $state, Actionability $expected) {
    expect((new IpoRiskEngine)->evaluate(new IpoInputs(state: $state))->actionability)->toBe($expected);
})->with([
    [IpoState::PRE_ISSUE, Actionability::NOT_YET_COMMITTED],
    [IpoState::ALLOTTED_UNLISTED, Actionability::NO_EXIT_AVAILABLE],
    [IpoState::LISTED_HELD, Actionability::EXIT_AVAILABLE],
]);

it('stays unscored when every registered scorer finds nothing', function () {
    $result = (new IpoRiskEngine)
        ->registerScorer(stubScorer('EQ_A', FactorFamily::EARNINGS_QUALITY, null))
        ->registerScorer(stubScorer('BS_A', FactorFamily::BALANCE_SHEET, null))
        ->evaluate(preIssue());

    expect($result->riskScore)->toBeNull()
        ->and($result->coverageScore)->toBe(0)
        ->and($result->factors)->toHaveCount(2)
        ->and($result->populatedFactors())->toBe([]);
});

// ---------------------------------------------------------------------------
// I7 — GMP cannot be registered
// ---------------------------------------------------------------------------

it('refuses to register a scorer whose code starts with GMP', function (string $code) {
    (new IpoRiskEngine)->registerScorer(stubScorer($code, FactorFamily::INSTITUTIONAL_DEMAND, 50));
})->with([
    'upper' => ['GMP_PREMIUM'],
    'lower' => ['gmp_trend'],
    'mixed' => ['GmpDrift'],
    'bare' => ['GMP'],
])->throws(InvalidArgumentException::class, 'never scored');

it('registers a code that merely contains GMP later on', function () {
    $engine = (new IpoRiskEngine)->registerScorer(stubScorer('DEMAND_GMP_UNUSED', FactorFamily::INSTITUTIONAL_DEMAND, 50));

    expect($engine->scorerCodes())->toBe(['DEMAND_GMP_UNUSED']);
});

it('carries the sentiment panel through unscored', function () {
    $inputs = new IpoInputs(
        state: IpoState::PRE_ISSUE,
        sentiment: new SentimentPanel(gmpValue: 120.0, gmpSource: 'unofficial dealer quote'),
    );

    $result = (new IpoRiskEngine)->evaluate($inputs);

    expect($result->sentiment->gmpValue)->toBe(120.0)
        ->and($result->sentiment->toArray()['scored'])->toBeFalse()
        ->and($result->riskScore)->toBeNull();
});

// ---------------------------------------------------------------------------
// Registration guards
// ---------------------------------------------------------------------------

it('refuses an empty scorer code', function () {
    (new IpoRiskEngine)->registerScorer(stubScorer('  ', FactorFamily::GOVERNANCE, 50));
})->throws(InvalidArgumentException::class, 'non-empty code');

it('refuses a duplicate scorer code', function () {
    (new IpoRiskEngine)
        ->registerScorer(stubScorer('EQ_A', FactorFamily::EARNINGS_QUALITY, 50))
        ->registerScorer(stubScorer('EQ_A', FactorFamily::BALANCE_SHEET, 50));
})->throws(InvalidArgumentException::class, 'already registered');

it('refuses a scorer that returns a result under another code', function () {
    $liar = new class implements FactorScorer
    {
        public function code(): string
        {
            return 'EQ_A';
        }

        public function family(): FactorFamily
        {
            return FactorFamily::EARNINGS_QUALITY;
        }

        public function score(IpoInputs $inputs): FactorResult
        {
            return FactorResult::absent('SOMETHING_ELSE', FactorFamily::EARNINGS_QUALITY, 'n/a');
        }
    };

    (new IpoRiskEngine)->registerScorer($liar)->evaluate(preIssue());
})->throws(InvalidArgumentException::class, 'returned a result coded');

it('refuses a scorer that returns a result in another family', function () {
    $liar = new class implements FactorScorer
    {
        public function code(): string
        {
            return 'EQ_A';
        }

        public function family(): FactorFamily
        {
            return FactorFamily::EARNINGS_QUALITY;
        }

        public function score(IpoInputs $inputs): FactorResult
        {
            return FactorResult::absent('EQ_A', FactorFamily::VALUATION, 'n/a');
        }
    };

    (new IpoRiskEngine)->registerScorer($liar)->evaluate(preIssue());
})->throws(InvalidArgumentException::class, 'returned a result in family');

it('counts registered gates', function () {
    expect((new IpoRiskEngine)->registerGate(stubGate(null))->gateCount())->toBe(1);
});

// ---------------------------------------------------------------------------
// End to end, with stubs
// ---------------------------------------------------------------------------

it('renormalises over populated families rather than defaulting the rest', function () {
    // earnings_quality 25 scored 100, balance_sheet 20 scored 40; rest absent.
    // (25*100 + 20*40) / 45 = 3300/45 = 73.33 -> 73
    $result = (new IpoRiskEngine)
        ->registerScorer(stubScorer('EQ_A', FactorFamily::EARNINGS_QUALITY, 100))
        ->registerScorer(stubScorer('BS_A', FactorFamily::BALANCE_SHEET, 40))
        ->registerScorer(stubScorer('GOV_A', FactorFamily::GOVERNANCE, null))
        ->evaluate(preIssue());

    expect($result->riskScore)->toBe(73)
        ->and($result->coverageScore)->toBe(45)
        ->and($result->scoreSuppressed)->toBeFalse()
        ->and($result->populatedFactors())->toHaveCount(2);
});

it('averages populated factors within one family', function () {
    // Both in earnings_quality (weight 25): mean(80, 40) = 60, and it is the
    // only populated family, so the final score is 60.
    $result = (new IpoRiskEngine)
        ->registerScorer(stubScorer('EQ_A', FactorFamily::EARNINGS_QUALITY, 80))
        ->registerScorer(stubScorer('EQ_B', FactorFamily::EARNINGS_QUALITY, 40))
        ->evaluate(preIssue());

    expect($result->riskScore)->toBe(60)
        ->and($result->coverageScore)->toBe(25);
});

it('lets a gate clamp a low weighted score upward', function () {
    $result = (new IpoRiskEngine)
        ->registerScorer(stubScorer('EQ_A', FactorFamily::EARNINGS_QUALITY, 10))
        ->registerGate(stubGate('GOING_CONCERN', 90))
        ->evaluate(preIssue());

    expect($result->riskScore)->toBe(90)
        ->and($result->gates)->toHaveCount(1)
        ->and($result->riskLevel)->toBe('HIGH');
});

it('leaves a weighted score above the gate floor alone', function () {
    $result = (new IpoRiskEngine)
        ->registerScorer(stubScorer('EQ_A', FactorFamily::EARNINGS_QUALITY, 95))
        ->registerGate(stubGate('NEGATIVE_CFO', 70))
        ->evaluate(preIssue());

    expect($result->riskScore)->toBe(95);
});

it('ignores a gate that did not fire', function () {
    $result = (new IpoRiskEngine)
        ->registerScorer(stubScorer('EQ_A', FactorFamily::EARNINGS_QUALITY, 10))
        ->registerGate(stubGate(null))
        ->evaluate(preIssue());

    expect($result->riskScore)->toBe(10)
        ->and($result->gates)->toBe([]);
});

it('skips scorers belonging to the other state family set', function () {
    $result = (new IpoRiskEngine)
        ->registerScorer(stubScorer('EQ_A', FactorFamily::EARNINGS_QUALITY, 80))
        ->registerScorer(stubScorer('LIQ_A', FactorFamily::LIQUIDITY, 20))
        ->evaluate(preIssue());

    expect($result->factors)->toHaveCount(1)
        ->and($result->riskScore)->toBe(80);
});

it('scores a listed holding off the listed family set', function () {
    $result = (new IpoRiskEngine)
        ->registerScorer(stubScorer('EQ_A', FactorFamily::EARNINGS_QUALITY, 80))
        ->registerScorer(stubScorer('LIQ_A', FactorFamily::LIQUIDITY, 20))
        ->evaluate(new IpoInputs(state: IpoState::LISTED_HELD));

    expect($result->factors)->toHaveCount(1)
        ->and($result->riskScore)->toBe(20)
        ->and($result->actionability)->toBe(Actionability::EXIT_AVAILABLE);
});

it('applies coverage penalties from the inputs', function () {
    $result = (new IpoRiskEngine)
        ->registerScorer(stubScorer('EQ_A', FactorFamily::EARNINGS_QUALITY, 100))
        ->registerScorer(stubScorer('BS_A', FactorFamily::BALANCE_SHEET, 40))
        ->evaluate(preIssue(['ocr_source' => 1]));

    expect($result->coverageScore)->toBe(35)
        ->and($result->scoreSuppressed)->toBeTrue()
        ->and($result->riskScore)->toBe(73);  // suppression hides it, it does not erase it
});

it('keeps a gate-clamped score suppressed when coverage is thin', function () {
    $result = (new IpoRiskEngine)
        ->registerGate(stubGate('REGULATOR_ACTION', 90))
        ->evaluate(preIssue());

    expect($result->riskScore)->toBe(90)
        ->and($result->coverageScore)->toBe(0)
        ->and($result->scoreSuppressed)->toBeTrue();
});
