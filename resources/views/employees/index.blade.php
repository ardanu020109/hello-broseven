@extends('layouts.app')

@section('title', 'Data Karyawan')
@section('page_title', 'Data Karyawan')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/crud.css') }}">
@endpush

@section('page_actions')
    <a href="{{ route('employees.create') }}" class="hb-btn hb-btn-primary">
        <i class="bi bi-person-plus me-1"></i> Tambah Karyawan
    </a>
@endsection

@section('content')
@php($filtered = request()->filled('q') || request()->filled('position_id') || request()->filled('department_id') || request()->filled('status'))

<div class="hb-card hb-panel">
    <form method="GET" class="hb-filters">
        <div class="hb-search">
            <i class="bi bi-search"></i>
            <input type="search" name="q" value="{{ request('q') }}" class="form-control"
                   placeholder="Cari nama, NIK internal, atau email..." aria-label="Pencarian">
        </div>
        <select name="position_id" class="form-select" aria-label="Filter jabatan">
            <option value="">Semua jabatan</option>
            @foreach ($positions as $p)
                <option value="{{ $p->id }}" @selected((string) request('position_id') === (string) $p->id)>{{ $p->name }}</option>
            @endforeach
        </select>
        <select name="department_id" class="form-select" aria-label="Filter departemen">
            <option value="">Semua departemen</option>
            @foreach ($departments as $d)
                <option value="{{ $d->id }}" @selected((string) request('department_id') === (string) $d->id)>{{ $d->name }}</option>
            @endforeach
        </select>
        <select name="status" class="form-select" aria-label="Filter status">
            <option value="">Semua status</option>
            <option value="active" @selected(request('status') === 'active')>Aktif</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Nonaktif</option>
        </select>
        <button type="submit" class="hb-btn hb-btn-secondary"><i class="bi bi-funnel me-1"></i> Terapkan</button>
        @if ($filtered)
            <a href="{{ route('employees.index') }}" class="hb-btn hb-btn-light">Reset</a>
        @endif
    </form>

    @if ($employees->isEmpty())
        <div class="hb-empty">
            <i class="bi bi-people"></i>
            <span>{{ $filtered ? 'Tidak ada karyawan yang cocok dengan filter.' : 'Belum ada data karyawan.' }}</span>
        </div>
    @else
        <div class="table-responsive">
            <table class="table hb-table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Karyawan</th><th>Jabatan</th><th>Departemen</th><th>Status</th>
                        <th>Mode Kerja</th><th>Akun</th><th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($employees as $e)
                        <tr>
                            <td>
                                <div class="hb-emp-cell">
                                    @if ($e->photo)
                                        <img class="hb-thumb" src="{{ asset('storage/'.$e->photo) }}" alt="">
                                    @else
                                        <span class="hb-avatar">{{ mb_strtoupper(mb_substr($e->full_name, 0, 1)) }}</span>
                                    @endif
                                    <div>
                                        <a href="{{ route('employees.show', $e->id) }}" class="fw-semibold text-decoration-none">{{ $e->full_name }}</a>
                                        <div class="small text-muted">{{ $e->employee_number }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $e->position?->name ?? '-' }}</td>
                            <td>{{ $e->department?->name ?? '-' }}</td>
                            <td>{{ $e->employeeStatus?->name ?? '-' }}</td>
                            <td><span class="hb-pill hb-pill-info">{{ $e->work_mode }}</span></td>
                            <td><span class="hb-st {{ $e->is_active ? 'hb-st-present' : 'hb-st-absent' }}">{{ $e->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                            <td>
                                <div class="hb-actions">
                                    <a href="{{ route('employees.show', $e->id) }}" class="hb-icon-action" title="Detail" aria-label="Detail"><i class="bi bi-eye"></i></a>
                                    <a href="{{ route('employees.edit', $e->id) }}" class="hb-icon-action" title="Ubah" aria-label="Ubah"><i class="bi bi-pencil-square"></i></a>
                                    <button type="button" class="hb-icon-action" title="Reset password" aria-label="Reset password"
                                            data-bs-toggle="modal" data-bs-target="#hbResetPw"
                                            data-action="{{ route('employees.reset-password', $e->id) }}" data-name="{{ $e->full_name }}">
                                        <i class="bi bi-key"></i>
                                    </button>
                                    <button type="button" class="hb-icon-action" title="{{ $e->is_active ? 'Nonaktifkan' : 'Aktifkan' }}"
                                            aria-label="{{ $e->is_active ? 'Nonaktifkan' : 'Aktifkan' }}"
                                            data-bs-toggle="modal" data-bs-target="#hbConfirm"
                                            data-action="{{ route('employees.toggle-status', $e->id) }}" data-method="PATCH"
                                            data-tone="primary" data-btn="{{ $e->is_active ? 'Nonaktifkan' : 'Aktifkan' }}"
                                            data-title="{{ $e->is_active ? 'Nonaktifkan' : 'Aktifkan' }} karyawan?"
                                            data-message="{{ $e->full_name }} {{ $e->is_active ? 'tidak akan dapat login dan absen sampai diaktifkan kembali.' : 'akan dapat login kembali.' }}">
                                        <i class="bi {{ $e->is_active ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
                                    </button>
                                    <button type="button" class="hb-icon-action is-danger" title="Hapus" aria-label="Hapus"
                                            data-bs-toggle="modal" data-bs-target="#hbConfirm"
                                            data-action="{{ route('employees.destroy', $e->id) }}" data-method="DELETE"
                                            data-tone="danger" data-btn="Hapus"
                                            data-title="Hapus karyawan?"
                                            data-message="{{ $e->full_name }} akan dihapus dan akun loginnya dinonaktifkan. Riwayat absensi dan payroll tetap tersimpan.">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <x-pagination :paginator="$employees" />
</div>

<x-confirm-modal />
<x-reset-password-modal />
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/crud.js') }}"></script>
@endpush
