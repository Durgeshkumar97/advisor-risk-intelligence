@extends('layouts.app')

@section('content')

<div class="checkout-wrap">

    <div class="checkout-shell">

        <div class="checkout-eyebrow">
            Secure Advisor Checkout
        </div>

        <h1 class="checkout-title">
            Activate RiskSignal
        </h1>

        <p class="checkout-sub">
            Join advisors using daily risk intelligence to retain trust and grow AUM.
            Checkout takes less than 30 seconds.
        </p>

        <div class="checkout-card">

            <div class="plan-chip">
                Selected Plan
            </div>

            <div class="plan-row">

                <div class="plan-name">
                    {{ $plan->name }}
                </div>

                <div class="plan-price">
                    ₹{{ number_format($plan->price) }}
                    <small>/ {{ $plan->duration_days }} days</small>
                </div>

            </div>
        </div>

        @if ($errors->any())

        <div class="alert-box">

            <ul>
                @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>

        </div>

        @endif


        <form id="payment-form">

            @csrf

            <input
                type="hidden"
                name="plan"
                value="{{ $plan->slug }}">
            <div class="form-group">

                <label for="name" class="form-label">
                    Full Name
                </label>

                <input
                    id="name"
                    type="text"
                    name="name"
                    value="{{ old('name') }}"
                    class="form-input"
                    placeholder="Enter your full name"
                    autocomplete="name"
                    required>

            </div>


            <div class="form-group">

                <label for="phone" class="form-label">
                    WhatsApp Number
                </label>

                <input
                    id="phone"
                    type="tel"
                    name="phone"
                    value="{{ old('phone') }}"
                    class="form-input"
                    placeholder="Enter active WhatsApp number"
                    autocomplete="tel"
                    inputmode="numeric"
                    required>

            </div>


            <div class="form-group">

                <label for="email" class="form-label">
                    Email Address
                </label>

                <input
                    id="email"
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    class="form-input"
                    placeholder="Enter email address"
                    autocomplete="email"
                    required>

            </div>


            <button type="button" id="payBtn" class="checkout-btn">
                Complete Purchase
            </button>

        </form>


        <div class="mini-points">

            <div class="mini-item">
                🔒 Secure Razorpay Checkout
            </div>

            <div class="mini-item">
                ⚡ Instant Plan Activation
            </div>

            <div class="mini-item">
                📈 Built For Advisors
            </div>

            <div class="mini-item">
                💬 Priority Support
            </div>

        </div>


        <div class="trust-box">
            Cancel anytime. No hidden fees. <br>
            Your data stays private and secure.
        </div>

    </div>

</div>

</div>

@push('scripts')
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>

<script>
    document.addEventListener("DOMContentLoaded", function() {

        console.log("Payment script initialized");

        const btn = document.getElementById('payBtn');

        if (!btn) {
            console.error("payBtn not found");
            return;
        }

        let isProcessing = false;

        btn.addEventListener('click', async function() {

            if (isProcessing) return;
            isProcessing = true;

            const btnEl = this;
            btnEl.innerText = "Processing...";
            btnEl.disabled = true;

            try {

                const name = document.getElementById('name')?.value.trim();
                const phone = document.getElementById('phone')?.value.trim();
                const email = document.getElementById('email')?.value.trim();
                const plan = "{{ $plan->slug }}";

                /*
                |--------------------------------------------------------------------------
                | VALIDATION
                |--------------------------------------------------------------------------
                */

                if (!name || !phone || !email) {
                    throw new Error("Please fill all fields");
                }

                if (!/^\d{10}$/.test(phone)) {
                    throw new Error("Invalid phone number");
                }

                if (!/^\S+@\S+\.\S+$/.test(email)) {
                    throw new Error("Invalid email");
                }

                /*
                |--------------------------------------------------------------------------
                | CREATE ORDER
                |--------------------------------------------------------------------------
                */

                const controller = new AbortController();
                const timeout = setTimeout(() => controller.abort(), 15000);

                const response = await fetch("/payment/create", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": "{{ csrf_token() }}"
                    },
                    body: JSON.stringify({
                        name,
                        phone,
                        email,
                        plan
                    }),
                    signal: controller.signal
                });

                clearTimeout(timeout);

                if (!response.ok) {
                    throw new Error("Server error while creating order");
                }

                const data = await response.json();

                if (!data.success || !data.order_id) {
                    throw new Error(data.error || "Invalid order response");
                }

                /*
                |--------------------------------------------------------------------------
                | RAZORPAY
                |--------------------------------------------------------------------------
                */

                const rzp = new Razorpay({
                    key: data.key,
                    amount: data.amount,
                    currency: "INR",
                    name: "RiskSignal",
                    description: "Subscription Payment",
                    order_id: data.order_id,

                    handler: async function(response) {

                        try {

                            /*
                            |--------------------------------------------------------------------------
                            | VERIFY (OPTIONAL BUT SAFE)
                            |--------------------------------------------------------------------------
                            */

                            const verifyRes = await fetch("/payment/verify", {
                                method: "POST",
                                headers: {
                                    "Content-Type": "application/json",
                                    "X-CSRF-TOKEN": "{{ csrf_token() }}"
                                },
                                body: JSON.stringify({
                                    razorpay_payment_id: response.razorpay_payment_id,
                                    razorpay_order_id: response.razorpay_order_id,
                                    razorpay_signature: response.razorpay_signature,
                                    name,
                                    email,
                                    phone,
                                    plan
                                })
                            });

                            if (!verifyRes.ok) {
                                throw new Error("Verification failed");
                            }

                            const result = await verifyRes.json();

                            if (!result.success) {
                                throw new Error(result.error || "Payment verification failed");
                            }

                            /*
                            |--------------------------------------------------------------------------
                            | AUTO LOGIN REDIRECT (FINAL)
                            |--------------------------------------------------------------------------
                            */

                            window.location.href =
                                "/payment/success?order_id=" + response.razorpay_order_id;

                        } catch (err) {
                            console.error("Verify error:", err);
                            alert("Payment verification failed. Contact support.");
                            resetButton();
                        }
                    },

                    modal: {
                        ondismiss: function() {
                            console.log("User closed Razorpay modal");
                            resetButton();
                        }
                    },

                    prefill: {
                        name: name,
                        email: email,
                        contact: phone
                    },

                    theme: {
                        color: "#0f172a"
                    }
                });

                rzp.on('payment.failed', function(response) {
                    console.error("Payment failed:", response.error);
                    alert("Payment failed. Please try again.");
                    resetButton();
                });

                rzp.open();

            } catch (error) {

                console.error("Payment flow error:", error);
                alert(error.message || "Something went wrong");

                resetButton();
            }

            function resetButton() {
                isProcessing = false;
                btnEl.innerText = "Complete Purchase";
                btnEl.disabled = false;
            }

        });

    });
</script>
@endpush
@endsection
