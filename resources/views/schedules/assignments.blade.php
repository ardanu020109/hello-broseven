@extends('layouts.app')

@section('title', 'Penugasan Jadwal')
@section('page_title', 'Data Jadwal')

@section('breadcrumb')
    <li class="breadcrumb-item">Data Jadwal</li>
    <li class="breadcrumb-item active" aria-current="page">Penugasan</li>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/crud.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/schedules.css') }}">
@endpush

@section('page_actions')
    <button type="button" class="hb-btn hb-btn-primary" data-bs-toggle="modal" data-bs-target="#hbAssignModal">
        <i class="bi bi-person-plus me-1"></i> Tugaskan Jadwal
    </button>
@endsection

@section('content')
@php
    $todayStr = today()->toDateString();
    $filtered = request()->filled('q') || request()->filled('department_id') || request()->filled('work_schedule_id') || (request('state') && request('state') !== 'current');
    $openModal = ($errors->any() && old('_form') === 'assign') || filled(request('assign'));
    $selectedSchedule = old('work_schedule_id', request('assign'));
    $selectedTarget = old('target', 'employees');
    $selectedIds = array_map('intval', (array) old('employee_ids', []));
@endphp

@include('schedules.partials.tabs')

@if ($unscheduledCount > 0)
    <div class="hb-alert hb-alert-info mb-3">
        <i class="bi bi-exclamation-circle-fill"></i>
        <span class="flex-grow-1">
            <strong>{{ $unscheduledCount }} karyawan aktif belum memiliki jadwal hari ini</strong>
            ({{ $unscheduledNames->implode(', ') }}{{ $unscheduledCount > $unscheduledNames->count() ? ', dan lainnya' : '' }}).
            Tanpa jadwal, mereka tidak dapat melakukan absensi.
        </span>
        <button type="button" class="hb-btn hb-btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#hbAssignModal" data-target="unscheduled">Tugaskan</button>
    </div>
@endif

