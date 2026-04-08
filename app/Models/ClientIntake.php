<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientIntake extends Model
{
    use HasFactory;

    protected $table = 'client_intakes';

    protected $fillable = [
        'submission_uuid',
        'name',
        'email',
        'whatsapp',
        'plan',
        'trial_started_at',
        'trial_ends_at',
        'portfolio_type',
        'portfolio_value',
        'status',
        'lead_score',
        'ai_status',
        'notes',
    ];
}