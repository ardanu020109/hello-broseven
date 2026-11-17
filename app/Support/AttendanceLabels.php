<?php

namespace App\Support;

use App\Models\AttendanceLocation;

class AttendanceLabels
{
    public const METHODS = [
        'face_gps' => 'Wajah + GPS',
        'face' => 'Wajah',
        'gps' => 'GPS',
        'none' => 'Tanpa verifikasi',
        'manual' => 'Manual (admin)',
        'system' => 'Sistem',
    ];

    public const STATUSES = [
        'present' => 'Hadir',
        'late' => 'Terlambat',
        'absent' => 'Tidak Hadir',
        'leave' => 'Izin',
    ];

    public static function method(?string $method): string
    {
        return $method ? (self::METHODS[$method] ?? $method) : '-';
    }

    public static function status(?string $status): string
    {
        return $status ? (self::STATUSES[$status] ?? $status) : '-';
    }

    public static function locationText(?AttendanceLocation $loc): string
    {
        if (!$loc) {
            return 'Tidak dicatat';
        }

        $parts = [];
        $parts[] = $loc->is_within_radius === true ? 'Dalam radius'
            : ($loc->is_within_radius === false ? 'Di luar radius' : 'Lokasi tercatat');

        if ($loc->distance_meters !== null) {
            $parts[] = number_format((float) $loc->distance_meters, 0, ',', '.').' m dari kantor';
        }
        if ($loc->accuracy_meters !== null) {
            $parts[] = 'akurasi ±'.round((float) $loc->accuracy_meters).' m';
        }

        return implode(' · ', $parts);
    }

    public static function mapUrl(?AttendanceLocation $loc): ?string
    {
        if (!$loc || $loc->latitude === null || $loc->longitude === null) {
            return null;
        }

        $lat = (float) $loc->latitude;
        $lng = (float) $loc->longitude;

        return "https://www.openstreetmap.org/?mlat={$lat}&mlon={$lng}#map=18/{$lat}/{$lng}";
    }
}
