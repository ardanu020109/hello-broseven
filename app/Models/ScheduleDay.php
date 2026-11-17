<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleDay extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_working_day' => 'boolean', 'day_of_week' => 'integer'];
    }

    public function workSchedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class);
    }
}
