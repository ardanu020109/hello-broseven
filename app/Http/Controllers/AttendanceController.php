<?php

namespace App\Http\Controllers;

use App\Exceptions\AttendanceException;
use App\Models\Company;
use App\Models\Employee;
use App\Services\AttendanceService;
use App\Services\FaceVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function __construct(
        private AttendanceService $attendance,
        private FaceVerificationService $faces,
    ) {
    }

    public function show(Request $request): View
    {
        $employee = $this->employee($request);
        $state = $this->attendance->stateFor($employee);
        $company = Company::current();
        $ws = $state['resolved']['schedule'] ?? null;
        $faceStatus = $this->faces->statusFor($employee);
        $canAct = in_array($state['phase'], ['ready_in', 'ready_out'], true);

        $config = [
            'canAct' => $canAct,
            'phase' => $state['phase'],
            'mode' => $state['mode'],
            'requiresFace' => $state['requires_face'],
            'faceReady' => !$state['requires_face'] || $faceStatus === 'active',
            'gpsEnabled' => $state['gps_enabled'],
            'requiresGps' => $state['requires_gps'],
            'office' => ($company && $company->hasCoordinates()) ? [
                'lat' => (float) $company->latitude,
                'lng' => (float) $company->longitude,
                'radius' => (int) $company->attendance_radius_meters,
                'maxAccuracy' => (int) $company->max_gps_accuracy_meters,
            ] : null,
            'serverNow' => (int) round(microtime(true) * 1000),
            'timezone' => $ws?->timezone ?: ($company?->timezone ?: config('app.timezone')),
            'endMs' => $state['end'] ? $state['end']->getTimestamp() * 1000 : null,
            'endTime' => $state['end']?->format('H:i'),
            'urls' => [
                'models' => asset('assets/models'),
                'verify' => route('employee.face.verify'),
                'checkIn' => route('employee.attendance.check-in'),
                'checkOut' => route('employee.attendance.check-out'),
            ],
        ];

        return view('attendance.employee', [
            'employee' => $employee,
            'state' => $state,
            'config' => $config,
            'faceStatus' => $faceStatus,
            'ws' => $ws,
            'record' => $state['record'],
            'todayLabel' => $state['now']->copy()->locale('id')->translatedFormat('l, d F Y'),
        ]);
    }

    public function checkIn(Request $request): JsonResponse
    {
        return $this->handle($request, 'in');
    }

    public function checkOut(Request $request): JsonResponse
    {
        return $this->handle($request, 'out');
    }

    private function handle(Request $request, string $kind): JsonResponse
    {
        $employee = $this->employee($request);

        $input = $request->validate([
            'face_ticket' => ['nullable', 'string', 'max:64'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'captured_at' => ['nullable', 'integer'],
        ], [], [
            'face_ticket' => 'tiket verifikasi wajah',
            'latitude' => 'latitude',
            'longitude' => 'longitude',
            'accuracy' => 'akurasi lokasi',
            'captured_at' => 'waktu lokasi',
        ]);

        try {
            $record = $kind === 'in'
                ? $this->attendance->checkIn($employee, $input, $request->ip(), $request->userAgent())
                : $this->attendance->checkOut($employee, $input, $request->ip(), $request->userAgent());
        } catch (AttendanceException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()] + $e->extra(), $e->status());
        }

        if ($kind === 'in') {
            $message = 'Absen masuk berhasil pukul '.$record->check_in_at->format('H:i').'. '
                .($record->status === 'late' ? "Status: Terlambat ({$record->late_minutes} menit)." : 'Status: Hadir tepat waktu.');
        } else {
            $message = 'Absen pulang berhasil pukul '.$record->check_out_at->format('H:i').'. Terima kasih atas kerja Anda hari ini.';
        }

        return response()->json(['ok' => true, 'message' => $message, 'status' => $record->status]);
    }

    private function employee(Request $request): Employee
    {
        $employee = $request->user()->employee;
        abort_if(!$employee || !$employee->is_active, 403, 'Akun Anda belum terhubung dengan data karyawan aktif. Hubungi administrator.');

        return $employee;
    }
}
