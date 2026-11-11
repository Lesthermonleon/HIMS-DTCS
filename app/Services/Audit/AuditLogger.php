<?php

namespace App\Services\Audit;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    /**
     * Log an activity event with standardized structure.
     */
    public static function log(
        string $action,
        string $module,
        string $description,
        string $severity = ActivityLog::SEVERITY_INFO,
        string $result = ActivityLog::RESULT_SUCCESS,
        ?int $userId = null,
        ?Model $loggable = null,
        ?string $ipAddress = null
    ): ?ActivityLog {
        try {
            $effectiveUserId = $userId ?? Auth::id();
            $effectiveIp = $ipAddress ?? Request::ip();

            return ActivityLog::create([
                'user_id'       => $effectiveUserId,
                'action'        => $action,
                'module'        => $module,
                'description'   => $description,
                'loggable_type' => $loggable ? get_class($loggable) : null,
                'loggable_id'   => $loggable?->getKey(),
                'ip_address'    => $effectiveIp,
                'severity'      => $severity,
                'result'        => $result,
                'logged_at'     => now(),
            ]);
        } catch (\Throwable $e) {
            // Fail open for user request but log to standard error log
            logger()->error('AuditLogger failed: ' . $e->getMessage(), [
                'action' => $action,
                'module' => $module,
            ]);
            return null;
        }
    }
}
