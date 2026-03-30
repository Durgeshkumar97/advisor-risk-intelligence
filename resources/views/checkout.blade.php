@extends('layouts.app')

@section('content')

<div class="container" style="padding:4rem 0;max-width:500px;">

    <h2 style="margin-bottom:1rem;">Secure Checkout</h2>

    <div class="card" style="padding:2rem;">

        <div style="margin-bottom:1rem;">
            <strong>Plan:</strong> {{ ucfirst($plan) }}
        </div>

        <div style="margin-bottom:1.5rem;font-size:1.5rem;">
            ₹{{ $price }} / month
        </div>

        <form method="POST" action="{{ route('checkout.process') }}">
            @csrf

            <input type="hidden" name="plan" value="{{ $plan }}">

            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="name" required>
            </div>

            <div class="form-group">
                <label>WhatsApp Number</label>
                <input type="text" name="phone" required>
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email">
            </div>

            <button class="btn-primary" style="width:100%;margin-top:1rem;">
                Complete Purchase
            </button>

        </form>

        <div style="margin-top:1rem;font-size:.8rem;color:var(--ink-3);">
            🔒 Secure payment powered by Razorpay (coming soon)
        </div>

    </div>

</div>

@endsection
 