<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>RiskSignal</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="flex flex-col min-h-screen">

    <!-- ✅ NAVBAR (REQUIRED) -->
    @include('navigation')

    <!-- MAIN CONTENT -->
    <main class="flex-1">
        @yield('content')
    </main>

    <!-- FOOTER -->
    <footer class="border-t" style="border-color: var(--paper-3);">

        <div class="container text-center py-10">

            <!-- LINKS -->
            <div class="flex flex-wrap justify-center gap-6 text-sm mb-4">
                <a href="{{ route('terms') }}">Terms</a>
                <span>•</span>
                <a href="{{ route('privacy') }}">Privacy</a>
                <span>•</span>
                <a href="{{ route('refund') }}">Refund</a>
            </div>

            <!-- COPYRIGHT -->
            <div class="text-sm" style="color: var(--ink-3);">
                © {{ date('Y') }} RiskSignal. All rights reserved.
            </div>

        </div>
    </footer>

    <!-- =========================
         THEME SCRIPT (CLEAN FIX)
    ========================= -->
    <script>
        function toggleTheme() {
            const html = document.documentElement;
            const current = html.getAttribute('data-theme');
            const next = current === 'light' ? 'dark' : 'light';

            html.setAttribute('data-theme', next);
            localStorage.setItem('theme', next);

            const icon = document.querySelector('.theme-icon');
            if (icon) {
                icon.textContent = next === 'dark' ? '☀️' : '🌙';
            }
        }

        // Load saved theme
        const saved = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', saved);

        document.addEventListener('DOMContentLoaded', () => {
            const icon = document.querySelector('.theme-icon');
            if (icon) {
                icon.textContent = saved === 'dark' ? '☀️' : '🌙';
            }
        });
    </script>

    <!-- =========================
         SCROLL ANIMATION
    ========================= -->
    <script>
        document.addEventListener("DOMContentLoaded", () => {

            const elements = document.querySelectorAll(".reveal");

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add("active");
                    }
                });
            }, {
                threshold: 0.15
            });

            elements.forEach(el => observer.observe(el));

        });
    </script>

</body>
</html>
