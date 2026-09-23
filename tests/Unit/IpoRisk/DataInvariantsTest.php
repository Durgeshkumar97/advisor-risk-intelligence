<?php

declare(strict_types=1);

use App\Services\IpoRisk\DTOs\FactorResult;
use App\Services\IpoRisk\DTOs\GateResult;
use App\Services\IpoRisk\DTOs\IpoInputs;
use App\Services\IpoRisk\DTOs\IpoRiskResult;
use App\Services\IpoRisk\DTOs\Provenance;
use App\Services\IpoRisk\DTOs\SentimentPanel;
use App\Services\IpoRisk\Enums\Actionability;
use App\Services\IpoRisk\Enums\CoverageBand;
use App\Services\IpoRisk\Enums\FactorFamily;
use App\Services\IpoRisk\Enums\IpoState;

uses(\Tests\TestCase::class);

function prov(): Provenance
{
    return Provenance::of('RHP', 214, new DateTimeImmutable('2026-03-01T00:00:00+00:00'));
}

// ---------------------------------------------------------------------------
// Provenance
// ---------------------------------------------------------------------------

it('rejects an empty source document', function () {
    new Provenance('   ', 1, new DateTimeImmutable);
})->throws(InvalidArgumentException::class, 'non-empty source_document');

it('rejects a non-positive source page', function () {
    new Provenance('RHP', 0, new DateTimeImmutable);
})->throws(InvalidArgumentException::class, 'positive page number');

it('allows a source without a page, because not every source is paginated', function () {
    expect(Provenance::of('NSE bhavcopy')->sourcePage)->toBeNull();
});

it('serialises provenance with snake_case keys', function () {
    expect(prov()->toArray())->toBe([
        'source_document' => 'RHP',
        'source_page' => 214,
        'as_of' => '2026-03-01T00:00:00+00:00',
    ]);
});

// ---------------------------------------------------------------------------
// I5 — a factor score without provenance is a hard failure
// ---------------------------------------------------------------------------

it('refuses a factor score with no provenance', function () {
    new FactorResult(
        code: 'EQ_RECEIVABLE_SHARE',
        family: FactorFamily::EARNINGS_QUALITY,
        value: 0.98,
        score: 82,
        provenance: null,
    );
})->throws(InvalidArgumentException::class, 'has a score but no provenance');

it('accepts a factor score carrying provenance', function () {
    $factor = FactorResult::populated('EQ_RECEIVABLE_SHARE', FactorFamily::EARNINGS_QUALITY, 0.98, 82, prov());

    expect($factor->isPopulated())->toBeTrue()
        ->and($factor->score)->toBe(82)
        ->and($factor->provenance?->sourceDocument)->toBe('RHP');
});

it('refuses an absent factor with no null reason', function () {
    new FactorResult(code: 'EQ_RECEIVABLE_SHARE', family: FactorFamily::EARNINGS_QUALITY);
})->throws(InvalidArgumentException::class, 'no score and no null_reason');

it('refuses a factor claiming both a score and a null reason', function () {
    new FactorResult(
        code: 'EQ_RECEIVABLE_SHARE',
        family: FactorFamily::EARNINGS_QUALITY,
        score: 40,
        provenance: prov(),
        nullReason: 'not disclosed',
    );
})->throws(InvalidArgumentException::class, 'both a score and a null_reason');

it('records an absent factor with the reason it is absent', function () {
    $factor = FactorResult::absent('VAL_PEER_PE', FactorFamily::VALUATION, 'Peer set could not be built.');

    expect($factor->isPopulated())->toBeFalse()
        ->and($factor->score)->toBeNull()
        ->and($factor->riskLevel)->toBeNull()
        ->and($factor->nullReason)->toBe('Peer set could not be built.');
});

it('clamps nothing — an out-of-range factor score is rejected, not silently fixed', function (int $score) {
    FactorResult::populated('X', FactorFamily::GOVERNANCE, null, $score, prov());
})->with([[-1], [101]])->throws(InvalidArgumentException::class, 'must be 0-100');

