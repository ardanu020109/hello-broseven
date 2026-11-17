@extends('layouts.app')

@section('title', 'Registrasi Wajah')
@section('page_title', 'Registrasi Wajah')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('employee.attendance') }}">Absensi</a></li>
    <li class="breadcrumb-item active" aria-current="page">Registrasi Wajah</li>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/crud.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/attendance.css') }}">
@endpush

@section('content')
@php
    $date = fn ($d) => $d ? $d->locale('id')->translatedFormat('d F Y H:i') : '-';
@endphp

<div class="row g-3">
    <div class="col-12 col-xl-7">
        <div class="hb-card hb-panel">
            <div class="hb-panel-head">
                <h2 class="hb-panel-title">Kamera</h2>
                <span class="hb-pill hb-pill-muted" id="regBadge">Belum dimulai</span>
            </div>

            <div class="hb-cam" id="camBox">
                <video id="cam" autoplay playsinline muted></video>
                <canvas id="camCanvas"></canvas>
                <div class="hb-cam-guide"></div>
                <div class="hb-cam-overlay" id="camOverlay">
                    <i class="bi bi-person-bounding-box"></i>
                    <span id="camOverlayText">Centang persetujuan, lalu tekan Mulai Pendaftaran.</span>
                </div>
            </div>

            <div class="hb-face-msg is-info" id="faceMsg" role="status" aria-live="polite">
                Pendaftaran mengambil {{ $config['samples'] }} sampel wajah. Duduk di tempat terang, lepas masker atau kacamata hitam, dan pastikan hanya Anda di depan kamera.
            </div>

            <div class="hb-dots" id="sampleDots" aria-label="Progres sampel wajah">
                @for ($i = 0; $i < $config['samples']; $i++) <span></span> @endfor
            </div>

            <ol class="hb-steps">
                <li data-step="detect"><i class="bi bi-circle"></i> Wajah terdeteksi (satu wajah)</li>
                <li data-step="live"><i class="bi bi-circle"></i> Pemeriksaan keaktifan (kedip atau tolehan)</li>
                <li data-step="match"><i class="bi bi-circle"></i> Pengambilan {{ $config['samples'] }} sampel</li>
            </ol>
        </div>
    </div>

    <div class="col-12 col-xl-5">
        <div class="hb-card hb-panel mb-3">
            <h2 class="hb-panel-title mb-3">Status Data Wajah</h2>
            @if ($faceStatus === 'active')
                <span class="hb-st hb-st-present"><i class="bi bi-check-circle"></i> Terdaftar</span>
                <p class="small text-muted mt-2 mb-0">Disetujui/didaftarkan: {{ $date($active?->approved_at ?? $active?->registered_at) }}. Registrasi ulang memerlukan persetujuan admin, dan data lama tetap dipakai sampai disetujui.</p>
            @elseif ($faceStatus === 'pending')
                <span class="hb-st hb-st-pending"><i class="bi bi-hourglass-split"></i> Menunggu persetujuan</span>
                <p class="small text-muted mt-2 mb-0">Diajukan: {{ $date($pending?->registered_at) }}. Mengajukan ulang akan menggantikan pengajuan ini.</p>
            @elseif ($faceStatus === 'inactive')
                <span class="hb-st hb-st-absent"><i class="bi bi-slash-circle"></i> Dinonaktifkan</span>
                <p class="small text-muted mt-2 mb-0">Data wajah Anda dinonaktifkan admin. Daftarkan ulang; pengajuan perlu disetujui admin.</p>
            @else
                <span class="hb-st hb-st-pending"><i class="bi bi-exclamation-circle"></i> Belum terdaftar</span>
                <p class="small text-muted mt-2 mb-0">Pendaftaran pertama langsung aktif setelah selesai.</p>
            @endif
        </div>

        <div class="hb-card hb-panel">
            <h2 class="hb-panel-title mb-3">Persetujuan Data Biometrik</h2>
            <ul class="hb-consent-list">
                <li><i class="bi bi-check2"></i> Yang disimpan adalah <strong>data numerik wajah (face descriptor)</strong>, bukan foto atau video.</li>
                <li><i class="bi bi-check2"></i> Data hanya dipakai untuk memverifikasi identitas Anda saat absensi.</li>
                <li><i class="bi bi-check2"></i> Data disimpan <strong>terenkripsi</strong> dan tidak ditampilkan kepada siapa pun, termasuk admin.</li>
                <li><i class="bi bi-check2"></i> Admin dapat menonaktifkan data wajah Anda kapan saja.</li>
                <li><i class="bi bi-check2"></i> Gambar kamera tidak direkam dan tidak diunggah.</li>
            </ul>

            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="consent">
                <label class="form-check-label" for="consent">
                    Saya memahami dan menyetujui penggunaan data biometrik wajah saya untuk keperluan absensi.
                </label>
            </div>

            <div class="d-grid gap-2">
                <button type="button" class="hb-btn hb-btn-primary py-2" id="btnStart">
                    <i class="bi bi-camera-video me-1"></i> <span>Mulai Pendaftaran</span>
                </button>
                <a href="{{ $config['urls']['back'] }}" class="hb-btn hb-btn-light">Kembali ke Absensi</a>
            </div>
            @if ($config['needsApproval'])
                <p class="small text-muted mt-3 mb-0">Karena Anda pernah mendaftar, hasil pendaftaran ini akan menunggu persetujuan admin.</p>
            @endif
        </div>
    </div>
</div>

<script type="application/json" id="regConfig">@json($config, JSON_HEX_TAG | JSON_HEX_AMP)</script>
@endsection

@push('scripts')
    <script src="{{ asset('assets/vendor/face-api/face-api.js') }}"></script>
    <script src="{{ asset('assets/js/face-engine.js') }}"></script>
    <script src="{{ asset('assets/js/face-register.js') }}"></script>
@endpush
