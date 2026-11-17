<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\FeatureSetting;
use App\Models\LeaveRequest;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

class DashboardService
{
    /** Warna status (satu-satunya selain palet utama yang dipakai, sesuai ketentuan). */
    public const STATUSES = [
        'present' => ['label' => 'Hadir', 'color' => '#3c6e71'],
        'late' => ['label' => 'Terlambat', 'color' => '#e0a100'],
        'absent' => ['label' => 'Tidak Hadir', 'color' => '#c0392b'],
        'leave' => ['label' => 'Izin', 'color' => '#284b63'],
    ];

    public function __construct(private ScheduleService $schedules) {}

    /* ===================== Admin / Super Admin ===================== */

    public function admin(): array
    {
        $today = Carbon::today();
        $todayStr = $today->toDateString();
        $rangeStart = $today->copy()->startOfMonth()->subMonths(5);

        // ---- Ringkasan hari ini ----
        $todayCounts = AttendanceRecord::query()
            ->whereDate('attendance_date', $todayStr)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $present = (int) ($todayCounts['present'] ?? 0);
        $late = (int) ($todayCounts['late'] ?? 0);
        $absent = (int) ($todayCounts['absent'] ?? 0);

        // Izin hari ini = karyawan dengan pengajuan yang DISETUJUI dan mencakup hari ini.
        // Pengajuan pending tidak dihitung sebagai izin yang sah.
        $onLeave = LeaveRequest::query()
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->whereDate('start_date', '<=', $todayStr)
            ->whereDate('end_date', '>=', $todayStr)
            ->distinct()
            ->count('employee_id');

        // ---- Satu query untuk semua grafik (6 bulan terakhir) ----
        $byDate = [];
        DB::table('attendance_records')
            ->selectRaw('attendance_date, status, COUNT(*) as total')
            ->whereBetween('attendance_date', [$rangeStart->toDateString(), $todayStr])
            ->groupBy('attendance_date', 'status')
            ->get()
            ->each(function ($row) use (&$byDate) {
                $byDate[substr((string) $row->attendance_date, 0, 10)][$row->status] = (int) $row->total;
            });

        $weekly = $this->weeklySeries($today, $byDate);
        $monthly = $this->monthlySeries($today, $byDate);
        $distribution = $this->distribution($monthly);

        // ---- Daftar terbaru ----
        $latestAttendance = AttendanceRecord::query()
            ->with('employee.position')
            ->orderByDesc('attendance_date')
            ->orderByDesc('id')
            ->limit(6)
            ->get();

        $latestLeaves = LeaveRequest::query()
            ->with(['employee', 'leaveType'])
            ->latest()
            ->limit(6)
            ->get();

        // ---- Payroll bulan ini ----
        $period = PayrollPeriod::query()
            ->where('year', $today->year)
            ->where('month', $today->month)
            ->first();
        $salaryAmount = $period
            ? (float) Payroll::query()->where('payroll_period_id', $period->id)->sum('net_salary')
            : 0.0;

        return [
            'today_label' => $today->copy()->locale('id')->translatedFormat('l, d F Y'),
            'features' => collect(['employees', 'schedules', 'attendance', 'leave_requests', 'payroll'])
                ->mapWithKeys(fn($key) => [$key => FeatureSetting::isEnabled($key)])
                ->all(),
            'links' => [
                'attendance' => Route::has('attendance.index') ? route('attendance.index') : null,
                'leave' => Route::has('leave-requests.index') ? route('leave-requests.index') : null,
            ],
            'counts' => [
                'total_employees' => Employee::query()->count(),
                'active_employees' => Employee::query()->active()->count(),
                'attended_today' => $present + $late,
                'late_today' => $late,
                'absent_today' => $absent,
                'leave_today' => $onLeave,
                'pending_leaves' => LeaveRequest::query()->where('status', LeaveRequest::STATUS_PENDING)->count(),
            ],
            'salary' => [
                'label' => 'Rp ' . number_format($salaryAmount, 0, ',', '.'),
                'hint' => $period
                    ? 'Payroll periode ini berstatus ' . ucfirst($period->status)
                    : 'Payroll bulan ini belum dibuat',
            ],
            'charts' => [
                'weekly' => $weekly,
                'monthly' => $monthly,
            ],
            'distribution' => $distribution,
            'schedule_today' => $this->scheduleToday($today),
            'latest_attendance' => $latestAttendance,
            'latest_leaves' => $latestLeaves,
        ];
    }

