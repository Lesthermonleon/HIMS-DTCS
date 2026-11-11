<?php

namespace App\Http\Middleware;

use App\Services\Settings\SettingsService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureMaintenanceMode
{
    public function __construct(
        protected SettingsService $settings
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $enabled = (bool) $this->settings->get('maintenance.enabled', false);

        if ($enabled) {
            // Always permit health checks, login/logout, OTP, password recovery, and assets
            if ($request->is('up') || $request->routeIs(
                'login',
                'logout',
                'password.*',
                'otp.*'
            )) {
                return $next($request);
            }

            // Super Admin can always access the entire application during maintenance
            if (Auth::check() && Auth::user()->isSuperAdmin()) {
                return $next($request);
            }

            $message = (string) $this->settings->get(
                'maintenance.message',
                'The hospital system is currently undergoing scheduled maintenance. Please check back shortly.'
            );
            $expectedBack = $this->settings->get('maintenance.expected_back');

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'maintenance'   => true,
                    'message'       => $message,
                    'expected_back' => $expectedBack,
                ], 503);
            }

            return response()->view('errors.maintenance', [
                'message'      => $message,
                'expectedBack' => $expectedBack,
            ], 503);
        }

        return $next($request);
    }
}
