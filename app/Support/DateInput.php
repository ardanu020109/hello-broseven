<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class DateInput
{
    /** Parse 'Y-m-d' yang ketat. Mengembalikan null jika tidak valid. */
    public static function date(?string $value): ?Carbon
    {
        return self::parse($value, '!Y-m-d', 'Y-m-d');
    }

    /** Parse 'Y-m' (awal bulan). Mengembalikan null jika tidak valid. */
    public static function month(?string $value): ?Carbon
    {
        return self::parse($value, '!Y-m', 'Y-m');
    }

    private static function parse(?string $value, string $parseFormat, string $checkFormat): ?Carbon
    {
        if (!$value) {
            return null;
        }

        try {
            $date = Carbon::createFromFormat($parseFormat, $value);
        } catch (\Throwable) {
            return null;
        }

        if (!$date || $date->format($checkFormat) !== $value || $date->year < 2000 || $date->year > 2100) {
            return null;
        }

        return $date;
    }
}
