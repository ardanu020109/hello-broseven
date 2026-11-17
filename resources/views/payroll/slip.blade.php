<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Slip Gaji - {{ $payroll->employee_name }} - {{ $label }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/images/logo.svg') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/components.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/reports.css') }}">
</head>
<body class="slip-page">
<div class="no-print rp-toolbar">
    <button type="button" class="hb-btn hb-btn-primary" data-print><i class="bi bi-printer me-1"></i> Cetak</button>
    <a href="{{ route('payroll.slip.pdf', [$period->id, $payroll->id]) }}" target="_blank" rel="noopener" class="hb-btn hb-btn-secondary"><i class="bi bi-file-earmark-pdf me-1"></i> Unduh PDF</a>
    <a href="{{ route('payroll.employee', [$period->id, $payroll->id]) }}" class="hb-btn hb-btn-light">Kembali</a>
</div>

<article class="slip">
    @if ($period->status === 'draft')
        <div class="slip-draft">DRAFT</div>
    @endif

    <header class="slip-head">
        <img src="{{ $company?->logo ? asset('storage/'.$company->logo) : asset('assets/images/logo.svg') }}" alt="Logo" class="slip-logo">
        <div>
            <h1>{{ $company?->name ?? config('app.name') }}</h1>
            @if ($company?->address)<div class="small text-muted">{{ $company->address }}</div>@endif
            @if ($company?->phone || $company?->email)
                <div class="small text-muted">{{ $company?->phone }}{{ $company?->phone && $company?->email ? ' | ' : '' }}{{ $company?->email }}</div>
            @endif
        </div>
    </header>

    <h2 class="slip-title">SLIP GAJI</h2>
    @if ($period->status === 'draft')
        <p class="text-center small" style="color:#a3301f">Slip sementara. Payroll belum final dan angka dapat berubah.</p>
    @endif

    <dl class="slip-info">
        <div><dt>Periode</dt><dd>{{ $label }}</dd></div>
        <div><dt>Nama</dt><dd>{{ $payroll->employee_name }}</dd></div>
        <div><dt>NIK Internal</dt><dd>{{ $payroll->employee_number }}</dd></div>
        <div><dt>Jabatan</dt><dd>{{ $payroll->position_name }}</dd></div>
        <div><dt>Departemen</dt><dd>{{ $payroll->department_name }}</dd></div>
        <div><dt>Gaji harian</dt><dd>{{ \App\Support\Format::rupiah($payroll->daily_salary) }}</dd></div>
    </dl>

    <table class="table table-sm slip-table">
        <thead><tr><th>Keterangan</th><th class="text-end">Hari</th><th class="text-end">Gaji Kotor</th><th class="text-end">Potongan</th><th class="text-end">Diterima</th></tr></thead>
        <tbody>
            @foreach ($groups as $g)
                <tr>
                    <td>{{ $g['label'] }}</td>
                    <td class="text-end">{{ $g['days'] }}</td>
                    <td class="text-end">{{ \App\Support\Format::rupiah($g['gross']) }}</td>
                    <td class="text-end">{{ \App\Support\Format::rupiah($g['deduction']) }}</td>
                    <td class="text-end">{{ \App\Support\Format::rupiah($g['net']) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <th>Total</th>
                <th class="text-end">{{ $payroll->total_working_days }}</th>
                <th class="text-end">{{ \App\Support\Format::rupiah($payroll->gross_salary) }}</th>
                <th class="text-end">{{ \App\Support\Format::rupiah($payroll->total_deduction) }}</th>
                <th class="text-end">{{ \App\Support\Format::rupiah($payroll->net_salary) }}</th>
            </tr>
        </tfoot>
    </table>

    <div class="slip-net">
        <span>Gaji bersih diterima</span>
        <strong>{{ \App\Support\Format::rupiah($payroll->net_salary) }}</strong>
    </div>

    <div class="slip-sign">
        <div><span>Penerima</span><i></i><small>{{ $payroll->employee_name }}</small></div>
        <div><span>HRD / Pembayar</span><i></i><small>&nbsp;</small></div>
    </div>

    <h3 class="slip-sub">Rincian Harian</h3>
    <table class="table table-sm slip-table slip-detail">
        <thead><tr><th>Tanggal</th><th>Hari</th><th>Status</th><th class="text-end">Potongan</th><th class="text-end">Diterima</th><th>Catatan</th></tr></thead>
        <tbody>
            @foreach ($payroll->details as $d)
                <tr>
                    <td>{{ $d->work_date->format('d/m/Y') }}</td>
                    <td>{{ $d->work_date->copy()->locale('id')->translatedFormat('l') }}</td>
                    <td>{{ \App\Support\Format::dayStatus($d->status) }}</td>
                    <td class="text-end">{{ \App\Support\Format::rupiah($d->deduction_amount) }} ({{ \App\Support\Format::percent($d->deduction_percent) }})</td>
                    <td class="text-end">{{ \App\Support\Format::rupiah($d->net_amount) }}</td>
                    <td class="small">{{ $d->notes ?: '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <footer class="small text-muted">Dokumen ini dibuat oleh sistem Hello BroSeven! pada {{ now()->format('d/m/Y H:i') }}.</footer>
</article>

<script src="{{ asset('assets/js/reports.js') }}"></script>
</body>
</html>
