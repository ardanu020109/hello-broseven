<?php

namespace App\Services;

use App\Models\EmployeeSchedule;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ScheduleAssignmentService
{
    /**
     * Tugaskan satu jadwal ke banyak karyawan pada rentang tanggal tertentu.
     * Penugasan lama yang bertabrakan dirapikan agar satu karyawan hanya punya satu jadwal per hari:
     *  - dimulai sebelum rentang baru   : dipotong (end_date = hari sebelum rentang baru),
     *                                     dan sisa setelah rentang baru dibuat sebagai penugasan lanjutan
     *  - berada di dalam rentang baru   : dihapus
     *  - menjorok ke ujung rentang baru : start_date dimajukan ke hari setelah rentang baru
     */
    public function assign(
        Collection|array $employeeIds,
        int $scheduleId,
        CarbonInterface $start,
        ?CarbonInterface $end,
        ?int $assignedBy
    ): int {
        $startStr = $start->toDateString();
        $endStr = $end?->toDateString();
        $count = 0;

        DB::transaction(function () use ($employeeIds, $scheduleId, $startStr, $endStr, $assignedBy, &$count) {
            foreach ($employeeIds as $employeeId) {
                $overlapping = EmployeeSchedule::query()
                    ->where('employee_id', $employeeId)
                    ->whereDate('start_date', '<=', $endStr ?? '9999-12-31')
                    ->where(function ($q) use ($startStr) {
                        $q->whereNull('end_date')->orWhereDate('end_date', '>=', $startStr);
                    })
                    ->lockForUpdate()
                    ->get();

                foreach ($overlapping as $old) {
                    $oldStart = $old->start_date->toDateString();
                    $oldEnd = $old->end_date?->toDateString();

                    if ($oldStart < $startStr) {
                        // Sisa penugasan lama setelah rentang baru dipertahankan
                        if ($endStr !== null && ($oldEnd === null || $oldEnd > $endStr)) {
                            EmployeeSchedule::create([
                                'employee_id' => $employeeId,
                                'work_schedule_id' => $old->work_schedule_id,
                                'start_date' => Carbon::parse($endStr)->addDay()->toDateString(),
                                'end_date' => $oldEnd,
                                'assigned_by' => $old->assigned_by,
                            ]);
                        }
                        $old->update(['end_date' => Carbon::parse($startStr)->subDay()->toDateString()]);
                    } elseif ($endStr === null || ($oldEnd !== null && $oldEnd <= $endStr)) {
                        $old->delete();
                    } else {
                        $old->update(['start_date' => Carbon::parse($endStr)->addDay()->toDateString()]);
                    }
                }

                EmployeeSchedule::create([
                    'employee_id' => $employeeId,
                    'work_schedule_id' => $scheduleId,
                    'start_date' => $startStr,
                    'end_date' => $endStr,
                    'assigned_by' => $assignedBy,
                ]);
                $count++;
            }
        });

        return $count;
    }
}
