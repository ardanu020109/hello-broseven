@extends('layouts.app')

@section('title', 'Riwayat Kehadiran')
@section('page_title', 'Riwayat Kehadiran')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/crud.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/records.css') }}">
@endpush

@section('content')
@php
    $statusOptions = ['present' => 'Hadir', 'late' => 'Terlambat', 'absent' => 'Tidak Hadir', 'leave' => 'Izin'];
@endphp

<div class="hb-chips">
    <span class="hb-chip hb-chip-present"><strong>{{ $counts['present'] ?? 0 }}</strong> hadir</span>
    <span class="hb-chip hb-chip-late"><strong>{{ $counts['late'] ?? 0 }}</strong> terlambat</span>
    <span class="hb-chip hb-chip-absent"><strong>{{ $counts['absent'] ?? 0 }}</strong> tidak hadir</span>
    <span class="hb-chip hb-chip-leave"><strong>{{ $counts['leave'] ?? 0 }}</strong> izin</span>
    <span class="hb-chip-note">Periode {{ $from->format('d/m/Y') }} - {{ $to->format('d/m/Y') }}</span>
</div>

<div class="hb-card hb-panel">
    <form method="GET" class="hb-filters">
        <input type="month" name="month" value="{{ $month }}" class="form-control" aria-label="Filter bulan">
        <input type="date" name="from" value="{{ request('from') }}" class="form-control" aria-label="Dari tanggal">
        <input type="date" name="to" value="{{ request('to') }}" class="form-control" aria-label="Sampai tanggal">
        <select name="status" class="form-select" aria-label="Filter status">
            <option value="">Semua status</option>
            @foreach ($statusOptions as $value => $label)
                <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button type="submit" class="hb-btn hb-btn-secondary"><i class="bi bi-funnel me-1"></i> Terapkan</button>
        @if ($filtered)
            <a href="{{ route('employee.history') }}" class="hb-btn hb-btn-light">Reset</a>
        @endif
    </form>
    <p class="small text-muted mb-3">Isian Dari/Sampai tanggal, jika diisi, menggantikan filter bulan.</p>

    @if ($records->isEmpty())
        <div class="hb-empty">
            <i class="bi bi-clock-history"></i>
            <span>{{ $filtered ? 'Tidak ada data yang cocok dengan filter.' : 'Belum ada riwayat kehadiran pada periode ini.' }}</span>
        </div>
    @else
        <div class="table-responsive">
            <table class="table hb-table align-middle mb-0">
                <thead>
                    <tr><th>Tanggal</th><th>Masuk</th><th>Pulang</th><th>Status</th><th>Lokasi</th><th>Jarak</th><th>Mode</th><th class="text-end">Detail</th></tr>
                </thead>
                <tbody>
                    @foreach ($records as $a)
                        @php($loc = $a->checkInLocation)
                        <tr>
                            <td class="text-nowrap">
                                <div class="fw-semibold">{{ $a->attendance_date->format('d/m/Y') }}</div>
                                <div class="small text-muted">{{ $a->attendance_date->copy()->locale('id')->translatedFormat('l') }}</div>
                            </td>
                            <td>{{ $a->check_in_at?->format('H:i') ?? '-' }}</td>
                            <td>{{ $a->check_out_at?->format('H:i') ?? '-' }}</td>
                            <td>
                                <x-status-badge :status="$a->status" />
                                @if ($a->late_minutes > 0)<div class="small text-muted">{{ $a->late_minutes }} menit</div>@endif
                            </td>
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
                            </td>
                            <td class="text-nowrap">{{ $loc?->distance_meters !== null ? number_format((float) $loc->distance_meters, 0, ',', '.').' m' : '-' }}</td>
                            <td><span class="hb-mode hb-mode-{{ strtolower($a->work_mode) }}">{{ $a->work_mode }}</span></td>
                            <td>
                                <div class="hb-actions">
                                    <button type="button" class="hb-icon-action" title="Detail" aria-label="Detail"
                                            data-bs-toggle="modal" data-bs-target="#hbDetailModal"
                                            data-detail="{{ json_encode($details[$a->id]) }}">
                                        <i class="bi bi-eye"></i>
                                    </button>
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

<div class="modal fade" id="hbDetailModal" tabindex="-1" aria-labelledby="hbDetailTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content hb-modal">
            <div class="modal-header">
                <h2 class="modal-title h5" id="hbDetailTitle">Detail Absensi</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <dl class="hb-dl hb-dl-wide mb-0">
                    <dt>Tanggal</dt><dd data-d="date">-</dd>
                    <dt>Status</dt><dd data-d="status">-</dd>
                    <dt>Jadwal</dt><dd data-d="schedule">-</dd>
                    <dt>Mode kerja</dt><dd data-d="mode">-</dd>
                    <dt>Jam masuk</dt><dd data-d="check_in">-</dd>
                    <dt>Jam pulang</dt><dd data-d="check_out">-</dd>
                    <dt>Keterlambatan</dt><dd data-d="late">-</dd>
                    <dt>Metode</dt><dd data-d="method">-</dd>
                    <dt>Lokasi masuk</dt><dd><span data-d="in_loc">-</span> <a href="#" target="_blank" rel="noopener noreferrer" data-d-map="in_map" hidden>Lihat peta</a></dd>
                    <dt>Lokasi pulang</dt><dd><span data-d="out_loc">-</span> <a href="#" target="_blank" rel="noopener noreferrer" data-d-map="out_map" hidden>Lihat peta</a></dd>
                    <dt>Catatan</dt><dd data-d="note">-</dd>
                    <dt>Koreksi</dt><dd data-d="correction">-</dd>
                </dl>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="hb-btn hb-btn-light" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/records.js') }}"></script>
@endpush
