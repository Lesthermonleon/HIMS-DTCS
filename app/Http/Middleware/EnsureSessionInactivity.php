<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use App\Services\Settings\SettingsService;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureSessionInactivity
{
    public function __construct(
        protected SettingsService $settings
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $timeoutMinutes = $this->settings->get('security.session_inactivity_minutes');

            // Exempt passive polling / background endpoints
            if ($request->routeIs(
                'notifications.unread-count',
                'notifications.recent',
                'messages.recent',
                'session.lock-status'
            )) {
                return $next($request);
            }

            if ($timeoutMinutes && (int) $timeoutMinutes > 0) {
                $lastActivity = $request->session()->get('hims_last_request_at');

                if ($lastActivity) {
                    $elapsedMinutes = now()->diffInMinutes(Carbon::createFromTimestamp($lastActivity));

                    if ($elapsedMinutes >= (int) $timeoutMinutes) {
                        /** @var \App\Models\User $user */
                        $user = Auth::user();

                        try {
                            ActivityLog::create([
                                'user_id'     => $user->id,
                                'action'      => 'Session Inactivity Expired',
                                'module'      => ActivityLog::MODULE_AUTH,
                                'description' => "Session expired after {$elapsedMinutes} minutes of inactivity (threshold: {$timeoutMinutes} min).",
                                'ip_address'  => $request->ip(),
                                'severity'    => ActivityLog::SEVERITY_INFO,
                                'result'      => ActivityLog::RESULT_SUCCESS,
                                'logged_at'   => now(),
                            ]);
                        } catch (\Throwable $e) {}

                        $user->clearActiveSession($request->session()->getId());
                        Auth::guard('web')->logout();
                        $request->session()->invalidate();
                        $request->session()->regenerateToken();

                        if ($request->expectsJson() || $request->ajax()) {
                            return response()->json([
                                'message'  => 'Your session has expired due to inactivity. Please sign in again.',
                                'redirect' => route('login'),
                            ], 401);
                        }

                        return redirect()->route('login')->with('warning', 'Your session expired due to inactivity. Please sign in again.');
                    }
                }

                $request->session()->put('hims_last_request_at', now()->timestamp);
            }
        }

        return $next($request);
    }
}
