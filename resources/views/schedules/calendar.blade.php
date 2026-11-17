@extends('layouts.app')

@section('title', 'Kalender Jadwal')
@section('page_title', 'Data Jadwal')

@section('breadcrumb')
    <li class="breadcrumb-item">
        Data Jadwal</li>
    <li class="breadcrumb-item active" aria-current="page">Kalender</li>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/crud.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/schedules.css') }}">
@endpush

@section('content')
    @php
        $todayKey = today()->toDateString();
        $stateLabels = [
            'working' => ['Bekerja', 'hb-pill-success'],
            'holiday' => ['Hari libur', 'hb-pill-info'],
            'off' => ['Libur (bukan hari kerja)', 'hb-pill-muted'],
            'none' => ['Belum ada jadwal', 'hb-pill-muted'],
        ];
        $single = $employeeId !== null;
    @endphp

    @include('schedules.partials.tabs')

    <div class="hb-card hb-panel mb-3">
        <div class="hb-cal-toolbar">
            <div class="hb-cal-nav">
                <a href="{{ $urls['prev'] }}" class="hb-icon-btn" aria-label="Sebelumnya"><i
                        class="bi bi-chevron-left"></i></a>
                <a href="{{ $urls['today'] }}" class="hb-btn hb-btn-light btn-sm">Hari ini</a>
                <a href="{{ $urls['next'] }}" class="hb-icon-btn" aria-label="Berikutnya"><i
                        class="bi bi-chevron-right"></i></a>
                <h2 class="hb-cal-title">{{ $title }}</h2>
            </div>
            <div class="btn-group hb-view-switch" role="group" aria-label="Tampilan kalender">
                <a href="{{ $urls['month'] }}" class="btn btn-sm {{ $view === 'month' ? 'is-active' : '' }}">Bulan</a>
                <a href="{{ $urls['week'] }}" class="btn btn-sm {{ $view === 'week' ? 'is-active' : '' }}">Minggu</a>
                <a href="{{ $urls['day'] }}" class="btn btn-sm {{ $view === 'day' ? 'is-active' : '' }}">Hari</a>
            </div>
        </div>

        <form method="GET" class="hb-filters mt-3 mb-0">
            <input type="hidden" name="view" value="{{ $view }}">
            <input type="hidden" name="date" value="{{ $date->toDateString() }}">
            <select name="department_id" class="form-select" aria-label="Filter departemen" data-autosubmit>
                <option value="">Semua departemen</option>
                @foreach ($departments as $d)
                    <option value="{{ $d->id }}" @selected($departmentId === $d->id)>{{ $d->name }}</option>
                @endforeach
            </select>
            <select name="employee_id" class="form-select" aria-label="Filter karyawan" data-autosubmit>
                <option value="">Semua karyawan</option>
                @foreach ($employees as $e)
                    <option value="{{ $e->id }}" @selected($employeeId === $e->id)>{{ $e->full_name }}
                        ({{ $e->employee_number }})
                    </option>
                @endforeach
            </select>
            @if ($filters)
                <a href="{{ route('schedules.calendar', ['view' => $view, 'date' => $date->toDateString()]) }}"
                    class="hb-btn hb-btn-light">Reset filter</a>
            @endif
        </form>
    </div>

    <div class="hb-card hb-panel">
        @if ($single && $cal['employees']->isEmpty())
            <div class="hb-empty"><i class="bi bi-person-x"></i><span>Karyawan tidak ditemukan atau sudah nonaktif.</span>
            </div>
        @elseif ($view === 'month')
            @include('schedules.partials.month-grid', [
                'cal' => $cal,
                'date' => $date,
                'single' => $single,
                'singleId' => $employeeId,
                'dayQuery' => $filters,
            ])
            <div class="hb-cal-legend">
                <span><i class="hb-dot" style="background:var(--hb-primary)"></i> Hari ini</span>
                <span><i class="hb-dot" style="background:#e0a100"></i> Hari libur</span>
                @if (!$single)
                    <span>Klik tanggal untuk melihat rincian harian.</span>
                @endif
            </div>
        @elseif ($view === 'week')
            @if ($cal['truncated'])
                <div class="hb-alert hb-alert-info mb-3"><i class="bi bi-info-circle-fill"></i><span>Menampilkan
                        {{ $cal['employees']->count() }} dari {{ $cal['total'] }} karyawan. Gunakan filter departemen atau
                        karyawan untuk mempersempit.</span></div>
            @endif
            @if ($cal['employees']->isEmpty())
                <div class="hb-empty"><i class="bi bi-people"></i><span>Tidak ada karyawan aktif.</span></div>
            @else
                <div class="table-responsive">
                    <table class="table hb-table hb-week align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="min-width:180px">Karyawan</th>
                                @foreach ($cal['days'] as $key => $day)
                                    <th class="text-center {{ $key === $todayKey ? 'is-today' : '' }}">
                                        {{ $day['date']->copy()->locale('id')->translatedFormat('D') }}
                                        <span class="d-block">{{ $day['date']->format('d/m') }}</span>
                                        @if ($day['holiday'])
                                            <small class="d-block hb-week-holiday"
                                                title="{{ $day['holiday'] }}">Libur</small>
                                        @endif
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($cal['employees'] as $emp)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $emp->full_name }}</div>
                                        <div class="small text-muted">{{ $emp->employee_number }} &middot;
                                            {{ $emp->work_mode }}</div>
                                    </td>
                                    @foreach ($cal['days'] as $key => $day)
                                        @php
                                            $e = $cal['matrix'][$emp->id][$key] ?? ['state' => 'none'];
                                        @endphp
                                        <td class="text-center hb-week-cell {{ $key === $todayKey ? 'is-today' : '' }}">
                                            @if ($e['state'] === 'working')
                                                <div class="hb-cal-time">{{ $e['time'] }}</div>
                                                <span
                                                    class="hb-mode hb-mode-{{ strtolower($e['mode']) }}">{{ $e['mode'] }}</span>
                                            @elseif ($e['state'] === 'holiday')
                                                <span class="hb-cal-muted">Libur</span>
                                            @elseif ($e['state'] === 'off')
                                                <span class="hb-cal-muted">Libur</span>
                                            @else
                                                <span class="hb-cal-muted">-</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @else
            @php
                $dayKey = array_key_first($cal['days']);
            @endphp
            @if ($cal['days'][$dayKey]['holiday'])
                <div class="hb-alert hb-alert-info mb-3"><i class="bi bi-calendar-heart"></i><span>Hari libur:
                        {{ $cal['days'][$dayKey]['holiday'] }}</span></div>
            @endif
            @if ($cal['truncated'])
                <div class="hb-alert hb-alert-info mb-3"><i class="bi bi-info-circle-fill"></i><span>Menampilkan
                        {{ $cal['employees']->count() }} dari {{ $cal['total'] }} karyawan. Gunakan filter untuk
                        mempersempit.</span></div>
            @endif
            @if ($cal['employees']->isEmpty())
                <div class="hb-empty"><i class="bi bi-people"></i><span>Tidak ada karyawan aktif.</span></div>
            @else
                <div class="table-responsive">
                    <table class="table hb-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Karyawan</th>
                                <th>Jabatan</th>
                                <th>Jadwal</th>
                                <th>Jam Kerja</th>
                                <th>Mode</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($cal['employees'] as $emp)
                                @php
                                    $e = $cal['matrix'][$emp->id][$dayKey] ?? [
                                        'state' => 'none',
                                        'schedule' => null,
                                        'time' => null,
                                        'mode' => null,
                                    ];
                                    [$lbl, $cls] = $stateLabels[$e['state']];
                                @endphp
                                <tr>
                                    <td>
                                        <a href="{{ route('employees.show', $emp->id) }}"
                                            class="fw-semibold text-decoration-none">{{ $emp->full_name }}</a>
                                        <div class="small text-muted">{{ $emp->employee_number }}</div>
                                    </td>
                                    <td>{{ $emp->position?->name ?? '-' }}</td>
                                    <td>{{ $e['schedule'] ?? '-' }}</td>
                                    <td class="text-nowrap">{{ $e['time'] ?? '-' }}</td>
                                    <td>
                                        @if ($e['mode'])
                                            <span
                                                class="hb-mode hb-mode-{{ strtolower($e['mode']) }}">{{ $e['mode'] }}</span>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td><span class="hb-pill {{ $cls }}">{{ $lbl }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/schedules.js') }}"></script>
@endpush
