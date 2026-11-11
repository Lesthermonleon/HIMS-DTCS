<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\BackupRun;
use App\Services\Backup\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class BackupController extends Controller
{
    public function __construct(
        protected BackupService $backupService
    ) {}

    /**
     * Display backup operations interface and history.
     */
    public function index(): View
    {
        $backupRuns = BackupRun::with('triggeredBy')
            ->orderBy('id', 'desc')
            ->paginate(15);

        $stats = [
            'total'      => BackupRun::count(),
            'successful' => BackupRun::where('status', 'success')->count(),
            'failed'     => BackupRun::where('status', 'failed')->count(),
            'last_run'   => BackupRun::orderBy('id', 'desc')->first(),
        ];

        return view('super_admin.backup.index', compact('backupRuns', 'stats'));
    }

    /**
     * Trigger a real protected database backup.
     */
    public function trigger(Request $request): RedirectResponse
    {
        $run = $this->backupService->runBackup('manual', Auth::id());

        if ($run->status === 'success') {
            return back()->with('success', sprintf(
                'Database backup completed successfully [%s] (%s). SHA256: %s',
                basename($run->file_path),
                $run->formatted_size,
                substr($run->checksum_sha256, 0, 16) . '...'
            ));
        }

        return back()->with('error', 'Database backup failed: ' . $run->error_summary);
    }

    /**
     * Run manual pruning of expired backups.
     */
    public function prune(): RedirectResponse
    {
        $count = $this->backupService->pruneOldBackups();
        return back()->with('info', "Pruning completed. {$count} expired backup(s) pruned. The 3 latest successful backups are always preserved.");
    }
}
