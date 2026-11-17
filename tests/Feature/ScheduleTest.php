<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\EmployeeSchedule;
use App\Models\Holiday;
use App\Models\WorkSchedule;
use App\Services\ScheduleService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    private function payload(array $o = []): array
    {
        $days = [];
        foreach (range(1, 7) as $iso) {
            $days[$iso] = ['is_working_day' => $iso <= 5 ? '1' : '0', 'work_mode' => 'WFO'];
        }

        return array_merge([
            'name' => 'Shift Siang', 'check_in_time' => '13:00', 'check_out_time' => '21:00', 'late_tolerance_minutes' => 10,
            'break_start' => '17:00', 'break_end' => '18:00', 'is_shift' => '1', 'timezone' => 'Asia/Makassar', 'is_active' => '1',
            'days' => $days,
        ], $o);
    }

    private function create(array $o = []): WorkSchedule
    {
        $this->actingAs($this->admin())->post(route('schedules.store'), $this->payload($o))->assertSessionHasNoErrors();

        return WorkSchedule::where('name', $o['name'] ?? 'Shift Siang')->firstOrFail();
    }

    public function test_schedule_crud(): void
    {
        $schedule = $this->create();

        $this->assertTrue($schedule->is_shift);
        $this->assertSame(7, $schedule->days()->count());
        $this->assertSame(5, $schedule->days()->where('is_working_day', true)->count());

        $this->put(route('schedules.update', $schedule->id), $this->payload(['name' => 'Shift Sore']))->assertSessionHasNoErrors();
        $this->assertSame('Shift Sore', $schedule->fresh()->name);

        $this->patch(route('schedules.toggle', $schedule->id));
        $this->assertFalse($schedule->fresh()->is_active);

        $this->delete(route('schedules.destroy', $schedule->id));
        $this->assertSoftDeleted($schedule);
    }

    public function test_schedule_validation(): void
    {
        $this->actingAs($this->admin());
        $off = array_fill(1, 7, ['is_working_day' => '0', 'work_mode' => 'WFO']);

        $this->post(route('schedules.store'), $this->payload(['check_out_time' => '12:00']))->assertSessionHasErrors('check_out_time');
        $this->post(route('schedules.store'), $this->payload(['break_start' => '06:00', 'break_end' => '07:00']))->assertSessionHasErrors('break_start');
        $this->post(route('schedules.store'), $this->payload(['break_end' => null]))->assertSessionHasErrors('break_end');
        $this->post(route('schedules.store'), $this->payload(['days' => $off]))->assertSessionHasErrors('days');
        $this->post(route('schedules.store'), $this->payload(['name' => 'Jadwal Reguler Senin-Jumat']))->assertSessionHasErrors('name');
        $this->post(route('schedules.store'), $this->payload(['late_tolerance_minutes' => 999]))->assertSessionHasErrors('late_tolerance_minutes');
    }

    public function test_schedule_in_use_cannot_be_deleted(): void
    {
        $regular = WorkSchedule::where('name', 'Jadwal Reguler Senin-Jumat')->firstOrFail();

        $this->actingAs($this->admin())->delete(route('schedules.destroy', $regular->id))->assertSessionHas('error');
        $this->assertNotSoftDeleted($regular);
    }

    public function test_assigning_trims_the_previous_assignment(): void
    {
        $b = $this->makeEmployee('b');
        $new = $this->create();

        $this->post(route('schedules.assignments.store'), [
            'target' => 'employees', 'work_schedule_id' => $new->id, 'employee_ids' => [$b->id], 'start_date' => '2026-11-18',
        ])->assertSessionHasNoErrors();

        $rows = EmployeeSchedule::where('employee_id', $b->id)->orderBy('start_date')->get();
        $this->assertCount(2, $rows);
        $this->assertSame('2026-11-17', $rows[0]->end_date->toDateString());
        $this->assertNull($rows[1]->end_date);
        $this->assertSame($new->id, $rows[1]->work_schedule_id);

        $resolved = app(ScheduleService::class)->resolveFor($b, Carbon::parse('2026-11-18'));
        $this->assertSame('Shift Siang', $resolved['schedule']->name);
    }

    public function test_assign_by_department_and_to_unscheduled_only(): void
    {
        $b = $this->makeEmployee('b');
        $c = $this->makeEmployee('c', false);
        $new = $this->create();
        $this->actingAs($this->admin());

        $this->post(route('schedules.assignments.store'), [
            'target' => 'unscheduled', 'work_schedule_id' => $new->id, 'start_date' => '2026-11-18',
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, EmployeeSchedule::where('work_schedule_id', $new->id)->count());
        $this->assertTrue(EmployeeSchedule::where('work_schedule_id', $new->id)->where('employee_id', $c->id)->exists());

        $this->post(route('schedules.assignments.store'), [
            'target' => 'department', 'work_schedule_id' => $new->id, 'department_id' => $this->employee()->department_id, 'start_date' => '2026-11-18',
        ])->assertSessionHasNoErrors();

        foreach ([$this->employee(), $b, $c] as $emp) {
            $this->assertSame('Shift Siang', app(ScheduleService::class)->resolveFor($emp, Carbon::parse('2026-11-18'))['schedule']->name);
        }
    }

    public function test_assignment_is_refused_when_attendance_exists_in_range(): void
    {
        $b = $this->makeEmployee('b');
        $new = $this->create();
        AttendanceRecord::create([
            'employee_id' => $b->id, 'work_schedule_id' => WorkSchedule::where('name', 'Jadwal Reguler Senin-Jumat')->value('id'),
            'attendance_date' => '2026-11-18', 'status' => 'present', 'work_mode' => 'WFO', 'verification_method' => 'manual',
        ]);

        $this->post(route('schedules.assignments.store'), [
            'target' => 'employees', 'work_schedule_id' => $new->id, 'employee_ids' => [$b->id], 'start_date' => '2026-11-18',
        ])->assertSessionHasErrors('start_date');

        $this->post(route('schedules.assignments.store'), [
            'target' => 'employees', 'work_schedule_id' => $new->id, 'employee_ids' => [$b->id], 'start_date' => '2026-11-19',
        ])->assertSessionHasNoErrors();
    }

    public function test_inactive_schedule_cannot_be_assigned(): void
    {
        $new = $this->create(['is_active' => '0']);

        $this->post(route('schedules.assignments.store'), [
            'target' => 'employees', 'work_schedule_id' => $new->id, 'employee_ids' => [$this->employee()->id], 'start_date' => '2026-11-18',
        ])->assertSessionHasErrors('work_schedule_id');
    }

    public function test_end_assignment_and_holidays(): void
    {
        $this->actingAs($this->admin());
        $assignment = EmployeeSchedule::firstOrFail();

        $this->patch(route('schedules.assignments.end', $assignment->id));
        $this->assertSame('2026-11-18', $assignment->fresh()->end_date->toDateString());

        $this->post(route('schedules.holidays.store'), ['holiday_date' => '2026-11-18', 'name' => 'Libur Uji', 'is_national' => '1'])
            ->assertSessionHasNoErrors();
        $this->post(route('schedules.holidays.store'), ['holiday_date' => '2026-11-18', 'name' => 'Duplikat'])->assertSessionHasErrors('holiday_date');
        $this->assertNotNull(app(ScheduleService::class)->holidayOn(Carbon::parse('2026-11-18')));

        $this->delete(route('schedules.holidays.destroy', Holiday::firstOrFail()->id));
        $this->assertSame(0, Holiday::count());
    }
}
