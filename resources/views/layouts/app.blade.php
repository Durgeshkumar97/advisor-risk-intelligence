<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portfolio Risk Intelligence</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <script>
        if (localStorage.getItem('theme') === 'dark') {
            document.documentElement.classList.add('dark-mode');
        }
    </script>

    <style>
        :root {
            --bg-main: #f8fafc;
            --bg-card: #ffffff;
            --bg-nav: rgba(255,255,255,.92);

            --text-main: #0f172a;
            --text-muted: #475569;

            --border-light: #e2e8f0;

            --shadow-soft: 0 10px 30px rgba(0,0,0,.08);
            --shadow-strong: 0 18px 40px rgba(0,0,0,.25);

            --accent: #0f172a;

            --radius-card: 22px;
            --radius-input: 14px;

            --transition-fast: .25s ease;
        }

        .dark-mode {
            --bg-main: #020617;
            --bg-card: #0f172a;
            --bg-nav: rgba(15,23,42,.92);

            --text-main: #f8fafc;
            --text-muted: #94a3b8;

            --border-light: rgba(255,255,255,.08);

            --shadow-soft: 0 10px 30px rgba(0,0,0,.28);
            --shadow-strong: 0 18px 40px rgba(0,0,0,.45);

            --accent: #f8fafc;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-main);
            color: var(--text-main);
            line-height: 1.6;
            padding-top: 90px;
            transition: background .3s ease, color .3s ease;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        .container {
            width: 90%;
            max-width: 1280px;
            margin: auto;
        }

        nav {
            position: fixed;
            top: 0;
            width: 100%;
            background: var(--bg-nav);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border-light);
            z-index: 1000;
        }

        .nav-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
        }

        nav ul {
            display: flex;
            gap: 28px;
            list-style: none;
        }

        nav ul li a {
            font-size: 14px;
            font-weight: 500;
            transition: opacity .2s ease;
        }

        nav ul li a:hover {
            opacity: 0.65;
        }

        .theme-toggle {
            padding: 10px 14px;
            border: 1px solid var(--border-light);
            border-radius: 10px;
            background: transparent;
            color: var(--text-main);
            cursor: pointer;
        }

        section {
            padding: 72px 0;
        }

        h1 {
            font-size: clamp(40px, 4.6vw, 64px);
            line-height: 1.02;
            letter-spacing: -0.8px;
            font-weight: 800;
            max-width: 720px;
        }

        h2 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 22px;
            letter-spacing: -0.4px;
        }

        h3 {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 12px;
        }

        p {
            color: var(--text-muted);
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 220px;
            padding: 16px 30px;
            border-radius: var(--radius-input);
            background: var(--accent);
            color: var(--bg-card);
            font-weight: 600;
            cursor: pointer;
            box-shadow: var(--shadow-soft);
            transition: all var(--transition-fast);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-strong);
        }

        .card {
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-card);
            box-shadow: var(--shadow-soft);
        }

        .premium-card {
            transition: transform var(--transition-fast);
        }

        .premium-card:hover {
            transform: translateY(-4px);
        }

        input,
        textarea {
            width: 100%;
            padding: 18px;
            border-radius: var(--radius-input);
            border: 1px solid var(--border-light);
            background: var(--bg-card);
            color: var(--text-main);
            font-size: 15px;
        }

        input:focus,
        textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 2px rgba(15,23,42,0.08);
        }

        button[type="submit"] {
            width: 100%;
            padding: 18px;
            border: none;
            border-radius: var(--radius-input);
            background: var(--accent);
            color: var(--bg-card);
            font-weight: 600;
            cursor: pointer;
        }

        .success-box,
        .error-box {
            max-width: 760px;
            margin: 0 auto 20px;
            padding: 16px 20px;
            border-radius: 14px;
            text-align: center;
            font-weight: 600;
        }

        .success-box {
            background: #ecfdf5;
            color: #166534;
        }

        .error-box {
            background: #fef2f2;
            color: #991b1b;
        }

        footer {
            padding: 45px 20px;
            text-align: center;
            font-size: 14px;
            color: var(--text-muted);
            border-top: 1px solid var(--border-light);
        }

        @media (max-width: 900px) {
            .nav-wrapper {
                flex-wrap: wrap;
                gap: 14px;
            }

            nav ul {
                gap: 14px;
                flex-wrap: wrap;
            }

            section {
                padding: 56px 0;
            }
        }
    </style>
</head>

<body>

@yield('content')

<script>
document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('themeBtn');

    if (!btn) return;

    function setTheme(mode) {
        if (mode === 'dark') {
            document.documentElement.classList.add('dark-mode');
            btn.textContent = '☀️';
        } else {
            document.documentElement.classList.remove('dark-mode');
            btn.textContent = '🌙';
        }
    }

    btn.addEventListener('click', function () {
        const next = document.documentElement.classList.contains('dark-mode')
            ? 'light'
            : 'dark';

        localStorage.setItem('theme', next);
        setTheme(next);
    });

    setTheme(localStorage.getItem('theme') || 'light');
});
</script>

</body>
</html>

