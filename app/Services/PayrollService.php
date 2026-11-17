<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\PayrollPeriod;
use App\Models\SalaryDeductionRule;
use App\Models\User;
use App\Support\Format;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PayrollService
{
    public const BASES = [
        'daily_salary' => 'Persentase dari gaji harian',
        'fixed_amount' => 'Nominal tetap per kejadian',
    ];

    public function __construct(private AttendanceSummaryService $summary)
    {
    }

    public function basis(): string
    {
        $basis = Company::current()?->payroll_deduction_basis;

        return in_array($basis, array_keys(self::BASES), true) ? $basis : 'daily_salary';
    }

    /** @return array<string, array{name:string, percent:float, fixed:float}> */
    public function rules(): array
    {
        return SalaryDeductionRule::query()->orderBy('id')->get()
            ->mapWithKeys(fn (SalaryDeductionRule $r) => [$r->status_key => [
                'name' => $r->name,
                'percent' => (float) $r->deduction_percent,
                'fixed' => (float) $r->fixed_amount,
            ]])
            ->all();
    }

    /** @throws DomainException */
    public function create(int $year, int $month, User $user): PayrollPeriod
    {
        $start = Carbon::create($year, $month, 1)->startOfDay();

        return DB::transaction(function () use ($year, $month, $start, $user) {
            $period = PayrollPeriod::create([
                'year' => $year,
                'month' => $month,
                'start_date' => $start->toDateString(),
                'end_date' => $start->copy()->endOfMonth()->toDateString(),
                'status' => PayrollPeriod::STATUS_DRAFT,
                'deduction_basis' => $this->basis(),
                'rules_snapshot' => $this->snapshot(),
                'created_by' => $user->id,
            ]);

            if ($this->fill($period) === 0) {
                throw new DomainException('Tidak ada karyawan dengan hari kerja pada periode ini, sehingga payroll tidak dibuat. Pastikan jadwal sudah ditugaskan dan absensi tercatat.');
            }

            return $period;
        });
    }

    /** Hitung ulang periode Draft memakai aturan dan dasar potongan yang berlaku sekarang. */
    public function recalculate(int $id): PayrollPeriod
    {
        return DB::transaction(function () use ($id) {
            $period = PayrollPeriod::query()->whereKey($id)->lockForUpdate()->firstOrFail();
            $this->assertDraft($period);

            $period->payrolls()->delete(); // rincian ikut terhapus lewat foreign key cascade
            $period->update(['deduction_basis' => $this->basis(), 'rules_snapshot' => $this->snapshot()]);

            if ($this->fill($period->refresh()) === 0) {
                throw new DomainException('Tidak ada karyawan dengan hari kerja pada periode ini. Perhitungan ulang dibatalkan.');
            }

            return $period;
        });
    }

    /**
     * Finalisasi: data absensi dihitung ulang dengan aturan snapshot lalu periode dikunci.
     *
     * @return array{period: PayrollPeriod, before: float, after: float}
     */
    public function finalize(int $id, User $user): array
    {
        return DB::transaction(function () use ($id, $user) {
            $period = PayrollPeriod::query()->whereKey($id)->lockForUpdate()->firstOrFail();
            $this->assertDraft($period);

            if ($period->end_date->toDateString() >= $this->summary->today()->toDateString()) {
                throw new DomainException('Periode ini belum berakhir. Finalisasi hanya dapat dilakukan setelah hari terakhir periode lewat.');
            }

            $before = (float) Payroll::where('payroll_period_id', $period->id)->sum('net_salary');

            $period->payrolls()->delete();
            if ($this->fill($period->refresh()) === 0) {
                throw new DomainException('Tidak ada karyawan dengan hari kerja pada periode ini. Finalisasi dibatalkan.');
            }

            $period->update([
                'status' => PayrollPeriod::STATUS_FINALIZED,
                'finalized_by' => $user->id,
                'finalized_at' => now(),
            ]);
            Payroll::where('payroll_period_id', $period->id)->update(['status' => PayrollPeriod::STATUS_FINALIZED]);

            $after = (float) Payroll::where('payroll_period_id', $period->id)->sum('net_salary');

            return ['period' => $period, 'before' => $before, 'after' => $after];
        });
    }

    public function markPaid(int $id): PayrollPeriod
    {
        return DB::transaction(function () use ($id) {
            $period = PayrollPeriod::query()->whereKey($id)->lockForUpdate()->firstOrFail();

            if ($period->status !== PayrollPeriod::STATUS_FINALIZED) {
                throw new DomainException('Hanya periode berstatus Final yang dapat ditandai Dibayar.');
            }

            $now = now();
            $period->update(['status' => PayrollPeriod::STATUS_PAID, 'paid_at' => $now]);
            Payroll::where('payroll_period_id', $period->id)->update(['status' => PayrollPeriod::STATUS_PAID, 'paid_at' => $now]);

            return $period;
        });
    }

    public function delete(int $id): PayrollPeriod
    {
        return DB::transaction(function () use ($id) {
            $period = PayrollPeriod::query()->whereKey($id)->lockForUpdate()->firstOrFail();
            $this->assertDraft($period);
            $period->delete();

            return $period;
        });
    }

    /** Ringkasan per status untuk slip dan rincian karyawan. */
    public function groups(Payroll $payroll): array
    {
        $groups = [];

        foreach (['present', 'late', 'absent', 'leave', 'sick'] as $status) {
            $items = $payroll->details->where('status', $status);
            if ($items->isEmpty()) {
                continue;
            }

            $gross = (float) $items->sum(fn ($d) => (float) $d->daily_salary);
            $deduction = (float) $items->sum(fn ($d) => (float) $d->deduction_amount);

            $groups[] = [
                'status' => $status,
                'label' => Format::dayStatus($status),
                'days' => $items->count(),
                'gross' => $gross,
                'deduction' => $deduction,
                'net' => $gross - $deduction,
            ];
        }

        return $groups;
    }

    /* ------------------------------------------------------------------ */

    private function assertDraft(PayrollPeriod $period): void
    {
        if ($period->isLocked()) {
            throw new DomainException('Periode ini sudah ditutup (Final atau Dibayar) dan tidak dapat diubah.');
        }
    }

    private function snapshot(): array
    {
        return [
            'basis' => $this->basis(),
            'rules' => $this->rules(),
            'generated_at' => now()->toDateTimeString(),
        ];
    }

    /** @return int jumlah karyawan yang masuk payroll */
    private function fill(PayrollPeriod $period): int
    {
        $rules = $period->rules_snapshot['rules'] ?? [];
        $basis = $period->deduction_basis;
        $now = now()->toDateTimeString();

        $employees = Employee::withTrashed()
            ->with([
                'position' => fn ($q) => $q->withTrashed(),
                'department' => fn ($q) => $q->withTrashed(),
            ])
            ->whereDate('join_date', '<=', $period->end_date->toDateString())
            ->get();

        $summary = $this->summary->build($employees, $period->start_date, $period->end_date, true);
        $created = 0;

        foreach ($employees as $emp) {
            $res = $summary[$emp->id];
            $c = $res['counts'];
            if ($c['total'] === 0) {
                continue;
            }

            $daily = (int) round(((float) ($emp->position?->daily_salary ?? 0)) * 100);
            $rows = [];
            $deductSum = 0;

            foreach ($res['days'] as $day) {
                $rule = $rules[$day['rule_key']] ?? ['percent' => 0, 'fixed' => 0];
                $deduct = $this->deduction($daily, $rule, $basis);
                $effective = $basis === 'fixed_amount'
                    ? ($daily > 0 ? round($deduct / $daily * 100, 2) : 0)
                    : round((float) $rule['percent'], 2);

                $rows[] = [
                    'attendance_record_id' => $day['record_id'],
                    'work_date' => $day['date'],
                    'status' => $day['status'],
                    'daily_salary' => $this->money($daily),
                    'deduction_percent' => $effective,
                    'deduction_amount' => $this->money($deduct),
                    'net_amount' => $this->money($daily - $deduct),
                    'notes' => $this->note($day),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $deductSum += $deduct;
            }

            $gross = $daily * $c['total'];

            $payroll = Payroll::create([
                'payroll_period_id' => $period->id,
                'employee_id' => $emp->id,
                'employee_number' => $emp->employee_number,
                'employee_name' => $emp->full_name,
                'position_name' => $emp->position?->name ?? '-',
                'department_name' => $emp->department?->name ?? '-',
                'daily_salary' => $this->money($daily),
                'total_working_days' => $c['total'],
                'present_days' => $c['present'],
                'late_days' => $c['late'],
                'absent_days' => $c['absent'],
                'leave_days' => $c['leave'],
                'sick_days' => $c['sick'],
                'unrecorded_days' => $c['unrecorded'],
                'gross_salary' => $this->money($gross),
                'total_deduction' => $this->money($deductSum),
                'net_salary' => $this->money($gross - $deductSum),
                'status' => PayrollPeriod::STATUS_DRAFT,
            ]);

            foreach (array_chunk($rows, 300) as $chunk) {
                PayrollDetail::insert(array_map(fn ($r) => $r + ['payroll_id' => $payroll->id], $chunk));
            }

            $created++;
        }

        return $created;
    }

    /** Potongan satu hari dalam sen, tidak pernah melebihi gaji harian. */
    private function deduction(int $dailyCents, array $rule, string $basis): int
    {
        if ($basis === 'fixed_amount') {
            return min($dailyCents, (int) round(((float) $rule['fixed']) * 100));
        }

        $basisPoints = (int) round(((float) $rule['percent']) * 100); // 10000 = 100%

        return min($dailyCents, intdiv($dailyCents * $basisPoints + 5000, 10000));
    }

    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    private function note(array $day): ?string
    {
        if ($day['synthetic']) {
            return $day['status'] === 'absent'
                ? 'Tidak ada catatan absensi: dihitung Tidak Hadir.'
                : 'Izin disetujui tanpa catatan absensi.';
        }
        if ($day['status'] === 'late' && $day['late_minutes'] > 0) {
            return 'Terlambat '.$day['late_minutes'].' menit.';
        }
        if (in_array($day['status'], ['leave', 'sick'], true) && $day['leave_type']) {
            return mb_substr($day['leave_type'], 0, 255);
        }

        return null;
    }
}
