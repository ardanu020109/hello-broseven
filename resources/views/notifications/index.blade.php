@extends('layouts.app')

@section('title', 'Notifikasi')
@section('page_title', 'Notifikasi')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/crud.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/records.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/settings.css') }}">
@endpush

@section('page_actions')
    @if ($counts['unread'] > 0)
        <form method="POST" action="{{ route('notifications.read-all') }}">
            @csrf
            <button type="submit" class="hb-btn hb-btn-secondary"><i class="bi bi-check2-all me-1"></i> Tandai semua dibaca</button>
        </form>
    @endif
@endsection

@section('content')
@php
    $icons = ['face' => 'bi-person-bounding-box', 'leave' => 'bi-envelope-paper', 'attendance' => 'bi-clipboard-check'];
@endphp

<div class="hb-chips">
    <a href="{{ route('notifications.index') }}" class="hb-chip hb-chip-link {{ $filter === 'all' ? 'is-active' : '' }}">Semua <strong>{{ $counts['all'] }}</strong></a>
    <a href="{{ route('notifications.index', ['filter' => 'unread']) }}" class="hb-chip hb-chip-link {{ $filter === 'unread' ? 'is-active' : '' }}">Belum dibaca <strong>{{ $counts['unread'] }}</strong></a>
</div>

<div class="hb-card hb-panel">
    @if ($notifications->isEmpty())
        <div class="hb-empty">
            <i class="bi bi-bell-slash"></i>
            <span>{{ $filter === 'unread' ? 'Tidak ada notifikasi yang belum dibaca.' : 'Belum ada notifikasi.' }}</span>
        </div>
    @else
        @foreach ($notifications as $n)
            <div class="nt-item {{ $n->read_at ? '' : 'is-unread' }}">
                <span class="nt-icon"><i class="bi {{ $icons[$n->type] ?? 'bi-bell' }}"></i></span>
                <div class="nt-body">
                    <div class="fw-semibold">{{ $n->title }}@if (!$n->read_at) <span class="nt-new">Baru</span>@endif</div>
                    @if ($n->message)<div class="text-muted">{{ $n->message }}</div>@endif
                    <div class="small text-muted" title="{{ $n->created_at->format('d/m/Y H:i') }}">{{ $n->created_at->diffForHumans() }}</div>
                </div>
                <div class="nt-actions">
                    @if ($n->url || !$n->read_at)
                        <form method="POST" action="{{ route('notifications.read', $n->id) }}">
                            @csrf
                            <button type="submit" class="hb-btn hb-btn-light btn-sm">{{ $n->url ? 'Buka' : 'Tandai dibaca' }}</button>
                        </form>
                    @endif
                    <button type="button" class="hb-icon-action is-danger" title="Hapus" aria-label="Hapus notifikasi"
                            data-bs-toggle="modal" data-bs-target="#hbConfirm"
                            data-action="{{ route('notifications.destroy', $n->id) }}" data-method="DELETE"
                            data-tone="danger" data-btn="Hapus"
                            data-title="Hapus notifikasi?" data-message="Notifikasi ini akan dihapus dari daftar Anda.">
                        <i class="bi bi-trash3"></i>
                    </button>
                </div>
            </div>
        @endforeach
    @endif

    <x-pagination :paginator="$notifications" />
</div>

<x-confirm-modal />
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/crud.js') }}"></script>
@endpush
