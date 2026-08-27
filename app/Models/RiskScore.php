<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskScore extends Model
{
    protected $fillable = [
        'user_id',
        'portfolio_id',
        'score',
        'volatility',
        'drawdown',
        'meta',
        'generated_at',
    ];

    protected $casts = [
        'score' => 'decimal:2',
        'volatility' => 'decimal:2',
        'drawdown' => 'decimal:2',
        'meta' => 'array',
        'generated_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class);
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    /**
     * THE canonical score → level mapping. Every other level() in the codebase
     * delegates here so the boundaries can never drift apart again.
     *
     *   score <  low_threshold   → LOW
     *   score <  high_threshold  → MEDIUM
     *   score >= high_threshold  → HIGH
     *
     * Matches the convention documented in config/risk.php and the one the
     * test suite asserts (30.0 → MEDIUM, 70.0 → HIGH). This method previously
     * used `<= low` and `>= high`, which made a score of exactly 30 read as
     * LOW here and MEDIUM everywhere else — the PDF could print a different
     * level than the engine computed for the same score.
     */
    public static function levelFromScore(float $score): string
    {
        $low = (float) config('risk.low_threshold', 30);
        $high = (float) config('risk.high_threshold', 70);

        return match (true) {
            $score < $low => 'LOW',
            $score < $high => 'MEDIUM',
            default => 'HIGH',
        };
    }

    public function level(): string
    {
        return self::levelFromScore((float) $this->score);
    }

    public function generatedTimestamp(): \Carbon\Carbon
    {
        return $this->generated_at ?? $this->created_at;
    }
}
