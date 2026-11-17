<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\PayrollPeriod;
use App\Support\Format;
use App\Support\PdfWriter;
use App\Support\XlsxWriter;
use Illuminate\Support\Collection;

class ReportExporter
{
    private const TEAL = [0.235, 0.431, 0.443];
    private const RED = [0.75, 0.22, 0.17];

    /* ===================== Laporan kehadiran ===================== */

    /** @return array{0:string,1:string} [path berkas sementara, nama berkas] */
    public function attendanceXlsx(array $period, string $filterText, array $data, bool $withDays): array
    {
        $company = Company::current();
        $t = $data['totals'];

        $rows = [
            [['Laporan Kehadiran', XlsxWriter::S_TITLE]],
            [$company?->name ?? config('app.name')],
            ['Periode', $period['label']],
            ['Rentang', $period['from']->format('d/m/Y').' - '.$period['to']->format('d/m/Y')],
            ['Filter', $filterText],
            ['Dicetak', now()->format('d/m/Y H:i')],
        ];
        if ($period['beyond_today']) {
            $rows[] = ['Catatan', 'Hari setelah hari ini belum dihitung.'];
        }
        if ($t['unrecorded'] > 0) {
            $rows[] = ['Catatan', $t['unrecorded'].' hari kerja tanpa catatan absensi dihitung Tidak Hadir/Izin.'];
        }
        $rows[] = [];

        $head = ['No', 'NIK Internal', 'Nama', 'Jabatan', 'Departemen', 'Hari Kerja', 'Hadir', 'Terlambat', 'Tidak Hadir', 'Izin', 'Sakit', '% Kehadiran'];
        $rows[] = array_map(fn ($h) => [$h, XlsxWriter::S_HEADER], $head);

        $i = 0;
        foreach ($data['rows'] as $r) {
            $e = $r['employee'];
            $c = $r['counts'];
            $rows[] = [
                ++$i, $e->employee_number, $e->full_name, $e->position?->name ?? '-', $e->department?->name ?? '-',
                $c['total'], $c['present'], $c['late'], $c['absent'], $c['leave'], $c['sick'],
                [round($r['rate'], 4), XlsxWriter::S_PERCENT],
            ];
        }

        $bold = fn ($v) => [$v, XlsxWriter::S_BOLD];
        $rate = $t['total'] > 0 ? ($t['present'] + $t['late']) / $t['total'] : 0;
        $rows[] = [
            '', '', $bold('TOTAL ('.$t['employees'].' karyawan)'), '', '',
            $bold($t['total']), $bold($t['present']), $bold($t['late']), $bold($t['absent']), $bold($t['leave']), $bold($t['sick']),
            [round($rate, 4), XlsxWriter::S_BOLD_PERCENT],
        ];

        $x = (new XlsxWriter())->addSheet('Rekap', $rows, [6, 16, 28, 20, 22, 11, 9, 11, 13, 8, 8, 13]);

        if ($withDays) {
            $detail = [array_map(fn ($h) => [$h, XlsxWriter::S_HEADER], ['NIK Internal', 'Nama', 'Tanggal', 'Hari', 'Status', 'Jam Masuk', 'Jam Pulang', 'Terlambat (menit)', 'Mode', 'Keterangan'])];

            foreach ($data['rows'] as $r) {
                foreach ($r['days'] ?? [] as $d) {
                    $date = \Illuminate\Support\Carbon::parse($d['date']);
                    $detail[] = [
                        $r['employee']->employee_number, $r['employee']->full_name, $date->format('d/m/Y'),
                        $date->copy()->locale('id')->translatedFormat('l'), Format::dayStatus($d['status']),
                        $d['check_in'] ?? '-', $d['check_out'] ?? '-', $d['late_minutes'], $d['work_mode'],
                        $d['synthetic'] ? 'Tidak ada catatan absensi' : '',
                    ];
                }
            }
            $x->addSheet('Rincian Harian', $detail, [16, 28, 12, 10, 14, 11, 11, 17, 8, 30]);
        }

        return [$x->save(), $this->attendanceFile($period, 'xlsx')];
    }

