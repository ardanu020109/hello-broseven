/* Hello BroSeven! - mesin pengenalan wajah di browser (pembungkus face-api.js).
   Gambar kamera tidak pernah disimpan atau diunggah; hanya angka descriptor yang dikirim ke server. */
(function () {
    'use strict';

    var E = (window.HBFace = window.HBFace || {});
    var detectorOptions = null;
    var loaded = false;

    function dist(a, b) { return Math.hypot(a.x - b.x, a.y - b.y); }

    /* Eye Aspect Ratio dari 6 titik landmark mata (s .. s+5). */
    function eyeAspect(pts, s) {
        return (dist(pts[s + 1], pts[s + 5]) + dist(pts[s + 2], pts[s + 4])) / (2 * dist(pts[s], pts[s + 3]));
    }

    function frameInfo(results, video) {
        var info = { count: results.length, face: null };
        if (results.length !== 1) { return info; }

        var r = results[0];
        var box = r.detection.box;
        var pts = r.landmarks.positions;
        var vw = video.videoWidth;
        var vh = video.videoHeight;
        var cx = (box.x + box.width / 2) / vw;
        var cy = (box.y + box.height / 2) / vh;

        info.face = r;
        info.box = { x: box.x, y: box.y, width: box.width, height: box.height };
        info.cx = cx;
        info.sizeRatio = box.width / vw;
        info.centered = cx > 0.3 && cx < 0.7 && cy > 0.25 && cy < 0.75;
        // Posisi hidung di antara tepi rahang: sekitar 0,5 saat menghadap lurus
        info.yaw = (pts[30].x - pts[0].x) / (pts[16].x - pts[0].x);
        info.ear = (eyeAspect(pts, 36) + eyeAspect(pts, 42)) / 2;
        return info;
    }

    E.sleep = function (ms) { return new Promise(function (resolve) { setTimeout(resolve, ms); }); };

    E.loadModels = async function (baseUrl) {
        if (loaded) { return; }
        var fa = window.faceapi;
        if (!fa) { throw new Error('LIB_MISSING'); }
        if (fa.tf && fa.tf.ready) { await fa.tf.ready(); }

        await Promise.all([
            fa.nets.tinyFaceDetector.loadFromUri(baseUrl),
            fa.nets.faceLandmark68Net.loadFromUri(baseUrl),
            fa.nets.faceRecognitionNet.loadFromUri(baseUrl)
        ]);

        detectorOptions = new fa.TinyFaceDetectorOptions({ inputSize: 320, scoreThreshold: 0.5 });
        loaded = true;
    };

    E.startCamera = async function (video) {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            var unsupported = new Error('Tidak didukung');
            unsupported.name = 'NotSupported';
            throw unsupported;
        }

        var stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
            audio: false
        });

        video.srcObject = stream;
        await video.play();

        if (!video.videoWidth) {
            await new Promise(function (resolve) { video.addEventListener('loadeddata', resolve, { once: true }); });
        }
        return stream;
    };

    E.stopCamera = function (video) {
        var stream = video && video.srcObject;
        if (stream && stream.getTracks) {
            stream.getTracks().forEach(function (track) { track.stop(); });
        }
        if (video) { video.srcObject = null; }
    };

    E.errorMessage = function (err) {
        var name = err && err.name;
        if (name === 'NotAllowedError' || name === 'SecurityError' || name === 'PermissionDeniedError') {
            return 'Kamera tidak diizinkan. Izinkan akses kamera pada pengaturan browser, lalu coba lagi.';
        }
        if (name === 'NotFoundError' || name === 'DevicesNotFoundError' || name === 'OverconstrainedError') {
            return 'Kamera tidak ditemukan pada perangkat ini.';
        }
        if (name === 'NotReadableError' || name === 'TrackStartError') {
            return 'Kamera sedang dipakai aplikasi lain. Tutup aplikasi tersebut lalu coba lagi.';
        }
        if (name === 'NotSupported') {
            return 'Browser tidak mendukung kamera, atau halaman tidak dibuka lewat HTTPS.';
        }
        return 'Gagal mengakses kamera.';
    };

    E.analyze = async function (video) {
        var results = await window.faceapi.detectAllFaces(video, detectorOptions).withFaceLandmarks();
        return frameInfo(results, video);
    };

    /* Descriptor 128 angka dari satu wajah. Mengembalikan null jika bukan tepat satu wajah. */
    E.descriptor = async function (video) {
        var results = await window.faceapi.detectAllFaces(video, detectorOptions).withFaceLandmarks().withFaceDescriptors();
        if (results.length !== 1) { return null; }
        return {
            descriptor: Array.prototype.slice.call(results[0].descriptor),
            info: frameInfo(results, video)
        };
    };

    E.quality = function (info) {
        if (info.count === 0) {
            return { ok: false, hint: 'Wajah tidak terdeteksi. Pastikan wajah terlihat jelas dan pencahayaan cukup.' };
        }
        if (info.count > 1) {
            return { ok: false, hint: 'Terdeteksi lebih dari satu wajah. Pastikan hanya Anda yang berada di depan kamera.' };
        }
        if (info.sizeRatio < 0.2) { return { ok: false, hint: 'Dekatkan wajah ke kamera.' }; }
        if (info.sizeRatio > 0.75) { return { ok: false, hint: 'Jauhkan wajah sedikit dari kamera.' }; }
        if (!info.centered) { return { ok: false, hint: 'Posisikan wajah di tengah bingkai.' }; }
        return { ok: true, hint: '' };
    };

    E.isFrontal = function (info) {
        return !!info.face && Math.abs(info.yaw - 0.5) <= 0.08;
    };

    /* Tantangan liveness sederhana: kedip atau tolehan kepala. Hanya penghalang tambahan, bukan jaminan anti-spoofing. */
    E.createLiveness = function (type) {
        var recent = [];
        var closed = false;
        var centered = 0;

        return {
            type: type,
            prompt: type === 'blink'
                ? 'Kedipkan mata Anda sekarang.'
                : 'Tolehkan kepala perlahan ke samping, lalu kembali menghadap ke depan.',
            update: function (info) {
                if (!info.face) { return false; }

                if (type === 'blink') {
                    recent.push(info.ear);
                    if (recent.length > 12) { recent.shift(); }
                    if (recent.length < 6) { return false; }

                    var base = Math.max.apply(null, recent);
                    if (base < 0.18) { return false; }
                    if (info.ear < base * 0.72) { closed = true; return false; }
                    return closed && info.ear > base * 0.85;
                }

                var dev = Math.abs(info.yaw - 0.5);
                if (dev <= 0.07) { centered += 1; }
                return centered >= 2 && dev >= 0.14;
            }
        };
    };

    E.draw = function (canvas, video, info, color) {
        if (!canvas || !video.videoWidth) { return; }
        if (canvas.width !== video.videoWidth || canvas.height !== video.videoHeight) {
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
        }
        var ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        if (!info || !info.box) { return; }
        ctx.lineWidth = 3;
        ctx.strokeStyle = color || '#3c6e71';
        ctx.strokeRect(info.box.x, info.box.y, info.box.width, info.box.height);
    };

    E.clear = function (canvas) {
        if (canvas) { canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height); }
    };
})();
