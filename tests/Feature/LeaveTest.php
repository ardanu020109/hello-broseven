<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\WorkSchedule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LeaveTest extends TestCase
{
    private function submit(array $o = [])
    {
        $type = LeaveType::where('name', 'Izin Pribadi')->firstOrFail();

        return $this->actingAs($this->employeeUser())->post(route('employee.leave-requests.store'), array_merge([
            'leave_type_id' => $type->id, 'start_date' => '2026-11-18', 'end_date' => '2026-11-18', 'reason' => 'Urusan keluarga mendesak',
        ], $o));
    }

    private function pending(): LeaveRequest
    {
        $this->submit()->assertSessionHasNoErrors();

        return LeaveRequest::firstOrFail();
    }

    public function test_employee_submits_leave_and_admins_are_notified(): void
    {
        $this->submit()->assertRedirect(route('employee.leave-requests.index'));

        $leave = LeaveRequest::firstOrFail();
        $this->assertSame('pending', $leave->status);
        $this->assertSame(1, $leave->total_days);
        $this->assertSame(1, AppNotification::where('user_id', $this->admin()->id)->where('type', 'leave')->count());
        $this->assertTrue(AuditLog::where('action', 'submit_leave')->exists());
    }

    public function test_submission_rules(): void
    {
        $this->pending();

        $this->submit()->assertSessionHasErrors('start_date');                                                       // tabrakan
        $this->submit(['start_date' => '2026-11-01', 'end_date' => '2026-11-01'])->assertSessionHasErrors('start_date'); // terlalu jauh ke belakang
        $this->submit(['start_date' => '2026-11-25', 'end_date' => '2027-03-01'])->assertSessionHasErrors('end_date');   // lebih dari 60 hari
        $this->submit(['start_date' => '2026-11-25', 'end_date' => '2026-11-24'])->assertSessionHasErrors('end_date');   // selesai sebelum mulai
        $this->submit(['reason' => 'ab', 'start_date' => '2026-11-26', 'end_date' => '2026-11-26'])->assertSessionHasErrors('reason');

        $this->assertSame(1, LeaveRequest::count());
    }

    public function test_sick_leave_requires_a_private_attachment(): void
    {
        Storage::fake('local');
        $sick = LeaveType::where('category', 'sick')->firstOrFail();

        $this->submit(['leave_type_id' => $sick->id])->assertSessionHasErrors('attachment');
        $this->submit(['leave_type_id' => $sick->id, 'attachment' => UploadedFile::fake()->create('shell.php', 10, 'application/x-php')])
            ->assertSessionHasErrors('attachment');

        $this->submit(['leave_type_id' => $sick->id, 'attachment' => UploadedFile::fake()->create('surat.pdf', 200, 'application/pdf')])
            ->assertSessionHasNoErrors();
        $leave = LeaveRequest::firstOrFail();
        Storage::disk('local')->assertExists($leave->attachment);

        $this->actingAs($this->admin())->get(route('leave-requests.attachment', $leave->id))->assertOk();
        $this->actingAs($this->employeeUser())->get(route('employee.leave-requests.attachment', $leave->id))->assertOk();

        $other = $this->makeEmployee('b');
        $this->actingAs($other->user)->get(route('employee.leave-requests.attachment', $leave->id))->assertNotFound();
    }

    public function test_approval_creates_leave_attendance_and_cannot_be_repeated(): void
    {
        $leave = $this->pending();
        $this->actingAs($this->admin());

        $this->post(route('leave-requests.approve', $leave->id), ['admin_note' => 'Disetujui'])->assertSessionHas('success');

        $leave->refresh();
        $this->assertSame('approved', $leave->status);
        $this->assertSame($this->admin()->id, $leave->reviewed_by);

        $record = AttendanceRecord::firstOrFail();
        $this->assertSame('leave', $record->status);
        $this->assertSame($leave->id, $record->leave_request_id);
        $this->assertSame(1, AppNotification::where('user_id', $this->employeeUser()->id)->where('type', 'leave')->count());

        $this->post(route('leave-requests.approve', $leave->id))->assertSessionHas('error');
        $this->post(route('leave-requests.reject', $leave->id), ['admin_note' => 'Coba tolak'])->assertSessionHas('error');
        $this->assertSame('approved', $leave->fresh()->status);
    }

    public function test_approval_converts_an_automatic_absent_to_leave(): void
    {
        $leave = $this->pending();
        AttendanceRecord::create([
            'employee_id' => $this->employee()->id, 'work_schedule_id' => WorkSchedule::firstOrFail()->id, 'attendance_date' => '2026-11-18',
            'status' => 'absent', 'work_mode' => 'WFO', 'verification_method' => 'system',
        ]);

        $this->actingAs($this->admin())->post(route('leave-requests.approve', $leave->id));

        $record = AttendanceRecord::firstOrFail();
        $this->assertSame('leave', $record->status);
        $this->assertTrue($record->is_corrected);
        $this->assertSame(1, AttendanceRecord::count());
    }

    public function test_rejection_needs_a_note_and_does_not_touch_attendance(): void
    {
        $leave = $this->pending();
        $this->actingAs($this->admin());

        $this->post(route('leave-requests.reject', $leave->id), ['admin_note' => ''])->assertSessionHasErrors('admin_note');
        $this->post(route('leave-requests.reject', $leave->id), ['admin_note' => 'Jadwal sedang padat'])->assertSessionHasNoErrors();

        $this->assertSame('rejected', $leave->fresh()->status);
        $this->assertSame(0, AttendanceRecord::count());
    }

    public function test_employee_can_cancel_only_pending_requests(): void
    {
        $leave = $this->pending();

        $this->delete(route('employee.leave-requests.destroy', $leave->id))->assertSessionHas('success');
        $this->assertSoftDeleted($leave);

        $approved = LeaveRequest::create([
            'employee_id' => $this->employee()->id, 'leave_type_id' => LeaveType::firstOrFail()->id,
            'start_date' => '2026-11-19', 'end_date' => '2026-11-19', 'total_days' => 1, 'reason' => 'Sudah disetujui', 'status' => 'approved',
        ]);
        $this->delete(route('employee.leave-requests.destroy', $approved->id))->assertSessionHas('error');
        $this->assertNotSoftDeleted($approved);
    }
}
