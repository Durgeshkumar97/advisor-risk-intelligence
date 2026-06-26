import "./bootstrap";
import Alpine from "alpinejs";
window.Alpine = Alpine;
Alpine.start();

/* =========================================================
   RISKSIGNAL FRONTEND CORE
   Production-Ready Application Layer
========================================================= */

class RiskSignalApp {

    constructor() {

        this.state = {
            theme: "light",
            menuOpen: false,
        };

        this.elements = {};

        this.observers = [];

        this.boundHandlers = new Map();
    }

    /* =====================================================
       INITIALIZE
    ===================================================== */

    init() {

        this.cacheDom();

        this.initializeTheme();

        this.initializeLegalPage();

        this.initializeMobileMenu();

        this.initializeRevealAnimations();

        this.initializeNavbar();

        this.initializeSmoothScroll();

        this.initializeActiveSections();

        this.initializeResizeHandler();

        this.initializeReducedMotionSupport();

        this.markApplicationReady();
    }

    /* =====================================================
       CACHE DOM
    ===================================================== */

    cacheDom() {

        this.elements = {

            html:
                document.documentElement,

            body:
                document.body,

            nav:
                document.querySelector("nav"),

            mobileMenu:
                document.getElementById("mobile-menu"),

            menuToggle:
                document.getElementById("menu-toggle"),

            themeToggle:
                document.getElementById("theme-toggle"),

            openIcon:
                document.getElementById("icon-open"),

            closeIcon:
                document.getElementById("icon-close"),

            revealElements:
                [
                    ...document.querySelectorAll(".reveal")
                ].filter(
                    (el) => !el.closest(".legal-shell")
                ),

            anchorLinks:
                [
                    ...document.querySelectorAll('a[href*="#"]')
                ],

            sections:
                [
                    ...document.querySelectorAll("section[id]")
                ],

            navLinks:
                [
                    ...document.querySelectorAll(".nav-link")
                ],
        };
    }

    /* =====================================================
       THEME SYSTEM
    ===================================================== */

    initializeTheme() {

        /*
         | Default is always "dark" — RiskSignal's brand aesthetic.
         | If the user has explicitly toggled the theme before, their
         | localStorage preference wins. OS preference is intentionally
         | ignored so first-time visitors always see the dark/colorful version.
         */
        const saved = localStorage.getItem("theme") || "dark";

        const theme = saved;

        this.applyTheme(theme);

        if (!this.elements.themeToggle) {
            return;
        }

        const handler = () => {

            const current =
                this.elements.html.getAttribute(
                    "data-theme"
                );

            const next =
                current === "dark"
                    ? "light"
                    : "dark";

            this.applyTheme(next);

            localStorage.setItem(
                "theme",
                next
            );
        };

        this.elements.themeToggle.addEventListener(
            "click",
            handler
        );

        this.boundHandlers.set(
            "themeToggle",
            handler
        );
    }

    applyTheme(theme) {

        this.state.theme = theme;

        this.elements.html.setAttribute(
            "data-theme",
            theme
        );

        if (!this.elements.themeToggle) {
            return;
        }

        const icon =
            this.elements.themeToggle.querySelector(
                ".theme-icon"
            );

        const symbol =
            theme === "dark"
                ? "☀️"
                : "🌙";

        if (icon) {

            icon.textContent = symbol;

        } else {

            this.elements.themeToggle.textContent =
                symbol;
        }
    }

    /* =====================================================
       LEGAL PAGE FIX
    ===================================================== */

    initializeLegalPage() {

        const shell =
            document.querySelector(".legal-shell");

        if (!shell) {
            return;
        }

        const nodes = [
            shell,
            ...shell.querySelectorAll("*"),
        ];

        nodes.forEach((element) => {

            element.style.setProperty(
                "opacity",
                "1",
                "important"
            );

            element.style.setProperty(
                "transform",
                "none",
                "important"
            );

            element.style.setProperty(
                "visibility",
                "visible",
                "important"
            );

            element.style.setProperty(
                "animation",
                "none",
                "important"
            );

            element.style.setProperty(
                "transition",
                "none",
                "important"
            );

            element.classList.add("active");
        });
    }

    /* =====================================================
       MOBILE MENU
    ===================================================== */

    initializeMobileMenu() {

        const {
            menuToggle,
            mobileMenu,
        } = this.elements;

        if (!menuToggle || !mobileMenu) {
            return;
        }

        const toggleHandler = (event) => {

            event.stopPropagation();

            this.state.menuOpen
                ? this.closeMenu()
                : this.openMenu();
        };

        menuToggle.addEventListener(
            "click",
            toggleHandler
        );

        this.boundHandlers.set(
            "menuToggle",
            toggleHandler
        );

        mobileMenu
            .querySelectorAll("a")
            .forEach((link) => {

                link.addEventListener(
                    "click",
                    () => this.closeMenu()
                );
            });

        document.addEventListener(
            "click",
            (event) => {

                if (
                    !this.state.menuOpen
                ) {
                    return;
                }

                if (
                    mobileMenu.contains(event.target)
                ) {
                    return;
                }

                if (
                    menuToggle.contains(event.target)
                ) {
                    return;
                }

                this.closeMenu();
            }
        );

        window.addEventListener(
            "keydown",
            (event) => {

                if (event.key === "Escape") {
                    this.closeMenu();
                }
            }
        );
    }

    openMenu() {

        const {
            mobileMenu,
            body,
            menuToggle,
            openIcon,
            closeIcon,
            themeToggle,
        } = this.elements;

        this.state.menuOpen = true;

        mobileMenu?.classList.add("open");

        body?.classList.add("overflow-hidden");

        menuToggle?.setAttribute(
            "aria-expanded",
            "true"
        );

        openIcon?.classList.add("hidden");

        closeIcon?.classList.remove("hidden");

        if (themeToggle) {
            themeToggle.style.opacity = "0";
        }
    }