    /** @return array{0:string,1:string} [isi PDF, nama berkas] */
    public function attendancePdf(array $period, string $filterText, array $data): array
    {
        $company = Company::current();
        $t = $data['totals'];

        $pdf = new PdfWriter(true);
        $pdf->heading('Laporan Kehadiran', 15);
        $pdf->paragraph(($company?->name ?? config('app.name')).' | Periode: '.$period['label'], 9, true, [0.2, 0.2, 0.2]);
        $pdf->paragraph('Rentang '.$period['from']->format('d/m/Y').' - '.$period['to']->format('d/m/Y').' | Filter: '.$filterText, 8);
        if ($period['beyond_today']) {
            $pdf->paragraph('Catatan: hari setelah hari ini belum dihitung.', 8);
        }
        if ($t['unrecorded'] > 0) {
            $pdf->paragraph('Catatan: '.$t['unrecorded'].' hari kerja tanpa catatan absensi dihitung Tidak Hadir/Izin.', 8, false, self::RED);
        }
        $pdf->moveDown(8);

        $cols = [
            ['label' => 'No', 'w' => 3, 'align' => 'R'],
            ['label' => 'NIK', 'w' => 9],
            ['label' => 'Nama', 'w' => 18],
            ['label' => 'Jabatan', 'w' => 12],
            ['label' => 'Departemen', 'w' => 13],
            ['label' => 'Hari Kerja', 'w' => 7, 'align' => 'R'],
            ['label' => 'Hadir', 'w' => 6, 'align' => 'R'],
            ['label' => 'Terlambat', 'w' => 8, 'align' => 'R'],
            ['label' => 'Tdk Hadir', 'w' => 8, 'align' => 'R'],
            ['label' => 'Izin', 'w' => 6, 'align' => 'R'],
            ['label' => 'Sakit', 'w' => 6, 'align' => 'R'],
            ['label' => '% Hadir', 'w' => 7, 'align' => 'R'],
        ];

        $rows = [];
        $i = 0;
        foreach ($data['rows'] as $r) {
            $c = $r['counts'];
            $rows[] = [
                (string) ++$i, $r['employee']->employee_number, $r['employee']->full_name,
                $r['employee']->position?->name ?? '-', $r['employee']->department?->name ?? '-',
                (string) $c['total'], (string) $c['present'], (string) $c['late'], (string) $c['absent'], (string) $c['leave'], (string) $c['sick'],
                number_format($r['rate'] * 100, 1, ',', '.').'%',
            ];
        }

        $rate = $t['total'] > 0 ? ($t['present'] + $t['late']) / $t['total'] * 100 : 0;
        $rows[] = ['bold' => true, 'cells' => [
            '', '', 'TOTAL ('.$t['employees'].' karyawan)', '', '',
            (string) $t['total'], (string) $t['present'], (string) $t['late'], (string) $t['absent'], (string) $t['leave'], (string) $t['sick'],
            number_format($rate, 1, ',', '.').'%',
        ]];

        $pdf->table($cols, $rows, 8);

        return [$pdf->output($this->footer($company)), $this->attendanceFile($period, 'pdf')];
    }

    /* ===================== Payroll ===================== */

