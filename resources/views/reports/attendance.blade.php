@extends('layouts.app')

@section('title', 'Laporan Kehadiran')
@section('page_title', 'Laporan Kehadiran')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/crud.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/records.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/reports.css') }}">
@endpush

@section('page_actions')
    @php
        $exportQuery = request()->except('page');
    @endphp
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('reports.attendance.export', ['format' => 'xlsx'] + $exportQuery) }}" class="hb-btn hb-btn-secondary"><i class="bi bi-file-earmark-excel me-1"></i> Excel</a>
        <a href="{{ route('reports.attendance.export', ['format' => 'pdf'] + $exportQuery) }}" class="hb-btn hb-btn-secondary"><i class="bi bi-file-earmark-pdf me-1"></i> PDF</a>
        <a href="{{ route('reports.attendance.print', $exportQuery) }}" target="_blank" rel="noopener" class="hb-btn hb-btn-light"><i class="bi bi-printer me-1"></i> Cetak</a>
    </div>
@endsection

@section('content')
@php
    $t = $data['totals'];
    $qs = request()->except('page');
    $rate = $t['total'] > 0 ? ($t['present'] + $t['late']) / $t['total'] * 100 : 0;
@endphp

<div class="hb-chips">
    <span class="hb-chip"><strong>{{ $t['employees'] }}</strong> karyawan</span>
    <span class="hb-chip"><strong>{{ $t['total'] }}</strong> hari kerja</span>
    <span class="hb-chip hb-chip-present"><strong>{{ $t['present'] }}</strong> hadir</span>
    <span class="hb-chip hb-chip-late"><strong>{{ $t['late'] }}</strong> terlambat</span>
    <span class="hb-chip hb-chip-absent"><strong>{{ $t['absent'] }}</strong> tidak hadir</span>
    <span class="hb-chip hb-chip-leave"><strong>{{ $t['leave'] }}</strong> izin</span>
    <span class="hb-chip hb-chip-leave"><strong>{{ $t['sick'] }}</strong> sakit</span>
    <span class="hb-chip-note">{{ $period['label'] }} &middot; kehadiran {{ number_format($rate, 1, ',', '.') }}%</span>
</div>

@if ($period['beyond_today'])
    <div class="hb-alert hb-alert-info mb-2"><i class="bi bi-info-circle-fill"></i><span>Periode melewati hari ini. Hari setelah hari ini belum dihitung, dan hari ini baru dihitung jika karyawan sudah punya catatan.</span></div>
@endif
{{-- @if ($t['unrecorded'] > 0)
    <div class="hb-alert hb-alert-info mb-2"><i class="bi bi-exclamation-circle-fill"></i><span>{{ $t['unrecorded'] }} hari kerja tanpa catatan absensi dihitung sebagai Tidak Hadir (atau Izin/Sakit jika ada izin disetujui). Jalankan <code>php artisan attendance:mark-absent --days=31</code> agar catatannya terbentuk.</span></div>
@endif --}}
@if ($period['clamped'])
    <div class="hb-alert hb-alert-info mb-2"><i class="bi bi-info-circle-fill"></i><span>Rentang tanggal dibatasi maksimal 366 hari.</span></div>
@endif

