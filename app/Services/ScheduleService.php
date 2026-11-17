<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\Holiday;
use App\Models\ScheduleDay;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class ScheduleService
{
    public function holidayOn(CarbonInterface $date): ?Holiday
    {
        return Holiday::query()->whereDate('holiday_date', $date->toDateString())->first();
    }

    /**
     * Penugasan jadwal yang berlaku pada tanggal tertentu untuk seluruh karyawan aktif.
     * Jika ada beberapa penugasan tumpang tindih, yang start_date-nya terbaru dipakai.
     *
     * @return Collection<int, EmployeeSchedule> dikunci dengan employee_id
     */
    public function effectiveAssignmentsOn(CarbonInterface $date): Collection
    {
        return EmployeeSchedule::query()
            ->effectiveOn($date->toDateString())
            ->whereHas('employee', fn ($q) => $q->where('is_active', true))
            ->with(['workSchedule.days'])
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (EmployeeSchedule $a) => $a->workSchedule !== null)
            ->unique('employee_id')
            ->keyBy('employee_id');
    }

    /**
     * Dari kumpulan penugasan milik satu karyawan, pilih yang berlaku pada tanggal tertentu.
     * Jika tumpang tindih, start_date terbaru menang, lalu id terbesar.
     *
     * @param iterable<EmployeeSchedule> $assignments
     */
    public function pickAssignment(iterable $assignments, CarbonInterface $date): ?EmployeeSchedule
    {
        $day = $date->toDateString();
        $best = null;

        foreach ($assignments as $a) {
            if ($a->start_date->toDateString() > $day) {
                continue;
            }
            if ($a->end_date !== null && $a->end_date->toDateString() < $day) {
                continue;
            }
            if ($best === null
                || $a->start_date->gt($best->start_date)
                || ($a->start_date->eq($best->start_date) && $a->id > $best->id)) {
                $best = $a;
            }
        }

        return $best;
    }

    /**
     * Mode kerja efektif: karyawan WFO atau WFH memakai mode di profilnya.
     * Karyawan Hybrid mengikuti schedule_days.work_mode pada hari tersebut.
     */
    public function modeFor(Employee $employee, ?ScheduleDay $day): string
    {
        return $employee->work_mode === Employee::MODE_HYBRID
            ? ($day?->work_mode ?? 'WFO')
            : $employee->work_mode;
    }

    /**
     * Jadwal efektif satu karyawan pada tanggal tertentu.
     * Mengembalikan null jika karyawan belum punya jadwal pada tanggal itu.
     */
    public function resolveFor(Employee $employee, CarbonInterface $date): ?array
    {
        $assignment = EmployeeSchedule::query()
            ->with('workSchedule.days')
            ->where('employee_id', $employee->id)
            ->effectiveOn($date->toDateString())
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();

        $schedule = $assignment?->workSchedule;
        if (!$schedule) {
            return null;
        }

        $day = $schedule->dayConfig($date->dayOfWeekIso); // ISO: 1 = Senin ... 7 = Minggu
        $holiday = $this->holidayOn($date);

        return [
            'schedule' => $schedule,
            'day' => $day,
            'holiday_name' => $holiday?->name,
            'is_working_day' => !$holiday && $day !== null && $day->is_working_day,
            'work_mode' => $this->modeFor($employee, $day),
        ];
    }
}
