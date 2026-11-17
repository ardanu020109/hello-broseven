/* Hello BroSeven! - perilaku halaman absensi/izin: form koreksi, modal pengajuan, modal detail. Tanpa jQuery. */
(function () {
    'use strict';

    /* ===== Form koreksi (admin): jam hanya aktif untuk Hadir/Terlambat ===== */
    var statusSel = document.getElementById('corrStatus');
    if (statusSel) {
        var times = document.querySelectorAll('[data-corr-time]');
        var hint = document.getElementById('corrLeaveHint');

        var syncCorrection = function () {
            var on = statusSel.value === 'present' || statusSel.value === 'late';
            times.forEach(function (el) { el.disabled = !on; });
            if (hint) { hint.hidden = statusSel.value !== 'leave'; }
        };
        statusSel.addEventListener('change', syncCorrection);
        syncCorrection();
    }

    /* ===== Modal pengajuan izin (karyawan) ===== */
    var leaveModal = document.getElementById('hbLeaveModal');
    if (leaveModal) {
        var type = document.getElementById('leaveType');
        var start = document.getElementById('leaveStart');
        var end = document.getElementById('leaveEnd');
        var file = document.getElementById('leaveAttachment');
        var star = document.getElementById('attachStar');

        var syncType = function () {
            var opt = type.options[type.selectedIndex];
            var required = !!(opt && opt.dataset.requires === '1');
            file.required = required;
            star.hidden = !required;
        };
        var syncDates = function () {
            end.min = start.value || '';
            if (start.value && end.value && end.value < start.value) { end.value = start.value; }
        };

        type.addEventListener('change', syncType);
        start.addEventListener('change', syncDates);
        syncType();
        syncDates();

        // Dibuka kembali otomatis setelah validasi server gagal
        if (leaveModal.dataset.reopen === '1') {
            bootstrap.Modal.getOrCreateInstance(leaveModal).show();
        }
    }

    /* ===== Modal detail absensi (karyawan) ===== */
    var detailModal = document.getElementById('hbDetailModal');
    if (detailModal) {
        detailModal.addEventListener('show.bs.modal', function (e) {
            var btn = e.relatedTarget;
            if (!btn) { return; }

            var data = {};
            try { data = JSON.parse(btn.dataset.detail || '{}'); } catch (err) { data = {}; }

            detailModal.querySelectorAll('[data-d]').forEach(function (el) {
                var v = data[el.dataset.d];
                el.textContent = (v === undefined || v === null || v === '') ? '-' : String(v);
            });

            // Tautan peta hanya dipasang jika berupa URL https
            detailModal.querySelectorAll('[data-d-map]').forEach(function (a) {
                var url = data[a.dataset.dMap];
                if (typeof url === 'string' && url.indexOf('https://') === 0) {
                    a.href = url;
                    a.hidden = false;
                } else {
                    a.hidden = true;
                    a.removeAttribute('href');
                }
            });
        });
    }
})();