    private function emptySeries(): array
    {
        $series = [];
        foreach (self::STATUSES as $key => $meta) {
            $series[$key] = ['key' => $key, 'label' => $meta['label'], 'color' => $meta['color'], 'data' => []];
        }

        return $series;
    }

    /** 7 hari terakhir (termasuk hari ini). */
    private function weeklySeries(Carbon $today, array $byDate): array
    {
        $series = $this->emptySeries();
        $labels = [];

        for ($i = 6; $i >= 0; $i--) {
            $day = $today->copy()->subDays($i);
            $labels[] = $day->locale('id')->translatedFormat('D, d/m');
            foreach (array_keys(self::STATUSES) as $key) {
                $series[$key]['data'][] = $byDate[$day->toDateString()][$key] ?? 0;
            }
        }

        return ['labels' => $labels, 'series' => array_values($series)];
    }

    /** 6 bulan terakhir (termasuk bulan ini). */
    private function monthlySeries(Carbon $today, array $byDate): array
    {
        $series = $this->emptySeries();
        $labels = [];
        $monthKeys = [];

        for ($i = 5; $i >= 0; $i--) {
            $start = $today->copy()->startOfMonth()->subMonths($i);
            $monthKeys[] = $start->format('Y-m');
            $labels[] = $start->locale('id')->translatedFormat('M Y');
            foreach (array_keys(self::STATUSES) as $key) {
                $series[$key]['data'][] = 0;
            }
        }

        foreach ($byDate as $date => $statuses) {
            $index = array_search(substr($date, 0, 7), $monthKeys, true);
            if ($index === false) {
                continue;
            }
            foreach ($statuses as $status => $total) {
                if (isset($series[$status])) {
                    $series[$status]['data'][$index] += $total;
                }
            }
        }

        return ['labels' => $labels, 'series' => array_values($series)];
    }

    /** Komposisi status bulan berjalan untuk donut. */
    private function distribution(array $monthly): array
    {
        $last = count($monthly['labels']) - 1;
        $items = [];
        $total = 0;

        foreach ($monthly['series'] as $serie) {
            $count = (int) $serie['data'][$last];
            $total += $count;
            $items[] = ['label' => $serie['label'], 'color' => $serie['color'], 'count' => $count];
        }

        $stops = [];
        $cursor = 0.0;
        if ($total > 0) {
            foreach ($items as &$item) {
                $item['percent'] = round($item['count'] / $total * 100, 1);
                if ($item['count'] > 0) {
                    $from = $cursor;
                    $cursor += $item['count'] / $total * 100;
                    $stops[] = $item['color'] . ' ' . number_format($from, 2, '.', '') . '% ' . number_format($cursor, 2, '.', '') . '%';
                }
            }
            unset($item);
        } else {
            foreach ($items as &$item) {
                $item['percent'] = 0;
            }
            unset($item);
        }

        return [
            'total' => $total,
            'items' => $items,
            'gradient' => $stops ? 'conic-gradient(' . implode(', ', $stops) . ')' : null,
            'month_label' => Carbon::today()->locale('id')->translatedFormat('F Y'),
        ];
    }