it('rejects an empty factor code', function () {
    FactorResult::absent(' ', FactorFamily::GOVERNANCE, 'nope');
})->throws(InvalidArgumentException::class, 'non-empty code');

it('derives a factor risk level from the canonical mapping', function () {
    $factor = FactorResult::populated('X', FactorFamily::GOVERNANCE, null, 82, prov());

    expect($factor->riskLevel)->toBe(\App\Models\RiskScore::levelFromScore(82.0));
});

// ---------------------------------------------------------------------------
// GateResult — provenance is structurally required
// ---------------------------------------------------------------------------

it('reads a gate floor from the ruleset rather than the caller', function () {
    $gate = GateResult::forCode('GOING_CONCERN', 'Auditor flagged material uncertainty.', prov());

    expect($gate->floor)->toBe(90)->toBe(config('ipo_risk.gate_floors.GOING_CONCERN'));
});

it('refuses a gate code that has no floor in the ruleset', function () {
    GateResult::forCode('VIBES_OFF', 'n/a', prov());
})->throws(InvalidArgumentException::class, 'Unknown gate code');

it('refuses a gate with no evidence', function () {
    new GateResult('GOING_CONCERN', 90, '  ', prov());
})->throws(InvalidArgumentException::class, 'requires non-empty evidence');

it('refuses a gate floor outside 0-100', function () {
    new GateResult('GOING_CONCERN', 140, 'x', prov());
})->throws(InvalidArgumentException::class, 'floor must be 0-100');

// ---------------------------------------------------------------------------
// I7 — GMP is structurally unscorable
// ---------------------------------------------------------------------------

it('holds grey-market premium as an unscored constant', function () {
    expect(SentimentPanel::SCORED)->toBeFalse()
        ->and(SentimentPanel::empty()->toArray()['scored'])->toBeFalse();
});

it('reports whether grey-market premium was observed at all', function () {
    expect(SentimentPanel::empty()->isObserved())->toBeFalse()
        ->and((new SentimentPanel(gmpValue: 42.5))->isObserved())->toBeTrue();
});

// ---------------------------------------------------------------------------
// I4 — risk_score cannot exist without coverage_score
// ---------------------------------------------------------------------------

it('refuses a risk score with no coverage score', function () {
    new IpoRiskResult(state: IpoState::PRE_ISSUE, riskScore: 61, coverageScore: null);
})->throws(InvalidArgumentException::class, 'without a coverage_score');

it('allows a coverage score with no risk score, which is what an unreadable filing looks like', function () {
    $result = new IpoRiskResult(state: IpoState::PRE_ISSUE, riskScore: null, coverageScore: 0);

    expect($result->riskScore)->toBeNull()
        ->and($result->coverageScore)->toBe(0)
        ->and($result->coverageBand)->toBe(CoverageBand::INSUFFICIENT)
        ->and($result->isScored())->toBeFalse();
});

it('refuses out-of-range result scores', function (?int $risk, ?int $coverage) {
    new IpoRiskResult(state: IpoState::PRE_ISSUE, riskScore: $risk, coverageScore: $coverage);
})->with([
    'risk too high' => [101, 90],
    'risk negative' => [-1, 90],
    'coverage too high' => [null, 101],
    'coverage negative' => [null, -5],
])->throws(InvalidArgumentException::class, 'must be 0-100');

// ---------------------------------------------------------------------------
// Derived fields cannot be handed in, so they cannot disagree
// ---------------------------------------------------------------------------

it('derives actionability from state, not from the score', function () {
    $low = new IpoRiskResult(state: IpoState::ALLOTTED_UNLISTED, riskScore: 3, coverageScore: 95);
    $high = new IpoRiskResult(state: IpoState::ALLOTTED_UNLISTED, riskScore: 97, coverageScore: 95);

    expect($low->actionability)->toBe(Actionability::NO_EXIT_AVAILABLE)
        ->and($high->actionability)->toBe(Actionability::NO_EXIT_AVAILABLE);
});

it('derives risk level from the canonical mapping', function () {
    $result = new IpoRiskResult(state: IpoState::LISTED_HELD, riskScore: 72, coverageScore: 90);

    expect($result->riskLevel)->toBe(\App\Models\RiskScore::levelFromScore(72.0));
});

