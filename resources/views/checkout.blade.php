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

        <form id="payment-form" novalidate>

            @csrf

            <input
                type="hidden"
                name="plan"
                value="{{ $plan->slug }}">

            {{-- NAME --}}
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
                    maxlength="120"
                    required>

            </div>

            {{-- PHONE --}}
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
                    maxlength="15"
                    required>

            </div>

            {{-- EMAIL --}}
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
                    maxlength="255"
                    required>

            </div>

            <button
                type="button"
                id="payBtn"
                class="checkout-btn"
                aria-label="Complete secure payment">

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

@push('scripts')

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>

<script>
    document.addEventListener("DOMContentLoaded", () => {

        /*
        |--------------------------------------------------------------------------
        | ELEMENTS
        |--------------------------------------------------------------------------
        */

        const payBtn = document.getElementById("payBtn");

        if (!payBtn) {
            console.error("Pay button not found");
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | STATE
        |--------------------------------------------------------------------------
        */

        let processing = false;

        /*
        |--------------------------------------------------------------------------
        | HELPERS
        |--------------------------------------------------------------------------
        */

        const csrfToken = document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute("content");

        function setButtonLoading() {
            processing = true;
            payBtn.disabled = true;
            payBtn.innerText = "Processing...";
            payBtn.style.opacity = "0.7";
            payBtn.style.cursor = "not-allowed";
        }

        function resetButton() {
            processing = false;
            payBtn.disabled = false;
            payBtn.innerText = "Complete Purchase";
            payBtn.style.opacity = "1";
            payBtn.style.cursor = "pointer";
        }

        function showError(message) {
            alert(message || "Something went wrong.");
        }

        function validateInput(name, phone, email) {

            if (name.length === 0 || phone.length === 0 || email.length === 0) {
                throw new Error("Please fill all fields.");
            }

            if (name.length < 2) {
                throw new Error("Please enter valid full name.");
            }

            if (!/^[6-9]\d{9}$/.test(phone)) {
                throw new Error("Please enter valid Indian mobile number.");
            }

            if (!/^\S+@\S+\.\S+$/.test(email)) {
                throw new Error("Please enter valid email address.");
            }
        }

        async function safeJson(response) {

            const text = await response.text();

            try {
                return JSON.parse(text);
            } catch {
                throw new Error("Invalid server response.");
            }
        }

        /*
        |--------------------------------------------------------------------------
        | PAYMENT FLOW
        |--------------------------------------------------------------------------
        */

        payBtn.addEventListener("click", async () => {

            if (processing) return;

            setButtonLoading();

            try {

                /*
                |--------------------------------------------------------------------------
                | CHECK RAZORPAY SDK
                |--------------------------------------------------------------------------
                */

                if (typeof Razorpay === "undefined") {
                    throw new Error(
                        "Payment SDK failed to load. Refresh and try again."
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | FORM DATA
                |--------------------------------------------------------------------------
                */

                const name = document.getElementById("name")?.value?.trim() || "";
                const phone = document.getElementById("phone")?.value?.trim() || "";
                const email = document.getElementById("email")?.value?.trim() || "";

                validateInput(name, phone, email);

                /*
                |--------------------------------------------------------------------------
                | CREATE ORDER
                |--------------------------------------------------------------------------
                */

                const controller = new AbortController();

                const timeout = setTimeout(() => {
                    controller.abort();
                }, 15000);

                const orderResponse = await fetch("/payment/create", {
                    method: "POST",

                    headers: {
                        "Content-Type": "application/json",
                        "Accept": "application/json",
                        "X-CSRF-TOKEN": csrfToken
                    },

                    credentials: "same-origin",

                    signal: controller.signal,

                    body: JSON.stringify({
                        name,
                        phone,
                        email,
                        plan: "{{ $plan->slug }}"
                    })
                });

                clearTimeout(timeout);
                if (!orderResponse.ok) {

                    const errorData = await safeJson(orderResponse);

                    if (orderResponse.status === 419) {
                        throw new Error(
                            errorData.message || "Session expired."
                        );
                    }

                    if (orderResponse.status === 422) {
                        throw new Error(
                            errorData.message || "Validation failed."
                        );
                    }

                    throw new Error(
                        errorData.message || "Unable to create payment order."
                    );
                }

                const orderData = await safeJson(orderResponse);

                if (
                    !orderData.success ||
                    !orderData.order_id ||
                    !orderData.amount ||
                    !orderData.key
                ) {
                    throw new Error(
                        orderData.message || "Invalid payment response."
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | RAZORPAY OPTIONS
                |--------------------------------------------------------------------------
                */

                const options = {

                    key: orderData.key,

                    amount: orderData.amount,

                    currency: orderData.currency || "INR",

                    order_id: orderData.order_id,

                    name: "RiskSignal",

                    description: "RiskSignal Subscription",

                    image: "/favicon.ico",

                    prefill: {
                        name,
                        email,
                        contact: phone
                    },

                    notes: {
                        platform: "RiskSignal",
                        plan: "{{ $plan->slug }}"
                    },

                    theme: {
                        color: "#eab308"
                    },

                    modal: {

                        escape: false,

                        ondismiss: function() {

                            console.warn("Checkout dismissed");

                            resetButton();
                        }
                    },

                    retry: {
                        enabled: false
                    },

                    handler: async function(paymentResponse) {

                        try {

                            /*
                            |--------------------------------------------------------------------------
                            | VERIFY PAYMENT
                            |--------------------------------------------------------------------------
                            */

                            const verifyController = new AbortController();

                            const verifyTimeout = setTimeout(() => {
                                verifyController.abort();
                            }, 15000);

                            const verifyResponse = await fetch("/payment/verify", {

                                method: "POST",

                                headers: {
                                    "Content-Type": "application/json",
                                    "Accept": "application/json",
                                    "X-CSRF-TOKEN": csrfToken
                                },

                                credentials: "same-origin",

                                signal: verifyController.signal,

                                body: JSON.stringify({
                                    razorpay_order_id: paymentResponse.razorpay_order_id,

                                    razorpay_payment_id: paymentResponse.razorpay_payment_id,

                                    razorpay_signature: paymentResponse.razorpay_signature
                                })
                            });

                            clearTimeout(verifyTimeout);

                            if (!verifyResponse.ok) {

                                if (verifyResponse.status === 419) {
                                    throw new Error(
                                        "Session expired after payment."
                                    );
                                }

                                throw new Error(
                                    "Payment verification failed."
                                );
                            }

                            const verifyData =
                                await safeJson(verifyResponse);

                            if (!verifyData.success) {
                                throw new Error(
                                    verifyData.message ||
                                    "Payment verification failed."
                                );
                            }

                            /*
                            |--------------------------------------------------------------------------
                            | SUCCESS REDIRECT
                            |--------------------------------------------------------------------------
                            */

                            window.location.href =
                                verifyData.redirect ||
                                "/dashboard";

                        } catch (error) {

                            console.error(
                                "Verification error:",
                                error
                            );

                            showError(
                                error.message ||
                                "Payment verification failed."
                            );

                            resetButton();
                        }
                    }
                };

                /*
                |--------------------------------------------------------------------------
                | OPEN CHECKOUT
                |--------------------------------------------------------------------------
                */

                const rzp = new Razorpay(options);

                /*
                |--------------------------------------------------------------------------
                | PAYMENT FAILED
                |--------------------------------------------------------------------------
                */

                rzp.on("payment.failed", async function(response) {

                    console.error("Payment failed:", response.error);

                    try {

                        await fetch("/payment/failure", {

                            method: "POST",

                            headers: {
                                "Content-Type": "application/json",

                                "X-CSRF-TOKEN": csrfToken
                            },

                            credentials: "same-origin",

                            body: JSON.stringify({
                                order_id: orderData.order_id
                            })
                        });

                    } catch (e) {

                        console.error(
                            "Failure tracking error:",
                            e
                        );
                    }

                    showError(
                        response?.error?.description ||
                        "Payment failed."
                    );

                    resetButton();
                });

                /*
                |--------------------------------------------------------------------------
                | OPEN RAZORPAY
                |--------------------------------------------------------------------------
                */

                rzp.open();

                } catch (error) {

                    console.error(
                        "Checkout error:",
                        error
                    );

                    if (error.name === "AbortError") {

                        showError(
                            "Request timed out. Please try again."
                        );

                    } else {

                        showError(
                            error.message ||
                            "Something went wrong."
                        );
                    }

                    resetButton();
                }

            });
});
</script>

@endpush

@endsection
