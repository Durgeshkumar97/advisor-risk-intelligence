{{-- Password rules, one bullet per rule. Built from the same config the
     validator uses (config/auth.php 'password_policy'), so the hint can never
     disagree with what the server enforces. Link it to the input with
     aria-describedby="{{ id }}". --}}
@props(['id'])

@php($policy = config('auth.password_policy'))

<div id="{{ $id }}" {{ $attributes->merge(['style' => 'margin:.5rem 0 0;font-size:.78rem;line-height:1.5;opacity:.8;']) }}>
    <div style="font-weight:600;margin-bottom:.2rem;">Password requirements:</div>
    <ul style="margin:0;padding-left:1.1rem;list-style:disc;">
        <li>{{ $policy['min'] }} to {{ $policy['max'] }} characters</li>
        @if ($policy['mixed_case'])
            <li>At least one uppercase letter (A-Z)</li>
            <li>At least one lowercase letter (a-z)</li>
        @endif
        @if ($policy['numbers'])
            <li>At least one number (0-9)</li>
        @endif
        @if ($policy['symbols'])
            <li>At least one special character (e.g. ! @ # $ % &amp; *)</li>
        @endif
        @if ($policy['check_breached'])
            <li>Not a password exposed in a known data breach</li>
        @endif
        <li>Must not contain your email or the word "RiskSignal"</li>
    </ul>
</div>
