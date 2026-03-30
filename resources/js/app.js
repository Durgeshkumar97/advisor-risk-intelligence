import './bootstrap';

// ─────────────────────────────
// SCROLL ANIMATION ENGINE
// ─────────────────────────────
document.addEventListener("DOMContentLoaded", () => {

    const elements = document.querySelectorAll(".reveal");

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {

            if (entry.isIntersecting) {
                entry.target.classList.add("active");
            } else {
                entry.target.classList.remove("active");
            }

        });
    }, {
        threshold: 0.15
    });

    elements.forEach(el => observer.observe(el));

});