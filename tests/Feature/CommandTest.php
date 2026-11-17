<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\EmployeeFaceDescriptor;
use App\Models\User;
use Tests\TestCase;

class CommandTest extends TestCase
{
    private function face(int $employeeId, string $status, ?int $deactivatedDaysAgo = null): EmployeeFaceDescriptor
    {
        return EmployeeFaceDescriptor::create([
            'employee_id' => $employeeId, 'descriptor' => $this->faceSamples(), 'status' => $status,
            'registered_at' => now(), 'deactivated_at' => $deactivatedDaysAgo ? now()->subDays($deactivatedDaysAgo) : null,
        ]);
    }

    public function test_purge_removes_old_inactive_and_orphaned_biometric_data_only(): void
    {
        $a = $this->employee();
        $old = $this->face($a->id, 'inactive', 100);
        $recent = $this->face($a->id, 'inactive', 10);
        $active = $this->face($a->id, 'active');

        $b = $this->makeEmployee('b');
        $orphan = $this->face($b->id, 'active');
        $b->delete(); // soft delete: data wajahnya yatim

        $this->artisan('faces:purge-inactive')->assertSuccessful();

        $this->assertDatabaseMissing('employee_face_descriptors', ['id' => $old->id]);
        $this->assertDatabaseMissing('employee_face_descriptors', ['id' => $orphan->id]);
        $this->assertDatabaseHas('employee_face_descriptors', ['id' => $recent->id]);
        $this->assertDatabaseHas('employee_face_descriptors', ['id' => $active->id]);
        $this->assertTrue(AuditLog::where('action', 'purge_face_data')->exists());
    }

    public function test_audit_prune_keeps_recent_logs_and_enforces_a_minimum(): void
    {
        AuditLog::create(['action' => 'uji-lama', 'created_at' => now()->subDays(800)]);
        AuditLog::create(['action' => 'uji-baru', 'created_at' => now()->subDays(5)]);

        $this->artisan('audit:prune', ['--days' => 30])->assertFailed();
        $this->assertSame(1, AuditLog::where('action', 'uji-lama')->count());

        $this->artisan('audit:prune', ['--force' => true])->assertSuccessful();
        $this->assertSame(0, AuditLog::where('action', 'uji-lama')->count());
        $this->assertSame(1, AuditLog::where('action', 'uji-baru')->count());
    }

    public function test_create_super_admin_command(): void
    {
        $this->artisan('app:create-super-admin', ['--name' => 'Pemilik', '--username' => 'pemilik', '--email' => 'pemilik@contoh.test'])
            ->expectsQuestion('Password (minimal 12 karakter)', 'KataSandiKuat#2026')
            ->expectsQuestion('Ulangi password', 'KataSandiKuat#2026')
            ->assertSuccessful();

        $user = User::where('username', 'pemilik')->firstOrFail();
        $this->assertSame('super_admin', $user->role);
        $this->assertTrue($user->is_active);
        $this->assertNotSame('KataSandiKuat#2026', $user->password);

        $this->post('/login', ['login' => 'pemilik', 'password' => 'KataSandiKuat#2026'])->assertRedirect(route('superadmin.dashboard'));
    }

    public function test_create_super_admin_rejects_weak_or_duplicate_input(): void
    {
        $this->artisan('app:create-super-admin', ['--name' => 'Lemah', '--username' => 'lemah', '--email' => 'lemah@contoh.test'])
            ->expectsQuestion('Password (minimal 12 karakter)', 'pendek')
            ->expectsQuestion('Ulangi password', 'pendek')
            ->assertFailed();

        $this->artisan('app:create-super-admin', ['--name' => 'Dobel', '--username' => 'admin', '--email' => 'baru@contoh.test'])
            ->expectsQuestion('Password (minimal 12 karakter)', 'KataSandiKuat#2026')
            ->expectsQuestion('Ulangi password', 'KataSandiKuat#2026')
            ->assertFailed();

        $this->assertSame(0, User::where('username', 'lemah')->count());
    }
}
