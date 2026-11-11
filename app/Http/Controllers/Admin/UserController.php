<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * UserController — Admin manages hospital system users.
 */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->input('search');
        $role = $request->input('role');
        $status = $request->input('status');
        $department = $request->input('department');

        // 1. Query regular users (excludes soft-deleted by default)
        $query = User::with('roles');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }

        if ($role) {
            $query->whereHas('roles', fn($q) => $q->where('slug', $role));
        }

        if ($department) {
            $query->where('department', $department);
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        } elseif ($status === 'locked') {
            $query->whereNotNull('locked_at');
        }

        $users = $query->latest()->paginate(15)->withQueryString();

        // 2. Query archived users (soft-deleted only)
        $archiveQuery = User::onlyTrashed()->with('roles');

        if ($search) {
            $archiveQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }

        if ($role) {
            $archiveQuery->whereHas('roles', fn($q) => $q->where('slug', $role));
        }

        if ($department) {
            $archiveQuery->where('department', $department);
        }

        $archivedUsers = $archiveQuery->latest()->get();
        $roles = Role::all();
        $departments = User::distinct()->whereNotNull('department')->pluck('department')->sort()->values();

        $statsByUserType = [
            'super_admin' => User::whereHas('roles', fn($q) => $q->where('slug', 'super_admin'))->count(),
            'admin'       => User::whereHas('roles', fn($q) => $q->where('slug', 'admin'))->count(),
            'doctor'      => User::whereHas('roles', fn($q) => $q->where('slug', 'doctor'))->count(),
            'staff'       => User::whereHas('roles', fn($q) => $q->whereNotIn('slug', ['super_admin', 'admin', 'doctor', 'patient']))->count(),
            'patient'     => User::whereHas('roles', fn($q) => $q->where('slug', 'patient'))->count(),
        ];

        return view('admin.users.index', compact('users', 'archivedUsers', 'roles', 'departments', 'statsByUserType'));
    }

    public function show(User $user): RedirectResponse
    {
        return redirect()->route('admin.users.edit', $user);
    }

    public function edit(User $user): View
    {
        if (! Auth::user()?->isSuperAdmin() && $user->isProtectedPrivilegedAccount() && $user->id !== Auth::id()) {
            abort(403, 'Unauthorized. Protected privileged accounts cannot be edited by ordinary administrators.');
        }

        $roles = Role::all();

        return view('admin.users.edit', compact('user', 'roles'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        // Prevent ordinary admin from modifying other privileged accounts (super_admin, admin)
        if (! Auth::user()?->isSuperAdmin() && $user->isProtectedPrivilegedAccount() && $user->id !== Auth::id()) {
            ActivityLog::create([
                'user_id'     => Auth::id(),
                'action'      => 'Privileged Account Modification Blocked',
                'module'      => ActivityLog::MODULE_SECURITY,
                'description' => "Admin attempted unauthorized modification of protected privileged account [{$user->email}].",
                'severity'    => ActivityLog::SEVERITY_CRITICAL,
                'result'      => ActivityLog::RESULT_BLOCKED,
                'ip_address'  => $request->ip(),
                'logged_at'   => now(),
            ]);
            abort(403, 'Unauthorized. Protected privileged accounts cannot be modified by ordinary administrators.');
        }

        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'email'       => "required|email|unique:users,email,{$user->id}",
            'employee_id' => "nullable|string|unique:users,employee_id,{$user->id}",
            'department'  => 'nullable|string|max:100',
            'phone'       => 'nullable|string|max:20',
            'is_active'   => 'boolean',
            'role_id'     => 'required|exists:roles,id',
        ]);

        $targetRole = Role::findOrFail($data['role_id']);

        // Anti-escalation check: Admin can NEVER assign super_admin or admin
        if (! Auth::user()?->isSuperAdmin() && in_array($targetRole->slug, ['super_admin', 'admin'], true) && $user->id !== Auth::id()) {
            ActivityLog::create([
                'user_id'     => Auth::id(),
                'action'      => 'Privilege Escalation Blocked',
                'module'      => ActivityLog::MODULE_SECURITY,
                'description' => sprintf(
                    'Admin attempted unauthorized assignment of privileged role [%s] to user [%s].',
                    $targetRole->name,
                    $user->email
                ),
                'severity'    => ActivityLog::SEVERITY_CRITICAL,
                'result'      => ActivityLog::RESULT_BLOCKED,
                'ip_address'  => $request->ip(),
                'logged_at'   => now(),
            ]);
            abort(403, 'Unauthorized. Privileged administrative roles cannot be assigned through ordinary administration.');
        }

        if ($user->id === Auth::id()) {
            $currentRoleId = $user->roles()->first()?->id;
            if ((int) $data['role_id'] !== (int) $currentRoleId) {
                return back()->withErrors(['role_id' => 'You cannot change your own role.']);
            }
            if ($request->has('is_active') && !$request->boolean('is_active')) {
                return back()->withErrors(['is_active' => 'You cannot deactivate your own account.']);
            }
        }

        $user->update([
            'name'        => $data['name'],
            'email'       => $data['email'],
            'employee_id' => $data['employee_id'] ?? null,
            'department'  => $data['department'] ?? null,
            'phone'       => $data['phone'] ?? null,
            'is_active'   => $request->boolean('is_active'),
        ]);

        // Sync role
        $user->roles()->sync([$data['role_id']]);

        ActivityLog::create([
            'user_id'     => Auth::id(),
            'action'      => 'User Updated',
            'module'      => 'User Management',
            'severity'    => ActivityLog::SEVERITY_INFO,
            'result'      => ActivityLog::RESULT_SUCCESS,
            'description' => "User account [{$user->email}] ({$user->name}) was updated by admin.",
            'ip_address'  => request()->ip(),
            'logged_at'   => now(),
        ]);

        return redirect()->route('admin.users.index')
                         ->with('success', 'User updated successfully.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($user->id === Auth::id(), 403, 'You cannot delete your own account.');

        if ($user->isSuperAdmin() && User::countActiveSuperAdmins() <= 1) {
            abort(403, 'Action blocked: The last active Super Administrator account cannot be deleted or archived.');
        }

        // Prevent ordinary admin from archiving protected accounts
        if (! Auth::user()?->isSuperAdmin() && $user->isProtectedPrivilegedAccount()) {
            ActivityLog::create([
                'user_id'     => Auth::id(),
                'action'      => 'Privileged Account Deletion Blocked',
                'module'      => ActivityLog::MODULE_SECURITY,
                'description' => "Admin attempted unauthorized deletion of protected privileged account [{$user->email}].",
                'severity'    => ActivityLog::SEVERITY_CRITICAL,
                'result'      => ActivityLog::RESULT_BLOCKED,
                'ip_address'  => $request->ip(),
                'logged_at'   => now(),
            ]);
            abort(403, 'Unauthorized. Protected privileged accounts cannot be archived by ordinary administrators.');
        }

        $request->validate([
            'comment' => ['required', 'string', 'filled', 'max:1000'],
        ], [
            'comment.required' => 'A reason / comment is required before archiving a user account.',
            'comment.filled'   => 'The reason / comment cannot be blank or whitespace only.',
            'comment.max'      => 'The reason / comment must not exceed 1,000 characters.',
        ]);

        $comment = trim($request->input('comment'));
        $email   = $user->email;
        $name    = $user->name;
        $user->delete();

        ActivityLog::create([
            'user_id'     => Auth::id(),
            'action'      => 'User Archived',
            'module'      => 'User Management',
            'severity'    => ActivityLog::SEVERITY_WARNING,
            'result'      => ActivityLog::RESULT_SUCCESS,
            'description' => "User account [{$email}] ({$name}) was archived by admin. Reason: {$comment}",
            'ip_address'  => $request->ip(),
            'logged_at'   => now(),
        ]);

        return redirect()->route('admin.users.index')
                         ->with('success', 'User account archived successfully.');
    }

    public function restore(Request $request, int|string $id): RedirectResponse
    {
        $request->validate([
            'comment' => ['required', 'string', 'filled', 'max:1000'],
        ], [
            'comment.required' => 'A reason / comment is required before restoring a user account.',
            'comment.filled'   => 'The reason / comment cannot be blank or whitespace only.',
            'comment.max'      => 'The reason / comment must not exceed 1,000 characters.',
        ]);

        $comment = trim($request->input('comment'));
        $user    = User::onlyTrashed()->findOrFail($id);
        $user->restore();

        ActivityLog::create([
            'user_id'     => Auth::id(),
            'action'      => 'User Restored',
            'module'      => 'User Management',
            'severity'    => ActivityLog::SEVERITY_WARNING,
            'result'      => ActivityLog::RESULT_SUCCESS,
            'description' => "User account [{$user->email}] ({$user->name}) was restored by admin. Reason: {$comment}",
            'ip_address'  => $request->ip(),
            'logged_at'   => now(),
        ]);

        return redirect()->route('admin.users.index')
                         ->with('success', 'User account restored successfully.');
    }

    public function showArchived(int|string $id): View
    {
        $user = User::onlyTrashed()->with('roles')->findOrFail($id);

        // Latest 'User Archived' event for this user — drives the Deletion Information card
        $deletionLog = ActivityLog::where('action', 'User Archived')
            ->where('description', 'LIKE', "%[{$user->email}]%")
            ->with('user')
            ->latest('logged_at')
            ->first();

        // Full archive + restore history in reverse chronological order — drives Account History
        $history = ActivityLog::whereIn('action', ['User Archived', 'User Restored'])
            ->where('description', 'LIKE', "%[{$user->email}]%")
            ->with('user')
            ->latest('logged_at')
            ->get();

        return view('admin.users.archived', compact('user', 'deletionLog', 'history'));
    }

    public function assignRole(Request $request, User $user): RedirectResponse
    {
        abort_if($user->id === Auth::id(), 403, 'You cannot change your own role.');

        if (! Auth::user()?->isSuperAdmin() && $user->isProtectedPrivilegedAccount()) {
            abort(403, 'Unauthorized. Protected accounts cannot have their roles altered by ordinary administrators.');
        }

        $request->validate(['role_id' => 'required|exists:roles,id']);
        $targetRole = Role::findOrFail($request->role_id);

        if (! Auth::user()?->isSuperAdmin() && in_array($targetRole->slug, ['super_admin', 'admin'], true)) {
            ActivityLog::create([
                'user_id'     => Auth::id(),
                'action'      => 'Privilege Escalation Blocked',
                'module'      => ActivityLog::MODULE_SECURITY,
                'description' => "Admin attempted unauthorized assignment of privileged role [{$targetRole->slug}] to [{$user->email}].",
                'severity'    => ActivityLog::SEVERITY_CRITICAL,
                'result'      => ActivityLog::RESULT_BLOCKED,
                'ip_address'  => $request->ip(),
                'logged_at'   => now(),
            ]);
            abort(403, 'Unauthorized. Privileged administrative roles cannot be assigned through ordinary administration.');
        }

        $user->roles()->sync([$targetRole->id]);

        ActivityLog::create([
            'user_id'     => Auth::id(),
            'action'      => 'Role Assignment Changed',
            'module'      => 'User Management',
            'severity'    => ActivityLog::SEVERITY_WARNING,
            'result'      => ActivityLog::RESULT_SUCCESS,
            'description' => "Role assignment changed to [{$targetRole->name}] for user [{$user->email}] ({$user->name}) by admin.",
            'ip_address'  => request()->ip(),
            'logged_at'   => now(),
        ]);

        return back()->with('success', 'Role assigned successfully.');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        if (! Auth::user()?->isSuperAdmin() && $user->isProtectedPrivilegedAccount()) {
            abort(403, 'Unauthorized. Protected privileged accounts cannot be reset by ordinary administrators.');
        }

        $reason = $request->input('reason', 'Administrative password reset request');

        // Dispatches secure password reset token via email rather than setting hardcoded credentials
        $token = \Illuminate\Support\Facades\Password::broker()->createToken($user);
        $user->sendPasswordResetNotification($token);

        ActivityLog::create([
            'user_id'     => Auth::id(),
            'action'      => 'Password Reset',
            'module'      => 'User Management',
            'severity'    => ActivityLog::SEVERITY_WARNING,
            'result'      => ActivityLog::RESULT_SUCCESS,
            'description' => "Password reset notification dispatched for user [{$user->email}] ({$user->name}) by admin. Reason: {$reason}",
            'ip_address'  => request()->ip(),
            'logged_at'   => now(),
        ]);

        return redirect()->route('admin.users.index')
                         ->with('success', "Password reset notification successfully dispatched to [{$user->email}].");
    }

    public function unlockAccount(Request $request, User $user): RedirectResponse
    {
        if (! Auth::user()?->isSuperAdmin() && $user->isProtectedPrivilegedAccount() && $user->id !== Auth::id()) {
            abort(403, 'Unauthorized. Protected privileged accounts cannot be unlocked by ordinary administrators.');
        }

        $reason = $request->input('reason', 'Administrative account unlock');

        $user->resetLoginAttempts();
        $user->update([
            'is_active' => true,
        ]);

        ActivityLog::create([
            'user_id'     => Auth::id(),
            'action'      => 'Account Unlocked',
            'module'      => 'User Management',
            'severity'    => ActivityLog::SEVERITY_INFO,
            'result'      => ActivityLog::RESULT_SUCCESS,
            'description' => "Locked account for user [{$user->email}] ({$user->name}) was unlocked by admin. Reason: {$reason}",
            'ip_address'  => request()->ip(),
            'logged_at'   => now(),
        ]);

        return redirect()->route('admin.users.index')
                         ->with('success', "User account for {$user->name} has been unlocked successfully.");
    }

    public function terminateSession(Request $request, User $user): RedirectResponse
    {
        if (! Auth::user()?->isSuperAdmin() && $user->isProtectedPrivilegedAccount() && $user->id !== Auth::id()) {
            abort(403, 'Unauthorized.');
        }

        $user->clearActiveSession();

        ActivityLog::create([
            'user_id'     => Auth::id(),
            'action'      => 'User Session Terminated',
            'module'      => 'User Management',
            'severity'    => ActivityLog::SEVERITY_WARNING,
            'result'      => ActivityLog::RESULT_SUCCESS,
            'description' => "Active session terminated for user [{$user->email}] by administrator.",
            'ip_address'  => $request->ip(),
            'logged_at'   => now(),
        ]);

        return back()->with('success', "Active session for [{$user->email}] terminated.");
    }
}