<div class="hb-card hb-panel mt-2">
    <form method="GET" class="hb-filters">
        <select name="mode" id="periodMode" class="form-select" aria-label="Jenis periode">
            <option value="month" @selected($period['mode'] === 'month')>Per bulan</option>
            <option value="year" @selected($period['mode'] === 'year')>Per tahun</option>
            <option value="range" @selected($period['mode'] === 'range')>Rentang tanggal</option>
        </select>
        <span data-mode="month"><input type="month" name="month" value="{{ $period['month'] }}" class="form-control" aria-label="Bulan"></span>
        <span data-mode="year"><input type="number" name="year" value="{{ $period['year'] }}" min="2000" max="2100" class="form-control" style="width:110px" aria-label="Tahun"></span>
        <span data-mode="range" class="d-flex gap-2">
            <input type="date" name="from" value="{{ $period['from']->toDateString() }}" class="form-control" aria-label="Dari tanggal">
            <input type="date" name="to" value="{{ $period['to']->toDateString() }}" class="form-control" aria-label="Sampai tanggal">
        </span>
        <select name="department_id" class="form-select" aria-label="Filter departemen">
            <option value="">Semua departemen</option>
            @foreach ($departments as $d)
                <option value="{{ $d->id }}" @selected((string) request('department_id') === (string) $d->id)>{{ $d->name }}</option>
            @endforeach
        </select>
        <select name="position_id" class="form-select" aria-label="Filter jabatan">
            <option value="">Semua jabatan</option>
            @foreach ($positions as $p)
                <option value="{{ $p->id }}" @selected((string) request('position_id') === (string) $p->id)>{{ $p->name }}</option>
            @endforeach
        </select>
        <select name="employee_id" class="form-select" aria-label="Filter karyawan">
            <option value="">Semua karyawan</option>
            @foreach ($employees as $e)
                <option value="{{ $e->id }}" @selected((string) request('employee_id') === (string) $e->id)>{{ $e->full_name }} ({{ $e->employee_number }})</option>
            @endforeach
        </select>
        <button type="submit" class="hb-btn hb-btn-secondary"><i class="bi bi-funnel me-1"></i> Terapkan</button>
        @if ($filtered)
            <a href="{{ route('reports.attendance') }}" class="hb-btn hb-btn-light">Reset</a>
        @endif
    </form>

    @if ($rows->isEmpty())
        <div class="hb-empty">
            <i class="bi bi-bar-chart-line"></i>
            <span>Tidak ada hari kerja yang dihitung pada periode dan filter ini.</span>
        </div>
    @else
        <div class="table-responsive">
            <table class="table hb-table align-middle mb-0">
                <thead>
                    <tr>
                        <th>#</th><th>Karyawan</th><th>Jabatan</th><th>Departemen</th>
                        <th class="text-end">Hari Kerja</th><th class="text-end">Hadir</th><th class="text-end">Terlambat</th>
                        <th class="text-end">Tidak Hadir</th><th class="text-end">Izin</th><th class="text-end">Sakit</th>
                        <th>Kehadiran</th><th class="text-end">Detail</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $r)
                        @php
                            $e = $r['employee'];
                            $c = $r['counts'];
                            $pct = round($r['rate'] * 100, 1);
                        @endphp
                        <tr>
                            <td class="text-muted">{{ $rows->firstItem() + $loop->index }}</td>
                            <td>
                                <a href="{{ route('reports.attendance.detail', ['id' => $e->id] + $qs) }}" class="fw-semibold text-decoration-none">{{ $e->full_name }}</a>
                                <div class="small text-muted">{{ $e->employee_number }}@if ($e->trashed()) &middot; dihapus @elseif (!$e->is_active) &middot; nonaktif @endif</div>
                            </td>
                            <td>{{ $e->position?->name ?? '-' }}</td>
                            <td>{{ $e->department?->name ?? '-' }}</td>
                            <td class="text-end fw-semibold">{{ $c['total'] }}</td>
                            <td class="text-end">{{ $c['present'] }}</td>
                            <td class="text-end">{{ $c['late'] }}</td>
                            <td class="text-end">{{ $c['absent'] }}@if ($c['unrecorded'] > 0)<span class="rp-flag" title="Termasuk hari tanpa catatan absensi">*</span>@endif</td>
                            <td class="text-end">{{ $c['leave'] }}</td>
                            <td class="text-end">{{ $c['sick'] }}</td>
                            <td>
                                <div class="rp-rate"><span class="rp-bar"><i style="width: {{ min(100, $pct) }}%"></i></span><span class="small">{{ number_format($pct, 1, ',', '.') }}%</span></div>
                            </td>
                            <td>
                                <div class="hb-actions">
                                    <a href="{{ route('reports.attendance.detail', ['id' => $e->id] + $qs) }}" class="hb-icon-action" title="Detail harian" aria-label="Detail harian"><i class="bi bi-eye"></i></a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($t['unrecorded'] > 0)
            <p class="small text-muted mt-2 mb-0"><span class="rp-flag">*</span> Termasuk hari kerja tanpa catatan absensi.</p>
        @endif
    @endif

    <x-pagination :paginator="$rows" />

    @if ($data['skipped'] > 0)
        <p class="small text-muted mt-3 mb-0">{{ $data['skipped'] }} karyawan tidak ditampilkan karena tidak punya hari kerja yang dihitung pada periode ini (belum masuk kerja, belum punya jadwal, atau libur penuh).</p>
    @endif
</div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/reports.js') }}"></script>
@endpush
