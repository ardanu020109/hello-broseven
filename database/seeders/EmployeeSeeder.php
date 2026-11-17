<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Division;
use App\Models\EducationLevel;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\EmployeeStatus;
use App\Models\Position;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrNew(['email' => 'employee@hellobroseven.test']);
        $user->forceFill([
            'name' => 'Karyawan Uji',
            'username' => 'employee',
            'password' => 'password123',
            'role' => User::ROLE_EMPLOYEE,
            'is_active' => true,
            'email_verified_at' => now(),
        ])->save();

        $department = Department::where('code', 'OPS')->firstOrFail();

        $employee = Employee::updateOrCreate(
            ['user_id' => $user->id],
            [
                'employee_number' => 'EMP-0001',
                'full_name' => 'Karyawan Uji',
                'email' => 'employee@hellobroseven.test',
                'phone' => '081200000001',
                'gender' => 'L',
                'position_id' => Position::where('code', 'STF')->firstOrFail()->id,
                'department_id' => $department->id,
                'division_id' => Division::where('department_id', $department->id)->first()?->id,
                'employee_status_id' => EmployeeStatus::where('name', 'Tetap')->firstOrFail()->id,
                'education_level_id' => EducationLevel::where('name', 'S1')->first()?->id,
                'join_date' => '2026-01-01',
                'is_active' => true,
                'work_mode' => Employee::MODE_WFO,
            ]
        );

        EmployeeSchedule::firstOrCreate(
            ['employee_id' => $employee->id, 'work_schedule_id' => WorkSchedule::firstOrFail()->id],
            ['start_date' => '2026-01-01', 'end_date' => null]
        );
    }
}