<div class="hb-card hb-panel">
    <form method="GET" class="hb-filters">
        <div class="hb-search">
            <i class="bi bi-search"></i>
            <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Cari nama, NIK internal, atau email..." aria-label="Pencarian">
        </div>
        <select name="department_id" class="form-select" aria-label="Filter departemen">
            <option value="">Semua departemen</option>
            @foreach ($departments as $d)
                <option value="{{ $d->id }}" @selected((string) request('department_id') === (string) $d->id)>{{ $d->name }}</option>
            @endforeach
        </select>
        <select name="work_schedule_id" class="form-select" aria-label="Filter jadwal">
            <option value="">Semua jadwal</option>
            @foreach ($allSchedules as $s)
                <option value="{{ $s->id }}" @selected((string) request('work_schedule_id') === (string) $s->id)>{{ $s->name }}</option>
            @endforeach
        </select>
        <select name="state" class="form-select" aria-label="Filter status penugasan">
            <option value="current" @selected($state === 'current')>Berlaku hari ini</option>
            <option value="upcoming" @selected($state === 'upcoming')>Akan datang</option>
            <option value="ended" @selected($state === 'ended')>Sudah berakhir</option>
            <option value="all" @selected($state === 'all')>Semua</option>
        </select>
        <button type="submit" class="hb-btn hb-btn-secondary"><i class="bi bi-funnel me-1"></i> Terapkan</button>
        @if ($filtered)
            <a href="{{ route('schedules.assignments.index') }}" class="hb-btn hb-btn-light">Reset</a>
        @endif
    </form>

    @if ($assignments->isEmpty())
        <div class="hb-empty">
            <i class="bi bi-person-lines-fill"></i>
            <span>{{ $filtered ? 'Tidak ada penugasan yang cocok dengan filter.' : 'Belum ada penugasan jadwal yang berlaku.' }}</span>
        </div>
    @else
        <div class="table-responsive">
            <table class="table hb-table align-middle mb-0">
                <thead>
                    <tr><th>Karyawan</th><th>Departemen</th><th>Jadwal</th><th>Mulai</th><th>Berakhir</th><th>Status</th><th class="text-end">Aksi</th></tr>
                </thead>
                <tbody>
                    @foreach ($assignments as $a)
                        @php
                            $startStr = $a->start_date->toDateString();
                            $endStr = $a->end_date?->toDateString();
                            if ($startStr > $todayStr) { [$stLabel, $stClass] = ['Akan datang', 'hb-pill-info']; }
                            elseif ($endStr !== null && $endStr < $todayStr) { [$stLabel, $stClass] = ['Berakhir', 'hb-pill-muted']; }
                            else { [$stLabel, $stClass] = ['Berlaku', 'hb-pill-success']; }
                            $canEnd = $startStr <= $todayStr && ($endStr === null || $endStr > $todayStr);
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('employees.show', $a->employee_id) }}" class="fw-semibold text-decoration-none">{{ $a->employee?->full_name }}</a>
                                <div class="small text-muted">{{ $a->employee?->employee_number }} &middot; {{ $a->employee?->work_mode }}</div>
                            </td>
                            <td>{{ $a->employee?->department?->name ?? '-' }}</td>
                            <td>
                                <div class="fw-semibold">{{ $a->workSchedule?->name ?? '-' }}</div>
                                @if ($a->workSchedule)
                                    <div class="small text-muted">{{ substr((string) $a->workSchedule->check_in_time, 0, 5) }} - {{ substr((string) $a->workSchedule->check_out_time, 0, 5) }}</div>
                                @endif
                            </td>
                            <td class="text-nowrap">{{ $a->start_date->format('d/m/Y') }}</td>
                            <td class="text-nowrap">{{ $a->end_date ? $a->end_date->format('d/m/Y') : 'Seterusnya' }}</td>
                            <td><span class="hb-pill {{ $stClass }}">{{ $stLabel }}</span></td>
                            <td>
                                <div class="hb-actions">
                                    @if ($canEnd)
                                        <button type="button" class="hb-icon-action" title="Akhiri sampai hari ini" aria-label="Akhiri penugasan"
                                                data-bs-toggle="modal" data-bs-target="#hbConfirm"
                                                data-action="{{ route('schedules.assignments.end', $a->id) }}" data-method="PATCH"
                                                data-tone="primary" data-btn="Akhiri"
                                                data-title="Akhiri penugasan?"
                                                data-message="Jadwal {{ $a->employee?->full_name }} berlaku sampai hari ini, dan mulai besok ia tidak punya jadwal sampai ditugaskan lagi.">
                                            <i class="bi bi-calendar-x"></i>
                                        </button>
                                    @endif
                                    <button type="button" class="hb-icon-action is-danger" title="Hapus penugasan" aria-label="Hapus penugasan"
                                            data-bs-toggle="modal" data-bs-target="#hbConfirm"
                                            data-action="{{ route('schedules.assignments.destroy', $a->id) }}" data-method="DELETE"
                                            data-tone="danger" data-btn="Hapus"
                                            data-title="Hapus penugasan?"
                                            data-message="Penugasan jadwal {{ $a->employee?->full_name }} akan dihapus. Penugasan yang sudah dipakai absensi tidak dapat dihapus.">
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

    <x-pagination :paginator="$assignments" />
</div>

