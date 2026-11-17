<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\FeatureSetting;
use Illuminate\Support\Facades\Cache;
use ReflectionProperty;
use Tests\TestCase;

class FeatureAccessTest extends TestCase
{
    private function toggle(string $key): void
    {
        $feature = FeatureSetting::where('feature_key', $key)->firstOrFail();
        $this->actingAs($this->superAdmin())->patch(route('feature-settings.toggle', $feature->id))->assertRedirect();
    }

    public function test_disabling_a_feature_blocks_every_role_and_hides_the_menu(): void
    {
        $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertOk()->assertSee(route('payroll.index'), false);

        $this->toggle('payroll');
        $this->assertFalse(FeatureSetting::isEnabled('payroll'));
        $this->assertTrue(AuditLog::where('action', 'toggle_feature')->exists());

        foreach ([$this->admin(), $this->superAdmin()] as $user) {
            $this->actingAs($user);
            $this->get('/payroll')->assertForbidden()->assertSee('Data Salary');
            $this->get('/payroll/1')->assertForbidden();
            $this->get('/payroll/1/export/pdf')->assertForbidden();
            $this->post('/payroll', ['month' => '2026-10'])->assertForbidden();
        }

        $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertOk()->assertDontSee(route('payroll.index'), false);

        // Halaman Pengaturan Fitur tetap bisa dibuka, dan fitur dapat diaktifkan kembali
        $this->actingAs($this->superAdmin())->get(route('feature-settings.index'))->assertOk();
        $this->toggle('payroll');
        $this->actingAs($this->admin())->get(route('payroll.index'))->assertOk();
    }

    public function test_role_check_comes_before_feature_check(): void
    {
        $this->toggle('payroll');

        $this->actingAs($this->employeeUser())->get('/payroll')->assertForbidden()->assertDontSee('dinonaktifkan');
    }

    public function test_json_endpoints_are_protected_too(): void
    {
        $this->toggle('attendance');
        $this->actingAs($this->employeeUser());

        $this->postJson(route('employee.attendance.check-in'), [])->assertForbidden()->assertJsonPath('code', 'feature_disabled');
        $this->get(route('employee.attendance'))->assertForbidden();
        $this->get(route('employee.history'))->assertForbidden();
        $this->get(route('employee.dashboard'))->assertOk()->assertSee('Absensi tidak tersedia');
    }

    public function test_face_leave_schedule_and_report_features(): void
    {
        $cases = [
            'face_recognition' => [[$this->admin(), '/face-data'], [$this->employeeUser(), '/employee/face']],
            'leave_requests' => [[$this->admin(), '/leave-requests'], [$this->employeeUser(), '/employee/leave-requests']],
            'schedules' => [[$this->admin(), '/schedules'], [$this->employeeUser(), '/employee/schedule']],
            'attendance_reports' => [[$this->admin(), '/reports/attendance']],
            'company_profile' => [[$this->admin(), '/company']],
            'employees' => [[$this->admin(), '/employees']],
        ];

        foreach ($cases as $key => $targets) {
            foreach ($targets as [$user, $url]) {
                $this->actingAs($user)->get($url)->assertOk();
            }

            $this->toggle($key);

            foreach ($targets as [$user, $url]) {
                $this->actingAs($user)->get($url)->assertForbidden();
            }
        }
    }

    public function test_master_data_has_two_levels(): void
    {
        $this->actingAs($this->admin());

        $this->toggle('positions');
        $this->actingAs($this->admin());
        $this->get('/positions')->assertForbidden();
        $this->get('/departments')->assertOk();

        $this->toggle('master_data');
        $this->actingAs($this->admin());
        $this->get('/departments')->assertForbidden();
        $this->get('/education-levels')->assertForbidden();
        $this->get('/leave-types')->assertForbidden();
        $this->get(route('employees.create'))->assertOk(); // form karyawan tidak bergantung pada Data Master
    }

    public function test_unknown_feature_key_fails_closed_and_empty_cache_is_not_trusted(): void
    {
        FeatureSetting::where('feature_key', 'payroll')->firstOrFail()->delete();
        $this->actingAs($this->admin())->get('/payroll')->assertForbidden();

        FeatureSetting::flush();
        $memo = new ReflectionProperty(FeatureSetting::class, 'memo');
        $memo->setValue(null, null);
        Cache::forever(FeatureSetting::CACHE_KEY, []); // cache kosong yang "terlanjur" tersimpan

        $this->assertTrue(FeatureSetting::isEnabled('positions')); // dibaca ulang dari database
    }

    public function test_only_super_admin_can_change_features(): void
    {
        $feature = FeatureSetting::where('feature_key', 'payroll')->firstOrFail();

        $this->actingAs($this->admin())->patch(route('feature-settings.toggle', $feature->id))->assertForbidden();
        $this->assertTrue(FeatureSetting::isEnabled('payroll'));
    }
}
