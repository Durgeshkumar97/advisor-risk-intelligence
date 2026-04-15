import './bootstrap';

// ─────────────────────────────
// APP INITIALIZATION
// ─────────────────────────────
document.addEventListener("DOMContentLoaded", () => {
    initScrollAnimations();
    initMobileMenu();
    initTheme();
    initNavbarScroll(); 
});

// ─────────────────────────────
// SCROLL ANIMATION ENGINE
// ─────────────────────────────
function initScrollAnimations() {

    const elements = document.querySelectorAll(".reveal");

    if (!elements.length) return;

    if (!("IntersectionObserver" in window)) {
        elements.forEach(el => el.classList.add("active"));
        return;
    }

    const observer = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {

            if (entry.isIntersecting) {
                entry.target.classList.add("active");
                observer.unobserve(entry.target);
            }

        });
    }, {
        threshold: 0.15,
        rootMargin: "0px 0px -50px 0px"
    });

    elements.forEach(el => observer.observe(el));
}


// ─────────────────────────────
// MOBILE MENU TOGGLE
// ─────────────────────────────
function initMobileMenu() {

    const toggle = document.getElementById("menu-toggle");
    const menu = document.getElementById("mobile-menu");
    const openIcon = document.getElementById("icon-open");
    const closeIcon = document.getElementById("icon-close");
    const themeToggle = document.getElementById("theme-toggle");

    if (!toggle || !menu) return;

    toggle.addEventListener("click", () => {

        // FIRST: toggle menu
        menu.classList.toggle("hidden");

        // handle theme toggle visibility
        if (themeToggle) {
            if (menu.classList.contains("hidden")) {
                themeToggle.style.display = "block"; // menu closed
            } else {
                themeToggle.style.display = "none";  // menu open
            }
        }

        // Toggle icons
        if (openIcon && closeIcon) {
            openIcon.classList.toggle("hidden");
            closeIcon.classList.toggle("hidden");
        }

        // Accessibility
        const expanded = toggle.getAttribute("aria-expanded") === "true";
        toggle.setAttribute("aria-expanded", !expanded);

        // Prevent background scroll
        if (menu.classList.contains("hidden")) {
            document.body.classList.remove("overflow-hidden");
        } else {
            document.body.classList.add("overflow-hidden");
        }
    });

    // CLOSE MENU ON LINK CLICK
    const links = menu.querySelectorAll("a");

    links.forEach(link => {
        link.addEventListener("click", () => {

            menu.classList.add("hidden");
            document.body.classList.remove("overflow-hidden");

            // Restore theme toggle
            if (themeToggle) {
                themeToggle.style.display = "block";
            }

            if (openIcon && closeIcon) {
                openIcon.classList.remove("hidden");
                closeIcon.classList.add("hidden");
            }

            toggle.setAttribute("aria-expanded", false);
        });
    });
}

// ─────────────────────────────
// THEME TOGGLE SYSTEM (FIXED)
// ─────────────────────────────
function initTheme() {

    const html = document.documentElement;
    const toggleBtn = document.getElementById("theme-toggle");

    // ✅ Load saved theme (default = dark recommended)
    const savedTheme = localStorage.getItem("theme") || "dark";
    html.setAttribute("data-theme", savedTheme);

    updateThemeIcon(savedTheme);

    // ✅ Button click handler (if button exists)
    if (toggleBtn) {
        toggleBtn.addEventListener("click", toggleTheme);
    }

    // ✅ Global fallback (optional use in HTML onclick)
    window.toggleTheme = toggleTheme;

    function toggleTheme() {
        const current = html.getAttribute("data-theme");
        const next = current === "light" ? "dark" : "light";

        html.setAttribute("data-theme", next);
        localStorage.setItem("theme", next);

        updateThemeIcon(next);
    }
}


// ─────────────────────────────
// UPDATE THEME ICON
// ─────────────────────────────
function updateThemeIcon(theme) {

    // Support BOTH:
    // 1. .theme-icon (inside button)
    // 2. #theme-toggle (direct button)

    const icon = document.querySelector(".theme-icon");
    const button = document.getElementById("theme-toggle");

    if (icon) {
        icon.textContent = theme === "dark" ? "☀️" : "🌙";
    } else if (button) {
        button.textContent = theme === "dark" ? "☀️" : "🌙";
    }
}

function initNavbarScroll() {

    const nav = document.querySelector("nav");

    if (!nav) return;

    function updateNav() {
        if (window.scrollY > 40) {
            nav.classList.add("nav-scrolled");
            nav.classList.remove("nav-default");
        } else {
            nav.classList.add("nav-default");
            nav.classList.remove("nav-scrolled");
        }
    }

    // initial state
    updateNav();

    window.addEventListener("scroll", updateNav);
}