<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Division;
use App\Models\EducationLevel;
use App\Models\Employee;
use App\Models\EmployeeStatus;
use App\Models\Position;
use App\Models\User;
use App\Services\ScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EmployeeController extends Controller
{
    private const EMPLOYEE_FIELDS = [
        'employee_number', 'full_name', 'national_id', 'birth_place', 'birth_date', 'gender',
        'address', 'phone', 'email', 'position_id', 'department_id', 'division_id',
        'employee_status_id', 'education_level_id', 'supervisor_id', 'join_date', 'work_mode',
        'emergency_contact_name', 'emergency_contact_phone',
    ];

    /** Foto profil publik; KTP dan dokumen pendukung disimpan di disk private. */
    private const FILES = [
        'photo' => ['disk' => 'public', 'dir' => 'employees/photos'],
        'ktp_photo' => ['disk' => 'local', 'dir' => 'employees/ktp'],
        'supporting_document' => ['disk' => 'local', 'dir' => 'employees/documents'],
    ];

    public function __construct(private ScheduleService $schedules)
    {
    }

    public function index(Request $request): View
    {
        $employees = Employee::query()
            ->with(['position', 'department', 'employeeStatus'])
            ->search(trim((string) $request->query('q')))
            ->when($request->filled('position_id'), fn ($q) => $q->where('position_id', $request->integer('position_id')))
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->integer('department_id')))
            ->when(in_array($request->query('status'), ['active', 'inactive'], true),
                fn ($q) => $q->where('is_active', $request->query('status') === 'active'))
            ->orderBy('full_name')
            ->paginate(10)
            ->withQueryString();

        return view('employees.index', [
            'employees' => $employees,
            'positions' => Position::orderBy('name')->get(['id', 'name']),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): View
    {
        $next = (Employee::withTrashed()->max('id') ?? 0) + 1;

        return view('employees.form', [
            'employee' => null,
            'options' => $this->options(null),
            'suggestedNumber' => 'EMP-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, null);
        $stored = [];

        try {
            $employee = DB::transaction(function () use ($request, $data, &$stored) {
                $user = new User([
                    'name' => $data['full_name'],
                    'username' => $data['username'],
                    'email' => $data['email'],
                    'password' => $data['password'], // di-hash oleh cast 'hashed'
                ]);
                // role dan is_active sengaja tidak mass-assignable
                $user->forceFill([
                    'role' => User::ROLE_EMPLOYEE,
                    'is_active' => $request->boolean('is_active'),
                ])->save();

                $attrs = Arr::only($data, self::EMPLOYEE_FIELDS)
                    + $this->storeFiles($request, $stored)
                    + ['user_id' => $user->id, 'is_active' => $request->boolean('is_active')];

                return Employee::create($attrs);
            });
        } catch (\Throwable $e) {
            $this->deleteFiles($stored);
            throw $e;
        }

        AuditLog::record('create', $employee, null, $employee->only(['employee_number', 'full_name', 'email', 'position_id', 'department_id']));

        return redirect()->route('employees.show', $employee->id)
            ->with('success', "Karyawan {$employee->full_name} berhasil ditambahkan beserta akun loginnya.");
    }

    public function show(string $id): View
    {
        $employee = Employee::with([
            'user', 'position', 'department', 'division', 'employeeStatus', 'educationLevel', 'supervisor',
        ])->withCount('subordinates')->findOrFail($id);

        return view('employees.show', [
            'employee' => $employee,
            'resolved' => $this->schedules->resolveFor($employee, today()),
            'hasFace' => $employee->faceDescriptors()->where('status', 'active')->exists(),
            'recent' => $employee->attendanceRecords()->latest('attendance_date')->limit(5)->get(),
        ]);
    }

    public function edit(string $id): View
    {
        $employee = Employee::with('user')->findOrFail($id);

        return view('employees.form', [
            'employee' => $employee,
            'options' => $this->options($employee),
            'suggestedNumber' => $employee->employee_number,
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $employee = Employee::with('user')->findOrFail($id);
        $data = $this->validated($request, $employee);

        $old = $employee->only(['employee_number', 'full_name', 'email', 'position_id', 'department_id', 'work_mode', 'is_active']);
        $active = $request->boolean('is_active');
        $stored = [];
        $replaced = [];

        try {
            DB::transaction(function () use ($request, $data, $employee, $active, &$stored, &$replaced) {
                $newFiles = $this->storeFiles($request, $stored);
                foreach ($newFiles as $column => $path) {
                    if ($employee->{$column}) {
                        $replaced[] = [self::FILES[$column]['disk'], $employee->{$column}];
                    }
                }

                $employee->update(Arr::only($data, self::EMPLOYEE_FIELDS) + $newFiles + ['is_active' => $active]);

                $employee->user->forceFill([
                    'name' => $data['full_name'],
                    'username' => $data['username'],
                    'email' => $data['email'],
                    'is_active' => $active,
                ])->save();
            });
        } catch (\Throwable $e) {
            $this->deleteFiles($stored);
            throw $e;
        }

        $this->deleteFiles($replaced);

        if (!$active) {
            $this->revokeSessions($employee->user);
        }

        AuditLog::record('update', $employee, $old,
            $employee->only(['employee_number', 'full_name', 'email', 'position_id', 'department_id', 'work_mode', 'is_active']));

        return redirect()->route('employees.show', $employee->id)
            ->with('success', 'Data karyawan berhasil diperbarui.');
    }

    /** Hapus aman: soft delete karyawan dan akunnya. Riwayat absensi dan payroll tetap tersimpan. */
    public function destroy(string $id): RedirectResponse
    {
        $employee = Employee::with('user')->findOrFail($id);
        $name = $employee->full_name;
        $old = $employee->only(['employee_number', 'full_name', 'email']);

        DB::transaction(function () use ($employee) {
            // Bawahan yang atasannya dihapus dilepas dari hierarki
            Employee::where('supervisor_id', $employee->id)->update(['supervisor_id' => null]);

            $employee->update(['is_active' => false]);
            if ($employee->user) {
                $employee->user->forceFill(['is_active' => false])->save();
            }
            $employee->delete();
            $employee->user?->delete();
        });

        if ($employee->user) {
            $this->revokeSessions($employee->user);
        }

        AuditLog::record('delete', $employee, $old);

        return redirect()->route('employees.index')
            ->with('success', "Karyawan {$name} dihapus. Akun loginnya ikut dinonaktifkan dan riwayatnya tetap tersimpan.");
    }

    public function toggleStatus(string $id): RedirectResponse
    {
        $employee = Employee::with('user')->findOrFail($id);
        $active = !$employee->is_active;

        DB::transaction(function () use ($employee, $active) {
            $employee->update(['is_active' => $active]);
            $employee->user?->forceFill(['is_active' => $active])->save();
        });

        if (!$active && $employee->user) {
            $this->revokeSessions($employee->user);
        }

        AuditLog::record($active ? 'activate' : 'deactivate', $employee);

        return back()->with('success', "Karyawan {$employee->full_name} ".($active ? 'diaktifkan.' : 'dinonaktifkan dan tidak dapat login.'));
    }

    public function resetPassword(Request $request, string $id): RedirectResponse
    {
        $employee = Employee::with('user')->findOrFail($id);
        abort_if(!$employee->user, 404);

        $validator = Validator::make($request->all(), [
            'password' => ['required', 'string', 'min:8', 'max:100'],
        ]);

        if ($validator->fails()) {
            return back()->with('error', $validator->errors()->first());
        }

        $employee->user->forceFill(['password' => $request->input('password')])->save(); // di-hash oleh cast
        $this->revokeSessions($employee->user); // sesi lama harus login ulang

        AuditLog::record('reset_password', $employee);

        return back()->with('success', "Password {$employee->full_name} berhasil direset. Sampaikan password baru kepada karyawan.");
    }

    /** Dokumen sensitif disajikan lewat route ini, bukan URL publik. */
    public function file(string $id, string $type): BinaryFileResponse
    {
        $employee = Employee::findOrFail($id);
        $column = $type === 'ktp' ? 'ktp_photo' : 'supporting_document';
        $path = $employee->{$column};

        abort_if(!$path || !Storage::disk('local')->exists($path), 404);

        AuditLog::record('view_document', $employee, null, ['file' => $column]);

        return response()->file(Storage::disk('local')->path($path));
    }

    /* ------------------------------------------------------------------ */

    private function validated(Request $request, ?Employee $employee): array
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
            'username' => Str::lower(trim((string) $request->input('username'))),
            'employee_number' => strtoupper(trim((string) $request->input('employee_number'))),
        ]);

        return $request->validate($this->rules($request, $employee));
    }

    private function rules(Request $request, ?Employee $employee): array
    {
        $userId = $employee?->user_id;

        return [
            'employee_number' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9._\/-]+$/', Rule::unique('employees', 'employee_number')->ignore($employee?->id)],
            'full_name' => ['required', 'string', 'max:255'],
            'national_id' => ['nullable', 'digits:16', Rule::unique('employees', 'national_id')->ignore($employee?->id)],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(['L', 'P'])],
            'address' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'regex:/^[0-9+\-\s()]{6,30}$/'],

            'position_id' => ['required', Rule::exists('positions', 'id')->whereNull('deleted_at')],
            'department_id' => ['required', Rule::exists('departments', 'id')->whereNull('deleted_at')],
            'division_id' => [
                'nullable',
                Rule::exists('divisions', 'id')->whereNull('deleted_at'),
                function (string $attribute, mixed $value, \Closure $fail) use ($request) {
                    $matches = Division::where('id', $value)
                        ->where('department_id', $request->input('department_id'))
                        ->exists();
                    if (!$matches) {
                        $fail('Divisi tidak termasuk dalam departemen yang dipilih.');
                    }
                },
            ],
            'employee_status_id' => ['required', 'integer', 'exists:employee_statuses,id'],
            'education_level_id' => ['nullable', 'integer', 'exists:education_levels,id'],
            'supervisor_id' => [
                'nullable',
                Rule::exists('employees', 'id')->whereNull('deleted_at'),
                function (string $attribute, mixed $value, \Closure $fail) use ($employee) {
                    if (!$employee) {
                        return;
                    }
                    // Cegah hierarki melingkar (termasuk menjadi atasan diri sendiri)
                    $cursor = (int) $value;
                    for ($i = 0; $cursor && $i < 50; $i++) {
                        if ($cursor === $employee->id) {
                            $fail('Atasan langsung tidak valid karena membentuk hierarki melingkar.');

                            return;
                        }
                        $cursor = (int) Employee::withTrashed()->whereKey($cursor)->value('supervisor_id');
                    }
                },
            ],
            'join_date' => ['required', 'date'],
            'work_mode' => ['required', Rule::in([Employee::MODE_WFO, Employee::MODE_WFH, Employee::MODE_HYBRID])],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'regex:/^[0-9+\-\s()]{6,30}$/'],

            'email' => ['required', 'email', 'max:255',
                Rule::unique('employees', 'email')->ignore($employee?->id),
                Rule::unique('users', 'email')->ignore($userId)],
            'username' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($userId)],
            'password' => $employee ? ['nullable'] : ['required', 'string', 'min:8', 'max:100'],
            'is_active' => ['nullable', 'boolean'],

            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'ktp_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'supporting_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    /** Simpan berkas yang diunggah. Mengembalikan [kolom => path] dan mencatat path baru ke $stored. */
    private function storeFiles(Request $request, array &$stored): array
    {
        $paths = [];

        foreach (self::FILES as $key => $target) {
            if (!$request->hasFile($key)) {
                continue;
            }
            $path = $request->file($key)->store($target['dir'], $target['disk']);
            $stored[] = [$target['disk'], $path];
            $paths[$key] = $path;
        }

        return $paths;
    }

    /** @param array<int, array{0:string,1:string}> $files */
    private function deleteFiles(array $files): void
    {
        foreach ($files as [$disk, $path]) {
            Storage::disk($disk)->delete($path);
        }
    }

    /** Akhiri semua sesi login pengguna (akun dinonaktifkan atau password direset). */
    private function revokeSessions(User $user): void
    {
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
        $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();
    }

    private function options(?Employee $employee): array
    {
        // Data nonaktif tidak ditawarkan, kecuali yang sedang dipakai karyawan ini.
        $keep = fn (?int $id) => fn ($q) => $q->where('is_active', true)->when($id, fn ($q) => $q->orWhere('id', $id));

        return [
            'positions' => Position::where($keep($employee?->position_id))->orderBy('name')->get(['id', 'name']),
            'departments' => Department::where($keep($employee?->department_id))->orderBy('name')->get(['id', 'name']),
            'divisions' => Division::where($keep($employee?->division_id))->orderBy('name')->get(['id', 'name', 'department_id']),
            'statuses' => EmployeeStatus::where($keep($employee?->employee_status_id))->orderBy('id')->get(['id', 'name']),
            'education' => EducationLevel::orderBy('id')->get(['id', 'name']),
            'supervisors' => Employee::active()->orderBy('full_name')->get(['id', 'full_name', 'employee_number']),
        ];
    }
}
