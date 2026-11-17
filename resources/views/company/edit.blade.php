@extends('layouts.app')

@section('title', 'Profile Perusahaan')
@section('page_title', 'Profile Perusahaan')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/crud.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/settings.css') }}">
@endpush

@section('content')
@php
    $val = fn (string $key, $default = null) => old($key, $company->{$key} ?? $default);
    $time = fn (?string $v) => $v ? substr($v, 0, 5) : null;
    $savedLat = $company->latitude !== null ? number_format((float) $company->latitude, 7, '.', '') : null;
    $savedLng = $company->longitude !== null ? number_format((float) $company->longitude, 7, '.', '') : null;
@endphp

@if ($errors->any())
    <div class="hb-alert hb-alert-error mb-3" role="alert">
        <i class="bi bi-exclamation-octagon-fill"></i>
        <span>Data belum dapat disimpan. Periksa {{ $errors->count() }} kesalahan pada formulir di bawah.</span>
    </div>
@endif

<div class="hb-card hb-panel co-saved mb-3">
    <div class="hb-panel-head">
        <h2 class="hb-panel-title">Titik Pusat Absensi (Tersimpan)</h2>
        @if ($company->hasCoordinates())
            <span class="hb-pill hb-pill-success">Koordinat sudah diatur</span>
        @else
            <span class="hb-pill hb-pill-warning">Koordinat belum diatur</span>
        @endif
    </div>
    @if ($company->hasCoordinates())
        <div class="row g-3">
            <div class="col-6 col-md-3"><div class="hb-mini"><span>Latitude</span><strong>{{ $savedLat }}</strong></div></div>
            <div class="col-6 col-md-3"><div class="hb-mini"><span>Longitude</span><strong>{{ $savedLng }}</strong></div></div>
            <div class="col-6 col-md-3"><div class="hb-mini"><span>Radius absensi</span><strong>{{ $company->attendance_radius_meters }} m</strong></div></div>
            <div class="col-6 col-md-3"><div class="hb-mini"><span>Batas akurasi GPS</span><strong>{{ $company->max_gps_accuracy_meters }} m</strong></div></div>
        </div>
        <a class="small d-inline-block mt-2" target="_blank" rel="noopener noreferrer"
           href="https://www.openstreetmap.org/?mlat={{ $savedLat }}&mlon={{ $savedLng }}#map=18/{{ $savedLat }}/{{ $savedLng }}">
            <i class="bi bi-geo-alt"></i> Lihat titik di peta
        </a>
    @else
        <p class="text-muted mb-0">Karyawan WFO tidak dapat absen sebelum koordinat kantor diatur. Atur di bagian Lokasi dan Absensi di bawah.</p>
    @endif
</div>

