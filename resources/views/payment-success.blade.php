@extends('layouts.app')

@section('title', 'Payment Successful — RiskSignal')

@section('content')

<section style="padding:5rem 1rem;">

    <div class="container" style="max-width:640px;margin:auto;text-align:center;">

        {{-- SUCCESS ICON --}}
        <div style="
            width:72px;
            height:72px;
            margin:0 auto 1.5rem;
            border-radius:50%;
            background:rgba(16,185,129,.12);
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
            You're in. Welcome to RiskSignal.
        </h1>

        <p style="color:var(--ink-3);font-size:1rem;line-height:1.6;">
            Your subscription is now active.<br>
            Your first daily risk signal will arrive at <strong>4:30 PM</strong> today.
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
                line-height:2;
                list-style:none;
                padding:0;
            ">
                <li>📊 &nbsp;Your dashboard is ready — view your risk score anytime</li>
                <li>📁 &nbsp;Upload your portfolio to personalise your reports</li>
                <li>💬 &nbsp;Daily WhatsApp signal delivered at <strong>4:30 PM</strong></li>
                <li>📝 &nbsp;Each report includes a ready-to-use client conversation script</li>
                <li>📧 &nbsp;A confirmation email is on its way to your inbox</li>
            </ul>

        </div>

        {{-- PRIMARY CTA --}}
        <div style="margin-top:2.5rem;">

            <a
                href="{{ route('dashboard') }}"
                class="btn-primary"
                style="padding:1rem 2rem;font-size:1rem;">
                Go to Dashboard →
            </a>

        </div>

        {{-- TRUST NOTE --}}
        <p style="
            margin-top:1.5rem;
            font-size:.85rem;
            color:var(--ink-3);
            line-height:1.6;
        ">
            Need help? Reply to the confirmation email or contact
            <a href="mailto:support@risksignal.in"
               style="color:inherit;text-decoration:underline;">
                support@risksignal.in
            </a> — we respond within 2 hours.
        </p>

    </div>

</section>

@endsection
