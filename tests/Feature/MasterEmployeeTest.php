<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
use App\Models\EmployeeFaceDescriptor;
use App\Models\Position;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MasterEmployeeTest extends TestCase
{
    /* ---------------- Jabatan ---------------- */

    private function positionPayload(array $o = []): array
    {
        return array_merge(['code' => 'adm', 'name' => 'Admin Kantor', 'daily_salary' => 120000, 'description' => 'Uji', 'is_active' => '1'], $o);
    }

    public function test_position_crud(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('positions.store'), $this->positionPayload())->assertRedirect(route('positions.index'));
        $position = Position::where('code', 'ADM')->firstOrFail(); // kode otomatis huruf besar
        $this->assertSame('120000.00', (string) $position->daily_salary);

        $this->put(route('positions.update', $position->id), $this->positionPayload(['name' => 'Admin Senior', 'daily_salary' => 130000]));
        $this->assertSame('Admin Senior', $position->fresh()->name);

        $this->delete(route('positions.destroy', $position->id));
        $this->assertSoftDeleted($position);
    }

    public function test_position_validation_and_usage_protection(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('positions.store'), $this->positionPayload(['code' => 'SPV']))->assertSessionHasErrors('code');
        $this->post(route('positions.store'), $this->positionPayload(['daily_salary' => -5]))->assertSessionHasErrors('daily_salary');
        $this->post(route('positions.store'), $this->positionPayload(['code' => 'a b!']))->assertSessionHasErrors('code');

        $staff = Position::where('code', 'STF')->firstOrFail();
        $this->delete(route('positions.destroy', $staff->id))->assertSessionHas('error');
        $this->assertNotSoftDeleted($staff);
    }

    /* ---------------- Departemen dan divisi ---------------- */

    public function test_department_crud_and_protection(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('departments.store'), ['code' => 'fin', 'name' => 'Keuangan', 'is_active' => '1'])
            ->assertRedirect(route('departments.index'));
        $dept = Department::where('code', 'FIN')->firstOrFail();

        $this->post(route('departments.store'), ['code' => 'FIN', 'name' => 'Lain', 'is_active' => '1'])->assertSessionHasErrors('code');

        $this->delete(route('departments.destroy', $dept->id));
        $this->assertSoftDeleted($dept);

        $ops = Department::where('code', 'OPS')->firstOrFail();        // dipakai karyawan
        $hrd = Department::where('code', 'HRD')->firstOrFail();        // punya divisi
        $this->delete(route('departments.destroy', $ops->id))->assertSessionHas('error');
        $this->delete(route('departments.destroy', $hrd->id))->assertSessionHas('error');
        $this->assertNotSoftDeleted($ops);
        $this->assertNotSoftDeleted($hrd);
    }

    public function test_division_name_unique_per_department(): void
    {
        $this->actingAs($this->admin());
        $hrd = Department::where('code', 'HRD')->firstOrFail();
        $ops = Department::where('code', 'OPS')->firstOrFail();

        $this->post(route('divisions.store'), ['department_id' => $hrd->id, 'name' => 'Rekrutmen & Administrasi', 'is_active' => '1'])
            ->assertSessionHasErrors('name');

        $this->post(route('divisions.store'), ['department_id' => $ops->id, 'name' => 'Rekrutmen & Administrasi', 'is_active' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertSame(2, Division::where('name', 'Rekrutmen & Administrasi')->count());
    }

    /* ---------------- Karyawan ---------------- */

    private function employeePayload(array $o = []): array
    {
        $base = $this->employee();

        return array_merge([
            'employee_number' => 'EMP-1001', 'full_name' => 'Budi Santoso', 'email' => 'budi@hellobroseven.test', 'username' => 'budi',
            'password' => 'rahasia123', 'position_id' => $base->position_id, 'department_id' => $base->department_id,
            'employee_status_id' => $base->employee_status_id, 'join_date' => '2026-02-01', 'work_mode' => 'WFO', 'is_active' => '1',
        ], $o);
    }

    public function test_creating_employee_creates_hashed_employee_account(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('employees.store'), $this->employeePayload())->assertRedirect();

        $employee = Employee::where('employee_number', 'EMP-1001')->with('user')->firstOrFail();
        $this->assertSame('employee', $employee->user->role);
        $this->assertTrue(Hash::check('rahasia123', $employee->user->password));
        $this->assertTrue($employee->user->is_active);

        // Akun baru bisa login ke dashboard karyawan
        $this->post('/logout');
        $this->post('/login', ['login' => 'budi', 'password' => 'rahasia123'])->assertRedirect(route('employee.dashboard'));
    }

    public function test_employee_form_validation(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('employees.store'), $this->employeePayload(['email' => 'employee@hellobroseven.test']))->assertSessionHasErrors('email');
        $this->post(route('employees.store'), $this->employeePayload(['national_id' => '12345']))->assertSessionHasErrors('national_id');
        $this->post(route('employees.store'), $this->employeePayload(['password' => 'pendek']))->assertSessionHasErrors('password');
        $this->post(route('employees.store'), $this->employeePayload(['employee_number' => 'EMP-0001']))->assertSessionHasErrors('employee_number');
        $this->post(route('employees.store'), $this->employeePayload(['work_mode' => 'REMOTE']))->assertSessionHasErrors('work_mode');

        $this->assertSame(1, Employee::count());
    }

    public function test_update_status_toggle_and_reset_password(): void
    {
        $this->actingAs($this->admin());
        $employee = $this->employee();

        $payload = $this->employeePayload([
            'employee_number' => $employee->employee_number, 'email' => $employee->email, 'username' => 'employee',
            'full_name' => 'Nama Baru', 'password' => '',
        ]);
        $this->put(route('employees.update', $employee->id), $payload)->assertRedirect();
        $this->assertSame('Nama Baru', $employee->fresh()->full_name);
        $this->assertSame('Nama Baru', $employee->user->fresh()->name); // akun ikut tersinkron

        $this->patch(route('employees.toggle-status', $employee->id));
        $this->assertFalse($employee->fresh()->is_active);
        $this->assertFalse($employee->user->fresh()->is_active);

        $this->post(route('employees.reset-password', $employee->id), ['password' => 'pendek'])->assertSessionHas('error');
        $this->post(route('employees.reset-password', $employee->id), ['password' => 'BaruBaru123'])->assertSessionHas('success');
        $this->assertTrue(Hash::check('BaruBaru123', $employee->user->fresh()->password));
    }

    public function test_deleting_employee_is_soft_and_removes_biometric_data(): void
    {
        $b = $this->makeEmployee('b');
        $this->registerFace($b);
        $this->assertSame(1, EmployeeFaceDescriptor::where('employee_id', $b->id)->count());

        $this->actingAs($this->admin())->delete(route('employees.destroy', $b->id))->assertRedirect(route('employees.index'));

        $this->assertSoftDeleted($b);
        $this->assertSoftDeleted('users', ['id' => $b->user_id]);
        $this->assertSame(0, EmployeeFaceDescriptor::where('employee_id', $b->id)->count());
    }
}
