<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>RiskSignal</title>

    <!-- Vite (auto handles local + production) -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

</head>

<body class="flex flex-col min-h-screen">

    <!-- NAVBAR -->
    @include('navigation')

    <!-- MAIN CONTENT -->
    <main class="flex-1">
        @yield('content')
    </main>

    <!-- FOOTER -->
    <footer class="border-t" style="border-color: var(--paper-3);">
        <div class="container text-center py-10">

            <div class="flex flex-wrap justify-center gap-6 text-sm mb-4">
                <a href="{{ route('terms') ?? '#' }}">Terms</a>
                <span>•</span>
                <a href="{{ route('privacy') ?? '#' }}">Privacy</a>
                <span>•</span>
                <a href="{{ route('refund') ?? '#' }}">Refund</a>
            </div>

            <div class="text-sm" style="color: var(--ink-3);">
                © {{ date('Y') }} RiskSignal. All rights reserved.
            </div>

        </div>
    </footer>

</body>
</html>