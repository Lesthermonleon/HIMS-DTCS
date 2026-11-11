<?php

namespace App\Services\Backup;

use App\Models\ActivityLog;
use App\Models\BackupRun;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\File;

class BackupService
{
    public function __construct(
        protected BackupRunnerInterface $runner,
        protected SettingsService $settings
    ) {}

    /**
     * Execute a backup run and record its outcome.
     */
    public function runBackup(string $triggerType = 'manual', ?int $userId = null): BackupRun
    {
        $backupRun = BackupRun::create([
            'trigger_type' => $triggerType,
            'status'       => 'running',
            'started_at'   => now(),
            'triggered_by' => $userId,
        ]);

        $backupDir = storage_path('app/backups');
        if (! File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0750, true);
        }

        $filename = sprintf('backup_%s_%06d.sql', now()->format('Y-m-d_His'), $backupRun->id);
        $absolutePath = $backupDir . DIRECTORY_SEPARATOR . $filename;
        $relativePath = 'backups/' . $filename;

        $result = $this->runner->dump($absolutePath);

        if ($result['success']) {
            $backupRun->update([
                'status'          => 'success',
                'file_path'       => $relativePath,
                'file_size'       => $result['file_size'],
                'checksum_sha256' => $result['checksum'],
                'finished_at'     => now(),
            ]);

            try {
                ActivityLog::create([
                    'user_id'     => $userId,
                    'action'      => 'Database Backup Completed',
                    'module'      => ActivityLog::MODULE_BACKUP,
                    'description' => sprintf(
                        'Database backup completed successfully [%s] (%s). Trigger: %s.',
                        $filename,
                        $backupRun->formatted_size,
                        $triggerType
                    ),
                    'severity'    => ActivityLog::SEVERITY_INFO,
                    'result'      => ActivityLog::RESULT_SUCCESS,
                    'logged_at'   => now(),
                ]);
            } catch (\Throwable $e) {}
        } else {
            $backupRun->update([
                'status'        => 'failed',
                'error_summary' => substr((string) ($result['error'] ?? 'Unknown error'), 0, 950),
                'finished_at'   => now(),
            ]);

            try {
                ActivityLog::create([
                    'user_id'     => $userId,
                    'action'      => 'Database Backup Failed',
                    'module'      => ActivityLog::MODULE_BACKUP,
                    'description' => sprintf(
                        'Database backup failed for run #%d. Error: %s',
                        $backupRun->id,
                        $backupRun->error_summary
                    ),
                    'severity'    => ActivityLog::SEVERITY_CRITICAL,
                    'result'      => ActivityLog::RESULT_FAILED,
                    'logged_at'   => now(),
                ]);
            } catch (\Throwable $e) {}
        }

        return $backupRun;
    }

    /**
     * Prune backups older than configured retention period.
     * Guaranteed to preserve at least 3 latest successful backups.
     */
    public function pruneOldBackups(): int
    {
        $retentionDays = (int) $this->settings->get('backup.retention_days', 30);
        $cutoff = now()->subDays($retentionDays);

        // Keep at least 3 latest successful backups
        $keepIds = BackupRun::where('status', 'success')
            ->orderBy('id', 'desc')
            ->take(3)
            ->pluck('id')
            ->all();

        $candidates = BackupRun::where('status', 'success')
            ->whereNull('pruned_at')
            ->where('created_at', '<', $cutoff)
            ->whereNotIn('id', $keepIds)
            ->get();

        $prunedCount = 0;
        foreach ($candidates as $run) {
            if ($run->file_path) {
                $fullPath = storage_path('app/' . $run->file_path);
                if (File::exists($fullPath)) {
                    @File::delete($fullPath);
                }
            }
            $run->update(['pruned_at' => now()]);
            $prunedCount++;
        }

        return $prunedCount;
    }
}
