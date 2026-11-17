<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AttendanceSummaryService
{
    public function __construct(private ScheduleService $schedules)
    {
    }

    /** Hari ini menurut zona waktu perusahaan. */
    public function today(): Carbon
    {
        $tz = Company::current()?->timezone ?: config('app.timezone');

        return Carbon::now($tz)->startOfDay();
    }

    /**
     * Hitung status harian tiap karyawan pada rentang tanggal.
     *
     * Aturan:
     *  - hanya hari kerja pada jadwal efektif, bukan hari libur, dan tidak sebelum tanggal masuk kerja;
     *  - hari setelah hari ini tidak dihitung;
     *  - ada catatan absensi: pakai statusnya (Izin dipecah Izin/Sakit menurut kategori jenis izin);
     *  - tanpa catatan pada hari yang sudah lewat: Izin/Sakit jika ada izin disetujui, selain itu Tidak Hadir
     *    (hanya untuk karyawan aktif dan tidak terhapus);
     *  - tanpa catatan pada hari ini: belum dihitung.
     *
     * @return array<int, array{days: array<int, array>, counts: array<string,int>}>
     */
    public function build(Collection $employees, CarbonInterface $from, CarbonInterface $to, bool $keepDays): array
    {
        $todayStr = $this->today()->toDateString();
        $fromStr = $from->toDateString();
        $toStr = min($to->toDateString(), $todayStr);

        $result = [];
        foreach ($employees as $e) {
            $result[$e->id] = ['days' => [], 'counts' => $this->emptyCounts()];
        }
        if ($employees->isEmpty() || $fromStr > $toStr) {
            return $result;
        }

        $ids = $employees->pluck('id')->all();

        $assignments = EmployeeSchedule::query()
            ->whereIn('employee_id', $ids)
            ->whereDate('start_date', '<=', $toStr)
            ->where(function ($q) use ($fromStr) {
                $q->whereNull('end_date')->orWhereDate('end_date', '>=', $fromStr);
            })
            ->with('workSchedule.days')
            ->get()
            ->groupBy('employee_id');

        $holidays = Holiday::query()
            ->whereBetween('holiday_date', [$fromStr, $toStr])
            ->get()
            ->keyBy(fn (Holiday $h) => $h->holiday_date->toDateString());

        $records = AttendanceRecord::query()
            ->whereIn('employee_id', $ids)
            ->whereBetween('attendance_date', [$fromStr, $toStr])
            ->get()
            ->groupBy('employee_id')
            ->map(fn (Collection $g) => $g->keyBy(fn (AttendanceRecord $r) => $r->attendance_date->toDateString()));

        $allLeaves = LeaveRequest::query()
            ->with('leaveType.salaryDeductionRule')
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->whereIn('employee_id', $ids)
            ->whereDate('start_date', '<=', $toStr)
            ->whereDate('end_date', '>=', $fromStr)
            ->get();
        $leavesByEmployee = $allLeaves->groupBy('employee_id');
        $leavesById = $allLeaves->keyBy('id');

        foreach ($employees as $emp) {
            $empAssignments = $assignments->get($emp->id, collect());
            $empRecords = $records->get($emp->id, collect());
            $empLeaves = $leavesByEmployee->get($emp->id, collect());
            $joinStr = $emp->join_date->toDateString();
            $synthetic = $emp->is_active && !$emp->trashed();

            for ($d = Carbon::parse($fromStr); $d->toDateString() <= $toStr; $d->addDay()) {
                $dateStr = $d->toDateString();

                if ($dateStr < $joinStr || $holidays->has($dateStr)) {
                    continue;
                }

                $ws = $this->schedules->pickAssignment($empAssignments, $d)?->workSchedule;
                if (!$ws) {
                    continue;
                }

                $cfg = $ws->dayConfig($d->dayOfWeekIso);
                if (!$cfg || !$cfg->is_working_day) {
                    continue;
                }

                $record = $empRecords->get($dateStr);
                $leave = $empLeaves->first(
                    fn (LeaveRequest $l) => $l->start_date->toDateString() <= $dateStr && $l->end_date->toDateString() >= $dateStr
                );

                if ($record) {
                    switch ($record->status) {
                        case AttendanceRecord::STATUS_PRESENT:
                            [$status, $rule, $leaveObj] = ['present', 'present', null];
                            break;
                        case AttendanceRecord::STATUS_LATE:
                            [$status, $rule, $leaveObj] = ['late', 'late', null];
                            break;
                        case AttendanceRecord::STATUS_LEAVE:
                            $leaveObj = ($record->leave_request_id ? $leavesById->get($record->leave_request_id) : null) ?? $leave;
                            [$status, $rule] = $this->leaveStatus($leaveObj);
                            break;
                        default:
                            [$status, $rule, $leaveObj] = ['absent', 'absent', null];
                    }
                } else {
                    if (!$synthetic || $dateStr >= $todayStr) {
                        continue;
                    }
                    if ($leave) {
                        $leaveObj = $leave;
                        [$status, $rule] = $this->leaveStatus($leave);
                    } else {
                        [$status, $rule, $leaveObj] = ['absent', 'absent', null];
                    }
                }

                $result[$emp->id]['counts']['total']++;
                $result[$emp->id]['counts'][$status]++;
                if (!$record) {
                    $result[$emp->id]['counts']['unrecorded']++;
                }

                if ($keepDays) {
                    $result[$emp->id]['days'][] = [
                        'date' => $dateStr,
                        'status' => $status,
                        'rule_key' => $rule,
                        'record_id' => $record?->id,
                        'synthetic' => $record === null,
                        'leave_type' => $leaveObj?->leaveType?->name,
                        'check_in' => $record?->check_in_at?->format('H:i'),
                        'check_out' => $record?->check_out_at?->format('H:i'),
                        'late_minutes' => (int) ($record?->late_minutes ?? 0),
                        'work_mode' => $record?->work_mode ?? $this->schedules->modeFor($emp, $cfg),
                        'schedule' => $ws->name,
                    ];
                }
            }
        }

        return $result;
    }

    private function emptyCounts(): array
    {
        return ['total' => 0, 'present' => 0, 'late' => 0, 'absent' => 0, 'leave' => 0, 'sick' => 0, 'unrecorded' => 0];
    }

    /** @return array{0:string,1:string} [status harian, kunci aturan potongan] */
    private function leaveStatus(?LeaveRequest $leave): array
    {
        $status = $leave?->leaveType?->category === 'sick' ? 'sick' : 'leave';
        $rule = $leave?->leaveType?->salaryDeductionRule?->status_key ?? $status;

        return [$status, $rule];
    }
}
