/* Hello BroSeven! - registrasi wajah: persetujuan, liveness, pengambilan 5 sampel, kirim ke server. */
(function () {
    'use strict';

    var cfgEl = document.getElementById('regConfig');
    if (!cfgEl) { return; }

    var cfg;
    try { cfg = JSON.parse(cfgEl.textContent); } catch (e) { return; }

    function $(id) { return document.getElementById(id); }

    var video = $('cam');
    var canvas = $('camCanvas');
    var overlay = $('camOverlay');
    var overlayText = $('camOverlayText');
    var faceMsg = $('faceMsg');
    var badge = $('regBadge');
    var consent = $('consent');
    var btnStart = $('btnStart');
    var dots = document.querySelectorAll('#sampleDots span');
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    var runId = 0;
    var running = false;

    function setMsg(text, tone) { faceMsg.textContent = text; faceMsg.className = 'hb-face-msg is-' + (tone || 'info'); }
    function setBadge(text, tone) { badge.textContent = text; badge.className = 'hb-pill hb-pill-' + (tone || 'muted'); }
    function showOverlay(text) {
        if (text) { overlayText.textContent = text; overlay.hidden = false; } else { overlay.hidden = true; }
    }

    var STEP_ICONS = { idle: 'bi-circle', active: 'bi-arrow-right-circle', done: 'bi-check-circle-fill', fail: 'bi-x-circle-fill' };
    function setStep(name, state) {
        var li = document.querySelector('.hb-steps [data-step="' + name + '"]');
        if (!li) { return; }
        li.className = state === 'idle' ? '' : 'is-' + state;
        var icon = li.querySelector('i');
        if (icon) { icon.className = 'bi ' + STEP_ICONS[state]; }
    }

    function setDots(n) {
        dots.forEach(function (d, i) { d.classList.toggle('is-on', i < n); });
    }

    function finishRun() {
        running = false;
        consent.disabled = false;
        btnStart.disabled = false;
        btnStart.querySelector('span').textContent = 'Mulai Ulang';
    }

    function failRun(message) {
        HBFace.stopCamera(video);
        HBFace.clear(canvas);
        showOverlay('Pendaftaran dihentikan');
        setBadge('Gagal', 'danger');
        setMsg(message, 'error');
        finishRun();
    }

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

    async function start() {
        if (running) { return; }
        if (!consent.checked) {
            HB.toast('Centang persetujuan penggunaan data biometrik terlebih dahulu.', 'warning');
            return;
        }

        running = true;
        var myRun = ++runId;
        consent.disabled = true;
        btnStart.disabled = true;
        setDots(0);
        ['detect', 'live', 'match'].forEach(function (s) { setStep(s, 'idle'); });
        setBadge('Memproses', 'info');
        showOverlay('Memuat model pengenalan wajah...');

        try {
            await HBFace.loadModels(cfg.urls.models);
        } catch (e) {
            failRun(e && e.message === 'LIB_MISSING'
                ? 'Library pengenalan wajah belum terpasang. Hubungi administrator.'
                : 'Model pengenalan wajah gagal dimuat. Muat ulang halaman atau hubungi administrator.');
            return;
        }

        showOverlay('Meminta akses kamera...');
        try { await HBFace.startCamera(video); }
        catch (e) { failRun(HBFace.errorMessage(e)); return; }

        showOverlay(null);
        var samples = await collect(myRun);
        if (!samples || runId !== myRun) { return; }

        HBFace.stopCamera(video);
        HBFace.clear(canvas);
        showOverlay('Mengirim data wajah...');
        setMsg('Mengirim data wajah ke server...', 'info');

        var r;
        try { r = await postJson(cfg.urls.store, { consent: true, samples: samples }); }
        catch (e) { failRun('Gagal terhubung ke server. Periksa koneksi internet, lalu mulai ulang.'); return; }

        if (r.ok && r.data.ok) {
            setStep('match', 'done');
            setBadge(r.data.status === 'active' ? 'Terdaftar' : 'Menunggu persetujuan', 'success');
            showOverlay('Selesai');
            setMsg(r.data.message, 'success');
            HB.toast(r.data.message, 'success');
            setTimeout(function () { window.location.href = cfg.urls.back; }, 2200);
            return;
        }

        var msg = r.status === 419 ? 'Sesi Anda berakhir. Muat ulang halaman lalu login kembali.'
            : ((r.data && r.data.message) || 'Pendaftaran wajah gagal.');
        setStep('match', 'fail');
        failRun(msg);
    }

    async function collect(myRun) {
        var phase = 'detect';
        var good = 0, challenges = 0, liveness = null, liveStart = 0;
        var samples = [];
        var lastCapture = 0, prevCx = null, stable = 0;
        var order = Math.random() < 0.5 ? ['blink', 'turn'] : ['turn', 'blink'];

        function newChallenge() {
            liveness = HBFace.createLiveness(order[challenges % 2]);
            liveStart = Date.now();
        }

        setStep('detect', 'active');
        setMsg('Hadapkan wajah ke kamera.', 'info');

        while (runId === myRun) {
            var started = Date.now();
            var info;
            try { info = await HBFace.analyze(video); }
            catch (e) { failRun('Gagal memproses gambar kamera. Muat ulang halaman.'); return null; }
            if (runId !== myRun) { return null; }

            var q = HBFace.quality(info);
            HBFace.draw(canvas, video, info, q.ok ? '#3c6e71' : '#e0a100');

            if (phase === 'detect') {
                if (q.ok) { good += 1; } else { good = 0; setMsg(q.hint, 'warning'); }
                if (good >= 3) {
                    setStep('detect', 'done'); setStep('live', 'active');
                    newChallenge(); phase = 'live';
                    setMsg(liveness.prompt, 'info');
                }
            } else if (phase === 'live') {
                if (info.count !== 1) {
                    phase = 'detect'; good = 0;
                    setStep('detect', 'active'); setStep('live', 'idle');
                    setMsg(q.hint, 'warning');
                } else if (liveness.update(info)) {
                    setStep('live', 'done'); setStep('match', 'active');
                    phase = 'sampling'; prevCx = null; stable = 0; lastCapture = 0;
                    setMsg('Bagus. Tahan wajah lurus ke kamera. Sampel diambil otomatis.', 'info');
                } else if (Date.now() - liveStart > 9000) {
                    challenges += 1; newChallenge();
                    setMsg('Belum terdeteksi. ' + liveness.prompt, 'warning');
                }
            } else if (phase === 'sampling') {
                if (!q.ok) {
                    stable = 0; prevCx = null; setMsg(q.hint, 'warning');
                } else if (!HBFace.isFrontal(info)) {
                    stable = 0; setMsg('Hadapkan wajah lurus ke kamera.', 'info');
                } else {
                    stable = (prevCx !== null && Math.abs(info.cx - prevCx) < 0.03) ? stable + 1 : 0;
                    prevCx = info.cx;

                    if (stable >= 2 && Date.now() - lastCapture > 800) {
                        var cap = null;
                        try { cap = await HBFace.descriptor(video); } catch (e) { cap = null; }
                        if (runId !== myRun) { return null; }

                        if (cap && HBFace.isFrontal(cap.info)) {
                            samples.push(cap.descriptor);
                            lastCapture = Date.now();
                            setDots(samples.length);
                            setMsg('Sampel ' + samples.length + ' dari ' + cfg.samples + ' tersimpan.', 'success');
                            if (samples.length >= cfg.samples) { return samples; }
                        }
                    }
                }
            }

            var wait = 130 - (Date.now() - started);
            if (wait > 0) { await HBFace.sleep(wait); }
        }
        return null;
    }

    btnStart.addEventListener('click', start);

    window.addEventListener('pagehide', function () {
        runId += 1;
        HBFace.stopCamera(video);
    });
})();
