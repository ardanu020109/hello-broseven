@extends('layouts.app')

@section('title', 'Data Absensi')
@section('page_title', 'Data Absensi')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/crud.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/records.css') }}">
@endpush

@section('content')
@php
    $statusOptions = ['present' => 'Hadir', 'late' => 'Terlambat', 'absent' => 'Tidak Hadir', 'leave' => 'Izin'];
    $total = $counts->sum();
@endphp

<div class="hb-chips">
    <span class="hb-chip"><strong>{{ $total }}</strong> catatan</span>
    <span class="hb-chip hb-chip-present"><strong>{{ $counts['present'] ?? 0 }}</strong> hadir</span>
    <span class="hb-chip hb-chip-late"><strong>{{ $counts['late'] ?? 0 }}</strong> terlambat</span>
    <span class="hb-chip hb-chip-absent"><strong>{{ $counts['absent'] ?? 0 }}</strong> tidak hadir</span>
    <span class="hb-chip hb-chip-leave"><strong>{{ $counts['leave'] ?? 0 }}</strong> izin</span>
    <span class="hb-chip-note">Periode {{ $from->format('d/m/Y') }} - {{ $to->format('d/m/Y') }}</span>
</div>

<div class="hb-card hb-panel">
    <form method="GET" class="hb-filters">
        <div class="hb-search">
            <i class="bi bi-search"></i>
            <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Cari nama, NIK internal, email..." aria-label="Pencarian">
        </div>
        <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control" aria-label="Dari tanggal">
        <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control" aria-label="Sampai tanggal">
        <select name="employee_id" class="form-select" aria-label="Filter karyawan">
            <option value="">Semua karyawan</option>
            @foreach ($employees as $e)
                <option value="{{ $e->id }}" @selected((string) request('employee_id') === (string) $e->id)>{{ $e->full_name }} ({{ $e->employee_number }})</option>
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
            @foreach ($statusOptions as $value => $label)
                <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button type="submit" class="hb-btn hb-btn-secondary"><i class="bi bi-funnel me-1"></i> Terapkan</button>
        @if ($filtered)
            <a href="{{ route('attendance.index') }}" class="hb-btn hb-btn-light">Reset</a>
        @endif
    </form>

    @if ($records->isEmpty())
        <div class="hb-empty">
            <i class="bi bi-clipboard-x"></i>
            <span>{{ $filtered ? 'Tidak ada absensi yang cocok dengan filter.' : 'Belum ada data absensi pada periode ini.' }}</span>
        </div>
    @else
        <div class="table-responsive">
            <table class="table hb-table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Tanggal</th><th>Karyawan</th><th>Jabatan</th><th>Jadwal</th><th>Masuk</th><th>Pulang</th>
                        <th>Lokasi</th><th>Jarak</th><th>Status</th><th>Metode</th><th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($records as $a)
                        @php($loc = $a->checkInLocation)
                        <tr>
                            <td class="text-nowrap">
                                <div class="fw-semibold">{{ $a->attendance_date->format('d/m/Y') }}</div>
                                <div class="small text-muted">{{ $a->attendance_date->copy()->locale('id')->translatedFormat('l') }}</div>
                            </td>
                            <td>
                                <a href="{{ route('attendance.show', $a->id) }}" class="fw-semibold text-decoration-none">{{ $a->employee?->full_name ?? '-' }}</a>
                                <div class="small text-muted">{{ $a->employee?->employee_number }}@if ($a->employee?->trashed()) &middot; dihapus @endif</div>
                            </td>
                            <td>{{ $a->employee?->position?->name ?? '-' }}</td>
                            <td class="small">{{ $a->workSchedule?->name ?? '-' }}</td>
                            <td>{{ $a->check_in_at?->format('H:i') ?? '-' }}</td>
                            <td>{{ $a->check_out_at?->format('H:i') ?? '-' }}</td>
                            <td>
                                @if (!$loc)
                                    <span class="text-muted">-</span>
                                @elseif ($loc->is_within_radius === true)
                                    <span class="hb-pill hb-pill-success">Dalam radius</span>
                                @elseif ($loc->is_within_radius === false)
                                    <span class="hb-pill hb-pill-warning">Luar radius</span>
                                @else
                                    <span class="hb-pill hb-pill-muted">Tercatat</span>
                                @endif
                                <div class="small text-muted">{{ $a->work_mode }}</div>
                            </td>
                            <td class="text-nowrap">{{ $loc?->distance_meters !== null ? number_format((float) $loc->distance_meters, 0, ',', '.').' m' : '-' }}</td>
                            <td>
                                <x-status-badge :status="$a->status" />
                                @if ($a->late_minutes > 0)<div class="small text-muted">{{ $a->late_minutes }} mnt</div>@endif
                                @if ($a->is_corrected)<div class="small"><span class="hb-pill hb-pill-info">Dikoreksi</span></div>@endif
                            </td>
                            <td class="small">{{ \App\Support\AttendanceLabels::method($a->verification_method) }}</td>
                            <td>
                                <div class="hb-actions">
                                    <a href="{{ route('attendance.show', $a->id) }}" class="hb-icon-action" title="Detail dan koreksi" aria-label="Detail dan koreksi"><i class="bi bi-eye"></i></a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <x-pagination :paginator="$records" />
</div>
@endsection
