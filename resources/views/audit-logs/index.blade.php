@extends('layouts.app')

@section('title', 'Audit Log')
@section('page_title', 'Audit Log')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/crud.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/records.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/settings.css') }}">
@endpush

@section('content')
<div class="hb-alert hb-alert-info mb-3">
    <i class="bi bi-shield-lock-fill"></i>
    <span>Jejak aktivitas penting aplikasi. Catatan tidak dapat diubah atau dihapus dari aplikasi. Nilai yang bersifat rahasia (password, token, data biometrik) disamarkan.</span>
</div>

<div class="hb-card hb-panel">
    <form method="GET" class="hb-filters">
        <div class="hb-search">
            <i class="bi bi-search"></i>
            <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Cari nama, email, username, atau IP..." aria-label="Pencarian">
        </div>
        <select name="action" class="form-select" aria-label="Filter aksi">
            <option value="">Semua aksi</option>
            @foreach ($actions as $a)
                <option value="{{ $a }}" @selected($action === $a)>{{ \App\Support\AuditLabels::action($a) }}</option>
            @endforeach
        </select>
        <input type="date" name="from" value="{{ request('from') }}" class="form-control" aria-label="Dari tanggal">
        <input type="date" name="to" value="{{ request('to') }}" class="form-control" aria-label="Sampai tanggal">
        <button type="submit" class="hb-btn hb-btn-secondary"><i class="bi bi-funnel me-1"></i> Terapkan</button>
        @if ($filtered)
            <a href="{{ route('audit-logs.index') }}" class="hb-btn hb-btn-light">Reset</a>
        @endif
    </form>

    @if ($logs->isEmpty())
        <div class="hb-empty">
            <i class="bi bi-journal-x"></i>
            <span>{{ $filtered ? 'Tidak ada catatan yang cocok dengan filter.' : 'Belum ada catatan audit.' }}</span>
        </div>
    @else
        <div class="table-responsive">
            <table class="table hb-table align-middle mb-0">
                <thead>
                    <tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Objek</th><th>IP</th><th class="text-end">Detail</th></tr>
                </thead>
                <tbody>
                    @foreach ($logs as $l)
                        <tr>
                            <td class="text-nowrap small">{{ $l->created_at->format('d/m/Y H:i:s') }}</td>
                            <td>
                                @if ($l->user)
                                    <div class="fw-semibold">{{ $l->user->name }}</div>
                                    <div class="small text-muted">{{ $l->user->roleLabel() }}</div>
                                @else
                                    <span class="text-muted">Sistem / tamu</span>
                                @endif
                            </td>
                            <td>
                                <div class="fw-semibold">{{ \App\Support\AuditLabels::action($l->action) }}</div>
                                <div class="fs-key">{{ $l->action }}</div>
                            </td>
                            <td class="small">{{ \App\Support\AuditLabels::subject($l->auditable_type, $l->auditable_id) }}</td>
                            <td class="small text-nowrap">{{ $l->ip_address ?: '-' }}</td>
                            <td>
                                <div class="hb-actions">
                                    <button type="button" class="hb-icon-action" title="Detail" aria-label="Detail"
                                            data-bs-toggle="modal" data-bs-target="#hbDetailModal"
                                            data-detail="{{ json_encode($details[$l->id]) }}">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <x-pagination :paginator="$logs" />
</div>

<div class="modal fade" id="hbDetailModal" tabindex="-1" aria-labelledby="hbDetailTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content hb-modal">
            <div class="modal-header">
                <h2 class="modal-title h5" id="hbDetailTitle">Detail Audit</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <dl class="hb-dl hb-dl-wide mb-0">
                    <dt>Waktu</dt><dd data-d="time">-</dd>
                    <dt>Pengguna</dt><dd data-d="user">-</dd>
                    <dt>Aksi</dt><dd data-d="action">-</dd>
                    <dt>Objek</dt><dd data-d="subject">-</dd>
                    <dt>Alamat IP</dt><dd data-d="ip">-</dd>
                    <dt>Perangkat</dt><dd data-d="agent" class="small">-</dd>
                    <dt>Alasan</dt><dd data-d="reason">-</dd>
                    <dt>Nilai lama</dt><dd><pre class="hb-pre" data-d="old">-</pre></dd>
                    <dt>Nilai baru</dt><dd><pre class="hb-pre" data-d="new">-</pre></dd>
                </dl>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="hb-btn hb-btn-light" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/records.js') }}"></script>
@endpush
