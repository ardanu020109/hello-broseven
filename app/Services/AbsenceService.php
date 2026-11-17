<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Support\PayrollLock;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;

class AbsenceService
{
    public function __construct(private ScheduleService $schedules)
    {
    }

    /**
     * Buat catatan Tidak Hadir / Izin untuk karyawan terjadwal yang tidak punya catatan absensi.
     * Memeriksa hari ini dan $daysBack hari ke belakang agar hari yang terlewat tetap terkejar.
     *
     * @return array{absent:int, leave:int, locked_days:int}
     */
    public function run(?Carbon $now = null, int $daysBack = 2): array
    {
        $company = Company::current();
        $tz = $company?->timezone ?: config('app.timezone');
        $cutoff = max(0, (int) ($company?->absent_cutoff_minutes ?? 120));
        $now = ($now ?? Carbon::now())->copy()->setTimezone($tz);
        $daysBack = min(max($daysBack, 0), 31);

        $totals = ['absent' => 0, 'leave' => 0, 'locked_days' => 0];

        for ($i = $daysBack; $i >= 0; $i--) {
            $this->processDate($now->copy()->startOfDay()->subDays($i), $now, $cutoff, $totals);
        }

        if ($totals['absent'] > 0 || $totals['leave'] > 0) {
            AuditLog::record('auto_attendance', null, null, $totals + ['run_at' => $now->toDateTimeString()]);
        }

        return $totals;
    }

    private function processDate(Carbon $date, Carbon $now, int $cutoff, array &$totals): void
    {
        $dateStr = $date->toDateString();

        // Hari libur: tidak ada yang dianggap absent.
        if ($this->schedules->holidayOn($date)) {
            return;
        }

        $assignments = $this->schedules->effectiveAssignmentsOn($date);
        if ($assignments->isEmpty()) {
            return;
        }

        if (PayrollLock::covers($dateStr)) {
            $totals['locked_days']++;

            return;
        }

        $ids = $assignments->keys()->all();

        $recorded = AttendanceRecord::query()
            ->whereDate('attendance_date', $dateStr)
            ->whereIn('employee_id', $ids)
            ->pluck('employee_id')
            ->flip();

        // Hanya izin yang DISETUJUI yang sah.
        $leaves = LeaveRequest::query()
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->whereIn('employee_id', $ids)
            ->whereDate('start_date', '<=', $dateStr)
            ->whereDate('end_date', '>=', $dateStr)
            ->get()
            ->keyBy('employee_id');

        $employees = Employee::query()->whereIn('id', $ids)->get()->keyBy('id');

        foreach ($assignments as $employeeId => $assignment) {
            if ($recorded->has($employeeId)) {
                continue;
            }

            $employee = $employees->get($employeeId);
            if (!$employee || $employee->join_date->toDateString() > $dateStr) {
                continue;
            }

            $schedule = $assignment->workSchedule;
            $day = $schedule->dayConfig($date->dayOfWeekIso);
            if (!$day || !$day->is_working_day) {
                continue; // bukan hari kerja pada jadwalnya
            }

            $base = [
                'work_schedule_id' => $schedule->id,
                'work_mode' => $this->schedules->modeFor($employee, $day),
                'late_minutes' => 0,
                'verification_method' => 'system',
            ];

            if ($leave = $leaves->get($employeeId)) {
                if ($this->create($employeeId, $dateStr, $base + [
                    'status' => AttendanceRecord::STATUS_LEAVE,
                    'leave_request_id' => $leave->id,
                    'notes' => 'Izin yang disetujui (pengajuan #'.$leave->id.').',
                ])) {
                    $totals['leave']++;
                }

                continue;
            }

            $deadline = Carbon::parse($dateStr.' '.$schedule->check_out_time, $schedule->timezone)->addMinutes($cutoff);
            if ($now->lt($deadline)) {
                continue; // masih ada waktu untuk absen
            }

            if ($this->create($employeeId, $dateStr, $base + [
                'status' => AttendanceRecord::STATUS_ABSENT,
                'notes' => 'Ditandai otomatis: tidak ada absensi sampai batas waktu.',
            ])) {
                $totals['absent']++;
            }
        }
    }

    /** @return bool true jika catatan baru dibuat (false jika sudah ada) */
    private function create(int $employeeId, string $dateStr, array $values): bool
    {
        try {
            $record = AttendanceRecord::query()->firstOrCreate(
                ['employee_id' => $employeeId, 'attendance_date' => $dateStr],
                $values
            );
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return $record->wasRecentlyCreated;
    }
}
