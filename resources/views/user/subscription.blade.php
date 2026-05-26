@extends('layouts.app')

@section('title', 'My Subscription — RiskSignal')

@section('content')

<div style="max-width:720px;margin:0 auto;padding:2rem 0;">

    <div style="margin-bottom:2rem;">
        <div class="eyebrow" style="margin-bottom:.5rem;">Account</div>
        <h1 style="font-size:1.75rem;font-weight:800;">My Subscription</h1>
    </div>

    {{-- FLASH MESSAGES --}}
    @if(session('success'))
    <div style="background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.2);color:#86efac;padding:.75rem 1rem;border-radius:10px;font-size:.875rem;font-weight:600;margin-bottom:1.5rem;">
        ✓ {{ session('success') }}
    </div>
    @endif

    @if(session('error'))
    <div style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2);color:#fca5a5;padding:.75rem 1rem;border-radius:10px;font-size:.875rem;margin-bottom:1.5rem;">
        {{ session('error') }}
    </div>
    @endif

    {{-- CURRENT PLAN --}}
    @if($subscription)

    <div class="card" style="padding:1.75rem;margin-bottom:1.5rem;">

        <div class="eyebrow" style="margin-bottom:.75rem;">Current Plan</div>

        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:1.25rem;">
            <div>
                <div style="font-size:1.5rem;font-weight:800;">{{ $plan?->name ?? 'Unknown Plan' }}</div>
                <div style="color:var(--ink-3);font-size:.875rem;margin-top:.25rem;">
                    ₹{{ number_format($plan?->price ?? 0, 0) }} / month
                </div>
            </div>
            <span style="display:inline-block;padding:.35rem 1rem;border-radius:999px;font-size:.8rem;font-weight:700;text-transform:uppercase;
                {{ $subscription->status === 'active' ? 'background:rgba(16,185,129,.15);color:#34d399;' :
                   ($subscription->status === 'trial' ? 'background:rgba(250,204,21,.15);color:#fde68a;' :
                   'background:rgba(239,68,68,.12);color:#fca5a5;') }}">
                {{ ucfirst($subscription->status) }}
            </span>
        </div>

        @php
            $endDate = $subscription->ends_at ?? $subscription->trial_ends_at;
        @endphp

        @if($endDate)
        <div style="padding:1rem;border-radius:10px;background:var(--paper-2);font-size:.875rem;">
            <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:.5rem;">
                <span style="color:var(--ink-3);">
                    {{ $subscription->status === 'trial' ? 'Trial ends' : 'Renews / expires' }}:
                    <strong style="color:var(--ink);">{{ $endDate->format('d M Y') }}</strong>
                </span>
                <span style="color:var(--ink-3);">
                    <strong style="color:var(--ink);">{{ $daysLeft }}</strong> days remaining
                </span>
            </div>
        </div>
        @endif

        @if(in_array($subscription->status, ['active', 'trial']))
        <div style="margin-top:1.25rem;padding-top:1.25rem;border-top:1px solid var(--paper-3);">
            <form method="POST" action="{{ route('subscription.cancel') }}" onsubmit="return confirm('Cancel your subscription? You keep access until {{ $endDate?->format('d M Y') ?? 'expiry' }}.')">
                @csrf
                @method('DELETE')
                <button type="submit" style="padding:.7rem 1.4rem;border-radius:10px;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.25);color:#fca5a5;font-weight:700;font-size:.875rem;cursor:pointer;transition:.2s ease;" onmouseover="this.style.background='rgba(239,68,68,.18)'" onmouseout="this.style.background='rgba(239,68,68,.08)'">
                    Cancel Subscription
                </button>
            </form>
        </div>
        @endif

    </div>

    @else

    {{-- NO SUBSCRIPTION --}}
    <div class="card" style="padding:2rem;text-align:center;margin-bottom:1.5rem;">
        <div style="font-size:2rem;margin-bottom:1rem;">📊</div>
        <div style="font-size:1.1rem;font-weight:700;margin-bottom:.5rem;">No active subscription</div>
        <p style="color:var(--ink-3);font-size:.875rem;margin-bottom:1.5rem;line-height:1.6;">
            Subscribe to get daily WhatsApp risk signals and client conversation scripts.
        </p>
        <a href="{{ route('pricing') }}" class="btn-primary" style="padding:.9rem 1.75rem;font-size:.95rem;">
            View Plans →
        </a>
    </div>

    @endif

    {{-- AVAILABLE PLANS --}}
    @if($plans->count() && $subscription?->status !== 'active')
    <div class="card" style="padding:1.75rem;">
        <div class="eyebrow" style="margin-bottom:1rem;">Available Plans</div>
        <div style="display:flex;flex-direction:column;gap:.75rem;">
            @foreach($plans as $p)
            <div style="display:flex;justify-content:space-between;align-items:center;padding:1rem;border-radius:10px;background:var(--paper-2);flex-wrap:wrap;gap:.75rem;">
                <div>
                    <div style="font-weight:700;">{{ $p->name }}</div>
                    <div style="color:var(--ink-3);font-size:.8rem;">₹{{ number_format($p->price, 0) }} / month</div>
                </div>
                <a href="{{ route('checkout.show', $p->slug) }}" style="padding:.6rem 1.2rem;border-radius:10px;background:#2563eb;color:#fff;font-weight:700;font-size:.8rem;text-decoration:none;">
                    Subscribe
                </a>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <div style="margin-top:1.5rem;">
        <a href="{{ route('dashboard') }}" style="color:var(--ink-3);font-size:.875rem;text-decoration:underline;">
            ← Back to Dashboard
        </a>
    </div>

</div>

@endsection
