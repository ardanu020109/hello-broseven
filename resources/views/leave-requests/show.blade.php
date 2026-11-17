@extends('layouts.app')

@section('title', 'Detail Pengajuan Izin')
@section('page_title', 'Detail Pengajuan Izin')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('leave-requests.index') }}">Request Izin</a></li>
    <li class="breadcrumb-item active" aria-current="page">#{{ $leave->id }}</li>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/crud.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/records.css') }}">
@endpush

@section('page_actions')
    <a href="{{ route('leave-requests.index') }}" class="hb-btn hb-btn-light"><i class="bi bi-arrow-left me-1"></i> Kembali</a>
@endsection

@section('content')
@php
    $emp = $leave->employee;
    $rule = $leave->leaveType?->salaryDeductionRule;
@endphp

<div class="row g-3">
    <div class="col-12 col-xl-7">
        <div class="hb-card hb-panel mb-3">
            <div class="hb-panel-head">
                <h2 class="hb-panel-title">Pengajuan #{{ $leave->id }}</h2>
                <x-status-badge :status="$leave->status" />
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
                <dt>Jenis izin</dt>
                <dd>
                    {{ $leave->leaveType?->name ?? '-' }}
                    @if ($rule)<div class="small text-muted">Potongan gaji {{ rtrim(rtrim(number_format((float) $rule->deduction_percent, 2, '.', ''), '0'), '.') }}% setelah disetujui</div>@endif
                </dd>
                <dt>Periode</dt>
                <dd>
                    {{ $leave->start_date->locale('id')->translatedFormat('d F Y') }}
                    @if (!$leave->start_date->isSameDay($leave->end_date)) - {{ $leave->end_date->locale('id')->translatedFormat('d F Y') }} @endif
                    <span class="text-muted">({{ $leave->total_days }} hari kalender)</span>
                </dd>
                <dt>Alasan</dt><dd style="white-space: pre-line">{{ $leave->reason }}</dd>
                <dt>Lampiran</dt>
                <dd>
                    @if ($leave->attachment)
                        <a href="{{ route('leave-requests.attachment', $leave->id) }}" target="_blank" rel="noopener"><i class="bi bi-paperclip"></i> Buka lampiran</a>
                    @else Tidak ada @endif
                </dd>
                <dt>Diajukan</dt><dd>{{ $leave->created_at->format('d/m/Y H:i') }}</dd>
            </dl>
        </div>

        <div class="hb-card hb-panel">
            <h2 class="hb-panel-title mb-3">Riwayat Persetujuan</h2>
            <ul class="hb-timeline">
                <li>
                    <div class="fw-semibold">Diajukan</div>
                    <div class="small text-muted">{{ $leave->created_at->format('d/m/Y H:i') }} &middot; {{ $emp?->full_name }}</div>
                </li>
                @foreach ($audits->reverse() as $log)
                    @if (in_array($log->action, ['approve_leave', 'reject_leave'], true))
                        <li>
                            <div class="fw-semibold">{{ $log->action === 'approve_leave' ? 'Disetujui' : 'Ditolak' }}</div>
                            <div class="small text-muted">{{ $log->created_at->format('d/m/Y H:i') }} &middot; {{ $log->user?->name ?? 'Sistem' }}</div>
                            @if ($log->reason)<div class="small">Catatan: {{ $log->reason }}</div>@endif
                        </li>
                    @endif
                @endforeach
            </ul>
            @if ($leave->status !== 'pending')
                <div class="hb-sched-foot mt-3">
                    <div>Diputuskan oleh <strong>{{ $leave->reviewer?->name ?? '-' }}</strong> pada {{ $leave->reviewed_at?->format('d/m/Y H:i') ?? '-' }}.</div>
                    <div class="mt-1">Catatan admin: {{ $leave->admin_note ?: '-' }}</div>
                </div>
            @endif
        </div>
    </div>

    <div class="col-12 col-xl-5">
        <div class="hb-card hb-panel">
            <h2 class="hb-panel-title mb-3">Keputusan</h2>

            @if ($leave->status === 'pending')
                @if ($existingAttendance > 0)
                    <div class="hb-alert hb-alert-info mb-3">
                        <i class="bi bi-info-circle-fill"></i>
                        <span>{{ $existingAttendance }} hari dalam rentang ini sudah tercatat Hadir/Terlambat. Hari tersebut tidak akan diubah jika disetujui.</span>
                    </div>
                @endif

                <p class="small text-muted">Jika disetujui, hari kerja dalam rentang ini yang sudah lewat atau hari ini dicatat sebagai Izin (hari yang sebelumnya Tidak Hadir diubah). Hari mendatang dicatat otomatis pada harinya. Jika ditolak, status absensi tidak berubah dan catatan wajib diisi.</p>

                <form method="POST" action="{{ route('leave-requests.approve', $leave->id) }}" data-hb-loading>
                    @csrf
                    <label for="admin_note" class="form-label fw-semibold">Catatan admin</label>
                    <textarea id="admin_note" name="admin_note" rows="3" maxlength="500"
                              class="form-control @error('admin_note') is-invalid @enderror"
                              placeholder="Opsional saat menyetujui, wajib saat menolak.">{{ old('admin_note') }}</textarea>
                    @error('admin_note')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror

                    <div class="d-grid gap-2 mt-3">
                        <button type="submit" class="hb-btn hb-btn-primary"><i class="bi bi-check2-circle me-1"></i> Setujui</button>
                        <button type="submit" class="hb-btn hb-btn-danger" formaction="{{ route('leave-requests.reject', $leave->id) }}"><i class="bi bi-x-circle me-1"></i> Tolak</button>
                    </div>
                </form>
            @else
                <div class="hb-alert hb-alert-info mb-0">
                    <i class="bi bi-lock-fill"></i>
                    <span>Pengajuan ini sudah {{ $leave->status === 'approved' ? 'disetujui' : 'ditolak' }} dan tidak dapat diubah, agar riwayat persetujuan tetap utuh.</span>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
