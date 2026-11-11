<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminAccountController extends Controller
{
    /**
     * List all privileged System Administrator accounts.
     */
    public function index(Request $request): View
    {
        $status = $request->query('status');

        $query = User::whereHas('roles', fn($q) => $q->where('slug', 'admin'))
            ->withTrashed()
            ->with('roles');

        if ($status === 'active') {
            $query->whereNull('deleted_at')->where('is_active', true)->whereNull('locked_at');
        } elseif ($status === 'inactive') {
            $query->whereNull('deleted_at')->where('is_active', false)->whereNull('locked_at');
        } elseif ($status === 'locked') {
            $query->whereNull('deleted_at')->whereNotNull('locked_at');
        } elseif ($status === 'archived') {
            $query->onlyTrashed();
        }

        if ($request->filled('search')) {
            $term = '%' . $request->query('search') . '%';
            $query->where(fn($q) => $q->where('name', 'like', $term)->orWhere('email', 'like', $term));
        }

        $admins = $query->orderBy('name')->paginate(15)->withQueryString();

        $stats = [
            'total'    => User::whereHas('roles', fn($q) => $q->where('slug', 'admin'))->withTrashed()->count(),
            'active'   => User::whereHas('roles', fn($q) => $q->where('slug', 'admin'))->whereNull('deleted_at')->where('is_active', true)->whereNull('locked_at')->count(),
            'inactive' => User::whereHas('roles', fn($q) => $q->where('slug', 'admin'))->whereNull('deleted_at')->where('is_active', false)->whereNull('locked_at')->count(),
            'locked'   => User::whereHas('roles', fn($q) => $q->where('slug', 'admin'))->whereNull('deleted_at')->whereNotNull('locked_at')->count(),
            'archived' => User::whereHas('roles', fn($q) => $q->where('slug', 'admin'))->onlyTrashed()->count(),
        ];

        return view('super_admin.admins.index', compact('admins', 'stats', 'status'));
    }

    /**
     * Create a new Admin account and dispatch a secure password setup link.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'email'       => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'department'  => ['nullable', 'string', 'max:100'],
            'phone'       => ['nullable', 'string', 'max:30'],
            'reason'      => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $adminRole = Role::where('slug', 'admin')->firstOrFail();

        DB::transaction(function () use ($validated, $adminRole) {
            // Generate temporary cryptographically random password
            $randomSecret = Str::random(32);

            $user = User::create([
                'name'              => $validated['name'],
                'email'             => $validated['email'],
                'password'          => Hash::make($randomSecret),
                'department'        => $validated['department'] ?? 'Hospital Administration',
                'phone'             => $validated['phone'] ?? null,
                'is_active'         => true,
                'email_verified_at' => now(),
            ]);

            $user->roles()->sync([$adminRole->id]);

            // Create password reset token and send invitation
            $token = Password::broker()->createToken($user);
            $user->sendPasswordResetNotification($token);

            ActivityLog::create([
                'user_id'     => Auth::id(),
                'action'      => 'Admin Account Provisioned',
                'module'      => ActivityLog::MODULE_SECURITY,
                'description' => sprintf(
                    'Privileged Administrator account [%s] (ID: %d) provisioned by Super Admin. Reason: %s. Password setup dispatched to email.',
                    $user->email,
                    $user->id,
                    $validated['reason']
                ),
                'severity'    => ActivityLog::SEVERITY_CRITICAL,
                'result'      => ActivityLog::RESULT_SUCCESS,
                'logged_at'   => now(),
            ]);
        });

        return back()->with('success', "Administrator account [{$validated['email']}] created. Password setup notification dispatched.");
    }

    /**
     * Toggle active/inactive status.
     */
    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        if ($user->id === Auth::id()) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        $newStatus = ! $user->is_active;
        $user->update(['is_active' => $newStatus]);

        if (! $newStatus) {
            $user->clearActiveSession();
        }

        ActivityLog::create([
            'user_id'     => Auth::id(),
            'action'      => $newStatus ? 'Admin Account Activated' : 'Admin Account Deactivated',
            'module'      => ActivityLog::MODULE_SECURITY,
            'description' => sprintf(
                'Administrator account [%s] %s by Super Admin. Reason: %s',
                $user->email,
                $newStatus ? 'activated' : 'deactivated',
                $validated['reason']
            ),
            'severity'    => ActivityLog::SEVERITY_WARNING,
            'result'      => ActivityLog::RESULT_SUCCESS,
            'logged_at'   => now(),
        ]);

        return back()->with('success', "Account [{$user->email}] status updated.");
    }

    /**
     * Unlock a locked account.
     */
    public function unlock(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $user->resetLoginAttempts();
        $user->update(['is_active' => true]);

        ActivityLog::create([
            'user_id'     => Auth::id(),
            'action'      => 'Admin Account Unlocked',
            'module'      => ActivityLog::MODULE_SECURITY,
            'description' => sprintf('Administrator account [%s] unlocked by Super Admin. Reason: %s', $user->email, $validated['reason']),
            'severity'    => ActivityLog::SEVERITY_WARNING,
            'result'      => ActivityLog::RESULT_SUCCESS,
            'logged_at'   => now(),
        ]);

        return back()->with('success', "Account [{$user->email}] successfully unlocked.");
    }

    /**
     * Send password reset link to an Admin account.
     */
    public function sendPasswordReset(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $token = Password::broker()->createToken($user);
        $user->sendPasswordResetNotification($token);

        ActivityLog::create([
            'user_id'     => Auth::id(),
            'action'      => 'Admin Password Reset Initiated',
            'module'      => ActivityLog::MODULE_SECURITY,
            'description' => sprintf('Password reset link sent to [%s] by Super Admin. Reason: %s', $user->email, $validated['reason']),
            'severity'    => ActivityLog::SEVERITY_WARNING,
            'result'      => ActivityLog::RESULT_SUCCESS,
            'logged_at'   => now(),
        ]);

        return back()->with('success', "Password reset notification dispatched to [{$user->email}].");
    }

    /**
     * Archive (soft-delete) an Admin account.
     */
    public function archive(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        if ($user->id === Auth::id()) {
            return back()->with('error', 'You cannot archive your own account.');
        }

        $user->clearActiveSession();
        $user->delete();

        ActivityLog::create([
            'user_id'     => Auth::id(),
            'action'      => 'Admin Account Archived',
            'module'      => ActivityLog::MODULE_SECURITY,
            'description' => sprintf('Administrator account [%s] was archived by Super Admin. Reason: %s', $user->email, $validated['reason']),
            'severity'    => ActivityLog::SEVERITY_CRITICAL,
            'result'      => ActivityLog::RESULT_SUCCESS,
            'logged_at'   => now(),
        ]);

        return back()->with('success', "Account [{$user->email}] archived.");
    }

    /**
     * Restore an archived Admin account.
     */
    public function restore(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $user = User::onlyTrashed()->findOrFail($id);
        $user->restore();

        ActivityLog::create([
            'user_id'     => Auth::id(),
            'action'      => 'Admin Account Restored',
            'module'      => ActivityLog::MODULE_SECURITY,
            'description' => sprintf('Administrator account [%s] restored by Super Admin. Reason: %s', $user->email, $validated['reason']),
            'severity'    => ActivityLog::SEVERITY_WARNING,
            'result'      => ActivityLog::RESULT_SUCCESS,
            'logged_at'   => now(),
        ]);

        return back()->with('success', "Account [{$user->email}] restored successfully.");
    }
}
