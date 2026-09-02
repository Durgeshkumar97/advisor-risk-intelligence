<?php

namespace App\Http\Requests;

use App\Support\PhoneNumber;
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
            // Optional WhatsApp number with country selection — normalized to E.164 in passedValidation()
            'whatsapp' => [
                'bail',
                'nullable',
                'string',
                'max:20',
            ],
            'whatsapp_country' => [
                'bail',
                'nullable',
                'string',
                'in:'.implode(',', array_column(config('countries.list'), 'iso')),
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

    protected function prepareForValidation(): void
    {
        $this->merge([
            'advisor_name' => $this->trimmedString('advisor_name'),
            'email' => $this->trimmedString('email'),
            'whatsapp' => $this->trimmedString('whatsapp'),
            'whatsapp_country' => $this->trimmedString('whatsapp_country'),
            'firm_name' => $this->trimmedString('firm_name'),
        ]);
    }

    protected function passedValidation(): void
    {
        // Normalize WhatsApp number to E.164 if provided
        $whatsapp = $this->input('whatsapp');
        if ($whatsapp !== null && trim($whatsapp) !== '') {
            $countries = config('countries.list');
            $countryIso = $this->input('whatsapp_country') ?? config('countries.default');
            $selectedCountry = collect($countries)->firstWhere('iso', $countryIso);

            if ($selectedCountry) {
                $normalized = PhoneNumber::normalize($selectedCountry['dial'], $whatsapp);
                if ($normalized !== null) {
                    $this->merge(['whatsapp' => $normalized]);
                }
            }
        }
    }

    private function trimmedString(string $key): mixed
    {
        $value = $this->input($key);

        return is_string($value) ? trim($value) : $value;
    }
}
