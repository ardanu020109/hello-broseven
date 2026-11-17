@extends('layouts.guest')

@section('title', 'Masuk')

@section('content')
<div class="hb-auth">
    <section class="hb-auth-brand">
        <a href="{{ route('home') }}" class="hb-brand">
            <img src="{{ asset('assets/images/logo.svg') }}" alt="" width="44" height="44">
            <span>Hello BroSeven!</span>
        </a>

        <div class="hb-auth-hero">
            <h1>Kelola Kehadiran,<br>Tingkatkan Produktivitas.</h1>
            <p>Absensi berbasis pengenalan wajah dan lokasi, jadwal kerja, pengajuan izin, laporan, dan penggajian harian dalam satu aplikasi.</p>
            <ul class="hb-auth-points">
                <li><i class="bi bi-person-bounding-box"></i> Face Recognition</li>
                <li><i class="bi bi-geo-alt"></i> Verifikasi lokasi GPS</li>
                <li><i class="bi bi-cash-coin"></i> Perhitungan gaji otomatis</li>
            </ul>
        </div>

        <small class="hb-auth-copy">&copy; {{ date('Y') }} Hello BroSeven!</small>
    </section>

    <section class="hb-auth-panel">
        <div class="hb-auth-card">
            <a href="{{ route('home') }}" class="hb-brand hb-brand-dark hb-auth-mobile-brand">
                <img src="{{ asset('assets/images/logo.svg') }}" alt="" width="40" height="40">
                <span>Hello BroSeven!</span>
            </a>

            <h2 class="hb-auth-title">Selamat datang kembali</h2>
            <p class="text-muted mb-4">Masuk untuk melanjutkan ke dashboard Anda.</p>

            @if (session('status'))
                <div class="hb-alert hb-alert-info mb-3" role="status">
                    <i class="bi bi-info-circle-fill"></i><span>{{ session('status') }}</span>
                </div>
            @endif

            <form method="POST" action="{{ route('login.attempt') }}" data-hb-loading>
                @csrf

                <div class="mb-3">
                    <label for="login" class="form-label fw-semibold">Email atau Username</label>
                    <div class="input-group hb-input">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" id="login" name="login" value="{{ old('login') }}"
                               class="form-control @error('login') is-invalid @enderror"
                               placeholder="nama@perusahaan.com" autocomplete="username" maxlength="255" required autofocus>
                    </div>
                    @error('login')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label fw-semibold">Password</label>
                    <div class="input-group hb-input">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" id="password" name="password"
                               class="form-control @error('password') is-invalid @enderror"
                               placeholder="Masukkan password" autocomplete="current-password" maxlength="255" required>
                        <button type="button" class="input-group-text hb-toggle-password" id="togglePassword"
                                aria-label="Tampilkan password" aria-pressed="false">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    @error('password')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
                    <label class="form-check-label" for="remember">Ingat saya</label>
                </div>

                <button type="submit" class="hb-btn hb-btn-primary w-100 py-2">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Masuk
                </button>
            </form>

            @if (app()->environment('local'))
                <div class="hb-dev-box mt-4">
                    <div class="fw-semibold mb-1"><i class="bi bi-tools"></i> Akun uji (hanya tampil di environment local)</div>
                    <div>superadmin@hellobroseven.test</div>
                    <div>admin@hellobroseven.test</div>
                    <div>employee@hellobroseven.test</div>
                    <div class="text-muted mt-1">Password: password123</div>
                </div>
            @endif
        </div>
    </section>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/auth.js') }}"></script>
@endpush
