<?php

namespace App\Http\Requests;

use App\Models\Portfolio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

class StorePortfolioUploadRequest extends FormRequest
{
    /*
    |--------------------------------------------------------------------------
    | AUTHORIZE
    |--------------------------------------------------------------------------
    */

    public function authorize(): bool
    {
        return auth()->check();
    }

    /*
    |--------------------------------------------------------------------------
    | PREPARE INPUT
    |--------------------------------------------------------------------------
    */

    protected function prepareForValidation(): void
    {
        /*
        |--------------------------------------------------------------------------
        | EMPTY PORTFOLIO FIX
        |--------------------------------------------------------------------------
        */

        if ($this->portfolio_id === '') {

            $this->merge([
                'portfolio_id' => null,
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | RULES
    |--------------------------------------------------------------------------
    */

    public function rules(): array
    {
        return [

            /*
            |--------------------------------------------------------------------------
            | PORTFOLIO
            |--------------------------------------------------------------------------
            */

            'portfolio_id' => [

                'nullable',

                'integer',

                'min:1',

                'exists:portfolios,id',
            ],

            /*
            |--------------------------------------------------------------------------
            | FILE
            |--------------------------------------------------------------------------
            */

            'file' => [

                'required',

                'file',

                /*
                |--------------------------------------------------------------------------
                | ALLOWED FILE TYPES
                |--------------------------------------------------------------------------
                */

                'mimes:pdf,csv,xlsx,xls,zip',

                /*
                |--------------------------------------------------------------------------
                | MAX SIZE
                |--------------------------------------------------------------------------
                |
                | 20MB
                |
                */

                'max:20480',
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | AFTER VALIDATION
    |--------------------------------------------------------------------------
    */

    public function withValidator(
        Validator $validator
    ): void {

        $validator->after(function (
            Validator $validator
        ) {

            $portfolioId =
                $this->input('portfolio_id');

            /*
            |--------------------------------------------------------------------------
            | NO PORTFOLIO PROVIDED
            |--------------------------------------------------------------------------
            */

            if (!$portfolioId) {
                return;
            }

            /*
            |--------------------------------------------------------------------------
            | VERIFY OWNERSHIP
            |--------------------------------------------------------------------------
            */

            $exists = Portfolio::query()

                ->where('id', $portfolioId)

                ->where(
                    'user_id',
                    $this->user()->id
                )

                ->exists();

            if (!$exists) {

                $validator->errors()->add(

                    'portfolio_id',

                    'The selected portfolio is invalid.'
                );
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | CUSTOM MESSAGES
    |--------------------------------------------------------------------------
    */

    public function messages(): array
    {
        return [

            'file.required' =>
            'Please select a file to upload.',

            'file.file' =>
            'The uploaded file must be valid.',

            'file.mimes' =>
            'Only PDF, CSV, XLSX, XLS, and ZIP files are allowed.',

            'file.max' =>
            'File size must not exceed 20MB.',

            'portfolio_id.integer' =>
            'Invalid portfolio selected.',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | GETTERS
    |--------------------------------------------------------------------------
    */

    public function getPortfolioId(): ?int
    {
        return $this->input('portfolio_id')
            ? (int) $this->input('portfolio_id')
            : null;
    }

    public function getFile(): UploadedFile
    {
        return $this->file('file');
    }
}
