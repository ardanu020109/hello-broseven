<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\FeatureSetting;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\ScheduleDay;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->registerFace($this->employee());
        $this->travelTo(Carbon::parse('2026-11-18 08:05:00', 'Asia/Makassar')); // dalam toleransi 15 menit
    }

    private function at(string $time): void
    {
        $this->travelTo(Carbon::parse("2026-11-18 {$time}", 'Asia/Makassar'));
    }

    private function ticket(): string
    {
        $r = $this->actingAs($this->employeeUser())->postJson(route('employee.face.verify'), ['descriptor' => $this->faceVector(1, 0.003)]);
        $r->assertOk()->assertJsonPath('matched', true);

        return (string) $r->json('ticket');
    }

    private function checkIn(array $payload = [], bool $withTicket = true)
    {
        if ($withTicket) {
            $payload['face_ticket'] = $this->ticket();
        }

        return $this->actingAs($this->employeeUser())->postJson(route('employee.attendance.check-in'), $payload);
    }

    private function record(): AttendanceRecord
    {
        return AttendanceRecord::where('employee_id', $this->employee()->id)->firstOrFail();
    }

    /* ---------------- hadir, terlambat, pulang ---------------- */

    public function test_on_time_check_in(): void
    {
        $this->checkIn($this->officePayload())->assertOk()->assertJsonPath('status', 'present');

        $record = $this->record();
        $this->assertSame('present', $record->status);
        $this->assertSame(0, (int) $record->late_minutes);
        $this->assertSame('face_gps', $record->verification_method);
        $this->assertSame('WFO', $record->work_mode);
        $this->assertTrue($record->checkInLocation->is_within_radius);
        $this->assertLessThan(1, (float) $record->checkInLocation->distance_meters);
    }

    public function test_late_check_in_records_minutes(): void
    {
        $this->at('08:30:00');

        $this->checkIn($this->officePayload())->assertOk()->assertJsonPath('status', 'late');
        $this->assertSame('late', $this->record()->status);
        $this->assertSame(30, (int) $this->record()->late_minutes);
    }

    public function test_check_out_and_duplicate_check_in(): void
    {
        $this->checkIn($this->officePayload())->assertOk();

        // Absen masuk kedua ditolak
        $this->checkIn($this->officePayload())->assertStatus(422);
        $this->assertSame(1, AttendanceRecord::count());

        $this->at('08:05:30'); // terlalu cepat setelah absen masuk
        $this->actingAs($this->employeeUser())->postJson(route('employee.attendance.check-out'),
            $this->officePayload(['face_ticket' => $this->ticket()]))->assertStatus(422);

        $this->at('17:10:00');
        $this->actingAs($this->employeeUser())->postJson(route('employee.attendance.check-out'),
            $this->officePayload(['face_ticket' => $this->ticket()]))->assertOk();

        $record = $this->record();
        $this->assertNotNull($record->check_out_at);
        $this->assertSame('face_gps', $record->check_out_method);
    }

    /* ---------------- geofence ---------------- */

    public function test_wfo_outside_radius_is_rejected_and_client_cannot_force_wfh(): void
    {
        $outside = $this->officePayload(['latitude' => $this->officeLat() + 0.01]); // sekitar 1 km

        $this->checkIn($outside)->assertStatus(422)->assertJsonPath('code', 'outside_radius');
        $this->checkIn($outside + ['work_mode' => 'WFH', 'is_wfh' => true])->assertStatus(422)->assertJsonPath('code', 'outside_radius');
        $this->assertSame(0, AttendanceRecord::count());
    }

    public function test_wfo_location_rules(): void
    {
        $this->checkIn([])->assertStatus(422)->assertJsonPath('code', 'location_required');
        $this->checkIn($this->officePayload(['accuracy' => 500]))->assertStatus(422)->assertJsonPath('code', 'location_accuracy');
        $this->checkIn($this->officePayload(['captured_at' => (int) round(microtime(true) * 1000) - 600000]))
            ->assertStatus(422)->assertJsonPath('code', 'location_stale');
    }

    /* ---------------- WFH dan Hybrid ---------------- */

    public function test_wfh_employee_can_check_in_outside_radius_but_face_is_still_required(): void
    {
        $this->employee()->update(['work_mode' => 'WFH']);

        $this->checkIn([], false)->assertStatus(422)->assertJsonPath('code', 'face_ticket_invalid');

        $this->checkIn($this->officePayload(['latitude' => $this->officeLat() + 0.05]))->assertOk();
        $record = $this->record();
        $this->assertSame('WFH', $record->work_mode);
        $this->assertFalse($record->checkInLocation->is_within_radius);
        $this->assertGreaterThan(5000, (float) $record->checkInLocation->distance_meters);
    }

    public function test_wfh_without_any_location_checks_in_with_face_only(): void
    {
        $this->employee()->update(['work_mode' => 'WFH']);

        $this->checkIn([])->assertOk();
        $this->assertSame('face', $this->record()->verification_method);
        $this->assertSame(0, $this->record()->locations()->count());
    }

    public function test_hybrid_follows_the_schedule_day_mode(): void
    {
        $this->employee()->update(['work_mode' => 'Hybrid']);
        $outside = $this->officePayload(['latitude' => $this->officeLat() + 0.05]);

        ScheduleDay::where('day_of_week', 3)->update(['work_mode' => 'WFO']); // Rabu
        $this->checkIn($outside)->assertStatus(422)->assertJsonPath('code', 'outside_radius');

        ScheduleDay::where('day_of_week', 3)->update(['work_mode' => 'WFH']);
        $this->checkIn($outside)->assertOk();
        $this->assertSame('WFH', $this->record()->work_mode);
    }

    /* ---------------- tiket wajah ---------------- */

    public function test_missing_face_ticket_is_rejected(): void
    {
        $this->checkIn($this->officePayload(), false)->assertStatus(422)->assertJsonPath('code', 'face_ticket_invalid');
    }

    public function test_ticket_is_single_use(): void
    {
        $this->checkIn($this->officePayload())->assertOk();

        $this->at('08:08:00');
        $ticket = $this->ticket();
        $this->actingAs($this->employeeUser())->postJson(route('employee.attendance.check-out'),
            $this->officePayload(['face_ticket' => $ticket]))->assertOk();

        // Tiket yang sama tidak bisa dipakai lagi
        AttendanceRecord::query()->update(['check_out_at' => null]);
        $this->actingAs($this->employeeUser())->postJson(route('employee.attendance.check-out'),
            $this->officePayload(['face_ticket' => $ticket]))->assertStatus(422)->assertJsonPath('code', 'face_ticket_invalid');
    }

    public function test_ticket_cannot_be_used_by_another_employee(): void
    {
        $b = $this->makeEmployee('b');
        $this->registerFace($b);
        $ticket = $this->ticket(); // milik karyawan A

        $this->actingAs($b->user)->postJson(route('employee.attendance.check-in'),
            $this->officePayload(['face_ticket' => $ticket]))->assertStatus(422)->assertJsonPath('code', 'face_ticket_invalid');
        $this->assertSame(0, AttendanceRecord::where('employee_id', $b->id)->count());
    }

    /* ---------------- keadaan lain ---------------- */

    public function test_no_schedule_holiday_and_closed_window(): void
    {
        $c = $this->makeEmployee('c', false);
        $this->actingAs($c->user)->postJson(route('employee.attendance.check-in'), [])
            ->assertStatus(422)->assertJsonPath('phase', 'no_schedule');

        Holiday::create(['holiday_date' => '2026-11-18', 'name' => 'Libur Uji']);
        $this->checkIn([], false)->assertStatus(422)->assertJsonPath('phase', 'holiday');
        Holiday::query()->delete();

        $this->at('18:00:00');
        $this->checkIn([], false)->assertStatus(422)->assertJsonPath('phase', 'closed');
    }

    public function test_disabling_face_recognition_allows_gps_only_attendance(): void
    {
        FeatureSetting::where('feature_key', 'face_recognition')->firstOrFail()->update(['is_enabled' => false]);

        $this->checkIn($this->officePayload(), false)->assertOk();
        $this->assertSame('gps', $this->record()->verification_method);
    }

    public function test_inactive_employee_cannot_attend(): void
    {
        $this->employee()->update(['is_active' => false]);

        $this->checkIn($this->officePayload(), false)->assertForbidden();
    }

    /* ---------------- absent otomatis ---------------- */

    private function runAbsent(): void
    {
        $this->artisan('attendance:mark-absent', ['--days' => 0])->assertSuccessful();
    }

    public function test_absent_is_marked_after_the_cutoff_and_not_duplicated(): void
    {
        $this->at('20:00:00'); // jam pulang 17:00 + batas 120 menit = 19:00

        $this->runAbsent();
        $record = $this->record();
        $this->assertSame('absent', $record->status);
        $this->assertSame('system', $record->verification_method);

        $this->runAbsent();
        $this->assertSame(1, AttendanceRecord::count());
        $this->assertTrue(AuditLog::where('action', 'auto_attendance')->exists());
    }

    public function test_absent_is_not_marked_before_cutoff_on_holidays_weekends_or_for_inactive_staff(): void
    {
        $this->at('18:00:00');
        $this->runAbsent();
        $this->assertSame(0, AttendanceRecord::count());

        $this->at('20:00:00');
        Holiday::create(['holiday_date' => '2026-11-18', 'name' => 'Libur Uji']);
        $this->runAbsent();
        $this->assertSame(0, AttendanceRecord::count());
        Holiday::query()->delete();

        $this->employee()->update(['is_active' => false]);
        $this->runAbsent();
        $this->assertSame(0, AttendanceRecord::count());
        $this->employee()->update(['is_active' => true]);

        $this->travelTo(Carbon::parse('2026-11-21 20:00:00', 'Asia/Makassar')); // Sabtu
        $this->runAbsent();
        $this->assertSame(0, AttendanceRecord::count());
    }

    public function test_only_approved_leave_prevents_absent(): void
    {
        $this->at('20:00:00');
        $leave = LeaveRequest::create([
            'employee_id' => $this->employee()->id, 'leave_type_id' => LeaveType::firstOrFail()->id,
            'start_date' => '2026-11-18', 'end_date' => '2026-11-18', 'total_days' => 1, 'reason' => 'Uji', 'status' => 'pending',
        ]);

        $this->runAbsent();
        $this->assertSame('absent', $this->record()->status); // pending tidak sah

        AttendanceRecord::query()->delete();
        $leave->update(['status' => 'approved']);
        $this->runAbsent();
        $this->assertSame('leave', $this->record()->status);
        $this->assertSame($leave->id, $this->record()->leave_request_id);
    }
}
