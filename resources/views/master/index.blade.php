@extends('layouts.app')

@section('title', $cfg['title'])
@section('page_title', $cfg['title'])

@section('breadcrumb')
    <li class="breadcrumb-item">Data Master</li>
    <li class="breadcrumb-item active" aria-current="page">{{ $cfg['title'] }}</li>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/crud.css') }}">
@endpush

@section('page_actions')
    @if ($cfg['allow_create'])
        <button type="button" class="hb-btn hb-btn-primary" data-bs-toggle="modal" data-bs-target="#hbFormModal" data-mode="create">
            <i class="bi bi-plus-lg me-1"></i> Tambah {{ $cfg['singular'] }}
        </button>
    @endif
@endsection

@section('content')
@php
    $reopenMode = old('_mode');
    $reopenId = old('_id');
@endphp

@if ($cfg['notice'])
    <div class="hb-alert hb-alert-info mb-3">
        <i class="bi bi-info-circle-fill"></i><span>{{ $cfg['notice'] }}</span>
    </div>
@endif

<div class="hb-card hb-panel">
    <form method="GET" class="hb-filters">
        <div class="hb-search">
            <i class="bi bi-search"></i>
            <input type="search" name="q" value="{{ request('q') }}" class="form-control"
                   placeholder="{{ $cfg['search_placeholder'] }}" aria-label="Pencarian">
        </div>
        @if ($cfg['has_active'])
            <select name="status" class="form-select" aria-label="Filter status">
                <option value="">Semua status</option>
                <option value="active" @selected(request('status') === 'active')>Aktif</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Nonaktif</option>
            </select>
        @endif
        <button type="submit" class="hb-btn hb-btn-secondary"><i class="bi bi-search me-1"></i> Cari</button>
        @if (request()->filled('q') || request()->filled('status'))
            <a href="{{ route($cfg['route'].'.index') }}" class="hb-btn hb-btn-light">Reset</a>
        @endif
    </form>

    @if ($records->isEmpty())
        <div class="hb-empty">
            <i class="bi bi-inbox"></i>
            <span>{{ request()->filled('q') || request()->filled('status') ? 'Tidak ada data yang cocok dengan pencarian.' : 'Belum ada data.' }}</span>
        </div>
    @else
        <div class="table-responsive">
            <table class="table hb-table align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:48px">#</th>
                        @foreach ($cfg['columns'] as $col)
                            <th>{{ $col['label'] }}</th>
                        @endforeach
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($records as $record)
                        @php
                            $payload = [];
                            foreach ($cfg['fields'] as $f) {
                                $payload[$f['name']] = $record->{$f['name']};
                            }
                        @endphp
                        <tr>
                            <td class="text-muted">{{ $records->firstItem() + $loop->index }}</td>
                            @foreach ($cfg['columns'] as $col)
                                @php
                                    $value = data_get($record, $col['key']);
                                    $type = $col['type'] ?? 'text';
                                @endphp
                                <td>
                                    @switch($type)
                                        @case('money')
                                            Rp {{ number_format((float) $value, 0, ',', '.') }}
                                            @break
                                        @case('percent')
                                            <strong>{{ number_format((float) $value, 2, ',', '.') }}%</strong>
                                            @break
                                        @case('active')
                                            <span class="hb-st {{ $value ? 'hb-st-present' : 'hb-st-absent' }}">{{ $value ? 'Aktif' : 'Nonaktif' }}</span>
                                            @break
                                        @case('bool')
                                            {{ $value ? 'Ya' : 'Tidak' }}
                                            @break
                                        @case('map')
                                            {{ $col['map'][$value] ?? $value }}
                                            @break
                                        @case('muted')
                                            <span class="text-muted">{{ \Illuminate\Support\Str::limit((string) $value, 70) ?: '-' }}</span>
                                            @break
                                        @default
                                            <span class="{{ !empty($col['bold']) ? 'fw-semibold' : '' }}">{{ $value !== null && $value !== '' ? $value : '-' }}</span>
                                    @endswitch
                                </td>
                            @endforeach
                            <td>
                                <div class="hb-actions">
                                    <button type="button" class="hb-icon-action" title="Ubah" aria-label="Ubah"
                                            data-bs-toggle="modal" data-bs-target="#hbFormModal"
                                            data-mode="edit" data-id="{{ $record->id }}"
                                            data-record="{{ json_encode($payload) }}">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>

                                    @if ($cfg['has_active'])
                                        <button type="button" class="hb-icon-action" title="{{ $record->is_active ? 'Nonaktifkan' : 'Aktifkan' }}"
                                                aria-label="{{ $record->is_active ? 'Nonaktifkan' : 'Aktifkan' }}"
                                                data-bs-toggle="modal" data-bs-target="#hbConfirm"
                                                data-action="{{ route($cfg['route'].'.toggle', $record->id) }}" data-method="PATCH"
                                                data-tone="primary" data-btn="{{ $record->is_active ? 'Nonaktifkan' : 'Aktifkan' }}"
                                                data-title="{{ $record->is_active ? 'Nonaktifkan' : 'Aktifkan' }} {{ $cfg['singular'] }}?"
                                                data-message="{{ $record->name }} akan {{ $record->is_active ? 'dinonaktifkan' : 'diaktifkan kembali' }}.">
                                            <i class="bi {{ $record->is_active ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
                                        </button>
                                    @endif

                                    @if ($cfg['allow_delete'])
                                        <button type="button" class="hb-icon-action is-danger" title="Hapus" aria-label="Hapus"
                                                data-bs-toggle="modal" data-bs-target="#hbConfirm"
                                                data-action="{{ route($cfg['route'].'.destroy', $record->id) }}" data-method="DELETE"
                                                data-tone="danger" data-btn="Hapus"
                                                data-title="Hapus {{ $cfg['singular'] }}?"
                                                data-message="{{ $record->name }} akan dihapus. Tindakan ini tidak dapat dibatalkan.">
                                            <i class="bi bi-trash3"></i>
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

    <x-pagination :paginator="$records" />
