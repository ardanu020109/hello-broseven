<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use App\Services\AttendanceSummaryService;
use App\Services\PayrollService;
use App\Services\ReportExporter;
use App\Support\DateInput;
use App\Support\Format;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class PayrollController extends Controller
{
    public function __construct(
        private PayrollService $payroll,
        private ReportExporter $exporter,
        private AttendanceSummaryService $summary,
    ) {
    }

    public function index(Request $request): View
    {
        $periods = PayrollPeriod::query()
            ->withCount('payrolls')
            ->withSum('payrolls as net_total', 'net_salary')
            ->orderByDesc('year')->orderByDesc('month')
            ->paginate(12);

        $today = $this->summary->today();

        return view('payroll.index', [
            'periods' => $periods,
            'basis' => $this->payroll->basis(),
            'bases' => PayrollService::BASES,
            'maxMonth' => $today->format('Y-m'),
            'defaultMonth' => $today->copy()->subMonthNoOverflow()->format('Y-m'),
            'canConfigure' => $request->user()->isSuperAdmin(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['month' => ['required', 'string']], ['month.required' => 'Bulan periode wajib dipilih.']);

        $month = DateInput::month($request->input('month'));
        $max = $this->summary->today()->format('Y-m');

        if (!$month) {
            throw ValidationException::withMessages(['month' => 'Bulan periode tidak valid.']);
        }
        if ($month->format('Y-m') > $max) {
            throw ValidationException::withMessages(['month' => 'Periode di masa depan tidak dapat dibuat.']);
        }
        if ($month->year < 2020) {
            throw ValidationException::withMessages(['month' => 'Periode paling awal adalah Januari 2020.']);
        }
        if (PayrollPeriod::where('year', $month->year)->where('month', $month->month)->exists()) {
            throw ValidationException::withMessages(['month' => 'Payroll periode '.Format::periodLabel($month->year, $month->month).' sudah ada.']);
        }

        try {
            $period = $this->payroll->create($month->year, $month->month, $request->user());
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (UniqueConstraintViolationException) {
            return back()->withInput()->with('error', 'Payroll periode ini sudah ada.');
        }

        AuditLog::record('create_payroll', $period, null, ['year' => $period->year, 'month' => $period->month, 'basis' => $period->deduction_basis]);

        return redirect()->route('payroll.show', $period->id)
            ->with('success', 'Payroll '.Format::periodLabel($period->year, $period->month).' dibuat sebagai Draft.');
    }

    public function settings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'payroll_deduction_basis' => ['required', Rule::in(array_keys(PayrollService::BASES))],
        ], [
            'payroll_deduction_basis.required' => 'Dasar potongan wajib dipilih.',
            'payroll_deduction_basis.in' => 'Dasar potongan tidak valid.',
        ]);

        $company = Company::current();
        if (!$company) {
            return back()->with('error', 'Data perusahaan belum ada.');
        }

        $old = $company->payroll_deduction_basis;
        $company->update(['payroll_deduction_basis' => $data['payroll_deduction_basis']]);

        AuditLog::record('update_payroll_basis', $company, ['basis' => $old], ['basis' => $data['payroll_deduction_basis']]);

        return back()->with('success', 'Dasar potongan diubah. Berlaku untuk payroll baru dan hitung ulang Draft. Payroll yang sudah dibuat tetap memakai snapshot-nya.');
    }

    public function show(Request $request, string $id): View
    {
        $period = PayrollPeriod::with(['creator:id,name', 'finalizer:id,name'])->findOrFail($id);
        $term = trim((string) $request->query('q'));
        $like = '%'.addcslashes($term, '%_\\').'%';

        $payrolls = Payroll::query()
            ->where('payroll_period_id', $period->id)
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('employee_name', 'like', $like)
                ->orWhere('employee_number', 'like', $like)))
            ->orderBy('employee_name')
            ->paginate(15)
            ->withQueryString();

        $totals = Payroll::query()
            ->where('payroll_period_id', $period->id)
            ->selectRaw('COUNT(*) as employees, COALESCE(SUM(gross_salary),0) as gross, COALESCE(SUM(total_deduction),0) as deduction, COALESCE(SUM(net_salary),0) as net, COALESCE(SUM(unrecorded_days),0) as unrecorded')
            ->first();

        return view('payroll.show', [
            'period' => $period,
            'payrolls' => $payrolls,
            'totals' => $totals,
            'label' => Format::periodLabel($period->year, $period->month),
            'ended' => $period->end_date->toDateString() < $this->summary->today()->toDateString(),
            'rules' => $period->rules_snapshot['rules'] ?? [],
        ]);
    }

    public function recalculate(string $id): RedirectResponse
    {
        try {
            $period = $this->payroll->recalculate((int) $id);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLog::record('recalculate_payroll', $period, null, ['basis' => $period->deduction_basis]);

        return back()->with('success', 'Payroll dihitung ulang dengan data absensi dan aturan potongan terbaru.');
    }

    public function finalize(string $id): RedirectResponse
    {
        try {
            $result = $this->payroll->finalize((int) $id, auth()->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        $period = $result['period'];
        AuditLog::record('finalize_payroll', $period, ['net_total' => $result['before']], ['net_total' => $result['after']]);

        return back()->with('success', 'Payroll '.Format::periodLabel($period->year, $period->month).' difinalisasi. Total gaji bersih '
            .Format::rupiah($result['after']).' (draft sebelumnya '.Format::rupiah($result['before']).'). Absensi periode ini kini terkunci.');
    }

    public function paid(string $id): RedirectResponse
    {
        try {
            $period = $this->payroll->markPaid((int) $id);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLog::record('pay_payroll', $period, ['status' => 'finalized'], ['status' => 'paid']);

        return back()->with('success', 'Payroll ditandai Dibayar.');
    }

    public function destroy(string $id): RedirectResponse
    {
        try {
            $period = $this->payroll->delete((int) $id);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLog::record('delete_payroll', $period, ['year' => $period->year, 'month' => $period->month]);

        return redirect()->route('payroll.index')->with('success', 'Payroll Draft dihapus.');
    }

    public function employee(string $id, string $payrollId): View
    {
        $period = PayrollPeriod::findOrFail($id);
        $payroll = $this->findPayroll($period, $payrollId);

        return view('payroll.employee', [
            'period' => $period,
            'payroll' => $payroll,
            'groups' => $this->payroll->groups($payroll),
            'label' => Format::periodLabel($period->year, $period->month),
        ]);
    }

    public function slip(string $id, string $payrollId): View
    {
        $period = PayrollPeriod::findOrFail($id);
        $payroll = $this->findPayroll($period, $payrollId);

        AuditLog::record('view_payslip', $payroll);

        return view('payroll.slip', [
            'period' => $period,
            'payroll' => $payroll,
            'groups' => $this->payroll->groups($payroll),
            'label' => Format::periodLabel($period->year, $period->month),
            'company' => Company::current(),
        ]);
    }

    public function slipPdf(string $id, string $payrollId)
    {
        $period = PayrollPeriod::findOrFail($id);
        $payroll = $this->findPayroll($period, $payrollId);

        AuditLog::record('export_payslip', $payroll, null, ['format' => 'pdf']);

        [$content, $name] = $this->exporter->slipPdf($period, $payroll, $this->payroll->groups($payroll));

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$name.'"',
        ]);
    }

    public function export(string $id, string $format)
    {
        $period = PayrollPeriod::findOrFail($id);
        $payrolls = Payroll::where('payroll_period_id', $period->id)->orderBy('employee_name')->get();

        AuditLog::record('export_payroll', $period, null, ['format' => $format]);

        try {
            if ($format === 'xlsx') {
                [$path, $name] = $this->exporter->payrollXlsx($period, $payrolls);

                return response()->download($path, $name, [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ])->deleteFileAfterSend(true);
            }

            [$content, $name] = $this->exporter->payrollPdf($period, $payrolls);

            return response($content, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$name.'"',
            ]);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /** Payroll harus milik periode di URL, dan rincian diurutkan per tanggal. */
    private function findPayroll(PayrollPeriod $period, string $payrollId): Payroll
    {
        return Payroll::query()
            ->with(['details' => fn ($q) => $q->orderBy('work_date')])
            ->where('payroll_period_id', $period->id)
            ->findOrFail($payrollId);
    }
}
