<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Support\DateInput;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AttendanceReportService
{
    public function __construct(private AttendanceSummaryService $summary)
    {
    }

    /**
     * @return array{mode:string, from:Carbon, to:Carbon, label:string, month:string, year:int, clamped:bool, beyond_today:bool}
     */
    public function period(Request $request): array
    {
        $mode = in_array($request->query('mode'), ['month', 'year', 'range'], true) ? $request->query('mode') : 'month';
        $today = $this->summary->today();
        $clamped = false;

        $year = (int) $request->query('year');
        if ($year < 2000 || $year > 2100) {
            $year = $today->year;
        }

        if ($mode === 'range') {
            $from = DateInput::date($request->query('from')) ?? Carbon::parse($today->format('Y-m-01'));
            $to = DateInput::date($request->query('to')) ?? Carbon::parse($today->toDateString());
            if ($from->gt($to)) {
                [$from, $to] = [$to, $from];
            }
            if ($from->diffInDays($to) > 365) {
                $to = $from->copy()->addDays(365);
                $clamped = true;
            }
            $label = $from->format('d/m/Y').' - '.$to->format('d/m/Y');
        } elseif ($mode === 'year') {
            $from = Carbon::create($year, 1, 1)->startOfDay();
            $to = Carbon::create($year, 12, 31)->startOfDay();
            $label = 'Tahun '.$year;
        } else {
            $anchor = DateInput::month($request->query('month')) ?? Carbon::parse($today->format('Y-m-01'));
            $from = $anchor->copy()->startOfMonth();
            $to = $anchor->copy()->endOfMonth()->startOfDay();
            $label = $from->copy()->locale('id')->translatedFormat('F Y');
        }

        return [
            'mode' => $mode,
            'from' => $from,
            'to' => $to,
            'label' => $label,
            'month' => $from->format('Y-m'),
            'year' => $mode === 'year' ? $year : $from->year,
            'clamped' => $clamped,
            'beyond_today' => $to->toDateString() > $today->toDateString(),
        ];
    }

    /** @return array{employee_id:?int, position_id:?int, department_id:?int} */
    public function filters(Request $request): array
    {
        return [
            'employee_id' => $request->filled('employee_id') ? $request->integer('employee_id') : null,
            'position_id' => $request->filled('position_id') ? $request->integer('position_id') : null,
            'department_id' => $request->filled('department_id') ? $request->integer('department_id') : null,
        ];
    }

    public function describe(array $filters): string
    {
        $parts = [];

        if ($filters['department_id']) {
            $parts[] = 'Departemen: '.(Department::withTrashed()->whereKey($filters['department_id'])->value('name') ?? '-');
        }
        if ($filters['position_id']) {
            $parts[] = 'Jabatan: '.(Position::withTrashed()->whereKey($filters['position_id'])->value('name') ?? '-');
        }
        if ($filters['employee_id']) {
            $parts[] = 'Karyawan: '.(Employee::withTrashed()->whereKey($filters['employee_id'])->value('full_name') ?? '-');
        }

        return $parts ? implode('; ', $parts) : 'Semua karyawan';
    }

    /**
     * @return array{rows: \Illuminate\Support\Collection, totals: array<string,int>, skipped: int}
     */
    public function build(CarbonInterface $from, CarbonInterface $to, array $filters, bool $withDays): array
    {
        $employees = Employee::withTrashed()
            ->with([
                'position' => fn ($q) => $q->withTrashed(),
                'department' => fn ($q) => $q->withTrashed(),
            ])
            ->whereDate('join_date', '<=', $to->toDateString())
            ->when($filters['employee_id'], fn ($q, $v) => $q->whereKey($v))
            ->when($filters['position_id'], fn ($q, $v) => $q->where('position_id', $v))
            ->when($filters['department_id'], fn ($q, $v) => $q->where('department_id', $v))
            ->orderBy('full_name')
            ->get();

        $result = $this->summary->build($employees, $from, $to, $withDays);

        $rows = collect();
        $skipped = 0;
        $totals = ['employees' => 0, 'total' => 0, 'present' => 0, 'late' => 0, 'absent' => 0, 'leave' => 0, 'sick' => 0, 'unrecorded' => 0];

        foreach ($employees as $e) {
            $r = $result[$e->id];
            $c = $r['counts'];

            if ($c['total'] === 0) {
                $skipped++;

                continue;
            }

            $rows->push([
                'employee' => $e,
                'counts' => $c,
                'rate' => ($c['present'] + $c['late']) / $c['total'],
                'days' => $withDays ? $r['days'] : null,
            ]);

            foreach (['total', 'present', 'late', 'absent', 'leave', 'sick', 'unrecorded'] as $k) {
                $totals[$k] += $c[$k];
            }
        }

        $totals['employees'] = $rows->count();

        return ['rows' => $rows, 'totals' => $totals, 'skipped' => $skipped];
    }

    /** @return array{days: array<int,array>, counts: array<string,int>} */
    public function employeeDetail(Employee $employee, CarbonInterface $from, CarbonInterface $to): array
    {
        return $this->summary->build(collect([$employee]), $from, $to, true)[$employee->id];
    }
}
