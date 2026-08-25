<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Portfolio extends Model
{
    use HasFactory;

    /*
    |--------------------------------------------------------------------------
    | Mass Assignable
    |--------------------------------------------------------------------------
    */

    protected $fillable = [

        'user_id',

        'name',

        'client_name',

        'total_value',

        'risk_score',

        'risk_level',

    ];

    /*
    |--------------------------------------------------------------------------
    | Attribute Casting
    |--------------------------------------------------------------------------
    */

    protected $casts = [

        'total_value' => 'decimal:2',

        'risk_score' => 'decimal:2',

    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Portfolio owner
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Portfolio assets
     */
    public function assets()
    {
        return $this->hasMany(PortfolioAsset::class);
    }

    /**
     * Uploaded files (PortfolioFile records)
     */
    public function files(): HasMany
    {
        return $this->hasMany(PortfolioFile::class);
    }

    /**
     * Client risk tolerance/capacity profile — one per portfolio (a
     * portfolio is one advisor's one client in this data model).
     */
    public function clientRiskProfile(): HasOne
    {
        return $this->hasOne(ClientRiskProfile::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Total asset count
     */
    public function totalAssets()
    {
        return $this->assets()->count();
    }

    /**
     * Active risk label
     */
    public function riskBadgeColor()
    {
        return match ($this->risk_level) {

            'LOW' => 'success',

            'MEDIUM' => 'warning',

            'HIGH' => 'danger',

            default => 'secondary',
        };
    }

    /**
     * Recalculate portfolio totals
     */
    public function recalculateMetrics()
    {
        $assets = $this->assets;

        $totalValue = $assets->sum('current_value');

        $averageRisk = $assets->avg('risk_score') ?? 0;

        // Canonical thresholds: LOW < 30, MEDIUM 30–69, HIGH >= 70
        // Must match RiskScore::level(), AssetRiskScorer::level(),
        // PortfolioRiskCalculator::level(), and DashboardController.
        $riskLevel = 'LOW';

        if ($averageRisk >= 70) {
            $riskLevel = 'HIGH';
        } elseif ($averageRisk >= 30) {
            $riskLevel = 'MEDIUM';
        }

        $this->update([

            'total_value' => $totalValue,

            'risk_score' => round($averageRisk, 2),

            'risk_level' => $riskLevel,

        ]);
    }
}
