/* Hello BroSeven! - halaman absensi karyawan: verifikasi wajah, GPS, absen masuk dan pulang. */
(function () {
    'use strict';

    var cfgEl = document.getElementById('attConfig');
    if (!cfgEl) { return; }

    var cfg;
    try { cfg = JSON.parse(cfgEl.textContent); } catch (e) { return; }

    function $(id) { return document.getElementById(id); }

    var video = $('cam');
    var canvas = $('camCanvas');
    var overlay = $('camOverlay');
    var overlayText = $('camOverlayText');
    var faceMsg = $('faceMsg');
    var faceBadge = $('faceBadge');
    var btnCamera = $('btnCamera');
    var btnIn = $('btnIn');
    var btnOut = $('btnOut');
    var actionHint = $('actionHint');
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    var useFace = !!(cfg.canAct && cfg.requiresFace && cfg.faceReady && video);
    var S = { ticket: null, ticketAt: 0, ticketTtl: 120, runId: 0, gps: null, gpsWatch: null, busy: false, done: false };

    /* ===== Jam sesuai waktu server ===== */
    var clockOffset = cfg.serverNow - Date.now();
    function serverNow() { return Date.now() + clockOffset; }

    var clockEl = $('clock');
    if (clockEl) {
        var fmt = new Intl.DateTimeFormat('en-GB', { timeZone: cfg.timezone, hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });
        var tick = function () { clockEl.textContent = fmt.format(new Date(serverNow())); };
        tick();
        setInterval(tick, 1000);
    }

    /* ===== Util tampilan ===== */
    function setFaceMsg(text, tone) {
        if (!faceMsg) { return; }
        faceMsg.textContent = text;
        faceMsg.className = 'hb-face-msg is-' + (tone || 'info');
    }

    function setBadge(text, tone) {
        if (!faceBadge) { return; }
        faceBadge.textContent = text;
        faceBadge.className = 'hb-pill hb-pill-' + (tone || 'muted');
    }

    var STEP_ICONS = { idle: 'bi-circle', active: 'bi-arrow-right-circle', done: 'bi-check-circle-fill', fail: 'bi-x-circle-fill' };
    function setStep(name, state) {
        var li = document.querySelector('.hb-steps [data-step="' + name + '"]');
        if (!li) { return; }
        li.className = state === 'idle' ? '' : 'is-' + state;
        var icon = li.querySelector('i');
        if (icon) { icon.className = 'bi ' + STEP_ICONS[state]; }
    }

    function showOverlay(text) {
        if (!overlay) { return; }
        if (text) { overlayText.textContent = text; overlay.hidden = false; } else { overlay.hidden = true; }
    }

    function fail(message) {
        setFaceMsg(message, 'error');
        setBadge('Gagal', 'danger');
        if (btnCamera) { btnCamera.hidden = false; btnCamera.querySelector('span').textContent = 'Coba lagi'; }
    }

    /* ===== GPS ===== */
    function haversine(lat1, lon1, lat2, lon2) {
        var R = 6371000, rad = Math.PI / 180;
        var dPhi = (lat2 - lat1) * rad, dLam = (lon2 - lon1) * rad;
        var a = Math.sin(dPhi / 2) * Math.sin(dPhi / 2) +
            Math.cos(lat1 * rad) * Math.cos(lat2 * rad) * Math.sin(dLam / 2) * Math.sin(dLam / 2);
        return 2 * R * Math.asin(Math.min(1, Math.sqrt(a)));
    }

    function gpsInfo() {
        if (!S.gps) { return null; }
        var d = cfg.office ? haversine(S.gps.lat, S.gps.lng, cfg.office.lat, cfg.office.lng) : null;
        return {
            distance: d,
            accuracy: S.gps.accuracy,
            within: d !== null ? d <= cfg.office.radius : null,
            accurate: cfg.office ? S.gps.accuracy <= cfg.office.maxAccuracy : true
        };
    }

    function gpsOk() {
        if (!cfg.requiresGps) { return true; }
        var g = gpsInfo();
        return !!(g && cfg.office && g.within && g.accurate);
    }

    function setGpsBadge(text, tone) {
        var el = $('gpsBadge');
        if (el) { el.textContent = text; el.className = 'hb-pill hb-pill-' + tone; }
    }

    function updateGpsUi() {
        var g = gpsInfo();
        if (!g) { return; }
        var dEl = $('gpsDistance'), aEl = $('gpsAccuracy');
        if (dEl) { dEl.textContent = g.distance !== null ? Math.round(g.distance) + ' m' : 'Kantor belum diatur'; }
        if (aEl) { aEl.textContent = '\u00B1' + Math.round(g.accuracy) + ' m'; }

        if (!cfg.office) {
            setGpsBadge(cfg.requiresGps ? 'Koordinat kantor belum diatur' : 'Lokasi tercatat', cfg.requiresGps ? 'danger' : 'muted');
        } else if (!g.accurate) {
            setGpsBadge('Akurasi rendah', 'warning');
        } else if (g.within) {
            setGpsBadge('Dalam radius kantor', 'success');
        } else {
            setGpsBadge(cfg.requiresGps ? 'Di luar radius kantor' : 'Di luar kantor (WFH)', cfg.requiresGps ? 'danger' : 'info');
        }
    }

    function gpsError(err) {
        var msg;
        if (err && err.code === 1) { msg = cfg.requiresGps ? 'Akses lokasi ditolak' : 'Lokasi tidak dibagikan'; }
        else if (err && err.code === 3) { msg = 'Lokasi terlalu lama'; }
        else { msg = 'Lokasi tidak tersedia'; }
        setGpsBadge(msg, cfg.requiresGps ? 'danger' : 'muted');
        var note = $('gpsNote');
        if (note && cfg.requiresGps && err && err.code === 1) {
            note.textContent = 'Izinkan akses lokasi pada pengaturan browser, lalu muat ulang halaman.';
        }
    }

    function onPosition(p) {
        S.gps = { lat: p.coords.latitude, lng: p.coords.longitude, accuracy: p.coords.accuracy, ts: p.timestamp || Date.now() };
        updateGpsUi();
        refreshButtons();
    }

    function watchGps(highAccuracy) {
        S.gpsWatch = navigator.geolocation.watchPosition(onPosition, function (err) {
            // High accuracy sering timeout di perangkat tanpa chip GPS: ulangi dengan mode biasa
            if (highAccuracy && err && err.code !== 1) {
                navigator.geolocation.clearWatch(S.gpsWatch);
                watchGps(false);
                return;
            }
            gpsError(err);
        }, { enableHighAccuracy: highAccuracy, maximumAge: 5000, timeout: 30000 });
    }

    function startGps() {
        if (!cfg.canAct || !cfg.gpsEnabled) { return; }
        if (!('geolocation' in navigator)) { setGpsBadge('GPS tidak didukung', 'danger'); return; }
        watchGps(true);
    }

    function getPosition(options) {
        return new Promise(function (resolve) {
            navigator.geolocation.getCurrentPosition(
                function (p) { resolve(p); },
                function () { resolve(null); },
                options
            );
        });
    }

    /* Lokasi untuk dikirim saat absen: pakai fix terbaru dari pemantauan jika masih segar,
       jika tidak, minta ulang dengan beberapa strategi. */
    async function freshPosition() {
        if (!cfg.gpsEnabled || !('geolocation' in navigator)) { return null; }

        if (S.gps && (Date.now() - S.gps.ts) < 120000) {
            return { coords: { latitude: S.gps.lat, longitude: S.gps.lng, accuracy: S.gps.accuracy }, timestamp: S.gps.ts };
        }

        var pos = await getPosition({ enableHighAccuracy: true, maximumAge: 15000, timeout: 20000 });
        if (!pos) { pos = await getPosition({ enableHighAccuracy: false, maximumAge: 60000, timeout: 20000 }); }
        return pos;
    }

    /* ===== Tombol absen ===== */
    function faceOk() {
        if (!cfg.requiresFace) { return true; }
        return !!(S.ticket && (Date.now() - S.ticketAt) < S.ticketTtl * 1000);
    }

    function refreshButtons() {
        if (!cfg.canAct) { return; }
        var ready = faceOk() && gpsOk() && !S.busy && !S.done;
        if (btnIn) { btnIn.disabled = !ready; }
        if (btnOut) { btnOut.disabled = !ready; }
        if (!actionHint || S.done) { return; }

        if (!cfg.faceReady) { actionHint.textContent = 'Daftarkan wajah terlebih dahulu untuk dapat absen.'; }
        else if (!faceOk()) { actionHint.textContent = 'Selesaikan verifikasi wajah terlebih dahulu.'; }
        else if (!gpsOk()) {
            var g = gpsInfo();
            if (!g) { actionHint.textContent = 'Menunggu lokasi GPS. Izinkan akses lokasi di browser.'; }
            else if (!cfg.office) { actionHint.textContent = 'Koordinat kantor belum diatur. Hubungi administrator.'; }
            else if (!g.accurate) { actionHint.textContent = 'Akurasi GPS belum memenuhi batas ' + cfg.office.maxAccuracy + ' m. Pindah ke area terbuka.'; }
            else { actionHint.textContent = 'Anda di luar radius kantor (' + Math.round(g.distance) + ' m dari kantor, maksimal ' + cfg.office.radius + ' m).'; }
        } else {
            actionHint.textContent = 'Semua verifikasi terpenuhi. Silakan absen.';
        }
    }

    /* ===== Jaringan ===== */
    function postJson(url, body) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(body)
        }).then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (data) {
                return { status: r.status, ok: r.ok, data: data };
            });
        });
    }

    /* ===== Verifikasi wajah ===== */
    async function verifyFace() {
        setFaceMsg('Memverifikasi wajah...', 'info');
        var cap = null;
        try { cap = await HBFace.descriptor(video); } catch (e) { cap = null; }
        if (!cap) { return { ok: false, message: 'Wajah tidak terdeteksi dengan jelas. Coba lagi.' }; }

        var r;
        try { r = await postJson(cfg.urls.verify, { descriptor: cap.descriptor }); }
        catch (e) { return { ok: false, message: 'Gagal terhubung ke server. Periksa koneksi internet.' }; }

        if (r.ok && r.data.ok && r.data.ticket) {
            return { ok: true, ticket: r.data.ticket, ttl: r.data.expires_in || 120, name: r.data.name };
        }
        if (r.status === 419) { return { ok: false, stop: true, message: 'Sesi Anda berakhir. Muat ulang halaman lalu login kembali.' }; }
        return {
            ok: false,
            stop: !!(r.data && (r.data.stop || r.data.needs_registration)) || r.status === 429 || (r.status === 422 && r.data && r.data.matched === undefined),
            message: (r.data && r.data.message) || 'Verifikasi wajah gagal.'
        };
    }

    async function runFace() {
        var myRun = ++S.runId;
        S.ticket = null;
        refreshButtons();
        setBadge('Memproses', 'info');
        if (btnCamera) { btnCamera.hidden = true; }
        showOverlay('Memuat model pengenalan wajah...');

        try {
            await HBFace.loadModels(cfg.urls.models);
        } catch (e) {
            showOverlay(null);
            fail(e && e.message === 'LIB_MISSING'
                ? 'Library pengenalan wajah belum terpasang. Hubungi administrator.'
                : 'Model pengenalan wajah gagal dimuat. Muat ulang halaman atau hubungi administrator.');
            return;
        }

        showOverlay('Meminta akses kamera...');
        try {
            await HBFace.startCamera(video);
        } catch (e) {
            showOverlay(null);
            fail(HBFace.errorMessage(e));
            return;
        }

        showOverlay(null);
        await detectLoop(myRun);
    }

    async function detectLoop(myRun) {
        var phase = 'detect';
        var good = 0, frontal = 0, challenges = 0;
        var liveness = null, liveStart = 0, capStart = 0;
        var order = Math.random() < 0.5 ? ['blink', 'turn'] : ['turn', 'blink'];

        function resetSteps() {
            setStep('detect', 'active'); setStep('live', 'idle'); setStep('match', 'idle');
        }
        function newChallenge() {
            liveness = HBFace.createLiveness(order[challenges % 2]);
            liveStart = Date.now();
        }

        resetSteps();
        setBadge('Memproses', 'info');
        setFaceMsg('Hadapkan wajah ke kamera.', 'info');

        while (S.runId === myRun) {
            var started = Date.now();
            var info;
            try { info = await HBFace.analyze(video); }
            catch (e) { fail('Gagal memproses gambar kamera. Muat ulang halaman.'); return; }
            if (S.runId !== myRun) { return; }

            var q = HBFace.quality(info);
            HBFace.draw(canvas, video, info, q.ok ? '#3c6e71' : '#e0a100');

            if (phase === 'detect') {
                if (q.ok) { good += 1; } else { good = 0; setFaceMsg(q.hint, 'warning'); }
                if (good >= 3) {
                    setStep('detect', 'done');
                    setStep('live', 'active');
                    newChallenge();
                    phase = 'live';
                    setFaceMsg(liveness.prompt, 'info');
                }
            } else if (phase === 'live') {
                if (info.count !== 1) {
                    phase = 'detect'; good = 0; resetSteps();
                    setFaceMsg(q.hint, 'warning');
                } else if (liveness.update(info)) {
                    setStep('live', 'done');
                    phase = 'capture'; frontal = 0; capStart = Date.now();
                    setFaceMsg('Pemeriksaan keaktifan berhasil. Hadapkan wajah lurus ke kamera dan tahan sebentar.', 'info');
                } else if (Date.now() - liveStart > 9000) {
                    challenges += 1;
                    newChallenge();
                    setFaceMsg('Belum terdeteksi. ' + liveness.prompt, 'warning');
                }
            } else if (phase === 'capture') {
                if (q.ok && HBFace.isFrontal(info)) { frontal += 1; }
                else { frontal = 0; if (!q.ok) { setFaceMsg(q.hint, 'warning'); } }

                if (frontal >= 2) {
                    var res = await verifyFace();
                    if (S.runId !== myRun) { return; }

                    if (res.ok) {
                        S.ticket = res.ticket; S.ticketAt = Date.now(); S.ticketTtl = res.ttl;
                        setStep('match', 'done');
                        setBadge('Terverifikasi', 'success');
                        setFaceMsg('Wajah berhasil dikenali' + (res.name ? ': ' + res.name : '') + '.', 'success');
                        HBFace.stopCamera(video);
                        HBFace.clear(canvas);
                        showOverlay('Wajah terverifikasi');
                        refreshButtons();
                        return;
                    }

                    setStep('match', 'fail');
                    setBadge('Tidak cocok', 'danger');
                    setFaceMsg(res.message, 'error');
                    if (res.stop) { HBFace.stopCamera(video); HBFace.clear(canvas); showOverlay('Verifikasi dihentikan'); fail(res.message); return; }

                    await HBFace.sleep(2200);
                    if (S.runId !== myRun) { return; }
                    phase = 'detect'; good = 0; frontal = 0; resetSteps();
                    setBadge('Memproses', 'info');
                    setFaceMsg('Hadapkan wajah ke kamera.', 'info');
                } else if (Date.now() - capStart > 10000) {
                    phase = 'detect'; good = 0; resetSteps();
                    setFaceMsg('Posisi wajah belum lurus. Ulangi dari awal.', 'warning');
                }
            }

            var wait = 130 - (Date.now() - started);
            if (wait > 0) { await HBFace.sleep(wait); }
        }
    }

    function expireTicket() {
        S.ticket = null;
        if (cfg.requiresFace) {
            setBadge('Kedaluwarsa', 'warning');
            setFaceMsg('Verifikasi wajah kedaluwarsa. Ulangi verifikasi.', 'warning');
            if (btnCamera) { btnCamera.hidden = false; btnCamera.querySelector('span').textContent = 'Verifikasi ulang'; }
        }
        refreshButtons();
    }

    setInterval(function () {
        if (S.ticket && (Date.now() - S.ticketAt) >= (S.ticketTtl - 5) * 1000) { expireTicket(); }
    }, 1000);

    /* ===== Kirim absen ===== */
    async function submit(kind) {
        if (S.busy || S.done) { return; }

        if (kind === 'out' && cfg.endMs && serverNow() < cfg.endMs) {
            if (!window.confirm('Belum jam pulang (' + cfg.endTime + '). Tetap absen pulang sekarang?')) { return; }
        }

        S.busy = true;
        refreshButtons();
        HB.loading(true);

        try {
            var payload = { face_ticket: S.ticket || null };
            var pos = await freshPosition();

            if (pos) {
                payload.latitude = pos.coords.latitude;
                payload.longitude = pos.coords.longitude;
                payload.accuracy = pos.coords.accuracy;
                payload.captured_at = Math.round(pos.timestamp);
            } else if (cfg.requiresGps) {
                HB.toast('Lokasi belum dapat diperoleh. Tunggu beberapa detik sampai jarak ke kantor tampil, lalu klik lagi.', 'error');
                return;
            }

            var res = await postJson(kind === 'in' ? cfg.urls.checkIn : cfg.urls.checkOut, payload);
            var d = res.data || {};

            if (res.ok && d.ok) {
                S.done = true;
                HB.toast(d.message, 'success');
                setTimeout(function () { window.location.reload(); }, 1800);
                return;
            }

            if (res.status === 419) {
                HB.toast('Sesi Anda berakhir. Muat ulang halaman lalu login kembali.', 'error');
                return;
            }

            HB.toast(d.message || 'Absensi gagal diproses.', 'error');
            if (d.code === 'face_ticket_invalid') { expireTicket(); }
            if (res.status === 409 && d.code !== 'face_not_registered') {
                setTimeout(function () { window.location.reload(); }, 2200);
            }
        } catch (e) {
            HB.toast('Gagal terhubung ke server. Periksa koneksi internet.', 'error');
        } finally {
            S.busy = false;
            HB.loading(false);
            refreshButtons();
        }
    }

    if (btnIn) { btnIn.addEventListener('click', function () { submit('in'); }); }
    if (btnOut) { btnOut.addEventListener('click', function () { submit('out'); }); }
    if (btnCamera) { btnCamera.addEventListener('click', function () { runFace(); }); }

    window.addEventListener('pagehide', function () {
        S.runId += 1;
        if (video) { HBFace.stopCamera(video); }
        if (S.gpsWatch !== null) { navigator.geolocation.clearWatch(S.gpsWatch); }
    });

    /* ===== Mulai ===== */
    startGps();
    refreshButtons();
    if (useFace) { runFace(); }
})();
