<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'attendance_radius_meters' => 'integer',
            'max_gps_accuracy_meters' => 'integer',
            'face_match_threshold' => 'decimal:3',
            'absent_cutoff_minutes' => 'integer',
        ];
    }

    public static function current(): ?self
    {
        return static::query()->first();
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }
}
