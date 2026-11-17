@extends('layouts.app')

@section('title', $employee->full_name)
@section('page_title', 'Detail Karyawan')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('employees.index') }}">Data Karyawan</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $employee->full_name }}</li>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/crud.css') }}">
@endpush

@section('page_actions')
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('employees.edit', $employee->id) }}" class="hb-btn hb-btn-primary"><i class="bi bi-pencil-square me-1"></i> Ubah</a>
        <button type="button" class="hb-btn hb-btn-secondary" data-bs-toggle="modal" data-bs-target="#hbResetPw"
                data-action="{{ route('employees.reset-password', $employee->id) }}" data-name="{{ $employee->full_name }}">
            <i class="bi bi-key me-1"></i> Reset Password
        </button>
        <a href="{{ route('employees.index') }}" class="hb-btn hb-btn-light">Kembali</a>
    </div>
@endsection

@section('content')
@php
    $date = fn ($d) => $d ? $d->locale('id')->translatedFormat('d F Y') : '-';
    $dash = fn ($v) => ($v !== null && $v !== '') ? $v : '-';
@endphp

<div class="row g-3">
    {{-- Kolom kiri --}}
    <div class="col-12 col-lg-4">
        <div class="hb-card hb-panel text-center mb-3">
            @if ($employee->photo)
                <img src="{{ asset('storage/'.$employee->photo) }}" alt="Foto {{ $employee->full_name }}" class="hb-profile-photo">
            @else
                <span class="hb-avatar hb-avatar-xl">{{ mb_strtoupper(mb_substr($employee->full_name, 0, 1)) }}</span>
            @endif
            <h2 class="h5 mt-3 mb-1">{{ $employee->full_name }}</h2>
            <div class="text-muted">{{ $employee->position?->name }}</div>
            <div class="mt-2 d-flex gap-2 justify-content-center flex-wrap">
                <span class="hb-role-badge">{{ $employee->employee_number }}</span>
                <span class="hb-st {{ $employee->is_active ? 'hb-st-present' : 'hb-st-absent' }}">{{ $employee->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                <span class="hb-pill hb-pill-info">{{ $employee->work_mode }}</span>
            </div>
            <div class="d-grid gap-2 mt-4">
                <button type="button" class="hb-btn hb-btn-light"
                        data-bs-toggle="modal" data-bs-target="#hbConfirm"
                        data-action="{{ route('employees.toggle-status', $employee->id) }}" data-method="PATCH"
                        data-tone="primary" data-btn="{{ $employee->is_active ? 'Nonaktifkan' : 'Aktifkan' }}"
                        data-title="{{ $employee->is_active ? 'Nonaktifkan' : 'Aktifkan' }} karyawan?"
                        data-message="{{ $employee->full_name }} {{ $employee->is_active ? 'tidak akan dapat login dan absen.' : 'akan dapat login kembali.' }}">
                    <i class="bi {{ $employee->is_active ? 'bi-toggle-on' : 'bi-toggle-off' }} me-1"></i>
                    {{ $employee->is_active ? 'Nonaktifkan Karyawan' : 'Aktifkan Karyawan' }}
                </button>
                <button type="button" class="hb-btn hb-btn-danger"
                        data-bs-toggle="modal" data-bs-target="#hbConfirm"
                        data-action="{{ route('employees.destroy', $employee->id) }}" data-method="DELETE"
                        data-tone="danger" data-btn="Hapus"
                        data-title="Hapus karyawan?"
                        data-message="{{ $employee->full_name }} akan dihapus dan akun loginnya dinonaktifkan. Riwayat absensi dan payroll tetap tersimpan.">
                    <i class="bi bi-trash3 me-1"></i> Hapus Karyawan
                </button>
            </div>
        </div>

        <div class="hb-card hb-panel mb-3">
            <h2 class="hb-panel-title mb-3">Data Wajah</h2>
            @if ($hasFace)
                <span class="hb-st hb-st-present"><i class="bi bi-check-circle"></i> Wajah terdaftar</span>
            @else
                <span class="hb-st hb-st-pending"><i class="bi bi-hourglass-split"></i> Belum terdaftar</span>
                <p class="small text-muted mt-2 mb-0">Karyawan mendaftarkan wajah sendiri melalui halaman absensi (Fase 6).</p>
            @endif
        </div>

        <div class="hb-card hb-panel">
            <h2 class="hb-panel-title mb-3">Dokumen</h2>
            <ul class="hb-doc-list">
                <li>
                    <i class="bi bi-person-vcard"></i> Foto KTP
                    @if ($employee->ktp_photo)
                        <a href="{{ route('employees.file', [$employee->id, 'ktp']) }}" target="_blank" rel="noopener">Buka</a>
                    @else
                        <span class="text-muted">belum ada</span>
                    @endif
                </li>
                <li>
                    <i class="bi bi-file-earmark-text"></i> Dokumen pendukung
                    @if ($employee->supporting_document)
                        <a href="{{ route('employees.file', [$employee->id, 'document']) }}" target="_blank" rel="noopener">Buka</a>
                    @else
                        <span class="text-muted">belum ada</span>
                    @endif
                </li>
            </ul>
        </div>
    </div>

    {{-- Kolom kanan --}}
    <div class="col-12 col-lg-8">
        <div class="hb-card hb-panel mb-3">
            <h2 class="hb-panel-title mb-3">Identitas</h2>
            <dl class="hb-dl hb-dl-wide mb-0">
                <dt>NIK KTP</dt><dd>{{ $dash($employee->national_id) }}</dd>
                <dt>Tempat, tgl lahir</dt><dd>{{ $dash($employee->birth_place) }}, {{ $date($employee->birth_date) }}</dd>
                <dt>Jenis kelamin</dt><dd>{{ ['L' => 'Laki-laki', 'P' => 'Perempuan'][$employee->gender] ?? '-' }}</dd>
                <dt>Telepon</dt><dd>{{ $dash($employee->phone) }}</dd>
                <dt>Alamat</dt><dd>{{ $dash($employee->address) }}</dd>
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
                <dt>Atasan langsung</dt>
                <dd>
                    @if ($employee->supervisor)
                        <a href="{{ route('employees.show', $employee->supervisor->id) }}">{{ $employee->supervisor->full_name }}</a>
                    @else - @endif
                </dd>
                <dt>Jumlah bawahan</dt><dd>{{ $employee->subordinates_count }}</dd>
                <dt>Mode kerja</dt><dd>{{ $employee->work_mode }}</dd>
            </dl>
        </div>

        <div class="hb-card hb-panel mb-3">
            <h2 class="hb-panel-title mb-3">Jadwal Hari Ini</h2>
            @if ($resolved)
                <dl class="hb-dl hb-dl-wide mb-0">
                    <dt>Jadwal</dt><dd>{{ $resolved['schedule']->name }}</dd>
                    <dt>Jam kerja</dt><dd>{{ substr((string) $resolved['schedule']->check_in_time, 0, 5) }} - {{ substr((string) $resolved['schedule']->check_out_time, 0, 5) }}</dd>
                    <dt>Hari ini</dt>
                    <dd>
                        @if ($resolved['holiday_name']) Libur: {{ $resolved['holiday_name'] }}
                        @elseif ($resolved['is_working_day']) Hari kerja ({{ $resolved['work_mode'] }})
                        @else Bukan hari kerja @endif
                    </dd>
                </dl>
            @else
                <div class="hb-empty"><i class="bi bi-calendar-x"></i><span>Belum ada jadwal yang ditugaskan. Penugasan jadwal dibuat di Fase 5.</span></div>
            @endif
        </div>

        <div class="hb-card hb-panel mb-3">
            <h2 class="hb-panel-title mb-3">Akun dan Kontak Darurat</h2>
            <dl class="hb-dl hb-dl-wide mb-0">
                <dt>Email login</dt><dd>{{ $employee->user?->email ?? '-' }}</dd>
                <dt>Username</dt><dd>{{ $employee->user?->username ?? '-' }}</dd>
                <dt>Role</dt><dd>Karyawan</dd>
                <dt>Login terakhir</dt><dd>{{ $employee->user?->last_login_at ? $employee->user->last_login_at->locale('id')->translatedFormat('d F Y H:i') : 'Belum pernah' }}</dd>
                <dt>Kontak darurat</dt><dd>{{ $dash($employee->emergency_contact_name) }} {{ $employee->emergency_contact_phone ? '('.$employee->emergency_contact_phone.')' : '' }}</dd>
            </dl>
        </div>

        <div class="hb-card hb-panel">
            <h2 class="hb-panel-title mb-3">Absensi Terakhir</h2>
            @if ($recent->isEmpty())
                <div class="hb-empty"><i class="bi bi-clipboard-x"></i><span>Belum ada data absensi</span></div>
            @else
                <div class="table-responsive">
                    <table class="table hb-table align-middle mb-0">
                        <thead><tr><th>Tanggal</th><th>Masuk</th><th>Pulang</th><th>Status</th></tr></thead>
                        <tbody>
                            @foreach ($recent as $a)
                                <tr>
                                    <td>{{ $a->attendance_date->format('d/m/Y') }}</td>
                                    <td>{{ $a->check_in_at?->format('H:i') ?? '-' }}</td>
                                    <td>{{ $a->check_out_at?->format('H:i') ?? '-' }}</td>
                                    <td><x-status-badge :status="$a->status" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>

<x-confirm-modal />
<x-reset-password-modal />
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/crud.js') }}"></script>
@endpush
