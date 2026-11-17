@extends('layouts.app')

@php
    $isEdit = $employee !== null;
    $title = $isEdit ? 'Ubah Karyawan' : 'Tambah Karyawan';
    $val = fn (string $key, $default = '') => old($key, $employee?->{$key} ?? $default);
    $isActive = old('is_active', $isEdit ? ($employee->is_active ? '1' : '0') : '1');
@endphp

@section('title', $title)
@section('page_title', $title)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('employees.index') }}">Data Karyawan</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $title }}</li>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/crud.css') }}">
@endpush

@section('content')
@if ($errors->any())
    <div class="hb-alert hb-alert-error mb-3" role="alert">
        <i class="bi bi-exclamation-octagon-fill"></i>
        <span>Data belum dapat disimpan. Periksa {{ $errors->count() }} kesalahan pada formulir di bawah.</span>
    </div>
@endif

<form method="POST" enctype="multipart/form-data" data-hb-loading
      action="{{ $isEdit ? route('employees.update', $employee->id) : route('employees.store') }}">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    {{-- ===== Identitas ===== --}}
    <section class="hb-card hb-form-section">
        <h2><i class="bi bi-person-vcard"></i> Identitas</h2>
        <div class="row g-3">
            <x-form-field name="employee_number" label="NIK Internal" required>
                <input type="text" id="employee_number" name="employee_number" maxlength="30" required
                       class="form-control @error('employee_number') is-invalid @enderror"
                       value="{{ old('employee_number', $isEdit ? $employee->employee_number : $suggestedNumber) }}">
            </x-form-field>
            <x-form-field name="full_name" label="Nama Lengkap" required>
                <input type="text" id="full_name" name="full_name" maxlength="255" required
                       class="form-control @error('full_name') is-invalid @enderror" value="{{ $val('full_name') }}">
            </x-form-field>
            <x-form-field name="national_id" label="NIK KTP" help="16 digit angka.">
                <input type="text" id="national_id" name="national_id" inputmode="numeric" maxlength="16"
                       class="form-control @error('national_id') is-invalid @enderror" value="{{ $val('national_id') }}">
            </x-form-field>
            <x-form-field name="gender" label="Jenis Kelamin">
                <select id="gender" name="gender" class="form-select @error('gender') is-invalid @enderror">
                    <option value="">- Pilih -</option>
                    <option value="L" @selected($val('gender') === 'L')>Laki-laki</option>
                    <option value="P" @selected($val('gender') === 'P')>Perempuan</option>
                </select>
            </x-form-field>
            <x-form-field name="birth_place" label="Tempat Lahir">
                <input type="text" id="birth_place" name="birth_place" maxlength="100"
                       class="form-control @error('birth_place') is-invalid @enderror" value="{{ $val('birth_place') }}">
            </x-form-field>
            <x-form-field name="birth_date" label="Tanggal Lahir">
                <input type="date" id="birth_date" name="birth_date" max="{{ now()->subDay()->toDateString() }}"
                       class="form-control @error('birth_date') is-invalid @enderror"
                       value="{{ old('birth_date', $employee?->birth_date?->format('Y-m-d')) }}">
            </x-form-field>
            <x-form-field name="phone" label="Nomor Telepon">
                <input type="tel" id="phone" name="phone" maxlength="30"
                       class="form-control @error('phone') is-invalid @enderror" value="{{ $val('phone') }}">
            </x-form-field>
            <x-form-field name="photo" label="Foto Profil" help="JPG, PNG, atau WEBP. Maksimal 2 MB.">
                <div class="d-flex align-items-center gap-3">
                    <img id="photoPreview" class="hb-thumb-lg" alt="Pratinjau foto"
                         src="{{ $employee?->photo ? asset('storage/'.$employee->photo) : '' }}" @if (!$employee?->photo) hidden @endif>
                    <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp"
                           class="form-control @error('photo') is-invalid @enderror"
                           data-hb-preview="#photoPreview" data-max-kb="2048">
                </div>
            </x-form-field>
            <x-form-field name="address" label="Alamat" col="col-12">
                <textarea id="address" name="address" rows="2" maxlength="1000"
                          class="form-control @error('address') is-invalid @enderror">{{ $val('address') }}</textarea>
            </x-form-field>
        </div>
    </section>

    {{-- ===== Pekerjaan ===== --}}
    <section class="hb-card hb-form-section">
        <h2><i class="bi bi-briefcase"></i> Informasi Pekerjaan</h2>
        <div class="row g-3">
            <x-form-field name="position_id" label="Jabatan" required>
                <select id="position_id" name="position_id" required class="form-select @error('position_id') is-invalid @enderror">
                    <option value="">- Pilih -</option>
                    @foreach ($options['positions'] as $o)
                        <option value="{{ $o->id }}" @selected((string) $val('position_id') === (string) $o->id)>{{ $o->name }}</option>
                    @endforeach
                </select>
            </x-form-field>
            <x-form-field name="employee_status_id" label="Status Karyawan" required>
                <select id="employee_status_id" name="employee_status_id" required class="form-select @error('employee_status_id') is-invalid @enderror">
                    <option value="">- Pilih -</option>
                    @foreach ($options['statuses'] as $o)
                        <option value="{{ $o->id }}" @selected((string) $val('employee_status_id') === (string) $o->id)>{{ $o->name }}</option>
                    @endforeach
                </select>
            </x-form-field>
            <x-form-field name="department_id" label="Departemen" required>
                <select id="department_id" name="department_id" required class="form-select @error('department_id') is-invalid @enderror">
                    <option value="">- Pilih -</option>
                    @foreach ($options['departments'] as $o)
                        <option value="{{ $o->id }}" @selected((string) $val('department_id') === (string) $o->id)>{{ $o->name }}</option>
                    @endforeach
                </select>
            </x-form-field>
            <x-form-field name="division_id" label="Divisi" help="Pilihan divisi mengikuti departemen.">
                <select id="division_id" name="division_id" class="form-select @error('division_id') is-invalid @enderror">
                    <option value="">- Tanpa divisi -</option>
                    @foreach ($options['divisions'] as $o)
                        <option value="{{ $o->id }}" data-dept="{{ $o->department_id }}" @selected((string) $val('division_id') === (string) $o->id)>{{ $o->name }}</option>
                    @endforeach
                </select>
            </x-form-field>
            <x-form-field name="join_date" label="Tanggal Masuk" required>
                <input type="date" id="join_date" name="join_date" required
                       class="form-control @error('join_date') is-invalid @enderror"
                       value="{{ old('join_date', $employee?->join_date?->format('Y-m-d') ?? now()->toDateString()) }}">
            </x-form-field>
            <x-form-field name="supervisor_id" label="Atasan Langsung">
                <select id="supervisor_id" name="supervisor_id" class="form-select @error('supervisor_id') is-invalid @enderror">
                    <option value="">- Tanpa atasan -</option>
                    @foreach ($options['supervisors'] as $o)
                        @continue($employee && $employee->id === $o->id)
                        <option value="{{ $o->id }}" @selected((string) $val('supervisor_id') === (string) $o->id)>{{ $o->full_name }} ({{ $o->employee_number }})</option>
                    @endforeach
                </select>
            </x-form-field>
            <x-form-field name="work_mode" label="Mode Kerja" required
                          help="WFH: radius kantor tidak diwajibkan, wajah tetap diverifikasi. Hybrid: mengikuti mode per hari pada jadwal (diatur di Fase 5).">
                <select id="work_mode" name="work_mode" required class="form-select @error('work_mode') is-invalid @enderror">
                    @foreach (['WFO' => 'WFO (Work From Office)', 'WFH' => 'WFH (Work From Home)', 'Hybrid' => 'Hybrid'] as $k => $label)
                        <option value="{{ $k }}" @selected($val('work_mode', 'WFO') === $k)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-form-field>
            <x-form-field name="education_level_id" label="Pendidikan Terakhir">
                <select id="education_level_id" name="education_level_id" class="form-select @error('education_level_id') is-invalid @enderror">
                    <option value="">- Pilih -</option>
                    @foreach ($options['education'] as $o)
                        <option value="{{ $o->id }}" @selected((string) $val('education_level_id') === (string) $o->id)>{{ $o->name }}</option>
                    @endforeach
                </select>
            </x-form-field>
        </div>
    </section>

    {{-- ===== Akun ===== --}}
    <section class="hb-card hb-form-section">
        <h2><i class="bi bi-shield-lock"></i> Akun Login</h2>
        <div class="row g-3">
            <x-form-field name="email" label="Email (untuk login)" required>
                <input type="email" id="email" name="email" maxlength="255" required autocomplete="off"
                       class="form-control @error('email') is-invalid @enderror"
                       value="{{ old('email', $employee?->email ?? $employee?->user?->email) }}">
            </x-form-field>
            <x-form-field name="username" label="Username" required help="Huruf, angka, titik, garis bawah, atau strip.">
                <input type="text" id="username" name="username" maxlength="50" required autocomplete="off"
                       class="form-control @error('username') is-invalid @enderror"
                       value="{{ old('username', $employee?->user?->username) }}">
            </x-form-field>

            @if ($isEdit)
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Password</label>
                    <div class="form-control-plaintext text-muted small">
                        Password tidak diubah dari sini. Gunakan tombol <i class="bi bi-key"></i> Reset Password pada daftar atau halaman detail karyawan.
                    </div>
                </div>
            @else
                <x-form-field name="password" label="Password Awal" required help="Minimal 8 karakter. Sampaikan kepada karyawan.">
                    <div class="input-group">
                        <input type="password" id="password" name="password" minlength="8" maxlength="100" required autocomplete="new-password"
                               class="form-control @error('password') is-invalid @enderror">
                        <button type="button" class="btn btn-outline-secondary" data-hb-togglepass="#password" aria-label="Tampilkan password"><i class="bi bi-eye"></i></button>
                        <button type="button" class="btn btn-outline-secondary" data-hb-genpass="#password">Buat acak</button>
                    </div>
                </x-form-field>
            @endif

            <div class="col-md-3">
                <label class="form-label fw-semibold">Role</label>
                <input type="text" class="form-control" value="Karyawan" disabled>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <input type="hidden" name="is_active" value="0">
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" {{ $isActive ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_active">Akun aktif</label>
                </div>
            </div>
        </div>
    </section>

    {{-- ===== Tambahan ===== --}}
    <section class="hb-card hb-form-section">
        <h2><i class="bi bi-telephone-inbound"></i> Kontak Darurat</h2>
        <div class="row g-3">
            <x-form-field name="emergency_contact_name" label="Nama Kontak Darurat">
                <input type="text" id="emergency_contact_name" name="emergency_contact_name" maxlength="255"
                       class="form-control @error('emergency_contact_name') is-invalid @enderror" value="{{ $val('emergency_contact_name') }}">
            </x-form-field>
            <x-form-field name="emergency_contact_phone" label="Nomor Kontak Darurat">
                <input type="tel" id="emergency_contact_phone" name="emergency_contact_phone" maxlength="30"
                       class="form-control @error('emergency_contact_phone') is-invalid @enderror" value="{{ $val('emergency_contact_phone') }}">
            </x-form-field>
        </div>
    </section>

    {{-- ===== Dokumen ===== --}}
    <section class="hb-card hb-form-section">
        <h2><i class="bi bi-folder2-open"></i> Dokumen</h2>
        <p class="small text-muted">Foto KTP dan dokumen pendukung disimpan privat dan hanya dapat dibuka Admin atau Super Admin. Mengunggah berkas baru menggantikan berkas lama.</p>
        <div class="row g-3">
            <x-form-field name="ktp_photo" label="Foto KTP" help="JPG, PNG, atau WEBP. Maksimal 2 MB.">
                <input type="file" id="ktp_photo" name="ktp_photo" accept="image/jpeg,image/png,image/webp"
                       class="form-control @error('ktp_photo') is-invalid @enderror" data-max-kb="2048">
                @if ($employee?->ktp_photo)
                    <a class="small d-inline-block mt-1" target="_blank" rel="noopener" href="{{ route('employees.file', [$employee->id, 'ktp']) }}">
                        <i class="bi bi-paperclip"></i> Lihat berkas saat ini
                    </a>
                @endif
            </x-form-field>
            <x-form-field name="supporting_document" label="Dokumen Pendukung" help="PDF, JPG, atau PNG. Maksimal 5 MB.">
                <input type="file" id="supporting_document" name="supporting_document" accept="application/pdf,image/jpeg,image/png"
                       class="form-control @error('supporting_document') is-invalid @enderror" data-max-kb="5120">
                @if ($employee?->supporting_document)
                    <a class="small d-inline-block mt-1" target="_blank" rel="noopener" href="{{ route('employees.file', [$employee->id, 'document']) }}">
                        <i class="bi bi-paperclip"></i> Lihat berkas saat ini
                    </a>
                @endif
            </x-form-field>
        </div>
    </section>

    <div class="d-flex gap-2 justify-content-end mb-4">
        <a href="{{ $isEdit ? route('employees.show', $employee->id) : route('employees.index') }}" class="hb-btn hb-btn-light">Batal</a>
        <button type="submit" class="hb-btn hb-btn-primary"><i class="bi bi-check2 me-1"></i> {{ $isEdit ? 'Simpan Perubahan' : 'Simpan Karyawan' }}</button>
    </div>
</form>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/crud.js') }}"></script>
@endpush
