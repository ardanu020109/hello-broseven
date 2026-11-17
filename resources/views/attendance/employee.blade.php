@extends('layouts.app')

@section('title', 'Absensi')
@section('page_title', 'Absensi')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/crud.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/attendance.css') }}">
@endpush

@section('page_actions')
    <span class="hb-date-chip"><i class="bi bi-calendar3"></i> {{ $todayLabel }}</span>
@endsection

@section('content')
@php
    $phase = $state['phase'];
    $canAct = $config['canAct'];
    $icons = [
        'inactive' => 'bi-person-slash', 'no_schedule' => 'bi-calendar-x', 'holiday' => 'bi-calendar-heart',
        'off_day' => 'bi-moon-stars', 'leave' => 'bi-envelope-check', 'absent' => 'bi-person-x',
        'too_early' => 'bi-hourglass-split', 'closed' => 'bi-door-closed', 'done' => 'bi-check-circle',
    ];
    $faceMessages = [
        'none' => 'Wajah Anda belum terdaftar. Daftarkan wajah terlebih dahulu untuk dapat absen.',
        'pending' => 'Pengajuan wajah Anda menunggu persetujuan admin. Absensi belum dapat dilakukan sampai disetujui.',
        'inactive' => 'Data wajah Anda dinonaktifkan oleh admin. Daftarkan ulang wajah (perlu persetujuan admin).',
    ];
@endphp

