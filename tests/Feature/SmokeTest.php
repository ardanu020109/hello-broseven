<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\AttendanceRecord;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Models\WorkSchedule;
use Tests\TestCase;

class SmokeTest extends TestCase
{
    private function seedRows(): array
    {
        $e = $this->employee();

        $record = AttendanceRecord::create([
            'employee_id' => $e->id, 'work_schedule_id' => WorkSchedule::firstOrFail()->id,
            'attendance_date' => '2026-11-17', 'check_in_at' => '2026-11-17 08:00:00', 'check_out_at' => '2026-11-17 17:00:00',
            'status' => 'present', 'work_mode' => 'WFO', 'verification_method' => 'face_gps',
        ]);
        $record->locations()->create([
            'type' => 'check_in', 'latitude' => -5.147654, 'longitude' => 119.432731,
            'accuracy_meters' => 10, 'distance_meters' => 3, 'is_within_radius' => true, 'captured_at' => '2026-11-17 08:00:00',
        ]);

        $leave = LeaveRequest::create([
            'employee_id' => $e->id, 'leave_type_id' => LeaveType::firstOrFail()->id,
            'start_date' => '2026-11-19', 'end_date' => '2026-11-19', 'total_days' => 1, 'reason' => 'Uji tampilan', 'status' => 'pending',
        ]);

        foreach ([$this->admin(), $this->employeeUser()] as $user) {
            AppNotification::create(['user_id' => $user->id, 'type' => 'leave', 'title' => 'Uji', 'message' => 'Pesan uji', 'url' => '/dashboard']);
        }

        return [$record, $leave];
    }

    private function visit(User $user, array $urls): void
    {
        $this->withoutExceptionHandling();
        $this->actingAs($user);

        foreach ($urls as $url) {
            try {
                $status = $this->get($url)->getStatusCode();
            } catch (\Throwable $e) {
                $this->fail("Gagal membuka {$url}: ".$e->getMessage());
            }

            $this->assertSame(200, $status, "Status {$url}");
        }
    }

    private function adminUrls(): array
    {
        [$record, $leave] = $this->seedRows();
        $emp = $this->employee();

        return [
            route('positions.index'), route('departments.index'), route('divisions.index'), route('education-levels.index'),
            route('employee-statuses.index'), route('leave-types.index'), route('deduction-rules.index'),
            route('employees.index'), route('employees.create'), route('employees.show', $emp->id), route('employees.edit', $emp->id),
            route('faces.index'),
            route('schedules.index'), route('schedules.create'), route('schedules.edit', WorkSchedule::firstOrFail()->id),
            route('schedules.assignments.index'), route('schedules.holidays.index'),
            route('schedules.calendar'), route('schedules.calendar', ['view' => 'week']), route('schedules.calendar', ['view' => 'day']),
            route('schedules.calendar', ['employee_id' => $emp->id]), route('schedules.calendar', ['view' => 'week', 'employee_id' => $emp->id]),
            route('attendance.index'), route('attendance.show', $record->id),
            route('leave-requests.index'), route('leave-requests.show', $leave->id),
            route('reports.attendance'), route('reports.attendance', ['mode' => 'year', 'year' => 2026]),
            route('reports.attendance', ['mode' => 'range', 'from' => '2026-11-01', 'to' => '2026-11-30']),
            route('reports.attendance.print'), route('reports.attendance.detail', $emp->id),
            route('payroll.index'), route('company.edit'), route('profile.show'), route('notifications.index'),
        ];
    }

    public function test_admin_pages_render(): void
    {
        $this->visit($this->admin(), array_merge([route('admin.dashboard')], $this->adminUrls()));
    }

    public function test_super_admin_pages_render(): void
    {
        $urls = array_merge(
            [route('superadmin.dashboard'), route('feature-settings.index'), route('audit-logs.index')],
            $this->adminUrls()
        );

        $this->visit($this->superAdmin(), $urls);
    }

    public function test_employee_pages_render(): void
    {
        $this->seedRows();
        $this->registerFace($this->employee());

        $this->visit($this->employeeUser(), [
            route('employee.dashboard'), route('employee.attendance'), route('employee.history'),
            route('employee.leave-requests.index'), route('employee.schedule'), route('employee.face'),
            route('profile.show'), route('notifications.index'),
        ]);
    }

    public function test_landing_and_login_render_for_guests(): void
    {
        $this->get('/')->assertOk()->assertSee('Hello BroSeven!');
        $this->get('/login')->assertOk();
    }
}
