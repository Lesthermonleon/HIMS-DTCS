<?php

namespace App\Http\Controllers\Auth;

use App\Events\SessionReplaced;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Mail\OtpNotificationMail;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function __construct(
        private readonly OtpService $otpService,
    ) {}
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     *
     * Single Active Session with Session Replacement Policy:
     * When a user logs in from another browser or device, the new login proceeds normally,
     * becomes the active session, and broadcasts a SessionReplaced event to immediately
     * terminate the previous active session.
     */
    /**
     * Handle an incoming authentication request.
     *
     * IMPORTANT: Auth::login() is NOT called here. Credentials are validated,
     * an OTP is generated and emailed, and only 'otp_pending_user_id' is stored
     * in session. The full authenticated session is established only after the
     * user successfully verifies the OTP in OtpVerificationController::verify().
     *
     * This preserves all existing lockout, single-session, RBAC, and audit
     * functionality — they all fire after OTP verification, not before.
     */
    public function store(LoginRequest $request): RedirectResponse|View|Response
    {
        // 1. Run basic request validation (email + password format)
        $request->validate([
            'email'    => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $email = $request->input('email');
        $user  = User::where('email', $email)->first();

        // 2. Check if user is locked out or in cooldown using existing LoginRequest logic
        if ($user) {
            if ($user->isLockedOut() || $user->isCoolingDown()) {
                $request->authenticate(); // Throws validation exception with lockout/cooldown rules
            }
        }

        // 3. Verify credentials with Hash check before proceeding
        if (! $user || ! Hash::check($request->input('password'), $user->password) || ! $user->is_active) {
            // Trigger standard Breeze lockout handling for invalid credentials
            $request->authenticate();
        }

        // 4. Password is VALID — require OTP verification
        // Check send rate limit (max 5 OTPs in 15 minutes)
        if ($this->otpService->isRateLimited($user)) {
            return back()->withErrors([
                'email' => 'Too many verification code requests. Please wait before trying again.',
            ])->onlyInput('email');
        }

        // Generate OTP and send email
        $plainOtp   = $this->otpService->generate($user);
        $expMinutes = config('otp.expires_minutes', 3);

        try {
            Mail::to($user->email)->send(new OtpNotificationMail($plainOtp, $expMinutes));
        } catch (\Throwable $e) {
            logger()->error('OTP email delivery failed for account [' . $user->email . ']: ' . $e->getMessage());
            return back()->withErrors([
                'email' => 'Failed to send verification code. Please try again.',
            ])->onlyInput('email');
        }

        // Store temporary pending state (NOT a full login)
        // The session holds only the user ID — no credentials, no tokens
        $request->session()->put('otp_pending_user_id', $user->id);

        // Handle Remember-email cookie at this stage so it is set regardless of OTP outcome
        if ($request->boolean('remember')) {
            cookie()->queue('remember_hims_email', $user->email, 43200);
        } else {
            cookie()->queue(cookie()->forget('remember_hims_email'));
        }

        // Audit: OTP generated
        ActivityLog::create([
            'user_id'     => $user->id,
            'action'      => 'OTP Generated',
            'module'      => 'Authentication',
            'severity'    => ActivityLog::SEVERITY_INFO,
            'result'      => ActivityLog::RESULT_SUCCESS,
            'description' => "Login verification code generated and sent to account [{$user->email}].",
            'ip_address'  => $request->ip(),
            'logged_at'   => now(),
        ]);

        return redirect()->route('otp.verify');
    }

    /**
     * Destroy an authenticated session.
     *
     * Invalidating the session removes active session registration on the user record.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user             = $request->user();
        $currentSessionId = $request->session()->getId();

        if ($user) {
            ActivityLog::create([
                'user_id'     => $user->id,
                'action'      => 'Logout',
                'module'      => 'Authentication',
                'severity'    => ActivityLog::SEVERITY_INFO,
                'result'      => ActivityLog::RESULT_SUCCESS,
                'description' => "User [{$user->email}] logged out.",
                'ip_address'  => $request->ip(),
                'logged_at'   => now(),
            ]);

            $user->clearActiveSession($currentSessionId);
            if ($user->active_session_id === null) {
                $user->update(['login_token' => null]);
            }
        }

        Auth::guard('web')->logout();

        // Destroy the current browser's session data
        $request->session()->invalidate();

        // Rotate the CSRF token so old tokens cannot be reused
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
