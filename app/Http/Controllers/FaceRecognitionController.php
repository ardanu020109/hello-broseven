<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\EmployeeFaceDescriptor;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\FaceVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class FaceRecognitionController extends Controller
{
    private const VERIFY_MAX_FAILS = 8;
    private const VERIFY_DECAY_SECONDS = 300;
    private const SAMPLES = 5;

    public function __construct(
        private FaceVerificationService $faces,
        private AttendanceService $attendance,
    ) {
    }

    /* ===================== Karyawan ===================== */

    public function register(Request $request): View
    {
        $employee = $this->employee($request);

        $records = $employee->faceDescriptors()
            ->select('id', 'employee_id', 'status', 'registered_at', 'approved_at')
            ->orderByDesc('id')
            ->get();

        return view('attendance.face-register', [
            'employee' => $employee,
            'faceStatus' => $this->faces->statusFor($employee),
            'active' => $records->firstWhere('status', 'active'),
            'pending' => $records->firstWhere('status', 'pending_approval'),
            'config' => [
                'samples' => self::SAMPLES,
                'needsApproval' => $records->isNotEmpty(),
                'urls' => [
                    'models' => asset('assets/models'),
                    'store' => route('employee.face.store'),
                    'back' => route('employee.attendance'),
                ],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $employee = $this->employee($request);

        $invalid = 'Data sampel wajah tidak valid. Ulangi pendaftaran.';

        $data = $request->validate([
            'consent' => ['required', 'accepted'],
            'samples' => ['required', 'array', 'min:3', 'max:'.self::SAMPLES],
            'samples.*' => ['required', 'array', 'size:128'],
            'samples.*.*' => ['required', 'numeric', 'between:-5,5'],
        ], [
            'consent.required' => 'Anda harus menyetujui penggunaan data biometrik.',
            'consent.accepted' => 'Anda harus menyetujui penggunaan data biometrik.',
            'samples.required' => 'Sampel wajah belum diambil.',
            'samples.array' => $invalid,
            'samples.min' => 'Sampel wajah kurang. Ulangi pendaftaran.',
            'samples.max' => $invalid,
            'samples.*.required' => $invalid,
            'samples.*.array' => $invalid,
            'samples.*.size' => $invalid,
            'samples.*.*.required' => $invalid,
            'samples.*.*.numeric' => $invalid,
            'samples.*.*.between' => $invalid,
        ]);

        $samples = array_map(
            fn (array $sample) => array_map('floatval', array_values($sample)),
            array_values($data['samples'])
        );

        foreach ($samples as $sample) {
            if (array_sum(array_map('abs', $sample)) < 1.0) {
                return response()->json(['ok' => false, 'message' => $invalid], 422);
            }
        }

        if (!$this->faces->samplesAreConsistent($samples)) {
            return response()->json([
                'ok' => false,
                'message' => 'Sampel wajah tidak konsisten. Ulangi dengan pencahayaan yang baik dan pastikan hanya Anda yang berada di depan kamera.',
            ], 422);
        }

        // Pertama kali: langsung aktif. Pernah punya data wajah (aktif, nonaktif, atau menunggu): perlu persetujuan admin.
        $needsApproval = $employee->faceDescriptors()->exists();
        $status = $needsApproval ? 'pending_approval' : 'active';

        $record = DB::transaction(function () use ($employee, $samples, $status) {
            // Pengajuan lama yang belum diputuskan digantikan pengajuan baru.
            $employee->faceDescriptors()->where('status', 'pending_approval')->delete();

            return $employee->faceDescriptors()->create([
                'descriptor' => $samples, // terenkripsi lewat cast model
                'status' => $status,
                'consent_at' => now(),
                'registered_at' => now(),
            ]);
        });

        AuditLog::record('register_face', $record, null, ['status' => $status]);

        if ($needsApproval) {
            $this->notifyAdmins($employee);
        }

        return response()->json([
            'ok' => true,
            'status' => $status,
            'message' => $needsApproval
                ? 'Pengajuan registrasi ulang wajah terkirim. Data wajah lama tetap dipakai sampai admin menyetujui.'
                : 'Wajah berhasil didaftarkan. Anda sudah dapat absen dengan verifikasi wajah.',
        ]);
    }

    /** Mencocokkan descriptor langsung dengan data terdaftar. Hasilnya tiket sekali pakai, bukan skor. */
    public function verify(Request $request): JsonResponse
    {
        $employee = $this->employee($request);

        $invalid = 'Data wajah tidak valid. Ulangi verifikasi.';

        $data = $request->validate([
            'descriptor' => ['required', 'array', 'size:128'],
            'descriptor.*' => ['required', 'numeric', 'between:-5,5'],
        ], [
            'descriptor.required' => $invalid,
            'descriptor.array' => $invalid,
            'descriptor.size' => $invalid,
            'descriptor.*.required' => $invalid,
            'descriptor.*.numeric' => $invalid,
            'descriptor.*.between' => $invalid,
        ]);

        // Biometrik hanya diproses ketika karyawan memang boleh absen sekarang.
        $state = $this->attendance->stateFor($employee);
        if (!in_array($state['phase'], ['ready_in', 'ready_out'], true)) {
            return response()->json(['ok' => false, 'matched' => false, 'message' => $state['message']], 422);
        }

        $record = $this->faces->activeDescriptor($employee);
        if (!$record) {
            return response()->json([
                'ok' => false,
                'matched' => false,
                'needs_registration' => true,
                'message' => 'Wajah Anda belum terdaftar atau belum disetujui admin.',
            ], 409);
        }

        $failKey = 'face-fail:'.$employee->id;
        if (RateLimiter::tooManyAttempts($failKey, self::VERIFY_MAX_FAILS)) {
            $seconds = RateLimiter::availableIn($failKey);

            return response()->json([
                'ok' => false,
                'matched' => false,
                'stop' => true,
                'message' => "Terlalu banyak verifikasi wajah yang gagal. Coba lagi dalam {$seconds} detik atau hubungi administrator.",
            ], 429);
        }

        $result = $this->faces->match($record, array_map('floatval', array_values($data['descriptor'])));

        if (!$result['matched']) {
            RateLimiter::hit($failKey, self::VERIFY_DECAY_SECONDS);
            AuditLog::record('face_verify_failed', $employee, null, ['distance' => round($result['distance'], 3)]);

            // Jarak dan ambang tidak dikirim ke klien agar tidak bisa dipakai menebak.
            return response()->json([
                'ok' => false,
                'matched' => false,
                'message' => 'Wajah tidak dikenali. Pastikan Anda sendiri yang berada di depan kamera dengan pencahayaan cukup.',
            ], 422);
        }

        RateLimiter::clear($failKey);

        return response()->json([
            'ok' => true,
            'matched' => true,
            'ticket' => $this->faces->issueTicket($employee, $result['score']),
            'expires_in' => FaceVerificationService::TICKET_TTL,
            'name' => $employee->full_name,
            'message' => 'Wajah berhasil dikenali.',
        ]);
    }

    /* ===================== Admin ===================== */

    public function manage(Request $request): View
    {
        $term = trim((string) $request->query('q'));
        $state = in_array($request->query('state'), ['pending', 'active', 'inactive', 'none'], true)
            ? $request->query('state')
            : '';

        $employees = Employee::query()
            ->active()
            ->with([
                'position:id,name',
                'department:id,name',
                // Kolom descriptor sengaja tidak dimuat.
                'faceDescriptors' => fn ($q) => $q
                    ->select('id', 'employee_id', 'status', 'consent_at', 'registered_at', 'approved_at', 'deactivated_at')
                    ->orderByDesc('id'),
            ])
            ->search($term)
            ->when($state === 'pending', fn ($q) => $q->whereHas('faceDescriptors', fn ($f) => $f->where('status', 'pending_approval')))
            ->when($state === 'active', fn ($q) => $q->whereHas('faceDescriptors', fn ($f) => $f->where('status', 'active')))
            ->when($state === 'inactive', fn ($q) => $q
                ->whereHas('faceDescriptors', fn ($f) => $f->where('status', 'inactive'))
                ->whereDoesntHave('faceDescriptors', fn ($f) => $f->where('status', 'active')))
            ->when($state === 'none', fn ($q) => $q->whereDoesntHave('faceDescriptors'))
            ->orderBy('full_name')
            ->paginate(10)
            ->withQueryString();

        $totalActive = Employee::active()->count();
        $registered = Employee::active()
            ->whereHas('faceDescriptors', fn ($f) => $f->where('status', 'active'))
            ->count();

        return view('attendance.faces', [
            'employees' => $employees,
            'state' => $state,
            'summary' => [
                'total' => $totalActive,
                'registered' => $registered,
                'pending' => EmployeeFaceDescriptor::where('status', 'pending_approval')->count(),
                'unregistered' => $totalActive - $registered,
            ],
        ]);
    }

    public function approve(string $id): RedirectResponse
    {
        $record = EmployeeFaceDescriptor::with('employee')->where('status', 'pending_approval')->findOrFail($id);

        DB::transaction(function () use ($record) {
            EmployeeFaceDescriptor::query()
                ->where('employee_id', $record->employee_id)
                ->where('status', 'active')
                ->update(['status' => 'inactive', 'deactivated_at' => now(), 'deactivated_by' => auth()->id()]);

            $record->update(['status' => 'active', 'approved_by' => auth()->id(), 'approved_at' => now()]);
        });

        AuditLog::record('approve_face', $record, ['status' => 'pending_approval'], ['status' => 'active']);
        $this->notifyEmployee($record->employee, 'Registrasi ulang wajah disetujui', 'Data wajah baru Anda sudah aktif dan dapat dipakai untuk absensi.');

        return back()->with('success', "Registrasi wajah {$record->employee?->full_name} disetujui.");
    }

    public function reject(string $id): RedirectResponse
    {
        $record = EmployeeFaceDescriptor::with('employee')->where('status', 'pending_approval')->findOrFail($id);
        $employee = $record->employee;

        // Data biometrik yang ditolak langsung dihapus, bukan disimpan.
        AuditLog::record('reject_face', $record, ['status' => 'pending_approval'], null);
        $record->delete();

        $this->notifyEmployee($employee, 'Registrasi ulang wajah ditolak', 'Pengajuan registrasi ulang wajah Anda ditolak. Hubungi administrator atau ajukan kembali.');

        return back()->with('success', "Pengajuan wajah {$employee?->full_name} ditolak dan datanya dihapus.");
    }

    public function deactivate(string $id): RedirectResponse
    {
        $record = EmployeeFaceDescriptor::with('employee')->where('status', 'active')->findOrFail($id);

        $record->update(['status' => 'inactive', 'deactivated_at' => now(), 'deactivated_by' => auth()->id()]);

        AuditLog::record('deactivate_face', $record, ['status' => 'active'], ['status' => 'inactive']);
        $this->notifyEmployee($record->employee, 'Data wajah dinonaktifkan', 'Data wajah Anda dinonaktifkan administrator. Daftarkan ulang wajah untuk dapat absen kembali.');

        return back()->with('success', "Data wajah {$record->employee?->full_name} dinonaktifkan. Karyawan harus mendaftar ulang.");
    }

    /* ------------------------------------------------------------------ */

    private function employee(Request $request): Employee
    {
        $employee = $request->user()->employee;
        abort_if(!$employee || !$employee->is_active, 403, 'Akun Anda belum terhubung dengan data karyawan aktif. Hubungi administrator.');

        return $employee;
    }

    private function notifyAdmins(Employee $employee): void
    {
        User::query()
            ->whereIn('role', [User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN])
            ->where('is_active', true)
            ->get(['id'])
            ->each(fn (User $admin) => AppNotification::create([
                'user_id' => $admin->id,
                'type' => 'face',
                'title' => 'Registrasi ulang wajah menunggu persetujuan',
                'message' => "{$employee->full_name} mengajukan registrasi ulang wajah.",
                'url' => route('faces.index', ['state' => 'pending']),
            ]));
    }

    private function notifyEmployee(?Employee $employee, string $title, string $message): void
    {
        if (!$employee?->user_id) {
            return;
        }

        AppNotification::create([
            'user_id' => $employee->user_id,
            'type' => 'face',
            'title' => $title,
            'message' => $message,
            'url' => route('employee.face'),
        ]);
    }
}
