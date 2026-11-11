<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditVaultController extends Controller
{
    /**
     * Display privileged audit vault with deep filtering.
     */
    public function index(Request $request): View
    {
        // Log vault review access itself
        try {
            ActivityLog::create([
                'user_id'     => Auth::id(),
                'action'      => 'Audit Vault Accessed',
                'module'      => ActivityLog::MODULE_AUDIT_VAULT,
                'description' => 'Super Administrator accessed protected audit trail review vault.',
                'ip_address'  => $request->ip(),
                'severity'    => ActivityLog::SEVERITY_INFO,
                'result'      => ActivityLog::RESULT_SUCCESS,
                'logged_at'   => now(),
            ]);
        } catch (\Throwable $e) {}

        $query = ActivityLog::with(['user', 'user.roles']);

        // Filter by user
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->query('user_id'));
        }

        // Filter by role
        if ($request->filled('role')) {
            $roleSlug = $request->query('role');
            $query->whereHas('user.roles', fn($q) => $q->where('slug', $roleSlug));
        }

        // Filter by module
        if ($request->filled('module')) {
            $query->where('module', $request->query('module'));
        }

        // Filter by severity
        if ($request->filled('severity')) {
            $query->where('severity', $request->query('severity'));
        }

        // Filter by result
        if ($request->filled('result')) {
            $query->where('result', $request->query('result'));
        }

        // Filter by IP
        if ($request->filled('ip_address')) {
            $query->where('ip_address', 'like', '%' . $request->query('ip_address') . '%');
        }

        // Date range
        if ($request->filled('date_from')) {
            $query->where('logged_at', '>=', $request->query('date_from') . ' 00:00:00');
        }
        if ($request->filled('date_to')) {
            $query->where('logged_at', '<=', $request->query('date_to') . ' 23:59:59');
        }

        // Free search
        if ($request->filled('search')) {
            $term = '%' . $request->query('search') . '%';
            $query->where(function ($q) use ($term) {
                $q->where('action', 'like', $term)
                  ->orWhere('description', 'like', $term);
            });
        }

        $logs = $query->orderBy('logged_at', 'desc')->paginate(25)->withQueryString();

        $modules = ActivityLog::distinct()->pluck('module')->filter()->sort()->values();
        $roles   = Role::orderBy('name')->get();

        return view('super_admin.audit_vault.index', compact('logs', 'modules', 'roles'));
    }

    /**
     * Show single audit log detail.
     */
    public function show(ActivityLog $auditLog): View
    {
        $auditLog->load(['user', 'user.roles', 'loggable']);
        return view('super_admin.audit_vault.show', compact('auditLog'));
    }

    /**
     * Export filtered audit records to CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        // Audit export action
        try {
            ActivityLog::create([
                'user_id'     => Auth::id(),
                'action'      => 'Audit Vault Exported',
                'module'      => ActivityLog::MODULE_AUDIT_VAULT,
                'description' => 'Super Administrator exported audit trail records to CSV.',
                'ip_address'  => $request->ip(),
                'severity'    => ActivityLog::SEVERITY_WARNING,
                'result'      => ActivityLog::RESULT_SUCCESS,
                'logged_at'   => now(),
            ]);
        } catch (\Throwable $e) {}

        $query = ActivityLog::with(['user']);

        if ($request->filled('module')) {
            $query->where('module', $request->query('module'));
        }
        if ($request->filled('severity')) {
            $query->where('severity', $request->query('severity'));
        }
        if ($request->filled('result')) {
            $query->where('result', $request->query('result'));
        }
        if ($request->filled('date_from')) {
            $query->where('logged_at', '>=', $request->query('date_from') . ' 00:00:00');
        }
        if ($request->filled('date_to')) {
            $query->where('logged_at', '<=', $request->query('date_to') . ' 23:59:59');
        }

        $filename = sprintf('audit_vault_export_%s.csv', now()->format('Ymd_His'));

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Timestamp (UTC)', 'User ID', 'User Name', 'User Email', 'Module', 'Action', 'Severity', 'Result', 'IP Address', 'Description']);

            $query->orderBy('logged_at', 'desc')->chunk(500, function ($records) use ($handle) {
                foreach ($records as $log) {
                    fputcsv($handle, [
                        $log->id,
                        $log->logged_at?->toIso8601String() ?? '',
                        $log->user_id ?? 'System',
                        $this->sanitizeCsvField($log->user?->name ?? 'System/Guest'),
                        $this->sanitizeCsvField($log->user?->email ?? '—'),
                        $this->sanitizeCsvField($log->module),
                        $this->sanitizeCsvField($log->action),
                        $log->severity,
                        $log->result,
                        $log->ip_address ?? '',
                        $this->sanitizeCsvField($log->description),
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Prevent CSV formula injection by prepending a single quote to dangerous leading characters.
     */
    protected function sanitizeCsvField(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $value;
        }
        return $value;
    }
}
