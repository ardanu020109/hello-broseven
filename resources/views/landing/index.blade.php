<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} - Aplikasi Absensi Karyawan</title>
    <meta name="description" content="Hello BroSeven! adalah aplikasi manajemen absensi karyawan dengan Face Recognition, verifikasi lokasi GPS, jadwal kerja, pengajuan izin, laporan, dan penggajian otomatis.">
    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/images/logo.svg') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/components.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/landing.css') }}">
</head>
<body class="lp-body">

{{-- ===== Navbar ===== --}}
<nav class="navbar navbar-expand-lg lp-nav fixed-top" id="lpNav">
    <div class="container">
        <a class="hb-brand hb-brand-dark" href="#beranda">
            <img src="{{ asset('assets/images/logo.svg') }}" alt="" width="36" height="36">
            <span>Hello BroSeven!</span>
        </a>
        <button class="navbar-toggler lp-toggler" type="button" data-bs-toggle="collapse"
                data-bs-target="#lpMenu" aria-controls="lpMenu" aria-expanded="false" aria-label="Buka menu">
            <i class="bi bi-list fs-2"></i>
        </button>
        <div class="collapse navbar-collapse" id="lpMenu">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                <li class="nav-item"><a class="nav-link lp-link" href="#beranda">Beranda</a></li>
                <li class="nav-item"><a class="nav-link lp-link" href="#tentang-aplikasi">Tentang Aplikasi</a></li>
                <li class="nav-item"><a class="nav-link lp-link" href="#tentang-perusahaan">Tentang Perusahaan</a></li>
                <li class="nav-item"><a class="nav-link lp-link" href="#fitur">Fitur</a></li>
                <li class="nav-item ms-lg-2 mt-2 mt-lg-0">
                    @auth
                        <a class="hb-btn hb-btn-primary d-inline-block" href="{{ route('dashboard') }}">
                            <i class="bi bi-speedometer2 me-1"></i> Dashboard
                        </a>
                    @else
                        <a class="hb-btn hb-btn-primary d-inline-block" href="{{ route('login') }}">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Login
                        </a>
                    @endauth
                </li>
            </ul>
        </div>
    </div>
</nav>

{{-- ===== Hero ===== --}}
<header class="lp-hero" id="beranda">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <span class="lp-chip"><i class="bi bi-stars"></i> Aplikasi absensi karyawan modern</span>
                <h1 class="lp-hero-title">Hello BroSeven! Kelola Kehadiran, Tingkatkan Produktivitas.</h1>
                <p class="lp-hero-text">
                    Catat kehadiran dengan pengenalan wajah dan verifikasi lokasi, atur jadwal kerja,
                    proses pengajuan izin, lihat laporan, dan hitung gaji harian secara otomatis
                    dalam satu aplikasi.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    @auth
                        <a href="{{ route('dashboard') }}" class="hb-btn lp-btn-light">
                            <i class="bi bi-speedometer2 me-1"></i> Buka Dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="hb-btn lp-btn-light">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Login
                        </a>
                    @endauth
                    <a href="#fitur" class="hb-btn lp-btn-outline">Lihat Fitur</a>
                </div>
            </div>

            <div class="col-lg-6">
                {{-- Ilustrasi dashboard (dekoratif) --}}
                <div class="lp-mock" aria-hidden="true">
                    <div class="lp-mock-bar"><span></span><span></span><span></span></div>
                    <div class="lp-mock-body">
                        <div class="lp-mock-tiles">
                            <div class="lp-tile"><i class="bi bi-person-check"></i><b>Hadir</b><div class="lp-line"></div></div>
                            <div class="lp-tile lp-tile-warn"><i class="bi bi-alarm"></i><b>Terlambat</b><div class="lp-line"></div></div>
                            <div class="lp-tile lp-tile-navy"><i class="bi bi-envelope-check"></i><b>Izin</b><div class="lp-line"></div></div>
                        </div>
                        <div class="lp-mock-chart">
                            <i style="height:45%"></i><i style="height:70%"></i><i style="height:55%"></i>
                            <i style="height:85%"></i><i style="height:65%"></i><i style="height:92%"></i><i style="height:78%"></i>
                        </div>
                        <div class="lp-mock-badges">
                            <span><i class="bi bi-person-bounding-box"></i> Wajah dikenali</span>
                            <span><i class="bi bi-geo-alt-fill"></i> Dalam radius kantor</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

{{-- ===== Tentang Aplikasi ===== --}}
<section class="lp-section" id="tentang-aplikasi">
    <div class="container">
        <div class="lp-heading">
            <span class="lp-eyebrow">Tentang Aplikasi</span>
            <h2>Sistem absensi yang akurat, cepat, dan mudah dipakai</h2>
            <p>Hello BroSeven! membantu perusahaan mencatat kehadiran secara terpercaya dan mengubahnya menjadi laporan serta penggajian tanpa rekap manual.</p>
        </div>

        <div class="row g-4">
            <div class="col-md-4">
                <div class="lp-card h-100">
                    <span class="lp-icon"><i class="bi bi-bullseye"></i></span>
                    <h3>Tujuan</h3>
                    <p>Mengurangi kecurangan dan kesalahan pencatatan kehadiran, serta mempercepat pekerjaan tim HR dalam mengelola jadwal, izin, dan gaji.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="lp-card h-100">
                    <span class="lp-icon"><i class="bi bi-shield-check"></i></span>
                    <h3>Verifikasi berlapis</h3>
                    <p>Identitas dicocokkan lewat wajah, lokasi diperiksa terhadap radius kantor, dan seluruh validasi diulang di server. Karyawan WFH mengikuti aturan khusus sesuai jadwalnya.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="lp-card h-100">
                    <span class="lp-icon"><i class="bi bi-diagram-3"></i></span>
                    <h3>Terintegrasi</h3>
                    <p>Data absensi, jadwal, izin, dan gaji tersambung dalam satu sistem sehingga angka di laporan dan payroll selalu konsisten.</p>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ===== Fitur ===== --}}
