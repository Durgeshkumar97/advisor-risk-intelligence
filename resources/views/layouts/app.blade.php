<!DOCTYPE html>
<html lang="en" data-theme="light">

<head>

    {{-- ========================================================================= --}}
    {{-- META --}}
    {{-- ========================================================================= --}}

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <meta
        http-equiv="X-UA-Compatible"
        content="IE=edge">

    <meta
        name="csrf-token"
        content="{{ csrf_token() }}">

    <meta
        name="robots"
        content="index, follow">

    <meta
        name="referrer"
        content="strict-origin-when-cross-origin">

    {{-- ========================================================================= --}}
    {{-- SEO --}}
    {{-- ========================================================================= --}}

    <title>
        @yield('title', 'RiskSignal')
    </title>

    <meta
        name="description"
        content="RiskSignal helps advisors monitor portfolio risks and improve client trust using AI-powered risk intelligence.">

    <meta
        name="keywords"
        content="RiskSignal, portfolio risk, advisor tools, wealth management, risk intelligence, financial advisor SaaS">

    <meta
        name="author"
        content="RiskSignal">

    {{-- ========================================================================= --}}
    {{-- SOCIAL --}}
    {{-- ========================================================================= --}}

    <meta
        property="og:type"
        content="website">

    <meta
        property="og:title"
        content="@yield('title', 'RiskSignal')">

    <meta
        property="og:description"
        content="AI-powered portfolio risk intelligence for financial advisors.">

    <meta
        property="og:url"
        content="{{ url()->current() }}">

    <meta
        property="og:site_name"
        content="RiskSignal">

    {{-- ========================================================================= --}}
    {{-- PERFORMANCE / PWA --}}
    {{-- ========================================================================= --}}

    <meta
        name="theme-color"
        content="#0f172a">

    <meta
        name="color-scheme"
        content="dark light">

    {{-- ========================================================================= --}}
    {{-- FAVICON --}}
    {{-- ========================================================================= --}}

    <link
        rel="icon"
        type="image/x-icon"
        href="{{ asset('favicon.ico') }}">

    <link
        rel="shortcut icon"
        href="{{ asset('favicon.ico') }}">

    {{-- ========================================================================= --}}
    {{-- PRECONNECT --}}
    {{-- ========================================================================= --}}

    <link
        rel="preconnect"
        href="https://checkout.razorpay.com">

    <link
        rel="dns-prefetch"
        href="//checkout.razorpay.com">

    {{-- ========================================================================= --}}
    {{-- VITE --}}
    {{-- ========================================================================= --}}

    @vite([
    'resources/css/app.css',
    'resources/js/app.js'
    ])

    {{-- ========================================================================= --}}
    {{-- PAGE HEAD EXTENSIONS --}}
    {{-- ========================================================================= --}}

    @stack('head')

</head>

<body
    class="flex flex-col min-h-screen antialiased">

    {{-- ========================================================================= --}}
    {{-- NOSCRIPT --}}
    {{-- ========================================================================= --}}

    <noscript>

        <div
            style="
                background:#dc2626;
                color:white;
                text-align:center;
                padding:1rem;
                font-weight:600;
            ">

            JavaScript is required to use RiskSignal.

        </div>

    </noscript>

    {{-- ========================================================================= --}}
    {{-- NAVBAR --}}
    {{-- ========================================================================= --}}

    <header
        style="
            position: sticky;
            top: 0;
            z-index: 1000;
            width: 100%;
            background: var(--paper-1);
            border-bottom: 1px solid var(--paper-3);
        ">
        @include('navigation')
    </header>

    {{-- ========================================================================= --}}
    {{-- MAIN CONTENT --}}
    {{-- ========================================================================= --}}

    <main
        id="main-content"
        class="flex-1"
        style="
            margin-top: var(--nav-height);
            min-height: 100vh;
        ">

        @yield('content')

    </main>

    {{-- ========================================================================= --}}
    {{-- FOOTER --}}
    {{-- ========================================================================= --}}

    <footer
        role="contentinfo"

        style="
            border-top: 1px solid var(--paper-3);
            margin-top: 3rem;
            background: var(--paper-1);
        ">

        <div
            style="
                max-width: 1200px;
                margin: 0 auto;
                padding: 2.5rem 1.5rem;
                text-align: center;
            ">

            {{-- LINKS --}}

            <nav
                aria-label="Footer Navigation"

                style="
                    display: flex;
                    justify-content: center;
                    gap: 1.2rem;
                    flex-wrap: wrap;
                    font-size: .875rem;
                    margin-bottom: 1rem;
                ">

                <a href="{{ route('terms') }}">
                    Terms
                </a>

                <span>•</span>

                <a href="{{ route('privacy') }}">
                    Privacy
                </a>

                <span>•</span>

                <a href="{{ route('refund') }}">
                    Refund
                </a>

                @auth

                <span>•</span>

                <a href="{{ route('admin.dashboard') }}">
                    Admin
                </a>

                @endauth

            </nav>

            {{-- COPYRIGHT --}}

            <div
                style="
                    font-size: .85rem;
                    color: var(--ink-3);
                ">

                © {{ now()->year }} RiskSignal.
                All rights reserved.

            </div>

        </div>

    </footer>

    {{-- ========================================================================= --}}
    {{-- THEME TOGGLE --}}
    {{-- ========================================================================= --}}

    <button
        id="theme-toggle"

        type="button"

        aria-label="Toggle theme"

        style="
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        ">

        <span class="theme-icon"></span>

    </button>

    {{-- ========================================================================= --}}
    {{-- GLOBAL SCRIPTS --}}
    {{-- ========================================================================= --}}

    @stack('scripts')

</body>

</html>
