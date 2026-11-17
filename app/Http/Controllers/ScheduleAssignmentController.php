<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\WorkSchedule;
use App\Services\ScheduleAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ScheduleAssignmentController extends Controller
{
    public function __construct(private ScheduleAssignmentService $assignments)
    {
    }

    public function index(Request $request): View
    {
        $todayStr = today()->toDateString();
        $term = trim((string) $request->query('q'));
        $state = in_array($request->query('state'), ['current', 'upcoming', 'ended', 'all'], true)
            ? $request->query('state')
            : 'current';

        $assignments = EmployeeSchedule::query()
            ->with(['employee.department', 'employee.position', 'workSchedule'])
            ->whereHas('employee')
            ->when($term !== '', fn ($q) => $q->whereHas('employee', fn ($e) => $e->search($term)))
            ->when($request->filled('department_id'),
                fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('department_id', $request->integer('department_id'))))
            ->when($request->filled('work_schedule_id'), fn ($q) => $q->where('work_schedule_id', $request->integer('work_schedule_id')))
            ->when($state === 'current', fn ($q) => $q->effectiveOn($todayStr))
            ->when($state === 'upcoming', fn ($q) => $q->where('start_date', '>', $todayStr))
            ->when($state === 'ended', fn ($q) => $q->whereNotNull('end_date')->where('end_date', '<', $todayStr))
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $unscheduledQuery = Employee::query()->active()
            ->whereDoesntHave('schedules', fn ($q) => $q->effectiveOn($todayStr));

        return view('schedules.assignments', [
            'assignments' => $assignments,
            'state' => $state,
            'allSchedules' => WorkSchedule::orderBy('name')->get(['id', 'name']),
            'activeSchedules' => WorkSchedule::where('is_active', true)->orderBy('name')->get(),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'employeesAll' => Employee::active()->with('department:id,name')->orderBy('full_name')
                ->get(['id', 'full_name', 'employee_number', 'department_id']),
            'unscheduledCount' => (clone $unscheduledQuery)->count(),
            'unscheduledNames' => (clone $unscheduledQuery)->orderBy('full_name')->limit(5)->pluck('full_name'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $target = $request->input('target');

        $data = $request->validate([
            'target' => ['required', Rule::in(['employees', 'department', 'unscheduled'])],
            'work_schedule_id' => ['required', Rule::exists('work_schedules', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'employee_ids' => [Rule::requiredIf($target === 'employees'), 'nullable', 'array'],
            'employee_ids.*' => ['integer', Rule::exists('employees', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'department_id' => [Rule::requiredIf($target === 'department'), 'nullable', Rule::exists('departments', 'id')->whereNull('deleted_at')],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ], [
            'work_schedule_id.exists' => 'Jadwal tidak ditemukan atau sudah nonaktif.',
            'employee_ids.array' => 'Pilihan karyawan tidak valid.',
            'employee_ids.*.exists' => 'Ada karyawan yang tidak ditemukan atau sudah nonaktif.',
        ], [
            'target' => 'target penugasan',
            'work_schedule_id' => 'jadwal',
            'employee_ids' => 'karyawan',
            'department_id' => 'departemen',
            'start_date' => 'tanggal mulai',
            'end_date' => 'tanggal berakhir',
        ]);

        $start = Carbon::parse($data['start_date'])->startOfDay();
        $end = !empty($data['end_date']) ? Carbon::parse($data['end_date'])->startOfDay() : null;

        $ids = match ($data['target']) {
            'employees' => Employee::active()->whereIn('id', $data['employee_ids'])->pluck('id'),
            'department' => Employee::active()->where('department_id', $data['department_id'])->pluck('id'),
            default => Employee::active()
                ->whereDoesntHave('schedules', fn ($q) => $q->effectiveOn($start->toDateString()))
                ->pluck('id'),
        };

        if ($ids->isEmpty()) {
            throw ValidationException::withMessages([
                'target' => 'Tidak ada karyawan aktif yang cocok dengan target yang dipilih.',
            ]);
        }

        // Jadwal tidak boleh diubah mundur jika karyawan sudah punya absensi dalam rentang tersebut.
        $blocked = AttendanceRecord::query()
            ->whereIn('employee_id', $ids)
            ->where('attendance_date', '>=', $start->toDateString())
            ->when($end, fn ($q) => $q->where('attendance_date', '<=', $end->toDateString()))
            ->select('employee_id', DB::raw('MAX(attendance_date) as last_date'))
            ->groupBy('employee_id')
            ->get();

        if ($blocked->isNotEmpty()) {
            $names = Employee::whereIn('id', $blocked->pluck('employee_id'))->orderBy('full_name')->limit(5)->pluck('full_name')->implode(', ');
            $more = $blocked->count() > 5 ? ' dan lainnya' : '';
            $last = Carbon::parse($blocked->max('last_date'))->format('d/m/Y');

            throw ValidationException::withMessages([
                'start_date' => "Penugasan ditolak: {$names}{$more} sudah memiliki catatan absensi dalam rentang tanggal tersebut (terakhir {$last}). Jadwal tidak dapat diubah mundur. Pilih tanggal mulai setelah {$last}.",
            ]);
        }

        $schedule = WorkSchedule::findOrFail($data['work_schedule_id']);
        $count = $this->assignments->assign($ids, $schedule->id, $start, $end, $request->user()->id);

        AuditLog::record('assign_schedule', $schedule, null, [
            'target' => $data['target'],
            'employee_ids' => $ids->values()->all(),
            'start_date' => $start->toDateString(),
            'end_date' => $end?->toDateString(),
        ]);

        $period = 'mulai '.$start->format('d/m/Y').($end ? ' sampai '.$end->format('d/m/Y') : ' seterusnya');

        return redirect()->route('schedules.assignments.index', ['state' => 'all'])
            ->with('success', "Jadwal \"{$schedule->name}\" ditugaskan ke {$count} karyawan, {$period}.");
    }

    /** Akhiri penugasan yang sedang berjalan: berlaku sampai hari ini. */
    public function end(string $id): RedirectResponse
    {
        $assignment = EmployeeSchedule::with(['employee', 'workSchedule'])->findOrFail($id);
        $today = today();

        if ($assignment->start_date->gt($today)) {
            return back()->with('error', 'Penugasan ini belum dimulai. Gunakan Hapus untuk membatalkannya.');
        }
        if ($assignment->end_date !== null && $assignment->end_date->lte($today)) {
            return back()->with('error', 'Penugasan ini sudah berakhir.');
        }

        $old = ['end_date' => $assignment->end_date?->toDateString()];
        $assignment->update(['end_date' => $today->toDateString()]);

        AuditLog::record('end_assignment', $assignment, $old, ['end_date' => $today->toDateString()]);

        return back()->with('success', "Penugasan jadwal {$assignment->employee?->full_name} diakhiri sampai hari ini.");
    }

    public function destroy(string $id): RedirectResponse
    {
        $assignment = EmployeeSchedule::with('employee')->findOrFail($id);

        $used = AttendanceRecord::query()
            ->where('employee_id', $assignment->employee_id)
            ->where('work_schedule_id', $assignment->work_schedule_id)
            ->where('attendance_date', '>=', $assignment->start_date->toDateString())
            ->when($assignment->end_date, fn ($q) => $q->where('attendance_date', '<=', $assignment->end_date->toDateString()))
            ->exists();

        if ($used) {
            return back()->with('error', 'Penugasan ini sudah dipakai absensi sehingga tidak dapat dihapus. Gunakan Akhiri sebagai gantinya.');
        }

        $old = $assignment->only(['employee_id', 'work_schedule_id', 'start_date', 'end_date']);
        $assignment->delete();

        AuditLog::record('delete_assignment', $assignment, $old);

        return back()->with('success', 'Penugasan jadwal berhasil dihapus.');
    }
}
