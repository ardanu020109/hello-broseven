<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Support\DateInput;
use App\Support\PayrollLock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AttendanceRecordController extends Controller
{
    private const STATUSES = ['present', 'late', 'absent', 'leave'];

    public function index(Request $request): View
    {
        $from = DateInput::date($request->query('from')) ?? today()->startOfMonth();
        $to = DateInput::date($request->query('to')) ?? today();
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        $term = trim((string) $request->query('q'));
        $status = in_array($request->query('status'), self::STATUSES, true) ? $request->query('status') : '';

        $base = AttendanceRecord::query()
            ->whereBetween('attendance_date', [$from->toDateString(), $to->toDateString()])
            ->when($request->filled('employee_id'), fn ($q) => $q->where('employee_id', $request->integer('employee_id')))
            ->when($request->filled('department_id'), fn ($q) => $q->whereHas('employee',
                fn ($e) => $e->withTrashed()->where('department_id', $request->integer('department_id'))))
            ->when($term !== '', fn ($q) => $q->whereHas('employee', fn ($e) => $e->withTrashed()->search($term)))
            ->when($status !== '', fn ($q) => $q->where('status', $status));

        $counts = (clone $base)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        $records = (clone $base)
            ->with([
                'employee' => fn ($q) => $q->withTrashed()->with(['position', 'department']),
                'workSchedule',
                'checkInLocation',
            ])
            ->orderByDesc('attendance_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('attendance.index', [
            'records' => $records,
            'from' => $from,
            'to' => $to,
            'status' => $status,
            'counts' => $counts,
            'filtered' => $request->filled('q') || $request->filled('employee_id') || $request->filled('department_id')
                || $status !== '' || $request->filled('from') || $request->filled('to'),
            'employees' => Employee::withTrashed()->orderBy('full_name')->get(['id', 'full_name', 'employee_number']),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(string $id): View
    {
        $record = AttendanceRecord::with([
            'employee' => fn ($q) => $q->withTrashed()->with(['position', 'department']),
            'workSchedule', 'locations', 'leaveRequest.leaveType', 'corrector',
        ])->findOrFail($id);

        $audits = AuditLog::query()
            ->where('auditable_type', $record->getMorphClass())
            ->where('auditable_id', $record->id)
            ->with('user:id,name')
            ->latest('id')
            ->get();

        return view('attendance.show', [
            'record' => $record,
            'in' => $record->locations->firstWhere('type', 'check_in'),
            'out' => $record->locations->firstWhere('type', 'check_out'),
            'audits' => $audits,
            'locked' => PayrollLock::covers($record->attendance_date->toDateString()),
        ]);
    }

    public function correct(Request $request, string $id): RedirectResponse
    {
        $record = AttendanceRecord::with(['workSchedule'])->findOrFail($id);
        $date = $record->attendance_date->toDateString();

        if (PayrollLock::covers($date)) {
            return back()->with('error', 'Tanggal ini masuk periode payroll yang sudah ditutup sehingga absensinya tidak dapat dikoreksi.');
        }

        $data = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
            'check_in_time' => ['nullable', 'date_format:H:i'],
            'check_out_time' => ['nullable', 'date_format:H:i'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            'status.required' => 'Status wajib dipilih.',
            'status.in' => 'Status tidak valid.',
            'check_in_time.date_format' => 'Jam masuk harus berformat JJ:MM.',
            'check_out_time.date_format' => 'Jam pulang harus berformat JJ:MM.',
            'reason.required' => 'Alasan koreksi wajib diisi.',
            'reason.min' => 'Alasan koreksi minimal 10 karakter agar jelas.',
            'reason.max' => 'Alasan koreksi maksimal 500 karakter.',
        ]);

        $status = $data['status'];
        $withTimes = in_array($status, ['present', 'late'], true);
        $appTz = config('app.timezone');
        $errors = [];

        $checkIn = null;
        $checkOut = null;
        $leaveId = null;

        if ($withTimes) {
            if (empty($data['check_in_time'])) {
                $errors['check_in_time'] = 'Jam masuk wajib diisi untuk status Hadir atau Terlambat.';
            } else {
                $checkIn = Carbon::parse($date.' '.$data['check_in_time'], $appTz);
            }

            if (!empty($data['check_out_time'])) {
                $checkOut = Carbon::parse($date.' '.$data['check_out_time'], $appTz);
                if ($checkIn && $checkOut->lte($checkIn)) {
                    $errors['check_out_time'] = 'Jam pulang harus setelah jam masuk.';
                }
            }
        }

        if ($status === 'leave') {
            $leaveId = LeaveRequest::query()
                ->where('employee_id', $record->employee_id)
                ->where('status', LeaveRequest::STATUS_APPROVED)
                ->whereDate('start_date', '<=', $date)
                ->whereDate('end_date', '>=', $date)
                ->value('id');

            if (!$leaveId) {
                $errors['status'] = 'Tidak ada pengajuan izin yang disetujui pada tanggal ini. Status Izin hanya sah jika ada pengajuan yang disetujui.';
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $lateMinutes = 0;
        if ($status === 'late' && $checkIn) {
            $ws = $record->workSchedule;
            $lateMinutes = $ws
                ? max(0, intdiv($checkIn->timestamp - Carbon::parse($date.' '.$ws->check_in_time, $ws->timezone)->timestamp, 60))
                : (int) $record->late_minutes;
        }

        $old = $this->snapshot($record);

        $attrs = [
            'status' => $status,
            'check_in_at' => $checkIn,
            'check_out_at' => $checkOut,
            'late_minutes' => $lateMinutes,
            'leave_request_id' => $leaveId,
            'is_corrected' => true,
            'corrected_by' => $request->user()->id,
            'corrected_at' => now(),
            'correction_reason' => $data['reason'],
        ];

        if ($checkIn === null) {
            // Tidak ada kehadiran lagi: metode verifikasi lama tidak relevan
            $attrs += [
                'verification_method' => 'manual',
                'check_out_method' => null,
                'face_match_score' => null,
                'check_out_face_score' => null,
            ];
        } else {
            if (!$record->check_in_at) {
                $attrs['verification_method'] = 'manual';
            }
            if ($checkOut && !$record->check_out_at) {
                $attrs['check_out_method'] = 'manual';
            }
            if (!$checkOut) {
                $attrs['check_out_method'] = null;
                $attrs['check_out_face_score'] = null;
            }
        }

        $record->update($attrs);
        $record->refresh();

        AuditLog::record('correct_attendance', $record, $old, $this->snapshot($record), $data['reason']);

        $userId = Employee::withTrashed()->whereKey($record->employee_id)->value('user_id');
        if ($userId) {
            AppNotification::create([
                'user_id' => $userId,
                'type' => 'attendance',
                'title' => 'Absensi Anda dikoreksi',
                'message' => 'Absensi tanggal '.$record->attendance_date->format('d/m/Y').' dikoreksi admin. Alasan: '.$data['reason'],
                'url' => route('employee.history'),
            ]);
        }

        return redirect()->route('attendance.show', $record->id)->with('success', 'Koreksi absensi berhasil disimpan dan tercatat di audit log.');
    }

    private function snapshot(AttendanceRecord $record): array
    {
        return [
            'status' => $record->status,
            'check_in_at' => $record->check_in_at?->format('Y-m-d H:i'),
            'check_out_at' => $record->check_out_at?->format('Y-m-d H:i'),
            'late_minutes' => $record->late_minutes,
            'leave_request_id' => $record->leave_request_id,
        ];
    }
}
