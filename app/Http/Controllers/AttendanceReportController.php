<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Services\AttendanceReportService;
use App\Services\ReportExporter;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\View\View;
use RuntimeException;

class AttendanceReportController extends Controller
{
    public function __construct(
        private AttendanceReportService $reports,
        private ReportExporter $exporter,
    ) {
    }

    public function index(Request $request): View
    {
        $period = $this->reports->period($request);
        $filters = $this->reports->filters($request);
        $data = $this->reports->build($period['from'], $period['to'], $filters, false);

        $page = Paginator::resolveCurrentPage();
        $perPage = 15;
        $rows = (new LengthAwarePaginator(
            $data['rows']->forPage($page, $perPage)->values(),
            $data['rows']->count(),
            $perPage,
            $page,
            ['path' => $request->url()]
        ))->withQueryString();


        return view('reports.attendance', [
            'period' => $period,
            'data' => $data,
            'rows' => $rows,
            'employees' => Employee::orderBy('full_name')->get(['id', 'full_name', 'employee_number']),
            'positions' => Position::orderBy('name')->get(['id', 'name']),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'filtered' => $request->hasAny(['mode', 'month', 'year', 'from', 'to', 'employee_id', 'position_id', 'department_id']),
        ]);
    }

    public function detail(Request $request, string $id): View
    {
        $employee = Employee::withTrashed()
            ->with([
                'position' => fn ($q) => $q->withTrashed(),
                'department' => fn ($q) => $q->withTrashed(),
            ])
            ->findOrFail($id);

        $period = $this->reports->period($request);
        $result = $this->reports->employeeDetail($employee, $period['from'], $period['to']);

        return view('reports.detail', [
            'employee' => $employee,
            'period' => $period,
            'result' => $result,
            'back' => route('reports.attendance', $request->except('page')),
        ]);
    }

    public function print(Request $request): View
    {
        $period = $this->reports->period($request);
        $filters = $this->reports->filters($request);

        AuditLog::record('print_attendance_report', null, null, ['period' => $period['label']] + $filters);

        return view('reports.print', [
            'period' => $period,
            'data' => $this->reports->build($period['from'], $period['to'], $filters, false),
            'filterText' => $this->reports->describe($filters),
        ]);
    }

    public function export(Request $request, string $format)
    {
        $period = $this->reports->period($request);
        $filters = $this->reports->filters($request);

        // Rincian harian hanya disertakan di Excel untuk periode sampai 62 hari agar berkas tetap ringan
        $withDays = $format === 'xlsx' && $period['from']->diffInDays($period['to']) <= 62;
        $data = $this->reports->build($period['from'], $period['to'], $filters, $withDays);
        $filterText = $this->reports->describe($filters);

        AuditLog::record('export_attendance_report', null, null, ['format' => $format, 'period' => $period['label']] + $filters);

        try {
            if ($format === 'xlsx') {
                [$path, $name] = $this->exporter->attendanceXlsx($period, $filterText, $data, $withDays);

                return response()->download($path, $name, [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ])->deleteFileAfterSend(true);
            }

            [$content, $name] = $this->exporter->attendancePdf($period, $filterText, $data);

            return response($content, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$name.'"',
            ]);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
