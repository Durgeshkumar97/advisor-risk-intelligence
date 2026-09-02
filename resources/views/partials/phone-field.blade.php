{{--
    Shared phone input: a country-code <select> (every country in
    config/countries.php, not just India) paired with the local-number
    input. Include with:

        @include('partials.phone-field', ['name' => 'phone', 'required' => true])

    Optional: id, placeholder, value, inputClass, wrapperClass, selectedIso.
--}}
@php
    $phoneFieldName = $name ?? 'phone';
    $phoneFieldId = $id ?? $phoneFieldName;
    $phoneCountryField = $phoneFieldName.'_country';
    $phoneCountries = config('countries.list');
    $phoneSelectedIso = old($phoneCountryField, $selectedIso ?? config('countries.default'));
    $phoneWrapperClass = $wrapperClass ?? 'phone-field';
    $phoneInputClass = $inputClass ?? 'form-input';
@endphp
<div class="{{ $phoneWrapperClass }}">
    <select
        id="{{ $phoneFieldId }}_country"
        name="{{ $phoneCountryField }}"
        class="phone-country"
        aria-label="Country code"
    >
        @foreach($phoneCountries as $country)
        <option value="{{ $country['iso'] }}" {{ $phoneSelectedIso === $country['iso'] ? 'selected' : '' }}>
            {{ $country['name'] }} ({{ $country['dial'] }})
        </option>
        @endforeach
    </select>
    <input
        type="tel"
        id="{{ $phoneFieldId }}"
        name="{{ $phoneFieldName }}"
        value="{{ old($phoneFieldName, $value ?? '') }}"
        class="{{ $phoneInputClass }}"
        placeholder="{{ $placeholder ?? 'Phone number' }}"
        inputmode="numeric"
        autocomplete="tel-national"
        @if($required ?? false) required @endif
    >
</div>
