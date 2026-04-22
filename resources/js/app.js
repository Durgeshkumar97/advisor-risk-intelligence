import './bootstrap';

/* ==================================================
   RISKSIGNAL APP.JS (FULL POLISHED VERSION)
   Responsive + Smooth + Optimized + Clean
================================================== */

document.addEventListener('DOMContentLoaded', () => {
    initScrollAnimations();
    initMobileMenu();
    initTheme();
    initNavbarScroll();
    initSmoothAnchorScroll();
    initResizeFix();
});

/* ==================================================
   SCROLL REVEAL
================================================== */
function initScrollAnimations() {
    const items = document.querySelectorAll('.reveal');

    if (!items.length) return;

    if (!('IntersectionObserver' in window)) {
        items.forEach(el => el.classList.add('active'));
        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('active');
                observer.unobserve(entry.target);
            }
        });
    }, {
        threshold: 0.12,
        rootMargin: '0px 0px -60px 0px'
    });

    items.forEach(el => observer.observe(el));
}

/* ==================================================
   MOBILE MENU
================================================== */
function initMobileMenu() {
    const toggle = document.getElementById('menu-toggle');
    const menu = document.getElementById('mobile-menu');
    const openIcon = document.getElementById('icon-open');
    const closeIcon = document.getElementById('icon-close');
    const themeBtn = document.getElementById('theme-toggle');

    if (!toggle || !menu) return;

    toggle.addEventListener('click', () => {
        menu.classList.contains('open')
            ? closeMenu()
            : openMenu();
    });

    menu.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', closeMenu);
    });

    document.addEventListener('click', (e) => {
        const clickedOutside =
            !menu.contains(e.target) &&
            !toggle.contains(e.target);

        if (menu.classList.contains('open') && clickedOutside) {
            closeMenu();
        }
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth > 768) closeMenu();
    });

    function openMenu() {
        menu.classList.add('open');
        menu.classList.remove('hidden');

        document.body.classList.add('overflow-hidden');

        toggle.setAttribute('aria-expanded', 'true');

        if (openIcon) openIcon.classList.add('hidden');
        if (closeIcon) closeIcon.classList.remove('hidden');

        if (themeBtn) themeBtn.style.display = 'none';
    }

    function closeMenu() {
        menu.classList.remove('open');
        menu.classList.add('hidden');

        document.body.classList.remove('overflow-hidden');

        toggle.setAttribute('aria-expanded', 'false');

        if (openIcon) openIcon.classList.remove('hidden');
        if (closeIcon) closeIcon.classList.add('hidden');

        if (themeBtn) themeBtn.style.display = 'block';
    }
}

/* ==================================================
   THEME SYSTEM
================================================== */
function initTheme() {
    const html = document.documentElement;
    const btn = document.getElementById('theme-toggle');

    const savedTheme =
        localStorage.getItem('theme') || 'dark';

    applyTheme(savedTheme);

    if (btn) {
        btn.addEventListener('click', toggleTheme);
    }

    function toggleTheme() {
        const current = html.getAttribute('data-theme');
        const next = current === 'light'
            ? 'dark'
            : 'light';

        applyTheme(next);
        localStorage.setItem('theme', next);
    }

    function applyTheme(theme) {
        html.setAttribute('data-theme', theme);
        updateThemeIcon(theme);
    }
}

/* ==================================================
   THEME ICON
================================================== */
function updateThemeIcon(theme) {
    const icon = document.querySelector('.theme-icon');
    const btn = document.getElementById('theme-toggle');

    const symbol = theme === 'dark'
        ? '☀️'
        : '🌙';

    if (icon) {
        icon.textContent = symbol;
    } else if (btn) {
        btn.textContent = symbol;
    }
}

/* ==================================================
   NAVBAR SCROLL EFFECT
================================================== */
function initNavbarScroll() {
    const nav = document.querySelector('nav');

    if (!nav) return;

    const onScroll = () => {
        if (window.scrollY > 40) {
            nav.classList.add('nav-scrolled');
            nav.classList.remove('nav-default');
        } else {
            nav.classList.add('nav-default');
            nav.classList.remove('nav-scrolled');
        }
    };

    onScroll();

    window.addEventListener('scroll', onScroll, {
        passive: true
    });
}

/* ==================================================
   SMOOTH ANCHOR SCROLL
================================================== */
function initSmoothAnchorScroll() {
    const links = document.querySelectorAll('a[href^="#"]');

    links.forEach(link => {
        link.addEventListener('click', function (e) {

            const targetId = this.getAttribute('href');

            if (targetId === '#') return;

            const target = document.querySelector(targetId);

            if (!target) return;

            e.preventDefault();

            const navHeight =
                document.querySelector('nav')?.offsetHeight || 70;

            const top =
                target.getBoundingClientRect().top +
                window.pageYOffset -
                navHeight;

            window.scrollTo({
                top,
                behavior: 'smooth'
            });
        });
    });
}

/* ==================================================
   SCREEN HEIGHT FIX
   Mobile browser vh bug fix
================================================== */
function initResizeFix() {
    const setHeight = () => {
        document.documentElement.style.setProperty(
            '--vh',
            `${window.innerHeight * 0.01}px`
        );
    };

    setHeight();

    window.addEventListener('resize', setHeight);
    window.addEventListener('orientationchange', setHeight);
}