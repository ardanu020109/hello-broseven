@extends('layouts.app')

@section('title', 'Detail Absensi')
@section('page_title', 'Detail Absensi')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('attendance.index') }}">Data Absensi</a></li>
    <li class="breadcrumb-item active" aria-current="page">Detail</li>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/crud.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/records.css') }}">
@endpush

@section('page_actions')
    <a href="{{ route('attendance.index', ['from' => $record->attendance_date->toDateString(), 'to' => $record->attendance_date->toDateString()]) }}" class="hb-btn hb-btn-light">
        <i class="bi bi-arrow-left me-1"></i> Kembali
    </a>
@endsection

@section('content')
@php
    $emp = $record->employee;
    $statusNow = old('status', $record->status);
    $times = in_array($statusNow, ['present', 'late'], true);
    $inMap = \App\Support\AttendanceLabels::mapUrl($in);
    $outMap = \App\Support\AttendanceLabels::mapUrl($out);
@endphp

<div class="row g-3">
    <div class="col-12 col-xl-6">
        <div class="hb-card hb-panel mb-3">
            <div class="hb-panel-head">
                <h2 class="hb-panel-title">Informasi Absensi</h2>
                <x-status-badge :status="$record->status" />
            </div>
            <dl class="hb-dl hb-dl-wide mb-0">
                <dt>Karyawan</dt>
                <dd>
                    @if ($emp && !$emp->trashed())
                        <a href="{{ route('employees.show', $emp->id) }}">{{ $emp->full_name }}</a>
                    @else
                        {{ $emp?->full_name ?? '-' }} (dihapus)
                    @endif
                    <div class="small text-muted">{{ $emp?->employee_number }} &middot; {{ $emp?->position?->name }} &middot; {{ $emp?->department?->name }}</div>
                </dd>
                <dt>Tanggal</dt><dd>{{ $record->attendance_date->copy()->locale('id')->translatedFormat('l, d F Y') }}</dd>
                <dt>Jadwal</dt>
                <dd>
                    {{ $record->workSchedule?->name ?? '-' }}
                    @if ($record->workSchedule)
                        <span class="text-muted">({{ substr((string) $record->workSchedule->check_in_time, 0, 5) }} - {{ substr((string) $record->workSchedule->check_out_time, 0, 5) }})</span>
                    @endif
                </dd>
                <dt>Mode kerja</dt><dd>{{ $record->work_mode }}</dd>
                <dt>Jam masuk</dt><dd>{{ $record->check_in_at?->format('H:i') ?? '-' }}@if ($record->late_minutes > 0) <span class="text-muted">(terlambat {{ $record->late_minutes }} menit)</span>@endif</dd>
                <dt>Jam pulang</dt><dd>{{ $record->check_out_at?->format('H:i') ?? '-' }}</dd>
                <dt>Metode masuk</dt><dd>{{ \App\Support\AttendanceLabels::method($record->verification_method) }}@if ($record->face_match_score !== null) <span class="text-muted">(skor wajah {{ $record->face_match_score }})</span>@endif</dd>
                <dt>Metode pulang</dt><dd>{{ \App\Support\AttendanceLabels::method($record->check_out_method) }}@if ($record->check_out_face_score !== null) <span class="text-muted">(skor wajah {{ $record->check_out_face_score }})</span>@endif</dd>
                <dt>Catatan</dt><dd>{{ $record->notes ?: '-' }}</dd>
                @if ($record->leaveRequest)
                    <dt>Pengajuan izin</dt>
                    <dd><a href="{{ route('leave-requests.show', $record->leaveRequest->id) }}">#{{ $record->leaveRequest->id }} - {{ $record->leaveRequest->leaveType?->name }}</a></dd>
                @endif
            </dl>
        </div>

        <div class="hb-card hb-panel">
            <h2 class="hb-panel-title mb-3">Lokasi</h2>
            <dl class="hb-dl hb-dl-wide mb-0">
                <dt>Absen masuk</dt>
                <dd>
                    {{ \App\Support\AttendanceLabels::locationText($in) }}
                    @if ($inMap) <a href="{{ $inMap }}" target="_blank" rel="noopener noreferrer" class="ms-1">Lihat peta</a>@endif
                </dd>
                <dt>Absen pulang</dt>
                <dd>
                    {{ \App\Support\AttendanceLabels::locationText($out) }}
                    @if ($outMap) <a href="{{ $outMap }}" target="_blank" rel="noopener noreferrer" class="ms-1">Lihat peta</a>@endif
                </dd>
            </dl>
        </div>
    </div>

    <div class="col-12 col-xl-6">
        <div class="hb-card hb-panel mb-3">
            <h2 class="hb-panel-title mb-3">Koreksi Absensi</h2>

            @if ($locked)
                <div class="hb-alert hb-alert-info mb-0">
                    <i class="bi bi-lock-fill"></i>
                    <span>Tanggal ini masuk periode payroll yang sudah ditutup, sehingga absensinya tidak dapat dikoreksi.</span>
                </div>
            @else
                <p class="small text-muted">Koreksi wajib disertai alasan dan tercatat di audit log. Karyawan akan menerima notifikasi. Jam diisi dalam zona waktu aplikasi ({{ config('app.timezone') }}).</p>

                @if ($errors->any())
                    <div class="hb-alert hb-alert-error mb-3" role="alert">
                        <i class="bi bi-exclamation-octagon-fill"></i>
                        <span>Koreksi belum dapat disimpan. Periksa isian di bawah.</span>
                    </div>
                @endif

                <form method="POST" action="{{ route('attendance.correct', $record->id) }}" data-hb-loading>
                    @csrf
                    @method('PUT')
                    <div class="row g-3">
                        <x-form-field name="status" label="Status" required col="col-12">
                            <select id="corrStatus" name="status" class="form-select @error('status') is-invalid @enderror" required>
                                @foreach (['present' => 'Hadir', 'late' => 'Terlambat', 'absent' => 'Tidak Hadir', 'leave' => 'Izin'] as $value => $label)
                                    <option value="{{ $value }}" @selected($statusNow === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </x-form-field>

                        <div class="col-12" id="corrLeaveHint" @if ($statusNow !== 'leave') hidden @endif>
                            <div class="hb-alert hb-alert-info mb-0">
                                <i class="bi bi-info-circle-fill"></i>
                                <span>Status Izin hanya dapat dipilih jika karyawan punya pengajuan izin yang <strong>disetujui</strong> pada tanggal ini.</span>
                            </div>
                        </div>

                        <x-form-field name="check_in_time" label="Jam Masuk" col="col-6" help="Wajib untuk Hadir atau Terlambat.">
                            <input type="time" id="check_in_time" name="check_in_time" data-corr-time {{ $times ? '' : 'disabled' }}
                                   class="form-control @error('check_in_time') is-invalid @enderror"
                                   value="{{ old('check_in_time', $record->check_in_at?->format('H:i')) }}">
                        </x-form-field>
                        <x-form-field name="check_out_time" label="Jam Pulang" col="col-6" help="Kosongkan jika belum pulang.">
                            <input type="time" id="check_out_time" name="check_out_time" data-corr-time {{ $times ? '' : 'disabled' }}
                                   class="form-control @error('check_out_time') is-invalid @enderror"
                                   value="{{ old('check_out_time', $record->check_out_at?->format('H:i')) }}">
                        </x-form-field>

                        <x-form-field name="reason" label="Alasan Koreksi" required col="col-12">
                            <textarea id="reason" name="reason" rows="3" maxlength="500" required
                                      class="form-control @error('reason') is-invalid @enderror"
                                      placeholder="Mis. karyawan lupa absen pulang, dikonfirmasi oleh atasan langsung.">{{ old('reason') }}</textarea>
                        </x-form-field>

                        <div class="col-12 d-grid">
                            <button type="submit" class="hb-btn hb-btn-primary"><i class="bi bi-check2 me-1"></i> Simpan Koreksi</button>
                        </div>
                    </div>
                </form>
            @endif
        </div>

        <div class="hb-card hb-panel">
            <h2 class="hb-panel-title mb-3">Riwayat Perubahan</h2>
            @if ($audits->isEmpty())
                <div class="hb-empty"><i class="bi bi-clock-history"></i><span>Belum ada perubahan tercatat.</span></div>
            @else
                <ul class="hb-timeline">
                    @foreach ($audits as $log)
                        <li>
                            <div class="fw-semibold">
                                {{ ['correct_attendance' => 'Koreksi admin', 'leave_sync' => 'Disesuaikan karena izin disetujui'][$log->action] ?? $log->action }}
                            </div>
                            <div class="small text-muted">{{ $log->created_at->format('d/m/Y H:i') }} &middot; {{ $log->user?->name ?? 'Sistem' }}</div>
                            @if ($log->reason)<div class="small">Alasan: {{ $log->reason }}</div>@endif
                            @if ($log->old_values && $log->new_values)
                                <div class="small text-muted">
                                    {{ \App\Support\AttendanceLabels::status($log->old_values['status'] ?? null) }}
                                    ({{ $log->old_values['check_in_at'] ?? '-' }} / {{ $log->old_values['check_out_at'] ?? '-' }})
                                    &rarr;
                                    {{ \App\Support\AttendanceLabels::status($log->new_values['status'] ?? null) }}
                                    ({{ $log->new_values['check_in_at'] ?? '-' }} / {{ $log->new_values['check_out_at'] ?? '-' }})
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/records.js') }}"></script>
@endpush
