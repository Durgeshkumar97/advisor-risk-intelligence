@extends('layouts.app')

@section('title', 'Already Subscribed — RiskSignal')

@section('content')

<section style="padding:5rem 1rem;">

    <div style="max-width:600px;margin:auto;text-align:center;">

        {{-- ICON --}}
        <div style="
            width:72px;height:72px;
            margin:0 auto 1.5rem;
            border-radius:50%;
            background:rgba(250,204,21,.1);
            border:2px solid rgba(250,204,21,.25);
            display:flex;align-items:center;justify-content:center;
            font-size:2rem;
        ">⚡</div>

        {{-- HEADING --}}
        <div class="eyebrow" style="margin-bottom:.6rem;">Already Subscribed</div>

        <h1 style="font-size:1.75rem;font-weight:800;margin-bottom:.75rem;line-height:1.2;">
            You already have an active plan
        </h1>

        <p style="color:var(--ink-3);font-size:.95rem;line-height:1.6;">
            Your <strong>{{ $currentPlan?->name ?? 'current' }}</strong> subscription is active
            @if($subscription->ends_at)
                until <strong>{{ $subscription->ends_at->format('d M Y') }}</strong>
            @endif.
            You don't need to purchase again.
        </p>

        {{-- CURRENT PLAN CARD --}}
        <div class="card" style="margin:2rem auto;padding:1.75rem;text-align:left;max-width:420px;">

            <div class="eyebrow" style="margin-bottom:.75rem;">Your current plan</div>

            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;">
                <div>
                    <div style="font-size:1.25rem;font-weight:800;">{{ $currentPlan?->name ?? '—' }}</div>
                    <div style="color:var(--ink-3);font-size:.875rem;margin-top:.25rem;">
                        ₹{{ number_format($currentPlan?->price ?? 0, 0) }} / month
                    </div>
                </div>
                <span style="
                    display:inline-block;padding:.3rem .9rem;border-radius:999px;
                    background:rgba(16,185,129,.15);color:#34d399;
                    font-size:.8rem;font-weight:700;text-transform:uppercase;
                ">
                    {{ $subscription->status === 'trial' ? 'Trial' : 'Active' }}
                </span>
            </div>

            @if($subscription->ends_at)
            <div style="margin-top:1rem;padding-top:1rem;border-top:1px solid var(--paper-3);font-size:.875rem;color:var(--ink-3);">
                Renews / expires: <strong style="color:inherit;">{{ $subscription->ends_at->format('d M Y') }}</strong>
                &nbsp;·&nbsp;
                {{ $subscription->daysRemaining() }} days remaining
            </div>
            @endif

        </div>

        {{-- ACTIONS --}}
        <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap;margin-top:1rem;">

            <a href="{{ route('dashboard') }}" class="btn-primary" style="padding:.9rem 1.75rem;font-size:.95rem;">
                Go to Dashboard →
            </a>

            <a href="{{ route('subscription.index') }}" style="
                padding:.9rem 1.75rem;border-radius:12px;
                border:1px solid var(--paper-3);
                color:var(--ink-3);font-weight:600;font-size:.95rem;text-decoration:none;
                transition:.15s ease;
            "
            onmouseover="this.style.color='inherit'"
            onmouseout="this.style.color='var(--ink-3)'">
                Manage Subscription
            </a>

        </div>

        {{-- UPGRADE NOTICE (if requesting a higher plan) --}}
        @if($plan->price > ($currentPlan?->price ?? 0))
        <div style="
            margin-top:2rem;padding:1.25rem;border-radius:14px;
            background:rgba(250,204,21,.06);border:1px solid rgba(250,204,21,.15);
            font-size:.875rem;line-height:1.6;
        ">
            <strong style="color:var(--gold);">Interested in upgrading to {{ $plan->name }}?</strong><br>
            <span style="color:var(--ink-3);">
                Please cancel your current plan first, then purchase the new one.
                Or contact us at
                <a href="mailto:support@risksignal.in" style="color:var(--gold);">support@risksignal.in</a>
                and we'll handle the upgrade for you.
            </span>
        </div>
        @endif

    </div>

</section>

@endsection
