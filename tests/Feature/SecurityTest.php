<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Position;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    public function test_security_headers_are_present(): void
    {
        $res = $this->get('/login');

        $res->assertHeader('X-Frame-Options', 'SAMEORIGIN')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', (string) $res->headers->get('Cache-Control'));
        $this->assertStringContainsString('camera=(self)', (string) $res->headers->get('Permissions-Policy'));
    }

    public function test_csp_report_only_mode_uses_a_matching_nonce(): void
    {
        config(['security.csp_mode' => 'report-only']);

        $res = $this->get('/login');
        $header = (string) $res->headers->get('Content-Security-Policy-Report-Only');

        $this->assertNotSame('', $header);
        $res->assertHeaderMissing('Content-Security-Policy');
        $this->assertSame(1, preg_match("/'nonce-([^']+)'/", $header, $m));
        $res->assertSee('nonce="'.$m[1].'"', false); // skrip inline membawa nonce yang sama
        $this->assertStringContainsString("object-src 'none'", $header);
        $this->assertStringContainsString("frame-ancestors 'self'", $header);
    }

    public function test_csp_enforce_and_off_modes(): void
    {
        config(['security.csp_mode' => 'enforce']);
        $this->get('/login')->assertHeader('Content-Security-Policy')->assertHeaderMissing('Content-Security-Policy-Report-Only');

        config(['security.csp_mode' => 'off']);
        $this->get('/login')->assertHeaderMissing('Content-Security-Policy')->assertHeaderMissing('Content-Security-Policy-Report-Only');
    }

    public function test_output_is_escaped(): void
    {
        Position::create(['code' => 'XSS', 'name' => '<script>alert(1)</script>', 'daily_salary' => 1, 'is_active' => true]);

        $this->actingAs($this->admin())->get(route('positions.index'))
            ->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_executable_uploads_are_rejected(): void
    {
        $base = $this->employee();

        $this->actingAs($this->admin())->post(route('employees.store'), [
            'employee_number' => 'EMP-2001', 'full_name' => 'Uji Unggah', 'email' => 'unggah@hellobroseven.test', 'username' => 'unggah',
            'password' => 'rahasia123', 'position_id' => $base->position_id, 'department_id' => $base->department_id,
            'employee_status_id' => $base->employee_status_id, 'join_date' => '2026-02-01', 'work_mode' => 'WFO', 'is_active' => '1',
            'photo' => UploadedFile::fake()->create('shell.php', 10, 'application/x-php'),
            'supporting_document' => UploadedFile::fake()->create('x.exe', 10, 'application/octet-stream'),
        ])->assertSessionHasErrors(['photo', 'supporting_document']);

        $this->assertSame(1, Employee::count());
    }

    public function test_private_employee_documents_are_not_public(): void
    {
        $this->get('/employees/1/files/ktp')->assertRedirect(route('login'));
        $this->actingAs($this->employeeUser())->get('/employees/1/files/ktp')->assertForbidden();
        $status = $this->get('/storage/employees/ktp/x.jpg')->getStatusCode();
        $this->assertContains($status, [403, 404], "Berkas privat tidak boleh dapat dibuka lewat /storage (status {$status}).");
    }

    public function test_mass_assignment_cannot_escalate_role(): void
    {
        $this->actingAs($this->employeeUser())->put(route('profile.update'), [
            'phone' => '081234567890', 'role' => 'super_admin', 'is_active' => 0, 'user_id' => 1,
        ]);

        $this->assertSame('employee', $this->employeeUser()->fresh()->role);
        $this->assertTrue($this->employeeUser()->fresh()->is_active);
    }
}
