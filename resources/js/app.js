import './bootstrap';

// ─────────────────────────────
// APP INITIALIZATION
// ─────────────────────────────
document.addEventListener("DOMContentLoaded", () => {

    initScrollAnimations();
    initMobileMenu();
    initTheme();

});


// ─────────────────────────────
// SCROLL ANIMATION ENGINE
// ─────────────────────────────
function initScrollAnimations() {

    const elements = document.querySelectorAll(".reveal");

    if (!elements.length) return;

    // Fallback for older browsers
    if (!("IntersectionObserver" in window)) {
        elements.forEach(el => el.classList.add("active"));
        return;
    }

    const observer = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {

            if (entry.isIntersecting) {
                entry.target.classList.add("active");

                // Stop observing after reveal (performance)
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

    if (!toggle || !menu) return;

    toggle.addEventListener("click", () => {

        menu.classList.toggle("hidden");

        // Toggle icons
        if (openIcon && closeIcon) {
            openIcon.classList.toggle("hidden");
            closeIcon.classList.toggle("hidden");
        }

        // Accessibility
        const expanded = toggle.getAttribute("aria-expanded") === "true";
        toggle.setAttribute("aria-expanded", !expanded);

        // Prevent background scroll
        document.body.classList.toggle("overflow-hidden");
    });

    // Close menu when clicking a link
    const links = menu.querySelectorAll("a");
    links.forEach(link => {
        link.addEventListener("click", () => {
            menu.classList.add("hidden");
            document.body.classList.remove("overflow-hidden");

            if (openIcon && closeIcon) {
                openIcon.classList.remove("hidden");
                closeIcon.classList.add("hidden");
            }

            toggle.setAttribute("aria-expanded", false);
        });
    });
}


// ─────────────────────────────
// THEME TOGGLE SYSTEM
// ─────────────────────────────
function initTheme() {

    const html = document.documentElement;

    // Load saved theme early
    const savedTheme = localStorage.getItem("theme") || "light";
    html.setAttribute("data-theme", savedTheme);

    updateThemeIcon(savedTheme);

    // Expose toggle globally (for button onclick)
    window.toggleTheme = function () {
        const current = html.getAttribute("data-theme");
        const next = current === "light" ? "dark" : "light";

        html.setAttribute("data-theme", next);
        localStorage.setItem("theme", next);

        updateThemeIcon(next);
    };
}


// Update icon (🌙 / ☀️)
function updateThemeIcon(theme) {
    const icon = document.querySelector(".theme-icon");

    if (!icon) return;

    icon.textContent = theme === "dark" ? "☀️" : "🌙";
}