    /** @return array{0:string,1:string} */
    public function payrollXlsx(PayrollPeriod $period, Collection $payrolls): array
    {
        $company = Company::current();
        $label = Format::periodLabel($period->year, $period->month);
        $totals = $this->payrollTotals($payrolls);

        $rows = [
            [['Rekap Gaji Karyawan', XlsxWriter::S_TITLE]],
            [$company?->name ?? config('app.name')],
            ['Periode', $label],
            ['Status', Format::payrollStatus($period->status).($period->status === 'draft' ? ' (belum final, angka dapat berubah)' : '')],
            ['Dasar potongan', PayrollService::BASES[$period->deduction_basis] ?? $period->deduction_basis],
            ['Dicetak', now()->format('d/m/Y H:i')],
            [],
        ];

        $head = ['No', 'NIK Internal', 'Nama', 'Jabatan', 'Departemen', 'Gaji Harian', 'Hari Kerja', 'Hadir', 'Terlambat', 'Tidak Hadir', 'Izin', 'Sakit', 'Gaji Kotor', 'Potongan', 'Gaji Bersih', 'Status'];
        $rows[] = array_map(fn ($h) => [$h, XlsxWriter::S_HEADER], $head);

        $money = XlsxWriter::S_MONEY;
        $i = 0;
        foreach ($payrolls as $p) {
            $rows[] = [
                ++$i, $p->employee_number, $p->employee_name, $p->position_name, $p->department_name,
                [(float) $p->daily_salary, $money], $p->total_working_days, $p->present_days, $p->late_days, $p->absent_days, $p->leave_days, $p->sick_days,
                [(float) $p->gross_salary, $money], [(float) $p->total_deduction, $money], [(float) $p->net_salary, $money],
                Format::payrollStatus($p->status),
            ];
        }

        $bm = XlsxWriter::S_BOLD_MONEY;
        $bold = fn ($v) => [$v, XlsxWriter::S_BOLD];
        $rows[] = [
            '', '', $bold('TOTAL ('.$payrolls->count().' karyawan)'), '', '', '', $bold($totals['days']), '', '', '', '', '',
            [$totals['gross'], $bm], [$totals['deduction'], $bm], [$totals['net'], $bm], '',
        ];

        $x = (new XlsxWriter())->addSheet('Ringkasan', $rows, [6, 16, 28, 20, 22, 14, 11, 8, 11, 13, 8, 8, 16, 16, 16, 11]);

        $detail = [array_map(fn ($h) => [$h, XlsxWriter::S_HEADER], ['NIK Internal', 'Nama', 'Tanggal', 'Hari', 'Status', 'Gaji Harian', 'Potongan (%)', 'Potongan', 'Diterima', 'Catatan'])];
        $byPayroll = PayrollDetail::query()
            ->whereIn('payroll_id', $payrolls->pluck('id'))
            ->orderBy('payroll_id')->orderBy('work_date')
            ->get()
            ->groupBy('payroll_id');

        foreach ($payrolls as $p) {
            foreach ($byPayroll->get($p->id, collect()) as $d) {
                $detail[] = [
                    $p->employee_number, $p->employee_name, $d->work_date->format('d/m/Y'),
                    $d->work_date->copy()->locale('id')->translatedFormat('l'), Format::dayStatus($d->status),
                    [(float) $d->daily_salary, $money], (float) $d->deduction_percent,
                    [(float) $d->deduction_amount, $money], [(float) $d->net_amount, $money], $d->notes ?? '',
                ];
            }
        }
        $x->addSheet('Rincian Harian', $detail, [16, 28, 12, 10, 14, 14, 13, 14, 14, 40]);

        return [$x->save(), 'payroll-'.sprintf('%04d-%02d', $period->year, $period->month).'.xlsx'];
    }