<form method="POST" enctype="multipart/form-data" action="{{ route('company.update') }}" data-hb-loading>
    @csrf
    @method('PUT')

    {{-- ===== Identitas ===== --}}
    <section class="hb-card hb-form-section">
        <h2><i class="bi bi-buildings"></i> Identitas Perusahaan</h2>
        <div class="row g-3">
            <x-form-field name="name" label="Nama Perusahaan" required>
                <input type="text" id="name" name="name" maxlength="255" required
                       class="form-control @error('name') is-invalid @enderror" value="{{ $val('name') }}">
            </x-form-field>
            <x-form-field name="logo" label="Logo" help="JPG, PNG, atau WEBP. Maksimal 2 MB.">
                <div class="d-flex align-items-center gap-3">
                    <img id="logoPreview" class="hb-thumb-lg" alt="Logo perusahaan"
                         src="{{ $company->logo ? asset('storage/'.$company->logo) : '' }}" @if (!$company->logo) hidden @endif>
                    <input type="file" id="logo" name="logo" accept="image/jpeg,image/png,image/webp"
                           class="form-control @error('logo') is-invalid @enderror" data-hb-preview="#logoPreview" data-max-kb="2048">
                </div>
                @if ($company->logo)
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" name="remove_logo" id="remove_logo" value="1" {{ old('remove_logo') ? 'checked' : '' }}>
                        <label class="form-check-label small" for="remove_logo">Hapus logo saat ini (jika tidak mengunggah logo baru)</label>
                    </div>
                @endif
            </x-form-field>
            <x-form-field name="description" label="Deskripsi" col="col-12" help="Tampil di bagian Tentang Perusahaan pada landing page.">
                <textarea id="description" name="description" rows="3" maxlength="2000"
                          class="form-control @error('description') is-invalid @enderror">{{ $val('description') }}</textarea>
            </x-form-field>
            <x-form-field name="address" label="Alamat" col="col-12">
                <textarea id="address" name="address" rows="2" maxlength="1000"
                          class="form-control @error('address') is-invalid @enderror">{{ $val('address') }}</textarea>
            </x-form-field>
            <x-form-field name="email" label="Email" col="col-md-4">
                <input type="email" id="email" name="email" maxlength="255"
                       class="form-control @error('email') is-invalid @enderror" value="{{ $val('email') }}">
            </x-form-field>
            <x-form-field name="phone" label="Nomor Telepon" col="col-md-4">
                <input type="tel" id="phone" name="phone" maxlength="30"
                       class="form-control @error('phone') is-invalid @enderror" value="{{ $val('phone') }}">
            </x-form-field>
            <x-form-field name="website" label="Website" col="col-md-4" help="Diawali http:// atau https://.">
                <input type="url" id="website" name="website" maxlength="255"
                       class="form-control @error('website') is-invalid @enderror" value="{{ $val('website') }}" placeholder="https://contoh.com">
            </x-form-field>
        </div>
    </section>

    {{-- ===== Lokasi dan absensi ===== --}}
    <section class="hb-card hb-form-section">
        <h2><i class="bi bi-geo-alt"></i> Lokasi dan Absensi</h2>
        <p class="small text-muted">Koordinat menjadi pusat geofence absensi untuk karyawan WFO. Pemeriksaan jarak dilakukan ulang di server.</p>
        <div class="row g-3">
            <x-form-field name="latitude" label="Latitude" col="col-md-4">
                <input type="number" id="latitude" name="latitude" step="any" min="-90" max="90"
                       class="form-control @error('latitude') is-invalid @enderror" value="{{ $val('latitude') !== null ? $val('latitude') : '' }}">
            </x-form-field>
            <x-form-field name="longitude" label="Longitude" col="col-md-4">
                <input type="number" id="longitude" name="longitude" step="any" min="-180" max="180"
                       class="form-control @error('longitude') is-invalid @enderror" value="{{ $val('longitude') !== null ? $val('longitude') : '' }}">
            </x-form-field>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Ambil dari lokasi saya</label>
                <button type="button" class="hb-btn hb-btn-secondary w-100" id="btnGeo"><i class="bi bi-crosshair me-1"></i> Gunakan lokasi saya</button>
            </div>
            <div class="col-12">
                <div class="small geo-info" id="geoStatus" role="status" aria-live="polite">
                    Buka halaman ini dari perangkat yang berada di kantor. Laptop tanpa GPS hanya memberi perkiraan (akurasi ratusan meter), jadi HP lebih tepat. Anda juga dapat mengisi manual dari Google Maps.
                </div>
            </div>

            <x-form-field name="attendance_radius_meters" label="Radius Absensi (meter)" required col="col-md-4" help="10 - 5000 meter.">
                <input type="number" id="attendance_radius_meters" name="attendance_radius_meters" min="10" max="5000" step="1" required
                       class="form-control @error('attendance_radius_meters') is-invalid @enderror" value="{{ $val('attendance_radius_meters', 100) }}">
            </x-form-field>
            <x-form-field name="max_gps_accuracy_meters" label="Batas Akurasi GPS (meter)" required col="col-md-4" help="Lokasi dengan akurasi lebih buruk ditolak. 5 - 500 meter.">
                <input type="number" id="max_gps_accuracy_meters" name="max_gps_accuracy_meters" min="5" max="500" step="1" required
                       class="form-control @error('max_gps_accuracy_meters') is-invalid @enderror" value="{{ $val('max_gps_accuracy_meters', 50) }}">
            </x-form-field>
        </div>
    </section>

    {{-- ===== Verifikasi dan absent ===== --}}
    <section class="hb-card hb-form-section">
        <h2><i class="bi bi-person-bounding-box"></i> Verifikasi Wajah dan Absent</h2>
        <div class="row g-3">
            <x-form-field name="face_match_threshold" label="Ambang Kecocokan Wajah" required col="col-md-6"
                          help="Jarak maksimum antara wajah terdaftar dan wajah saat absen (0,30 - 0,80). Makin kecil makin ketat; bawaan 0,50. Terlalu ketat membuat karyawan sah sering ditolak.">
                <input type="number" id="face_match_threshold" name="face_match_threshold" min="0.30" max="0.80" step="0.01" required
                       class="form-control @error('face_match_threshold') is-invalid @enderror"
                       value="{{ old('face_match_threshold', number_format((float) ($company->face_match_threshold ?? 0.5), 2, '.', '')) }}">
            </x-form-field>
            <x-form-field name="absent_cutoff_minutes" label="Batas Waktu Absent (menit setelah jam pulang)" required col="col-md-6"
                          help="Karyawan tanpa absensi ditandai Tidak Hadir setelah jam pulang jadwal ditambah menit ini. 0 - 720.">
                <input type="number" id="absent_cutoff_minutes" name="absent_cutoff_minutes" min="0" max="720" step="1" required
                       class="form-control @error('absent_cutoff_minutes') is-invalid @enderror" value="{{ $val('absent_cutoff_minutes', 120) }}">
            </x-form-field>
        </div>
    </section>

    {{-- ===== Waktu ===== --}}
    <section class="hb-card hb-form-section">
        <h2><i class="bi bi-clock"></i> Zona Waktu dan Jam Operasional</h2>
        <div class="row g-3">
            <x-form-field name="timezone" label="Zona Waktu" required col="col-md-4">
                <select id="timezone" name="timezone" required class="form-select @error('timezone') is-invalid @enderror">
                    @foreach ($timezones as $value => $label)
                        <option value="{{ $value }}" @selected($val('timezone', 'Asia/Makassar') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-form-field>
            <x-form-field name="operating_start" label="Jam Buka" col="col-md-4 col-6">
                <input type="time" id="operating_start" name="operating_start"
                       class="form-control @error('operating_start') is-invalid @enderror"
                       value="{{ old('operating_start', $time($company->operating_start)) }}">
            </x-form-field>
            <x-form-field name="operating_end" label="Jam Tutup" col="col-md-4 col-6">
                <input type="time" id="operating_end" name="operating_end"
                       class="form-control @error('operating_end') is-invalid @enderror"
                       value="{{ old('operating_end', $time($company->operating_end)) }}">
            </x-form-field>
        </div>
    </section>

    <div class="d-flex gap-2 justify-content-end mb-4">
        <button type="submit" class="hb-btn hb-btn-primary"><i class="bi bi-check2 me-1"></i> Simpan Profile Perusahaan</button>
    </div>
</form>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/crud.js') }}"></script>
    <script src="{{ asset('assets/js/company.js') }}"></script>
@endpush