<div class="row g-3">
    {{-- ===== Kolom kiri: wajah ===== --}}
    <div class="col-12 col-xl-7">
        @if ($canAct && $config['requiresFace'] && $faceStatus === 'active')
            <div class="hb-card hb-panel">
                <div class="hb-panel-head">
                    <h2 class="hb-panel-title">Verifikasi Wajah</h2>
                    <span class="hb-pill hb-pill-muted" id="faceBadge">Menunggu</span>
                </div>

                <div class="hb-cam" id="camBox">
                    <video id="cam" autoplay playsinline muted></video>
                    <canvas id="camCanvas"></canvas>
                    <div class="hb-cam-guide"></div>
                    <div class="hb-cam-overlay" id="camOverlay">
                        <i class="bi bi-camera-video"></i>
                        <span id="camOverlayText">Menyiapkan kamera...</span>
                    </div>
                </div>

                <div class="hb-face-msg is-info" id="faceMsg" role="status" aria-live="polite">Menyiapkan verifikasi wajah...</div>

                <ol class="hb-steps">
                    <li data-step="detect"><i class="bi bi-circle"></i> Wajah terdeteksi (satu wajah)</li>
                    <li data-step="live"><i class="bi bi-circle"></i> Pemeriksaan keaktifan (kedip atau tolehan)</li>
                    <li data-step="match"><i class="bi bi-circle"></i> Wajah dikenali</li>
                </ol>

                <button type="button" class="hb-btn hb-btn-secondary" id="btnCamera" hidden>
                    <i class="bi bi-arrow-repeat me-1"></i> <span>Coba lagi</span>
                </button>
                <p class="small text-muted mb-0 mt-3">
                    Gambar kamera hanya diproses di perangkat Anda dan tidak direkam atau diunggah. Yang dikirim ke server hanya data numerik wajah, dan kamera dimatikan setelah verifikasi berhasil.
                </p>
            </div>

        @elseif ($canAct && $config['requiresFace'])
            <div class="hb-card hb-panel text-center">
                <span class="hb-state-icon"><i class="bi bi-person-bounding-box"></i></span>
                <h2 class="h5 mt-3">Verifikasi wajah belum dapat dipakai</h2>
                <p class="text-muted">{{ $faceMessages[$faceStatus] ?? $faceMessages['none'] }}</p>
                @if ($faceStatus !== 'pending')
                    <a href="{{ route('employee.face') }}" class="hb-btn hb-btn-primary"><i class="bi bi-person-plus me-1"></i> Daftarkan Wajah</a>
                @else
                    <a href="{{ route('employee.face') }}" class="hb-btn hb-btn-light">Lihat status pendaftaran</a>
                @endif
            </div>

        @elseif ($canAct)
            <div class="hb-card hb-panel text-center">
                <span class="hb-state-icon"><i class="bi bi-person-check"></i></span>
                <h2 class="h5 mt-3">Verifikasi wajah dinonaktifkan</h2>
                <p class="text-muted mb-0">Super Admin menonaktifkan fitur Face Recognition. Absensi dilakukan tanpa verifikasi wajah.</p>
            </div>

        @else
            <div class="hb-card hb-panel text-center">
                <span class="hb-state-icon"><i class="bi {{ $icons[$phase] ?? 'bi-info-circle' }}"></i></span>
                <h2 class="h5 mt-3">{{ $phase === 'done' ? 'Absensi selesai' : 'Absensi tidak tersedia' }}</h2>
                <p class="text-muted mb-0">{{ $state['message'] }}</p>
            </div>
        @endif
    </div>

    {{-- ===== Kolom kanan: status, lokasi, aksi ===== --}}
    <div class="col-12 col-xl-5">
        <div class="hb-card hb-panel mb-3">
            <div class="hb-panel-head">
                <h2 class="hb-panel-title">Hari Ini</h2>
                <span class="hb-clock-mini" id="clock">--:--:--</span>
            </div>

            @if ($ws)
                <dl class="hb-dl hb-dl-sm mb-0">
                    <dt>Jadwal</dt><dd>{{ $ws->name }}</dd>
                    <dt>Jam kerja</dt><dd>{{ substr((string) $ws->check_in_time, 0, 5) }} - {{ substr((string) $ws->check_out_time, 0, 5) }} ({{ $ws->timezone }})</dd>
                    <dt>Toleransi</dt><dd>{{ $ws->late_tolerance_minutes }} menit</dd>
                    <dt>Mode hari ini</dt>
                    <dd>
                        @if ($state['mode'] === 'WFO')
                            <span class="hb-mode hb-mode-wfo">WFO</span> wajib di area kantor
                        @elseif ($state['mode'] === 'WFH')
                            <span class="hb-mode hb-mode-wfh">WFH</span> radius kantor tidak diwajibkan
                        @else - @endif
                    </dd>
                    <dt>Masuk</dt><dd>{{ $record?->check_in_at?->format('H:i') ?? '--:--' }}</dd>
                    <dt>Pulang</dt><dd>{{ $record?->check_out_at?->format('H:i') ?? '--:--' }}</dd>
                </dl>
            @else
                <div class="hb-alert hb-alert-info mb-0"><i class="bi bi-info-circle-fill"></i><span>{{ $state['message'] }}</span></div>
            @endif
        </div>

        @if ($canAct && $config['gpsEnabled'])
            <div class="hb-card hb-panel mb-3" id="gpsCard">
                <div class="hb-panel-head">
                    <h2 class="hb-panel-title">Lokasi</h2>
                    <span class="hb-pill hb-pill-muted" id="gpsBadge">Mencari lokasi...</span>
                </div>
                <div class="hb-gps">
                    <div><span>Jarak ke kantor</span><strong id="gpsDistance">-</strong></div>
                    <div><span>Akurasi GPS</span><strong id="gpsAccuracy">-</strong></div>
                    <div><span>Radius kantor</span><strong>{{ $config['office'] ? $config['office']['radius'].' m' : 'Belum diatur' }}</strong></div>
                </div>
                <p class="small text-muted mt-3 mb-0" id="gpsNote">
                    @if ($config['requiresGps'])
                        Anda harus berada dalam radius kantor untuk absen. Server memeriksa ulang lokasi Anda.
                    @else
                        Mode WFH: radius kantor tidak diwajibkan. Lokasi tetap dicatat jika Anda mengizinkannya.
                    @endif
                </p>
            </div>
        @endif

        @if ($canAct)
            <div class="hb-card hb-panel">
                <h2 class="hb-panel-title mb-3">Absen</h2>
                <div class="d-grid gap-2">
                    <button type="button" class="hb-btn hb-btn-primary py-2" id="btnIn" disabled {{ $phase === 'ready_in' ? '' : 'hidden' }}>
                        <i class="bi bi-box-arrow-in-right me-1"></i> Absen Masuk
                    </button>
                    <button type="button" class="hb-btn hb-btn-secondary py-2" id="btnOut" disabled {{ $phase === 'ready_out' ? '' : 'hidden' }}>
                        <i class="bi bi-box-arrow-right me-1"></i> Absen Pulang
                    </button>
                </div>
                <p class="small text-muted mt-3 mb-0" id="actionHint">{{ $state['message'] }}</p>
            </div>
        @endif
    </div>
</div>

<script type="application/json" id="attConfig">@json($config, JSON_HEX_TAG | JSON_HEX_AMP)</script>
@endsection

@push('scripts')
    @if ($canAct && $config['requiresFace'] && $faceStatus === 'active')
        <script src="{{ asset('assets/vendor/face-api/face-api.js') }}"></script>
        <script src="{{ asset('assets/js/face-engine.js') }}"></script>
    @endif
    <script src="{{ asset('assets/js/attendance.js') }}"></script>
@endpush
