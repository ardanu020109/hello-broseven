<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\LeaveService;
use App\Support\DateInput;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LeaveRequestController extends Controller
{
    public function __construct(private LeaveService $leaves)
    {
    }

    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q'));
        $status = in_array($request->query('status'), ['pending', 'approved', 'rejected'], true) ? $request->query('status') : '';
        $from = DateInput::date($request->query('from'));
        $to = DateInput::date($request->query('to'));

        $requests = LeaveRequest::query()
            ->with(['employee' => fn ($q) => $q->withTrashed()->with('position'), 'leaveType', 'reviewer:id,name'])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($request->filled('leave_type_id'), fn ($q) => $q->where('leave_type_id', $request->integer('leave_type_id')))
            ->when($term !== '', fn ($q) => $q->whereHas('employee', fn ($e) => $e->withTrashed()->search($term)))
            // Rentang filter: tampilkan pengajuan yang periodenya beririsan
            ->when($from, fn ($q) => $q->whereDate('end_date', '>=', $from->toDateString()))
            ->when($to, fn ($q) => $q->whereDate('start_date', '<=', $to->toDateString()))
            ->orderByRaw("FIELD(status, 'pending', 'approved', 'rejected')")
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        return view('leave-requests.index', [
            'requests' => $requests,
            'status' => $status,
            'counts' => LeaveRequest::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'types' => LeaveType::orderBy('id')->get(['id', 'name']),
            'filtered' => $request->filled('q') || $status !== '' || $request->filled('leave_type_id') || $from || $to,
        ]);
    }

    public function show(string $id): View
    {
        $leave = LeaveRequest::with([
            'employee' => fn ($q) => $q->withTrashed()->with(['position', 'department']),
            'leaveType.salaryDeductionRule', 'reviewer:id,name',
        ])->findOrFail($id);

        $audits = AuditLog::query()
            ->where('auditable_type', $leave->getMorphClass())
            ->where('auditable_id', $leave->id)
            ->with('user:id,name')
            ->latest('id')
            ->get();

        $existing = AttendanceRecord::query()
            ->where('employee_id', $leave->employee_id)
            ->whereBetween('attendance_date', [$leave->start_date->toDateString(), $leave->end_date->toDateString()])
            ->whereIn('status', [AttendanceRecord::STATUS_PRESENT, AttendanceRecord::STATUS_LATE])
            ->count();

        return view('leave-requests.show', [
            'leave' => $leave,
            'audits' => $audits,
            'existingAttendance' => $existing,
        ]);
    }

    public function approve(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:500'],
        ], ['admin_note.max' => 'Catatan maksimal 500 karakter.']);

        try {
            $result = $this->leaves->approve((int) $id, $request->user(), $data['admin_note'] ?? null);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        $detail = "Catatan absensi: {$result['created']} dibuat, {$result['converted']} diubah dari Tidak Hadir";
        if ($result['kept'] > 0) {
            $detail .= ", {$result['kept']} tidak diubah (sudah hadir/terlambat)";
        }
        if ($result['locked'] > 0) {
            $detail .= ", {$result['locked']} dilewati (periode payroll ditutup)";
        }

        return redirect()->route('leave-requests.show', $id)->with('success', 'Pengajuan izin disetujui. '.$detail.'.');
    }

    public function reject(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate([
            'admin_note' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'admin_note.required' => 'Catatan wajib diisi untuk menolak pengajuan.',
            'admin_note.min' => 'Catatan penolakan minimal 5 karakter.',
            'admin_note.max' => 'Catatan maksimal 500 karakter.',
        ]);

        try {
            $this->leaves->reject((int) $id, $request->user(), $data['admin_note']);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('leave-requests.show', $id)->with('success', 'Pengajuan izin ditolak.');
    }

    /** Lampiran izin: private, hanya admin (atau pemilik lewat route karyawan). */
    public function attachment(string $id): BinaryFileResponse
    {
        $leave = LeaveRequest::findOrFail($id);
        abort_if(!$leave->attachment || !Storage::disk('local')->exists($leave->attachment), 404);

        AuditLog::record('view_leave_attachment', $leave);

        return response()->file(Storage::disk('local')->path($leave->attachment));
    }
}