{{-- ===== Modal penugasan ===== --}}
<div class="modal fade" id="hbAssignModal" tabindex="-1" aria-labelledby="hbAssignTitle" aria-hidden="true" data-reopen="{{ $openModal ? '1' : '0' }}">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content hb-modal">
            <form method="POST" action="{{ route('schedules.assignments.store') }}" data-hb-loading>
                @csrf
                <input type="hidden" name="_form" value="assign">

                <div class="modal-header">
                    <h2 class="modal-title h5" id="hbAssignTitle">Tugaskan Jadwal</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>

                <div class="modal-body">
                    @if ($errors->any() && old('_form') === 'assign')
                        <div class="hb-alert hb-alert-error mb-3" role="alert">
                            <i class="bi bi-exclamation-octagon-fill"></i>
                            <span>
                                @foreach ($errors->all() as $message)
                                    {{ $message }}<br>
                                @endforeach
                            </span>
                        </div>
                    @endif

                    <div class="row g-3">
                        <div class="col-12">
                            <label for="assign_schedule" class="form-label fw-semibold">Jadwal <span class="text-danger">*</span></label>
                            <select id="assign_schedule" name="work_schedule_id" class="form-select" required>
                                <option value="">- Pilih jadwal -</option>
                                @foreach ($activeSchedules as $s)
                                    <option value="{{ $s->id }}" @selected((string) $selectedSchedule === (string) $s->id)>
                                        {{ $s->name }} ({{ substr((string) $s->check_in_time, 0, 5) }} - {{ substr((string) $s->check_out_time, 0, 5) }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12">
                            <div class="form-label fw-semibold">Tugaskan ke</div>
                            <div class="hb-segment" role="radiogroup" aria-label="Target penugasan">
                                <label><input type="radio" name="target" value="employees" {{ $selectedTarget === 'employees' ? 'checked' : '' }}><span><i class="bi bi-people"></i> Karyawan terpilih</span></label>
                                <label><input type="radio" name="target" value="department" {{ $selectedTarget === 'department' ? 'checked' : '' }}><span><i class="bi bi-building"></i> Satu departemen</span></label>
                                <label><input type="radio" name="target" value="unscheduled" {{ $selectedTarget === 'unscheduled' ? 'checked' : '' }}><span><i class="bi bi-person-exclamation"></i> Yang belum punya jadwal</span></label>
                            </div>
                        </div>

                        <div class="col-12" data-panel="employees">
                            <div class="d-flex flex-wrap gap-2 mb-2">
                                <input type="search" id="empSearch" class="form-control form-control-sm flex-grow-1" placeholder="Cari nama, NIK internal, atau departemen..." aria-label="Cari karyawan">
                                <button type="button" class="hb-btn hb-btn-light btn-sm" data-select-visible>Pilih yang tampil</button>
                                <button type="button" class="hb-btn hb-btn-light btn-sm" data-clear-all>Kosongkan</button>
                            </div>
                            <div class="hb-checklist" id="empList">
                                @forelse ($employeesAll as $e)
                                    <label class="hb-check-item" data-search="{{ \Illuminate\Support\Str::lower($e->full_name.' '.$e->employee_number.' '.($e->department?->name ?? '')) }}">
                                        <input type="checkbox" class="form-check-input mt-0" name="employee_ids[]" value="{{ $e->id }}" {{ in_array($e->id, $selectedIds, true) ? 'checked' : '' }}>
                                        <span><strong>{{ $e->full_name }}</strong><small>{{ $e->employee_number }} &middot; {{ $e->department?->name ?? '-' }}</small></span>
                                    </label>
                                @empty
                                    <div class="hb-empty"><i class="bi bi-people"></i><span>Belum ada karyawan aktif</span></div>
                                @endforelse
                            </div>
                            <div class="form-text"><span data-selected-count>0</span> karyawan dipilih.</div>
                        </div>

                        <div class="col-12" data-panel="department" hidden>
                            <label for="assign_department" class="form-label fw-semibold">Departemen <span class="text-danger">*</span></label>
                            <select id="assign_department" name="department_id" class="form-select">
                                <option value="">- Pilih departemen -</option>
                                @foreach ($departments as $d)
                                    <option value="{{ $d->id }}" @selected((string) old('department_id') === (string) $d->id)>{{ $d->name }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">Semua karyawan aktif di departemen ini akan memakai jadwal tersebut.</div>
                        </div>

                        <div class="col-12" data-panel="unscheduled" hidden>
                            <div class="hb-alert hb-alert-info mb-0">
                                <i class="bi bi-info-circle-fill"></i>
                                <span>Jadwal diberikan ke semua karyawan aktif yang belum punya jadwal pada tanggal mulai di bawah.</span>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label for="assign_start" class="form-label fw-semibold">Tanggal mulai <span class="text-danger">*</span></label>
                            <input type="date" id="assign_start" name="start_date" class="form-control" required value="{{ old('start_date', $todayStr) }}">
                        </div>
                        <div class="col-md-6">
                            <label for="assign_end" class="form-label fw-semibold">Tanggal berakhir</label>
                            <input type="date" id="assign_end" name="end_date" class="form-control" value="{{ old('end_date') }}">
                            <div class="form-text">Kosongkan jika berlaku seterusnya.</div>
                        </div>

                        <div class="col-12">
                            <p class="small text-muted mb-0">Penugasan lama yang bertabrakan akan dipotong atau diganti otomatis. Jadwal tidak dapat ditetapkan mundur untuk karyawan yang sudah punya absensi pada rentang tersebut.</p>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-0">
                    <button type="button" class="hb-btn hb-btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="hb-btn hb-btn-primary"><i class="bi bi-check2 me-1"></i> Tugaskan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<x-confirm-modal />
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/crud.js') }}"></script>
    <script src="{{ asset('assets/js/schedules.js') }}"></script>
@endpush
