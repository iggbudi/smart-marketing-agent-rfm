// assets/mobile.js — perilaku shell mobile: backdrop sidebar + close on outside.
// Catatan: function toggleSidebar() TETAP inline di tiap halaman (diunci test);
// file ini hanya menambah backdrop & sinkronisasi class .show.
(function () {
    'use strict';

    function init() {
        var sidebar = document.querySelector('.sidebar');
        var toggle = document.querySelector('.mobile-menu-toggle');
        if (!sidebar || !toggle) { return; }

        var backdrop = document.createElement('div');
        backdrop.className = 'sidebar-backdrop';
        document.body.appendChild(backdrop);

        // Klik backdrop -> tutup sidebar
        backdrop.addEventListener('click', function () {
            sidebar.classList.remove('show');
        });

        // Sinkronkan tampilan backdrop dengan class .show (toggleSidebar inline)
        function sync() {
            backdrop.classList.toggle('show', sidebar.classList.contains('show'));
        }
        if (window.MutationObserver) {
            new MutationObserver(sync).observe(sidebar, { attributes: true, attributeFilter: ['class'] });
        } else {
            sidebar.addEventListener('click', sync);
            toggle.addEventListener('click', sync);
        }

        // Tutup dengan tombol ESC
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { sidebar.classList.remove('show'); }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
