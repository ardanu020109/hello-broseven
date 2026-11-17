/* Hello BroSeven! - perilaku halaman CRUD (modal master, konfirmasi, form karyawan). Tanpa jQuery. */
(function () {
    'use strict';

    /* ===== Modal konfirmasi: diisi dari atribut data-* tombol pemicu ===== */
    var confirmEl = document.getElementById('hbConfirm');
    if (confirmEl) {
        confirmEl.addEventListener('show.bs.modal', function (e) {
            var btn = e.relatedTarget;
            if (!btn) { return; }

            var form = confirmEl.querySelector('form');
            var methodInput = confirmEl.querySelector('[data-confirm-method]');
            var submit = confirmEl.querySelector('[data-confirm-submit]');
            var icon = confirmEl.querySelector('[data-confirm-icon]');
            var danger = (btn.dataset.tone || 'danger') === 'danger';
            var method = (btn.dataset.method || 'POST').toUpperCase();

            form.action = btn.dataset.action;
            methodInput.value = method;
            methodInput.disabled = (method === 'POST');
            confirmEl.querySelector('[data-confirm-title]').textContent = btn.dataset.title || 'Konfirmasi';
            confirmEl.querySelector('[data-confirm-message]').textContent = btn.dataset.message || '';
            submit.textContent = btn.dataset.btn || 'Ya, lanjutkan';
            submit.className = 'hb-btn ' + (danger ? 'hb-btn-danger' : 'hb-btn-primary');
            icon.className = 'hb-confirm-icon ' + (danger ? 'is-danger' : 'is-primary');
        });
    }

    /* ===== Modal reset password ===== */
    var resetEl = document.getElementById('hbResetPw');
    if (resetEl) {
        resetEl.addEventListener('show.bs.modal', function (e) {
            var btn = e.relatedTarget;
            if (!btn) { return; }
            resetEl.querySelector('form').action = btn.dataset.action;
            resetEl.querySelector('[data-resetpw-name]').textContent = btn.dataset.name || '';
            var input = resetEl.querySelector('#resetPwInput');
            input.value = '';
            input.type = 'password';
        });
    }

    /* ===== Modal tambah/ubah Data Master ===== */
    var formModal = document.getElementById('hbFormModal');
    if (formModal) {
        var form = formModal.querySelector('form');
        var titleEl = formModal.querySelector('[data-form-title]');
        var methodInput = form.querySelector('[data-form-method]');
        var modeInput = form.querySelector('[data-mode-input]');
        var idInput = form.querySelector('[data-id-input]');
        var singular = formModal.dataset.singular || '';

        var configure = function (mode, id) {
            if (mode === 'edit') {
                form.action = form.dataset.updateUrl.replace(/\/0$/, '/' + id);
                methodInput.disabled = false;
                titleEl.textContent = 'Ubah ' + singular;
            } else {
                form.action = form.dataset.storeUrl;
                methodInput.disabled = true;
                titleEl.textContent = 'Tambah ' + singular;
            }
            modeInput.value = mode;
            idInput.value = id || '';
        };

        var controls = function () { return form.querySelectorAll('[data-field]'); };

        var clearErrors = function () {
            form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
            form.querySelectorAll('.invalid-feedback').forEach(function (el) { el.remove(); });
        };

        var fillValues = function (values) {
            controls().forEach(function (el) {
                var v = values[el.dataset.field];
                if (el.type === 'checkbox') {
                    el.checked = (v === true || v === 1 || v === '1');
                } else {
                    el.value = (v === null || v === undefined) ? '' : String(v);
                }
            });
        };

        var fillDefaults = function () {
            controls().forEach(function (el) {
                if (el.type === 'checkbox') {
                    el.checked = el.dataset.default === '1';
                } else {
                    el.value = el.dataset.default || '';
                }
            });
        };

        formModal.addEventListener('show.bs.modal', function (e) {
            var btn = e.relatedTarget;
            if (!btn) { return; } // dibuka otomatis setelah validasi gagal: biarkan nilai lama dari server

            clearErrors();
            var mode = btn.dataset.mode || 'create';
            configure(mode, btn.dataset.id);

            if (mode === 'edit') {
                var record = {};
                try { record = JSON.parse(btn.dataset.record || '{}'); } catch (err) { record = {}; }
                fillValues(record);
            } else {
                fillDefaults();
            }
        });

        formModal.addEventListener('shown.bs.modal', function () {
            var first = form.querySelector('[data-field]:not([type="checkbox"])');
            if (first) { first.focus(); }
        });

        // Validasi server gagal: buka kembali modal dengan input lama dan pesan kesalahan
        if (formModal.dataset.reopenMode) {
            configure(formModal.dataset.reopenMode, formModal.dataset.reopenId);
            bootstrap.Modal.getOrCreateInstance(formModal).show();
        }
    }

    /* ===== Password: buat acak dan tampilkan/sembunyikan ===== */
    function rand(n) {
        return crypto.getRandomValues(new Uint32Array(1))[0] % n;
    }

    function randomPassword(length) {
        var sets = ['ABCDEFGHJKLMNPQRSTUVWXYZ', 'abcdefghijkmnpqrstuvwxyz', '23456789'];
        var all = sets.join('');
        var chars = sets.map(function (s) { return s.charAt(rand(s.length)); });
        while (chars.length < length) { chars.push(all.charAt(rand(all.length))); }
        for (var i = chars.length - 1; i > 0; i--) {
            var j = rand(i + 1);
            var t = chars[i]; chars[i] = chars[j]; chars[j] = t;
        }
        return chars.join('');
    }

    document.addEventListener('click', function (e) {
        var gen = e.target.closest('[data-hb-genpass]');
        if (gen) {
            var target = document.querySelector(gen.dataset.hbGenpass);
            if (target) { target.value = randomPassword(12); target.type = 'text'; }
            return;
        }
        var tog = e.target.closest('[data-hb-togglepass]');
        if (tog) {
            var input = document.querySelector(tog.dataset.hbTogglepass);
            if (!input) { return; }
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            var icon = tog.querySelector('i');
            if (icon) { icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye'; }
        }
    });

    /* ===== Berkas: batasi ukuran di browser (server tetap memvalidasi) + pratinjau foto ===== */
    document.addEventListener('change', function (e) {
        var input = e.target;
        if (!(input instanceof HTMLInputElement) || input.type !== 'file' || !input.files || !input.files[0]) { return; }

        var file = input.files[0];
        var maxKb = parseInt(input.dataset.maxKb || '0', 10);
        if (maxKb && file.size > maxKb * 1024) {
            HB.toast('Ukuran berkas melebihi ' + (maxKb / 1024) + ' MB.', 'error');
            input.value = '';
            return;
        }

        if (input.dataset.hbPreview) {
            var img = document.querySelector(input.dataset.hbPreview);
            if (img) { img.src = URL.createObjectURL(file); img.hidden = false; }
        }
    });

    /* ===== Form karyawan: pilihan divisi mengikuti departemen ===== */
    var dept = document.getElementById('department_id');
    var division = document.getElementById('division_id');
    if (dept && division) {
        var filterDivisions = function () {
            Array.prototype.forEach.call(division.options, function (opt) {
                if (!opt.value) { return; }
                var match = opt.dataset.dept === dept.value;
                opt.hidden = !match;
                opt.disabled = !match;
            });
            var selected = division.options[division.selectedIndex];
            if (selected && selected.disabled) { division.value = ''; }
        };
        dept.addEventListener('change', filterDivisions);
        filterDivisions();
    }
})();
