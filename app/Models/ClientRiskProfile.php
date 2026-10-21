<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientRiskProfile extends Model
{
    protected $fillable = [
        'portfolio_id',
        'time_horizon',
        'income_stability',
        'drawdown_reaction',
        'emergency_savings',
        'primary_goal',
        'capacity_score',
    ];

    protected $casts = [
        'capacity_score' => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | QUESTIONNAIRE SCORING MAPS
    |--------------------------------------------------------------------------
    |
    | Each question's 1-based option index maps to a sub-score that spans a
    | genuine 0–100 (least-risk-tolerant option = 0, most = 100). Because the
    | plain average of five 0–100 values is itself 0–100, capacity_score lands
    | on the SAME 0–100 scale as PortfolioRiskCalculator's score — that is what
    | makes the two directly comparable in comparisonMessage(), with no
    | compressed range and no caveat.
    |
    | 4-option questions use 0/30/70/100 (the 30 and 70 stops line up with the
    | LOW/MEDIUM/HIGH thresholds in config/risk.php); 3-option questions use
    | 0/50/100. Deliberately simple and equally weighted — an MVP instrument,
    | not a psychometric one.
    |
    | These maps are also the SINGLE source of truth for which option indexes
    | are valid: StoreClientRiskProfileRequest derives its allowed values from
    | array_keys() of these constants, so validation can never drift from
    | scoring.
    |
    */

    public const TIME_HORIZON_SCORES = [
        1 => 0,   // < 2 years
        2 => 30,  // 2-5 years
        3 => 70,  // 5-10 years
        4 => 100, // 10+ years
    ];

    public const INCOME_STABILITY_SCORES = [
        1 => 0,   // unstable / variable
        2 => 50,  // stable, single income source
        3 => 100, // very stable (salaried/pension/multiple sources)
    ];

    public const DRAWDOWN_REACTION_SCORES = [
        1 => 0,   // sell immediately
        2 => 30,  // anxious but hold
        3 => 70,  // stay invested, unconcerned
        4 => 100, // buying opportunity
    ];

    public const EMERGENCY_SAVINGS_SCORES = [
        1 => 0,   // no emergency savings
        2 => 50,  // some/partial
        3 => 100, // yes, 3-6+ months
    ];

    public const PRIMARY_GOAL_SCORES = [
        1 => 0,   // capital preservation
        2 => 30,  // income generation
        3 => 70,  // balanced growth
        4 => 100, // aggressive growth
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class);
    }

    /*
    |--------------------------------------------------------------------------
    | SCORING
    |--------------------------------------------------------------------------
    */

    public static function computeCapacityScore(
        int $timeHorizon,
        int $incomeStability,
        int $drawdownReaction,
        int $emergencySavings,
        int $primaryGoal,
    ): float {
        // Resolve each answer through subScore() rather than indexing the maps
        // directly: an out-of-range option must fail LOUDLY. Silently reading a
        // missing key coerces to null -> 0 and skews a client's risk score
        // downward with no error — far worse than a thrown exception.
        $subScores = [
            self::subScore(self::TIME_HORIZON_SCORES, $timeHorizon, 'time_horizon'),
            self::subScore(self::INCOME_STABILITY_SCORES, $incomeStability, 'income_stability'),
            self::subScore(self::DRAWDOWN_REACTION_SCORES, $drawdownReaction, 'drawdown_reaction'),
            self::subScore(self::EMERGENCY_SAVINGS_SCORES, $emergencySavings, 'emergency_savings'),
            self::subScore(self::PRIMARY_GOAL_SCORES, $primaryGoal, 'primary_goal'),
        ];

        return round(array_sum($subScores) / count($subScores), 2);
    }

    /**
     * Map one option index to its sub-score, throwing on an unknown index so a
     * bad answer can never silently become a 0.
     */
    private static function subScore(array $map, int $option, string $question): int
    {
        if (! array_key_exists($option, $map)) {
            throw new \InvalidArgumentException(
                "Invalid option [{$option}] for question [{$question}]; expected one of: "
                .implode(', ', array_keys($map)).'.'
            );
        }

        return $map[$option];
    }

    public function level(): string
    {
        return RiskScore::levelFromScore((float) $this->capacity_score);
    }

    /*
    |--------------------------------------------------------------------------
    | COMPARISON — one sentence for the report
    |--------------------------------------------------------------------------
    |
    | Observational only — same compliance rule as
    | PortfolioRiskCalculator::buildNextAction(): state the two scores and
    | their difference, never prescribe an action. No "should", "consider",
    | "recommend", "review", or "discuss" — and no verdict on whether the
    | portfolio fits the client either: no "aligned", "suitable", "exceeds",
    | "above" or "below" tolerance. Whether a difference matters is the
    | advisor's call, so there is no threshold here and every difference is
    | stated the same way.
    |
    | The difference is taken between the two printed (rounded) numbers, so
    | the sentence always adds up as read: 69.6 against 45.4 prints as
    | 70, 45 and +25, not +24.
    |
    */

    public function comparisonMessage(float $portfolioScore): string
    {
        $portfolioDisplay = (int) round($portfolioScore);
        $capacityDisplay = (int) round((float) $this->capacity_score);
        $difference = $portfolioDisplay - $capacityDisplay;

        return sprintf(
            'Portfolio risk score: %d. Client risk tolerance score: %d. Difference: %s points.',
            $portfolioDisplay,
            $capacityDisplay,
            $difference > 0 ? '+'.$difference : (string) $difference,
        );
    }
}
