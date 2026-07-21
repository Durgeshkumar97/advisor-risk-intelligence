@extends('layouts.app')

@section('title', 'Client Risk Profile — RiskSignal')

@section('content')

<div style="max-width:700px;margin:0 auto;padding:2rem 0;">

    {{-- HEADER --}}
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem;margin-bottom:2rem;">
        <div>
            <div class="eyebrow" style="margin-bottom:.4rem;">Client Risk Profile</div>
            <h1 style="font-size:1.85rem;font-weight:800;margin-bottom:.4rem;">{{ $portfolio->name }}</h1>
            <p style="color:var(--ink-3);font-size:.9rem;">
                Answer on behalf of the client. This is compared against the portfolio's risk
                score on every report — it doesn't change the portfolio score itself.
            </p>
        </div>
        <a href="{{ route('portfolio.manage') }}" style="padding:.75rem 1.2rem;border-radius:12px;border:1px solid var(--paper-3);text-decoration:none;color:inherit;font-weight:600;font-size:.875rem;white-space:nowrap;">
            ← Back
        </a>
    </div>

    {{-- ALERTS --}}
    @if($errors->any())
        <div style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2);color:#fca5a5;padding:.85rem 1.1rem;border-radius:12px;margin-bottom:1.5rem;font-size:.9rem;">
            {{ $errors->first() }}
        </div>
    @endif

    @if($profile)
        <div style="background:rgba(37,99,235,.1);border:1px solid rgba(37,99,235,.2);color:#93c5fd;padding:.85rem 1.1rem;border-radius:12px;margin-bottom:1.5rem;font-size:.85rem;">
            Current tolerance score: <strong>{{ number_format($profile->capacity_score, 0) }} ({{ $profile->level() }})</strong> — saving below will replace it.
        </div>
    @endif

    <form method="POST" action="{{ route('portfolio.risk-profile.update', $portfolio->id) }}" style="background:var(--paper-2);border:1px solid var(--paper-3);border-radius:16px;padding:1.75rem;">
        @csrf

        @php
            $questions = [
                'time_horizon' => [
                    'label' => 'When will the client likely need to withdraw a significant portion of this money?',
                    'options' => [
                        1 => 'Less than 2 years',
                        2 => '2–5 years',
                        3 => '5–10 years',
                        4 => '10+ years',
                    ],
                ],
                'income_stability' => [
                    'label' => "How stable is the client's income?",
                    'options' => [
                        1 => 'Unstable / variable',
                        2 => 'Stable, single income source',
                        3 => 'Very stable (salaried, pension, or multiple sources)',
                    ],
                ],
                'drawdown_reaction' => [
                    'label' => 'If this portfolio dropped 20% in a few months, what would the client most likely do?',
                    'options' => [
                        1 => 'Sell immediately to limit further loss',
                        2 => 'Feel anxious but hold',
                        3 => 'Stay invested, unconcerned',
                        4 => 'See it as a buying opportunity',
                    ],
                ],
                'emergency_savings' => [
                    'label' => 'Does the client have emergency savings (3–6+ months of expenses) outside this portfolio?',
                    'options' => [
                        1 => 'No',
                        2 => 'Some / partial',
                        3 => 'Yes',
                    ],
                ],
                'primary_goal' => [
                    'label' => "What is the client's primary goal for this money?",
                    'options' => [
                        1 => 'Capital preservation',
                        2 => 'Income generation',
                        3 => 'Balanced growth',
                        4 => 'Aggressive growth',
                    ],
                ],
            ];
        @endphp

        @foreach($questions as $field => $question)
            <div style="margin-bottom:1.5rem;{{ !$loop->last ? 'padding-bottom:1.5rem;border-bottom:1px solid var(--paper-3);' : '' }}">
                <div style="font-weight:700;font-size:.9rem;margin-bottom:.75rem;">
                    {{ $loop->iteration }}. {{ $question['label'] }}
                </div>
                @foreach($question['options'] as $value => $label)
                    <label style="display:flex;align-items:center;gap:.6rem;padding:.5rem 0;font-size:.875rem;cursor:pointer;">
                        <input
                            type="radio"
                            name="{{ $field }}"
                            value="{{ $value }}"
                            {{ old($field, $profile?->{$field}) == $value ? 'checked' : '' }}
                            required
                        >
                        {{ $label }}
                    </label>
                @endforeach
            </div>
        @endforeach

        <button type="submit" style="padding:.85rem 1.6rem;border-radius:10px;background:var(--accent);color:#fff;border:none;font-weight:700;font-size:.9rem;cursor:pointer;">
            Save Risk Profile
        </button>
    </form>

</div>

@endsection
