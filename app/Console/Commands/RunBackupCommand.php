<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupService;
use Illuminate\Console\Command;

class RunBackupCommand extends Command
{
    protected $signature = 'hims:backup-run {--trigger=cli : Trigger type (cli, manual, scheduled)}';

    protected $description = 'Execute a database backup dump and record its outcome.';

    public function handle(BackupService $backupService): int
    {
        $trigger = (string) $this->option('trigger');
        $this->info("Starting DTCS HIMS database backup ({$trigger})...");

        $run = $backupService->runBackup($trigger);

        if ($run->status === 'success') {
            $this->info("✓ Backup completed successfully: {$run->file_path} ({$run->formatted_size})");
            $this->info("Checksum SHA256: {$run->checksum_sha256}");
            return self::SUCCESS;
        }

        $this->error("✗ Backup failed: {$run->error_summary}");
        return self::FAILURE;
    }
}
