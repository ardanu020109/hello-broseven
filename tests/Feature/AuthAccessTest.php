<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Models\WorkSchedule;
use Tests\TestCase;

class AuthAccessTest extends TestCase
{
    public function test_each_role_logs_in_and_lands_on_its_dashboard(): void
    {
        $cases = [
            ['superadmin@hellobroseven.test', 'superadmin.dashboard'],
            ['admin', 'admin.dashboard'], // login memakai username
            ['employee@hellobroseven.test', 'employee.dashboard'],
        ];

        foreach ($cases as [$login, $route]) {
            $this->post('/login', ['login' => $login, 'password' => 'password123'])->assertRedirect(route($route));
            $this->assertAuthenticated();

            $this->post('/logout')->assertRedirect(route('login'));
            $this->assertGuest();
        }
    }

    public function test_wrong_password_is_rejected_with_generic_message(): void
    {
        $this->post('/login', ['login' => 'admin', 'password' => 'salah-sekali'])->assertSessionHasErrors('login');
        $this->assertGuest();

        // Pesan sama persis untuk akun yang tidak ada: tidak membocorkan keberadaan akun
        $this->post('/login', ['login' => 'tidak-ada', 'password' => 'salah-sekali'])
            ->assertSessionHasErrors(['login' => 'Email/username atau password salah, atau akun tidak aktif.']);
    }

    public function test_passwords_are_hashed(): void
    {
        $user = $this->admin();

        $this->assertNotSame('password123', $user->password);
        $this->assertStringStartsWith('$2y$', $user->password);
    }

    public function test_inactive_account_cannot_login(): void
    {
        $this->admin()->forceFill(['is_active' => false])->save();

        $this->post('/login', ['login' => 'admin', 'password' => 'password123'])->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_account_deactivated_mid_session_is_logged_out(): void
    {
        $user = $this->employeeUser();
        $this->actingAs($user);
        $user->forceFill(['is_active' => false])->save();

        $this->get('/employee/dashboard')->assertRedirect(route('login'));
    }

    public function test_login_is_rate_limited_per_login_and_ip(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['login' => 'admin', 'password' => 'salah'])->assertSessionHasErrors('login');
        }

        // Percobaan ke-6 diblokir walau password benar
        $this->post('/login', ['login' => 'admin', 'password' => 'password123'])->assertSessionHasErrors('login');
        $this->assertStringContainsString('Terlalu banyak', session('errors')->first('login'));
        $this->assertGuest();
    }

    public function test_rotating_usernames_does_not_bypass_ip_limit(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->post('/login', ['login' => "orang{$i}", 'password' => 'salah']);
        }

        $this->post('/login', ['login' => 'superadmin', 'password' => 'password123'])->assertSessionHasErrors('login');
        $this->assertStringContainsString('Terlalu banyak', session('errors')->first('login'));
        $this->assertGuest();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        foreach (['/dashboard', '/employees', '/payroll', '/employee/attendance', '/profile', '/employees/1/files/ktp'] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    public function test_role_boundaries_return_403(): void
    {
        $matrix = [
            'employee' => ['/super-admin/dashboard', '/admin/dashboard', '/employees', '/positions', '/schedules', '/attendance',
                '/leave-requests', '/reports/attendance', '/payroll', '/company', '/face-data', '/feature-settings', '/audit-logs'],
            'admin' => ['/super-admin/dashboard', '/employee/dashboard', '/employee/attendance', '/feature-settings', '/audit-logs'],
            'superadmin' => ['/admin/dashboard', '/employee/dashboard', '/employee/history'],
        ];

        foreach ($matrix as $username => $urls) {
            $user = User::where('username', $username)->firstOrFail();

            foreach ($urls as $url) {
                $this->actingAs($user)->get($url)->assertForbidden();
            }
        }
    }

    public function test_employee_cannot_see_or_touch_another_employees_data(): void
    {
        $a = $this->employee();
        $b = $this->makeEmployee('b');
        $schedule = WorkSchedule::firstOrFail();

        foreach ([$a, $b] as $emp) {
            AttendanceRecord::create([
                'employee_id' => $emp->id, 'work_schedule_id' => $schedule->id, 'attendance_date' => '2026-11-17',
                'status' => 'present', 'work_mode' => 'WFO', 'verification_method' => 'manual',
            ]);
        }

        $leaveB = LeaveRequest::create([
            'employee_id' => $b->id, 'leave_type_id' => LeaveType::firstOrFail()->id,
            'start_date' => '2026-11-19', 'end_date' => '2026-11-19', 'total_days' => 1, 'reason' => 'Milik B', 'status' => 'pending',
        ]);

        $this->actingAs($this->employeeUser());

        $records = $this->get(route('employee.history', ['month' => '2026-11']))->assertOk()->viewData('records');
        $this->assertSame([$a->id], $records->getCollection()->pluck('employee_id')->unique()->values()->all());

        $this->get(route('employee.leave-requests.attachment', $leaveB->id))->assertNotFound();
        $this->delete(route('employee.leave-requests.destroy', $leaveB->id))->assertNotFound();
        $this->assertDatabaseHas('leave_requests', ['id' => $leaveB->id]);
    }
}
