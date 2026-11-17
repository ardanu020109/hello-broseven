@extends('layouts.app')

@section('title', 'Payroll '.$label)
@section('page_title', 'Payroll '.$label)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('payroll.index') }}">Data Salary</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $label }}</li>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/crud.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/reports.css') }}">
@endpush

@section('page_actions')
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('payroll.export', [$period->id, 'xlsx']) }}" class="hb-btn hb-btn-secondary"><i class="bi bi-file-earmark-excel me-1"></i> Excel</a>
        <a href="{{ route('payroll.export', [$period->id, 'pdf']) }}" class="hb-btn hb-btn-secondary"><i class="bi bi-file-earmark-pdf me-1"></i> PDF</a>
    </div>
@endsection

@section('content')
@php
    $basisLabel = \App\Services\PayrollService::BASES[$period->deduction_basis] ?? $period->deduction_basis;
    $ruleNames = ['present' => 'Hadir', 'late' => 'Terlambat', 'absent' => 'Tidak Hadir', 'leave' => 'Izin', 'sick' => 'Sakit'];
@endphp

<div class="hb-card hb-panel mb-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h2 class="h5 mb-0">{{ $label }}</h2>
                <x-status-badge :status="$period->status" />
            </div>
            <div class="small text-muted">
                {{ $period->start_date->format('d/m/Y') }} - {{ $period->end_date->format('d/m/Y') }}
                &middot; dasar potongan: {{ $basisLabel }}
                &middot; dibuat oleh {{ $period->creator?->name ?? '-' }}
            </div>
            @if ($period->finalized_at)
                <div class="small text-muted">Final: {{ $period->finalized_at->format('d/m/Y H:i') }} oleh {{ $period->finalizer?->name ?? '-' }}</div>
            @endif
            @if ($period->paid_at)
                <div class="small text-muted">Dibayar: {{ $period->paid_at->format('d/m/Y H:i') }}</div>
            @endif
        </div>

        <div class="d-flex flex-wrap gap-2">
            @if ($period->status === 'draft')
                <button type="button" class="hb-btn hb-btn-light"
                        data-bs-toggle="modal" data-bs-target="#hbConfirm"
                        data-action="{{ route('payroll.recalculate', $period->id) }}" data-method="POST"
                        data-tone="primary" data-btn="Hitung ulang"
                        data-title="Hitung ulang payroll?"
                        data-message="Seluruh angka Draft dihitung ulang dengan data absensi, aturan potongan, dan dasar potongan terbaru.">
                    <i class="bi bi-arrow-repeat me-1"></i> Hitung Ulang
                </button>

                @if ($ended)
                    <button type="button" class="hb-btn hb-btn-primary"
                            data-bs-toggle="modal" data-bs-target="#hbConfirm"
                            data-action="{{ route('payroll.finalize', $period->id) }}" data-method="POST"
                            data-tone="primary" data-btn="Finalisasi"
                            data-title="Finalisasi payroll?"
                            data-message="Angka dihitung ulang dengan data absensi terbaru lalu dikunci. Setelah final, payroll dan absensi periode ini tidak dapat diubah lagi.">
                        <i class="bi bi-lock me-1"></i> Finalisasi
                    </button>
                @else
                    <button type="button" class="hb-btn hb-btn-primary" disabled title="Tersedia setelah hari terakhir periode lewat"><i class="bi bi-lock me-1"></i> Finalisasi</button>
                @endif

                <button type="button" class="hb-btn hb-btn-danger"
                        data-bs-toggle="modal" data-bs-target="#hbConfirm"
                        data-action="{{ route('payroll.destroy', $period->id) }}" data-method="DELETE"
                        data-tone="danger" data-btn="Hapus"
                        data-title="Hapus payroll Draft?"
                        data-message="Seluruh perhitungan periode ini akan dihapus. Anda dapat membuatnya lagi nanti.">
                    <i class="bi bi-trash3 me-1"></i> Hapus
                </button>
            @elseif ($period->status === 'finalized')
                <button type="button" class="hb-btn hb-btn-primary"
                        data-bs-toggle="modal" data-bs-target="#hbConfirm"
                        data-action="{{ route('payroll.paid', $period->id) }}" data-method="POST"
                        data-tone="primary" data-btn="Tandai dibayar"
                        data-title="Tandai sebagai dibayar?"
                        data-message="Seluruh payroll periode ini ditandai Dibayar. Tindakan ini tidak dapat dibatalkan.">
                    <i class="bi bi-cash-coin me-1"></i> Tandai Dibayar
                </button>
            @endif
        </div>
    </div>
</div>

@if ($period->status === 'draft')
    <div class="hb-alert hb-alert-info mb-3">
        <i class="bi bi-info-circle-fill"></i>
        <span>
            Ini masih <strong>Draft</strong>: data absensi dapat berubah setelah perhitungan, jadi gunakan Hitung Ulang untuk memperbarui.
            @if ($ended) Saat Finalisasi, angka dihitung ulang otomatis.
            @else Periode masih berjalan: hari setelah hari ini belum dihitung, dan Finalisasi tersedia setelah hari terakhir periode lewat. @endif
        </span>
    </div>
