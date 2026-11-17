<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Support\PayrollLock;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LeaveService
{
    public function __construct(private ScheduleService $schedules)
    {
    }

    /** Apakah ada pengajuan pending/approved yang bertabrakan dengan rentang tanggal ini? */
    public function overlapExists(int $employeeId, string $start, string $end, ?int $ignoreId = null): bool
    {
        return LeaveRequest::query()
            ->where('employee_id', $employeeId)
            ->whereIn('status', [LeaveRequest::STATUS_PENDING, LeaveRequest::STATUS_APPROVED])
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();
    }

    /**
     * Setujui pengajuan dan sinkronkan catatan absensi (hari kerja yang sudah lewat atau hari ini).
     *
     * @return array{leave: LeaveRequest, created:int, converted:int, kept:int, locked:int}
     * @throws DomainException
     */
    public function approve(int $id, User $reviewer, ?string $note): array
    {
        [$leave, $stats] = DB::transaction(function () use ($id, $reviewer, $note) {
            $leave = LeaveRequest::query()->whereKey($id)->lockForUpdate()->firstOrFail();

            if ($leave->status !== LeaveRequest::STATUS_PENDING) {
                throw new DomainException('Pengajuan ini sudah diproses sebelumnya.');
            }

            $employee = Employee::query()->find($leave->employee_id); // karyawan terhapus tidak ditemukan
            if (!$employee) {
                throw new DomainException('Karyawan pemilik pengajuan ini sudah dihapus.');
            }

            $leave->update([
                'status' => LeaveRequest::STATUS_APPROVED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'admin_note' => $note,
            ]);

            return [$leave, $this->syncAttendance($leave->load('leaveType'), $employee, $reviewer)];
        });

        AuditLog::record('approve_leave', $leave, ['status' => 'pending'], ['status' => 'approved'] + $stats, $note);
        $this->notifyEmployee($leave, 'Pengajuan izin disetujui', 'Pengajuan izin Anda untuk '.$this->period($leave).' disetujui.');

        return ['leave' => $leave] + $stats;
    }

    /** @throws DomainException */
    public function reject(int $id, User $reviewer, string $note): LeaveRequest
    {
        $leave = DB::transaction(function () use ($id, $reviewer, $note) {
            $leave = LeaveRequest::query()->whereKey($id)->lockForUpdate()->firstOrFail();

            if ($leave->status !== LeaveRequest::STATUS_PENDING) {
                throw new DomainException('Pengajuan ini sudah diproses sebelumnya.');
            }

            $leave->update([
                'status' => LeaveRequest::STATUS_REJECTED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'admin_note' => $note,
            ]);

            return $leave;
        });

        AuditLog::record('reject_leave', $leave, ['status' => 'pending'], ['status' => 'rejected'], $note);
        $this->notifyEmployee($leave, 'Pengajuan izin ditolak', 'Pengajuan izin Anda untuk '.$this->period($leave).' ditolak. Lihat catatan admin.');

        return $leave;
    }

    public function notifySubmitted(LeaveRequest $leave, Employee $employee): void
    {
        User::query()
            ->whereIn('role', [User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN])
            ->where('is_active', true)
            ->get(['id'])
            ->each(fn (User $admin) => AppNotification::create([
                'user_id' => $admin->id,
                'type' => 'leave',
                'title' => 'Pengajuan izin baru',
                'message' => "{$employee->full_name} mengajukan izin untuk ".$this->period($leave).'.',
                'url' => route('leave-requests.show', $leave->id),
            ]));
    }

    /* ------------------------------------------------------------------ */

    private function period(LeaveRequest $leave): string
    {
        $start = $leave->start_date->format('d/m/Y');

        return $leave->start_date->isSameDay($leave->end_date)
            ? $start
            : $start.' - '.$leave->end_date->format('d/m/Y');
    }

    private function notifyEmployee(LeaveRequest $leave, string $title, string $message): void
    {
        $userId = Employee::withTrashed()->whereKey($leave->employee_id)->value('user_id');
        if (!$userId) {
            return;
        }

        AppNotification::create([
            'user_id' => $userId,
            'type' => 'leave',
            'title' => $title,
            'message' => $message,
            'url' => route('employee.leave-requests.index'),
        ]);
    }

    /**
     * Hari kerja yang sudah lewat/hari ini: buat catatan Izin, atau ubah Tidak Hadir menjadi Izin.
     * Hadir/Terlambat tidak diubah. Hari mendatang dibuat oleh scheduler pada hari itu.
     *
     * @return array{created:int, converted:int, kept:int, locked:int}
     */
    private function syncAttendance(LeaveRequest $leave, Employee $employee, User $reviewer): array
    {
        $stats = ['created' => 0, 'converted' => 0, 'kept' => 0, 'locked' => 0];

        $tz = Company::current()?->timezone ?: config('app.timezone');
        $todayStr = Carbon::now($tz)->toDateString();
        $limitStr = min($leave->end_date->toDateString(), $todayStr);
        $label = $leave->leaveType?->name ?? 'Izin';

        for ($d = $leave->start_date->copy()->startOfDay(); $d->toDateString() <= $limitStr; $d->addDay()) {
            $dateStr = $d->toDateString();

            if (PayrollLock::covers($dateStr)) {
                $stats['locked']++;

                continue;
            }

            $resolved = $this->schedules->resolveFor($employee, $d);
            if (!$resolved || !$resolved['is_working_day']) {
                continue; // libur atau tidak ada jadwal: tidak perlu catatan
            }

            $record = AttendanceRecord::query()
                ->where('employee_id', $employee->id)
                ->whereDate('attendance_date', $dateStr)
                ->lockForUpdate()
                ->first();

            if (!$record) {
                AttendanceRecord::create([
                    'employee_id' => $employee->id,
                    'work_schedule_id' => $resolved['schedule']->id,
                    'attendance_date' => $dateStr,
                    'status' => AttendanceRecord::STATUS_LEAVE,
                    'work_mode' => $resolved['work_mode'],
                    'late_minutes' => 0,
                    'verification_method' => 'system',
                    'leave_request_id' => $leave->id,
                    'notes' => "Izin disetujui: {$label} (pengajuan #{$leave->id}).",
                ]);
                $stats['created']++;
            } elseif ($record->status === AttendanceRecord::STATUS_ABSENT) {
                $record->update([
                    'status' => AttendanceRecord::STATUS_LEAVE,
                    'leave_request_id' => $leave->id,
                    'is_corrected' => true,
                    'corrected_by' => $reviewer->id,
                    'corrected_at' => now(),
                    'correction_reason' => "Pengajuan izin #{$leave->id} disetujui.",
                ]);
                AuditLog::record('leave_sync', $record, ['status' => 'absent'], ['status' => 'leave', 'leave_request_id' => $leave->id]);
                $stats['converted']++;
            } else {
                $stats['kept']++;
            }
        }

        return $stats;
    }
}