// I8
it('suppresses the score below the coverage threshold', function () {
    $thin = new IpoRiskResult(state: IpoState::PRE_ISSUE, riskScore: 70, coverageScore: 39);
    $thick = new IpoRiskResult(state: IpoState::PRE_ISSUE, riskScore: 70, coverageScore: 40);

    expect($thin->scoreSuppressed)->toBeTrue()
        ->and($thick->scoreSuppressed)->toBeFalse();
});

it('rejects factors or gates of the wrong type', function (array $factors, array $gates) {
    new IpoRiskResult(
        state: IpoState::PRE_ISSUE,
        riskScore: null,
        coverageScore: 0,
        factors: $factors,
        gates: $gates,
    );
})->with([
    'bad factor' => [['nope'], []],
    'bad gate' => [[], ['nope']],
])->throws(InvalidArgumentException::class);

it('serialises the full output contract with both scores present', function () {
    $result = new IpoRiskResult(
        state: IpoState::LISTED_HELD,
        riskScore: 55,
        coverageScore: 84,
        factors: [FactorResult::absent('LIQ_ADV', FactorFamily::LIQUIDITY, 'No traded history yet.')],
        gates: [GateResult::forCode('ASM_GSM_FLAGGED', 'Moved to GSM Stage I.', prov())],
        rulesetHash: str_repeat('a', 64),
        generatedAt: new DateTimeImmutable('2026-04-02T09:30:00+00:00'),
    );

    $array = $result->toArray();

    expect($array)->toHaveKeys([
        'state', 'actionability', 'risk_score', 'risk_level',
        'coverage_score', 'coverage_band', 'score_suppressed',
        'factors', 'gates', 'sentiment', 'ruleset_hash', 'generated_at',
    ])
        ->and($array['state'])->toBe('listed_held')
        ->and($array['actionability'])->toBe('exit_available')
        ->and($array['coverage_band'])->toBe('high')
        ->and($array['score_suppressed'])->toBeFalse()
        ->and($array['sentiment']['scored'])->toBeFalse()
        ->and($array['generated_at'])->toBe('2026-04-02T09:30:00+00:00')
        ->and($result->populatedFactors())->toBeEmpty();
});

it('builds an unscored result for a filing nothing could be read from', function () {
    $result = IpoRiskResult::unscored(IpoState::PRE_ISSUE, 'hash');

    expect($result->riskScore)->toBeNull()
        ->and($result->coverageScore)->toBe(0)
        ->and($result->coverageBand)->toBe(CoverageBand::INSUFFICIENT)
        ->and($result->scoreSuppressed)->toBeTrue();
});

// ---------------------------------------------------------------------------
// IpoInputs
// ---------------------------------------------------------------------------

it('treats a key present with a null value as still absent', function () {
    $inputs = new IpoInputs(state: IpoState::PRE_ISSUE, facts: ['promoter_holding' => null, 'ocf_fy24' => 0]);

    expect($inputs->has('promoter_holding'))->toBeFalse()
        ->and($inputs->has('ocf_fy24'))->toBeTrue()
        ->and($inputs->fact('ocf_fy24'))->toBe(0)
        ->and($inputs->fact('missing', 'fallback'))->toBe('fallback');
});

it('rejects a negative coverage penalty count', function () {
    new IpoInputs(state: IpoState::PRE_ISSUE, coveragePenalties: ['ocr_source' => -1]);
})->throws(InvalidArgumentException::class, 'non-negative integer count');

it('always hands back a sentiment panel, observed or not', function () {
    expect((new IpoInputs(state: IpoState::PRE_ISSUE))->sentiment())
        ->toBeInstanceOf(SentimentPanel::class);
});

it('merges facts without mutating the original inputs', function () {
    $inputs = new IpoInputs(state: IpoState::PRE_ISSUE, facts: ['a' => 1]);
    $merged = $inputs->withFacts(['b' => 2]);

    expect($inputs->facts)->toBe(['a' => 1])
        ->and($merged->facts)->toBe(['a' => 1, 'b' => 2]);
});
