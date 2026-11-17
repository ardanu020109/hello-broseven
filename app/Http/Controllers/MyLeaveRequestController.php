<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\LeaveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MyLeaveRequestController extends Controller
{
    private const MAX_RANGE_DAYS = 60;
    private const BACKDATE_DAYS = 7;

    public function __construct(private LeaveService $leaves)
    {
    }

    public function index(Request $request): View
    {
        $employee = $this->employee($request);
        $status = in_array($request->query('status'), ['pending', 'approved', 'rejected'], true) ? $request->query('status') : '';

        $requests = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->with(['leaveType', 'reviewer:id,name'])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return view('leave-requests.mine', [
            'requests' => $requests,
            'status' => $status,
            'counts' => LeaveRequest::where('employee_id', $employee->id)
                ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'types' => LeaveType::where('is_active', true)->orderBy('id')->get(['id', 'name', 'description', 'requires_attachment']),
            'minDate' => today()->subDays(self::BACKDATE_DAYS)->toDateString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $employee = $this->employee($request);

        $type = LeaveType::query()->where('is_active', true)->find($request->input('leave_type_id'));
        $minStart = today()->subDays(self::BACKDATE_DAYS)->toDateString();
        $maxStart = today()->addYear()->toDateString();

        $data = $request->validate([
            'leave_type_id' => ['required', Rule::exists('leave_types', 'id')->where('is_active', true)],
            'start_date' => ['required', 'date', 'after_or_equal:'.$minStart, 'before_or_equal:'.$maxStart],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'attachment' => [Rule::requiredIf($type?->requires_attachment === true), 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
        ], [
            'leave_type_id.required' => 'Jenis izin wajib dipilih.',
            'leave_type_id.exists' => 'Jenis izin tidak valid atau sudah nonaktif.',
            'start_date.required' => 'Tanggal mulai wajib diisi.',
            'start_date.date' => 'Tanggal mulai tidak valid.',
            'start_date.after_or_equal' => 'Tanggal mulai paling lama '.self::BACKDATE_DAYS.' hari ke belakang.',
            'start_date.before_or_equal' => 'Tanggal mulai maksimal 1 tahun ke depan.',
            'end_date.required' => 'Tanggal selesai wajib diisi.',
            'end_date.date' => 'Tanggal selesai tidak valid.',
            'end_date.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            'reason.required' => 'Alasan wajib diisi.',
            'reason.min' => 'Alasan minimal 5 karakter.',
            'reason.max' => 'Alasan maksimal 1000 karakter.',
            'attachment.required' => 'Jenis izin ini wajib melampirkan dokumen pendukung (mis. surat dokter).',
            'attachment.mimes' => 'Lampiran harus berupa PDF, JPG, atau PNG.',
            'attachment.max' => 'Ukuran lampiran maksimal 2 MB.',
            'attachment.uploaded' => 'Lampiran gagal diunggah. Ukuran berkas kemungkinan melebihi batas server.',
        ]);

        $start = Carbon::parse($data['start_date'])->startOfDay();
        $end = Carbon::parse($data['end_date'])->startOfDay();
        $errors = [];

        $days = (int) $start->diffInDays($end) + 1;
        if ($days > self::MAX_RANGE_DAYS) {
            $errors['end_date'] = 'Rentang izin maksimal '.self::MAX_RANGE_DAYS.' hari. Pecah menjadi beberapa pengajuan.';
        }
        if ($start->lt($employee->join_date->copy()->startOfDay())) {
            $errors['start_date'] = 'Tanggal mulai tidak boleh sebelum tanggal masuk kerja Anda ('.$employee->join_date->format('d/m/Y').').';
        }
        if (!$errors && $this->leaves->overlapExists($employee->id, $start->toDateString(), $end->toDateString())) {
            $errors['start_date'] = 'Anda sudah memiliki pengajuan izin (menunggu atau disetujui) yang bertabrakan dengan tanggal ini.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $path = $request->hasFile('attachment')
            ? $request->file('attachment')->store('leave-attachments', 'local')
            : null;

        try {
            $leave = LeaveRequest::create([
                'employee_id' => $employee->id,
                'leave_type_id' => $data['leave_type_id'],
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'total_days' => $days,
                'reason' => $data['reason'],
                'attachment' => $path,
                'status' => LeaveRequest::STATUS_PENDING,
            ]);
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $e;
        }

        AuditLog::record('submit_leave', $leave, null, $leave->only(['leave_type_id', 'start_date', 'end_date', 'total_days']));
        $this->leaves->notifySubmitted($leave, $employee);

        return redirect()->route('employee.leave-requests.index')
            ->with('success', 'Pengajuan izin terkirim dan menunggu persetujuan admin. Izin baru sah setelah disetujui.');
    }

    /** Batalkan pengajuan yang masih menunggu. */
    public function destroy(Request $request, string $id): RedirectResponse
    {
        $employee = $this->employee($request);
        $leave = LeaveRequest::where('employee_id', $employee->id)->findOrFail($id);

        if ($leave->status !== LeaveRequest::STATUS_PENDING) {
            return back()->with('error', 'Hanya pengajuan yang masih menunggu yang dapat dibatalkan.');
        }

        $old = $leave->only(['leave_type_id', 'start_date', 'end_date']);
        $path = $leave->attachment;
        $leave->delete();

        if ($path) {
            Storage::disk('local')->delete($path);
        }

        AuditLog::record('cancel_leave', $leave, $old);

        return back()->with('success', 'Pengajuan izin dibatalkan.');
    }

    public function attachment(Request $request, string $id): BinaryFileResponse
    {
        $employee = $this->employee($request);
        $leave = LeaveRequest::where('employee_id', $employee->id)->findOrFail($id); // hanya milik sendiri

        abort_if(!$leave->attachment || !Storage::disk('local')->exists($leave->attachment), 404);

        return response()->file(Storage::disk('local')->path($leave->attachment));
    }

    private function employee(Request $request): Employee
    {
        $employee = $request->user()->employee;
        abort_if(!$employee || !$employee->is_active, 403, 'Akun Anda belum terhubung dengan data karyawan aktif. Hubungi administrator.');

        return $employee;
    }
}
