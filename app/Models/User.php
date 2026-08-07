<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'login_token',
        'login_token_expires_at',
        'login_method',
        'onboarding_completed',
        'email_reports',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'login_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'login_token_expires_at' => 'datetime',
            'onboarding_completed' => 'boolean',
            'email_reports' => 'boolean',
            'is_admin' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Latest subscription (single object, for dashboard/middleware checks)
     */
    public function subscription()
    {
        return $this->hasOne(\App\Models\Subscription::class)->latestOfMany();
    }

    public function subscriptions()
    {
        return $this->hasMany(\App\Models\Subscription::class);
    }

    public function portfolios()
    {
        return $this->hasMany(\App\Models\Portfolio::class);
    }

    public function payments()
    {
        return $this->hasMany(\App\Models\Payment::class);
    }

    /*
    |--------------------------------------------------------------------------
    | SaaS Helpers (VERY IMPORTANT)
    |--------------------------------------------------------------------------
    */

    public function hasActiveSubscription()
    {
        return $this->subscription &&
            $this->subscription->status === 'active' &&
            $this->subscription->ends_at &&
            $this->subscription->ends_at->isFuture();
    }

    public function isTrial()
    {
        return $this->subscription &&
            $this->subscription->status === 'trial' &&
            $this->subscription->trial_ends_at &&
            $this->subscription->trial_ends_at->isFuture();
    }

    public function isExpired()
    {
        return $this->subscription &&
            $this->subscription->ends_at &&
            $this->subscription->ends_at->isPast();
    }
}
