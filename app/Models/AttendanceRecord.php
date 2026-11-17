<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AttendanceRecord extends Model
{
    public const STATUS_PRESENT = 'present';
    public const STATUS_LATE = 'late';
    public const STATUS_ABSENT = 'absent';
    public const STATUS_LEAVE = 'leave';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'corrected_at' => 'datetime',
            'is_corrected' => 'boolean',
            'face_match_score' => 'decimal:4',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function workSchedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class);
    }

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    public function corrector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(AttendanceLocation::class);
    }

    public function checkInLocation(): HasOne
    {
        return $this->hasOne(AttendanceLocation::class)->where('type', 'check_in');
    }

    public function checkOutLocation(): HasOne
    {
        return $this->hasOne(AttendanceLocation::class)->where('type', 'check_out');
    }
}
