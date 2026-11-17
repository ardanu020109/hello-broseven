<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeFaceDescriptor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class FaceVerificationService
{
    public const TICKET_TTL = 120;              // detik
    public const SAMPLE_CONSISTENCY_MAX = 0.5;  // jarak maksimum antar sampel registrasi

    /** Ambang jarak Euclidean (lebih kecil = lebih ketat), dari pengaturan perusahaan. */
    public function threshold(): float
    {
        $value = Company::current()?->face_match_threshold;
        $threshold = $value !== null ? (float) $value : 0.5;

        return min(max($threshold, 0.3), 0.8);
    }

    public function activeDescriptor(Employee $employee): ?EmployeeFaceDescriptor
    {
        return $employee->faceDescriptors()->where('status', 'active')->latest('id')->first();
    }

    /** active | pending | inactive | none */
    public function statusFor(Employee $employee): string
    {
        $statuses = $employee->faceDescriptors()->pluck('status')->all();

        return match (true) {
            in_array('active', $statuses, true) => 'active',
            in_array('pending_approval', $statuses, true) => 'pending',
            in_array('inactive', $statuses, true) => 'inactive',
            default => 'none',
        };
    }

    public static function distance(array $a, array $b): float
    {
        $sum = 0.0;
        $n = min(count($a), count($b));

        for ($i = 0; $i < $n; $i++) {
            $d = (float) $a[$i] - (float) $b[$i];
            $sum += $d * $d;
        }

        return sqrt($sum);
    }

    /** @return array<int, array<int, float>> daftar sampel descriptor milik satu record */
    public static function samplesOf(EmployeeFaceDescriptor $record): array
    {
        $data = $record->descriptor;
        if (!is_array($data) || $data === []) {
            return [];
        }

        return is_array($data[0] ?? null) ? array_values($data) : [array_values($data)];
    }

    /** @return array{matched: bool, distance: float, score: float} */
    public function match(EmployeeFaceDescriptor $record, array $descriptor): array
    {
        $best = INF;
        foreach (self::samplesOf($record) as $sample) {
            $best = min($best, self::distance($sample, $descriptor));
        }

        return [
            'matched' => is_finite($best) && $best <= $this->threshold(),
            'distance' => $best,
            'score' => is_finite($best) ? round(max(0.0, 1 - $best), 4) : 0.0,
        ];
    }

    /** Semua sampel registrasi harus mirip satu sama lain (orang yang sama, bukan data acak). */
    public function samplesAreConsistent(array $samples): bool
    {
        $n = count($samples);

        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                if (self::distance($samples[$i], $samples[$j]) > self::SAMPLE_CONSISTENCY_MAX) {
                    return false;
                }
            }
        }

        return true;
    }

    /** Tiket sekali pakai bukti bahwa wajah karyawan ini baru saja cocok. */
    public function issueTicket(Employee $employee, float $score): string
    {
        $token = Str::random(40);

        Cache::put(
            $this->ticketKey($token),
            ['employee_id' => $employee->id, 'score' => $score],
            now()->addSeconds(self::TICKET_TTL)
        );

        return $token;
    }

    /** @return array{employee_id:int, score:float}|null */
    public function consumeTicket(Employee $employee, ?string $token): ?array
    {
        if (!$token || strlen($token) !== 40) {
            return null;
        }

        $payload = Cache::pull($this->ticketKey($token));

        if (!is_array($payload) || (int) ($payload['employee_id'] ?? 0) !== $employee->id) {
            return null;
        }

        return $payload;
    }

    private function ticketKey(string $token): string
    {
        return 'face_ticket:'.$token;
    }
}
