<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\EmployeeFaceDescriptor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FaceTest extends TestCase
{
    private function facePayload(array $o = []): array
    {
        return array_merge(['consent' => true, 'samples' => $this->faceSamples()], $o);
    }

    private function register(array $o = [])
    {
        return $this->actingAs($this->employeeUser())->postJson(route('employee.face.store'), $this->facePayload($o));
    }

    public function test_first_registration_is_active_and_stored_encrypted(): void
    {
        $this->register()->assertOk()->assertJsonPath('status', 'active');

        $record = EmployeeFaceDescriptor::firstOrFail();
        $this->assertSame('active', $record->status);
        $this->assertNotNull($record->consent_at);
        $this->assertCount(5, $record->descriptor);
        $this->assertCount(128, $record->descriptor[0]);

        // Nilai mentah di database terenkripsi, bukan angka terbaca
        $raw = (string) DB::table('employee_face_descriptors')->value('descriptor');
        $this->assertDoesNotMatchRegularExpression('/\d\.\d/', $raw);
        $this->assertArrayNotHasKey('descriptor', $record->toArray());
    }

    public function test_registration_requirements(): void
    {
        $this->postJson(route('employee.face.store'), $this->facePayload())->assertUnauthorized();
        $this->actingAs($this->admin())->postJson(route('employee.face.store'), $this->facePayload())->assertForbidden();

        $this->register(['consent' => false])->assertStatus(422);
        $this->register(['samples' => [$this->faceVector(1), $this->faceVector(1)]])->assertStatus(422);
        $this->register(['samples' => [array_slice($this->faceVector(1), 0, 127), $this->faceVector(1), $this->faceVector(1)]])->assertStatus(422);
        $this->register(['samples' => [$this->faceVector(1), $this->faceVector(2), $this->faceVector(3)]])
            ->assertStatus(422)->assertJsonPath('ok', false);

        $this->assertSame(0, EmployeeFaceDescriptor::count());
    }

    public function test_reregistration_needs_admin_approval(): void
    {
        $this->registerFace($this->employee());

        $this->register()->assertOk()->assertJsonPath('status', 'pending_approval');
        $this->assertSame(1, EmployeeFaceDescriptor::where('status', 'active')->count()); // data lama tetap dipakai
        $this->assertSame(1, AppNotification::where('user_id', $this->admin()->id)->where('type', 'face')->count());

        $pending = EmployeeFaceDescriptor::where('status', 'pending_approval')->firstOrFail();
        $old = EmployeeFaceDescriptor::where('status', 'active')->firstOrFail();

        $this->actingAs($this->admin())->post(route('faces.approve', $pending->id))->assertSessionHas('success');
        $this->assertSame('active', $pending->fresh()->status);
        $this->assertSame('inactive', $old->fresh()->status);
    }

    public function test_admin_can_reject_and_deactivate(): void
    {
        $active = $this->registerFace($this->employee());
        $pending = $this->registerFace($this->employee(), 'pending_approval');

        $this->actingAs($this->admin());
        $this->post(route('faces.reject', $pending->id));
        $this->assertDatabaseMissing('employee_face_descriptors', ['id' => $pending->id]); // data ditolak dihapus

        $this->post(route('faces.deactivate', $active->id));
        $this->assertSame('inactive', $active->fresh()->status);

        $this->travelTo(Carbon::parse('2026-11-18 08:05:00', 'Asia/Makassar'));
        $this->actingAs($this->employeeUser())
            ->postJson(route('employee.face.verify'), ['descriptor' => $this->faceVector(1, 0.003)])
            ->assertStatus(409)->assertJsonPath('needs_registration', true);
    }

    public function test_verification_matches_only_the_registered_face(): void
    {
        $this->registerFace($this->employee());
        $this->travelTo(Carbon::parse('2026-11-18 08:05:00', 'Asia/Makassar'));
        $this->actingAs($this->employeeUser());

        $ok = $this->postJson(route('employee.face.verify'), ['descriptor' => $this->faceVector(1, 0.003)]);
        $ok->assertOk()->assertJsonPath('matched', true);
        $this->assertSame(40, strlen((string) $ok->json('ticket')));

        // Wajah lain ditolak dan responsnya tidak membocorkan jarak atau skor
        $this->postJson(route('employee.face.verify'), ['descriptor' => $this->faceVector(2)])
            ->assertStatus(422)->assertJsonPath('matched', false)
            ->assertJsonMissingPath('distance')->assertJsonMissingPath('score')->assertJsonMissingPath('ticket');
    }

    public function test_verification_is_locked_after_repeated_failures(): void
    {
        $this->registerFace($this->employee());
        $this->travelTo(Carbon::parse('2026-11-18 08:05:00', 'Asia/Makassar'));
        $this->actingAs($this->employeeUser());

        for ($i = 0; $i < 8; $i++) {
            $this->postJson(route('employee.face.verify'), ['descriptor' => $this->faceVector(2)])->assertStatus(422);
        }

        $this->postJson(route('employee.face.verify'), ['descriptor' => $this->faceVector(1, 0.003)])
            ->assertStatus(429)->assertJsonPath('stop', true);
    }

    public function test_verification_refused_outside_attendance_window(): void
    {
        $this->registerFace($this->employee());
        $this->travelTo(Carbon::parse('2026-11-18 20:00:00', 'Asia/Makassar'));

        $this->actingAs($this->employeeUser())
            ->postJson(route('employee.face.verify'), ['descriptor' => $this->faceVector(1, 0.003)])
            ->assertStatus(422)->assertJsonPath('matched', false);
    }
}
