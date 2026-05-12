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
    document.addEventListener('DOMContentLoaded', () => {

        console.info('[Checkout] Initialized');

        /*
        |--------------------------------------------------------------------------
        | ELEMENTS
        |--------------------------------------------------------------------------
        */

        const form = document.getElementById('payment-form');
        const payBtn = document.getElementById('payBtn');

        if (!form || !payBtn) {

            console.error('[Checkout] Required elements missing');

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | STATE
        |--------------------------------------------------------------------------
        */

        let isProcessing = false;

        /*
        |--------------------------------------------------------------------------
        | HELPERS
        |--------------------------------------------------------------------------
        */

        const csrfToken = document.querySelector(
            'meta[name="csrf-token"]'
        )?.getAttribute('content');

        const setButtonState = (loading = false) => {

            isProcessing = loading;

            payBtn.disabled = loading;

            payBtn.innerText = loading ?
                'Processing...' :
                'Complete Purchase';
        };

        const sanitizePhone = (value) => {
            return value.replace(/\D/g, '');
        };

        const showError = (message) => {

            console.error('[Checkout]', message);

            alert(message);
        };

        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
        */

        const validateForm = ({
            name,
            phone,
            email
        }) => {

            if (!name || !phone || !email) {
                throw new Error('Please fill all fields.');
            }

            if (name.length < 2) {
                throw new Error('Please enter valid full name.');
            }

            if (!/^[6-9]\d{9}$/.test(phone)) {
                throw new Error('Enter valid Indian mobile number.');
            }

            const emailRegex =
                /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

            if (!emailRegex.test(email)) {
                throw new Error('Enter valid email address.');
            }
        };

        /*
        |--------------------------------------------------------------------------
        | PAYMENT CLICK
        |--------------------------------------------------------------------------
        */

        payBtn.addEventListener('click', async () => {

            if (isProcessing) {
                return;
            }

            /*
            |--------------------------------------------------------------------------
            | RAZORPAY SDK CHECK
            |--------------------------------------------------------------------------
            */

            if (typeof Razorpay === 'undefined') {

                showError(
                    'Payment gateway failed to load. Refresh page.'
                );

                return;
            }

            setButtonState(true);

            try {

                /*
                |--------------------------------------------------------------------------
                | FORM DATA
                |--------------------------------------------------------------------------
                */

                const name =
                    document.getElementById('name')
                    ?.value
                    .trim();

                const email =
                    document.getElementById('email')
                    ?.value
                    .trim()
                    .toLowerCase();

                const phone =
                    sanitizePhone(
                        document.getElementById('phone')
                        ?.value
                        .trim()
                    );

                const plan = "{{ $plan->slug }}";

                validateForm({
                    name,
                    phone,
                    email
                });

                /*
                |--------------------------------------------------------------------------
                | CREATE ORDER REQUEST
                |--------------------------------------------------------------------------
                */

                const controller = new AbortController();

                const timeout = setTimeout(() => {
                    controller.abort();
                }, 15000);

                const orderResponse = await fetch(
                    "{{ url('/payment/create') }}", {
                        method: 'POST',

                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },

                        credentials: 'same-origin',

                        body: JSON.stringify({
                            name,
                            email,
                            phone,
                            plan,
                        }),

                        signal: controller.signal,
                    }
                );

                clearTimeout(timeout);

                /*
                |--------------------------------------------------------------------------
                | RESPONSE CHECK
                |--------------------------------------------------------------------------
                */

                if (!orderResponse.ok) {

                    let errorMessage =
                        'Unable to create payment order.';

                    try {

                        const errorData =
                            await orderResponse.json();

                        errorMessage =
                            errorData.message ||
                            errorMessage;

                    } catch (_) {}

                    throw new Error(errorMessage);
                }

                const orderData =
                    await orderResponse.json();

                if (
                    !orderData.success ||
                    !orderData.order_id ||
                    !orderData.amount
                ) {
                    throw new Error(
                        'Invalid payment gateway response.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | RAZORPAY INSTANCE
                |--------------------------------------------------------------------------
                */

                const razorpay = new Razorpay({

                    key: orderData.key,

                    amount: orderData.amount,

                    currency: 'INR',

                    order_id: orderData.order_id,

                    name: 'RiskSignal',

                    description: 'RiskSignal Subscription',

                    image: "{{ asset('favicon.ico') }}",

                    prefill: {
                        name,
                        email,
                        contact: phone,
                    },

                    notes: {
                        plan,
                        source: 'RiskSignal Checkout',
                    },

                    theme: {
                        color: '#0f172a',
                    },

                    modal: {

                        escape: false,

                        backdropclose: false,

                        ondismiss: function() {

                            console.warn(
                                '[Checkout] Razorpay modal dismissed'
                            );

                            setButtonState(false);
                        }
                    },

                    handler: async function(response) {

                        try {

                            /*
                            |--------------------------------------------------------------------------
                            | VERIFY PAYMENT
                            |--------------------------------------------------------------------------
                            */

                            const verifyResponse = await fetch(
                                "{{ url('/payment/verify') }}", {
                                    method: 'POST',

                                    headers: {
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': csrfToken,
                                    },

                                    credentials: 'same-origin',

                                    body: JSON.stringify({

                                        razorpay_payment_id: response.razorpay_payment_id,

                                        razorpay_order_id: response.razorpay_order_id,

                                        razorpay_signature: response.razorpay_signature,
                                    }),
                                }
                            );

                            if (!verifyResponse.ok) {

                                throw new Error(
                                    'Payment verification failed.'
                                );
                            }

                            const verifyData =
                                await verifyResponse.json();

                            if (!verifyData.success) {

                                throw new Error(
                                    verifyData.message ||
                                    'Unable to verify payment.'
                                );
                            }

                            /*
                            |--------------------------------------------------------------------------
                            | SUCCESS REDIRECT
                            |--------------------------------------------------------------------------
                            */

                            window.location.href =
                                verifyData.redirect ||
                                '/dashboard';

                        } catch (error) {

                            console.error(
                                '[Checkout Verify Error]',
                                error
                            );

                            showError(
                                'Payment verification failed. Contact support.'
                            );

                            setButtonState(false);
                        }
                    }
                });

                /*
                |--------------------------------------------------------------------------
                | PAYMENT FAILED EVENT
                |--------------------------------------------------------------------------
                */

                razorpay.on('payment.failed', function(response) {

                    console.error(
                        '[Razorpay Failure]',
                        response.error
                    );

                    showError(
                        response.error?.description ||
                        'Payment failed. Please try again.'
                    );

                    setButtonState(false);
                });

                /*
                |--------------------------------------------------------------------------
                | OPEN CHECKOUT
                |--------------------------------------------------------------------------
                */

                razorpay.open();

            } catch (error) {

                console.error(
                    '[Checkout Flow Error]',
                    error
                );

                if (error.name === 'AbortError') {

                    showError(
                        'Request timeout. Please try again.'
                    );

                } else {

                    showError(
                        error.message ||
                        'Something went wrong.'
                    );
                }

                setButtonState(false);
            }
        });
    });
</script>

@endpush

@endsection
