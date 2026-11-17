<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Kehadiran - {{ $period['label'] }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/images/logo.svg') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/components.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/reports.css') }}">
    <style>@page { size: A4 landscape; margin: 10mm; }</style>
</head>
<body class="rp-print-body" data-autoprint>
@php
    $t = $data['totals'];
    $company = \App\Models\Company::current();
    $rate = $t['total'] > 0 ? ($t['present'] + $t['late']) / $t['total'] * 100 : 0;
@endphp

<div class="rp-print">
    <div class="no-print rp-toolbar">
        <button type="button" class="hb-btn hb-btn-primary" data-print><i class="bi bi-printer me-1"></i> Cetak</button>
        <button type="button" class="hb-btn hb-btn-light" data-close>Tutup</button>
    </div>

    <header class="rp-print-head">
        <div>
            <h1>Laporan Kehadiran</h1>
            <div>{{ $company?->name ?? config('app.name') }}</div>
        </div>
        <div class="text-end small">
            <div>Periode: <strong>{{ $period['label'] }}</strong></div>
            <div>{{ $period['from']->format('d/m/Y') }} - {{ $period['to']->format('d/m/Y') }}</div>
            <div>Filter: {{ $filterText }}</div>
            <div>Dicetak {{ now()->format('d/m/Y H:i') }}</div>
        </div>
    </header>

    @if ($t['unrecorded'] > 0)
        <p class="small mb-2">Catatan: {{ $t['unrecorded'] }} hari kerja tanpa catatan absensi dihitung Tidak Hadir/Izin.</p>
    @endif

    <table class="table table-sm rp-table">
        <thead>
            <tr>
                <th>#</th><th>NIK</th><th>Nama</th><th>Jabatan</th><th>Departemen</th>
                <th class="text-end">Hari Kerja</th><th class="text-end">Hadir</th><th class="text-end">Terlambat</th>
                <th class="text-end">Tdk Hadir</th><th class="text-end">Izin</th><th class="text-end">Sakit</th><th class="text-end">% Hadir</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($data['rows'] as $r)
                @php($c = $r['counts'])
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $r['employee']->employee_number }}</td>
                    <td>{{ $r['employee']->full_name }}</td>
                    <td>{{ $r['employee']->position?->name ?? '-' }}</td>
                    <td>{{ $r['employee']->department?->name ?? '-' }}</td>
                    <td class="text-end">{{ $c['total'] }}</td>
                    <td class="text-end">{{ $c['present'] }}</td>
                    <td class="text-end">{{ $c['late'] }}</td>
                    <td class="text-end">{{ $c['absent'] }}</td>
                    <td class="text-end">{{ $c['leave'] }}</td>
                    <td class="text-end">{{ $c['sick'] }}</td>
                    <td class="text-end">{{ number_format($r['rate'] * 100, 1, ',', '.') }}%</td>
                </tr>
            @empty
                <tr><td colspan="12" class="text-center text-muted py-3">Tidak ada data pada periode dan filter ini.</td></tr>
            @endforelse
        </tbody>
        @if ($data['rows']->isNotEmpty())
            <tfoot>
                <tr>
                    <th colspan="5">TOTAL ({{ $t['employees'] }} karyawan)</th>
                    <th class="text-end">{{ $t['total'] }}</th><th class="text-end">{{ $t['present'] }}</th><th class="text-end">{{ $t['late'] }}</th>
                    <th class="text-end">{{ $t['absent'] }}</th><th class="text-end">{{ $t['leave'] }}</th><th class="text-end">{{ $t['sick'] }}</th>
                    <th class="text-end">{{ number_format($rate, 1, ',', '.') }}%</th>
                </tr>
            </tfoot>
        @endif
    </table>
</div>

<script src="{{ asset('assets/js/reports.js') }}"></script>
</body>
</html>
