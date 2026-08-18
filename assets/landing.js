// Smart Marketing Agent — Landing page interactions (dipakai index.php)
(function () {
    'use strict';

    var navbar = document.querySelector('.navbar-landing');

    // Navbar: beri bayangan saat di-scroll
    function updateNavbar() {
        if (navbar) {
            navbar.classList.toggle('scrolled', window.scrollY > 10);
        }
    }
    window.addEventListener('scroll', updateNavbar, { passive: true });
    updateNavbar();

    // Reveal on scroll (IntersectionObserver, fallback tampilkan semua)
    var reveals = document.querySelectorAll('.reveal');
    if ('IntersectionObserver' in window && reveals.length > 0) {
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('revealed');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.15 });
        reveals.forEach(function (el) { observer.observe(el); });
    } else {
        reveals.forEach(function (el) { el.classList.add('revealed'); });
    }

    // Tutup menu mobile setelah klik link anchor
    var navCollapse = document.getElementById('navLanding');
    if (navCollapse) {
        navCollapse.querySelectorAll('.nav-link').forEach(function (link) {
            link.addEventListener('click', function () {
                if (navCollapse.classList.contains('show')) {
                    var instance = bootstrap.Collapse.getInstance(navCollapse);
                    if (instance) { instance.hide(); }
                }
            });
        });
    }
})();
