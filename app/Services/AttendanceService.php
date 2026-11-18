<?php

namespace App\Services;

use App\Exceptions\AttendanceException;
use App\Models\AttendanceLocation;
use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\FeatureSetting;
use App\Models\LeaveRequest;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    public const EARLY_WINDOW_MINUTES = 120;      // absen masuk dibuka sekian menit sebelum jam masuk
    public const LOCATION_MAX_AGE_SECONDS = 300;  // umur maksimum data lokasi dari perangkat
    public const MIN_STAY_SECONDS = 60;           // jeda minimum antara absen masuk dan pulang

    public function __construct(
        private ScheduleService $schedules,
        private GeoService $geo,
        private FaceVerificationService $faces,
    ) {
    }

    /**
     * Keadaan absensi karyawan saat ini, ditentukan sepenuhnya di server.
     * phase: inactive | no_schedule | holiday | off_day | leave | absent | too_early |
     *        ready_in | ready_out | done | closed
     */
    public function stateFor(Employee $employee): array
    {
        $tz = Company::current()?->timezone ?: config('app.timezone');
        $now = Carbon::now($tz);
        $resolved = $this->schedules->resolveFor($employee, $now->copy()->startOfDay());

        $scheduleTz = $resolved['schedule']->timezone ?? null;
        if ($resolved && $scheduleTz && $scheduleTz !== $tz) {
            $now = Carbon::now($scheduleTz);
            $resolved = $this->schedules->resolveFor($employee, $now->copy()->startOfDay());
        }

        $mode = $resolved['work_mode'] ?? null;
        $gpsEnabled = FeatureSetting::isEnabled('gps_verification');

        $state = [
            'now' => $now,
            'date' => $now->toDateString(),
            'resolved' => $resolved,
            'record' => null,
            'start' => null,
            'end' => null,
            'mode' => $mode,
            'requires_face' => FeatureSetting::isEnabled('face_recognition'),
            'gps_enabled' => $gpsEnabled,
            // Radius kantor hanya diwajibkan untuk hari kerja bermode WFO.
            'requires_gps' => $gpsEnabled && $mode === Employee::MODE_WFO,
            'phase' => 'no_schedule',
            'message' => '',
        ];

        if (!$employee->is_active) {
            return $this->finish($state, 'inactive', 'Akun karyawan Anda tidak aktif.');
        }
        if (!$resolved) {
            return $this->finish($state, 'no_schedule', 'Anda belum memiliki jadwal kerja yang berlaku hari ini. Hubungi administrator.');
        }
        if ($resolved['holiday_name']) {
            return $this->finish($state, 'holiday', 'Hari ini libur: '.$resolved['holiday_name'].'. Absensi tidak diperlukan.');
        }
        if (!$resolved['is_working_day']) {
            return $this->finish($state, 'off_day', 'Hari ini bukan hari kerja pada jadwal Anda. Absensi tidak diperlukan.');
        }

        $ws = $resolved['schedule'];
        $state['start'] = Carbon::parse($state['date'].' '.$ws->check_in_time, $ws->timezone);
        $state['end'] = Carbon::parse($state['date'].' '.$ws->check_out_time, $ws->timezone);

        $record = AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->whereDate('attendance_date', $state['date'])
            ->first();
        $state['record'] = $record;

        if ($record) {
            if ($record->status === AttendanceRecord::STATUS_LEAVE) {
                return $this->finish($state, 'leave', 'Anda memiliki izin yang disetujui hari ini.');
            }
            if ($record->status === AttendanceRecord::STATUS_ABSENT) {
                return $this->finish($state, 'absent', 'Anda ditandai tidak hadir hari ini. Hubungi administrator untuk koreksi.');
            }
            if (!$record->check_in_at) {
                return $this->finish($state, 'done', 'Kehadiran Anda hari ini sudah tercatat.');
            }
            if (!$record->check_out_at) {
                return $this->finish($state, 'ready_out', 'Anda sudah absen masuk pukul '.$record->check_in_at->format('H:i').'. Lakukan absen pulang saat selesai bekerja.');
            }

            return $this->finish($state, 'done', 'Absensi hari ini selesai. Masuk '.$record->check_in_at->format('H:i').', pulang '.$record->check_out_at->format('H:i').'.');
        }

        $onLeave = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->whereDate('start_date', '<=', $state['date'])
            ->whereDate('end_date', '>=', $state['date'])
            ->exists();
        if ($onLeave) {
            return $this->finish($state, 'leave', 'Anda memiliki izin yang disetujui hari ini.');
        }

        $opensAt = $state['start']->copy()->subMinutes(self::EARLY_WINDOW_MINUTES);
        if ($now->lt($opensAt)) {
            return $this->finish($state, 'too_early', 'Absensi dibuka mulai pukul '.$opensAt->format('H:i').'.');
        }
        if ($now->gte($state['end'])) {
            return $this->finish($state, 'closed', 'Jam kerja hari ini sudah berakhir pukul '.$state['end']->format('H:i').'. Hubungi administrator jika Anda perlu koreksi absensi.');
        }

        return $this->finish(
            $state,
            'ready_in',
            'Siap absen masuk. Jam masuk '.$state['start']->format('H:i').', toleransi '.(int) $ws->late_tolerance_minutes.' menit.'
        );
    }

    public function checkIn(Employee $employee, array $input, ?string $ip, ?string $userAgent): AttendanceRecord
    {
        $state = $this->stateFor($employee);
        if ($state['phase'] !== 'ready_in') {
            throw new AttendanceException($state['message'], 422, ['phase' => $state['phase']]);
        }

        // Lokasi dicek dulu agar tiket wajah tidak terpakai sia-sia jika lokasi ditolak.
        $location = $this->evaluateLocation($input, $state);
        $faceScore = $this->consumeFace($employee, $state, $input);

        $ws = $state['resolved']['schedule'];
        $now = $state['now'];
        $graceEnd = $state['start']->copy()->addMinutes((int) $ws->late_tolerance_minutes);
        $isLate = $now->copy()->startOfMinute()->gt($graceEnd);
        $lateMinutes = $isLate ? intdiv(max(0, $now->timestamp - $state['start']->timestamp), 60) : 0;
        $storedNow = $now->copy()->setTimezone(config('app.timezone'));
        $method = $this->method($faceScore, $location);

        try {
            $record = DB::transaction(function () use ($employee, $state, $ws, $storedNow, $isLate, $lateMinutes, $method, $faceScore, $location, $ip, $userAgent) {
                $record = AttendanceRecord::create([
                    'employee_id' => $employee->id,
                    'work_schedule_id' => $ws->id,
                    'attendance_date' => $state['date'],
                    'check_in_at' => $storedNow,
                    'status' => $isLate ? AttendanceRecord::STATUS_LATE : AttendanceRecord::STATUS_PRESENT,
                    'work_mode' => $state['mode'],
                    'late_minutes' => $lateMinutes,
                    'verification_method' => $method,
                    'face_match_score' => $faceScore,
                ]);

                if ($location) {
                    $this->storeLocation($record, 'check_in', $location, $ip, $userAgent);
                }

                return $record;
            });
        } catch (UniqueConstraintViolationException) {
            throw new AttendanceException('Absen masuk Anda hari ini sudah tercatat.', 409);
        }

        AuditLog::record('check_in', $record, null, [
            'status' => $record->status,
            'mode' => $state['mode'],
            'late_minutes' => $lateMinutes,
            'method' => $method,
            'distance_m' => isset($location['distance']) ? round($location['distance'], 1) : null,
        ]);

        return $record;
    }

    public function checkOut(Employee $employee, array $input, ?string $ip, ?string $userAgent): AttendanceRecord
    {
        $state = $this->stateFor($employee);
        if ($state['phase'] !== 'ready_out') {
            throw new AttendanceException($state['message'], 422, ['phase' => $state['phase']]);
        }

        $now = $state['now'];
        if ($now->timestamp - $state['record']->check_in_at->timestamp < self::MIN_STAY_SECONDS) {
            throw new AttendanceException('Absen pulang terlalu cepat setelah absen masuk. Tunggu sebentar.', 422);
        }

        $location = $this->evaluateLocation($input, $state);
        $faceScore = $this->consumeFace($employee, $state, $input);

        $storedNow = $now->copy()->setTimezone(config('app.timezone'));
        $method = $this->method($faceScore, $location);
        $early = $now->lt($state['end']);

        $record = DB::transaction(function () use ($state, $storedNow, $method, $faceScore, $location, $ip, $userAgent) {
            $record = AttendanceRecord::query()->whereKey($state['record']->id)->lockForUpdate()->firstOrFail();

            if ($record->check_out_at) {
                throw new AttendanceException('Absen pulang Anda hari ini sudah tercatat.', 409);
            }

            $record->update([
                'check_out_at' => $storedNow,
                'check_out_method' => $method,
                'check_out_face_score' => $faceScore,
            ]);

            if ($location) {
                $this->storeLocation($record, 'check_out', $location, $ip, $userAgent);
            }

            return $record;
        });

        AuditLog::record('check_out', $record, null, [
            'method' => $method,
            'early' => $early,
            'distance_m' => isset($location['distance']) ? round($location['distance'], 1) : null,
        ]);

        return $record;
    }

    /* ------------------------------------------------------------------ */

    private function finish(array $state, string $phase, string $message): array
    {
        $state['phase'] = $phase;
        $state['message'] = $message;

        return $state;
    }

    /**
     * Validasi lokasi. Wajib dan ditegakkan hanya untuk hari kerja WFO dengan fitur GPS aktif.
     * Untuk WFH (atau GPS dimatikan), lokasi hanya dicatat jika diberikan.
     *
     * @return array{latitude:float,longitude:float,accuracy:?float,distance:?float,within:?bool,captured_at:?Carbon}|null
     */
    private function evaluateLocation(array $input, array $state): ?array
    {
        $lat = $input['latitude'] ?? null;
        $lng = $input['longitude'] ?? null;
        $has = is_numeric($lat) && is_numeric($lng);
        $enforced = $state['requires_gps'];

        $company = Company::current();
        $officeKnown = $company && $company->hasCoordinates();

        if ($enforced) {
            if (!$has) {
                throw new AttendanceException('Lokasi belum diperoleh. Izinkan akses lokasi dan tunggu sampai koordinat muncul.', 422, ['code' => 'location_required']);
            }
            if (!$officeKnown) {
                throw new AttendanceException('Koordinat kantor belum diatur. Hubungi administrator.', 422, ['code' => 'office_unset']);
            }
        }

        if (!$has) {
            return null;
        }

        $accuracy = isset($input['accuracy']) && is_numeric($input['accuracy']) ? (float) $input['accuracy'] : null;
        $capturedMs = isset($input['captured_at']) && is_numeric($input['captured_at']) ? (int) $input['captured_at'] : null;

        $distance = $officeKnown
            ? $this->geo->distanceMeters((float) $lat, (float) $lng, (float) $company->latitude, (float) $company->longitude)
            : null;
        $radius = $officeKnown ? (int) $company->attendance_radius_meters : 0;
        $within = $distance !== null ? $distance <= $radius : null;

        if ($enforced) {
            $maxAccuracy = (int) $company->max_gps_accuracy_meters;

            if ($accuracy === null) {
                throw new AttendanceException('Akurasi lokasi tidak tersedia. Coba muat ulang lokasi.', 422, ['code' => 'location_accuracy']);
            }
            if ($accuracy > $maxAccuracy) {
                throw new AttendanceException('Akurasi lokasi terlalu rendah (sekitar '.round($accuracy).' m, maksimal '.$maxAccuracy.' m). Pindah ke area terbuka lalu coba lagi.', 422, ['code' => 'location_accuracy']);
            }

            $nowMs = (int) round(microtime(true) * 1000);
            if ($capturedMs === null || abs($nowMs - $capturedMs) > self::LOCATION_MAX_AGE_SECONDS * 1000) {
                throw new AttendanceException('Data lokasi sudah kedaluwarsa. Muat ulang lokasi lalu coba lagi.', 422, ['code' => 'location_stale']);
            }
            if (!$within) {
                throw new AttendanceException('Anda berada sekitar '.round($distance).' m dari kantor, di luar radius '.$radius.' m. Absensi ditolak.', 422, ['code' => 'outside_radius']);
            }
        }

        return [
            'latitude' => (float) $lat,
            'longitude' => (float) $lng,
            'accuracy' => $accuracy,
            'distance' => $distance,
            'within' => $within,
            'captured_at' => $capturedMs ? Carbon::createFromTimestampMs($capturedMs)->setTimezone(config('app.timezone')) : null,
        ];
    }

    /** @return float|null skor kecocokan, atau null jika verifikasi wajah dimatikan */
    private function consumeFace(Employee $employee, array $state, array $input): ?float
    {
        if (!$state['requires_face']) {
            return null;
        }

        if (!$this->faces->activeDescriptor($employee)) {
            throw new AttendanceException('Wajah Anda belum terdaftar atau belum disetujui. Daftarkan wajah terlebih dahulu.', 409, ['code' => 'face_not_registered']);
        }

        $ticket = $this->faces->consumeTicket($employee, $input['face_ticket'] ?? null);
        if (!$ticket) {
            throw new AttendanceException('Verifikasi wajah belum dilakukan atau sudah kedaluwarsa. Ulangi verifikasi wajah.', 422, ['code' => 'face_ticket_invalid']);
        }

        return (float) $ticket['score'];
    }

    private function method(?float $faceScore, ?array $location): string
    {
        $face = $faceScore !== null;
        $gps = $location !== null;

        return $face && $gps ? 'face_gps' : ($face ? 'face' : ($gps ? 'gps' : 'none'));
    }

    private function storeLocation(AttendanceRecord $record, string $type, array $loc, ?string $ip, ?string $userAgent): void
    {
        AttendanceLocation::create([
            'attendance_record_id' => $record->id,
            'type' => $type,
            'latitude' => $loc['latitude'],
            'longitude' => $loc['longitude'],
            'accuracy_meters' => $loc['accuracy'],
            'distance_meters' => $loc['distance'],
            'is_within_radius' => $loc['within'],
            'captured_at' => $loc['captured_at'],
            'ip_address' => $ip,
            'user_agent' => $userAgent ? mb_substr($userAgent, 0, 255) : null,
        ]);
    }
}
