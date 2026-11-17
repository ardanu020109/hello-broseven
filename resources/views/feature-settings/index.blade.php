@extends('layouts.app')

@section('title', 'Pengaturan Fitur')
@section('page_title', 'Pengaturan Fitur')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/crud.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/settings.css') }}">
@endpush

@section('content')
<div class="hb-chips">
    <span class="hb-chip hb-chip-present"><strong>{{ $enabledCount }}</strong> fitur aktif</span>
    <span class="hb-chip hb-chip-absent"><strong>{{ $features->count() - $enabledCount }}</strong> fitur nonaktif</span>
</div>

<div class="hb-alert hb-alert-info mb-3">
    <i class="bi bi-info-circle-fill"></i>
    <span>Fitur yang dinonaktifkan hilang dari sidebar dan alamat URL-nya <strong>ditolak untuk semua pengguna, termasuk Super Admin dan Admin</strong>. Endpoint JSON terkait ikut ditolak. Data yang sudah tersimpan tidak dihapus. Halaman ini tidak pernah ikut dimatikan.</span>
</div>

<div class="hb-card hb-panel">
    <div class="table-responsive">
        <table class="table hb-table align-middle mb-0">
            <thead>
                <tr><th>Fitur</th><th>Kegunaan</th><th>Dampak jika dimatikan</th><th>Status</th><th class="text-end">Aksi</th></tr>
            </thead>
            <tbody>
                @foreach ($features as $f)
                    @php
                        $risky = in_array($f->feature_key, $security, true);
                        $message = 'Fitur "'.$f->name.'" akan dinonaktifkan untuk SEMUA pengguna, termasuk Super Admin dan Admin. Menunya hilang dan URL-nya ditolak.';
                        if ($risky) {
                            $message .= ' PERINGATAN: ini melemahkan keamanan absensi.';
                        }
                    @endphp
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $f->name }}</div>
                            <div class="fs-key">{{ $f->feature_key }}</div>
                        </td>
                        <td class="small">{{ $f->description ?: '-' }}</td>
                        <td class="fs-impact">{{ $impact[$f->feature_key] ?? '-' }}</td>
                        <td><span class="hb-st {{ $f->is_enabled ? 'hb-st-present' : 'hb-st-absent' }}">{{ $f->is_enabled ? 'Aktif' : 'Nonaktif' }}</span></td>
                        <td>
                            <div class="hb-actions">
                                @if ($f->is_enabled)
                                    <button type="button" class="hb-icon-action is-danger" title="Nonaktifkan" aria-label="Nonaktifkan {{ $f->name }}"
                                            data-bs-toggle="modal" data-bs-target="#hbConfirm"
                                            data-action="{{ route('feature-settings.toggle', $f->id) }}" data-method="PATCH"
                                            data-tone="danger" data-btn="Nonaktifkan"
                                            data-title="Nonaktifkan fitur?" data-message="{{ $message }}">
                                        <i class="bi bi-toggle-on"></i>
                                    </button>
                                @else
                                    <button type="button" class="hb-icon-action" title="Aktifkan" aria-label="Aktifkan {{ $f->name }}"
                                            data-bs-toggle="modal" data-bs-target="#hbConfirm"
                                            data-action="{{ route('feature-settings.toggle', $f->id) }}" data-method="PATCH"
                                            data-tone="primary" data-btn="Aktifkan"
                                            data-title="Aktifkan fitur?" data-message="Fitur &quot;{{ $f->name }}&quot; akan diaktifkan kembali untuk seluruh pengguna sesuai role-nya.">
                                        <i class="bi bi-toggle-off"></i>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<x-confirm-modal />
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/crud.js') }}"></script>
@endpush