    /** @return array{0:string,1:string} */
    public function payrollPdf(PayrollPeriod $period, Collection $payrolls): array
    {
        $company = Company::current();
        $totals = $this->payrollTotals($payrolls);

        $pdf = new PdfWriter(true);
        $pdf->heading('Rekap Gaji Karyawan', 15);
        $pdf->paragraph(($company?->name ?? config('app.name')).' | Periode: '.Format::periodLabel($period->year, $period->month).' | Status: '.Format::payrollStatus($period->status), 9, true, [0.2, 0.2, 0.2]);
        $pdf->paragraph('Dasar potongan: '.(PayrollService::BASES[$period->deduction_basis] ?? $period->deduction_basis), 8);
        if ($period->status === 'draft') {
            $pdf->paragraph('DRAFT - belum final, angka dapat berubah.', 8, true, self::RED);
        }
        $pdf->moveDown(8);

        $cols = [
            ['label' => 'No', 'w' => 3, 'align' => 'R'],
            ['label' => 'NIK', 'w' => 9],
            ['label' => 'Nama', 'w' => 16],
            ['label' => 'Jabatan', 'w' => 11],
            ['label' => 'Gaji/Hari', 'w' => 9, 'align' => 'R'],
            ['label' => 'Hari', 'w' => 4, 'align' => 'R'],
            ['label' => 'H', 'w' => 3, 'align' => 'R'],
            ['label' => 'T', 'w' => 3, 'align' => 'R'],
            ['label' => 'A', 'w' => 3, 'align' => 'R'],
            ['label' => 'I', 'w' => 3, 'align' => 'R'],
            ['label' => 'S', 'w' => 3, 'align' => 'R'],
            ['label' => 'Gaji Kotor', 'w' => 11, 'align' => 'R'],
            ['label' => 'Potongan', 'w' => 10, 'align' => 'R'],
            ['label' => 'Gaji Bersih', 'w' => 11, 'align' => 'R'],
        ];

        $rows = [];
        $i = 0;
        foreach ($payrolls as $p) {
            $rows[] = [
                (string) ++$i, $p->employee_number, $p->employee_name, $p->position_name,
                Format::rupiah($p->daily_salary), (string) $p->total_working_days,
                (string) $p->present_days, (string) $p->late_days, (string) $p->absent_days, (string) $p->leave_days, (string) $p->sick_days,
                Format::rupiah($p->gross_salary), Format::rupiah($p->total_deduction), Format::rupiah($p->net_salary),
            ];
        }
        $rows[] = ['bold' => true, 'cells' => [
            '', '', 'TOTAL ('.$payrolls->count().' karyawan)', '', '', (string) $totals['days'], '', '', '', '', '',
            Format::rupiah($totals['gross']), Format::rupiah($totals['deduction']), Format::rupiah($totals['net']),
        ]];

        $pdf->table($cols, $rows, 7.5);
        $pdf->moveDown(6);
        $pdf->paragraph('H = Hadir, T = Terlambat, A = Tidak Hadir, I = Izin, S = Sakit.', 7);

        return [$pdf->output($this->footer($company)), 'payroll-'.sprintf('%04d-%02d', $period->year, $period->month).'.pdf'];
    }

