@extends('layouts.app')

@section('content')

<style>

/* ==========================================
   CHECKOUT PAGE — PREMIUM CONVERSION MODE
========================================== */

.checkout-wrap{
    padding:4rem 1rem;
}

.checkout-shell{
    max-width:560px;
    margin:0 auto;
}

.checkout-eyebrow{
    color:#facc15;
    font-size:.78rem;
    font-weight:800;
    letter-spacing:1.6px;
    text-transform:uppercase;
    margin-bottom:.8rem;
}

.checkout-title{
    font-size:2.6rem;
    font-weight:800;
    line-height:1.08;
    margin:0 0 .8rem;
    color:#ffffff;
}

.checkout-sub{
    font-size:1rem;
    color:#94a3b8;
    margin-bottom:2rem;
    line-height:1.6;
}

.checkout-card{
    background:#0f172a;
    border:1px solid rgba(255,255,255,.08);
    border-radius:20px;
    padding:2rem;
    box-shadow:0 20px 60px rgba(0,0,0,.35);
}

.plan-chip{
    display:inline-block;
    padding:.45rem .8rem;
    border-radius:999px;
    background:rgba(250,204,21,.10);
    color:#facc15;
    font-size:.78rem;
    font-weight:700;
    margin-bottom:1rem;
}

.plan-row{
    display:flex;
    justify-content:space-between;
    align-items:flex-end;
    gap:1rem;
    margin-bottom:1.8rem;
    padding-bottom:1.3rem;
    border-bottom:1px solid rgba(255,255,255,.06);
}

.plan-name{
    font-size:1.15rem;
    color:#e2e8f0;
    font-weight:700;
}

.plan-price{
    font-size:2.1rem;
    font-weight:900;
    color:#ffffff;
}

.plan-price small{
    font-size:.95rem;
    color:#94a3b8;
    font-weight:500;
}

.form-group{
    margin-bottom:1rem;
}

.form-label{
    display:block;
    margin-bottom:.5rem;
    font-size:.95rem;
    font-weight:600;
    color:#e2e8f0;
}

.form-input{
    width:100%;
    padding:15px 16px;
    border-radius:12px;
    border:1px solid #334155;
    background:#111827;
    color:#ffffff;
    font-size:1rem;
    transition:.2s ease;
    outline:none;
}

.form-input::placeholder{
    color:#64748b;
}

.form-input:focus{
    border-color:#facc15;
    box-shadow:0 0 0 4px rgba(250,204,21,.08);
}

/* Autofill Fix */
.form-input:-webkit-autofill,
.form-input:-webkit-autofill:hover,
.form-input:-webkit-autofill:focus{
    -webkit-text-fill-color:#ffffff !important;
    -webkit-box-shadow:0 0 0px 1000px #111827 inset !important;
    transition:background-color 5000s ease-in-out 0s;
    caret-color:#ffffff;
}

.checkout-btn{
    width:100%;
    margin-top:1rem;
    border:none;
    border-radius:14px;
    padding:16px;
    background:#ffffff;
    color:#020617;
    font-size:1rem;
    font-weight:800;
    cursor:pointer;
    transition:.2s ease;
}

.checkout-btn:hover{
    transform:translateY(-1px);
    box-shadow:0 10px 24px rgba(255,255,255,.08);
}

.trust-box{
    margin-top:1.2rem;
    text-align:center;
    color:#94a3b8;
    font-size:.9rem;
    line-height:1.7;
}

.mini-points{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:.75rem;
    margin-top:1.4rem;
}

.mini-item{
    padding:.8rem;
    border-radius:12px;
    background:#111827;
    border:1px solid rgba(255,255,255,.05);
    color:#cbd5e1;
    font-size:.82rem;
    text-align:center;
}

.alert-box{
    margin-bottom:1rem;
    padding:1rem;
    border-radius:12px;
    background:rgba(239,68,68,.10);
    border:1px solid rgba(239,68,68,.18);
    color:#fecaca;
}

.alert-box ul{
    margin:0;
    padding-left:1rem;
}

@media (max-width:640px){

    .checkout-wrap{
        padding:2rem .8rem;
    }

    .checkout-title{
        font-size:2rem;
    }

    .checkout-card{
        padding:1.2rem;
        border-radius:18px;
    }

    .plan-row{
        flex-direction:column;
        align-items:flex-start;
    }

    .mini-points{
        grid-template-columns:1fr;
    }
}

</style>


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
                    {{ ucfirst($plan) }}
                </div>

                <div class="plan-price">
                    ₹{{ number_format($price) }}
                    <small>/ month</small>
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
                    value="{{ $plan }}"
                >

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
                        required
                    >

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
                        required
                    >

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
                        required
                    >

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
document.addEventListener("DOMContentLoaded", function () {

    console.log("🚀 Payment script initialized");

    const btn = document.getElementById('payBtn');

    if (!btn) {
        console.error("❌ payBtn not found");
        return;
    }

    let isProcessing = false; // 🔐 prevent double click

    btn.addEventListener('click', async function () {

        if (isProcessing) return; // prevent spam click
        isProcessing = true;

        const btnEl = this;
        btnEl.innerText = "Processing...";
        btnEl.disabled = true;

        try {

            // 🔹 Get inputs
            const name  = document.getElementById('name')?.value.trim();
            const phone = document.getElementById('phone')?.value.trim();
            const email = document.getElementById('email')?.value.trim();
            const plan  = "{{ $plan }}";

            // 🔹 Validation
            if (!name || !phone || !email) {
                throw new Error("Please fill all fields");
            }

            if (!/^\d{10}$/.test(phone)) {
                throw new Error("Invalid phone number");
            }

            if (!/^\S+@\S+\.\S+$/.test(email)) {
                throw new Error("Invalid email");
            }

            // 🔹 Create order
            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 15000);

            const response = await fetch("/payment/create", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": "{{ csrf_token() }}"
                },
                body: JSON.stringify({ name, phone, email, plan }),
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

            // 🔹 Razorpay instance
            const rzp = new Razorpay({
                key: data.key,
                amount: data.amount,
                currency: "INR",
                name: "RiskSignal",
                description: "Subscription Payment",
                order_id: data.order_id,

                handler: async function (response) {

                    try {

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
                            throw new Error("Verification request failed");
                        }

                        const result = await verifyRes.json();

                        if (!result.success) {
                            throw new Error(result.error || "Payment verification failed");
                        }

                        // ✅ success redirect
                        window.location.href = result.redirect || "/";

                    } catch (err) {
                        console.error("Verify error:", err);
                        alert("Payment verification failed. Contact support.");
                        resetButton();
                    }
                },

                modal: {
                    ondismiss: function () {
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

            rzp.on('payment.failed', function (response) {
                console.error("Payment failed:", response.error);
                alert("Payment failed. Please try again.");
                resetButton();
            });

            rzp.open();

        } catch (error) {

            console.error("🔥 Payment flow error:", error);
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