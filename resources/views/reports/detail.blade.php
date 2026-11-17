@extends('layouts.app')

@section('title', 'Detail Laporan')
@section('page_title', 'Detail Laporan Kehadiran')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ $back }}">Laporan Kehadiran</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $employee->full_name }}</li>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/crud.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/records.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/reports.css') }}">
@endpush

@section('page_actions')
    <a href="{{ $back }}" class="hb-btn hb-btn-light"><i class="bi bi-arrow-left me-1"></i> Kembali</a>
@endsection

@section('content')
@php($c = $result['counts'])

<div class="hb-card hb-panel mb-3">
    <div class="d-flex flex-wrap justify-content-between gap-2">
        <div>
            <h2 class="h5 mb-1">{{ $employee->full_name }}</h2>
            <div class="text-muted small">{{ $employee->employee_number }} &middot; {{ $employee->position?->name }} &middot; {{ $employee->department?->name }}</div>
        </div>
        <div class="text-end small text-muted">Periode<br><strong class="text-body">{{ $period['label'] }}</strong></div>
    </div>
</div>

<div class="hb-chips">
    <span class="hb-chip"><strong>{{ $c['total'] }}</strong> hari kerja</span>
    <span class="hb-chip hb-chip-present"><strong>{{ $c['present'] }}</strong> hadir</span>
    <span class="hb-chip hb-chip-late"><strong>{{ $c['late'] }}</strong> terlambat</span>
    <span class="hb-chip hb-chip-absent"><strong>{{ $c['absent'] }}</strong> tidak hadir</span>
    <span class="hb-chip hb-chip-leave"><strong>{{ $c['leave'] }}</strong> izin</span>
    <span class="hb-chip hb-chip-leave"><strong>{{ $c['sick'] }}</strong> sakit</span>
</div>

<div class="hb-card hb-panel">
    @if (empty($result['days']))
        <div class="hb-empty"><i class="bi bi-calendar-x"></i><span>Tidak ada hari kerja yang dihitung pada periode ini.</span></div>
    @else
        <div class="table-responsive">
            <table class="table hb-table align-middle mb-0">
                <thead>
                    <tr><th>Tanggal</th><th>Hari</th><th>Jadwal</th><th>Masuk</th><th>Pulang</th><th>Terlambat</th><th>Mode</th><th>Status</th><th>Keterangan</th></tr>
                </thead>
                <tbody>
                    @foreach ($result['days'] as $d)
                        @php($date = \Illuminate\Support\Carbon::parse($d['date']))
                        <tr>
                            <td class="text-nowrap fw-semibold">{{ $date->format('d/m/Y') }}</td>
                            <td>{{ $date->copy()->locale('id')->translatedFormat('l') }}</td>
                            <td class="small">{{ $d['schedule'] }}</td>
                            <td>{{ $d['check_in'] ?? '-' }}</td>
                            <td>{{ $d['check_out'] ?? '-' }}</td>
                            <td>{{ $d['late_minutes'] > 0 ? $d['late_minutes'].' menit' : '-' }}</td>
                            <td><span class="hb-mode hb-mode-{{ strtolower($d['work_mode']) }}">{{ $d['work_mode'] }}</span></td>
                            <td><x-status-badge :status="$d['status']" /></td>
                            <td class="small text-muted">
                                @if ($d['synthetic']) Tidak ada catatan absensi
                                @elseif ($d['leave_type']) {{ $d['leave_type'] }}
                                @else - @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
