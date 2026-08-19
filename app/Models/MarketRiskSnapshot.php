<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketRiskSnapshot extends Model
{
    protected $fillable = [
        'market_date',
        'score',
        'score_smooth',
        'label',
        'vol_regime',
        'dd_regime',
        'market_regime',
        'warning_severity',
        'warning_text',
    ];

    protected $casts = [
        'market_date'  => 'date',
        'score'        => 'float',
        'score_smooth' => 'float',
    ];

    public static function latest(): ?self
    {
        return static::orderByDesc('market_date')->first();
    }

    public function multiplier(): float
    {
        return match($this->label) {
            'LOW'     => 1.00,
            'MEDIUM'  => 1.08,
            'HIGH'    => 1.15,
            'EXTREME' => 1.30,
            default   => 1.05,
        };
    }
}
