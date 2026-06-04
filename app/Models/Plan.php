<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    /*
    |--------------------------------------------------------------------------
    | MASS ASSIGNABLE
    |--------------------------------------------------------------------------
    */

    protected $fillable = [
        'name',
        'slug',
        'price',
        'duration_days',
        'portfolio_limit',
        'daily_reports',
        'client_script',
        'whatsapp_delivery',
        'branded_pdf',
        'priority_support',
        'multi_advisor',
        'trial_days',
        'monthly_client_limit',
        'is_active',
    ];

    /*
    |--------------------------------------------------------------------------
    | CASTS
    |--------------------------------------------------------------------------
    */

    protected $casts = [
        'price'              => 'decimal:2',
        'duration_days'      => 'integer',
        'portfolio_limit'       => 'integer',
        'monthly_client_limit'  => 'integer',
        'trial_days'            => 'integer',
        'daily_reports'      => 'boolean',
        'client_script'      => 'boolean',
        'whatsapp_delivery'  => 'boolean',
        'branded_pdf'        => 'boolean',
        'priority_support'   => 'boolean',
        'multi_advisor'      => 'boolean',
        'is_active'          => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    /**
     * Duration in days, falling back to 30 if not set.
     */
    public function getDurationDaysAttribute($value): int
    {
        return $value ?? 30;
    }
}
