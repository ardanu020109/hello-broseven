<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use App\Models\WorkSchedule;
use App\Services\AttendanceSummaryService;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use ZipArchive;

class ReportExportTest extends TestCase
{
    public function test_report_totals_match_the_data(): void
    {
        $this->seedOctoberAttendance();

        $data = $this->actingAs($this->admin())
            ->get(route('reports.attendance', ['mode' => 'month', 'month' => '2026-10']))->assertOk()->viewData('data');

        $t = $data['totals'];
        $this->assertSame([1, 22, 16, 3, 2, 0, 1], [$t['employees'], $t['total'], $t['present'], $t['late'], $t['absent'], $t['leave'], $t['sick']]);
    }

    public function test_days_without_records_follow_the_counting_rules(): void
    {
        $employee = $this->employee();
        $summary = app(AttendanceSummaryService::class);
        $counts = fn () => $summary->build(collect([$employee]), Carbon::parse('2026-11-01'), Carbon::parse('2026-11-30'), false)[$employee->id]['counts'];

        // 2-17 Nov = 12 hari kerja lewat tanpa catatan => Tidak Hadir; hari ini dan setelahnya belum dihitung
        $c = $counts();
        $this->assertSame([12, 12, 12], [$c['total'], $c['absent'], $c['unrecorded']]);

        AttendanceRecord::create([
            'employee_id' => $employee->id, 'work_schedule_id' => WorkSchedule::firstOrFail()->id, 'attendance_date' => '2026-11-18',
            'status' => 'present', 'work_mode' => 'WFO', 'verification_method' => 'manual',
        ]);
        $c = $counts();
        $this->assertSame([13, 1], [$c['total'], $c['present']]); // hari ini dihitung jika sudah ada catatan

        $employee->update(['is_active' => false]);
        $c = $counts();
        $this->assertSame([1, 0], [$c['total'], $c['absent']]); // karyawan nonaktif: tanpa Tidak Hadir buatan
    }

    public function test_attendance_report_downloads_and_print(): void
    {
        $this->seedOctoberAttendance();
        $this->actingAs($this->admin());
        $q = ['mode' => 'month', 'month' => '2026-10'];

        $pdf = $this->get(route('reports.attendance.export', ['format' => 'pdf'] + $q))->assertOk();
        $this->assertStringContainsString('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-1.4', $pdf->getContent());
        $this->assertStringEndsWith('%%EOF', $pdf->getContent());

        $this->get(route('reports.attendance.print', $q))->assertOk()->assertSee('Laporan Kehadiran');
        $this->get(route('reports.attendance.export', ['format' => 'csv'] + $q))->assertNotFound();
    }

    private function assertXlsx($response, array $sheets): void
    {
        if (!class_exists(ZipArchive::class)) {
            $this->markTestSkipped('Ekstensi zip belum aktif.');
        }

        $path = $response->assertOk()->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path) === true, 'Berkas xlsx bukan zip yang valid.');

        $this->assertNotFalse($zip->locateName('xl/workbook.xml'));
        $workbook = $zip->getFromName('xl/workbook.xml');
        foreach ($sheets as $name) {
            $this->assertStringContainsString($name, $workbook);
        }

        $zip->close();
        @unlink($path);
    }

    public function test_attendance_report_xlsx(): void
    {
        $this->seedOctoberAttendance();

        $this->assertXlsx(
            $this->actingAs($this->admin())->get(route('reports.attendance.export', ['format' => 'xlsx', 'mode' => 'month', 'month' => '2026-10'])),
            ['Rekap', 'Rincian Harian']
        );
    }

    public function test_payroll_exports_and_payslip(): void
    {
        $this->seedOctoberAttendance();
        $this->actingAs($this->admin())->post(route('payroll.store'), ['month' => '2026-10']);

        $period = PayrollPeriod::firstOrFail();
        $payroll = Payroll::firstOrFail();

        $this->get(route('payroll.show', $period->id))->assertOk();
        $this->get(route('payroll.employee', [$period->id, $payroll->id]))->assertOk();
        $this->get(route('payroll.slip', [$period->id, $payroll->id]))->assertOk()->assertSee('SLIP GAJI')->assertSee('DRAFT');

        foreach ([route('payroll.export', [$period->id, 'pdf']), route('payroll.slip.pdf', [$period->id, $payroll->id])] as $url) {
            $res = $this->get($url)->assertOk();
            $this->assertStringStartsWith('%PDF-1.4', $res->getContent());
        }

        $this->assertXlsx($this->get(route('payroll.export', [$period->id, 'xlsx'])), ['Ringkasan', 'Rincian Harian']);

        // slip hanya untuk payroll milik periode di URL
        $other = PayrollPeriod::create([
            'year' => 2026, 'month' => 9, 'start_date' => '2026-09-01', 'end_date' => '2026-09-30', 'status' => 'draft', 'deduction_basis' => 'daily_salary',
        ]);
        $this->get(route('payroll.slip', [$other->id, $payroll->id]))->assertNotFound();
    }

    public function test_employees_cannot_open_reports_or_payroll(): void
    {
        $this->actingAs($this->employeeUser());

        $this->get('/reports/attendance/export/pdf')->assertForbidden();
        $this->get('/payroll/1/export/xlsx')->assertForbidden();
        $this->get('/payroll/1/employees/1/slip.pdf')->assertForbidden();
    }
}
