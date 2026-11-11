<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * RoleMiddleware — restricts access to routes based on user role slugs.
 *
 * Usage in routes: ->middleware('role:admin') or ->middleware('role:admin,doctor')
 */
class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     * @param  string  ...$roles  Comma-separated role slugs.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        // Must be authenticated first
        if (! $request->user()) {
            return redirect()->route('login');
        }

        // Check if the user holds any of the required roles
        if (! $request->user()->hasAnyRole($roles)) {
            try {
                \App\Models\ActivityLog::create([
                    'user_id'     => $request->user()->id,
                    'action'      => 'Unauthorized Access Attempt',
                    'module'      => \App\Models\ActivityLog::MODULE_SECURITY,
                    'description' => sprintf(
                        'User [%s] (ID: %d) attempted unauthorized access to route [%s] requiring roles: %s',
                        $request->user()->email,
                        $request->user()->id,
                        $request->path(),
                        implode(', ', $roles)
                    ),
                    'ip_address'  => $request->ip(),
                    'severity'    => \App\Models\ActivityLog::SEVERITY_WARNING,
                    'result'      => \App\Models\ActivityLog::RESULT_BLOCKED,
                    'logged_at'   => now(),
                ]);
            } catch (\Throwable $e) {
                // Ignore audit failure so 403 response is not broken
            }

            abort(403, 'Unauthorized. You do not have access to this resource.');
        }

        return $next($request);
    }
}
