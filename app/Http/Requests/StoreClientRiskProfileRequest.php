<?php

namespace App\Http\Requests;

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
        return [
            'time_horizon'      => ['required', 'integer', Rule::in([1, 2, 3, 4])],
            'income_stability'  => ['required', 'integer', Rule::in([1, 2, 3])],
            'drawdown_reaction' => ['required', 'integer', Rule::in([1, 2, 3, 4])],
            'emergency_savings' => ['required', 'integer', Rule::in([1, 2, 3])],
            'primary_goal'      => ['required', 'integer', Rule::in([1, 2, 3, 4])],
        ];
    }

    public function messages(): array
    {
        return [
            '*.required' => 'Please answer every question.',
            '*.in'       => 'Invalid answer selected.',
        ];
    }
}
