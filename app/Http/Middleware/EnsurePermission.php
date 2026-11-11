<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     * @param  string  $permission
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }

        if (! $request->user()->hasPermission($permission)) {
            try {
                ActivityLog::create([
                    'user_id'     => $request->user()->id,
                    'action'      => 'Unauthorized Permission Attempt',
                    'module'      => ActivityLog::MODULE_SECURITY,
                    'description' => sprintf(
                        'User [%s] (ID: %d) attempted access to route [%s] requiring permission [%s].',
                        $request->user()->email,
                        $request->user()->id,
                        $request->path(),
                        $permission
                    ),
                    'ip_address'  => $request->ip(),
                    'severity'    => ActivityLog::SEVERITY_WARNING,
                    'result'      => ActivityLog::RESULT_BLOCKED,
                    'logged_at'   => now(),
                ]);
            } catch (\Throwable $e) {
                // Ignore audit failure so 403 response is not broken
            }

            abort(403, 'Unauthorized. You do not hold the required permission [' . $permission . '].');
        }

        return $next($request);
    }
}
