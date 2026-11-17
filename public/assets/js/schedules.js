/* Hello BroSeven! - perilaku halaman Data Jadwal. Tanpa jQuery. */
(function () {
    'use strict';

    /* ===== Form jadwal: mode kerja nonaktif pada hari libur + durasi kerja ===== */
    document.querySelectorAll('.js-day-toggle').forEach(function (toggle) {
        var row = toggle.closest('tr');
        var select = row ? row.querySelector('select') : null;

        function sync() {
            if (select) { select.disabled = !toggle.checked; }
            if (row) { row.classList.toggle('is-off', !toggle.checked); }
        }
        toggle.addEventListener('change', sync);
        sync();
    });

    var inEl = document.getElementById('check_in_time');
    var outEl = document.getElementById('check_out_time');
    var durationEl = document.getElementById('durationText');

    function toMinutes(v) {
        if (!v || v.indexOf(':') === -1) { return null; }
        var p = v.split(':');
        return parseInt(p[0], 10) * 60 + parseInt(p[1], 10);
    }

    function updateDuration() {
        if (!durationEl || !inEl || !outEl) { return; }
        var a = toMinutes(inEl.value);
        var b = toMinutes(outEl.value);
        if (a === null || b === null || b <= a) { durationEl.textContent = '-'; return; }
        var diff = b - a;
        durationEl.textContent = Math.floor(diff / 60) + ' jam ' + (diff % 60) + ' menit';
    }

    if (inEl && outEl) {
        inEl.addEventListener('input', updateDuration);
        outEl.addEventListener('input', updateDuration);
        updateDuration();
    }

    /* ===== Filter kalender: kirim otomatis saat pilihan berubah ===== */
    document.querySelectorAll('[data-autosubmit]').forEach(function (el) {
        el.addEventListener('change', function () { if (el.form) { el.form.submit(); } });
    });

    /* ===== Modal penugasan ===== */
    var modal = document.getElementById('hbAssignModal');
    if (!modal) { return; }

    var form = modal.querySelector('form');
    var panels = form.querySelectorAll('[data-panel]');
    var items = form.querySelectorAll('.hb-check-item');
    var search = form.querySelector('#empSearch');
    var counter = form.querySelector('[data-selected-count]');

    function currentTarget() {
        var checked = form.querySelector('input[name="target"]:checked');
        return checked ? checked.value : 'employees';
    }

    // Panel yang tidak aktif dinonaktifkan agar isinya tidak ikut terkirim.
    function syncPanels() {
        var target = currentTarget();
        panels.forEach(function (panel) {
            var on = panel.dataset.panel === target;
            panel.hidden = !on;
            panel.querySelectorAll('input, select').forEach(function (el) { el.disabled = !on; });
        });
    }

    function updateCount() {
        if (!counter) { return; }
        counter.textContent = form.querySelectorAll('input[name="employee_ids[]"]:checked').length;
    }

    form.querySelectorAll('input[name="target"]').forEach(function (radio) {
        radio.addEventListener('change', syncPanels);
    });

    form.addEventListener('change', function (e) {
        if (e.target.name === 'employee_ids[]') { updateCount(); }
    });

    if (search) {
        search.addEventListener('input', function () {
            var term = search.value.trim().toLowerCase();
            items.forEach(function (item) {
                item.hidden = term !== '' && (item.dataset.search || '').indexOf(term) === -1;
            });
        });
    }

    var selectVisible = form.querySelector('[data-select-visible]');
    if (selectVisible) {
        selectVisible.addEventListener('click', function () {
            items.forEach(function (item) {
                if (!item.hidden) { item.querySelector('input').checked = true; }
            });
            updateCount();
        });
    }

    var clearAll = form.querySelector('[data-clear-all]');
    if (clearAll) {
        clearAll.addEventListener('click', function () {
            items.forEach(function (item) { item.querySelector('input').checked = false; });
            updateCount();
        });
    }

    // Dibuka dari tombol: terapkan target atau jadwal bawaan tombol tersebut.
    modal.addEventListener('show.bs.modal', function (e) {
        var btn = e.relatedTarget;
        if (btn) {
            if (btn.dataset.target) {
                var radio = form.querySelector('input[name="target"][value="' + btn.dataset.target + '"]');
                if (radio) { radio.checked = true; }
            }
            if (btn.dataset.schedule) {
                var select = form.querySelector('#assign_schedule');
                if (select) { select.value = btn.dataset.schedule; }
            }
        }
        syncPanels();
        updateCount();
    });

    syncPanels();
    updateCount();

    // Dibuka otomatis setelah validasi server gagal, atau dari tautan "Tugaskan" di daftar jadwal.
    if (modal.dataset.reopen === '1') {
        bootstrap.Modal.getOrCreateInstance(modal).show();
    }
})();
