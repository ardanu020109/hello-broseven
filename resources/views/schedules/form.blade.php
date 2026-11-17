@extends('layouts.app')

@php
    $isEdit = $schedule !== null;
    $title = $isEdit ? 'Ubah Jadwal' : 'Tambah Jadwal';
    $time = fn (?string $v, string $fallback = '') => $v ? substr($v, 0, 5) : $fallback;
    $isShift = old('is_shift', $isEdit ? ($schedule->is_shift ? '1' : '0') : '0');
    $isActive = old('is_active', $isEdit ? ($schedule->is_active ? '1' : '0') : '1');
@endphp

@section('title', $title)
@section('page_title', $title)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('schedules.index') }}">Data Jadwal</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $title }}</li>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/crud.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/schedules.css') }}">
@endpush

@section('content')
@if ($errors->any())
    <div class="hb-alert hb-alert-error mb-3" role="alert">
        <i class="bi bi-exclamation-octagon-fill"></i>
        <span>Data belum dapat disimpan. Periksa {{ $errors->count() }} kesalahan pada formulir di bawah.</span>
    </div>
@endif

@if ($hasHistory)
    <div class="hb-alert hb-alert-info mb-3">
        <i class="bi bi-info-circle-fill"></i>
        <span>Jadwal ini sudah memiliki riwayat absensi. Status absensi yang sudah tercatat tidak ikut berubah, tetapi perubahan jam dan hari kerja berlaku untuk hari-hari berikutnya.</span>
    </div>
@endif

