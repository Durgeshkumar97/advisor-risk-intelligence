@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')

@php
    $riskBadgeClass = match (strtoupper($riskLevel ?? 'LOW')) {
        'HIGH' => 'dashboard-risk-badge-high',
        'MEDIUM' => 'dashboard-risk-badge-medium',
        default => 'dashboard-risk-badge-low',
    };

    $gaugeFillClass = match (strtoupper($riskLevel ?? 'LOW')) {
        'HIGH' => 'dashboard-gauge-fill-high',
        'MEDIUM' => 'dashboard-gauge-fill-medium',
        default => 'dashboard-gauge-fill-low',
    };
@endphp

<div class="dashboard-wrapper">

    {{-- EXPIRY WARNING --}}
    @if(round($daysLeft) <= 7 && round($daysLeft) > 0)
    <div class="dashboard-expiry-warning">
        <span>
            Your subscription expires in
            <strong>{{ round($daysLeft) }} day{{ round($daysLeft) == 1 ? '' : 's' }}</strong>.
        </span>
        <a href="{{ route('subscription.index') }}">Renew now &rarr;</a>
    </div>
    @elseif(round($daysLeft) === 0)
    <div class="dashboard-expiry-warning dashboard-expiry-expired">
        <span>Your subscription has expired.</span>
        <a href="{{ route('subscription.index') }}">Renew now &rarr;</a>
    </div>
    @endif

    {{-- HERO --}}
    <div class="dashboard-hero">

        <div>

            <div class="dashboard-eyebrow">
                Advisor Intelligence Dashboard
            </div>

            <h1 class="dashboard-title">
                Welcome, {{ $user->name }}
            </h1>

            <p class="dashboard-subtitle">
                Monitor portfolio risk, advisor intelligence,
                market exposure, and client panic signals
                from one unified command center.
            </p>

        </div>

    </div>

    {{-- STATS GRID --}}
    <div class="dashboard-grid">

        {{-- SUBSCRIPTION --}}
        <div class="dashboard-card">

            <div class="dashboard-label">
                Subscription
            </div>

            <div class="dashboard-value">
                {{ $planName }}
            </div>

            <div class="dashboard-meta">
                {{ round($daysLeft) }} days remaining
            </div>

        </div>

        {{-- RISK SCORE --}}
        <div class="dashboard-card">

            <div class="dashboard-label">
                Portfolio Risk
            </div>

            <div class="dashboard-gauge" role="progressbar" aria-valuenow="{{ $riskScore }}" aria-valuemin="0" aria-valuemax="100">
                <div class="dashboard-gauge-fill {{ $gaugeFillClass }}" style="width:{{ min((int)$riskScore, 100) }}%"></div>
            </div>

            <div class="dashboard-risk-score">
                {{ $riskScore }}
            </div>

            <div class="dashboard-risk-badge {{ $riskBadgeClass }}">
                {{ strtoupper($riskLevel) }} RISK
            </div>

        </div>

        {{-- RECOMMENDATION --}}
        <div class="dashboard-card">

            <div class="dashboard-label">
                Recommendation
            </div>

            <div class="dashboard-message">
                {{ $recommendation }}
            </div>

        </div>

        {{-- NEXT ACTION --}}
        <div class="dashboard-card">

            <div class="dashboard-label">
                Next Action
            </div>

            <div class="dashboard-message">
                {{ $nextAction }}
            </div>

        </div>

    </div>

    {{-- ACTION BUTTONS --}}
    <div class="dashboard-actions">

        <a
            href="{{ route('portfolio.upload') }}"
            class="dashboard-btn dashboard-btn-primary">

            Upload Portfolio

        </a>

        <a
            href="{{ route('portfolio.manage') }}"
            class="dashboard-btn dashboard-btn-secondary">

            View Risk Reports

        </a>

    </div>

    {{-- PORTFOLIOS SECTION --}}
    <div class="dashboard-portfolios-section">

        <h2 class="dashboard-section-heading">
            Your Portfolios
        </h2>

        @forelse($portfolios as $portfolio)

        @php
            $pLevel = strtoupper($portfolio->risk_level ?? 'LOW');
            $pBadge = match($pLevel) {
                'HIGH' => 'dashboard-risk-badge-high',
                'MEDIUM' => 'dashboard-risk-badge-medium',
                default => 'dashboard-risk-badge-low',
            };
        @endphp

        <div class="dashboard-portfolio-row">

            <div>
                <div class="dashboard-portfolio-name">
                    {{ $portfolio->name }}
                </div>
                <div class="dashboard-portfolio-meta">
                    {{ $portfolio->assets()->count() }} assets
                    &bull;
                    Updated {{ $portfolio->updated_at->diffForHumans() }}
                </div>
            </div>

            <div class="dashboard-portfolio-right">

                <span class="dashboard-risk-badge {{ $pBadge }}">
                    {{ $pLevel }}
                </span>

                @if($portfolio->total_value)
                <div class="dashboard-portfolio-value">
                    &#8377;{{ number_format($portfolio->total_value) }}
                </div>
                @endif

            </div>

        </div>

        @empty

        <div class="dashboard-portfolio-empty">
            No portfolios uploaded yet.
            <a href="{{ route('portfolio.upload') }}">Upload your first portfolio &rarr;</a>
        </div>

        @endforelse

    </div>

</div>

@endsection