    closeMenu() {

        const {
            mobileMenu,
            body,
            menuToggle,
            openIcon,
            closeIcon,
            themeToggle,
        } = this.elements;

        this.state.menuOpen = false;

        mobileMenu?.classList.remove("open");

        body?.classList.remove("overflow-hidden");

        menuToggle?.setAttribute(
            "aria-expanded",
            "false"
        );

        openIcon?.classList.remove("hidden");

        closeIcon?.classList.add("hidden");

        if (themeToggle) {
            themeToggle.style.opacity = "1";
        }
    }

    /* =====================================================
       REVEAL ANIMATIONS
    ===================================================== */

    initializeRevealAnimations() {

        const elements =
            this.elements.revealElements;

        if (!elements.length) {
            return;
        }

        if (
            !("IntersectionObserver" in window)
        ) {

            elements.forEach((element) => {
                element.classList.add("active");
            });

            return;
        }

        const observer =
            new IntersectionObserver(

                (entries) => {

                    entries.forEach((entry) => {

                        if (
                            !entry.isIntersecting
                        ) {
                            return;
                        }

                        requestAnimationFrame(() => {

                            entry.target.classList.add(
                                "active"
                            );
                        });

                        observer.unobserve(
                            entry.target
                        );
                    });
                },

                {
                    threshold: 0.12,
                    rootMargin:
                        "0px 0px -20px 0px",
                }
            );

        elements.forEach((element) => {
            observer.observe(element);
        });

        this.observers.push(observer);
    }

    /* =====================================================
       NAVBAR SCROLL
    ===================================================== */

    initializeNavbar() {

        const nav =
            this.elements.nav;

        if (!nav) {
            return;
        }

        let ticking = false;

        const update = () => {

            const scrolled =
                window.scrollY > 30;

            nav.classList.toggle(
                "nav-scrolled",
                scrolled
            );

            nav.classList.toggle(
                "nav-default",
                !scrolled
            );

            ticking = false;
        };

        update();

        window.addEventListener(
            "scroll",

            () => {

                if (ticking) {
                    return;
                }

                requestAnimationFrame(update);

                ticking = true;
            },

            { passive: true }
        );
    }

    /* =====================================================
       SMOOTH SCROLL
    ===================================================== */

    initializeSmoothScroll() {

        const {
            anchorLinks,
            nav,
        } = this.elements;

        if (!anchorLinks.length) {
            return;
        }

        anchorLinks.forEach((link) => {

            link.addEventListener(
                "click",

                (event) => {

                    const href =
                        link.getAttribute("href");

                    if (
                        !href ||
                        !href.includes("#")
                    ) {
                        return;
                    }

                    const hash =
                        href.substring(
                            href.indexOf("#")
                        );

                    const target =
                        document.querySelector(hash);

                    if (!target) {
                        return;
                    }

                    event.preventDefault();

                    const navHeight =
                        nav?.offsetHeight || 70;

                    const top =
                        target.getBoundingClientRect()
                            .top +
                        window.scrollY -
                        navHeight;

                    window.scrollTo({
                        top,
                        behavior: "smooth",
                    });

                    this.closeMenu();
                }
            );
        });
    }

    /* =====================================================
       ACTIVE NAVIGATION
    ===================================================== */

    initializeActiveSections() {

        const {
            sections,
            navLinks,
        } = this.elements;

        if (!sections.length) {
            return;
        }

        const observer =
            new IntersectionObserver(

                (entries) => {

                    entries.forEach((entry) => {

                        if (
                            !entry.isIntersecting
                        ) {
                            return;
                        }

                        const id =
                            entry.target.id;

                        navLinks.forEach((link) => {

                            const href =
                                link.getAttribute(
                                    "href"
                                );

                            const active =
                                href?.includes(
                                    `#${id}`
                                );

                            link.classList.toggle(
                                "active",
                                active
                            );
                        });
                    });
                },

                {
                    threshold: 0.45,
                }
            );

        sections.forEach((section) => {
            observer.observe(section);
        });

        this.observers.push(observer);
    }

    /* =====================================================
       RESIZE HANDLER
    ===================================================== */

    initializeResizeHandler() {

        const handler =
            this.debounce(() => {

                if (
                    window.innerWidth >= 768
                ) {
                    this.closeMenu();
                }

            }, 180);

        window.addEventListener(
            "resize",
            handler
        );

        this.boundHandlers.set(
            "resize",
            handler
        );
    }

    /* =====================================================
       REDUCED MOTION SUPPORT
    ===================================================== */

    initializeReducedMotionSupport() {

        const reduced =
            window.matchMedia(
                "(prefers-reduced-motion: reduce)"
            );

        if (!reduced.matches) {
            return;
        }

        document.documentElement.classList.add(
            "reduced-motion"
        );
    }

    /* =====================================================
       READY STATE
    ===================================================== */

    markApplicationReady() {

        document.documentElement.classList.add(
            "app-ready"
        );
    }

    /* =====================================================
       HELPERS
    ===================================================== */

    debounce(callback, delay = 200) {

        let timer;

        return (...args) => {

            clearTimeout(timer);

            timer = setTimeout(() => {
                callback(...args);
            }, delay);
        };
    }
}

/* =========================================================
   APPLICATION BOOT
========================================================= */

document.addEventListener(
    "DOMContentLoaded",
    () => {

        try {

            const app =
                new RiskSignalApp();

            app.init();

            window.RiskSignalApp = app;

        } catch (error) {

            console.error(
                "[RiskSignal] Frontend initialization failed:",
                error
            );
        }
    }
);
