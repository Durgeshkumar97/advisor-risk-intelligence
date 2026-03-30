<!-- FILE: resources/views/layouts/app.blade.php -->

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>RiskLens</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body style="display:flex;flex-direction:column;min-height:100vh;">

<!-- THEME TOGGLE -->
<button class="theme-toggle" onclick="toggleTheme()" title="Toggle theme"
    style="position:fixed;bottom:20px;right:20px;z-index:999;">
    <span class="theme-icon">🌙</span>
</button>

<!-- MAIN CONTENT -->
<main style="flex:1;">
    @yield('content')
</main>

<!-- FOOTER -->
<footer style="
    padding:3rem 1rem 2rem;
    text-align:center;
    border-top:1px solid var(--paper-3);
    background:rgba(255,255,255,0.02);
">

    <div style="
        display:flex;
        justify-content:center;
        gap:28px;
        flex-wrap:wrap;
        font-size:1.05rem;
        margin-bottom:1.2rem;
    ">
        <a href="{{ route('terms') }}">Terms</a>
        <span>•</span>
        <a href="{{ route('privacy') }}">Privacy</a>
        <span>•</span>
        <a href="{{ route('refund') }}">Refund</a>
    </div>

    <div style="font-size:0.95rem;color:var(--ink-3);">
        © {{ date('Y') }} RiskLens. All rights reserved.
    </div>
</footer>

<!-- THEME SCRIPT -->
<script>
function toggleTheme() {
    const html = document.documentElement;
    const current = html.getAttribute('data-theme');
    const next = current === 'light' ? 'dark' : 'light';
    html.setAttribute('data-theme', next);
    localStorage.setItem('theme', next);
    document.querySelector('.theme-icon').textContent = next === 'dark' ? '☀️' : '🌙';
}

const saved = localStorage.getItem('theme') || 'light';
document.documentElement.setAttribute('data-theme', saved);

document.addEventListener('DOMContentLoaded', () => {
    document.querySelector('.theme-icon').textContent =
        saved === 'dark' ? '☀️' : '🌙';
});
</script>

<!-- ✅ SCROLL ANIMATION FALLBACK (CRITICAL) -->
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
