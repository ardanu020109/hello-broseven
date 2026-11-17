@extends('layouts.app')

@section('title', 'Data Jadwal')
@section('page_title', 'Data Jadwal')

@section('breadcrumb')
    <li class="breadcrumb-item">Data Jadwal</li>
    <li class="breadcrumb-item active" aria-current="page">Jadwal Kerja</li>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/crud.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/schedules.css') }}">
@endpush

@section('page_actions')
    <a href="{{ route('schedules.create') }}" class="hb-btn hb-btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Tambah Jadwal
    </a>
@endsection

@section('content')
@php
    $dayShort = [1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab', 7 => 'Min'];
    $filtered = request()->filled('q') || request()->filled('status') || request()->filled('type');
@endphp

@include('schedules.partials.tabs')

<div class="hb-alert hb-alert-info mb-3">
    <i class="bi bi-info-circle-fill"></i>
    <span>Jadwal dibuat di sini, lalu ditugaskan ke karyawan melalui tab <strong>Penugasan</strong>. Jadwal nonaktif tidak bisa dipilih untuk penugasan baru, tetapi penugasan yang sudah ada tetap berlaku. Shift dibuat sebagai jadwal terpisah (mis. Shift Pagi dan Shift Siang).</span>
</div>

<div class="hb-card hb-panel">
    <form method="GET" class="hb-filters">
        <div class="hb-search">
            <i class="bi bi-search"></i>
            <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Cari nama jadwal..." aria-label="Pencarian">
        </div>
        <select name="type" class="form-select" aria-label="Filter tipe">
            <option value="">Semua tipe</option>
            <option value="regular" @selected(request('type') === 'regular')>Reguler</option>
            <option value="shift" @selected(request('type') === 'shift')>Shift</option>
        </select>
        <select name="status" class="form-select" aria-label="Filter status">
            <option value="">Semua status</option>
            <option value="active" @selected(request('status') === 'active')>Aktif</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Nonaktif</option>
        </select>
        <button type="submit" class="hb-btn hb-btn-secondary"><i class="bi bi-funnel me-1"></i> Terapkan</button>
        @if ($filtered)
            <a href="{{ route('schedules.index') }}" class="hb-btn hb-btn-light">Reset</a>
        @endif
    </form>

    @if ($schedules->isEmpty())
        <div class="hb-empty">
            <i class="bi bi-calendar-x"></i>
            <span>{{ $filtered ? 'Tidak ada jadwal yang cocok dengan filter.' : 'Belum ada jadwal kerja.' }}</span>
        </div>
    @else
        <div class="table-responsive">
            <table class="table hb-table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Jadwal</th><th>Jam Kerja</th><th>Istirahat</th><th>Toleransi</th>
                        <th>Hari Kerja</th><th>Zona Waktu</th><th>Penugasan Aktif</th><th>Status</th><th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($schedules as $s)
                        @php($workDays = $s->days->where('is_working_day', true)->pluck('day_of_week')->all())
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $s->name }}</div>
                                <span class="hb-pill {{ $s->is_shift ? 'hb-pill-warning' : 'hb-pill-muted' }}">{{ $s->is_shift ? 'Shift' : 'Reguler' }}</span>
                            </td>
                            <td class="text-nowrap">{{ substr((string) $s->check_in_time, 0, 5) }} - {{ substr((string) $s->check_out_time, 0, 5) }}</td>
                            <td class="text-nowrap">
                                @if ($s->break_start && $s->break_end)
                                    {{ substr((string) $s->break_start, 0, 5) }} - {{ substr((string) $s->break_end, 0, 5) }}
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>{{ $s->late_tolerance_minutes }} menit</td>
                            <td>
                                <div class="hb-days">
                                    @foreach ($dayShort as $n => $lbl)
                                        <span class="hb-day {{ in_array($n, $workDays, true) ? 'is-on' : '' }}">{{ $lbl }}</span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="small">{{ $s->timezone }}</td>
                            <td>{{ $s->current_assignments }} karyawan</td>
                            <td><span class="hb-st {{ $s->is_active ? 'hb-st-present' : 'hb-st-absent' }}">{{ $s->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                            <td>
                                <div class="hb-actions">
                                    @if ($s->is_active)
                                        <a href="{{ route('schedules.assignments.index', ['assign' => $s->id]) }}" class="hb-icon-action" title="Tugaskan ke karyawan" aria-label="Tugaskan"><i class="bi bi-person-plus"></i></a>
                                    @endif
                                    <a href="{{ route('schedules.edit', $s->id) }}" class="hb-icon-action" title="Ubah" aria-label="Ubah"><i class="bi bi-pencil-square"></i></a>
                                    <button type="button" class="hb-icon-action" title="{{ $s->is_active ? 'Nonaktifkan' : 'Aktifkan' }}"
                                            aria-label="{{ $s->is_active ? 'Nonaktifkan' : 'Aktifkan' }}"
                                            data-bs-toggle="modal" data-bs-target="#hbConfirm"
                                            data-action="{{ route('schedules.toggle', $s->id) }}" data-method="PATCH"
                                            data-tone="primary" data-btn="{{ $s->is_active ? 'Nonaktifkan' : 'Aktifkan' }}"
                                            data-title="{{ $s->is_active ? 'Nonaktifkan' : 'Aktifkan' }} jadwal?"
                                            data-message="{{ $s->name }} akan {{ $s->is_active ? 'dinonaktifkan. Penugasan yang sudah ada tetap berlaku.' : 'diaktifkan kembali.' }}">
                                        <i class="bi {{ $s->is_active ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
                                    </button>
                                    <button type="button" class="hb-icon-action is-danger" title="Hapus" aria-label="Hapus"
                                            data-bs-toggle="modal" data-bs-target="#hbConfirm"
                                            data-action="{{ route('schedules.destroy', $s->id) }}" data-method="DELETE"
                                            data-tone="danger" data-btn="Hapus"
                                            data-title="Hapus jadwal?"
                                            data-message="{{ $s->name }} akan dihapus. Jadwal yang masih punya penugasan atau riwayat absensi tidak dapat dihapus.">
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

    <x-pagination :paginator="$schedules" />
</div>

<x-confirm-modal />
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/crud.js') }}"></script>
@endpush