    /**
     * @param array<int, array{label:string, days:int, gross:float, deduction:float, net:float}> $groups
     * @return array{0:string,1:string}
     */
    public function slipPdf(PayrollPeriod $period, Payroll $payroll, array $groups): array
    {
        $company = Company::current();

        $pdf = new PdfWriter(false);
        $pdf->heading($company?->name ?? config('app.name'), 15);
        if ($company?->address) {
            $pdf->paragraph($company->address, 8);
        }
        $pdf->rule();
        $pdf->heading('SLIP GAJI', 13);
        if ($period->status === 'draft') {
            $pdf->paragraph('DRAFT - belum final, angka dapat berubah.', 9, true, self::RED);
        }
        $pdf->moveDown(4);

        $pdf->keyValue('Periode', Format::periodLabel($period->year, $period->month));
        $pdf->keyValue('Nama', $payroll->employee_name);
        $pdf->keyValue('NIK Internal', $payroll->employee_number);
        $pdf->keyValue('Jabatan', $payroll->position_name);
        $pdf->keyValue('Departemen', $payroll->department_name);
        $pdf->keyValue('Gaji harian', Format::rupiah($payroll->daily_salary));
        $pdf->keyValue('Status', Format::payrollStatus($payroll->status));
        $pdf->moveDown(10);

        $rows = [];
        foreach ($groups as $g) {
            $rows[] = [$g['label'], (string) $g['days'], Format::rupiah($g['gross']), Format::rupiah($g['deduction']), Format::rupiah($g['net'])];
        }
        $rows[] = ['bold' => true, 'cells' => [
            'TOTAL', (string) $payroll->total_working_days, Format::rupiah($payroll->gross_salary),
            Format::rupiah($payroll->total_deduction), Format::rupiah($payroll->net_salary),
        ]];

        $pdf->table([
            ['label' => 'Keterangan', 'w' => 6],
            ['label' => 'Hari', 'w' => 2, 'align' => 'R'],
            ['label' => 'Gaji Kotor', 'w' => 4, 'align' => 'R'],
            ['label' => 'Potongan', 'w' => 4, 'align' => 'R'],
            ['label' => 'Diterima', 'w' => 4, 'align' => 'R'],
        ], $rows, 9);

        $pdf->moveDown(12);
        $pdf->heading('Gaji bersih diterima: '.Format::rupiah($payroll->net_salary), 12);

        $pdf->ensureSpace(110);
        $pdf->moveDown(20);
        $y = $pdf->y();
        $pdf->text($pdf->margin, $y, 'Penerima', 9);
        $pdf->text($pdf->margin + $pdf->contentWidth() - 120, $y, 'HRD / Pembayar', 9);
        $pdf->moveDown(60);
        $y = $pdf->y();
        $pdf->line($pdf->margin, $y, $pdf->margin + 130, $y, 0.5, 0.4);
        $pdf->line($pdf->margin + $pdf->contentWidth() - 130, $y, $pdf->margin + $pdf->contentWidth(), $y, 0.5, 0.4);

        // Rincian harian di halaman berikutnya
        $pdf->addPage();
        $pdf->heading('Rincian Harian - '.$payroll->employee_name, 12);
        $pdf->paragraph('Periode '.Format::periodLabel($period->year, $period->month), 8);
        $pdf->moveDown(6);

        $detailRows = [];
        foreach ($payroll->details as $d) {
            $detailRows[] = [
                $d->work_date->format('d/m/Y'),
                $d->work_date->copy()->locale('id')->translatedFormat('l'),
                Format::dayStatus($d->status),
                Format::percent($d->deduction_percent),
                Format::rupiah($d->deduction_amount),
                Format::rupiah($d->net_amount),
                (string) ($d->notes ?? ''),
            ];
        }

        $pdf->table([
            ['label' => 'Tanggal', 'w' => 3],
            ['label' => 'Hari', 'w' => 3],
            ['label' => 'Status', 'w' => 3],
            ['label' => 'Potongan', 'w' => 2.4, 'align' => 'R'],
            ['label' => 'Nominal', 'w' => 3.2, 'align' => 'R'],
            ['label' => 'Diterima', 'w' => 3.2, 'align' => 'R'],
            ['label' => 'Catatan', 'w' => 7],
        ], $detailRows, 8);

        return [$pdf->output($this->footer($company)), 'slip-'.sprintf('%04d-%02d', $period->year, $period->month).'-'.\Illuminate\Support\Str::slug($payroll->employee_number).'.pdf'];
    }

    /* ------------------------------------------------------------------ */

    private function payrollTotals(Collection $payrolls): array
    {
        return [
            'days' => (int) $payrolls->sum('total_working_days'),
            'gross' => (float) $payrolls->sum(fn ($p) => (float) $p->gross_salary),
            'deduction' => (float) $payrolls->sum(fn ($p) => (float) $p->total_deduction),
            'net' => (float) $payrolls->sum(fn ($p) => (float) $p->net_salary),
        ];
    }

    private function attendanceFile(array $period, string $ext): string
    {
        return 'laporan-kehadiran-'.$period['from']->format('Ymd').'-'.$period['to']->format('Ymd').'.'.$ext;
    }

    private function footer(?Company $company): string
    {
        return ($company?->name ?? config('app.name')).' - dicetak '.now()->format('d/m/Y H:i');
    }
}
