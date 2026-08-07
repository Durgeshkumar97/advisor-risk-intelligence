<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PortfolioAsset extends Model
{
    use HasFactory;

    /*
    |--------------------------------------------------------------------------
    | Mass Assignable
    |--------------------------------------------------------------------------
    */

    protected $fillable = [

        'portfolio_id',

        'asset_type',

        'symbol',

        'name',

        'isin',

        'quantity',

        'buy_price',

        'current_price',

        'invested_value',

        'current_value',

        'profit_loss',

        'risk_score',

        'risk_level',

        'meta',

    ];

    /*
    |--------------------------------------------------------------------------
    | Attribute Casting
    |--------------------------------------------------------------------------
    */

    protected $casts = [

        'quantity' => 'decimal:4',

        'buy_price' => 'decimal:2',

        'current_price' => 'decimal:2',

        'invested_value' => 'decimal:2',

        'current_value' => 'decimal:2',

        'profit_loss' => 'decimal:2',

        'risk_score' => 'decimal:2',

        'meta' => 'array',

    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Parent portfolio
     */
    public function portfolio()
    {
        return $this->belongsTo(Portfolio::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Profit / Loss Percentage
     */
    public function profitLossPercentage()
    {
        if ($this->invested_value <= 0) {
            return 0;
        }

        return round(
            (($this->current_value - $this->invested_value)
            / $this->invested_value) * 100,
            2
        );
    }

    /**
     * Asset allocation %
     */
    public function allocationPercentage()
    {
        if (! $this->portfolio || $this->portfolio->total_value <= 0) {
            return 0;
        }

        return round(
            ($this->current_value / $this->portfolio->total_value) * 100,
            2
        );
    }

    /**
     * Risk label color
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
     * Asset category label
     */
    public function formattedAssetType()
    {
        return match ($this->asset_type) {

            'mutual_fund' => 'Mutual Fund',

            'foreign_stock' => 'Foreign Stock',

            default => ucwords(
                str_replace('_', ' ', $this->asset_type)
            ),
        };
    }

    /**
     * Auto compute profit/loss
     */
    public function calculatePnL()
    {
        $invested = $this->quantity * $this->buy_price;

        $current = $this->quantity * $this->current_price;

        $this->update([

            'invested_value' => round($invested, 2),

            'current_value' => round($current, 2),

            'profit_loss' => round(
                $current - $invested,
                2
            ),

        ]);
    }
}
