@extends('layouts.app')

@section('title', 'Dashboard — RiskSignal')

@section('content')

@php
    $hasSubscription = $subscription !== null;
    $isActive        = $hasSubscription && $subscription->isActive();
    $isTrial         = $hasSubscription && $subscription->isTrial();
    $isExpired       = $hasSubscription && $subscription->isExpired();
    $isInGrace       = $hasSubscription && $subscription->isInGracePeriod();
    $isExpiringSoon  = $hasSubscription && $subscription->isExpiringSoon(7);
    $isCancelled     = $hasSubscription && $subscription->status === 'cancelled';
    $hasRiskScore    = $riskScore !== null;
    $hasFiles        = $recentFiles->isNotEmpty();

    $riskBadgeClass = match($riskLevel) {
        'HIGH'   => 'dashboard-risk-badge-high',
        'MEDIUM' => 'dashboard-risk-badge-medium',
        default  => 'dashboard-risk-badge-low',
    };
@endphp

<div class="dashboard-wrapper">

    {{-- ================================================================
       SUBSCRIPTION STATUS BANNERS
    ================================================================ --}}

    @if($isExpired && $isInGrace)

        <div style="
            background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.25);
            border-radius:12px;padding:1rem 1.25rem;margin-bottom:1.5rem;
            display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;
        ">
            <span style="color:#fca5a5;font-weight:600;font-size:.9rem;">
                ⚠️ Subscription expired {{ $subscription->ends_at?->diffForHumans() }}. You're in your 3-day grace period — renew now.
            </span>
            <a href="{{ route('pricing') }}" class="btn-primary" style="padding:.55rem 1.1rem;font-size:.85rem;white-space:nowrap;">
                Renew Now →
            </a>
        </div>

    @elseif($isExpired && !$isInGrace)

        <div style="
            background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.25);
            border-radius:12px;padding:1rem 1.25rem;margin-bottom:1.5rem;
            display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;
        ">
            <span style="color:#fca5a5;font-weight:600;font-size:.9rem;">
                ⚠️ Your subscription has expired. Renew to continue receiving daily risk signals.
            </span>
            <a href="{{ route('pricing') }}" class="btn-primary" style="padding:.55rem 1.1rem;font-size:.85rem;white-space:nowrap;">
                Renew Now →
            </a>
        </div>

    @elseif($isCancelled)

        <div style="
            background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2);
            border-radius:12px;padding:1rem 1.25rem;margin-bottom:1.5rem;
            display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;
        ">
            <span style="color:#fca5a5;font-weight:600;font-size:.9rem;">
                ℹ️ Subscription cancelled — you retain access until {{ $subscription->ends_at?->format('d M Y') }}.
            </span>
            <a href="{{ route('pricing') }}" style="
                padding:.55rem 1.1rem;border-radius:9px;border:1px solid rgba(239,68,68,.3);
                background:rgba(239,68,68,.1);color:#fca5a5;font-weight:700;font-size:.85rem;text-decoration:none;white-space:nowrap;
            ">Renew →</a>
        </div>

    @elseif($isExpiringSoon && !$isExpired)

        <div style="
            background:rgba(251,191,36,.1);border:1px solid rgba(251,191,36,.22);
            border-radius:12px;padding:1rem 1.25rem;margin-bottom:1.5rem;
            display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;
        ">
            <span style="color:#fbbf24;font-weight:600;font-size:.9rem;">
                ⏳ Subscription expires in <strong>{{ $subscription->daysRemaining() }} {{ Str::plural('day', $subscription->daysRemaining()) }}</strong> ({{ $subscription->ends_at?->format('d M Y') }}).
            </span>
            <a href="{{ route('pricing') }}" style="
                padding:.55rem 1.1rem;border-radius:9px;border:1px solid rgba(251,191,36,.35);
                background:rgba(251,191,36,.1);color:#fbbf24;font-weight:700;font-size:.85rem;text-decoration:none;white-space:nowrap;
            ">Renew Now →</a>
        </div>

    @elseif(!$hasSubscription)

        <div style="
            background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.22);
            border-radius:12px;padding:1rem 1.25rem;margin-bottom:1.5rem;
            display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;
        ">
            <span style="color:#a5b4fc;font-weight:600;font-size:.9rem;">
                🚀 You don't have an active plan yet. Choose a plan to activate your risk signals.
            </span>
            <a href="{{ route('pricing') }}" class="btn-primary" style="padding:.55rem 1.1rem;font-size:.85rem;white-space:nowrap;">
                View Plans →
            </a>
        </div>

    @endif

    {{-- ================================================================
       HERO
    ================================================================ --}}
    <div class="dashboard-hero">

        <div class="dashboard-eyebrow">
            Advisor Intelligence Dashboard
        </div>

        <h1 class="dashboard-title">
            Welcome, {{ $user->name }}
        </h1>

        <p class="dashboard-subtitle">
            Monitor portfolio risk, market exposure, and client panic
            signals from one unified command center.
        </p>

    </div>

    {{-- ================================================================
       STAT CARDS GRID
    ================================================================ --}}
    <div class="dashboard-grid">

        {{-- SUBSCRIPTION CARD --}}
        <div class="dashboard-card">

            <div class="dashboard-label">Subscription</div>

            @if($hasSubscription)

                <div class="dashboard-value" style="font-size:2rem;">
                    {{ $planName }}
                </div>

                <div style="margin-top:.5rem;display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;">

                    @if($isTrial)
                        <span style="
                            display:inline-block;padding:.25rem .7rem;border-radius:999px;
                            background:rgba(99,102,241,.15);color:#a5b4fc;
                            font-size:.75rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;
                        ">Trial</span>
                    @elseif($isActive)
                        <span style="
                            display:inline-block;padding:.25rem .7rem;border-radius:999px;
                            background:rgba(16,185,129,.15);color:#34d399;
                            font-size:.75rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;
                        ">Active</span>
                    @elseif($isCancelled)
                        <span style="
                            display:inline-block;padding:.25rem .7rem;border-radius:999px;
                            background:rgba(239,68,68,.15);color:#fca5a5;
                            font-size:.75rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;
                        ">Cancelled</span>
                    @else
                        <span style="
                            display:inline-block;padding:.25rem .7rem;border-radius:999px;
                            background:rgba(239,68,68,.15);color:#fca5a5;
                            font-size:.75rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;
                        ">Expired</span>
                    @endif

                </div>

                <div class="dashboard-meta" style="margin-top:.75rem;">
                    @if($isTrial)
                        Trial ends {{ $subscription->trial_ends_at?->format('d M Y') }}
                    @elseif($isActive || $isCancelled)
                        {{ $daysLeft }} {{ Str::plural('day', $daysLeft) }} remaining
                    @else
                        Expired {{ $subscription->ends_at?->diffForHumans() }}
                    @endif
                </div>

                @if($portfolioLimit > 0)
                <div class="dashboard-meta" style="font-size:.8rem;margin-top:.35rem;">
                    Portfolios: {{ $portfolioCount }} / {{ $portfolioLimit }} used
                </div>
                @endif

            @else

                <div class="dashboard-value" style="font-size:1.5rem;color:var(--ink-3);">
                    No plan
                </div>
                <div class="dashboard-meta" style="margin-top:.75rem;">
                    <a href="{{ route('pricing') }}" style="color:var(--gold);font-weight:700;text-decoration:underline;">
                        View pricing plans →
                    </a>
                </div>

            @endif

        </div>

        {{-- RISK SCORE CARD --}}
        <div class="dashboard-card">

            <div class="dashboard-label">Portfolio Risk Score</div>

            @if($hasRiskScore)

                <div class="dashboard-risk-score">
                    {{ number_format($riskScore, 0) }}
                </div>

                <div class="dashboard-risk-badge {{ $riskBadgeClass }}">
                    {{ $riskLevel }} RISK
                </div>

                @if($riskGeneratedAt)
                <div class="dashboard-meta" style="font-size:.78rem;margin-top:.75rem;">
                    As of {{ $riskGeneratedAt->format('d M Y, h:i A') }}
                </div>
                @endif

            @else

                <div style="margin-top:1.5rem;color:var(--ink-3);">
                    <div style="font-size:2.5rem;margin-bottom:.5rem;">📊</div>
                    <div style="font-weight:600;font-size:.9rem;">No score yet</div>
                    <div style="font-size:.82rem;margin-top:.35rem;line-height:1.5;">
                        Your first risk signal will be generated at <strong>8:00 AM</strong> tomorrow.
                    </div>
                </div>

            @endif

        </div>

        {{-- RECOMMENDATION CARD --}}
        <div class="dashboard-card">

            <div class="dashboard-label">Recommendation</div>

            <div class="dashboard-message">
                {{ $recommendation }}
            </div>

        </div>

        {{-- NEXT ACTION CARD --}}
        <div class="dashboard-card">

            <div class="dashboard-label">Next Action</div>

            <div class="dashboard-message">
                {{ $nextAction }}
            </div>

            @if(!$hasFiles)
            <div style="margin-top:auto;padding-top:1rem;">
                <a
                    href="{{ route('portfolio.upload') }}"
                    style="
                        display:inline-block;
                        padding:.55rem 1rem;
                        border-radius:9px;
                        background:rgba(251,191,36,.12);
                        border:1px solid rgba(251,191,36,.25);
                        color:var(--gold);
                        font-weight:700;
                        font-size:.8rem;
                        text-decoration:none;
                        transition:.2s ease;
                    "
                    onmouseover="this.style.background='rgba(251,191,36,.22)'"
                    onmouseout="this.style.background='rgba(251,191,36,.12)'"
                >
                    Upload Portfolio →
                </a>
            </div>
            @endif

        </div>

    </div>

    {{-- ================================================================
       RECENT UPLOADS
    ================================================================ --}}
    <div class="card" style="margin-bottom:2rem;padding:1.75rem;">

        <div style="
            display:flex;justify-content:space-between;align-items:center;
            flex-wrap:wrap;gap:.75rem;margin-bottom:1.25rem;
        ">

            <div>
                <div class="eyebrow" style="margin-bottom:.3rem;">Recent activity</div>
                <h2 style="font-size:1.1rem;font-weight:700;margin:0;">Portfolio Uploads</h2>
            </div>

            <a
                href="{{ route('portfolio.upload') }}"
                style="
                    padding:.55rem 1.1rem;border-radius:10px;
                    border:1px solid var(--paper-3);
                    color:var(--ink-3);font-weight:600;font-size:.85rem;text-decoration:none;
                    transition:.15s ease;
                "
                onmouseover="this.style.color='var(--ink)';this.style.borderColor='var(--ink-3)'"
                onmouseout="this.style.color='var(--ink-3)';this.style.borderColor='var(--paper-3)'"
            >
                {{ $hasFiles ? 'View all →' : 'Upload file →' }}
            </a>

        </div>

        @if($hasFiles)

        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:.875rem;">
                <thead>
                    <tr style="border-bottom:1px solid var(--paper-3);">
                        <th style="text-align:left;padding:.6rem .5rem;color:var(--ink-3);font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;">File</th>
                        <th style="text-align:left;padding:.6rem .5rem;color:var(--ink-3);font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;">Status</th>
                        <th style="text-align:left;padding:.6rem .5rem;color:var(--ink-3);font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;">Uploaded</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentFiles as $file)
                    @php
                        $statusStyles = match($file->status) {
                            'processed'  => ['bg' => 'rgba(16,185,129,.15)',  'color' => '#34d399',  'label' => '✓ Processed'],
                            'failed'     => ['bg' => 'rgba(239,68,68,.15)',   'color' => '#fca5a5',  'label' => '✗ Failed'],
                            'processing' => ['bg' => 'rgba(59,130,246,.15)',  'color' => '#93c5fd',  'label' => '⟳ Processing'],
                            default      => ['bg' => 'rgba(251,191,36,.15)',  'color' => '#fbbf24',  'label' => '⏳ Pending'],
                        };
                    @endphp
                    <tr style="border-bottom:1px solid var(--paper-3);">
                        <td style="padding:.75rem .5rem;">
                            <div style="font-weight:600;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $file->original_name }}">
                                {{ $file->original_name }}
                            </div>
                            @if($file->portfolio)
                            <div style="font-size:.78rem;color:var(--ink-3);margin-top:.1rem;">{{ $file->portfolio->name }}</div>
                            @endif
                        </td>
                        <td style="padding:.75rem .5rem;">
                            <span style="
                                display:inline-block;padding:.25rem .6rem;border-radius:999px;
                                font-size:.75rem;font-weight:700;white-space:nowrap;
                                background:{{ $statusStyles['bg'] }};color:{{ $statusStyles['color'] }};
                            ">{{ $statusStyles['label'] }}</span>
                        </td>
                        <td style="padding:.75rem .5rem;color:var(--ink-3);font-size:.82rem;white-space:nowrap;">
                            {{ $file->created_at->format('d M Y, h:i A') }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @else

        <div style="
            text-align:center;
            padding:2.5rem 1rem;
            color:var(--ink-3);
        ">
            <div style="font-size:2.5rem;margin-bottom:.75rem;">📁</div>
            <div style="font-weight:600;margin-bottom:.35rem;">No portfolio files yet</div>
            <div style="font-size:.875rem;margin-bottom:1.25rem;line-height:1.6;">
                Upload a CSV, XLSX, or PDF portfolio file to start receiving<br>
                AI-powered risk analysis and daily advisor signals.
            </div>
            <a
                href="{{ route('portfolio.upload') }}"
                class="btn-primary"
                style="padding:.7rem 1.4rem;font-size:.9rem;">
                Upload Your First Portfolio →
            </a>
        </div>

        @endif

    </div>

    {{-- ================================================================
       ACTION BUTTONS
    ================================================================ --}}
    <div class="dashboard-actions">

        <a
            href="{{ route('portfolio.upload') }}"
            class="dashboard-btn dashboard-btn-primary">
            Upload Portfolio
        </a>

        <a
            href="{{ route('subscription.index') }}"
            class="dashboard-btn dashboard-btn-secondary">
            Manage Subscription
        </a>

        <a
            href="{{ route('profile.edit') }}"
            class="dashboard-btn dashboard-btn-secondary">
            My Profile
        </a>

    </div>

</div>

@endsection
