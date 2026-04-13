<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">

    <!-- ✅ VIEWPORT FIX (prevents zoom bugs on mobile) -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- CSRF -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>RiskSignal</title>

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

            <!-- LINKS -->
            <div class="flex flex-wrap justify-center gap-6 text-sm mb-4">
                <a href="{{ route('terms') ?? '#' }}">Terms</a>
                <span>•</span>
                <a href="{{ route('privacy') ?? '#' }}">Privacy</a>
                <span>•</span>
                <a href="{{ route('refund') ?? '#' }}">Refund</a>
            </div>

            <!-- COPYRIGHT -->
            <div class="text-sm" style="color: var(--ink-3);">
                © {{ date('Y') }} RiskSignal. All rights reserved.
            </div>

        </div>
    </footer>

    <!-- =========================
         THEME SCRIPT (HARDENED)
    ========================= -->
    <script>
        (function () {
            const html = document.documentElement;

            // Load theme early (prevents flash)
            const saved = localStorage.getItem('theme') || 'light';
            html.setAttribute('data-theme', saved);

            window.toggleTheme = function () {
                const current = html.getAttribute('data-theme');
                const next = current === 'light' ? 'dark' : 'light';

                html.setAttribute('data-theme', next);
                localStorage.setItem('theme', next);

                updateIcon(next);
            };

            function updateIcon(theme) {
                const icon = document.querySelector('.theme-icon');
                if (icon) {
                    icon.textContent = theme === 'dark' ? '☀️' : '🌙';
                }
            }

            document.addEventListener('DOMContentLoaded', () => {
                updateIcon(saved);
            });
        })();
    </script>

    <!-- =========================
         SCROLL ANIMATION (OPTIMIZED)
    ========================= -->
    <script>
        document.addEventListener("DOMContentLoaded", () => {

            const elements = document.querySelectorAll(".reveal");

            if (!("IntersectionObserver" in window)) {
                // fallback for old browsers
                elements.forEach(el => el.classList.add("active"));
                return;
            }

            const observer = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add("active");

                        // stop observing once revealed (performance win)
                        observer.unobserve(entry.target);
                    }
                });
            }, {
                threshold: 0.15,
                rootMargin: "0px 0px -50px 0px"
            });

            elements.forEach(el => observer.observe(el));

        });
    </script>

</body>
</html>