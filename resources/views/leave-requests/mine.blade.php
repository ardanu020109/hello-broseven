@extends('layouts.app')

@section('title', 'Request Izin')
@section('page_title', 'Request Izin')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/crud.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/records.css') }}">
@endpush

@section('page_actions')
    <button type="button" class="hb-btn hb-btn-primary" data-bs-toggle="modal" data-bs-target="#hbLeaveModal">
        <i class="bi bi-plus-lg me-1"></i> Ajukan Izin
    </button>
@endsection

@section('content')
@php
    $tabs = ['' => 'Semua', 'pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'];
    $openModal = $errors->any();
@endphp

<div class="hb-alert hb-alert-info mb-3">
    <i class="bi bi-info-circle-fill"></i>
    <span>Pengajuan baru sah setelah <strong>disetujui admin</strong>. Selama masih menunggu atau jika ditolak, ketidakhadiran tetap dihitung sebagai Tidak Hadir. Pengajuan yang masih menunggu dapat dibatalkan.</span>
</div>

<div class="hb-chips">
    @foreach ($tabs as $key => $label)
        <a href="{{ route('employee.leave-requests.index', $key !== '' ? ['status' => $key] : []) }}"
           class="hb-chip hb-chip-link {{ $status === $key ? 'is-active' : '' }}">
            {{ $label }} <strong>{{ $key === '' ? $counts->sum() : ($counts[$key] ?? 0) }}</strong>
        </a>
    @endforeach
</div>

<div class="hb-card hb-panel">
    @if ($requests->isEmpty())
        <div class="hb-empty">
            <i class="bi bi-envelope-x"></i>
            <span>{{ $status !== '' ? 'Tidak ada pengajuan dengan status ini.' : 'Anda belum pernah mengajukan izin.' }}</span>
        </div>
    @else
        <div class="table-responsive">
            <table class="table hb-table align-middle mb-0">
                <thead>
                    <tr><th>Diajukan</th><th>Jenis</th><th>Periode</th><th>Hari</th><th>Alasan</th><th>Lampiran</th><th>Status</th><th>Catatan Admin</th><th class="text-end">Aksi</th></tr>
                </thead>
                <tbody>
                    @foreach ($requests as $l)
                        <tr>
                            <td class="text-nowrap small">{{ $l->created_at->format('d/m/Y H:i') }}</td>
                            <td>{{ $l->leaveType?->name ?? '-' }}</td>
                            <td class="text-nowrap small">
                                {{ $l->start_date->format('d/m/Y') }}
                                @if (!$l->start_date->isSameDay($l->end_date)) - {{ $l->end_date->format('d/m/Y') }} @endif
                            </td>
                            <td>{{ $l->total_days }}</td>
                            <td><span class="text-muted" title="{{ $l->reason }}">{{ \Illuminate\Support\Str::limit($l->reason, 40) }}</span></td>
                            <td>
                                @if ($l->attachment)
                                    <a href="{{ route('employee.leave-requests.attachment', $l->id) }}" target="_blank" rel="noopener"><i class="bi bi-paperclip"></i> Buka</a>
                                @else <span class="text-muted">-</span> @endif
                            </td>
                            <td><x-status-badge :status="$l->status" /></td>
                            <td>
                                @if ($l->admin_note)
                                    <span title="{{ $l->admin_note }}">{{ \Illuminate\Support\Str::limit($l->admin_note, 40) }}</span>
                                    <div class="small text-muted">{{ $l->reviewer?->name }}</div>
                                @else <span class="text-muted">-</span> @endif
                            </td>
                            <td>
                                <div class="hb-actions">
                                    @if ($l->status === 'pending')
                                        <button type="button" class="hb-icon-action is-danger" title="Batalkan pengajuan" aria-label="Batalkan pengajuan"
                                                data-bs-toggle="modal" data-bs-target="#hbConfirm"
                                                data-action="{{ route('employee.leave-requests.destroy', $l->id) }}" data-method="DELETE"
                                                data-tone="danger" data-btn="Batalkan"
                                                data-title="Batalkan pengajuan?"
                                                data-message="Pengajuan izin {{ $l->start_date->format('d/m/Y') }} akan dibatalkan.">
                                            <i class="bi bi-x-circle"></i>
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

    <x-pagination :paginator="$requests" />
</div>

{{-- ===== Modal pengajuan ===== --}}
<div class="modal fade" id="hbLeaveModal" tabindex="-1" aria-labelledby="hbLeaveTitle" aria-hidden="true" data-reopen="{{ $openModal ? '1' : '0' }}">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content hb-modal">
            <form method="POST" action="{{ route('employee.leave-requests.store') }}" enctype="multipart/form-data" data-hb-loading>
                @csrf
                <div class="modal-header">
                    <h2 class="modal-title h5" id="hbLeaveTitle">Ajukan Izin</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="leaveType" class="form-label fw-semibold">Jenis izin <span class="text-danger">*</span></label>
                            <select id="leaveType" name="leave_type_id" class="form-select @error('leave_type_id') is-invalid @enderror" required>
                                <option value="">- Pilih -</option>
                                @foreach ($types as $t)
                                    <option value="{{ $t->id }}" data-requires="{{ $t->requires_attachment ? '1' : '0' }}" @selected((string) old('leave_type_id') === (string) $t->id)>{{ $t->name }}</option>
                                @endforeach
                            </select>
                            @error('leave_type_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="leaveStart" class="form-label fw-semibold">Tanggal mulai <span class="text-danger">*</span></label>
                            <input type="date" id="leaveStart" name="start_date" min="{{ $minDate }}" value="{{ old('start_date', today()->toDateString()) }}"
                                   class="form-control @error('start_date') is-invalid @enderror" required>
                            @error('start_date') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="leaveEnd" class="form-label fw-semibold">Tanggal selesai <span class="text-danger">*</span></label>
                            <input type="date" id="leaveEnd" name="end_date" value="{{ old('end_date', today()->toDateString()) }}"
                                   class="form-control @error('end_date') is-invalid @enderror" required>
                            @error('end_date') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12">
                            <label for="leaveReason" class="form-label fw-semibold">Alasan <span class="text-danger">*</span></label>
                            <textarea id="leaveReason" name="reason" rows="3" maxlength="1000" required
                                      class="form-control @error('reason') is-invalid @enderror">{{ old('reason') }}</textarea>
                            @error('reason') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12">
                            <label for="leaveAttachment" class="form-label fw-semibold">Lampiran <span class="text-danger" id="attachStar" hidden>*</span></label>
                            <input type="file" id="leaveAttachment" name="attachment" accept="application/pdf,image/jpeg,image/png"
                                   class="form-control @error('attachment') is-invalid @enderror" data-max-kb="2048">
                            @error('attachment') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            <div class="form-text">PDF, JPG, atau PNG, maksimal 2 MB. Wajib untuk jenis izin tertentu (mis. surat dokter untuk sakit).</div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-0">
                    <button type="button" class="hb-btn hb-btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="hb-btn hb-btn-primary"><i class="bi bi-send me-1"></i> Kirim Pengajuan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<x-confirm-modal />
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/crud.js') }}"></script>
    <script src="{{ asset('assets/js/records.js') }}"></script>
@endpush