<form method="POST" data-hb-loading
      action="{{ $isEdit ? route('schedules.update', $schedule->id) : route('schedules.store') }}">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    <section class="hb-card hb-form-section">
        <h2><i class="bi bi-clock"></i> Informasi Jadwal</h2>
        <div class="row g-3">
            <x-form-field name="name" label="Nama Jadwal" required col="col-md-8">
                <input type="text" id="name" name="name" maxlength="100" required
                       class="form-control @error('name') is-invalid @enderror"
                       value="{{ old('name', $schedule?->name) }}" placeholder="Jadwal Reguler Senin-Jumat">
            </x-form-field>
            <x-form-field name="timezone" label="Zona Waktu" required col="col-md-4">
                <select id="timezone" name="timezone" required class="form-select @error('timezone') is-invalid @enderror">
                    @foreach ($timezones as $value => $label)
                        <option value="{{ $value }}" @selected(old('timezone', $defaultTimezone) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-form-field>

            <x-form-field name="check_in_time" label="Jam Masuk" required col="col-md-3 col-6">
                <input type="time" id="check_in_time" name="check_in_time" required
                       class="form-control @error('check_in_time') is-invalid @enderror"
                       value="{{ old('check_in_time', $time($schedule?->check_in_time, '08:00')) }}">
            </x-form-field>
            <x-form-field name="check_out_time" label="Jam Pulang" required col="col-md-3 col-6">
                <input type="time" id="check_out_time" name="check_out_time" required
                       class="form-control @error('check_out_time') is-invalid @enderror"
                       value="{{ old('check_out_time', $time($schedule?->check_out_time, '17:00')) }}">
            </x-form-field>
            <x-form-field name="late_tolerance_minutes" label="Toleransi Keterlambatan (menit)" required col="col-md-3 col-6"
                          help="0 - 240 menit setelah jam masuk.">
                <input type="number" id="late_tolerance_minutes" name="late_tolerance_minutes" min="0" max="240" step="1" required
                       class="form-control @error('late_tolerance_minutes') is-invalid @enderror"
                       value="{{ old('late_tolerance_minutes', $schedule?->late_tolerance_minutes ?? 15) }}">
            </x-form-field>
            <div class="col-md-3 col-6">
                <label class="form-label fw-semibold">Durasi Kerja</label>
                <div class="form-control-plaintext fw-semibold" id="durationText">-</div>
            </div>

            <x-form-field name="break_start" label="Mulai Istirahat" col="col-md-3 col-6" help="Kosongkan jika tanpa istirahat.">
                <input type="time" id="break_start" name="break_start"
                       class="form-control @error('break_start') is-invalid @enderror"
                       value="{{ old('break_start', $time($schedule?->break_start, '12:00')) }}">
            </x-form-field>
            <x-form-field name="break_end" label="Selesai Istirahat" col="col-md-3 col-6">
                <input type="time" id="break_end" name="break_end"
                       class="form-control @error('break_end') is-invalid @enderror"
                       value="{{ old('break_end', $time($schedule?->break_end, '13:00')) }}">
            </x-form-field>

            <div class="col-md-3 col-6 d-flex align-items-end">
                <input type="hidden" name="is_shift" value="0">
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" role="switch" id="is_shift" name="is_shift" value="1" {{ $isShift ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_shift">Jadwal shift</label>
                </div>
            </div>
            <div class="col-md-3 col-6 d-flex align-items-end">
                <input type="hidden" name="is_active" value="0">
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" {{ $isActive ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_active">Jadwal aktif</label>
                </div>
            </div>
        </div>
        <p class="small text-muted mt-2 mb-0">Jam pulang harus setelah jam masuk. Jadwal yang melewati tengah malam belum didukung. Untuk istirahat, kosongkan kedua kolomnya jika tidak ada.</p>
    </section>

    <section class="hb-card hb-form-section">
        <h2><i class="bi bi-calendar-week"></i> Hari Kerja dan Mode Kerja</h2>
        <p class="small text-muted">Aktifkan hari yang menjadi hari kerja. Kolom mode kerja hanya berlaku untuk karyawan bermode <strong>Hybrid</strong>: pada hari tersebut ia WFO atau WFH. Karyawan WFO atau WFH murni mengikuti mode di profilnya.</p>

        @error('days')
            <div class="hb-alert hb-alert-error mb-3"><i class="bi bi-exclamation-octagon-fill"></i><span>{{ $message }}</span></div>
        @enderror

        <div class="table-responsive">
            <table class="table hb-table hb-days-table align-middle mb-0">
                <thead><tr><th>Hari</th><th>Hari kerja</th><th style="min-width:200px">Mode (untuk Hybrid)</th></tr></thead>
                <tbody>
                    @foreach ($dayNames as $iso => $label)
                        @php
                            $cfg = $schedule?->days->firstWhere('day_of_week', $iso);
                            $working = old("days.$iso.is_working_day", $cfg ? ($cfg->is_working_day ? '1' : '0') : ($iso <= 5 ? '1' : '0'));
                            $mode = old("days.$iso.work_mode", $cfg?->work_mode ?? 'WFO');
                        @endphp
                        <tr>
                            <td class="fw-semibold">{{ $label }}</td>
                            <td>
                                <input type="hidden" name="days[{{ $iso }}][is_working_day]" value="0">
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input js-day-toggle" type="checkbox" role="switch"
                                           id="day{{ $iso }}" name="days[{{ $iso }}][is_working_day]" value="1" {{ $working ? 'checked' : '' }}>
                                    <label class="form-check-label" for="day{{ $iso }}">Kerja</label>
                                </div>
                            </td>
                            <td>
                                <select name="days[{{ $iso }}][work_mode]" class="form-select form-select-sm" aria-label="Mode kerja {{ $label }}">
                                    <option value="WFO" @selected($mode === 'WFO')>WFO (di kantor)</option>
                                    <option value="WFH" @selected($mode === 'WFH')>WFH (dari rumah)</option>
                                </select>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <div class="d-flex gap-2 justify-content-end mb-4">
        <a href="{{ route('schedules.index') }}" class="hb-btn hb-btn-light">Batal</a>
        <button type="submit" class="hb-btn hb-btn-primary"><i class="bi bi-check2 me-1"></i> {{ $isEdit ? 'Simpan Perubahan' : 'Simpan Jadwal' }}</button>
    </div>
</form>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/schedules.js') }}"></script>
@endpush
