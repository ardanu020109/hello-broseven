@extends('layouts.app')

@section('title', 'Rincian Gaji')
@section('page_title', 'Rincian Gaji')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('payroll.index') }}">Data Salary</a></li>
    <li class="breadcrumb-item"><a href="{{ route('payroll.show', $period->id) }}">{{ $label }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $payroll->employee_name }}</li>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/crud.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/reports.css') }}">
@endpush

@section('page_actions')
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('payroll.slip', [$period->id, $payroll->id]) }}" target="_blank" rel="noopener" class="hb-btn hb-btn-primary"><i class="bi bi-receipt me-1"></i> Slip Gaji</a>
        <a href="{{ route('payroll.slip.pdf', [$period->id, $payroll->id]) }}" target="_blank" rel="noopener" class="hb-btn hb-btn-secondary"><i class="bi bi-file-earmark-pdf me-1"></i> Slip PDF</a>
        <a href="{{ route('payroll.show', $period->id) }}" class="hb-btn hb-btn-light">Kembali</a>
    </div>
@endsection

@section('content')
<div class="row g-3 mb-3">
    <div class="col-12 col-xl-5">
        <div class="hb-card hb-panel h-100">
            <div class="hb-panel-head">
                <h2 class="hb-panel-title">{{ $payroll->employee_name }}</h2>
                <x-status-badge :status="$payroll->status" />
            </div>
            <dl class="hb-dl hb-dl-wide mb-0">
                <dt>NIK internal</dt><dd>{{ $payroll->employee_number }}</dd>
                <dt>Jabatan</dt><dd>{{ $payroll->position_name }}</dd>
                <dt>Departemen</dt><dd>{{ $payroll->department_name }}</dd>
                <dt>Periode</dt><dd>{{ $label }}</dd>
                <dt>Gaji harian</dt><dd>{{ \App\Support\Format::rupiah($payroll->daily_salary) }}</dd>
                <dt>Hari kerja</dt><dd>{{ $payroll->total_working_days }}</dd>
            </dl>
        </div>
    </div>
    <div class="col-12 col-xl-7">
        <div class="hb-card hb-panel h-100">
            <h2 class="hb-panel-title mb-3">Ringkasan per Status</h2>
            <div class="table-responsive">
                <table class="table hb-table align-middle mb-0">
                    <thead><tr><th>Status</th><th class="text-end">Hari</th><th class="text-end">Gaji Kotor</th><th class="text-end">Potongan</th><th class="text-end">Diterima</th></tr></thead>
                    <tbody>
                        @foreach ($groups as $g)
                            <tr>
                                <td><x-status-badge :status="$g['status']" /></td>
                                <td class="text-end">{{ $g['days'] }}</td>
                                <td class="text-end">{{ \App\Support\Format::rupiah($g['gross']) }}</td>
                                <td class="text-end">{{ \App\Support\Format::rupiah($g['deduction']) }}</td>
                                <td class="text-end">{{ \App\Support\Format::rupiah($g['net']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="fw-bold">
                            <td>Total</td>
                            <td class="text-end">{{ $payroll->total_working_days }}</td>
                            <td class="text-end">{{ \App\Support\Format::rupiah($payroll->gross_salary) }}</td>
                            <td class="text-end">{{ \App\Support\Format::rupiah($payroll->total_deduction) }}</td>
                            <td class="text-end">{{ \App\Support\Format::rupiah($payroll->net_salary) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="hb-card hb-panel">
    <h2 class="hb-panel-title mb-3">Rincian Harian</h2>
    <div class="table-responsive">
        <table class="table hb-table align-middle mb-0">
            <thead><tr><th>Tanggal</th><th>Hari</th><th>Status</th><th class="text-end">Gaji Harian</th><th class="text-end">Potongan</th><th class="text-end">Nominal</th><th class="text-end">Diterima</th><th>Catatan</th></tr></thead>
            <tbody>
                @foreach ($payroll->details as $d)
                    <tr>
                        <td class="text-nowrap fw-semibold">{{ $d->work_date->format('d/m/Y') }}</td>
                        <td>{{ $d->work_date->copy()->locale('id')->translatedFormat('l') }}</td>
                        <td><x-status-badge :status="$d->status" /></td>
                        <td class="text-end">{{ \App\Support\Format::rupiah($d->daily_salary) }}</td>
                        <td class="text-end">{{ \App\Support\Format::percent($d->deduction_percent) }}</td>
                        <td class="text-end">{{ \App\Support\Format::rupiah($d->deduction_amount) }}</td>
                        <td class="text-end fw-semibold">{{ \App\Support\Format::rupiah($d->net_amount) }}</td>
                        <td class="small text-muted">{{ $d->notes ?: '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
