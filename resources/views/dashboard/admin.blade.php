@extends('layouts.app')

@section('title', $heading)
@section('page_title', $heading)

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/dashboard.css') }}">
@endpush

@section('page_actions')
    <span class="hb-date-chip"><i class="bi bi-calendar3"></i> {{ $data['today_label'] }}</span>
@endsection

@section('content')
@php
    $f = $data['features'];
    $c = $data['counts'];
    $dist = $data['distribution'];
    $sched = $data['schedule_today'];
@endphp

{{-- ===== Kartu statistik ===== --}}
<div class="row g-3 mb-3">
    @if ($f['employees'])
        <div class="col-6 col-xl-3">
            <x-stat-card icon="bi-people" label="Total Karyawan" :value="$c['total_employees']" tone="primary" />
        </div>
        <div class="col-6 col-xl-3">
            <x-stat-card icon="bi-person-check" label="Karyawan Aktif" :value="$c['active_employees']" tone="secondary" />
        </div>
    @endif

    @if ($f['attendance'])
        <div class="col-6 col-xl-3">
            <x-stat-card icon="bi-clipboard-check" label="Kehadiran Hari Ini" :value="$c['attended_today']" tone="primary" hint="Hadir + terlambat" />
        </div>
        <div class="col-6 col-xl-3">
            <x-stat-card icon="bi-alarm" label="Terlambat Hari Ini" :value="$c['late_today']" tone="warning" />
        </div>
        <div class="col-6 col-xl-3">
            <x-stat-card icon="bi-person-x" label="Tidak Hadir Hari Ini" :value="$c['absent_today']" tone="danger" hint="Ditandai otomatis setelah jam kerja berakhir" />
        </div>
        <div class="col-6 col-xl-3">
            <x-stat-card icon="bi-envelope-check" label="Izin Hari Ini" :value="$c['leave_today']" tone="secondary" hint="Hanya izin yang disetujui" />
        </div>
    @endif

    @if ($f['leave_requests'])
        <div class="col-6 col-xl-3">
            <x-stat-card icon="bi-hourglass-split" label="Izin Menunggu Persetujuan" :value="$c['pending_leaves']" tone="warning" />
        </div>
    @endif

    @if ($f['payroll'])
        <div class="col-6 col-xl-3">
            <x-stat-card icon="bi-cash-coin" label="Pengeluaran Gaji Bulan Ini" :value="$data['salary']['label']" tone="dark" :hint="$data['salary']['hint']" />
        </div>
    @endif
</div>

@if ($f['attendance'])
    {{-- ===== Grafik mingguan + donut ===== --}}
    <div class="row g-3 mb-3">
        <div class="col-12 col-lg-8">
            <div class="hb-card hb-panel h-100">
                <div class="hb-panel-head">
                    <h2 class="hb-panel-title">Kehadiran 7 Hari Terakhir</h2>
                </div>
                <div class="hb-chart" data-chart="weekly"></div>
            </div>
        </div>
        <div class="col-12 col-lg-4">
            <div class="hb-card hb-panel h-100">
                <div class="hb-panel-head">
                    <h2 class="hb-panel-title">Status Kehadiran</h2>
                    <span class="hb-panel-sub">{{ $dist['month_label'] }}</span>
                </div>

                @if ($dist['total'] > 0)
                    <div class="hb-donut-wrap">
                        <div class="hb-donut" style="background: {{ $dist['gradient'] }}" role="img"
                             aria-label="Komposisi status kehadiran bulan ini">
                            <div class="hb-donut-center">
                                <strong>{{ $dist['total'] }}</strong>
                                <span>catatan</span>
                            </div>
                        </div>
                        <ul class="hb-donut-legend">
                            @foreach ($dist['items'] as $item)
                                <li>
                                    <span class="hb-dot" style="background: {{ $item['color'] }}"></span>
                                    <span class="flex-grow-1">{{ $item['label'] }}</span>
                                    <strong>{{ $item['count'] }}</strong>
                                    <span class="text-muted small">({{ $item['percent'] }}%)</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @else
                    <div class="hb-empty">
                        <i class="bi bi-pie-chart"></i>
                        <span>Belum ada data absensi bulan ini</span>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endif

