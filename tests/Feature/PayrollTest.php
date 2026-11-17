<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use App\Models\SalaryDeductionRule;
use Tests\TestCase;

class PayrollTest extends TestCase
{
    private function create(string $month = '2026-10')
    {
        return $this->actingAs($this->admin())->post(route('payroll.store'), ['month' => $month]);
    }

    public function test_payroll_matches_the_formula(): void
    {
        $exp = $this->seedOctoberAttendance();
        $this->create()->assertSessionHasNoErrors()->assertRedirect();

        $period = PayrollPeriod::firstOrFail();
        $payroll = Payroll::firstOrFail();

        $this->assertSame('draft', $period->status);
        $this->assertSame($exp['days'], $payroll->total_working_days);
        $this->assertSame([16, 3, 2, 0, 1], [$payroll->present_days, $payroll->late_days, $payroll->absent_days, $payroll->leave_days, $payroll->sick_days]);

        // gaji harian Rp100.000: potongan 3x10% + 2x100% + 1x50% = 280.000
        $this->assertSame('100000.00', (string) $payroll->daily_salary);
        $this->assertSame('2200000.00', (string) $payroll->gross_salary);
        $this->assertSame('280000.00', (string) $payroll->total_deduction);
        $this->assertSame('1920000.00', (string) $payroll->net_salary);

        // tidak ada penghitungan ganda, dan rincian cocok dengan total
        $this->assertSame(22, $payroll->details()->count());
        $this->assertSame(22, $payroll->details->pluck('work_date')->map(fn ($d) => $d->toDateString())->unique()->count());
        $this->assertEqualsWithDelta(1920000.0, (float) $payroll->details->sum(fn ($d) => (float) $d->net_amount), 0.001);
    }

    public function test_only_working_days_count_weekends_are_never_absent(): void
    {
        $this->create('2026-10');
        $weekend = Payroll::firstOrFail()->details->filter(fn ($d) => $d->work_date->isWeekend());

        $this->assertCount(0, $weekend);
    }

    public function test_period_rules(): void
    {
        $this->seedOctoberAttendance();

        $this->create('2026-12')->assertSessionHasErrors('month');   // masa depan
        $this->create('2026-10');
        $this->create('2026-10')->assertSessionHasErrors('month');   // duplikat
        $this->create('2026-03')->assertSessionHas('error');         // tidak ada hari kerja yang dihitung (tanpa data)
    }

    public function test_rule_changes_do_not_alter_a_draft_until_recalculated_and_finalize_uses_the_snapshot(): void
    {
        $this->seedOctoberAttendance();
        $this->create();
        $period = PayrollPeriod::firstOrFail();

        SalaryDeductionRule::where('status_key', 'late')->update(['deduction_percent' => 50]);

        $this->post(route('payroll.finalize', $period->id))->assertSessionHas('success');
        $this->assertSame('280000.00', (string) Payroll::firstOrFail()->total_deduction); // snapshot: late tetap 10%
        $this->assertSame('finalized', $period->fresh()->status);
    }

    public function test_finalized_period_is_locked(): void
    {
        $this->seedOctoberAttendance();
        $this->create();
        $period = PayrollPeriod::firstOrFail();

        $this->post(route('payroll.finalize', $period->id));

        $this->post(route('payroll.recalculate', $period->id))->assertSessionHas('error');
        $this->delete(route('payroll.destroy', $period->id))->assertSessionHas('error');
        $this->assertSame(1, PayrollPeriod::count());

        // absensi pada periode final tidak dapat dikoreksi
        $absent = AttendanceRecord::where('status', 'absent')->firstOrFail();
        $this->put(route('attendance.correct', $absent->id), [
            'status' => 'present', 'check_in_time' => '08:00', 'reason' => 'Koreksi uji coba panjang',
        ])->assertSessionHas('error');
        $this->assertSame('absent', $absent->fresh()->status);

        // absent otomatis juga melewati periode final
        AttendanceRecord::query()->whereDate('attendance_date', '2026-10-05')->delete();
        $this->artisan('attendance:mark-absent', ['--days' => 31])->assertSuccessful();
        $this->assertFalse(AttendanceRecord::whereDate('attendance_date', '2026-10-05')->exists());

        $this->post(route('payroll.paid', $period->id))->assertSessionHas('success');
        $this->assertSame('paid', $period->fresh()->status);
        $this->assertSame('paid', Payroll::firstOrFail()->status);
    }

    public function test_draft_can_be_deleted_and_paid_requires_final(): void
    {
        $this->seedOctoberAttendance();
        $this->create();
        $period = PayrollPeriod::firstOrFail();

        $this->post(route('payroll.paid', $period->id))->assertSessionHas('error');
        $this->delete(route('payroll.destroy', $period->id))->assertRedirect(route('payroll.index'));
        $this->assertSame(0, PayrollPeriod::count());
    }

    public function test_running_month_cannot_be_finalized(): void
    {
        $this->create('2026-11')->assertSessionHasNoErrors();
        $period = PayrollPeriod::firstOrFail();

        $this->post(route('payroll.finalize', $period->id))->assertSessionHas('error');
        $this->assertSame('draft', $period->fresh()->status);
        // hari ini (18 Nov) belum dihitung tanpa catatan: 2-17 Nov = 12 hari kerja
        $this->assertSame(12, Payroll::firstOrFail()->total_working_days);
    }

    public function test_fixed_amount_basis_is_super_admin_only_and_changes_the_deduction(): void
    {
        $this->seedOctoberAttendance();
        SalaryDeductionRule::where('status_key', 'absent')->update(['fixed_amount' => 40000]);

        $this->actingAs($this->admin())->post(route('payroll.settings'), ['payroll_deduction_basis' => 'fixed_amount'])->assertForbidden();
        $this->assertSame('daily_salary', Company::current()->payroll_deduction_basis);

        $this->actingAs($this->superAdmin())->post(route('payroll.settings'), ['payroll_deduction_basis' => 'fixed_amount'])
            ->assertSessionHasNoErrors();
        $this->create();

        // 2 hari tidak hadir x 40.000; terlambat dan sakit bernominal 0
        $this->assertSame('80000.00', (string) Payroll::firstOrFail()->total_deduction);
    }
}
