<?php

namespace Tests;

use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeFaceDescriptor;
use App\Models\EmployeeSchedule;
use App\Models\FeatureSetting;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Models\WorkSchedule;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Carbon;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /** Dipanggil sebelum migrate:fresh: tolak database yang bukan database uji. */
    protected function beforeRefreshingDatabase()
    {
        $connection = config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        if ($connection !== 'mysql' || !str_ends_with($database, '_test')) {
            throw new RuntimeException(
                "Tes dihentikan: database \"{$database}\" ({$connection}) bukan database uji. "
                .'Gunakan MySQL dengan nama berakhiran _test (lihat phpunit.xml).'
            );
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Rabu, 18 November 2026, 09:00 WITA (hari kerja)
        $this->travelTo(Carbon::parse('2026-11-18 09:00:00', 'Asia/Makassar'));

        $this->seed(DatabaseSeeder::class);
        FeatureSetting::flush();
    }

    /* ---------------------------- akun ---------------------------- */

    protected function superAdmin(): User
    {
        return User::where('username', 'superadmin')->firstOrFail();
    }

    protected function admin(): User
    {
        return User::where('username', 'admin')->firstOrFail();
    }

    protected function employeeUser(): User
    {
        return User::where('username', 'employee')->firstOrFail();
    }

    protected function employee(): Employee
    {
        return Employee::where('employee_number', 'EMP-0001')->firstOrFail();
    }

    protected function makeEmployee(string $key = 'b', bool $withSchedule = true, array $attrs = []): Employee
    {
        $base = $this->employee();

        $user = new User([
            'name' => "Karyawan {$key}",
            'username' => "emp_{$key}",
            'email' => "emp_{$key}@hellobroseven.test",
            'password' => 'password123',
        ]);
        $user->forceFill(['role' => User::ROLE_EMPLOYEE, 'is_active' => true])->save();

        $employee = Employee::create(array_merge([
            'user_id' => $user->id,
            'employee_number' => 'EMP-T-'.strtoupper($key),
            'full_name' => "Karyawan {$key}",
            'email' => $user->email,
            'position_id' => $base->position_id,
            'department_id' => $base->department_id,
            'employee_status_id' => $base->employee_status_id,
            'join_date' => '2026-01-01',
            'is_active' => true,
            'work_mode' => 'WFO',
        ], $attrs));

        if ($withSchedule) {
            EmployeeSchedule::create([
                'employee_id' => $employee->id,
                'work_schedule_id' => WorkSchedule::firstOrFail()->id,
                'start_date' => '2026-01-01',
            ]);
        }

        return $employee;
    }

    /* ---------------------------- wajah ---------------------------- */

    /** Vektor 128 angka deterministik. Orang berbeda = jarak jauh; jitter kecil = orang yang sama. */
    protected function faceVector(int $person = 1, float $jitter = 0.0): array
    {
        $v = [];
        for ($i = 0; $i < 128; $i++) {
            $v[] = round(0.2 * sin($i * 0.37 + $person * 1.9) + $jitter * cos($i * 1.3), 4);
        }

        return $v;
    }

    protected function faceSamples(): array
    {
        return array_map(fn (float $j) => $this->faceVector(1, $j), [0.0, 0.005, -0.005, 0.01, -0.01]);
    }

    protected function registerFace(Employee $employee, string $status = 'active'): EmployeeFaceDescriptor
    {
        return EmployeeFaceDescriptor::create([
            'employee_id' => $employee->id,
            'descriptor' => $this->faceSamples(),
            'status' => $status,
            'consent_at' => now(),
            'registered_at' => now(),
        ]);
    }

    /* ---------------------------- lokasi ---------------------------- */

    protected function officeLat(): float
    {
        return (float) Company::current()->latitude;
    }

    protected function officePayload(array $override = []): array
    {
        $company = Company::current();

        return array_merge([
            'latitude' => (float) $company->latitude,
            'longitude' => (float) $company->longitude,
            'accuracy' => 10,
            // microtime nyata: waktu beku Carbon tidak memengaruhi fungsi ini, sama seperti di server
            'captured_at' => (int) round(microtime(true) * 1000),
        ], $override);
    }

    /* ---------------------------- data Oktober 2026 ---------------------------- */

    /**
     * 22 hari kerja: 16 hadir, 3 terlambat, 2 tidak hadir, 1 sakit (disetujui).
     * Dengan gaji Rp100.000, Terlambat 10%, Tidak Hadir 100%, Sakit 50%:
     * kotor 2.200.000, potongan 280.000, bersih 1.920.000.
     *
     * @return array{days:int,present:int,late:int,absent:int,sick:int}
     */
    protected function seedOctoberAttendance(?Employee $employee = null): array
    {
        $employee ??= $this->employee();
        $schedule = WorkSchedule::firstOrFail();
        $sick = LeaveType::where('category', 'sick')->firstOrFail();
        $exp = ['days' => 0, 'present' => 0, 'late' => 0, 'absent' => 0, 'sick' => 0];
        $i = 0;

        for ($d = Carbon::parse('2026-10-01'); $d->lte(Carbon::parse('2026-10-31')); $d->addDay()) {
            if ($d->isWeekend()) {
                continue;
            }

            $i++;
            $date = $d->toDateString();
            $attrs = [
                'employee_id' => $employee->id,
                'work_schedule_id' => $schedule->id,
                'attendance_date' => $date,
                'work_mode' => 'WFO',
                'verification_method' => 'manual',
            ];

            if ($i === 3) {
                $leave = LeaveRequest::create([
                    'employee_id' => $employee->id, 'leave_type_id' => $sick->id,
                    'start_date' => $date, 'end_date' => $date, 'total_days' => 1,
                    'reason' => 'Sakit (data uji)', 'status' => 'approved',
                ]);
                $attrs += ['status' => 'leave', 'leave_request_id' => $leave->id];
                $exp['sick']++;
            } elseif ($i % 7 === 0) {
                $attrs += ['status' => 'late', 'late_minutes' => 40];
                $exp['late']++;
            } elseif ($i % 11 === 0) {
                $attrs += ['status' => 'absent'];
                $exp['absent']++;
            } else {
                $attrs += ['status' => 'present'];
                $exp['present']++;
            }

            AttendanceRecord::create($attrs);
            $exp['days']++;
        }

        return $exp;
    }
}
