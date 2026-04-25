@extends('layouts.app')

@section('content')

<div class="container" style="padding:4rem 0; max-width:520px;">

    <h2 style="margin-bottom:1rem;">
        Secure Checkout
    </h2>

    <p style="margin-bottom:2rem; color:var(--ink-3);">
        Complete your subscription in under 30 seconds.
    </p>

    <div
        class="card"
        style="
            padding:2rem;
            background:var(--paper);
            border:1px solid var(--paper-3);
            border-radius:14px;
        "
    >

        <div style="margin-bottom:1rem;">
            <strong>Selected Plan:</strong>
            {{ ucfirst($plan) }}
        </div>

        <div
            style="
                margin-bottom:1.5rem;
                font-size:1.7rem;
                font-weight:700;
                color:var(--ink);
            "
        >
            ₹{{ number_format($price) }} / month
        </div>

        @if ($errors->any())
            <div
                class="alert-warning"
                style="
                    margin-bottom:1rem;
                    padding:1rem;
                    border-radius:8px;
                "
            >
                <ul style="margin:0; padding-left:1rem;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('checkout.process') }}">

            @csrf

            <input
                type="hidden"
                name="plan"
                value="{{ $plan }}"
            >

            <div style="margin-bottom:1rem;">

                <label
                    for="name"
                    style="
                        display:block;
                        margin-bottom:.45rem;
                        font-weight:500;
                    "
                >
                    Full Name
                </label>

                <input
                    id="name"
                    type="text"
                    name="name"
                    value="{{ old('name') }}"
                    class="input"
                    required
                >

            </div>

            <div style="margin-bottom:1rem;">

                <label
                    for="phone"
                    style="
                        display:block;
                        margin-bottom:.45rem;
                        font-weight:500;
                    "
                >
                    WhatsApp Number
                </label>

                <input
                    id="phone"
                    type="tel"
                    name="phone"
                    value="{{ old('phone') }}"
                    class="input"
                    inputmode="numeric"
                    required
                >

            </div>

            <div style="margin-bottom:1rem;">

                <label
                    for="email"
                    style="
                        display:block;
                        margin-bottom:.45rem;
                        font-weight:500;
                    "
                >
                    Email Address
                </label>

                <input
                    id="email"
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    class="input"
                >

            </div>

            <button
                type="submit"
                class="btn-primary"
                style="
                    width:100%;
                    margin-top:1rem;
                "
            >
                Complete Purchase
            </button>

        </form>

        <div
            style="
                margin-top:1rem;
                font-size:.85rem;
                color:var(--ink-3);
                text-align:center;
            "
        >
            🔒 Secure payment powered by Razorpay
        </div>

    </div>

</div>

@endsection