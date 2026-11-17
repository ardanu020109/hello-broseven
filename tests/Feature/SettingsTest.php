<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\Company;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    /* ---------------- Profile Perusahaan ---------------- */

    private function company(array $o = []): array
    {
        return array_merge([
            'name' => 'PT Uji Coba', 'description' => 'Deskripsi uji', 'address' => 'Makassar', 'email' => 'info@uji.test',
            'phone' => '+62 411 123456', 'website' => 'https://uji.test', 'latitude' => '-5.1476540', 'longitude' => '119.4327310',
            'attendance_radius_meters' => 120, 'max_gps_accuracy_meters' => 40, 'face_match_threshold' => '0.45',
            'absent_cutoff_minutes' => 90, 'timezone' => 'Asia/Makassar', 'operating_start' => '08:00', 'operating_end' => '17:00',
        ], $o);
    }

    public function test_company_profile_is_saved_and_audited(): void
    {
        $this->actingAs($this->admin())->put(route('company.update'), $this->company())->assertRedirect(route('company.edit'));

        $c = Company::current();
        $this->assertSame('PT Uji Coba', $c->name);
        $this->assertSame([120, 40, 90], [$c->attendance_radius_meters, $c->max_gps_accuracy_meters, $c->absent_cutoff_minutes]);
        $this->assertEqualsWithDelta(0.45, (float) $c->face_match_threshold, 0.0001);
        $this->assertTrue(AuditLog::where('action', 'update_company')->exists());

        $this->get('/')->assertSee('PT Uji Coba'); // landing page ikut berubah
    }

    public function test_company_profile_validation(): void
    {
        $this->actingAs($this->admin());

        $this->put(route('company.update'), $this->company([
            'attendance_radius_meters' => 5, 'face_match_threshold' => '0.9', 'website' => 'contoh.com', 'email' => 'bukan-email',
        ]))->assertSessionHasErrors(['attendance_radius_meters', 'face_match_threshold', 'website', 'email']);

        $this->put(route('company.update'), $this->company(['longitude' => '']))->assertSessionHasErrors('longitude');
        $this->put(route('company.update'), $this->company(['operating_start' => '17:00', 'operating_end' => '08:00']))->assertSessionHasErrors('operating_end');
        $this->put(route('company.update'), $this->company(['logo' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml')]))->assertSessionHasErrors('logo');

        $this->assertNotSame('PT Uji Coba', Company::current()->name);
    }

    public function test_employee_cannot_open_company_profile(): void
    {
        $this->actingAs($this->employeeUser())->get('/company')->assertForbidden();
        $this->put('/company', $this->company())->assertForbidden();
    }

    /* ---------------- Profile Saya ---------------- */

    public function test_employee_can_change_only_allowed_fields(): void
    {
        $user = $this->employeeUser();

        $this->actingAs($user)->put(route('profile.update'), [
            'phone' => '081234567890', 'address' => 'Jl. Uji No. 1', 'emergency_contact_name' => 'Ibu', 'emergency_contact_phone' => '081200000000',
            'full_name' => 'DIRETAS', 'position_id' => 3, 'email' => 'diretas@x.test', 'employee_number' => 'HACK-1',
        ])->assertSessionHasNoErrors();

        $e = $this->employee()->fresh();
        $this->assertSame('081234567890', $e->phone);
        $this->assertSame('Jl. Uji No. 1', $e->address);
        $this->assertSame('Karyawan Uji', $e->full_name);
        $this->assertSame('EMP-0001', $e->employee_number);
        $this->assertSame('employee@hellobroseven.test', $user->fresh()->email);
    }

    public function test_admin_can_edit_own_account(): void
    {
        $this->actingAs($this->admin());

        $this->put(route('profile.update'), ['name' => 'Admin Baru', 'username' => 'admin', 'email' => 'superadmin@hellobroseven.test'])
            ->assertSessionHasErrors('email'); // sudah dipakai akun lain

        $this->put(route('profile.update'), ['name' => 'Admin Baru', 'username' => 'admin', 'email' => 'admin@hellobroseven.test'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Admin Baru', $this->admin()->name);
    }

    public function test_password_change_rules(): void
    {
        $this->actingAs($this->admin());
        $new = ['password' => 'PasswordBaru#2026', 'password_confirmation' => 'PasswordBaru#2026'];

        $this->put(route('profile.password'), ['current_password' => 'salah'] + $new)->assertSessionHasErrorsIn('password', ['current_password']);
        $this->put(route('profile.password'), ['current_password' => 'password123', 'password' => 'PasswordBaru#2026', 'password_confirmation' => 'beda'])
            ->assertSessionHasErrorsIn('password', ['password']);
        $this->put(route('profile.password'), ['current_password' => 'password123', 'password' => 'password123', 'password_confirmation' => 'password123'])
            ->assertSessionHasErrorsIn('password', ['password']);

        $this->put(route('profile.password'), ['current_password' => 'password123'] + $new)->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('PasswordBaru#2026', $this->admin()->password));
        $this->assertTrue(AuditLog::where('action', 'change_password')->exists());
    }

    public function test_password_change_is_throttled(): void
    {
        $this->actingAs($this->admin());

        for ($i = 0; $i < 6; $i++) {
            $this->put(route('profile.password'), ['current_password' => 'salah', 'password' => 'abcdefgh', 'password_confirmation' => 'abcdefgh']);
        }

        $this->put(route('profile.password'), ['current_password' => 'salah', 'password' => 'abcdefgh', 'password_confirmation' => 'abcdefgh'])
            ->assertStatus(429);
    }

    /* ---------------- Notifikasi ---------------- */

    public function test_notifications_belong_to_their_owner(): void
    {
        $mine = AppNotification::create(['user_id' => $this->employeeUser()->id, 'type' => 'leave', 'title' => 'Milik saya', 'url' => '/employee/dashboard']);
        $theirs = AppNotification::create(['user_id' => $this->admin()->id, 'type' => 'leave', 'title' => 'Milik admin', 'url' => '/dashboard']);

        $this->actingAs($this->employeeUser());
        $this->get(route('notifications.index'))->assertOk()->assertSee('Milik saya')->assertDontSee('Milik admin');

        $this->post(route('notifications.read', $theirs->id))->assertNotFound();
        $this->delete(route('notifications.destroy', $theirs->id))->assertNotFound();
        $this->assertNull($theirs->fresh()->read_at);

        $this->post(route('notifications.read', $mine->id))->assertRedirect(url('/employee/dashboard'));
        $this->assertNotNull($mine->fresh()->read_at);
    }

    public function test_external_notification_urls_are_never_followed(): void
    {
        $n = AppNotification::create(['user_id' => $this->employeeUser()->id, 'type' => 'leave', 'title' => 'Uji', 'url' => 'https://jahat.example/phish']);

        $res = $this->actingAs($this->employeeUser())->post(route('notifications.read', $n->id));
        $this->assertStringNotContainsString('jahat.example', (string) $res->headers->get('Location'));
    }

    public function test_mark_all_read_and_delete(): void
    {
        $user = $this->employeeUser();
        AppNotification::create(['user_id' => $user->id, 'type' => 'face', 'title' => 'A']);
        $b = AppNotification::create(['user_id' => $user->id, 'type' => 'face', 'title' => 'B']);

        $this->actingAs($user)->post(route('notifications.read-all'));
        $this->assertSame(0, AppNotification::where('user_id', $user->id)->whereNull('read_at')->count());

        $this->delete(route('notifications.destroy', $b->id));
        $this->assertSame(1, AppNotification::where('user_id', $user->id)->count());
    }

    /* ---------------- Audit log ---------------- */

    public function test_audit_log_hides_secrets_and_is_super_admin_only(): void
    {
        AuditLog::create(['action' => 'uji', 'new_values' => ['password' => 'rahasia-banget', 'x' => 1]]);

        $res = $this->actingAs($this->superAdmin())->get(route('audit-logs.index'))->assertOk();
        $this->assertStringContainsString('[disembunyikan]', json_encode($res->viewData('details')));
        $res->assertDontSee('rahasia-banget');

        $this->actingAs($this->admin())->get(route('audit-logs.index'))->assertForbidden();
    }

    public function test_sensitive_actions_are_audited(): void
    {
        $this->post('/login', ['login' => 'admin', 'password' => 'password123']);
        $this->post(route('faces.approve', 999999));
        $this->put(route('company.update'), $this->company());

        $actions = AuditLog::pluck('action')->all();
        $this->assertContains('login', $actions);
        $this->assertContains('update_company', $actions);
    }
}
