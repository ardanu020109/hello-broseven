<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeFaceDescriptor extends Model
{
    protected $guarded = ['id'];

    // descriptor tidak pernah ikut ter-serialize (toArray/toJson) secara tidak sengaja
    protected $hidden = ['descriptor'];

    protected function casts(): array
    {
        return [
            'descriptor' => 'encrypted:array', // 128 angka float, terenkripsi dengan APP_KEY
            'consent_at' => 'datetime',
            'registered_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function deactivator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deactivated_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
