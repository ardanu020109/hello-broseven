<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Tandai karyawan yang tidak absen (dan belum punya izin disetujui) sebagai Tidak Hadir.
Schedule::command('attendance:mark-absent')
    ->everyTenMinutes()
    ->withoutOverlapping();
