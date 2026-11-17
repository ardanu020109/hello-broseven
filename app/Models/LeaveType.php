<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['requires_attachment' => 'boolean', 'is_active' => 'boolean'];
    }

    public function salaryDeductionRule(): BelongsTo
    {
        return $this->belongsTo(SalaryDeductionRule::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }
}
