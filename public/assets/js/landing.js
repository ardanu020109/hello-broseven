/* Hello BroSeven! - landing page: navbar berubah saat scroll, menu mobile menutup setelah klik. */
(function () {
    'use strict';

    var nav = document.getElementById('lpNav');
    var menu = document.getElementById('lpMenu');
    if (!nav) { return; }

    function onScroll() {
        nav.classList.toggle('is-scrolled', window.scrollY > 24);
    }
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    if (menu) {
        menu.querySelectorAll('a.nav-link').forEach(function (link) {
            link.addEventListener('click', function () {
                if (menu.classList.contains('show')) {
                    bootstrap.Collapse.getOrCreateInstance(menu).hide();
                }
            });
        });
    }
})();
