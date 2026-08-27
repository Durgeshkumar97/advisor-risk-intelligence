@extends('layouts.app')

@section('content')

<div class="legal-shell">
    <div class="legal-container">

        {{-- HEADER --}}
        <div class="legal-header">

            <h1 class="legal-title">Privacy Policy</h1>

            <p class="legal-updated">Last updated: {{ now()->format('d M Y') }}</p>

        </div>

        {{-- CONTENT --}}
        <div class="legal-content">

            {{-- 1. INTRODUCTION --}}
            <div class="legal-section">

                <h2>1. Introduction</h2>

                <p>
                    RiskSignal respects your privacy and is committed
                    to protecting your personal information
                    and financial data.
                </p>

            </div>

            {{-- 2. INFORMATION WE COLLECT --}}
            <div class="legal-section">

                <h2>2. Information We Collect</h2>

                <ul>
                    <li>Name and contact information</li>
                    <li>Email address</li>
                    <li>Mobile number (optional)</li>
                    <li>Firm or business details</li>
                    <li>Portfolio and uploaded document data</li>
                    <li>Subscription and transaction metadata</li>
                    <li>Technical and usage analytics</li>
                    <li>Login history and security events</li>
                </ul>

            </div>

            {{-- 3. HOW WE USE INFORMATION --}}
            <div class="legal-section">

                <h2>3. How We Use Information</h2>

                <ul>
                    <li>Deliver portfolio intelligence reports</li>
                    <li>Provide analytics and risk monitoring</li>
                    <li>Process subscriptions and payments</li>
                    <li>Improve platform functionality and security</li>
                    <li>Communicate product updates and alerts</li>
                    <li>Detect abuse, fraud, or suspicious activity</li>
                    <li>Maintain audit and operational records</li>
                </ul>

            </div>

            {{-- 4. DATA SHARING --}}
            <div class="legal-section">

                <h2>4. Data Sharing</h2>

                <p>
                    RiskSignal does not sell personal data.
                </p>

                <p>
                    Limited information may be processed through trusted
                    third-party providers for payment processing, analytics,
                    cloud infrastructure, email delivery, storage,
                    monitoring, or operational support.
                </p>

            </div>

            {{-- 5. PAYMENT SECURITY --}}
            <div class="legal-section">

                <h2>5. Payment Security</h2>

                <p>
                    Payments are securely processed through Razorpay
                    and associated banking infrastructure.
                </p>

                <p>
                    RiskSignal does not store complete card credentials,
                    banking passwords, UPI PINs,
                    or sensitive payment secrets.
                </p>

            </div>

            {{-- 6. SECURITY MEASURES --}}
            <div class="legal-section">

                <h2>6. Security Measures</h2>

                <p>
                    We implement reasonable technical, operational,
                    and organizational safeguards to protect user information
                    from unauthorized access, misuse, disclosure,
                    alteration, or destruction.
                </p>

            </div>

            {{-- 7. DATA RETENTION --}}
            <div class="legal-section">

                <h2>7. Data Retention</h2>

                <p>
                    Personal data associated with your account is retained while
                    your account remains active. Upon account deletion, your data
                    is permanently purged within 30 days.
                </p>

                <p>
                    Portfolio and client data uploaded by you is retained for the
                    duration of your active subscription and deleted within 30
                    days of account closure.
                </p>

                <p>
                    Beyond these periods, data is retained only where required
                    for legal, tax, fraud prevention, or regulatory compliance
                    purposes.
                </p>

            </div>

            {{-- 8. COOKIES & ANALYTICS --}}
            <div class="legal-section">

                <h2>8. Cookies & Analytics</h2>

                <p>
                    RiskSignal may use cookies, logs, analytics,
                    and session technologies to improve platform performance,
                    reliability, and user experience.
                </p>

            </div>

            {{-- 9. USER RIGHTS --}}
            <div class="legal-section">

                <h2>9. User Rights</h2>

                <p>
                    Users may request correction, access,
                    or deletion of personal information,
                    subject to operational and legal obligations.
                </p>

                <p>
                    To exercise these rights, email
                    <a href="mailto:support@risksignal.in">support@risksignal.in</a>.
                    We will respond within 30 days. You may also delete your
                    account at any time from your profile settings, which starts
                    the 30-day purge described in Section 7.
                </p>

            </div>

            {{-- 10. REGULATORY NOTICE --}}
            <div class="legal-section">

                <h2>10. Regulatory Notice</h2>

                <p>
                    RiskSignal operates as a technology and analytics platform.
                    Users remain responsible for compliance with applicable
                    RBI, SEBI, NSE, BSE, AMFI,
                    and other regulatory requirements.
                </p>

            </div>

            {{-- 11. POLICY UPDATES --}}
            <div class="legal-section">

                <h2>11. Policy Updates</h2>

                <p>
                    We may update this Privacy Policy periodically.
                    Continued usage of the platform constitutes acceptance
                    of revised policies.
                </p>

            </div>

            {{-- 12. CONTACT --}}
            <div class="legal-section">

                <h2>12. Contact &amp; Grievance Officer</h2>

                <p>
                    In accordance with the Digital Personal Data Protection Act,
                    2023, the following officer may be contacted regarding any
                    question or grievance about the processing of your personal
                    data.
                </p>

                <div class="legal-card">
                    <p class="legal-contact-title">RiskSignal</p>
                    <p><strong>Grievance Officer:</strong> Durgesh Kumar</p>
                    {{-- TODO: Replace with actual postal address --}}
                    <p>[ADD YOUR POSTAL ADDRESS]</p>
                    <p>India</p>
                    <div class="legal-divider"></div>
                    <p><a href="mailto:support@risksignal.in">support@risksignal.in</a></p>
                    <div class="legal-divider"></div>
                    <p>
                        Response window: acknowledgement within 48 hours,
                        resolution within 30 days.
                    </p>
                </div>

            </div>

        </div>
    </div>
</div>

@endsection
