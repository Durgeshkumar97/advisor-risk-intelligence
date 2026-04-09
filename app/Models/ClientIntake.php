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
        'firm_name',
        'document_path',
        'plan',
        'status',
        'trial_started_at',
        'trial_ends_at',
        'plan_price',
        'revenue_generated',
        'lead_score',
        'ai_status',
    ];
}
