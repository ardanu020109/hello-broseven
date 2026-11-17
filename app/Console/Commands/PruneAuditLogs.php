<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;

class PruneAuditLogs extends Command
{
    protected $signature = 'audit:prune {--days=730 : Hapus catatan yang lebih lama dari ini (minimal 90)} {--force : Lewati konfirmasi}';

    protected $description = 'Hapus catatan audit lama sesuai kebijakan retensi perusahaan.';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        if ($days < 90) {
            $this->error('Masa simpan minimal 90 hari.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);
        $count = AuditLog::query()->where('created_at', '<', $cutoff)->count();

        if ($count === 0) {
            $this->info('Tidak ada catatan audit yang melewati masa simpan.');

            return self::SUCCESS;
        }

        if (!$this->option('force') && !$this->confirm("Hapus {$count} catatan audit yang lebih lama dari {$days} hari?")) {
            $this->warn('Dibatalkan.');

            return self::SUCCESS;
        }

        do {
            $deleted = AuditLog::query()->where('created_at', '<', $cutoff)->limit(1000)->delete();
        } while ($deleted > 0);

        AuditLog::record('prune_audit', null, null, ['count' => $count, 'days' => $days]);
        $this->info("Catatan audit dihapus: {$count}.");

        return self::SUCCESS;
    }
}
