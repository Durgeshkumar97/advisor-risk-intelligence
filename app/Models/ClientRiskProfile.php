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

    // The gap (portfolio score - capacity score) within which the two are
    // considered aligned rather than flagged as exceeding/under-using client
    // tolerance. Reviewed against the rescaled maps above: capacity_score and
    // the portfolio score now both span a genuine 0–100, so ±15 is the same
    // 15% of full range on each side (previously capacity was compressed to
    // ~14–89, making a 15-point gap asymmetric between the two scales). One
    // questionnaire answer moves capacity_score by 6 points (4-option question)
    // to 10 points (3-option), so ±15 ≈ 1.5–2.5 answers of leeway — still a
    // sensible aligned band, left unchanged. Exactly ±15 is "well-aligned"
    // (abs() <= 15).
    private const ALIGNED_GAP_THRESHOLD = 15;

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
    */

    public function comparisonMessage(float $portfolioScore, string $portfolioLevel): string
    {
        $capacityScore = (float) $this->capacity_score;
        $capacityLevel = $this->level();
        $gap = round($portfolioScore - $capacityScore, 2);

        $capacityDisplay = number_format($capacityScore, 0);
        $portfolioDisplay = number_format($portfolioScore, 0);

        if (abs($gap) <= self::ALIGNED_GAP_THRESHOLD) {
            return "Portfolio risk ({$portfolioDisplay}, {$portfolioLevel}) is well-aligned with client risk tolerance ({$capacityDisplay}, {$capacityLevel}).";
        }

        if ($gap > self::ALIGNED_GAP_THRESHOLD) {
            return "Client risk tolerance: {$capacityDisplay} ({$capacityLevel}). Portfolio risk: {$portfolioDisplay} ({$portfolioLevel}). Portfolio risk exceeds client tolerance by ".number_format($gap, 0).' points — consider rebalancing toward lower-volatility holdings.';
        }

        return "Client risk tolerance: {$capacityDisplay} ({$capacityLevel}). Portfolio risk: {$portfolioDisplay} ({$portfolioLevel}). Portfolio risk is ".number_format(abs($gap), 0)." points below client tolerance — there may be room for a more growth-oriented allocation if that fits the client's goals.";
    }
}
