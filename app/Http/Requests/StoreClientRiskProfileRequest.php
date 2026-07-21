<?php

namespace App\Http\Requests;

use App\Models\ClientRiskProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientRiskProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        // Valid option indexes are derived from ClientRiskProfile's scoring maps
        // (array_keys) so validation can never drift from scoring — one source
        // of truth for which options exist per question.
        return [
            'time_horizon' => ['required', 'integer', Rule::in(array_keys(ClientRiskProfile::TIME_HORIZON_SCORES))],
            'income_stability' => ['required', 'integer', Rule::in(array_keys(ClientRiskProfile::INCOME_STABILITY_SCORES))],
            'drawdown_reaction' => ['required', 'integer', Rule::in(array_keys(ClientRiskProfile::DRAWDOWN_REACTION_SCORES))],
            'emergency_savings' => ['required', 'integer', Rule::in(array_keys(ClientRiskProfile::EMERGENCY_SAVINGS_SCORES))],
            'primary_goal' => ['required', 'integer', Rule::in(array_keys(ClientRiskProfile::PRIMARY_GOAL_SCORES))],
        ];
    }

    public function messages(): array
    {
        return [
            '*.required' => 'Please answer every question.',
            '*.in' => 'Invalid answer selected.',
        ];
    }
}
