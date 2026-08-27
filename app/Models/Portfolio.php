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

    /**
     * Composite risk scores produced by PortfolioRiskCalculator — one per
     * scoring run (file upload or the daily risk:generate cron).
     */
    public function riskScores(): HasMany
    {
        return $this->hasMany(RiskScore::class);
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
     * Recalculate portfolio totals.
     *
     * risk_score mirrors the canonical composite produced by
     * PortfolioRiskCalculator and persisted on RiskScore — it is NOT computed
     * here. This column previously held an unweighted mean of per-asset
     * risk_score values, which is a genuinely different number from the
     * 4-factor composite shown on the dashboard and in the PDF. Both were
     * labelled "Risk Score", so the portfolio list and the report could
     * disagree for the same client.
     *
     * Pass $riskScore when the caller has just created one (see
     * ProcessPortfolioFile) — ordering matters, since the latest stored score
     * would otherwise be the *previous* run's, or none at all on a first
     * upload. Omit it to fall back to the latest persisted score.
     *
     * total_value is always refreshed from the assets; risk_score/risk_level
     * are left untouched when no composite exists yet, rather than being
     * zeroed.
     */
    public function recalculateMetrics(?RiskScore $riskScore = null)
    {
        $payload = [
            'total_value' => $this->assets->sum('current_value'),
        ];

        $riskScore ??= $this->riskScores()->latest('generated_at')->first();

        if ($riskScore) {
            $payload['risk_score'] = $riskScore->score;
            $payload['risk_level'] = $riskScore->level();
        }

        $this->update($payload);
    }
}
