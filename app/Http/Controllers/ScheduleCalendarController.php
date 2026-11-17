<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Services\ScheduleCalendarService;
use App\Services\ScheduleService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ScheduleCalendarController extends Controller
{
    /** Kalender admin: bulan (ringkas atau per karyawan), minggu, dan hari. */
    public function index(Request $request, ScheduleCalendarService $calendar): View
    {
        $view = in_array($request->query('view'), ['day', 'week', 'month'], true) ? $request->query('view') : 'month';
        $date = $this->parseDate($request->query('date'));
        if ($view === 'month') {
            $date = $date->copy()->startOfMonth();
        }

        $employeeId = $request->filled('employee_id') ? $request->integer('employee_id') : null;
        $departmentId = $request->filled('department_id') ? $request->integer('department_id') : null;

        [$from, $to, $prev, $next, $title] = $this->range($view, $date);

        $detailed = $view !== 'month' || $employeeId !== null;
        $cal = $calendar->build($from, $to, $employeeId, $departmentId, $detailed, $detailed ? 300 : null);

        $filters = array_filter(
            ['employee_id' => $employeeId, 'department_id' => $departmentId],
            fn ($v) => $v !== null
        );
        $url = fn (array $override) => route('schedules.calendar', array_merge($filters, ['view' => $view, 'date' => $date->toDateString()], $override));

        return view('schedules.calendar', [
            'view' => $view,
            'date' => $date,
            'title' => $title,
            'cal' => $cal,
            'filters' => $filters,
            'employeeId' => $employeeId,
            'departmentId' => $departmentId,
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'employees' => Employee::active()->orderBy('full_name')->get(['id', 'full_name', 'employee_number']),
            'urls' => [
                'prev' => $url(['date' => $prev->toDateString()]),
                'next' => $url(['date' => $next->toDateString()]),
                'today' => $url(['date' => today()->toDateString()]),
                'month' => $url(['view' => 'month']),
                'week' => $url(['view' => 'week']),
                'day' => $url(['view' => 'day']),
            ],
        ]);
    }

    /** Jadwal Saya (karyawan): hanya data milik sendiri. */
    public function mine(Request $request, ScheduleCalendarService $calendar, ScheduleService $schedules): View
    {
        $employee = $request->user()->employee()->with(['position', 'department'])->first();
        abort_if(!$employee, 403, 'Akun Anda belum terhubung dengan data karyawan. Hubungi administrator.');

        $date = $this->parseDate($request->query('date'))->startOfMonth();
        [$from, $to, $prev, $next, $title] = $this->range('month', $date);

        $cal = $calendar->build($from, $to, $employee->id, null, true);

        return view('schedules.my', [
            'employee' => $employee,
            'date' => $date,
            'title' => $title,
            'cal' => $cal,
            'resolved' => $schedules->resolveFor($employee, today()),
            'upcoming' => EmployeeSchedule::with('workSchedule')
                ->where('employee_id', $employee->id)
                ->where('start_date', '>', today()->toDateString())
                ->orderBy('start_date')->first(),
            'urls' => [
                'prev' => route('employee.schedule', ['date' => $prev->toDateString()]),
                'next' => route('employee.schedule', ['date' => $next->toDateString()]),
                'today' => route('employee.schedule'),
            ],
        ]);
    }

    private function parseDate(?string $value): Carbon
    {
        if ($value) {
            try {
                $d = Carbon::createFromFormat('!Y-m-d', $value);
                if ($d && $d->format('Y-m-d') === $value && $d->year >= 2000 && $d->year <= 2100) {
                    return $d;
                }
            } catch (\Throwable) {
                // abaikan: pakai hari ini
            }
        }

        return Carbon::today();
    }

    /** @return array{0:Carbon,1:Carbon,2:Carbon,3:Carbon,4:string} from, to, prev, next, judul */
    private function range(string $view, Carbon $date): array
    {
        if ($view === 'day') {
            return [
                $date->copy(), $date->copy(),
                $date->copy()->subDay(), $date->copy()->addDay(),
                $date->copy()->locale('id')->translatedFormat('l, d F Y'),
            ];
        }

        if ($view === 'week') {
            $from = $date->copy()->startOfWeek(Carbon::MONDAY);
            $to = $date->copy()->endOfWeek(Carbon::SUNDAY);

            return [
                $from, $to,
                $date->copy()->subWeek(), $date->copy()->addWeek(),
                $from->copy()->locale('id')->translatedFormat('d M').' - '.$to->copy()->locale('id')->translatedFormat('d M Y'),
            ];
        }

        $first = $date->copy()->startOfMonth();

        return [
            $first->copy()->startOfWeek(Carbon::MONDAY),
            $first->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY),
            $first->copy()->subMonth(), $first->copy()->addMonth(),
            $first->copy()->locale('id')->translatedFormat('F Y'),
        ];
    }
}
