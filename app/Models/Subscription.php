<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = [
        'lead_id',
        'user_id',
        'plan_id',
        'status',
        'trial_started_at',
        'trial_ends_at',
        'starts_at',
        'ends_at',
        'renewal_at',
        'provider',
        'provider_subscription_id'
    ];
}