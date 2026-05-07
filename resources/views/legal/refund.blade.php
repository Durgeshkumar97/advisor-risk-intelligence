@extends('layouts.app')

@section('content')

<div class="legal-shell">
    <div class="legal-container">

        {{-- HEADER --}}
        <div class="legal-header">

            <h1 class="legal-title">Refund & Cancellation Policy</h1>

            <p class="legal-updated">Last updated: {{ now()->format('d M Y') }}</p>

        </div>

        {{-- CONTENT --}}
        <div class="legal-content">

            {{-- 1. NATURE OF SERVICE --}}
            <div class="legal-section">

                <h2>1. Nature of Service</h2>

                <p>
                    RiskSignal provides subscription-based digital analytics,
                    AI-assisted reporting, portfolio intelligence,
                    and financial risk monitoring services.
                </p>

                <p>
                    Due to the customized and digital nature of the service,
                    refund eligibility is limited after service activation
                    or report generation begins.
                </p>

            </div>

            {{-- 2. REFUND ELIGIBILITY --}}
            <div class="legal-section">

                <h2>2. Refund Eligibility</h2>

                <ul>
                    <li>
                        Refund requests must be submitted within
                        24 hours of purchase
                    </li>
                    <li>
                        No report generation, onboarding,
                        or dashboard access should have started
                    </li>
                    <li>
                        Duplicate or accidental transactions
                        may qualify for refunds
                    </li>
                    <li>
                        Refund approval remains subject
                        to internal verification
                    </li>
                </ul>

            </div>

            {{-- 3. NON-REFUNDABLE SITUATIONS --}}
            <div class="legal-section">

                <h2>3. Non-Refundable Situations</h2>

                <ul>
                    <li>After report generation has started</li>
                    <li>After dashboard access or onboarding completion</li>
                    <li>Partial or completed usage of subscription services</li>
                    <li>Downloaded, viewed, or delivered reports</li>
                    <li>User-side technical limitations or internet issues</li>
                    <li>Violation of platform policies or misuse</li>
                </ul>

            </div>

            {{-- 4. SUBSCRIPTION CANCELLATION --}}
            <div class="legal-section">

                <h2>4. Subscription Cancellation</h2>

                <p>
                    Users may cancel future renewals at any time
                    through account settings or by contacting support.
                </p>

                <p>
                    Cancellation prevents future billing but does not
                    retroactively refund completed billing cycles.
                </p>

            </div>

            {{-- 5. REFUND PROCESSING TIMELINE --}}
            <div class="legal-section">

                <h2>5. Refund Processing Timeline</h2>

                <p>
                    Approved refunds are generally processed back
                    to the original payment method within 5–10 business days,
                    depending on banking and payment gateway timelines.
                </p>

            </div>

            {{-- 6. PAYMENT GATEWAY NOTICE --}}
            <div class="legal-section">

                <h2>6. Payment Gateway Notice</h2>

                <p>
                    Payments are securely processed through Razorpay
                    and associated banking infrastructure.
                </p>

                <p>
                    Refund timing may vary depending on banks,
                    card issuers, UPI systems, or payment processors.
                </p>

            </div>

            {{-- 7. ABUSE PREVENTION --}}
            <div class="legal-section">

                <h2>7. Abuse Prevention</h2>

                <p>
                    RiskSignal reserves the right to deny refund requests
                    involving abuse, fraud, excessive claims,
                    suspicious activity, or policy violations.
                </p>

            </div>

            {{-- 8. REGULATORY DISCLAIMER --}}
            <div class="legal-section">

                <h2>8. Regulatory Disclaimer</h2>

                <p>
                    RiskSignal operates as a technology and analytics platform
                    and does not guarantee investment performance,
                    financial outcomes, or market returns.
                </p>

            </div>

            {{-- 9. POLICY UPDATES --}}
            <div class="legal-section">

                <h2>9. Policy Updates</h2>

                <p>
                    We may update this Refund Policy periodically
                    to reflect operational, regulatory,
                    or product-related changes.
                </p>

            </div>

            {{-- 10. CONTACT --}}
            <div class="legal-section">

                <h2>10. Contact</h2>

                <div class="legal-card">
                    <p class="legal-contact-title">RiskSignal</p>
                    <p>India</p>
                    <div class="legal-divider"></div>
                    <p>support@risksignal.in</p>
                </div>

            </div>

        </div>
    </div>
</div>

@endsection
