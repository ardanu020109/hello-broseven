@extends('layouts.app')

@section('title', 'Data Wajah')
@section('page_title', 'Data Wajah')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('employees.index') }}">Data Karyawan</a></li>
    <li class="breadcrumb-item active" aria-current="page">Data Wajah</li>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/crud.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/attendance.css') }}">
@endpush

@section('content')
@php
    $filtered = request()->filled('q') || $state !== '';
    $date = fn ($d) => $d ? $d->format('d/m/Y H:i') : '-';
@endphp

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-4">
        <x-stat-card icon="bi-person-check" label="Wajah Terdaftar" :value="$summary['registered'].' / '.$summary['total']" tone="primary" hint="Karyawan aktif" />
    </div>
    <div class="col-6 col-xl-4">
        <x-stat-card icon="bi-hourglass-split" label="Menunggu Persetujuan" :value="$summary['pending']" tone="warning" />
    </div>
    <div class="col-12 col-xl-4">
        <x-stat-card icon="bi-person-exclamation" label="Belum Terdaftar" :value="$summary['unregistered']" tone="danger" hint="Tidak dapat absen jika Face Recognition aktif" />
    </div>
</div>

<div class="hb-alert hb-alert-info mb-3">
    <i class="bi bi-shield-lock-fill"></i>
    <span>Data numerik wajah disimpan terenkripsi dan tidak pernah ditampilkan di halaman mana pun. Halaman ini hanya menampilkan status, serta memungkinkan Anda menyetujui, menolak (data pengajuan dihapus), atau menonaktifkan.</span>
</div>

<div class="hb-card hb-panel">
    <form method="GET" class="hb-filters">
        <div class="hb-search">
            <i class="bi bi-search"></i>
            <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Cari nama, NIK internal, atau email..." aria-label="Pencarian">
        </div>
        <select name="state" class="form-select" aria-label="Filter status wajah">
            <option value="">Semua status</option>
            <option value="pending" @selected($state === 'pending')>Menunggu persetujuan</option>
            <option value="active" @selected($state === 'active')>Terdaftar</option>
            <option value="inactive" @selected($state === 'inactive')>Dinonaktifkan</option>
            <option value="none" @selected($state === 'none')>Belum terdaftar</option>
        </select>
        <button type="submit" class="hb-btn hb-btn-secondary"><i class="bi bi-funnel me-1"></i> Terapkan</button>
        @if ($filtered)
            <a href="{{ route('faces.index') }}" class="hb-btn hb-btn-light">Reset</a>
        @endif
    </form>

    @if ($employees->isEmpty())
        <div class="hb-empty">
            <i class="bi bi-person-bounding-box"></i>
            <span>{{ $filtered ? 'Tidak ada karyawan yang cocok dengan filter.' : 'Belum ada karyawan aktif.' }}</span>
        </div>
    @else
        <div class="table-responsive">
            <table class="table hb-table align-middle mb-0">
                <thead>
                    <tr><th>Karyawan</th><th>Departemen</th><th>Status Wajah</th><th>Terakhir</th><th class="text-end">Aksi</th></tr>
                </thead>
                <tbody>
                    @foreach ($employees as $e)
                        @php
                            $active = $e->faceDescriptors->firstWhere('status', 'active');
                            $pending = $e->faceDescriptors->firstWhere('status', 'pending_approval');
                            $inactive = $e->faceDescriptors->firstWhere('status', 'inactive');
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('employees.show', $e->id) }}" class="fw-semibold text-decoration-none">{{ $e->full_name }}</a>
                                <div class="small text-muted">{{ $e->employee_number }} &middot; {{ $e->position?->name }}</div>
                            </td>
                            <td>{{ $e->department?->name ?? '-' }}</td>
                            <td>
                                @if ($active)
                                    <span class="hb-st hb-st-present">Terdaftar</span>
                                @elseif ($inactive)
                                    <span class="hb-st hb-st-absent">Dinonaktifkan</span>
                                @else
                                    <span class="hb-pill hb-pill-muted">Belum terdaftar</span>
                                @endif
                                @if ($pending)
                                    <span class="hb-st hb-st-pending">Registrasi ulang menunggu</span>
                                @endif
                            </td>
                            <td class="small text-nowrap">
                                @if ($pending) Diajukan {{ $date($pending->registered_at) }}
                                @elseif ($active) {{ $date($active->approved_at ?? $active->registered_at) }}
                                @elseif ($inactive) Nonaktif {{ $date($inactive->deactivated_at) }}
                                @else - @endif
                            </td>
                            <td>
                                <div class="hb-actions">
                                    @if ($pending)
                                        <button type="button" class="hb-icon-action" title="Setujui" aria-label="Setujui"
                                                data-bs-toggle="modal" data-bs-target="#hbConfirm"
                                                data-action="{{ route('faces.approve', $pending->id) }}" data-method="POST"
                                                data-tone="primary" data-btn="Setujui"
                                                data-title="Setujui registrasi wajah?"
                                                data-message="Data wajah baru {{ $e->full_name }} akan aktif dan data lamanya dinonaktifkan.">
                                            <i class="bi bi-check-lg"></i>
                                        </button>
                                        <button type="button" class="hb-icon-action is-danger" title="Tolak" aria-label="Tolak"
                                                data-bs-toggle="modal" data-bs-target="#hbConfirm"
                                                data-action="{{ route('faces.reject', $pending->id) }}" data-method="POST"
                                                data-tone="danger" data-btn="Tolak"
                                                data-title="Tolak pengajuan?"
                                                data-message="Pengajuan wajah {{ $e->full_name }} akan ditolak dan datanya dihapus.">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    @endif
                                    @if ($active)
                                        <button type="button" class="hb-icon-action is-danger" title="Nonaktifkan data wajah" aria-label="Nonaktifkan data wajah"
                                                data-bs-toggle="modal" data-bs-target="#hbConfirm"
                                                data-action="{{ route('faces.deactivate', $active->id) }}" data-method="POST"
                                                data-tone="danger" data-btn="Nonaktifkan"
                                                data-title="Nonaktifkan data wajah?"
                                                data-message="{{ $e->full_name }} tidak dapat absen sampai mendaftarkan wajah kembali dan disetujui admin.">
                                            <i class="bi bi-person-slash"></i>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <x-pagination :paginator="$employees" />
</div>

<x-confirm-modal />
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/crud.js') }}"></script>
@endpush
