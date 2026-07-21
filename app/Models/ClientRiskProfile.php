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
    | Each question's 1-based option index maps to a 0-100 sub-score;
    | capacity_score is the plain average of all five (an intentionally
    | simple, equally-weighted MVP instrument, not a psychometric one).
    |
    */

    public const TIME_HORIZON_SCORES = [
        1 => 10, // < 2 years
        2 => 35, // 2-5 years
        3 => 65, // 5-10 years
        4 => 90, // 10+ years
    ];

    public const INCOME_STABILITY_SCORES = [
        1 => 20, // unstable / variable
        2 => 55, // stable, single income source
        3 => 85, // very stable (salaried/pension/multiple sources)
    ];

    public const DRAWDOWN_REACTION_SCORES = [
        1 => 10,  // sell immediately
        2 => 50,  // anxious but hold
        3 => 80,  // stay invested, unconcerned
        4 => 100, // buying opportunity
    ];

    public const EMERGENCY_SAVINGS_SCORES = [
        1 => 15, // no emergency savings
        2 => 50, // some/partial
        3 => 80, // yes, 3-6+ months
    ];

    public const PRIMARY_GOAL_SCORES = [
        1 => 15, // capital preservation
        2 => 45, // income generation
        3 => 65, // balanced growth
        4 => 90, // aggressive growth
    ];

    // The gap (portfolio score - capacity score) within which the two are
    // considered aligned rather than flagged as exceeding/under-using
    // client tolerance. Exactly ±15 is still "well-aligned" (abs() <= 15).
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
        $subScores = [
            self::TIME_HORIZON_SCORES[$timeHorizon],
            self::INCOME_STABILITY_SCORES[$incomeStability],
            self::DRAWDOWN_REACTION_SCORES[$drawdownReaction],
            self::EMERGENCY_SAVINGS_SCORES[$emergencySavings],
            self::PRIMARY_GOAL_SCORES[$primaryGoal],
        ];

        return round(array_sum($subScores) / count($subScores), 2);
    }

    public function level(): string
    {
        $score = (float) $this->capacity_score;
        $low   = config('risk.low_threshold', 30);
        $high  = config('risk.high_threshold', 70);

        return match (true) {
            $score < $low  => 'LOW',
            $score < $high => 'MEDIUM',
            default        => 'HIGH',
        };
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
        $gap           = round($portfolioScore - $capacityScore, 2);

        $capacityDisplay  = number_format($capacityScore, 0);
        $portfolioDisplay = number_format($portfolioScore, 0);

        if (abs($gap) <= self::ALIGNED_GAP_THRESHOLD) {
            return "Portfolio risk ({$portfolioDisplay}, {$portfolioLevel}) is well-aligned with client risk tolerance ({$capacityDisplay}, {$capacityLevel}).";
        }

        if ($gap > self::ALIGNED_GAP_THRESHOLD) {
            return "Client risk tolerance: {$capacityDisplay} ({$capacityLevel}). Portfolio risk: {$portfolioDisplay} ({$portfolioLevel}). Portfolio risk exceeds client tolerance by " . number_format($gap, 0) . ' points — consider rebalancing toward lower-volatility holdings.';
        }

        return "Client risk tolerance: {$capacityDisplay} ({$capacityLevel}). Portfolio risk: {$portfolioDisplay} ({$portfolioLevel}). Portfolio risk is " . number_format(abs($gap), 0) . " points below client tolerance — there may be room for a more growth-oriented allocation if that fits the client's goals.";
    }
}
