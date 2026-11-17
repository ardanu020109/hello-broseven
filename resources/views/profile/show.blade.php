@extends('layouts.app')

@section('title', 'Profile Saya')
@section('page_title', 'Profile Saya')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/crud.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/settings.css') }}">
@endpush

@section('content')
@php
    $date = fn ($d) => $d ? $d->locale('id')->translatedFormat('d F Y') : '-';
    $dash = fn ($v) => ($v !== null && $v !== '') ? $v : '-';
    $ws = $resolved['schedule'] ?? null;
    $faceLabels = ['active' => ['Terdaftar', 'hb-st-present'], 'pending' => ['Menunggu persetujuan admin', 'hb-st-pending'], 'inactive' => ['Dinonaktifkan admin', 'hb-st-absent'], 'none' => ['Belum terdaftar', 'hb-st-pending']];
    $hasPasswordErrors = $errors->password->any();
@endphp

@if ($errors->any() && !$hasPasswordErrors)
    <div class="hb-alert hb-alert-error mb-3" role="alert">
        <i class="bi bi-exclamation-octagon-fill"></i>
        <span>Perubahan belum dapat disimpan. Periksa isian di bawah.</span>
    </div>
@endif

<div class="row g-3">
    {{-- ===== Kiri: identitas ===== --}}
    <div class="col-12 col-lg-4">
        <div class="hb-card hb-panel text-center mb-3">
            @if ($employee?->photo)
                <img src="{{ asset('storage/'.$employee->photo) }}" alt="Foto {{ $user->name }}" class="hb-profile-photo">
            @else
                <span class="hb-avatar hb-avatar-xl">{{ $user->initial() }}</span>
            @endif
            <h2 class="h5 mt-3 mb-1">{{ $user->name }}</h2>
            <span class="hb-role-badge">{{ $user->roleLabel() }}</span>
            @if ($employee)
                <div class="text-muted mt-2">{{ $employee->position?->name }}</div>
                <div class="small text-muted">{{ $employee->employee_number }}</div>
            @endif
            <dl class="hb-dl hb-dl-sm text-start mt-4 mb-0">
                <dt>Email</dt><dd>{{ $user->email }}</dd>
                <dt>Username</dt><dd>{{ $user->username }}</dd>
                <dt>Login terakhir</dt><dd>{{ $user->last_login_at ? $user->last_login_at->locale('id')->translatedFormat('d F Y H:i') : '-' }}</dd>
            </dl>
        </div>

        @if ($employee && $faceEnabled)
            <div class="hb-card hb-panel">
                <h2 class="hb-panel-title mb-3">Data Wajah</h2>
                <span class="hb-st {{ $faceLabels[$faceStatus][1] }}">{{ $faceLabels[$faceStatus][0] }}</span>
                @if (\Illuminate\Support\Facades\Route::has('employee.face'))
                    <div class="mt-3"><a href="{{ route('employee.face') }}" class="hb-btn hb-btn-light btn-sm">{{ $faceStatus === 'none' ? 'Daftarkan wajah' : 'Kelola data wajah' }}</a></div>
                @endif
            </div>
        @endif
    </div>

    {{-- ===== Kanan ===== --}}
    <div class="col-12 col-lg-8">

        @if ($employee)
            <div class="hb-card hb-panel mb-3">
                <h2 class="hb-panel-title mb-3">Data Pribadi</h2>
                <dl class="hb-dl hb-dl-wide mb-0">
                    <dt>Nama lengkap</dt><dd>{{ $employee->full_name }}</dd>
                    <dt>NIK KTP</dt><dd>{{ $dash($employee->national_id) }}</dd>
                    <dt>Tempat, tgl lahir</dt><dd>{{ $dash($employee->birth_place) }}, {{ $date($employee->birth_date) }}</dd>
                    <dt>Jenis kelamin</dt><dd>{{ ['L' => 'Laki-laki', 'P' => 'Perempuan'][$employee->gender] ?? '-' }}</dd>
                    <dt>Pendidikan</dt><dd>{{ $employee->educationLevel?->name ?? '-' }}</dd>
                </dl>
            </div>

            <div class="hb-card hb-panel mb-3">
                <h2 class="hb-panel-title mb-3">Informasi Pekerjaan</h2>
                <dl class="hb-dl hb-dl-wide mb-0">
                    <dt>Jabatan</dt><dd>{{ $employee->position?->name ?? '-' }}</dd>
                    <dt>Departemen</dt><dd>{{ $employee->department?->name ?? '-' }}</dd>
                    <dt>Divisi</dt><dd>{{ $employee->division?->name ?? '-' }}</dd>
                    <dt>Status karyawan</dt><dd>{{ $employee->employeeStatus?->name ?? '-' }}</dd>
                    <dt>Tanggal masuk</dt><dd>{{ $date($employee->join_date) }}</dd>
                    <dt>Atasan langsung</dt><dd>{{ $employee->supervisor?->full_name ?? '-' }}</dd>
                    <dt>Mode kerja</dt><dd>{{ $employee->work_mode }}</dd>
                </dl>
            </div>

            @if ($scheduleEnabled)
                <div class="hb-card hb-panel mb-3">
                    <h2 class="hb-panel-title mb-3">Jadwal Hari Ini</h2>
                    @if ($ws)
                        <dl class="hb-dl hb-dl-wide mb-0">
                            <dt>Jadwal</dt><dd>{{ $ws->name }}</dd>
                            <dt>Jam kerja</dt><dd>{{ substr((string) $ws->check_in_time, 0, 5) }} - {{ substr((string) $ws->check_out_time, 0, 5) }} ({{ $ws->timezone }})</dd>
                            <dt>Hari ini</dt>
                            <dd>
                                @if ($resolved['holiday_name']) Libur: {{ $resolved['holiday_name'] }}
                                @elseif ($resolved['is_working_day']) Hari kerja ({{ $resolved['work_mode'] }})
                                @else Bukan hari kerja @endif
                            </dd>
                        </dl>
                    @else
                        <div class="hb-empty"><i class="bi bi-calendar-x"></i><span>Belum ada jadwal yang berlaku. Hubungi administrator.</span></div>
                    @endif
                </div>
            @endif

            <form method="POST" enctype="multipart/form-data" action="{{ route('profile.update') }}" class="hb-card hb-form-section" data-hb-loading>
                @csrf
                @method('PUT')
                <h2><i class="bi bi-pencil-square"></i> Data yang Dapat Anda Ubah</h2>
                <p class="small text-muted">Nama, email, jabatan, dan data pekerjaan hanya dapat diubah administrator.</p>
                <div class="row g-3">
                    <x-form-field name="phone" label="Nomor Telepon">
                        <input type="tel" id="phone" name="phone" maxlength="30" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $employee->phone) }}">
                    </x-form-field>
                    <x-form-field name="photo" label="Foto Profil" help="JPG, PNG, atau WEBP. Maksimal 2 MB.">
                        <div class="d-flex align-items-center gap-3">
                            <img id="profilePhotoPreview" class="hb-thumb-lg" alt="Pratinjau foto" src="{{ $employee->photo ? asset('storage/'.$employee->photo) : '' }}" @if (!$employee->photo) hidden @endif>
                            <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp"
                                   class="form-control @error('photo') is-invalid @enderror" data-hb-preview="#profilePhotoPreview" data-max-kb="2048">
                        </div>
                    </x-form-field>
                    <x-form-field name="address" label="Alamat" col="col-12">
                        <textarea id="address" name="address" rows="2" maxlength="1000" class="form-control @error('address') is-invalid @enderror">{{ old('address', $employee->address) }}</textarea>
                    </x-form-field>
                    <x-form-field name="emergency_contact_name" label="Nama Kontak Darurat">
                        <input type="text" id="emergency_contact_name" name="emergency_contact_name" maxlength="255" class="form-control @error('emergency_contact_name') is-invalid @enderror" value="{{ old('emergency_contact_name', $employee->emergency_contact_name) }}">
                    </x-form-field>
                    <x-form-field name="emergency_contact_phone" label="Nomor Kontak Darurat">
                        <input type="tel" id="emergency_contact_phone" name="emergency_contact_phone" maxlength="30" class="form-control @error('emergency_contact_phone') is-invalid @enderror" value="{{ old('emergency_contact_phone', $employee->emergency_contact_phone) }}">
                    </x-form-field>
                    <div class="col-12 d-flex justify-content-end">
                        <button type="submit" class="hb-btn hb-btn-primary"><i class="bi bi-check2 me-1"></i> Simpan Perubahan</button>
                    </div>
                </div>
            </form>
        @elseif ($user->isEmployee())
            <div class="hb-alert hb-alert-info mb-3">
                <i class="bi bi-info-circle-fill"></i>
                <span>Akun Anda belum terhubung dengan data karyawan. Hubungi administrator.</span>
            </div>
        @else
            <form method="POST" action="{{ route('profile.update') }}" class="hb-card hb-form-section" data-hb-loading>
                @csrf
                @method('PUT')
                <h2><i class="bi bi-person-gear"></i> Data Akun</h2>
                <div class="row g-3">
                    <x-form-field name="name" label="Nama" required col="col-12">
                        <input type="text" id="name" name="name" maxlength="255" required class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $user->name) }}">
                    </x-form-field>
                    <x-form-field name="username" label="Username" required>
                        <input type="text" id="username" name="username" maxlength="50" required autocomplete="username" class="form-control @error('username') is-invalid @enderror" value="{{ old('username', $user->username) }}">
                    </x-form-field>
                    <x-form-field name="email" label="Email" required>
                        <input type="email" id="email" name="email" maxlength="255" required autocomplete="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $user->email) }}">
                    </x-form-field>
                    <div class="col-12 d-flex justify-content-end">
                        <button type="submit" class="hb-btn hb-btn-primary"><i class="bi bi-check2 me-1"></i> Simpan Perubahan</button>
                    </div>
                </div>
            </form>
        @endif

        {{-- ===== Ganti password ===== --}}
        <form method="POST" action="{{ route('profile.password') }}" class="hb-card hb-form-section" data-hb-loading>
            @csrf
            @method('PUT')
            <h2><i class="bi bi-shield-lock"></i> Ganti Password</h2>
            <p class="small text-muted">Setelah password diganti, sesi login Anda di perangkat lain diakhiri.</p>
            <div class="row g-3">
                <div class="col-12">
                    <label for="current_password" class="form-label fw-semibold">Password saat ini <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="password" id="current_password" name="current_password" maxlength="255" required autocomplete="current-password"
                               class="form-control @error('current_password', 'password') is-invalid @enderror">
                        <button type="button" class="btn btn-outline-secondary" data-hb-togglepass="#current_password" aria-label="Tampilkan password"><i class="bi bi-eye"></i></button>
                    </div>
                    @error('current_password', 'password') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label for="new_password" class="form-label fw-semibold">Password baru <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="password" id="new_password" name="password" minlength="8" maxlength="100" required autocomplete="new-password"
                               class="form-control @error('password', 'password') is-invalid @enderror">
                        <button type="button" class="btn btn-outline-secondary" data-hb-togglepass="#new_password" aria-label="Tampilkan password"><i class="bi bi-eye"></i></button>
                    </div>
                    @error('password', 'password') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    <div class="form-text">Minimal 8 karakter.</div>
                </div>
                <div class="col-md-6">
                    <label for="password_confirmation" class="form-label fw-semibold">Ulangi password baru <span class="text-danger">*</span></label>
                    <input type="password" id="password_confirmation" name="password_confirmation" minlength="8" maxlength="100" required autocomplete="new-password" class="form-control">
                </div>
                <div class="col-12 d-flex justify-content-end">
                    <button type="submit" class="hb-btn hb-btn-secondary"><i class="bi bi-key me-1"></i> Ganti Password</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/crud.js') }}"></script>
@endpush
