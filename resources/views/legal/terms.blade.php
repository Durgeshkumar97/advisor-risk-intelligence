@extends('layouts.app')

@section('content')

<div class="legal-shell">
    <div class="legal-container">

        {{-- HEADER --}}
        <div class="legal-header">

            <h1 class="legal-title">Terms & Conditions</h1>

            <p class="legal-updated">Last updated: {{ now()->format('d M Y') }}</p>

        </div>

        {{-- CONTENT --}}
        <div class="legal-content">

            {{-- 1. INTRODUCTION --}}
            <div class="legal-section">

                <h2>1. Introduction</h2>

                <p>
                    Welcome to RiskSignal. By accessing or using our platform,
                    dashboards, analytics systems, APIs, reports,
                    and subscription services, you agree to comply
                    with these Terms & Conditions.
                </p>

            </div>

            {{-- 2. NATURE OF SERVICE --}}
            <div class="legal-section">

                <h2>2. Nature of Service</h2>

                <p>
                    RiskSignal provides AI-assisted portfolio analytics,
                    financial risk monitoring, advisor intelligence tools,
                    and market analytics infrastructure.
                </p>

                <p>
                    All analytics, dashboards, reports, scores,
                    and market signals are intended solely
                    for informational and educational purposes.
                </p>

                <div class="legal-highlight">
                    <p style="max-width:100%; font-weight:500;">
                        RiskSignal does not provide investment advice,
                        portfolio management services, legal advice,
                        tax advice, or guarantees of financial performance.
                    </p>
                </div>

            </div>

            {{-- 3. REGULATORY DISCLAIMER --}}
            <div class="legal-section">

                <h2>3. Regulatory Disclaimer</h2>

                <p>
                    Users remain solely responsible for ensuring compliance
                    with all applicable RBI, SEBI, NSE, BSE, AMFI,
                    and other financial regulations.
                </p>

                <p>
                    RiskSignal operates strictly as a technology
                    and analytics platform.
                </p>

            </div>

            {{-- 4. USER RESPONSIBILITIES --}}
            <div class="legal-section">

                <h2>4. User Responsibilities</h2>

                <ul>
                    <li>Maintain accurate account information</li>
                    <li>Protect account credentials and access</li>
                    <li>Use the platform lawfully and ethically</li>
                    <li>Not redistribute reports without permission</li>
                    <li>Not attempt unauthorized access or abuse</li>
                    <li>Ensure advisor-client compliance obligations</li>
                </ul>

            </div>

            {{-- 5. SUBSCRIPTION & BILLING --}}
            <div class="legal-section">

                <h2>5. Subscription & Billing</h2>

                <p>
                    Certain platform features require active paid subscriptions.
                </p>

                <p>
                    Access may be suspended or restricted in cases of
                    failed payment, subscription expiry, abuse,
                    or policy violations.
                </p>

            </div>

            {{-- 6. INTELLECTUAL PROPERTY --}}
            <div class="legal-section">

                <h2>6. Intellectual Property</h2>

                <p>
                    All software, reports, analytics systems, branding,
                    algorithms, dashboards, and platform infrastructure
                    remain the intellectual property of RiskSignal.
                </p>

            </div>

            {{-- 7. NO PERFORMANCE GUARANTEE --}}
            <div class="legal-section">

                <h2>7. No Performance Guarantee</h2>

                <p>
                    Financial markets involve risk. RiskSignal does not
                    guarantee investment performance, alpha generation,
                    portfolio returns, risk reduction,
                    or future market outcomes.
                </p>

            </div>

            {{-- 8. LIMITATION OF LIABILITY --}}
            <div class="legal-section">

                <h2>8. Limitation of Liability</h2>

                <p>
                    To the maximum extent permitted by law, RiskSignal
                    shall not be liable for any direct, indirect, operational,
                    financial, reputational, trading, or consequential losses
                    arising from platform usage.
                </p>

            </div>

            {{-- 9. SERVICE AVAILABILITY --}}
            <div class="legal-section">

                <h2>9. Service Availability</h2>

                <p>
                    While we strive for high availability and reliability,
                    we do not guarantee uninterrupted access,
                    real-time processing, error-free operation,
                    or permanent uptime.
                </p>

            </div>

            {{-- 10. TERMINATION --}}
            <div class="legal-section">

                <h2>10. Termination</h2>

                <p>
                    We reserve the right to suspend or terminate access
                    for abuse, fraudulent activity, policy violations,
                    non-payment, or platform security risks.
                </p>

            </div>

            {{-- 11. GOVERNING LAW --}}
            <div class="legal-section">

                <h2>11. Governing Law</h2>

                <p>
                    These Terms shall be governed under the laws of India.
                    Any disputes shall fall under the jurisdiction
                    of competent Indian courts.
                </p>

            </div>

            {{-- 12. CONTACT --}}
            <div class="legal-section">

                <h2>12. Contact</h2>

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
