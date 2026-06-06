@extends('layouts.app')

@section('title', 'My Subscription — RiskSignal')

@section('content')

<div style="max-width:700px;margin:0 auto;padding:2rem 0;">

    {{-- PAGE HEADER --}}
    <div style="margin-bottom:2.5rem;">

        <div class="eyebrow" style="margin-bottom:.5rem;">
            Account
        </div>

        <h1 style="font-size:1.75rem;font-weight:800;">
            My Subscription
        </h1>

    </div>

    {{-- STATUS BANNER --}}
    @php
        $daysLeft = $subscription->daysRemaining();
        $isExpiringSoon = $subscription->isExpiringSoon(7);
        $isExpired = $subscription->isExpired();
        $isInGrace = $subscription->isInGracePeriod();
        $isCancelled = $subscription->status === 'cancelled';
        $isTrial = $subscription->isTrial();
        $isActive = $subscription->isActive();
    @endphp

    @if($isExpired && !$isInGrace)

        <div class="alert alert-error" style="margin-bottom:1.5rem;">
            ⚠️ Your subscription has expired. Renew now to continue receiving daily risk signals.
        </div>

    @elseif($isInGrace)

        <div class="alert alert-error" style="margin-bottom:1.5rem;">
            ⚠️ Your subscription expired {{ $subscription->ends_at?->diffForHumans() }}. You're in a 3-day grace period — renew now to avoid losing access.
        </div>

    @elseif($isCancelled)

        <div class="alert alert-error" style="margin-bottom:1.5rem;">
            ℹ️ Your subscription has been cancelled. You retain access until {{ $subscription->ends_at?->format('d M Y') }}.
        </div>

    @elseif($isExpiringSoon)

        <div class="alert" style="
            background:rgba(251,191,36,.1);
            border:1px solid rgba(251,191,36,.25);
            color:#fbbf24;
            margin-bottom:1.5rem;
        ">
            ⏳ Your subscription expires in <strong>{{ $daysLeft }} day{{ $daysLeft !== 1 ? 's' : '' }}</strong> ({{ $subscription->ends_at?->format('d M Y') }}). Renew now to avoid interruption.
        </div>

    @elseif($isTrial)

        <div class="alert" style="
            background:rgba(16,185,129,.1);
            border:1px solid rgba(16,185,129,.2);
            color:#34d399;
            margin-bottom:1.5rem;
        ">
            🎉 You're on a free trial — <strong>{{ $daysLeft }} day{{ $daysLeft !== 1 ? 's' : '' }}</strong> remaining. Upgrade before your trial ends to keep access.
        </div>

    @endif

    {{-- PLAN CARD --}}
    <div class="card" style="padding:1.75rem;margin-bottom:1.5rem;">

        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem;">

            <div>
                <div class="eyebrow" style="margin-bottom:.35rem;">Current Plan</div>
                <div style="font-size:1.5rem;font-weight:800;">{{ $plan->name ?? '—' }}</div>
                <div style="color:var(--ink-3);font-size:.9rem;margin-top:.25rem;">
                    ₹{{ number_format($plan->price ?? 0, 0) }} / month
                </div>
            </div>

            <div style="text-align:right;">
                @if($isActive || $isTrial)
                    <span style="
                        display:inline-block;
                        padding:.3rem .9rem;
                        border-radius:999px;
                        background:rgba(16,185,129,.15);
                        color:#34d399;
                        font-size:.8rem;
                        font-weight:700;
                        text-transform:uppercase;
                        letter-spacing:.06em;
                    ">
                        {{ $isTrial ? 'Trial' : 'Active' }}
                    </span>
                @elseif($isCancelled)
                    <span style="
                        display:inline-block;
                        padding:.3rem .9rem;
                        border-radius:999px;
                        background:rgba(239,68,68,.15);
                        color:#fca5a5;
                        font-size:.8rem;
                        font-weight:700;
                        text-transform:uppercase;
                        letter-spacing:.06em;
                    ">
                        Cancelled
                    </span>
                @else
                    <span style="
                        display:inline-block;
                        padding:.3rem .9rem;
                        border-radius:999px;
                        background:rgba(239,68,68,.15);
                        color:#fca5a5;
                        font-size:.8rem;
                        font-weight:700;
                        text-transform:uppercase;
                        letter-spacing:.06em;
                    ">
                        Expired
                    </span>
                @endif
            </div>

        </div>

        <hr style="margin:1.25rem 0;border:none;border-top:1px solid var(--paper-3);">

        {{-- PLAN DETAILS --}}
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem 1.5rem;font-size:.9rem;">

            <div>
                <div style="color:var(--ink-3);font-size:.8rem;margin-bottom:.2rem;">
                    Started
                </div>
                <div>{{ $subscription->starts_at?->format('d M Y') ?? '—' }}</div>
            </div>

            <div>
                <div style="color:var(--ink-3);font-size:.8rem;margin-bottom:.2rem;">
                    {{ $isCancelled ? 'Access until' : ($isExpired ? 'Expired on' : 'Renews on') }}
                </div>
                <div>{{ $subscription->ends_at?->format('d M Y') ?? '—' }}</div>
            </div>

            <div>
                <div style="color:var(--ink-3);font-size:.8rem;margin-bottom:.2rem;">
                    Clients / mo
                </div>
                <div>{{ $plan->monthly_client_limit ?? 50 }}</div>
            </div>

            <div>
                <div style="color:var(--ink-3);font-size:.8rem;margin-bottom:.2rem;">
                    Delivery
                </div>
                <div>Email delivery</div>
            </div>

        </div>

    </div>

    {{-- PLAN FEATURES --}}
    <div class="card" style="padding:1.75rem;margin-bottom:1.5rem;">

        <div class="eyebrow" style="margin-bottom:1rem;">
            Plan features
        </div>

        <ul style="list-style:none;padding:0;margin:0;font-size:.9rem;line-height:2.2;">
            <li>{{ ($plan->daily_reports ?? false) ? '✅' : '✗' }} &nbsp;Daily risk reports</li>
            <li>{{ ($plan->whatsapp_delivery ?? false) ? '✅' : '✗' }} &nbsp;WhatsApp delivery</li>
            <li>{{ ($plan->client_script ?? false) ? '✅' : '✗' }} &nbsp;Client conversation script</li>
            <li>{{ ($plan->branded_pdf ?? false) ? '✅' : '✗' }} &nbsp;Branded PDF reports</li>
            <li>{{ ($plan->priority_support ?? false) ? '✅' : '✗' }} &nbsp;Priority support</li>
            <li>{{ ($plan->multi_advisor ?? false) ? '✅' : '✗' }} &nbsp;Multi-advisor access</li>
        </ul>

    </div>

    {{-- ACTIONS --}}
    <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:2rem;">

        {{-- RENEW / UPGRADE --}}
        @if($isExpired || $isInGrace || $isExpiringSoon || $isCancelled || $isTrial)

            <a
                href="{{ route('pricing') }}"
                class="btn-primary"
                style="padding:.85rem 1.75rem;font-size:.95rem;">
                {{ ($isExpired && !$isInGrace) || $isCancelled ? 'Renew Subscription' : 'Upgrade Plan' }} →
            </a>

        @endif

        {{-- CANCEL (only if still active/trial) --}}
        @if($isActive || $isTrial)

            <form
                method="POST"
                action="{{ route('subscription.cancel') }}"
                onsubmit="return confirm('Are you sure you want to cancel? You keep access until {{ $subscription->ends_at?->format('d M Y') }}.');">

                @csrf
                @method('DELETE')

                <button
                    type="submit"
                    style="
                        padding:.85rem 1.75rem;
                        border-radius:12px;
                        border:1px solid rgba(239,68,68,.35);
                        background:rgba(239,68,68,.08);
                        color:#fca5a5;
                        font-weight:700;
                        font-size:.95rem;
                        cursor:pointer;
                        transition:.2s ease;
                    "
                    onmouseover="this.style.background='rgba(239,68,68,.16)'"
                    onmouseout="this.style.background='rgba(239,68,68,.08)'">
                    Cancel Subscription
                </button>

            </form>

        @endif

    </div>

    {{-- BACK TO DASHBOARD --}}
    <div>
        <a
            href="{{ route('dashboard') }}"
            style="color:var(--ink-3);font-size:.875rem;text-decoration:underline;">
            ← Back to Dashboard
        </a>
    </div>

</div>

@endsection