    /** Ringkasan jadwal hari ini: berapa karyawan bekerja per jadwal dan berapa yang belum absen. */
    private function scheduleToday(Carbon $today): array
    {
        $holiday = $this->schedules->holidayOn($today);
        $assignments = $this->schedules->effectiveAssignmentsOn($today);

        $groups = [];
        $workingIds = [];

        foreach ($assignments as $assignment) {
            $schedule = $assignment->workSchedule;
            $day = $schedule->dayConfig($today->dayOfWeekIso);
            $isWorking = !$holiday && $day !== null && $day->is_working_day;

            $groups[$schedule->id] ??= [
                'name' => $schedule->name,
                'check_in' => substr((string) $schedule->check_in_time, 0, 5),
                'check_out' => substr((string) $schedule->check_out_time, 0, 5),
                'assigned' => 0,
                'working' => 0,
            ];
            $groups[$schedule->id]['assigned']++;

            if ($isWorking) {
                $groups[$schedule->id]['working']++;
                $workingIds[] = $assignment->employee_id;
            }
        }

        $alreadyRecorded = $workingIds
            ? AttendanceRecord::query()
            ->whereDate('attendance_date', $today->toDateString())
            ->whereIn('employee_id', $workingIds)
            ->count()
            : 0;

        return [
            'holiday_name' => $holiday?->name,
            'groups' => array_values($groups),
            'working_total' => count($workingIds),
            'not_yet_attended' => max(0, count($workingIds) - $alreadyRecorded),
            'without_schedule' => max(0, Employee::query()->active()->count() - $assignments->count()),
        ];
    }

    /* ===================== Karyawan ===================== */

    public function employee(Employee $employee): array
    {
        $today = Carbon::today();
        $todayStr = $today->toDateString();

        $resolved = $this->schedules->resolveFor($employee, $today);

        $record = AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->whereDate('attendance_date', $todayStr)
            ->first();

        $monthCounts = AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('attendance_date', [
                $today->copy()->startOfMonth()->toDateString(),
                $today->copy()->endOfMonth()->toDateString(),
            ])
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $schedule = null;
        if ($resolved) {
            $ws = $resolved['schedule'];
            $schedule = [
                'name' => $ws->name,
                'check_in' => substr((string) $ws->check_in_time, 0, 5),
                'check_out' => substr((string) $ws->check_out_time, 0, 5),
                'tolerance' => (int) $ws->late_tolerance_minutes,
                'work_mode' => $resolved['work_mode'],
                'is_working_day' => $resolved['is_working_day'],
                'holiday_name' => $resolved['holiday_name'],
            ];
        }

        $attendanceUrl = (Route::has('employee.attendance') && FeatureSetting::isEnabled('attendance'))
            ? route('employee.attendance')
            : null;

        return [
            'today_label' => $today->copy()->locale('id')->translatedFormat('l, d F Y'),
            'schedule' => $schedule,
            'today_status' => $this->todayStatus($resolved, $record, $employee, $todayStr),
            'check_in' => $record?->check_in_at?->format('H:i'),
            'check_out' => $record?->check_out_at?->format('H:i'),
            'attendance_url' => $attendanceUrl,
            'month_label' => $today->copy()->locale('id')->translatedFormat('F Y'),
            'month' => [
                'present' => (int) ($monthCounts['present'] ?? 0),
                'late' => (int) ($monthCounts['late'] ?? 0),
                'absent' => (int) ($monthCounts['absent'] ?? 0),
                'leave' => (int) ($monthCounts['leave'] ?? 0),
            ],
            'photo_url' => $employee->photo ? asset('storage/' . $employee->photo) : null,
        ];
    }

    /** @return array{0:string,1:string} label dan nada warna */
    private function todayStatus(?array $resolved, ?AttendanceRecord $record, Employee $employee, string $todayStr): array
    {
        if ($record) {
            return match ($record->status) {
                AttendanceRecord::STATUS_PRESENT => ['Hadir', 'success'],
                AttendanceRecord::STATUS_LATE => ['Terlambat', 'warning'],
                AttendanceRecord::STATUS_ABSENT => ['Tidak Hadir', 'danger'],
                default => ['Izin', 'info'],
            };
        }

        if (!$resolved) {
            return ['Belum ada jadwal', 'muted'];
        }
        if ($resolved['holiday_name']) {
            return ['Hari libur: ' . $resolved['holiday_name'], 'muted'];
        }
        if (!$resolved['is_working_day']) {
            return ['Libur (bukan hari kerja)', 'muted'];
        }

        $hasApprovedLeave = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->whereDate('start_date', '<=', $todayStr)
            ->whereDate('end_date', '>=', $todayStr)
            ->exists();

        return $hasApprovedLeave ? ['Izin disetujui', 'info'] : ['Belum absen', 'warning'];
    }
}
