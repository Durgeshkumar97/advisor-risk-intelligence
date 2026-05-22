<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreIfaTrialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'advisor_name' => ['bail', 'required', 'string', 'max:120'],
            'email' => ['bail', 'required', 'string', 'email:rfc', 'max:200'],
            'whatsapp' => [
                'bail',
                'required',
                'string',
                'max:20',
                'regex:/^[0-9+()\-\s]+$/',
            ],
            'firm_name' => ['bail', 'required', 'string', 'max:200'],
            'document' => [
                'nullable',
                'file',
                'mimes:pdf,png,jpg,jpeg,zip',
                'max:10240',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'whatsapp.regex' => 'Enter a valid WhatsApp number.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'advisor_name' => $this->trimmedString('advisor_name'),
            'email' => $this->trimmedString('email'),
            'whatsapp' => $this->trimmedString('whatsapp'),
            'firm_name' => $this->trimmedString('firm_name'),
        ]);
    }

    private function trimmedString(string $key): mixed
    {
        $value = $this->input($key);

        return is_string($value) ? trim($value) : $value;
    }
}
