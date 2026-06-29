<!DOCTYPE html>
<html
    lang="en"
    data-theme="dark">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <meta
        name="csrf-token"
        content="{{ csrf_token() }}">

    <title>
        @yield('title', 'RiskSignal')
    </title>

    <meta
        name="description"
        content="AI-powered portfolio risk intelligence platform for independent financial advisors.">

    {{-- FONT PRELOAD --}}
    <link
        rel="preload"
        href="/fonts/figtree/figtree-latin.woff2"
        as="font"
        type="font/woff2"
        crossorigin>
    <link
        rel="preload"
        href="/fonts/figtree/figtree-latin-ext.woff2"
        as="font"
        type="font/woff2"
        crossorigin>

    {{-- VITE --}}
    @vite([
    'resources/css/app.css',
    'resources/js/app.js'
    ])

    <style>
        /*
        |--------------------------------------------------------------------------
        | GLOBAL
        |--------------------------------------------------------------------------
        */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {

            min-height:
                100vh;

            display: flex;

            flex-direction: column;

            font-family:
                'Figtree',
                'FigtreeFallback',
                sans-serif;

            overflow-x: hidden;
        }

        a {
            text-decoration: none;
        }

        /*
        |--------------------------------------------------------------------------
        | MAIN
        |--------------------------------------------------------------------------
        */

        .main-wrapper {

            flex: 1;

            width: 100%;

            margin-top: 72px;
        }

        .content-container {

            max-width:
                1280px;

            margin: 0 auto;

            width: 100%;

            padding:
                0 1.5rem 4rem;
        }

        /*
        |--------------------------------------------------------------------------
        | ALERTS
        |--------------------------------------------------------------------------
        */

        .alert-region {
            min-height: 6.5rem;
        }

        .alert {
            padding: 1rem 1.2rem;
            border-radius: 14px;
            margin: 1.5rem 0;
            font-weight: 600;
            text-align: center;
        }

        .alert-success {

            background:
                rgba(34, 197, 94, .12);

            color:
                #86efac;

            border:
                1px solid rgba(34, 197, 94, .2);
        }

        .alert-error {

            background:
                rgba(239, 68, 68, .12);

            color:
                #fca5a5;

            border:
                1px solid rgba(239, 68, 68, .2);
        }

        /*
        |--------------------------------------------------------------------------
        | FOOTER
        |--------------------------------------------------------------------------
        */

        footer {

            border-top:
                1px solid var(--paper-3);

            margin-top:
                auto;
        }

        .footer-container {

            max-width:
                1200px;

            margin: 0 auto;

            padding:
                2.5rem 1.5rem;

            text-align: center;
        }

        .footer-links {

            display: flex;

            justify-content: center;

            gap: 1.2rem;

            flex-wrap: wrap;

            font-size: .875rem;

            margin-bottom: 1rem;
        }

        .footer-copy {

            font-size:
                .85rem;

            color:
                var(--ink-3);
        }

        /*
        |--------------------------------------------------------------------------
        | MOBILE
        |--------------------------------------------------------------------------
        */

        @media(max-width:768px) {

            .content-container {

                padding:
                    0 1rem 4rem;
            }

            .main-wrapper {

                margin-top: 68px;
            }
        }
    </style>

    @stack('styles')

</head>

<body>

    {{-- NAVBAR --}}
    <div class="navbar-wrapper">

        @include('navigation')

    </div>

    {{-- MAIN --}}
    <main class="main-wrapper">

        <div class="content-container">

            {{-- ALERT REGION: always present so alerts never shift content below --}}
            <div class="alert-region">

                @if(session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
                @elseif(session('restored_account'))
                <div class="alert alert-success">
                    {{ session('restored_account') }}
                </div>
                @elseif(session('error'))
                <div class="alert alert-error">
                    {{ session('error') }}
                </div>
                @endif

            </div>

            {{-- CONTENT --}}
            @yield('content')

        </div>

    </main>

    {{-- FOOTER --}}
    <footer>

        <div class="footer-container">

            <div class="footer-links">

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

            </div>

            <div class="footer-copy">

                © {{ date('Y') }}
                RiskSignal.
                All rights reserved.

            </div>

        </div>

    </footer>

    {{-- THEME TOGGLE --}}
    <button
        id="theme-toggle"

        aria-label="Toggle theme"

        style="
            position:fixed;
            bottom:20px;
            right:20px;
            z-index:1000;
        ">
        <span class="theme-icon"></span>
    </button>

    @stack('scripts')

</body>

</html>