{{-- ===== Grafik bulanan + jadwal hari ini ===== --}}
<div class="row g-3 mb-3">
    @if ($f['attendance'])
        <div class="col-12 col-lg-8">
            <div class="hb-card hb-panel h-100">
                <div class="hb-panel-head">
                    <h2 class="hb-panel-title">Kehadiran 6 Bulan Terakhir</h2>
                </div>
                <div class="hb-chart" data-chart="monthly"></div>
            </div>
        </div>
    @endif

    @if ($f['schedules'])
        <div class="col-12 col-lg-4">
            <div class="hb-card hb-panel h-100">
                <div class="hb-panel-head">
                    <h2 class="hb-panel-title">Jadwal Hari Ini</h2>
                </div>

                @if ($sched['holiday_name'])
                    <div class="hb-alert hb-alert-info mb-3">
                        <i class="bi bi-calendar-heart"></i><span>Hari libur: {{ $sched['holiday_name'] }}</span>
                    </div>
                @endif

                @forelse ($sched['groups'] as $group)
                    <div class="hb-sched-item">
                        <div class="fw-semibold">{{ $group['name'] }}</div>
                        <div class="small text-muted">
                            <i class="bi bi-clock"></i> {{ $group['check_in'] }} - {{ $group['check_out'] }}
                        </div>
                        <div class="small">
                            <span class="hb-pill hb-pill-success">{{ $group['working'] }} bekerja hari ini</span>
                            <span class="text-muted">dari {{ $group['assigned'] }} karyawan</span>
                        </div>
                    </div>
                @empty
                    <div class="hb-empty">
                        <i class="bi bi-calendar-x"></i>
                        <span>Belum ada karyawan dengan jadwal aktif</span>
                    </div>
                @endforelse

                @if (count($sched['groups']))
                    <div class="hb-sched-foot">
                        <div><strong>{{ $sched['not_yet_attended'] }}</strong> karyawan terjadwal belum memiliki catatan absensi hari ini</div>
                        @if ($sched['without_schedule'] > 0)
                            <div class="text-muted small mt-1">{{ $sched['without_schedule'] }} karyawan aktif belum punya jadwal</div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>

{{-- ===== Daftar terbaru ===== --}}
<div class="row g-3">
    @if ($f['attendance'])
        <div class="col-12 {{ $f['leave_requests'] ? 'col-xl-7' : '' }}">
            <div class="hb-card hb-panel h-100">
                <div class="hb-panel-head">
                    <h2 class="hb-panel-title">Absensi Terbaru</h2>
                    @if ($data['links']['attendance'])
                        <a href="{{ $data['links']['attendance'] }}" class="hb-link-sm">Lihat semua</a>
                    @endif
                </div>

                @if ($data['latest_attendance']->isEmpty())
                    <div class="hb-empty">
                        <i class="bi bi-clipboard-x"></i>
                        <span>Belum ada data absensi</span>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table hb-table align-middle mb-0">
                            <thead>
                                <tr><th>Tanggal</th><th>Karyawan</th><th>Masuk</th><th>Pulang</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($data['latest_attendance'] as $a)
                                    <tr>
                                        <td>{{ $a->attendance_date->format('d/m/Y') }}</td>
                                        <td>
                                            <div class="fw-semibold">{{ $a->employee?->full_name ?? '-' }}</div>
                                            <div class="small text-muted">{{ $a->employee?->position?->name }}</div>
                                        </td>
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
    @endif

    @if ($f['leave_requests'])
        <div class="col-12 {{ $f['attendance'] ? 'col-xl-5' : '' }}">
            <div class="hb-card hb-panel h-100">
                <div class="hb-panel-head">
                    <h2 class="hb-panel-title">Request Izin Terbaru</h2>
                    @if ($data['links']['leave'])
                        <a href="{{ $data['links']['leave'] }}" class="hb-link-sm">Lihat semua</a>
                    @endif
                </div>

                @if ($data['latest_leaves']->isEmpty())
                    <div class="hb-empty">
                        <i class="bi bi-envelope-x"></i>
                        <span>Belum ada pengajuan izin</span>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table hb-table align-middle mb-0">
                            <thead>
                                <tr><th>Karyawan</th><th>Jenis</th><th>Tanggal</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($data['latest_leaves'] as $l)
                                    <tr>
                                        <td class="fw-semibold">{{ $l->employee?->full_name ?? '-' }}</td>
                                        <td>{{ $l->leaveType?->name ?? '-' }}</td>
                                        <td class="small">
                                            {{ $l->start_date->format('d/m/Y') }}
                                            @if (!$l->start_date->isSameDay($l->end_date))
                                                - {{ $l->end_date->format('d/m/Y') }}
                                            @endif
                                        </td>
                                        <td><x-status-badge :status="$l->status" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>

@if ($f['attendance'])
    <script type="application/json" id="dashData">@json($data['charts'], JSON_HEX_TAG | JSON_HEX_AMP)</script>
@endif
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/dashboard.js') }}"></script>
@endpush