</div>

{{-- ===== Modal tambah / ubah ===== --}}
<div class="modal fade" id="hbFormModal" tabindex="-1" aria-labelledby="hbFormTitle" aria-hidden="true"
     data-singular="{{ $cfg['singular'] }}"
     data-reopen-mode="{{ $reopenMode }}" data-reopen-id="{{ $reopenId }}">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content hb-modal">
            <form method="POST" action="{{ route($cfg['route'].'.store') }}" data-hb-loading
                  data-store-url="{{ route($cfg['route'].'.store') }}"
                  data-update-url="{{ route($cfg['route'].'.update', ['id' => 0]) }}">
                @csrf
                <input type="hidden" name="_method" value="PUT" data-form-method disabled>
                <input type="hidden" name="_mode" value="create" data-mode-input>
                <input type="hidden" name="_id" value="" data-id-input>

                <div class="modal-header">
                    <h2 class="modal-title h5" id="hbFormTitle" data-form-title>Tambah {{ $cfg['singular'] }}</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        @foreach ($cfg['fields'] as $f)
                            @php
                                $name = $f['name'];
                                $type = $f['type'];
                                $default = $f['default'] ?? ($type === 'checkbox' ? false : '');
                                $value = $reopenMode ? old($name, $type === 'checkbox' ? '0' : '') : $default;
                                $invalid = $errors->has($name) ? 'is-invalid' : '';
                            @endphp
                            <div class="col-md-{{ $f['col'] ?? 12 }}">
                                @if ($type === 'checkbox')
                                    <input type="hidden" name="{{ $name }}" value="0">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="f_{{ $name }}"
                                               name="{{ $name }}" value="1" data-field="{{ $name }}"
                                               data-default="{{ $default ? '1' : '0' }}" {{ $value ? 'checked' : '' }}>
                                        <label class="form-check-label" for="f_{{ $name }}">{{ $f['label'] }}</label>
                                    </div>
                                @else
                                    <label class="form-label fw-semibold" for="f_{{ $name }}">
                                        {{ $f['label'] }}@if (!empty($f['required'])) <span class="text-danger">*</span>@endif
                                    </label>

                                    @if ($type === 'textarea')
                                        <textarea class="form-control {{ $invalid }}" id="f_{{ $name }}" name="{{ $name }}" rows="3"
                                                  data-field="{{ $name }}" data-default="{{ $default }}"
                                                  @if (!empty($f['maxlength'])) maxlength="{{ $f['maxlength'] }}" @endif>{{ $value }}</textarea>
                                    @elseif ($type === 'select')
                                        <select class="form-select {{ $invalid }}" id="f_{{ $name }}" name="{{ $name }}"
                                                data-field="{{ $name }}" data-default="{{ $default }}" @if (!empty($f['required'])) required @endif>
                                            <option value="">- Pilih -</option>
                                            @foreach (($f['options'] ?? $options[$name] ?? []) as $optValue => $optLabel)
                                                <option value="{{ $optValue }}" {{ (string) $value === (string) $optValue ? 'selected' : '' }}>{{ $optLabel }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <input type="{{ $type }}" class="form-control {{ $invalid }}" id="f_{{ $name }}" name="{{ $name }}"
                                               value="{{ $value }}" data-field="{{ $name }}" data-default="{{ $default }}"
                                               @if (!empty($f['required'])) required @endif
                                               @if (!empty($f['maxlength'])) maxlength="{{ $f['maxlength'] }}" @endif
                                               @if (isset($f['min'])) min="{{ $f['min'] }}" @endif
                                               @if (isset($f['max'])) max="{{ $f['max'] }}" @endif
                                               @if (!empty($f['step'])) step="{{ $f['step'] }}" @endif
                                               @if (!empty($f['placeholder'])) placeholder="{{ $f['placeholder'] }}" @endif
                                               autocomplete="off">
                                    @endif
                                @endif

                                @error($name)
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        @endforeach
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
