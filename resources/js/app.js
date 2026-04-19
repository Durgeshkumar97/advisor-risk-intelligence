import './bootstrap';

/* ==========================================
   APP START
========================================== */
document.addEventListener("DOMContentLoaded", () => {
    initScrollAnimations();
    initMobileMenu();
    initTheme();
    initNavbarScroll();
    initSmoothAnchorScroll();
});


/* ==========================================
   SCROLL ANIMATIONS
========================================== */
function initScrollAnimations() {

    const elements = document.querySelectorAll(".reveal");

    if (!elements.length) return;

    /* fallback */
    if (!("IntersectionObserver" in window)) {
        elements.forEach(el => el.classList.add("active"));
        return;
    }

    const observer = new IntersectionObserver((entries) => {

        entries.forEach(entry => {

            if (entry.isIntersecting) {
                entry.target.classList.add("active");
            }

        });

    }, {
        threshold: 0.12,
        rootMargin: "0px 0px -60px 0px"
    });

    elements.forEach(el => observer.observe(el));
}


/* ==========================================
   MOBILE MENU
========================================== */
function initMobileMenu() {

    const toggle = document.getElementById("menu-toggle");
    const menu = document.getElementById("mobile-menu");
    const openIcon = document.getElementById("icon-open");
    const closeIcon = document.getElementById("icon-close");
    const themeToggle = document.getElementById("theme-toggle");

    if (!toggle || !menu) return;

    toggle.addEventListener("click", () => {

        const isOpen = menu.classList.contains("open");

        if (isOpen) {
            closeMenu();
        } else {
            openMenu();
        }

    });

    /* close when link clicked */
    menu.querySelectorAll("a").forEach(link => {
        link.addEventListener("click", closeMenu);
    });

    /* close on outside click */
    document.addEventListener("click", (e) => {

        if (
            menu.classList.contains("open") &&
            !menu.contains(e.target) &&
            !toggle.contains(e.target)
        ) {
            closeMenu();
        }

    });

    function openMenu() {

        menu.classList.add("open");
        menu.classList.remove("hidden");

        document.body.classList.add("overflow-hidden");

        toggle.setAttribute("aria-expanded", "true");

        if (openIcon) openIcon.classList.add("hidden");
        if (closeIcon) closeIcon.classList.remove("hidden");

        if (themeToggle) themeToggle.style.display = "none";
    }

    function closeMenu() {

        menu.classList.remove("open");
        menu.classList.add("hidden");

        document.body.classList.remove("overflow-hidden");

        toggle.setAttribute("aria-expanded", "false");

        if (openIcon) openIcon.classList.remove("hidden");
        if (closeIcon) closeIcon.classList.add("hidden");

        if (themeToggle) themeToggle.style.display = "block";
    }
}


/* ==========================================
   THEME SYSTEM
========================================== */
function initTheme() {

    const html = document.documentElement;
    const btn = document.getElementById("theme-toggle");

    const saved = localStorage.getItem("theme") || "dark";

    applyTheme(saved);

    if (btn) {
        btn.addEventListener("click", toggleTheme);
    }

    window.toggleTheme = toggleTheme;

    function toggleTheme() {

        const current = html.getAttribute("data-theme");
        const next = current === "light" ? "dark" : "light";

        applyTheme(next);
        localStorage.setItem("theme", next);
    }

    function applyTheme(theme) {

        html.setAttribute("data-theme", theme);
        updateThemeIcon(theme);
    }
}


/* ==========================================
   THEME ICON
========================================== */
function updateThemeIcon(theme) {

    const icon = document.querySelector(".theme-icon");
    const button = document.getElementById("theme-toggle");

    const symbol = theme === "dark" ? "☀️" : "🌙";

    if (icon) {
        icon.textContent = symbol;
    } else if (button) {
        button.textContent = symbol;
    }
}


/* ==========================================
   NAVBAR SHRINK ON SCROLL
========================================== */
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

    updateNav();

    window.addEventListener("scroll", updateNav);
}


/* ==========================================
   SMOOTH ANCHOR SCROLL
========================================== */
function initSmoothAnchorScroll() {

    document.querySelectorAll('a[href^="#"]').forEach(link => {

        link.addEventListener("click", function (e) {

            const targetId = this.getAttribute("href");

            if (targetId === "#") return;

            const target = document.querySelector(targetId);

            if (!target) return;

            e.preventDefault();

            const navHeight = 70;

            const top =
                target.getBoundingClientRect().top +
                window.pageYOffset -
                navHeight;

            window.scrollTo({
                top,
                behavior: "smooth"
            });

        });

    });
}