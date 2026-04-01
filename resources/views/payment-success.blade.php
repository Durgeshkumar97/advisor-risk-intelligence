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
            background:rgba(16,185,129,0.1);
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
            Payment successful
        </h1>

        <p style="color:var(--ink-3);font-size:1rem;">
            Your subscription is now active.
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

            <ul style="margin-top:.5rem;font-size:.9rem;line-height:1.8;list-style:disc;padding-left:1.2rem;">

                <li>You’ll receive your first weekly report on WhatsApp</li>
                <li>Delivery starts this coming Monday at 9:00 AM</li>
                <li>Each report includes a ready-to-use client script</li>

            </ul>

        </div>

        {{-- TRUST NOTE --}}
        <p style="margin-top:1.5rem;font-size:.85rem;color:var(--ink-3);">
            If you don’t receive your report, contact support — we’ll fix it immediately.
        </p>

        {{-- CTA --}}
        <div style="margin-top:2.5rem;">
            <a href="{{ route('home') }}" class="btn-primary" style="margin-right:.75rem;">
                Go to dashboard
            </a>

            <a href="/" class="btn-outline">
                Back to home
            </a>
        </div>

    </div>
</section>

@endsection
