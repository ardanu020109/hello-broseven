@extends('layouts.app')

@section('title', 'Jadwal Saya')
@section('page_title', 'Jadwal Saya')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/crud.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/schedules.css') }}">
@endpush

@section('content')
@php
    $dayShort = [1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab', 7 => 'Min'];
    $ws = $resolved['schedule'] ?? null;
    $workDays = $ws ? $ws->days->where('is_working_day', true)->pluck('day_of_week')->all() : [];
@endphp

<div class="row g-3 mb-3">
    <div class="col-12 col-lg-5">
        <div class="hb-card hb-panel h-100">
            <div class="hb-panel-head"><h2 class="hb-panel-title">Jadwal Aktif Hari Ini</h2></div>

            @if ($ws)
                <div class="fw-semibold fs-5">{{ $ws->name }}</div>
                <div class="text-muted mb-3">{{ substr((string) $ws->check_in_time, 0, 5) }} - {{ substr((string) $ws->check_out_time, 0, 5) }} &middot; {{ $ws->timezone }}</div>

                <dl class="hb-dl hb-dl-sm mb-3">
                    <dt>Toleransi</dt><dd>{{ $ws->late_tolerance_minutes }} menit</dd>
                    <dt>Istirahat</dt>
                    <dd>{{ $ws->break_start && $ws->break_end ? substr((string) $ws->break_start, 0, 5).' - '.substr((string) $ws->break_end, 0, 5) : '-' }}</dd>
                    <dt>Mode kerja</dt><dd>{{ $employee->work_mode }}</dd>
                    <dt>Hari ini</dt>
                    <dd>
                        @if ($resolved['holiday_name']) Libur: {{ $resolved['holiday_name'] }}
                        @elseif ($resolved['is_working_day']) Hari kerja ({{ $resolved['work_mode'] }})
                        @else Bukan hari kerja @endif
                    </dd>
                </dl>

                <div class="hb-days">
                    @foreach ($dayShort as $n => $lbl)
                        <span class="hb-day {{ in_array($n, $workDays, true) ? 'is-on' : '' }}">{{ $lbl }}</span>
                    @endforeach
                </div>
            @else
                <div class="hb-alert hb-alert-info mb-0">
                    <i class="bi bi-info-circle-fill"></i>
                    <span>Anda belum memiliki jadwal kerja yang berlaku hari ini. Hubungi administrator.</span>
                </div>
            @endif

            @if ($upcoming)
                <div class="hb-sched-foot mt-3">
                    Jadwal berikutnya: <strong>{{ $upcoming->workSchedule?->name }}</strong>
                    mulai {{ $upcoming->start_date->format('d/m/Y') }}.
                </div>
            @endif
        </div>
    </div>

    <div class="col-12 col-lg-7">
        <div class="hb-card hb-panel h-100">
            <div class="hb-cal-toolbar mb-3">
                <div class="hb-cal-nav">
                    <a href="{{ $urls['prev'] }}" class="hb-icon-btn" aria-label="Bulan sebelumnya"><i class="bi bi-chevron-left"></i></a>
                    <a href="{{ $urls['today'] }}" class="hb-btn hb-btn-light btn-sm">Bulan ini</a>
                    <a href="{{ $urls['next'] }}" class="hb-icon-btn" aria-label="Bulan berikutnya"><i class="bi bi-chevron-right"></i></a>
                    <h2 class="hb-cal-title">{{ $title }}</h2>
                </div>
            </div>

            @include('schedules.partials.month-grid', [
                'cal' => $cal, 'date' => $date, 'single' => true, 'singleId' => $employee->id,
            ])
        </div>
    </div>
</div>
@endsection