@endif
{{-- @if ((int) $totals->unrecorded > 0)
    <div class="hb-alert hb-alert-info mb-3">
        <i class="bi bi-exclamation-circle-fill"></i>
        <span>{{ $totals->unrecorded }} hari kerja tanpa catatan absensi dihitung Tidak Hadir (atau Izin/Sakit jika ada izin disetujui). Periksa Data Absensi dan jalankan <code>attendance:mark-absent --days=31</code>, lalu Hitung Ulang jika perlu.</span>
    </div>
@endif --}}

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><x-stat-card icon="bi-people" label="Karyawan" :value="$totals->employees" tone="primary" /></div>
    <div class="col-6 col-xl-3"><x-stat-card icon="bi-wallet2" label="Gaji Kotor" :value="\App\Support\Format::rupiah($totals->gross)" tone="secondary" /></div>
    <div class="col-6 col-xl-3"><x-stat-card icon="bi-scissors" label="Total Potongan" :value="\App\Support\Format::rupiah($totals->deduction)" tone="danger" /></div>
    <div class="col-6 col-xl-3"><x-stat-card icon="bi-cash-coin" label="Gaji Bersih" :value="\App\Support\Format::rupiah($totals->net)" tone="dark" /></div>
</div>

<div class="hb-card hb-panel mb-3">
    <form method="GET" class="hb-filters">
        <div class="hb-search">
            <i class="bi bi-search"></i>
            <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Cari nama atau NIK internal..." aria-label="Pencarian">
        </div>
        <button type="submit" class="hb-btn hb-btn-secondary"><i class="bi bi-search me-1"></i> Cari</button>
        @if (request()->filled('q'))
            <a href="{{ route('payroll.show', $period->id) }}" class="hb-btn hb-btn-light">Reset</a>
        @endif
    </form>

    @if ($payrolls->isEmpty())
        <div class="hb-empty"><i class="bi bi-cash-coin"></i><span>Tidak ada data yang cocok.</span></div>
    @else
        <div class="table-responsive">
            <table class="table hb-table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Karyawan</th><th>Jabatan</th><th class="text-end">Gaji/Hari</th><th class="text-end">Hari</th>
                        <th class="text-end" title="Hadir">H</th><th class="text-end" title="Terlambat">T</th><th class="text-end" title="Tidak Hadir">A</th>
                        <th class="text-end" title="Izin">I</th><th class="text-end" title="Sakit">S</th>
                        <th class="text-end">Gaji Kotor</th><th class="text-end">Potongan</th><th class="text-end">Gaji Bersih</th><th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($payrolls as $p)
                        <tr>
                            <td>
                                <a href="{{ route('payroll.employee', [$period->id, $p->id]) }}" class="fw-semibold text-decoration-none">{{ $p->employee_name }}</a>
                                <div class="small text-muted">{{ $p->employee_number }}</div>
                            </td>
                            <td>{{ $p->position_name }}</td>
                            <td class="text-end text-nowrap">{{ \App\Support\Format::rupiah($p->daily_salary) }}</td>
                            <td class="text-end">{{ $p->total_working_days }}</td>
                            <td class="text-end">{{ $p->present_days }}</td>
                            <td class="text-end">{{ $p->late_days }}</td>
                            <td class="text-end">{{ $p->absent_days }}@if ($p->unrecorded_days > 0)<span class="rp-flag" title="{{ $p->unrecorded_days }} hari tanpa catatan absensi">*</span>@endif</td>
                            <td class="text-end">{{ $p->leave_days }}</td>
                            <td class="text-end">{{ $p->sick_days }}</td>
                            <td class="text-end text-nowrap">{{ \App\Support\Format::rupiah($p->gross_salary) }}</td>
                            <td class="text-end text-nowrap">{{ \App\Support\Format::rupiah($p->total_deduction) }}</td>
                            <td class="text-end text-nowrap fw-semibold">{{ \App\Support\Format::rupiah($p->net_salary) }}</td>
                            <td>
                                <div class="hb-actions">
                                    <a href="{{ route('payroll.employee', [$period->id, $p->id]) }}" class="hb-icon-action" title="Rincian" aria-label="Rincian"><i class="bi bi-list-ul"></i></a>
                                    <a href="{{ route('payroll.slip', [$period->id, $p->id]) }}" target="_blank" rel="noopener" class="hb-icon-action" title="Slip gaji" aria-label="Slip gaji"><i class="bi bi-receipt"></i></a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="small text-muted mt-2 mb-0">H = Hadir, T = Terlambat, A = Tidak Hadir, I = Izin, S = Sakit. <span class="rp-flag">*</span> termasuk hari tanpa catatan absensi.</p>
    @endif

    <x-pagination :paginator="$payrolls" />
</div>

<div class="hb-card hb-panel">
    <h2 class="hb-panel-title mb-3">Aturan Potongan yang Dipakai (Snapshot)</h2>
    @if ($rules)
        <div class="table-responsive">
            <table class="table hb-table align-middle mb-0">
                <thead><tr><th>Status</th><th>Nama</th><th class="text-end">Potongan (%)</th><th class="text-end">Potongan Nominal</th><th>Dipakai</th></tr></thead>
                <tbody>
                    @foreach ($rules as $key => $rule)
                        <tr>
                            <td>{{ $ruleNames[$key] ?? $key }}</td>
                            <td>{{ $rule['name'] }}</td>
                            <td class="text-end">{{ \App\Support\Format::percent($rule['percent']) }}</td>
                            <td class="text-end">{{ \App\Support\Format::rupiah($rule['fixed']) }}</td>
                            <td class="small text-muted">{{ $period->deduction_basis === 'fixed_amount' ? 'Nominal' : 'Persentase' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="hb-empty"><i class="bi bi-percent"></i><span>Snapshot aturan tidak tersedia.</span></div>
    @endif
</div>

<x-confirm-modal />
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/crud.js') }}"></script>
    <script src="{{ asset('assets/js/reports.js') }}"></script>
@endpush
