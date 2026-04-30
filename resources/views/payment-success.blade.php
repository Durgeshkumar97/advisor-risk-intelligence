@extends('layouts.app')

@section('content')

<section style="padding:5rem 1rem;">

    <div class="container" style="max-width:640px;margin:auto;text-align:center;">

        {{-- SUCCESS ICON --}}
        <div style="
            width:72px;
            height:72px;
            margin:0 auto 1.5rem;
            border-radius:50%;
            background:rgba(16,185,129,.10);
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:2rem;
            color:#10b981;
        ">
            ✓
        </div>

        {{-- HEADING --}}
        <h1 style="margin-bottom:.75rem;">
            Payment Successful
        </h1>

        <p style="color:var(--ink-3);font-size:1rem;">
            Your RiskSignal subscription is now active.
        </p>

        {{-- CONFIRMATION CARD --}}
        <div class="card" style="
            margin-top:2rem;
            padding:1.75rem;
            text-align:left;
        ">

            <div class="eyebrow" style="margin-bottom:.6rem;">
                What happens next
            </div>

            <ul style="
                margin-top:.5rem;
                font-size:.95rem;
                line-height:1.9;
                list-style:disc;
                padding-left:1.2rem;
            ">

                <li>Your onboarding is complete</li>
                <li>You will start receiving risk insights shortly</li>
                <li>Each report includes ready-to-use client communication</li>
                <li>No setup required from your side</li>

            </ul>

        </div>

        {{-- TRUST NOTE --}}
        <p style="
            margin-top:1.5rem;
            font-size:.88rem;
            color:var(--ink-3);
        ">
            If you face any issue, contact support — we’ll fix it immediately.
        </p>

        {{-- CTA --}}
        <div style="margin-top:2.5rem;">

            <a href="{{ route('home') }}" class="btn-primary" style="margin-right:.75rem;">
                Back to Home
            </a>

        </div>

    </div>

</section>

@endsection