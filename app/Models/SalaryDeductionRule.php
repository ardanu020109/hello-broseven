<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalaryDeductionRule extends Model
{
    public const STATUS_PRESENT = 'present';
    public const STATUS_LATE = 'late';
    public const STATUS_ABSENT = 'absent';
    public const STATUS_LEAVE = 'leave';
    public const STATUS_SICK = 'sick';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'deduction_percent' => 'decimal:2',
            'fixed_amount' => 'decimal:2',
        ];
    }

    public function leaveTypes(): HasMany
    {
        return $this->hasMany(LeaveType::class);
    }
}
