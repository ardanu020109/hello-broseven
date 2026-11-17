<?php

namespace App\Support;

use App\Models\PayrollPeriod;

class PayrollLock
{
    /** True jika tanggal masuk periode payroll yang sudah Finalized atau Paid. */
    public static function covers(string $date): bool
    {
        return PayrollPeriod::query()
            ->where('status', '!=', PayrollPeriod::STATUS_DRAFT)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists();
    }
}
