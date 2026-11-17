<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\EmployeeFaceDescriptor;
use Illuminate\Console\Command;

class PurgeInactiveFaces extends Command
{
    protected $signature = 'faces:purge-inactive {--days=90 : Masa simpan data wajah nonaktif (hari)}';

    protected $description = 'Hapus data wajah nonaktif yang melewati masa simpan, dan data wajah milik karyawan yang sudah dihapus.';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $query = EmployeeFaceDescriptor::query()->where(function ($q) use ($cutoff) {
            $q->where(fn ($i) => $i->where('status', 'inactive')->where('deactivated_at', '<', $cutoff))
                ->orWhereDoesntHave('employee'); // karyawan terhapus tidak dihitung "ada"
        });

        $count = (clone $query)->count();
        $query->delete();

        if ($count > 0) {
            AuditLog::record('purge_face_data', null, null, ['count' => $count, 'days' => $days]);
        }

        $this->info("Data wajah dihapus: {$count}.");

        return self::SUCCESS;
    }
}
