<?php

namespace App\Http\Requests;

use App\Models\Portfolio;
use Illuminate\Foundation\Http\FormRequest;

class StoreManualPortfolioRequest extends FormRequest
{
    // decimal(15,2) column — safe ceiling well below max representable value
    private const MAX_VALUE = 9_999_999_999;

    public function authorize(): bool
    {
        return auth()->check();
    }

    protected function prepareForValidation(): void
    {
        if ($this->portfolio_id === '') {
            $this->merge(['portfolio_id' => null]);
        }
    }

    public function rules(): array
    {
        $mode = $this->input('mode'); // 'holdings' or 'alloc'

        $base = [
            'mode'         => ['required', 'in:holdings,alloc'],
            'client_name'  => ['required', 'string', 'max:100'],
            'portfolio_id' => ['nullable', 'integer', 'min:1', 'exists:portfolios,id'],
        ];

        if ($mode === 'holdings') {
            return array_merge($base, [
                'holdings'             => ['required', 'array', 'min:1'],
                'holdings.*.name'      => ['required', 'string', 'max:200'],
                'holdings.*.type'      => ['required', 'string', 'in:stock,bond,mutual_fund,etf,commodity,crypto,cash,foreign_stock'],
                'holdings.*.value'     => ['required', 'numeric', 'min:0', 'max:' . self::MAX_VALUE],
            ]);
        }

        // alloc mode
        return array_merge($base, [
            'alloc'        => ['required', 'array'],
            'alloc.*'      => ['nullable', 'numeric', 'min:0', 'max:' . self::MAX_VALUE],
        ]);
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function ($validator) {
            $mode = $this->input('mode');

            // Portfolio ownership check
            $portfolioId = $this->input('portfolio_id');
            if ($portfolioId) {
                $exists = Portfolio::query()
                    ->where('id', $portfolioId)
                    ->where('user_id', $this->user()->id)
                    ->exists();
                if (!$exists) {
                    $validator->errors()->add('portfolio_id', 'The selected portfolio is invalid.');
                }
            }

            if ($mode === 'holdings') {
                // At least one holding with value > 0
                $holdings = $this->input('holdings', []);
                $hasValue = false;
                foreach ($holdings as $h) {
                    if (($h['value'] ?? 0) > 0) {
                        $hasValue = true;
                        break;
                    }
                }
                if (!$hasValue) {
                    $validator->errors()->add('holdings', 'At least one holding must have a value greater than zero.');
                }
            }

            if ($mode === 'alloc') {
                // At least one category > 0
                $alloc = $this->input('alloc', []);
                $total = array_sum(array_map('floatval', $alloc));
                if ($total <= 0) {
                    $validator->errors()->add('alloc', 'At least one category must have a value greater than zero.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'client_name.required'      => 'Please enter a client or portfolio name.',
            'client_name.max'           => 'Client name must not exceed 100 characters.',
            'holdings.required'         => 'Please add at least one holding.',
            'holdings.*.name.required'  => 'Each holding must have a name.',
            'holdings.*.value.min'      => 'Holding values cannot be negative.',
            'holdings.*.value.max'      => 'A single holding value exceeds the maximum allowed.',
            'alloc.*.min'               => 'Category values cannot be negative.',
            'alloc.*.max'               => 'A single category value exceeds the maximum allowed.',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | GETTERS — sanitized values ready for the controller
    |--------------------------------------------------------------------------
    */

    public function getClientName(): string
    {
        return $this->sanitizeName($this->input('client_name'));
    }

    public function getPortfolioId(): ?int
    {
        $id = $this->input('portfolio_id');
        return $id ? (int) $id : null;
    }

    /** Returns rows: [{name, type, value}] with sanitized names, value > 0 only */
    public function getHoldingsRows(): array
    {
        $rows = [];
        foreach ($this->input('holdings', []) as $h) {
            $val = (float) ($h['value'] ?? 0);
            if ($val <= 0) {
                continue;
            }
            $rows[] = [
                'name'  => $this->sanitizeName($h['name'] ?? ''),
                'type'  => $h['type'] ?? 'stock',
                'value' => $val,
            ];
        }
        return $rows;
    }

    /** Returns [{type, value}] for alloc mode, value > 0 only */
    public function getAllocRows(): array
    {
        $rows = [];
        foreach ($this->input('alloc', []) as $type => $rawVal) {
            $val = (float) $rawVal;
            if ($val <= 0) {
                continue;
            }
            $rows[] = ['type' => $type, 'value' => $val];
        }
        return $rows;
    }

    private function sanitizeName(string $raw): string
    {
        // Strip HTML tags
        $s = strip_tags($raw);

        // Strip formula-injection characters at the start (CSV/Excel safety)
        $s = ltrim($s, '=+-@');

        // Remove path separators
        $s = str_replace(['/', '\\', '..'], '', $s);

        return trim($s);
    }
}
