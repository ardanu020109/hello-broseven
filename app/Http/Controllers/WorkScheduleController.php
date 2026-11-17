<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\ScheduleDay;
use App\Models\WorkSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WorkScheduleController extends Controller
{
    private const TIMEZONES = [
        'Asia/Jakarta' => 'WIB (Asia/Jakarta)',
        'Asia/Makassar' => 'WITA (Asia/Makassar)',
        'Asia/Jayapura' => 'WIT (Asia/Jayapura)',
    ];

    private const DAYS = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];

    private const TRACKED = [
        'name', 'check_in_time', 'check_out_time', 'late_tolerance_minutes',
        'break_start', 'break_end', 'is_shift', 'timezone', 'is_active',
    ];

    public function index(Request $request): View
    {
        $today = today()->toDateString();
        $term = trim((string) $request->query('q'));

        $schedules = WorkSchedule::query()
            ->with('days')
            ->withCount(['employeeSchedules as current_assignments' => fn ($q) => $q->effectiveOn($today)])
            ->when($term !== '', fn ($q) => $q->where('name', 'like', '%'.addcslashes($term, '%_\\').'%'))
            ->when(in_array($request->query('status'), ['active', 'inactive'], true),
                fn ($q) => $q->where('is_active', $request->query('status') === 'active'))
            ->when(in_array($request->query('type'), ['shift', 'regular'], true),
                fn ($q) => $q->where('is_shift', $request->query('type') === 'shift'))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('schedules.index', ['schedules' => $schedules]);
    }

    public function create(): View
    {
        return view('schedules.form', [
            'schedule' => null,
            'timezones' => $this->timezones(),
            'dayNames' => self::DAYS,
            'hasHistory' => false,
            'defaultTimezone' => Company::current()?->timezone ?? 'Asia/Makassar',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, null);

        $schedule = DB::transaction(function () use ($data, $request) {
            $schedule = WorkSchedule::create($this->modelAttributes($data, $request));
            $this->syncDays($schedule, $request);

            return $schedule;
        });

        AuditLog::record('create', $schedule, null, $schedule->only(self::TRACKED) + ['days' => $this->daysSnapshot($schedule)]);

        return redirect()->route('schedules.index')
            ->with('success', "Jadwal \"{$schedule->name}\" berhasil dibuat. Tugaskan ke karyawan melalui tab Penugasan.");
    }

    public function edit(string $id): View
    {
        $schedule = WorkSchedule::with('days')->findOrFail($id);

        return view('schedules.form', [
            'schedule' => $schedule,
            'timezones' => $this->timezones(),
            'dayNames' => self::DAYS,
            'hasHistory' => $schedule->attendanceRecords()->exists(),
            'defaultTimezone' => $schedule->timezone,
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $schedule = WorkSchedule::findOrFail($id);
        $data = $this->validated($request, $schedule);

        $old = $schedule->only(self::TRACKED) + ['days' => $this->daysSnapshot($schedule)];

        DB::transaction(function () use ($schedule, $data, $request) {
            $schedule->update($this->modelAttributes($data, $request));
            $this->syncDays($schedule, $request);
        });

        $schedule->refresh();
        AuditLog::record('update', $schedule, $old, $schedule->only(self::TRACKED) + ['days' => $this->daysSnapshot($schedule)]);

        return redirect()->route('schedules.index')
            ->with('success', "Jadwal \"{$schedule->name}\" berhasil diperbarui.");
    }

    public function toggle(string $id): RedirectResponse
    {
        $schedule = WorkSchedule::findOrFail($id);
        $was = (bool) $schedule->is_active;
        $schedule->update(['is_active' => !$was]);

        AuditLog::record('toggle_active', $schedule, ['is_active' => $was], ['is_active' => !$was]);

        return back()->with('success', "Jadwal \"{$schedule->name}\" ".($was ? 'dinonaktifkan. Penugasan yang sudah ada tetap berlaku.' : 'diaktifkan.'));
    }

    public function destroy(string $id): RedirectResponse
    {
        $schedule = WorkSchedule::findOrFail($id);

        if ($schedule->attendanceRecords()->exists()) {
            return back()->with('error', "Jadwal \"{$schedule->name}\" sudah memiliki riwayat absensi sehingga tidak dapat dihapus. Nonaktifkan jadwal ini sebagai gantinya.");
        }
        if ($schedule->employeeSchedules()->exists()) {
            return back()->with('error', "Jadwal \"{$schedule->name}\" masih memiliki penugasan karyawan. Hapus penugasannya di tab Penugasan, atau nonaktifkan jadwal ini.");
        }

        $old = $schedule->only(self::TRACKED);
        $schedule->delete();

        AuditLog::record('delete', $schedule, $old);

        return back()->with('success', "Jadwal \"{$schedule->name}\" berhasil dihapus.");
    }

    /* ------------------------------------------------------------------ */

    private function timezones(): array
    {
        $zones = self::TIMEZONES;
        $company = Company::current()?->timezone;
        if ($company && !isset($zones[$company])) {
            $zones[$company] = $company;
        }

        return $zones;
    }

    private function validated(Request $request, ?WorkSchedule $schedule): array
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100',
                Rule::unique('work_schedules', 'name')->whereNull('deleted_at')->ignore($schedule?->id)],
            'check_in_time' => ['required', 'date_format:H:i'],
            'check_out_time' => ['required', 'date_format:H:i'],
            'late_tolerance_minutes' => ['required', 'integer', 'between:0,240'],
            'break_start' => ['nullable', 'date_format:H:i'],
            'break_end' => ['nullable', 'date_format:H:i'],
            'is_shift' => ['nullable', 'boolean'],
            'timezone' => ['required', Rule::in(array_keys($this->timezones()))],
            'is_active' => ['nullable', 'boolean'],
            'days' => ['required', 'array'],
            'days.*.is_working_day' => ['nullable', 'boolean'],
            'days.*.work_mode' => ['nullable', Rule::in(['WFO', 'WFH'])],
        ], [
            'name.unique' => 'Nama jadwal sudah dipakai jadwal lain.',
            'check_in_time.date_format' => 'Jam masuk harus berformat JJ:MM.',
            'check_out_time.date_format' => 'Jam pulang harus berformat JJ:MM.',
            'break_start.date_format' => 'Jam mulai istirahat harus berformat JJ:MM.',
            'break_end.date_format' => 'Jam selesai istirahat harus berformat JJ:MM.',
            'days.required' => 'Konfigurasi hari kerja wajib diisi.',
            'days.array' => 'Konfigurasi hari kerja tidak valid.',
            'days.*.work_mode.in' => 'Mode kerja per hari harus WFO atau WFH.',
        ], [
            'name' => 'nama jadwal',
            'check_in_time' => 'jam masuk',
            'check_out_time' => 'jam pulang',
            'late_tolerance_minutes' => 'toleransi keterlambatan',
            'break_start' => 'jam mulai istirahat',
            'break_end' => 'jam selesai istirahat',
            'timezone' => 'zona waktu',
        ]);

        $this->crossCheck($data, $request);

        return $data;
    }

    private function minutes(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', $time));

        return $h * 60 + $m;
    }

    /** Validasi antar-field yang tidak bisa dinyatakan dengan satu rule. */
    private function crossCheck(array $data, Request $request): void
    {
        $errors = [];
        $in = $this->minutes($data['check_in_time']);
        $out = $this->minutes($data['check_out_time']);

        if ($out <= $in) {
            $errors['check_out_time'] = 'Jam pulang harus setelah jam masuk. Jadwal yang melewati tengah malam belum didukung.';
        }

        $bs = $data['break_start'] ?? null;
        $be = $data['break_end'] ?? null;

        if (($bs === null || $bs === '') xor ($be === null || $be === '')) {
            $errors['break_end'] = 'Isi jam mulai dan selesai istirahat, atau kosongkan keduanya.';
        } elseif ($bs && $be) {
            $bsm = $this->minutes($bs);
            $bem = $this->minutes($be);
            if ($bem <= $bsm) {
                $errors['break_end'] = 'Jam selesai istirahat harus setelah jam mulai istirahat.';
            } elseif ($bsm < $in || $bem > $out) {
                $errors['break_start'] = 'Waktu istirahat harus berada di antara jam masuk dan jam pulang.';
            }
        }

        $working = collect(range(1, 7))
            ->filter(fn ($iso) => $request->boolean("days.$iso.is_working_day"))
            ->count();
        if ($working === 0) {
            $errors['days'] = 'Pilih minimal satu hari kerja.';
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function modelAttributes(array $data, Request $request): array
    {
        return [
            'name' => $data['name'],
            'check_in_time' => $data['check_in_time'].':00',
            'check_out_time' => $data['check_out_time'].':00',
            'late_tolerance_minutes' => (int) $data['late_tolerance_minutes'],
            'break_start' => !empty($data['break_start']) ? $data['break_start'].':00' : null,
            'break_end' => !empty($data['break_end']) ? $data['break_end'].':00' : null,
            'is_shift' => $request->boolean('is_shift'),
            'timezone' => $data['timezone'],
            'is_active' => $request->boolean('is_active'),
        ];
    }

    private function syncDays(WorkSchedule $schedule, Request $request): void
    {
        foreach (array_keys(self::DAYS) as $iso) {
            $mode = $request->input("days.$iso.work_mode");

            ScheduleDay::updateOrCreate(
                ['work_schedule_id' => $schedule->id, 'day_of_week' => $iso],
                [
                    'is_working_day' => $request->boolean("days.$iso.is_working_day"),
                    'work_mode' => in_array($mode, ['WFO', 'WFH'], true) ? $mode : 'WFO',
                ]
            );
        }
    }

    private function daysSnapshot(WorkSchedule $schedule): array
    {
        return $schedule->days()->orderBy('day_of_week')->get()
            ->mapWithKeys(fn (ScheduleDay $d) => [$d->day_of_week => $d->is_working_day ? $d->work_mode : 'libur'])
            ->all();
    }
}
