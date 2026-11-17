/* Hello BroSeven! - perilaku sidebar: collapse di desktop, offcanvas di mobile. */
(function () {
    'use strict';

    var root = document.documentElement;
    var sidebar = document.getElementById('hbSidebar');
    var toggleBtn = document.getElementById('hbSidebarToggle');
    if (!sidebar || !toggleBtn) { return; }

    var KEY = 'hb.sidebar';
    var desktop = window.matchMedia('(min-width: 992px)');

    function save(collapsed) {
        try { localStorage.setItem(KEY, collapsed ? 'collapsed' : 'expanded'); } catch (e) { /* abaikan */ }
    }

    function syncAria() {
        toggleBtn.setAttribute('aria-expanded', String(!(desktop.matches && root.classList.contains('sidebar-collapsed'))));
    }

    toggleBtn.addEventListener('click', function () {
        if (desktop.matches) {
            var collapsed = root.classList.toggle('sidebar-collapsed');
            save(collapsed);
            syncAria();
        } else {
            bootstrap.Offcanvas.getOrCreateInstance(sidebar).toggle();
        }
    });

    // Saat sidebar ciut, klik menu bergrup membukanya kembali agar submenu terlihat.
    document.querySelectorAll('.hb-nav-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (desktop.matches && root.classList.contains('sidebar-collapsed')) {
                root.classList.remove('sidebar-collapsed');
                save(false);
                syncAria();
            }
        });
    });

    // Mobile: tutup offcanvas setelah memilih link navigasi.
    sidebar.querySelectorAll('a.hb-nav-link:not([data-soon])').forEach(function (a) {
        a.addEventListener('click', function () {
            if (!desktop.matches) { bootstrap.Offcanvas.getOrCreateInstance(sidebar).hide(); }
        });
    });

    // Layar diperlebar ke desktop saat offcanvas terbuka: tutup agar backdrop tidak tertinggal.
    desktop.addEventListener('change', function (e) {
        if (e.matches) {
            var instance = bootstrap.Offcanvas.getInstance(sidebar);
            if (instance) { instance.hide(); }
        }
        syncAria();
    });

    syncAria();
})();
