<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupService;
use Illuminate\Console\Command;

class PruneBackupsCommand extends Command
{
    protected $signature = 'hims:backup-prune';

    protected $description = 'Prune old database backups based on retention policy, keeping at least 3 latest backups.';

    public function handle(BackupService $backupService): int
    {
        $this->info("Pruning expired DTCS HIMS database backups...");
        $pruned = $backupService->pruneOldBackups();
        $this->info("✓ Pruning complete. {$pruned} old backup file(s) removed.");
        return self::SUCCESS;
    }
}
