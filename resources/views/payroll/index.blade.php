@extends('layouts.app')

@section('title', 'Data Salary')
@section('page_title', 'Data Salary')

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Data Salary</li>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/crud.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/reports.css') }}">
@endpush

@section('page_actions')
    <button type="button" class="hb-btn hb-btn-primary" data-bs-toggle="modal" data-bs-target="#hbPeriodModal">
        <i class="bi bi-plus-lg me-1"></i> Buat Payroll Bulanan
    </button>
@endsection

@section('content')
{{-- <div class="hb-alert hb-alert-info mb-3">
    <i class="bi bi-info-circle-fill"></i>
    <span>Gaji dihitung per hari kerja: <strong>gaji harian jabatan &times; (100% &minus; potongan)</strong>. Hanya hari kerja pada jadwal yang dihitung, hari libur tidak dihitung, dan izin sah hanya yang sudah disetujui. Hasil disimpan sebagai snapshot sehingga perubahan master tidak mengubah payroll yang sudah dibuat. Sebelum membuat payroll, jalankan <code>php artisan attendance:mark-absent --days=31</code> agar status Tidak Hadir terbentuk.</span>
</div> --}}

<div class="row g-3">
    <div class="{{ $canConfigure ? 'col-12 col-xl-8' : 'col-12' }}">
        <div class="hb-card hb-panel">
            <h2 class="hb-panel-title mb-3">Periode Payroll</h2>

            @if ($periods->isEmpty())
                <div class="hb-empty"><i class="bi bi-cash-coin"></i><span>Belum ada payroll. Klik <strong>Buat Payroll Bulanan</strong>.</span></div>
            @else
                <div class="table-responsive">
                    <table class="table hb-table align-middle mb-0">
                        <thead>
                            <tr><th>Periode</th><th>Rentang</th><th class="text-end">Karyawan</th><th class="text-end">Total Gaji Bersih</th><th>Status</th><th class="text-end">Aksi</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($periods as $p)
                                <tr>
                                    <td class="fw-semibold"><a href="{{ route('payroll.show', $p->id) }}" class="text-decoration-none">{{ \App\Support\Format::periodLabel($p->year, $p->month) }}</a></td>
                                    <td class="small text-nowrap">{{ $p->start_date->format('d/m/Y') }} - {{ $p->end_date->format('d/m/Y') }}</td>
                                    <td class="text-end">{{ $p->payrolls_count }}</td>
                                    <td class="text-end fw-semibold">{{ \App\Support\Format::rupiah($p->net_total) }}</td>
                                    <td><x-status-badge :status="$p->status" /></td>
                                    <td>
                                        <div class="hb-actions">
                                            <a href="{{ route('payroll.show', $p->id) }}" class="hb-icon-action" title="Buka" aria-label="Buka"><i class="bi bi-eye"></i></a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <x-pagination :paginator="$periods" />
        </div>
    </div>

    @if ($canConfigure)
        <div class="col-12 col-xl-4">
            <div class="hb-card hb-panel">
                <h2 class="hb-panel-title mb-1">Dasar Potongan</h2>
                <p class="small text-muted">Hanya Super Admin. Berlaku untuk payroll baru dan hitung ulang Draft.</p>
                <form method="POST" action="{{ route('payroll.settings') }}" data-hb-loading>
                    @csrf
                    @foreach ($bases as $value => $label)
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="payroll_deduction_basis" id="basis_{{ $value }}" value="{{ $value }}" @checked($basis === $value)>
                            <label class="form-check-label" for="basis_{{ $value }}">
                                <strong>{{ $label }}</strong>
                                <span class="d-block small text-muted">
                                    @if ($value === 'daily_salary') Potongan = persen (Data Master &gt; Potongan Gaji) &times; gaji harian.
                                    @else Potongan = nominal tetap per kejadian, maksimal sebesar gaji harian. @endif
                                </span>
                            </label>
                        </div>
                    @endforeach
                    @error('payroll_deduction_basis') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    <button type="submit" class="hb-btn hb-btn-primary mt-2"><i class="bi bi-check2 me-1"></i> Simpan</button>
                    <a href="{{ route('deduction-rules.index') }}" class="hb-btn hb-btn-light mt-2">Atur potongan</a>
                </form>
            </div>
        </div>
    @endif
</div>

{{-- ===== Modal buat periode ===== --}}
<div class="modal fade" id="hbPeriodModal" tabindex="-1" aria-labelledby="hbPeriodTitle" aria-hidden="true" data-reopen="{{ $errors->has('month') ? '1' : '0' }}">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content hb-modal">
            <form method="POST" action="{{ route('payroll.store') }}" data-hb-loading data-hb-overlay>
                @csrf
                <div class="modal-header">
                    <h2 class="modal-title h5" id="hbPeriodTitle">Buat Payroll Bulanan</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <label for="periodMonth" class="form-label fw-semibold">Bulan <span class="text-danger">*</span></label>
                    <input type="month" id="periodMonth" name="month" min="2020-01" max="{{ $maxMonth }}" required
                           value="{{ old('month', $defaultMonth) }}" class="form-control @error('month') is-invalid @enderror">
                    @error('month') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    <div class="form-text">Payroll dibuat sebagai <strong>Draft</strong> dan dapat dihitung ulang sampai difinalisasi. Hari setelah hari ini tidak dihitung.</div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="hb-btn hb-btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="hb-btn hb-btn-primary"><i class="bi bi-calculator me-1"></i> Hitung</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/reports.js') }}"></script>
@endpush