<section class="lp-section lp-section-alt" id="fitur">
    <div class="container">
        <div class="lp-heading">
            <span class="lp-eyebrow">Fitur</span>
            <h2>Semua yang dibutuhkan untuk mengelola kehadiran</h2>
        </div>

        <div class="row g-4">
            @php
                $features = [
                    ['bi-person-bounding-box', 'Face Recognition', 'Absensi dengan pengenalan wajah melalui kamera perangkat, lengkap dengan pemeriksaan liveness sederhana.'],
                    ['bi-geo-alt', 'GPS Location Verification', 'Memastikan karyawan berada dalam radius kantor menggunakan perhitungan jarak Haversine.'],
                    ['bi-calendar-week', 'Manajemen Jadwal', 'Jadwal reguler dan shift, hari libur, serta penugasan jadwal per karyawan atau departemen.'],
                    ['bi-people', 'Manajemen Karyawan', 'Data karyawan, jabatan, departemen, dokumen, dan akun login dalam satu tempat.'],
                    ['bi-envelope-paper', 'Pengajuan Izin', 'Karyawan mengajukan izin, sakit, atau cuti, dan admin menyetujui atau menolaknya dengan catatan.'],
                    ['bi-bar-chart-line', 'Laporan Absensi', 'Rekap kehadiran per karyawan dan periode, dengan export dan cetak.'],
                    ['bi-cash-coin', 'Perhitungan Gaji Otomatis', 'Gaji harian per jabatan dengan potongan yang dapat dikonfigurasi, plus slip gaji.'],
                ];
            @endphp
            @foreach ($features as [$icon, $title, $text])
                <div class="col-sm-6 col-lg-4">
                    <div class="lp-card lp-feature h-100">
                        <span class="lp-icon"><i class="bi {{ $icon }}"></i></span>
                        <h3>{{ $title }}</h3>
                        <p>{{ $text }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ===== Tentang Perusahaan ===== --}}
<section class="lp-section" id="tentang-perusahaan">
    <div class="container">
        <div class="lp-heading">
            <span class="lp-eyebrow">Tentang Perusahaan</span>
            <h2>{{ $company?->name ?? config('app.name') }}</h2>
        </div>

        <div class="lp-company">
            <div class="lp-company-logo">
                @if ($company?->logo)
                    <img src="{{ asset('storage/'.$company->logo) }}" alt="Logo {{ $company->name }}">
                @else
                    <img src="{{ asset('assets/images/logo.svg') }}" alt="Logo {{ $company?->name ?? config('app.name') }}">
                @endif
            </div>

            <div class="lp-company-info">
                <p class="lp-company-desc">
                    {{ $company?->description ?: 'Deskripsi perusahaan belum diisi. Super Admin dapat mengisinya melalui menu Profile Perusahaan.' }}
                </p>

                <ul class="lp-contact">
                    @if ($company?->address)
                        <li><i class="bi bi-geo-alt"></i><span>{{ $company->address }}</span></li>
                    @endif
                    @if ($company?->email)
                        <li><i class="bi bi-envelope"></i><a href="mailto:{{ $company->email }}">{{ $company->email }}</a></li>
                    @endif
                    @if ($company?->phone)
                        <li><i class="bi bi-telephone"></i><a href="tel:{{ preg_replace('/[^0-9+]/', '', $company->phone) }}">{{ $company->phone }}</a></li>
                    @endif
                    @if ($company?->website && preg_match('~^https?://~i', $company->website))
                        <li><i class="bi bi-globe2"></i><a href="{{ $company->website }}" target="_blank" rel="noopener noreferrer">{{ $company->website }}</a></li>
                    @endif
                </ul>
            </div>
        </div>
    </div>
</section>

{{-- ===== Footer ===== --}}
<footer class="lp-footer">
    <div class="container">
        <div class="row g-4 align-items-center">
            <div class="col-md-6">
                <a class="hb-brand" href="#beranda">
                    <img src="{{ asset('assets/images/logo.svg') }}" alt="" width="32" height="32">
                    <span>Hello BroSeven!</span>
                </a>
                <p class="lp-footer-text mt-2 mb-0">{{ $company?->name ?? config('app.name') }}@if ($company?->address) &middot; {{ $company->address }}@endif</p>
            </div>
            <div class="col-md-6 text-md-end">
                @if ($company?->email)
                    <div class="lp-footer-text"><i class="bi bi-envelope me-1"></i>{{ $company->email }}</div>
                @endif
                @if ($company?->phone)
                    <div class="lp-footer-text"><i class="bi bi-telephone me-1"></i>{{ $company->phone }}</div>
                @endif
            </div>
        </div>
        <hr class="lp-footer-hr">
        <div class="lp-footer-text text-center">&copy; {{ date('Y') }} Hello BroSeven!. Seluruh hak dilindungi.</div>
    </div>
</footer>

<script src="{{ asset('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('assets/js/landing.js') }}"></script>
</body>
</html>d
