/* Hello BroSeven! - fungsi inti bersama (toast, loading, submit form). Tanpa jQuery. */
(function () {
    'use strict';

    var HB = (window.HB = window.HB || {});

    var ICONS = {
        success: 'bi-check-circle-fill',
        error: 'bi-x-octagon-fill',
        warning: 'bi-exclamation-triangle-fill',
        info: 'bi-info-circle-fill'
    };
    var TITLES = { success: 'Berhasil', error: 'Gagal', warning: 'Perhatian', info: 'Informasi' };

    /** Tampilkan toast. Teks disisipkan lewat textContent (aman dari XSS). */
    HB.toast = function (message, type, title) {
        var container = document.getElementById('hbToasts');
        if (!container || !message) { return; }
        type = ICONS[type] ? type : 'info';

        var el = document.createElement('div');
        el.className = 'toast hb-toast is-' + type;
        el.setAttribute('role', type === 'error' ? 'alert' : 'status');
        el.setAttribute('aria-live', type === 'error' ? 'assertive' : 'polite');
        el.setAttribute('aria-atomic', 'true');

        var body = document.createElement('div');
        body.className = 'hb-toast-body';

        var icon = document.createElement('i');
        icon.className = 'bi ' + ICONS[type] + ' hb-toast-icon';

        var text = document.createElement('div');
        text.className = 'hb-toast-text';
        var strong = document.createElement('strong');
        strong.textContent = title || TITLES[type];
        var msg = document.createElement('div');
        msg.textContent = message;
        text.appendChild(strong);
        text.appendChild(msg);

        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'btn-close';
        close.setAttribute('data-bs-dismiss', 'toast');
        close.setAttribute('aria-label', 'Tutup');

        body.appendChild(icon);
        body.appendChild(text);
        body.appendChild(close);
        el.appendChild(body);
        container.appendChild(el);

        var toast = new bootstrap.Toast(el, { delay: type === 'error' ? 7000 : 4500 });
        el.addEventListener('hidden.bs.toast', function () { el.remove(); });
        toast.show();
    };

    /** Overlay loading layar penuh. */
    HB.loading = function (show) {
        var el = document.getElementById('hbLoading');
        if (!el) { return; }
        el.classList.toggle('is-visible', !!show);
        el.setAttribute('aria-hidden', show ? 'false' : 'true');
    };

    /* Form ber-atribut data-hb-loading: cegah kirim ganda + tampilkan spinner di tombol.
       Event "submit" hanya terpicu setelah validasi HTML5 browser lolos. */
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-hb-loading')) { return; }

        if (form.dataset.submitting === '1') {
            e.preventDefault();
            return;
        }
        form.dataset.submitting = '1';

        var btn = e.submitter || form.querySelector('[type="submit"]');
        if (btn) {
            btn.dataset.originalHtml = btn.innerHTML;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>' +
                (btn.dataset.loadingText || 'Memproses...');
            btn.classList.add('disabled');
            btn.setAttribute('aria-disabled', 'true');
        }
        if (form.hasAttribute('data-hb-overlay')) { HB.loading(true); }
    });

    /* Tombol Back pada browser dapat memulihkan halaman dari bfcache dengan tombol masih "loading". */
    window.addEventListener('pageshow', function () {
        document.querySelectorAll('form[data-submitting="1"]').forEach(function (form) {
            delete form.dataset.submitting;
            form.querySelectorAll('[data-original-html]').forEach(function (btn) {
                btn.innerHTML = btn.dataset.originalHtml;
                btn.classList.remove('disabled');
                btn.removeAttribute('aria-disabled');
            });
        });
        HB.loading(false);
    });

    /* Menu yang belum tersedia (route belum dibuat). */
    document.addEventListener('click', function (e) {
        var link = e.target.closest('[data-soon]');
        if (!link) { return; }
        e.preventDefault();
        var label = link.dataset.label || link.textContent.trim();
        HB.toast('Menu "' + label + '" belum tersedia. Fitur ini dibuat pada fase berikutnya.', 'info');
    });

    /* Flash message dari server -> toast. */
    document.addEventListener('DOMContentLoaded', function () {
        var flash = window.HB_FLASH || {};
        ['success', 'error', 'warning', 'info'].forEach(function (type) {
            if (flash[type]) { HB.toast(flash[type], type); }
        });
    });
})();
