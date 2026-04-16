<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>RiskSignal</title>

    <!-- Vite -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="flex flex-col min-h-screen">

    <!-- NAVBAR -->
    <div style="position:fixed; top:0; left:0; width:100%; z-index:1000;">
        @include('navigation')
    </div>

    <!-- MAIN CONTENT -->
    <main class="flex-1"
          style="
            margin-top:72px; /* matches navbar height */
          ">
        <div style="
            max-width:1200px;
            margin:0 auto;
            width:100%;
        ">
            @yield('content')
        </div>
    </main>

    <!-- FOOTER -->
    <footer style="
        border-top:1px solid var(--paper-3);
        margin-top:3rem;
    ">
        <div style="
            max-width:1200px;
            margin:0 auto;
            padding:2.5rem 1.5rem;
            text-align:center;
        ">

            <div style="
                display:flex;
                justify-content:center;
                gap:1.2rem;
                flex-wrap:wrap;
                font-size:.875rem;
                margin-bottom:1rem;
            ">
                <a href="{{ route('terms') ?? '#' }}">Terms</a>
                <span>•</span>
                <a href="{{ route('privacy') ?? '#' }}">Privacy</a>
                <span>•</span>
                <a href="{{ route('refund') ?? '#' }}">Refund</a>
            </div>

            <div style="font-size:.85rem; color:var(--ink-3);">
                © {{ date('Y') }} RiskSignal. All rights reserved.
            </div>

        </div>
    </footer>

    <!-- THEME TOGGLE -->
    <button id="theme-toggle" aria-label="Toggle theme"
            style="
                position:fixed;
                bottom:20px;
                right:20px;
                z-index:1000;
            ">
        <span class="theme-icon"></span>
    </button>

</body>
</html>