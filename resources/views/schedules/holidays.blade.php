@extends('layouts.app')

@section('title', 'Hari Libur')
@section('page_title', 'Data Jadwal')

@section('breadcrumb')
    <li class="breadcrumb-item">Data Jadwal</li>
    <li class="breadcrumb-item active" aria-current="page">Hari Libur</li>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/crud.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/schedules.css') }}">
@endpush

@section('page_actions')
    <button type="button" class="hb-btn hb-btn-primary" data-bs-toggle="modal" data-bs-target="#hbFormModal" data-mode="create">
        <i class="bi bi-plus-lg me-1"></i> Tambah Hari Libur
    </button>
@endsection

@section('content')
@php
    $reopenMode = old('_mode');
    $reopenId = old('_id');
    $filtered = request()->filled('q') || (request('year') && (string) request('year') !== (string) now()->year);
@endphp

@include('schedules.partials.tabs')

<div class="hb-alert hb-alert-info mb-3">
    <i class="bi bi-info-circle-fill"></i>
    <span>Pada hari libur, karyawan tidak dianggap absent dan tidak dihitung sebagai hari kerja dalam payroll. Daftar ini diisi manual, termasuk cuti bersama, karena tanggalnya ditetapkan pemerintah dan bisa berubah setiap tahun.</span>
</div>

<div class="hb-card hb-panel">
    <form method="GET" class="hb-filters">
        <div class="hb-search">
            <i class="bi bi-search"></i>
            <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Cari nama hari libur..." aria-label="Pencarian">
        </div>
        <select name="year" class="form-select" aria-label="Filter tahun">
            @foreach ($years as $y)
                <option value="{{ $y }}" @selected($year === $y)>{{ $y }}</option>
            @endforeach
            <option value="all" @selected($year === null)>Semua tahun</option>
        </select>
        <button type="submit" class="hb-btn hb-btn-secondary"><i class="bi bi-funnel me-1"></i> Terapkan</button>
        @if ($filtered)
            <a href="{{ route('schedules.holidays.index') }}" class="hb-btn hb-btn-light">Reset</a>
        @endif
    </form>

    @if ($holidays->isEmpty())
        <div class="hb-empty">
            <i class="bi bi-calendar-heart"></i>
            <span>{{ request()->filled('q') ? 'Tidak ada hari libur yang cocok dengan pencarian.' : 'Belum ada hari libur pada periode ini.' }}</span>
        </div>
    @else
        <div class="table-responsive">
            <table class="table hb-table align-middle mb-0">
                <thead><tr><th>Tanggal</th><th>Nama</th><th>Keterangan</th><th>Jenis</th><th class="text-end">Aksi</th></tr></thead>
                <tbody>
                    @foreach ($holidays as $h)
                        @php
                            $payload = [
                                'holiday_date' => $h->holiday_date->format('Y-m-d'),
                                'name' => $h->name,
                                'description' => $h->description,
                                'is_national' => (bool) $h->is_national,
                            ];
                        @endphp
                        <tr>
                            <td class="text-nowrap">
                                <div class="fw-semibold">{{ $h->holiday_date->format('d/m/Y') }}</div>
                                <div class="small text-muted">{{ $h->holiday_date->copy()->locale('id')->translatedFormat('l') }}</div>
                            </td>
                            <td class="fw-semibold">{{ $h->name }}</td>
                            <td><span class="text-muted">{{ \Illuminate\Support\Str::limit((string) $h->description, 70) ?: '-' }}</span></td>
                            <td><span class="hb-pill {{ $h->is_national ? 'hb-pill-info' : 'hb-pill-muted' }}">{{ $h->is_national ? 'Nasional' : 'Perusahaan' }}</span></td>
                            <td>
                                <div class="hb-actions">
                                    <button type="button" class="hb-icon-action" title="Ubah" aria-label="Ubah"
                                            data-bs-toggle="modal" data-bs-target="#hbFormModal"
                                            data-mode="edit" data-id="{{ $h->id }}" data-record="{{ json_encode($payload) }}">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <button type="button" class="hb-icon-action is-danger" title="Hapus" aria-label="Hapus"
                                            data-bs-toggle="modal" data-bs-target="#hbConfirm"
                                            data-action="{{ route('schedules.holidays.destroy', $h->id) }}" data-method="DELETE"
                                            data-tone="danger" data-btn="Hapus"
                                            data-title="Hapus hari libur?"
                                            data-message="{{ $h->name }} ({{ $h->holiday_date->format('d/m/Y') }}) akan dihapus. Tanggal tersebut kembali dihitung sebagai hari kerja biasa.">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <x-pagination :paginator="$holidays" />
</div>

{{-- ===== Modal tambah / ubah ===== --}}
<div class="modal fade" id="hbFormModal" tabindex="-1" aria-labelledby="hbFormTitle" aria-hidden="true"
     data-singular="Hari Libur" data-reopen-mode="{{ $reopenMode }}" data-reopen-id="{{ $reopenId }}">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content hb-modal">
            <form method="POST" action="{{ route('schedules.holidays.store') }}" data-hb-loading
                  data-store-url="{{ route('schedules.holidays.store') }}"
                  data-update-url="{{ route('schedules.holidays.update', ['id' => 0]) }}">
                @csrf
                <input type="hidden" name="_method" value="PUT" data-form-method disabled>
                <input type="hidden" name="_mode" value="create" data-mode-input>
                <input type="hidden" name="_id" value="" data-id-input>

                <div class="modal-header">
                    <h2 class="modal-title h5" id="hbFormTitle" data-form-title>Tambah Hari Libur</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold" for="f_holiday_date">Tanggal <span class="text-danger">*</span></label>
                            <input type="date" class="form-control @error('holiday_date') is-invalid @enderror" id="f_holiday_date" name="holiday_date"
                                   value="{{ $reopenMode ? old('holiday_date') : '' }}" data-field="holiday_date" data-default="" required>
                            @error('holiday_date') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold" for="f_name">Nama hari libur <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" id="f_name" name="name" maxlength="150"
                                   value="{{ $reopenMode ? old('name') : '' }}" data-field="name" data-default="" required autocomplete="off">
                            @error('name') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold" for="f_description">Keterangan</label>
                            <input type="text" class="form-control @error('description') is-invalid @enderror" id="f_description" name="description" maxlength="255"
                                   value="{{ $reopenMode ? old('description') : '' }}" data-field="description" data-default="" autocomplete="off">
                            @error('description') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <input type="hidden" name="is_national" value="0">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="f_is_national" name="is_national" value="1"
                                       data-field="is_national" data-default="1" {{ ($reopenMode ? old('is_national', '0') : '1') ? 'checked' : '' }}>
                                <label class="form-check-label" for="f_is_national">Libur nasional (matikan untuk libur khusus perusahaan)</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-0">
                    <button type="button" class="hb-btn hb-btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="hb-btn hb-btn-primary"><i class="bi bi-check2 me-1"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<x-confirm-modal />
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/crud.js') }}"></script>
@endpush
