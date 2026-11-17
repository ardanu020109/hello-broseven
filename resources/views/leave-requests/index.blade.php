@extends('layouts.app')

@section('title', 'Request Izin')
@section('page_title', 'Request Izin')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/crud.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/records.css') }}">
@endpush

@section('content')
@php
    $tabs = ['' => 'Semua', 'pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'];
    $query = request()->except(['status', 'page']);
@endphp

<div class="hb-chips">
    @foreach ($tabs as $key => $label)
        <a href="{{ route('leave-requests.index', $query + ($key !== '' ? ['status' => $key] : [])) }}"
           class="hb-chip hb-chip-link {{ $status === $key ? 'is-active' : '' }}">
            {{ $label }}
            <strong>{{ $key === '' ? $counts->sum() : ($counts[$key] ?? 0) }}</strong>
        </a>
    @endforeach
</div>

<div class="hb-card hb-panel">
    <form method="GET" class="hb-filters">
        @if ($status !== '') <input type="hidden" name="status" value="{{ $status }}"> @endif
        <div class="hb-search">
            <i class="bi bi-search"></i>
            <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Cari nama, NIK internal, email..." aria-label="Pencarian">
        </div>
        <select name="leave_type_id" class="form-select" aria-label="Filter jenis izin">
            <option value="">Semua jenis izin</option>
            @foreach ($types as $t)
                <option value="{{ $t->id }}" @selected((string) request('leave_type_id') === (string) $t->id)>{{ $t->name }}</option>
            @endforeach
        </select>
        <input type="date" name="from" value="{{ request('from') }}" class="form-control" aria-label="Dari tanggal">
        <input type="date" name="to" value="{{ request('to') }}" class="form-control" aria-label="Sampai tanggal">
        <button type="submit" class="hb-btn hb-btn-secondary"><i class="bi bi-funnel me-1"></i> Terapkan</button>
        @if ($filtered)
            <a href="{{ route('leave-requests.index') }}" class="hb-btn hb-btn-light">Reset</a>
        @endif
    </form>

    @if ($requests->isEmpty())
        <div class="hb-empty">
            <i class="bi bi-envelope-x"></i>
            <span>{{ $filtered ? 'Tidak ada pengajuan yang cocok dengan filter.' : 'Belum ada pengajuan izin.' }}</span>
        </div>
    @else
        <div class="table-responsive">
            <table class="table hb-table align-middle mb-0">
                <thead>
                    <tr><th>Karyawan</th><th>Jenis</th><th>Periode</th><th>Hari</th><th>Alasan</th><th>Lampiran</th><th>Status</th><th class="text-end">Aksi</th></tr>
                </thead>
                <tbody>
                    @foreach ($requests as $l)
                        <tr>
                            <td>
                                <a href="{{ route('leave-requests.show', $l->id) }}" class="fw-semibold text-decoration-none">{{ $l->employee?->full_name ?? '-' }}</a>
                                <div class="small text-muted">{{ $l->employee?->employee_number }} &middot; {{ $l->employee?->position?->name }}</div>
                            </td>
                            <td>{{ $l->leaveType?->name ?? '-' }}</td>
                            <td class="text-nowrap small">
                                {{ $l->start_date->format('d/m/Y') }}
                                @if (!$l->start_date->isSameDay($l->end_date)) - {{ $l->end_date->format('d/m/Y') }} @endif
                            </td>
                            <td>{{ $l->total_days }}</td>
                            <td><span class="text-muted">{{ \Illuminate\Support\Str::limit($l->reason, 50) }}</span></td>
                            <td>
                                @if ($l->attachment)
                                    <a href="{{ route('leave-requests.attachment', $l->id) }}" target="_blank" rel="noopener"><i class="bi bi-paperclip"></i> Buka</a>
                                @else <span class="text-muted">-</span> @endif
                            </td>
                            <td><x-status-badge :status="$l->status" /></td>
                            <td>
                                <div class="hb-actions">
                                    <a href="{{ route('leave-requests.show', $l->id) }}" class="hb-icon-action" title="{{ $l->status === 'pending' ? 'Tinjau' : 'Detail' }}" aria-label="Detail">
                                        <i class="bi {{ $l->status === 'pending' ? 'bi-clipboard-check' : 'bi-eye' }}"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <x-pagination :paginator="$requests" />
</div>
@endsection
