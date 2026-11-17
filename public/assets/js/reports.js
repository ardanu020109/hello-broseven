/* Hello BroSeven! - laporan dan payroll: jenis periode, modal periode, cetak. Tanpa jQuery. */
(function () {
    'use strict';

    var mode = document.getElementById('periodMode');
    if (mode) {
        var fields = document.querySelectorAll('[data-mode]');
        var sync = function () {
            fields.forEach(function (f) {
                var on = f.dataset.mode === mode.value;
                f.hidden = !on;
                f.querySelectorAll('input').forEach(function (i) { i.disabled = !on; });
            });
        };
        mode.addEventListener('change', sync);
        sync();
    }

    document.querySelectorAll('[data-print]').forEach(function (btn) {
        btn.addEventListener('click', function () { window.print(); });
    });

    if (document.body.hasAttribute('data-autoprint')) {
        window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 400); });
    }

    /* Modal buat periode dibuka kembali setelah validasi gagal. */
    var periodModal = document.getElementById('hbPeriodModal');
    if (periodModal && periodModal.dataset.reopen === '1' && window.bootstrap) {
        bootstrap.Modal.getOrCreateInstance(periodModal).show();
    }
})();
