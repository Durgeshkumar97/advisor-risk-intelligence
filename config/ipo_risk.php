<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | FAMILY WEIGHTS
    |--------------------------------------------------------------------------
    |
    | Factor families and their share of the weighted score, keyed by which
    | set of factors applies to the IPO's state. PRE_ISSUE and
    | ALLOTTED_UNLISTED both score off the pre-listing set (nothing has
    | traded yet); LISTED_HELD scores off the listed set.
    |
    | Each set MUST sum to 100 — asserted in ConfigSkeletonTest. Weights are
    | renormalised at runtime over the factors that were actually populated,
    | so an absent factor never silently contributes a default score.
    |
    | There is deliberately NO `gmp` key here. Grey-market premium is
    | observed and displayed, never scored — see SentimentPanel::SCORED and
    | IpoRiskEngine::registerScorer()'s GMP guard.
    |
    */

    'families' => [

        'pre_listing' => [
            'earnings_quality' => 25,
            'balance_sheet' => 20,
            'governance' => 20,
            'issue_structure' => 15,
            'valuation' => 12,
            'institutional_demand' => 8,
        ],

        'listed' => [
            'liquidity' => 25,
            'lockin' => 20,
            'post_listing_delivery' => 20,
            'concentration' => 15,
            'governance_drift' => 12,
            'price_drift' => 8,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | HARD GATE FLOORS
    |--------------------------------------------------------------------------
    |
    | A fired gate clamps the final score UP to its floor — it never enters
    | the weighted mean and never averages away:
    |
    |     final = max(weighted_score, highest_fired_gate_floor)
    |
    | So a portfolio-of-evidence that scores 30 on the weighted factors still
    | reports 90 if the auditor flagged going concern.
    |
    */

    'gate_floors' => [
        'GOING_CONCERN' => 90,
        'NEGATIVE_CFO' => 70,
        'NEGATIVE_CFO_2YR' => 85,
        'INTEREST_COVER_SUB_1' => 85,
        'SINGLE_YEAR_DISCLOSURE' => 65,
        'PROMOTER_LITIGATION_CRIMINAL' => 80,
        'REGULATOR_ACTION' => 90,
        'RESTATEMENT_MATERIAL' => 70,
        'ASM_GSM_FLAGGED' => 80,
    ],

    /*
    |--------------------------------------------------------------------------
    | COVERAGE
    |--------------------------------------------------------------------------
    |
    | `coverage_score` is the share of applicable input weight that was
    | actually populated from source documents, 0-100 — NOT a measure of how
    | confident a model is in a prediction. (The portfolio engine's existing
    | per-asset `confidence` float is a different thing entirely: it comes
    | from the external ML risk_service. The two must not be conflated.)
    |
    | Penalties are subtracted from the populated-weight base. Each is
    | expressed per occurrence; `penalty_caps` bounds the ones that can fire
    | more than once.
    |
    */

    'coverage' => [

        'suppression_threshold' => 40,

        'penalties' => [
            'peer_set_failed' => -15,
            'under_two_years_audited' => -20,
            'ocr_source' => -10,
            'secondary_source' => -5,
            'stale_over_90_days' => -10,
        ],

        'penalty_caps' => [
            'secondary_source' => -20,
        ],

        'bands' => [
            'high' => 80,
            'moderate' => 60,
            'limited' => 40,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | RISK LEVEL — NOT OWNED HERE
    |--------------------------------------------------------------------------
    |
    | This module deliberately defines no risk-band edges. The canonical
    | score -> level mapping is App\Models\RiskScore::levelFromScore(), driven
    | by config/risk.php's `low_threshold` / `high_threshold`. A second set of
    | edges here would be exactly the drift that method's docblock warns
    | against.
    |
    | RulesetHasher folds the keys below into the ruleset hash so that an env
    | override which moves a boundary also changes the hash — the reproduced
    | score and the reproduced label stay in step.
    |
    */

    'bound_config_keys' => [
        'risk.low_threshold',
        'risk.high_threshold',
    ],

    /*
    |--------------------------------------------------------------------------
    | PROHIBITED LANGUAGE
    |--------------------------------------------------------------------------
    |
    | The operator is NOT a SEBI-registered Investment Adviser. Every string
    | this module puts in front of a user is observational: it states a
    | condition, it never prescribes an action.
    |
    | This list is the union of two origins:
    |
    |   1. The IPO module's own list (prescriptive / promotional vocabulary).
    |   2. The house rule already documented in
    |      PortfolioRiskCalculator::buildNextAction() (app/Services/RiskEngine/
    |      PortfolioRiskCalculator.php:382-392) and restated in
    |      ClientRiskProfile::comparisonMessage() — verbatim:
    |      "No 'should', 'consider', 'recommend', 'review', or 'discuss'."
    |
    | Where the two conflict, the house rule wins: the pre-existing engine
    | has shipped under it, and one product cannot hold two compliance bars.
    | There is no allowlist — a false positive is rephrased, not exempted.
    |
    | Matched case-insensitively on word boundaries; multi-word entries match
    | as phrases across any run of whitespace. Applied to user-facing
    | narrative only, never to code, config keys or enum values.
    |
    */

    'prohibited_terms' => [

        // --- IPO module list ---
        'buy',
        'sell',
        'avoid',
        'subscribe',
        'apply',
        'skip',
        'recommend',
        'advise',
        'should',
        'must',
        'worth it',
        'good investment',
        'bad investment',
        'safe',
        'guaranteed',
        'target price',
        'expected return',
        'will rise',
        'will fall',
        'undervalued',
        'overvalued',
        'opportunity',
        'attractive',
        'cheap',
        'expensive',
        'we suggest',
        'our view',

        // --- House rule (PortfolioRiskCalculator.php:382-392, ClientRiskProfile) ---
        // 'should' and 'recommend' are already listed above.
        'consider',
        'review',
        'discuss',

    ],

];
