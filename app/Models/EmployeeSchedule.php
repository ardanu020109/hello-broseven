<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSchedule extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function workSchedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class);
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /** Penugasan yang berlaku pada tanggal tertentu (Y-m-d). */
    public function scopeEffectiveOn(Builder $query, string $date): Builder
    {
        return $query->where('start_date', '<=', $date)
            ->where(function (Builder $q) use ($date) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $date);
            });
    }
}
