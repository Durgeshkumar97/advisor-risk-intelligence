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
        'score'        => 'decimal:2',
        'volatility'   => 'decimal:2',
        'drawdown'     => 'decimal:2',
        'meta'         => 'array',
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

    public function level(): string
    {
        return match (true) {
            $this->score < 30 => 'LOW',
            $this->score < 70 => 'MEDIUM',
            default           => 'HIGH',
        };
    }

    public function generatedTimestamp(): \Carbon\Carbon
    {
        return $this->generated_at ?? $this->created_at;
    }
}
