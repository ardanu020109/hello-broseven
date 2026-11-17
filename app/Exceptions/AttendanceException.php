<?php

namespace App\Exceptions;

use RuntimeException;

/** Kegagalan aturan absensi yang pesannya aman ditampilkan ke karyawan. */
class AttendanceException extends RuntimeException
{
    public function __construct(string $message, private int $status = 422, private array $extra = [])
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function extra(): array
    {
        return $this->extra;
    }
}
