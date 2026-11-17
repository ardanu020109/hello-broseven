<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkSchedule extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_shift' => 'boolean', 'is_active' => 'boolean'];
    }

    public function days(): HasMany
    {
        return $this->hasMany(ScheduleDay::class);
    }

    public function employeeSchedules(): HasMany
    {
        return $this->hasMany(EmployeeSchedule::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    /** Konfigurasi hari (ISO 1-7) untuk jadwal ini. */
    public function dayConfig(int $isoDayOfWeek): ?ScheduleDay
    {
        return $this->days->firstWhere('day_of_week', $isoDayOfWeek);
    }
}
