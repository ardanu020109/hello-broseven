<?php

namespace App\Console\Commands;

use App\Services\AbsenceService;
use Illuminate\Console\Command;

class MarkAbsentAttendance extends Command
{
    protected $signature = 'attendance:mark-absent {--days=2 : Berapa hari ke belakang yang diperiksa (0-31)}';

    protected $description = 'Tandai karyawan terjadwal tanpa absensi sebagai Tidak Hadir, dan buat catatan Izin untuk izin yang disetujui.';

    public function handle(AbsenceService $service): int
    {
        $days = (int) $this->option('days');

        $result = $service->run(null, $days);

        $this->info(sprintf(
            'Selesai. Tidak Hadir dibuat: %d, Izin dibuat: %d, hari terkunci payroll dilewati: %d.',
            $result['absent'],
            $result['leave'],
            $result['locked_days']
        ));

        return self::SUCCESS;
    }
}
