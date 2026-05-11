import "./bootstrap";

/* =========================================================
   RISKSIGNAL APP CORE
   Production Grade Frontend System
========================================================= */

document.addEventListener("DOMContentLoaded", () => {
    RiskSignal.init();
});

const RiskSignal = {

    /* =====================================================
       INITIALIZE
    ===================================================== */

    init() {
        this.cache();
        this.theme();
        this.legalPageFix();
        this.mobileMenu();
        this.revealAnimations();
        this.navbarScroll();
        this.smoothAnchors();
        this.activeNavLinks();
        this.resizeFix();
    },

    /* =====================================================
       CACHE DOM
    ===================================================== */

    cache() {

        this.html = document.documentElement;
        this.body = document.body;

        this.nav = document.querySelector("nav");

        this.menu = document.getElementById("mobile-menu");
        this.toggle = document.getElementById("menu-toggle");

        this.themeBtn = document.getElementById("theme-toggle");

        this.openIcon = document.getElementById("icon-open");
        this.closeIcon = document.getElementById("icon-close");

        this.reveals = [
            ...document.querySelectorAll(".reveal")
        ].filter((el) => !el.closest(".legal-shell"));

        this.anchorLinks = [
            ...document.querySelectorAll('a[href*="#"]')
        ];

        this.sections = [
            ...document.querySelectorAll("section[id]")
        ];
    },

    /* =====================================================
       LEGAL PAGE FIX
    ===================================================== */

    legalPageFix() {

        const shell = document.querySelector(".legal-shell");

        if (!shell) return;

        const all = [shell, ...shell.querySelectorAll("*")];

        all.forEach((el) => {

            el.style.setProperty("opacity", "1", "important");
            el.style.setProperty("transform", "none", "important");
            el.style.setProperty("visibility", "visible", "important");
            el.style.setProperty("animation", "none", "important");
            el.style.setProperty("transition", "none", "important");

            el.classList.add("active");

        });
    },

    /* =====================================================
       THEME SYSTEM
    ===================================================== */

    theme() {

        const saved =
            localStorage.getItem("theme") ||
            (
                window.matchMedia("(prefers-color-scheme: dark)").matches
                    ? "dark"
                    : "light"
            );

        this.applyTheme(saved);

        if (!this.themeBtn) return;

        this.themeBtn.addEventListener("click", () => {

            const current = this.html.getAttribute("data-theme");

            const next =
                current === "dark"
                    ? "light"
                    : "dark";

            this.applyTheme(next);

            localStorage.setItem("theme", next);

        });
    },

    applyTheme(theme) {

        this.html.setAttribute("data-theme", theme);

        if (!this.themeBtn) return;

        const icon = this.themeBtn.querySelector(".theme-icon");

        const symbol =
            theme === "dark"
                ? "☀️"
                : "🌙";

        if (icon) {
            icon.textContent = symbol;
        } else {
            this.themeBtn.textContent = symbol;
        }
    },

/* =====================================================
   MOBILE MENU
===================================================== */

    mobileMenu() {

        if (!this.toggle || !this.menu) return;

        this.toggle.addEventListener("click", (e) => {

            e.stopPropagation();

            this.menu.classList.contains("open")
                ? this.closeMenu()
                : this.openMenu();

        });

        this.menu.querySelectorAll("a").forEach((link) => {

            link.addEventListener("click", () => {
                this.closeMenu();
            });

        });

        document.addEventListener("click", (e) => {

            if (
                this.menu.classList.contains("open") &&
                !this.menu.contains(e.target) &&
                !this.toggle.contains(e.target)
            ) {
                this.closeMenu();
            }

        });

        window.addEventListener("keydown", (e) => {

            if (e.key === "Escape") {
                this.closeMenu();
            }

        });
    },

    openMenu() {

        this.menu.classList.add("open");

        this.body.classList.add("overflow-hidden");

        this.toggle.setAttribute(
            "aria-expanded",
            "true"
        );

        this.openIcon?.classList.add("hidden");

        this.closeIcon?.classList.remove("hidden");

        if (this.themeBtn) {
            this.themeBtn.style.opacity = "0";
        }
    },

    closeMenu() {

        this.menu.classList.remove("open");

        this.body.classList.remove("overflow-hidden");

        this.toggle.setAttribute(
            "aria-expanded",
            "false"
        );

        this.openIcon?.classList.remove("hidden");

        this.closeIcon?.classList.add("hidden");

        if (this.themeBtn) {
            this.themeBtn.style.opacity = "1";
        }
    },

    /* =====================================================
       REVEAL ANIMATIONS
    ===================================================== */

    revealAnimations() {

        if (!this.reveals.length) return;

        if (!("IntersectionObserver" in window)) {

            this.reveals.forEach((el) => {
                el.classList.add("active");
            });

            return;
        }

        const observer = new IntersectionObserver(

            (entries) => {

                entries.forEach((entry) => {

                    if (!entry.isIntersecting) return;

                    requestAnimationFrame(() => {
                        entry.target.classList.add("active");
                    });

                    observer.unobserve(entry.target);

                });

            },

            {
                threshold: 0.12,
                rootMargin: "0px 0px -40px 0px",
            }

        );

        this.reveals.forEach((el) => {
            observer.observe(el);
        });
    },

    /* =====================================================
       NAVBAR SCROLL
    ===================================================== */

    navbarScroll() {

        if (!this.nav) return;

        let ticking = false;

        const update = () => {

            const scrolled = window.scrollY > 30;

            this.nav.classList.toggle("nav-scrolled", scrolled);

            this.nav.classList.toggle("nav-default", !scrolled);

            ticking = false;
        };

        const onScroll = () => {

            if (!ticking) {

                requestAnimationFrame(update);

                ticking = true;
            }
        };

        update();

        window.addEventListener(
            "scroll",
            onScroll,
            { passive: true }
        );
    },

    /* =====================================================
       SMOOTH SCROLL
    ===================================================== */

    smoothAnchors() {

        if (!this.anchorLinks.length) return;

        this.anchorLinks.forEach((link) => {

            link.addEventListener("click", (e) => {

                const href = link.getAttribute("href");

                if (!href || !href.includes("#")) return;

                const hash = href.substring(href.indexOf("#"));

                const target = document.querySelector(hash);

                if (!target) return;

                e.preventDefault();

                const navHeight =
                    this.nav?.offsetHeight || 70;

                const top =
                    target.getBoundingClientRect().top +
                    window.scrollY -
                    navHeight;

                window.scrollTo({
                    top,
                    behavior: "smooth",
                });

                this.closeMenu();

            });

        });
    },

    /* =====================================================
       ACTIVE NAV LINKS
    ===================================================== */

    activeNavLinks() {

        if (!this.sections.length) return;

        const links = [
            ...document.querySelectorAll(".nav-link")
        ];

        const observer = new IntersectionObserver(

            (entries) => {

                entries.forEach((entry) => {

                    if (!entry.isIntersecting) return;

                    const id = entry.target.id;

                    links.forEach((link) => {

                        const href =
                            link.getAttribute("href");

                        const isActive =
                            href?.includes(`#${id}`);

                        link.classList.toggle(
                            "active",
                            isActive
                        );

                    });

                });

            },

            {
                threshold: 0.45,
            }

        );

        this.sections.forEach((section) => {
            observer.observe(section);
        });
    },

    /* =====================================================
       RESIZE FIX
    ===================================================== */

    resizeFix() {

        window.addEventListener(

            "resize",

            this.debounce(() => {

                if (window.innerWidth >= 768) {
                    this.closeMenu();
                }

            }, 180)

        );
    },

    /* =====================================================
       HELPERS
    ===================================================== */

    debounce(fn, wait = 200) {

        let timer;

        return (...args) => {

            clearTimeout(timer);

            timer = setTimeout(() => {
                fn(...args);
            }, wait);

        };
    },
};
