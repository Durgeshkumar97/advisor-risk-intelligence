@extends('layouts.app')

@section('content')

<div class="container" style="padding:4rem 0;max-width:520px;">

    <h2 style="margin-bottom:1rem;">Secure Checkout</h2>

    <p style="margin-bottom:2rem;color:var(--ink-3);">
        Complete your subscription in under 30 seconds.
    </p>

    <div class="card" style="
        padding:2rem;
        border:1px solid rgba(255,255,255,.08);
        border-radius:14px;
        background:#0f172a;
    ">

        <div style="margin-bottom:1rem;">
            <strong>Selected Plan:</strong> {{ ucfirst($plan) }}
        </div>

        <div style="
            margin-bottom:1.5rem;
            font-size:1.7rem;
            font-weight:700;
            color:#fff;
        ">
            ₹{{ number_format($price) }} / month
        </div>

        @if ($errors->any())
            <div style="
                margin-bottom:1rem;
                padding:1rem;
                background:#3b0d0d;
                border-radius:8px;
                color:#fff;
            ">
                <ul style="margin:0;padding-left:1rem;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('checkout.process') }}">
            @csrf

            <input type="hidden" name="plan" value="{{ $plan }}">

            <div style="margin-bottom:1rem;">
                <label style="display:block;margin-bottom:.4rem;">Full Name</label>
                <input
                    type="text"
                    name="name"
                    value="{{ old('name') }}"
                    required
                    style="width:100%;padding:.9rem;border-radius:8px;"
                >
            </div>

            <div style="margin-bottom:1rem;">
                <label style="display:block;margin-bottom:.4rem;">WhatsApp Number</label>
                <input
                    type="text"
                    name="phone"
                    value="{{ old('phone') }}"
                    required
                    style="width:100%;padding:.9rem;border-radius:8px;"
                >
            </div>

            <div style="margin-bottom:1rem;">
                <label style="display:block;margin-bottom:.4rem;">Email Address</label>
                <input
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    style="width:100%;padding:.9rem;border-radius:8px;"
                >
            </div>

            <button
                type="submit"
                class="btn-primary"
                style="width:100%;margin-top:1rem;"
            >
                Complete Purchase
            </button>

        </form>

        <div style="
            margin-top:1rem;
            font-size:.85rem;
            color:var(--ink-3);
        ">
            🔒 Secure payment powered by Razorpay
        </div>

    </div>

</div>

@endsection