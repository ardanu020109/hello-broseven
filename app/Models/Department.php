<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function divisions(): HasMany
    {
        return $this->hasMany(Division::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
