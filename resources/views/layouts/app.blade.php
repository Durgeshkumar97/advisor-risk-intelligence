<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>RiskLens</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>

<button class="theme-toggle" onclick="toggleTheme()" title="Toggle theme">
    <span class="theme-icon">🌙</span>
</button>

@yield('content')

<script>
    function toggleTheme() {
        const html = document.documentElement;
        const current = html.getAttribute('data-theme');
        const next = current === 'light' ? 'dark' : 'light';
        html.setAttribute('data-theme', next);
        localStorage.setItem('theme', next);
        document.querySelector('.theme-icon').textContent = next === 'dark' ? '☀️' : '🌙';
    }

    // Remember preference
    const saved = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', saved);
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelector('.theme-icon').textContent = saved === 'dark' ? '☀️' : '🌙';
    });
</script>

</body>
</html>