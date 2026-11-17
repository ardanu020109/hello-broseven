/* Hello BroSeven! - halaman login: tampilkan/sembunyikan password. */
(function () {
    'use strict';

    var btn = document.getElementById('togglePassword');
    var input = document.getElementById('password');
    if (!btn || !input) { return; }

    btn.addEventListener('click', function () {
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.setAttribute('aria-pressed', String(show));
        btn.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');
        btn.querySelector('i').className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
    });
})();
