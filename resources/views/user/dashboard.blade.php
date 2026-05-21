@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')

@php
    $riskBadgeClass = match (strtoupper($riskLevel ?? 'LOW')) {
        'HIGH' => 'dashboard-risk-badge-high',
        'MEDIUM' => 'dashboard-risk-badge-medium',
        default => 'dashboard-risk-badge-low',
    };
@endphp

<div class="dashboard-wrapper">

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
            href="#"
            class="dashboard-btn dashboard-btn-secondary">

            View Risk Reports

        </a>

    </div>

</div>

@endsection
