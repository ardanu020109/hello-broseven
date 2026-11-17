/* Hello BroSeven! - profile perusahaan: ambil koordinat kantor dari Geolocation API. Tanpa jQuery. */
(function () {
    'use strict';

    var btn = document.getElementById('btnGeo');
    var lat = document.getElementById('latitude');
    var lng = document.getElementById('longitude');
    var status = document.getElementById('geoStatus');
    if (!btn || !lat || !lng || !status) { return; }

    function say(text, tone) {
        status.textContent = text;
        status.className = 'small geo-info geo-' + tone;
    }

    function getPosition(options) {
        return new Promise(function (resolve) {
            navigator.geolocation.getCurrentPosition(resolve, function (err) { resolve({ error: err }); }, options);
        });
    }

    function errorText(err) {
        if (err && err.code === 1) {
            return 'Akses lokasi ditolak. Izinkan lokasi pada pengaturan browser (ikon gembok di address bar), lalu coba lagi.';
        }
        if (err && err.code === 3) {
            return 'Pencarian lokasi terlalu lama. Coba lagi di area terbuka, atau isi koordinat secara manual.';
        }
        return 'Lokasi tidak tersedia. Pastikan halaman dibuka lewat HTTPS atau localhost, atau isi koordinat secara manual.';
    }

    btn.addEventListener('click', async function () {
        if (!('geolocation' in navigator)) {
            say('Browser tidak mendukung Geolocation. Isi koordinat secara manual.', 'error');
            return;
        }

        btn.disabled = true;
        say('Mencari lokasi Anda...', 'info');

        var pos = await getPosition({ enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
        if (pos.error && pos.error.code !== 1) {
            // High accuracy sering gagal di perangkat tanpa GPS: ulangi dengan mode biasa
            pos = await getPosition({ enableHighAccuracy: false, timeout: 20000, maximumAge: 60000 });
        }

        btn.disabled = false;

        if (pos.error) {
            say(errorText(pos.error), 'error');
            return;
        }

        lat.value = pos.coords.latitude.toFixed(7);
        lng.value = pos.coords.longitude.toFixed(7);

        var accuracy = Math.round(pos.coords.accuracy);
        if (accuracy > 100) {
            say('Koordinat terisi, tetapi akurasinya rendah (sekitar ' + accuracy + ' m). Untuk titik yang tepat, ulangi dari HP di lokasi kantor. Klik Simpan jika sudah sesuai.', 'warn');
        } else {
            say('Koordinat terisi dengan akurasi sekitar ' + accuracy + ' m. Klik Simpan Profile Perusahaan untuk menyimpannya.', 'ok');
        }
    });
})();
