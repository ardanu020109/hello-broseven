<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\Holiday;
use Carbon\CarbonInterface;

class ScheduleCalendarService
{
    public function __construct(private ScheduleService $schedules)
    {
    }

    /**
     * @return array{days: array, matrix: array, employees: \Illuminate\Support\Collection, total: int, truncated: bool}
     */
    public function build(
        CarbonInterface $from,
        CarbonInterface $to,
        ?int $employeeId,
        ?int $departmentId,
        bool $detailed,
        ?int $limit = null
    ): array {
        $query = Employee::query()->active()
            ->when($employeeId, fn ($q) => $q->whereKey($employeeId))
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->orderBy('full_name');

        $total = (clone $query)->count();
        if ($limit) {
            $query->limit($limit);
        }

        $employees = $query->with('position:id,name')
            ->get(['id', 'full_name', 'employee_number', 'work_mode', 'position_id', 'department_id']);

        $assignments = $employees->isEmpty()
            ? collect()
            : EmployeeSchedule::query()
                ->whereIn('employee_id', $employees->pluck('id'))
                ->whereDate('start_date', '<=', $to->toDateString())
                ->where(function ($q) use ($from) {
                    $q->whereNull('end_date')->orWhereDate('end_date', '>=', $from->toDateString());
                })
                ->with('workSchedule.days')
                ->get()
                ->groupBy('employee_id');

        $holidays = Holiday::query()
            ->whereBetween('holiday_date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->keyBy(fn (Holiday $h) => $h->holiday_date->toDateString());

        $days = [];
        $matrix = [];

        for ($d = $from->copy()->startOfDay(); $d->lte($to); $d->addDay()) {
            $key = $d->toDateString();
            $holiday = $holidays->get($key);

            $day = [
                'date' => $d->copy(),
                'holiday' => $holiday?->name,
                'working_count' => 0,
                'off_count' => 0,
                'unscheduled' => 0,
                'groups' => [],
            ];

            foreach ($employees as $emp) {
                $assignment = $this->schedules->pickAssignment($assignments->get($emp->id, collect()), $d);
                $ws = $assignment?->workSchedule;

                $entry = ['state' => 'none', 'schedule' => null, 'time' => null, 'mode' => null];

                if ($ws) {
                    $cfg = $ws->dayConfig($d->dayOfWeekIso);
                    $time = substr((string) $ws->check_in_time, 0, 5).' - '.substr((string) $ws->check_out_time, 0, 5);

                    if ($holiday) {
                        $state = 'holiday';
                    } elseif ($cfg && $cfg->is_working_day) {
                        $state = 'working';
                    } else {
                        $state = 'off';
                    }

                    $entry = [
                        'state' => $state,
                        'schedule' => $ws->name,
                        'time' => $time,
                        'mode' => $state === 'working' ? $this->schedules->modeFor($emp, $cfg) : null,
                    ];

                    if ($state === 'working') {
                        $day['working_count']++;
                        $day['groups'][$ws->id] ??= ['name' => $ws->name, 'time' => $time, 'count' => 0];
                        $day['groups'][$ws->id]['count']++;
                    } else {
                        $day['off_count']++;
                    }
                } else {
                    $day['unscheduled']++;
                }

                if ($detailed) {
                    $matrix[$emp->id][$key] = $entry;
                }
            }

            $days[$key] = $day;
        }

        return [
            'days' => $days,
            'matrix' => $matrix,
            'employees' => $employees,
            'total' => $total,
            'truncated' => $total > $employees->count(),
        ];
    }
}
