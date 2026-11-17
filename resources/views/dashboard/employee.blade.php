@extends('layouts.app')

@section('title', 'Dashboard Karyawan')
@section('page_title', 'Dashboard Karyawan')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/dashboard.css') }}">
@endpush

@section('page_actions')
    <span class="hb-date-chip"><i class="bi bi-calendar3"></i> {{ $data['today_label'] }}</span>
@endsection

@section('content')
    @php
        $sched = $data['schedule'];
        [$statusLabel, $statusTone] = $data['today_status'];
    @endphp

    <div class="row g-3 mb-3">
        {{-- Profil --}}
        <div class="col-12 col-lg-4">
            <div class="hb-card hb-panel h-100 text-center">
                @if ($data['photo_url'])
                    <img src="{{ $data['photo_url'] }}" alt="Foto {{ $employee->full_name }}" class="hb-profile-photo">
                @else
                    <span class="hb-avatar hb-avatar-xl">{{ mb_strtoupper(mb_substr($employee->full_name, 0, 1)) }}</span>
                @endif

                <h2 class="h5 mt-3 mb-1">{{ $employee->full_name }}</h2>
                <div class="text-muted">{{ $employee->position?->name }}</div>
                <div class="mt-2"><span class="hb-role-badge">{{ $employee->employee_number }}</span></div>

                <dl class="hb-dl hb-dl-sm text-start mt-4 mb-0">
                    <dt>Departemen</dt>
                    <dd>{{ $employee->department?->name ?? '-' }}</dd>
                    <dt>Divisi</dt>
                    <dd>{{ $employee->division?->name ?? '-' }}</dd>
                    <dt>Status</dt>
                    <dd>{{ $employee->employeeStatus?->name ?? '-' }}</dd>
                    <dt>Mode kerja</dt>
                    <dd>{{ $employee->work_mode }}</dd>
                </dl>
            </div>
        </div>

        {{-- Absensi hari ini --}}
        <div class="col-12 col-lg-8">
            <div class="hb-card hb-panel h-100">
                <div class="hb-panel-head">
                    <h2 class="hb-panel-title">Absensi Hari Ini</h2>
                    <span class="hb-pill hb-pill-{{ $statusTone }}">{{ $statusLabel }}</span>
                </div>


                @if ($sched)
                    <div class="row g-3 mb-3">
                        <div class="col-6 col-md-3">
                            <div class="hb-mini"><span>Jadwal</span><strong>{{ $sched['name'] }}</strong></div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="hb-mini"><span>Jam masuk</span><strong>{{ $sched['check_in'] }}</strong></div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="hb-mini"><span>Jam pulang</span><strong>{{ $sched['check_out'] }}</strong></div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="hb-mini"><span>Mode hari
                                    ini</span><strong>{{ $sched['is_working_day'] ? $sched['work_mode'] : '-' }}</strong>
                            </div>
                        </div>
                    </div>
                    <p class="small text-muted">Toleransi keterlambatan {{ $sched['tolerance'] }} menit.</p>
                @else
                    <div class="hb-alert hb-alert-info mb-3">
                        <i class="bi bi-info-circle-fill"></i>
                        <span>Anda belum memiliki jadwal kerja. Hubungi administrator.</span>
                    </div>
                @endif

                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <div class="hb-clock hb-clock-in">
                            <i class="bi bi-box-arrow-in-right"></i>
                            <div><span>Masuk</span><strong>{{ $data['check_in'] ?? '--:--' }}</strong></div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="hb-clock hb-clock-out">
                            <i class="bi bi-box-arrow-right"></i>
                            <div><span>Pulang</span><strong>{{ $data['check_out'] ?? '--:--' }}</strong></div>
                        </div>
                    </div>
                </div>

                @if ($data['attendance_url'])
                    <a href="{{ $data['attendance_url'] }}" class="hb-btn hb-btn-primary">
                        <i class="bi bi-camera-video me-1"></i> Buka Halaman Absensi
                    </a>
                @else
                    <button type="button" class="hb-btn hb-btn-primary" disabled>
                        <i class="bi bi-camera-video me-1"></i> Absensi tidak tersedia
                    </button>
                @endif
            </div>
        </div>
    </div>

    {{-- Ringkasan bulan ini --}}
    <h2 class="hb-section-title">Ringkasan {{ $data['month_label'] }}</h2>
    <div class="row g-3">
        <div class="col-6 col-xl-3">
            <x-stat-card icon="bi-check2-circle" label="Total Hadir" :value="$data['month']['present']" tone="primary" />
        </div>
        <div class="col-6 col-xl-3">
            <x-stat-card icon="bi-alarm" label="Total Terlambat" :value="$data['month']['late']" tone="warning" />
        </div>
        <div class="col-6 col-xl-3">
            <x-stat-card icon="bi-person-x" label="Total Tidak Hadir" :value="$data['month']['absent']" tone="danger" />
        </div>
        <div class="col-6 col-xl-3">
            <x-stat-card icon="bi-envelope-check" label="Total Izin" :value="$data['month']['leave']" tone="secondary" />
        </div>
    </div>
@endsection
