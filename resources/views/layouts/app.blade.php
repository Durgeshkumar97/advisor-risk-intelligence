<!DOCTYPE html>
<html>
<head>

<title>Portfolio Risk Intelligence</title>

<meta name="viewport" content="width=device-width, initial-scale=1.0">
 
<script>
if (localStorage.getItem("theme") === "dark") {
    document.documentElement.classList.add("dark-mode");
}
</script>

<style>

/* =========================
   THEME VARIABLES
========================= */

:root {
    --bg-main: #ffffff;
    --bg-hero: #ffffff;
    --text-main: #000000;
    --text-muted: #334155;

    --nav-bg: #ffffff;
    --footer-bg: #ffffff;

    --card-bg: #ffffff;
    --border-light: #e5e7eb;
}

.dark-mode {
    --bg-main: #0e0e0e;
    --bg-hero: #151515;
    --text-main: #f5f5f5;
    --text-muted: #bbbbbb;

    --nav-bg: #1a1a1a;
    --footer-bg: #1a1a1a;

    --card-bg: #1f1f1f;
    --border-light: rgba(255,255,255,0.12);
}

/* =========================
   GLOBAL
========================= */

* {
    box-sizing: border-box;
}

html,
body {
    background: var(--bg-main);
}

body {
    margin: 0;
    padding-top: 80px;
    font-family: Arial, sans-serif;
    color: var(--text-main);
    transition: background 0.3s ease, color 0.3s ease;
}

/* =========================
   NAVBAR
========================= */

nav {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    background: var(--nav-bg);
    padding: 18px 40px;
    border-bottom: 1px solid var(--border-light);
    z-index: 1000;
}

.nav-wrapper {
    max-width: 1200px;
    margin: auto;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

nav a {
    color: var(--text-main);
    margin-right: 25px;
    text-decoration: none;
    font-weight: bold;
    font-size: 14px;
}

.theme-toggle {
    cursor: pointer;
    padding: 8px 14px;
    border: 1px solid var(--border-light);
    border-radius: 6px;
    font-size: 13px;
    background: transparent;
    color: var(--text-main);
}

/* =========================
   CONTAINER
========================= */

.container {
    max-width: 1400px;
    margin: auto;
    padding: 0 24px;
}

/* =========================
   HERO SECTION
========================= */

.hero {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 80px;
    padding: 100px 40px;
    min-height: 85vh;
    max-width: 1400px;
    margin: auto;
    background: var(--bg-hero);
}

.hero-left {
    flex: 1;
    max-width: 620px;
}

.hero-left h1 {
    font-size: clamp(54px, 5vw, 72px);
    line-height: 1.05;
    margin-bottom: 24px;
}

.hero-left p {
    font-size: 20px;
    line-height: 1.7;
    color: var(--text-muted);
    max-width: 540px;
}

/* =========================
   BUTTON SYSTEM
========================= */

.hero-buttons {
    margin-top: 28px;
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
}

.btn-primary,
.btn-secondary {
    padding: 14px 24px;
    min-width: 220px;
    min-height: 54px;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    border-radius: 8px;
    text-decoration: none;
    font-weight: bold;
    font-size: 16px;
    text-align: center;

    white-space: nowrap;
}

.btn-primary {
    background: #28ae2f;
    color: #ffffff;
    border: none;
}

.btn-secondary {
    border: 1.5px solid var(--border-light);
    color: var(--text-main);
    background: transparent;
}

/* =========================
   TOGGLE BUTTONS
========================= */

.toggle-btn {
    padding: 10px 20px;
    border: 1px solid var(--border-light);
    border-radius: 6px;
    text-decoration: none;
    font-weight: bold;
    color: var(--text-main);
    background: transparent;
    margin: 0 10px;
    transition: all 0.2s ease;
}

.toggle-btn:hover {
    background: var(--border-light);
}

.toggle-btn.active {
    background: var(--text-main);
    color: var(--bg-main);
}

/* =========================
   DASHBOARD IMAGE
========================= */

.hero-right {
    flex: 1;
    display: flex;
    justify-content: center;
}

.dashboard-wrapper {
    width: 100%;
    max-width: 650px;
}

.dashboard-wrapper img {
    width: 100%;
    display: block;
    border-radius: 14px;
}

body:not(.dark-mode) .dashboard-wrapper img {
    box-shadow: 0 10px 25px rgba(0,0,0,0.06);
}

.dark-mode .dashboard-wrapper img {
    box-shadow:
        0 20px 40px rgba(0,0,0,0.25),
        0 5px 15px rgba(0,0,0,0.15);
}

/* =========================
   FOOTER
========================= */

footer {
    padding: 40px 20px;
}

.footer-box {
    background: var(--footer-bg);
    max-width: 900px;
    margin: auto;
    padding: 24px;
    border-radius: 10px;
    text-align: center;
    border: 1px solid var(--border-light);
}

.footer-links {
    display: flex;
    justify-content: center;
    gap: 28px;
    flex-wrap: wrap;
}

.footer-links a {
    color: var(--text-main);
    text-decoration: none;
    font-size: 13px;
    opacity: 0.75;
}

.footer-links a:hover {
    opacity: 1;
}

/* =========================
   MOBILE
========================= */

@media (max-width: 1024px) {

    .hero {
        flex-direction: column;
        text-align: center;
        padding: 60px 24px;
        gap: 40px;
    }

    .hero-left {
        max-width: 100%;
    }

    .hero-left p {
        margin: auto;
    }

    .hero-buttons {
        flex-direction: column;
        align-items: stretch;
    }

    .btn-primary,
    .btn-secondary {
        width: 100%;
        min-width: auto;
    }

    .hero-right {
        width: 100%;
    }

    .dashboard-wrapper {
        max-width: 100%;
    }
}

</style>
</head>

<body>

<nav>
    <div class="nav-wrapper">
        <div>
            <a href="/">Home</a>
            <a href="/service">Service</a>
            <a href="/pricing">Pricing</a>
            <a href="/how-it-works">How It Works</a>
            <a href="/contact">Contact</a>
        </div>

        <button id="themeBtn" class="theme-toggle">
            🌙 Night Mode
        </button>
    </div>
</nav>

<div class="container">
    @yield('content')
</div>

<footer>
    <div class="footer-box">
        <div style="font-size:14px;margin-bottom:14px;">
            Risk Intelligence — Not Investment Advisory
        </div>

        <div class="footer-links">
            <a href="https://www.sebi.gov.in" target="_blank">SEBI Official Website</a>
            <a href="https://investor.sebi.gov.in" target="_blank">SEBI Investor Education</a>
            <a href="https://www.nseindia.com/invest/education" target="_blank">NSE Risk Education</a>
        </div>
    </div>
</footer>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const btn = document.getElementById("themeBtn");

    function setTheme(mode) {
        if (mode === "dark") {
            document.documentElement.classList.add("dark-mode");
            btn.textContent = "☀️ Day Mode";
        } else {
            document.documentElement.classList.remove("dark-mode");
            btn.textContent = "🌙 Night Mode";
        }
    }

    btn.addEventListener("click", function () {
        const isDark = document.documentElement.classList.contains("dark-mode");
        const newMode = isDark ? "light" : "dark";
        localStorage.setItem("theme", newMode);
        setTheme(newMode);
    });

    const savedTheme = localStorage.getItem("theme") || "light";
    setTheme(savedTheme);

});
</script>

</body>
</html>

