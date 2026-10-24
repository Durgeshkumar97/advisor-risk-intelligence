@extends('layouts.app')

@section('title', 'Pricing — RiskSignal')

@section('content')

<section style="padding:4rem 0 2rem;">

    {{-- HEADER --}}
    <div style="text-align:center;max-width:640px;margin:0 auto 3rem;">

        <div class="eyebrow" style="margin-bottom:.75rem;">
            Simple, transparent pricing
        </div>

        <h1 style="font-size:2.25rem;font-weight:800;line-height:1.2;margin-bottom:1rem;">
            Choose the plan that fits<br>your advisory practice
        </h1>

        <p style="color:var(--ink-3);font-size:1rem;line-height:1.6;">
            Every plan: a risk report PDF emailed after each upload.
            @if($trialDays)
                Start with a {{ $trialDays }}-day free trial.
            @endif
            No credit card required to start.
        </p>

    </div>

    {{-- PLAN GRID --}}
    <div style="
        display:grid;
        grid-template-columns:repeat(auto-fit,minmax(280px,1fr));
        gap:1.5rem;
        max-width:1000px;
        margin:0 auto;
    ">

        {{-- ================================================================
           STARTER PLAN
        ================================================================ --}}
        <div class="card" style="padding:2rem;position:relative;">

            <div class="eyebrow" style="margin-bottom:.5rem;">Starter</div>

            <div style="margin-bottom:1.5rem;">
                <span style="font-size:2.5rem;font-weight:800;color:var(--ink-1);">
                    ₹999
                </span>
                <span style="color:var(--ink-3);font-size:.9rem;">/ month</span>
            </div>

            <ul style="
                list-style:none;
                padding:0;
                margin:0 0 2rem;
                font-size:.9rem;
                line-height:2.2;
                color:var(--ink-2);
            ">
                <li>✅ &nbsp;50 clients / month</li>
                <li>✅ &nbsp;Risk report PDF after each upload</li>
                <li>✅ &nbsp;Daily 8 AM email: latest upload's score</li>
                <li>✅ &nbsp;Email support</li>
                @if($trialDays)
                <li>✅ &nbsp;{{ $trialDays }}-day free trial</li>
                @endif
            </ul>

            <a
                href="{{ route('checkout.show', 'starter') }}"
                class="btn-primary"
                style="display:block;text-align:center;padding:.9rem 1.5rem;">
                Get Started
            </a>

        </div>

        {{-- ================================================================
           PRO PLAN (highlighted)
        ================================================================ --}}
        <div class="card" style="
            padding:2rem;
            position:relative;
            border:2px solid #fbbf24;
        ">

            <div class="eyebrow" style="margin-bottom:.5rem;">Pro</div>

            <div style="margin-bottom:1.5rem;">
                <span style="font-size:2.5rem;font-weight:800;color:var(--ink-1);">
                    ₹2,499
                </span>
                <span style="color:var(--ink-3);font-size:.9rem;">/ month</span>
            </div>

            <ul style="
                list-style:none;
                padding:0;
                margin:0 0 2rem;
                font-size:.9rem;
                line-height:2.2;
                color:var(--ink-2);
            ">
                <li>✅ &nbsp;Up to 250 clients / month</li>
                <li>✅ &nbsp;Risk report PDF after each upload</li>
                <li>✅ &nbsp;Daily 8 AM email: latest upload's score</li>
                <li>✅ &nbsp;Email support</li>
                @if($trialDays)
                <li>✅ &nbsp;{{ $trialDays }}-day free trial</li>
                @endif
            </ul>

            <a
                href="{{ route('checkout.show', 'pro') }}"
                class="btn-primary"
                style="display:block;text-align:center;padding:.9rem 1.5rem;">
                Get Pro →
            </a>

        </div>

        {{-- ================================================================
           TEAM PLAN
        ================================================================ --}}
        <div class="card" style="padding:2rem;position:relative;">

            <div class="eyebrow" style="margin-bottom:.5rem;">Team</div>

            <div style="margin-bottom:1.5rem;">
                <span style="font-size:2.5rem;font-weight:800;color:var(--ink-1);">
                    ₹4,999
                </span>
                <span style="color:var(--ink-3);font-size:.9rem;">/ month</span>
            </div>

            <ul style="
                list-style:none;
                padding:0;
                margin:0 0 2rem;
                font-size:.9rem;
                line-height:2.2;
                color:var(--ink-2);
            ">
                <li>✅ &nbsp;Up to 1,000 clients / month</li>
                <li>✅ &nbsp;Risk report PDF after each upload</li>
                <li>✅ &nbsp;Daily 8 AM email: latest upload's score</li>
                <li>✅ &nbsp;Email support</li>
                @if($trialDays)
                <li>✅ &nbsp;{{ $trialDays }}-day free trial</li>
                @endif
            </ul>

            <a
                href="{{ route('checkout.show', 'team') }}"
                class="btn-primary"
                style="display:block;text-align:center;padding:.9rem 1.5rem;">
                Get Team →
            </a>

        </div>

    </div>

    {{-- TRUST ROW --}}
    <div style="
        text-align:center;
        margin-top:3rem;
        color:var(--ink-3);
        font-size:.875rem;
        line-height:2;
    ">
        <p>🔒 &nbsp;Secure payment via Razorpay &nbsp;·&nbsp; 30-day plan · No auto-debit &nbsp;·&nbsp; No hidden fees</p>
        <p>Questions? Email <a href="mailto:support@risksignal.in" style="color:inherit;text-decoration:underline;">support@risksignal.in</a></p>
    </div>

</section>

@endsection
