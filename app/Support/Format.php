<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class Format
{
    public const DAY_STATUS = [
        'present' => 'Hadir',
        'late' => 'Terlambat',
        'absent' => 'Tidak Hadir',
        'leave' => 'Izin',
        'sick' => 'Sakit',
    ];

    public const PAYROLL_STATUS = [
        'draft' => 'Draft',
        'finalized' => 'Final',
        'paid' => 'Dibayar',
    ];

    public static function rupiah(float|int|string|null $value): string
    {
        $v = (float) $value;
        $hasCents = abs($v - round($v)) > 0.004;

        return 'Rp '.number_format($v, $hasCents ? 2 : 0, ',', '.');
    }

    public static function percent(float|int|string|null $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',').'%';
    }

    public static function dayStatus(?string $status): string
    {
        return $status ? (self::DAY_STATUS[$status] ?? $status) : '-';
    }

    public static function payrollStatus(?string $status): string
    {
        return $status ? (self::PAYROLL_STATUS[$status] ?? $status) : '-';
    }

    public static function periodLabel(int $year, int $month): string
    {
        return Carbon::create($year, $month, 1)->locale('id')->translatedFormat('F Y');
    }
}
