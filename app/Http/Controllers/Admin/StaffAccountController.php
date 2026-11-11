<?php

namespace App\Http\Controllers\Admin;

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

class StaffAccountController extends Controller
{
    public const STAFF_ROLE_SLUGS = [
        'med-tech',
        'rad-tech',
        'radiologist',
        'pharmacist',
        'dietitian',
        'or-coordinator',
        'or-staff',
    ];

    /**
     * Display staff directory with role, department, status, and archive filters.
     */
    public function index(Request $request): View
    {
        $status = $request->query('status');
        $roleSlug = $request->query('role');
        $department = $request->query('department');

        $query = User::whereHas('roles', fn($q) => $q->whereIn('slug', self::STAFF_ROLE_SLUGS))
            ->withTrashed()
            ->with('roles');

        if ($roleSlug && in_array($roleSlug, self::STAFF_ROLE_SLUGS, true)) {
            $query->whereHas('roles', fn($q) => $q->where('slug', $roleSlug));
        }

        if ($department) {
            $query->where('department', $department);
        }

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
            $query->where(fn($q) => $q->where('name', 'like', $term)->orWhere('email', 'like', $term)->orWhere('employee_id', 'like', $term));
        }

        $staff = $query->orderBy('name')->paginate(15)->withQueryString();

        $stats = [
            'total'    => User::whereHas('roles', fn($q) => $q->whereIn('slug', self::STAFF_ROLE_SLUGS))->withTrashed()->count(),
            'active'   => User::whereHas('roles', fn($q) => $q->whereIn('slug', self::STAFF_ROLE_SLUGS))->whereNull('deleted_at')->where('is_active', true)->whereNull('locked_at')->count(),
            'inactive' => User::whereHas('roles', fn($q) => $q->whereIn('slug', self::STAFF_ROLE_SLUGS))->whereNull('deleted_at')->where('is_active', false)->whereNull('locked_at')->count(),
            'locked'   => User::whereHas('roles', fn($q) => $q->whereIn('slug', self::STAFF_ROLE_SLUGS))->whereNull('deleted_at')->whereNotNull('locked_at')->count(),
            'archived' => User::whereHas('roles', fn($q) => $q->whereIn('slug', self::STAFF_ROLE_SLUGS))->onlyTrashed()->count(),
        ];

        $staffRoles = Role::whereIn('slug', self::STAFF_ROLE_SLUGS)->get();
        $departments = User::whereHas('roles', fn($q) => $q->whereIn('slug', self::STAFF_ROLE_SLUGS))
            ->distinct()
            ->pluck('department')
            ->filter()
            ->sort()
            ->values();

        return view('admin.staff.index', compact('staff', 'stats', 'staffRoles', 'departments', 'status', 'roleSlug'));
    }

    /**
     * Store a new staff account and dispatch password setup notification.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'email'       => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'role_slug'   => ['required', 'in:' . implode(',', self::STAFF_ROLE_SLUGS)],
            'department'  => ['required', 'string', 'max:100'],
            'employee_id' => ['nullable', 'string', 'max:50', 'unique:users,employee_id'],
            'phone'       => ['nullable', 'string', 'max:30'],
            'reason'      => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $role = Role::where('slug', $validated['role_slug'])->firstOrFail();

        $user = DB::transaction(function () use ($validated, $role) {
            $randomSecret = Str::random(32);

            $user = User::create([
                'name'              => $validated['name'],
                'email'             => $validated['email'],
                'password'          => Hash::make($randomSecret),
                'department'        => $validated['department'],
                'employee_id'       => $validated['employee_id'] ?? null,
                'phone'             => $validated['phone'] ?? null,
                'is_active'         => true,
                'email_verified_at' => now(),
            ]);

            $user->roles()->sync([$role->id]);

            // Dispatch password setup token
            $token = Password::broker()->createToken($user);
            $user->sendPasswordResetNotification($token);

            ActivityLog::create([
                'user_id'     => Auth::id(),
                'action'      => 'Staff Account Created',
                'module'      => 'User Management',
                'description' => sprintf(
                    'Staff account [%s] (%s) created with role [%s]. Reason: %s. Password setup dispatched.',
                    $user->email,
                    $user->name,
                    $role->name,
                    $validated['reason']
                ),
                'severity'    => ActivityLog::SEVERITY_INFO,
                'result'      => ActivityLog::RESULT_SUCCESS,
                'logged_at'   => now(),
            ]);

            return $user;
        });

        return back()->with('success', "Staff account for {$user->name} created. Password setup notification dispatched.");
    }

    /**
     * Toggle staff active status.
     */
    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        if (! $user->hasAnyRole(self::STAFF_ROLE_SLUGS)) {
            abort(403, 'Unauthorized target role.');
        }

        $newStatus = ! $user->is_active;
        $user->update(['is_active' => $newStatus]);

        if (! $newStatus) {
            $user->clearActiveSession();
        }

        ActivityLog::create([
            'user_id'     => Auth::id(),
            'action'      => $newStatus ? 'Staff Account Activated' : 'Staff Account Deactivated',
            'module'      => 'User Management',
            'description' => sprintf('Staff account [%s] %s by admin. Reason: %s', $user->email, $newStatus ? 'activated' : 'deactivated', $validated['reason']),
            'severity'    => ActivityLog::SEVERITY_WARNING,
            'result'      => ActivityLog::RESULT_SUCCESS,
            'logged_at'   => now(),
        ]);

        return back()->with('success', "Staff account for {$user->name} status updated.");
    }

    /**
     * Unlock a locked staff account.
     */
    public function unlock(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        if (! $user->hasAnyRole(self::STAFF_ROLE_SLUGS)) {
            abort(403, 'Unauthorized target role.');
        }

        $user->resetLoginAttempts();
        $user->update(['is_active' => true]);

        ActivityLog::create([
            'user_id'     => Auth::id(),
            'action'      => 'Staff Account Unlocked',
            'module'      => 'User Management',
            'description' => sprintf('Staff account [%s] unlocked by admin. Reason: %s', $user->email, $validated['reason']),
            'severity'    => ActivityLog::SEVERITY_INFO,
            'result'      => ActivityLog::RESULT_SUCCESS,
            'logged_at'   => now(),
        ]);

        return back()->with('success', "Staff account for {$user->name} unlocked successfully.");
    }

    /**
     * Send password reset link to a staff member.
     */
    public function sendPasswordReset(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        if (! $user->hasAnyRole(self::STAFF_ROLE_SLUGS)) {
            abort(403, 'Unauthorized target role.');
        }

        $token = Password::broker()->createToken($user);
        $user->sendPasswordResetNotification($token);

        ActivityLog::create([
            'user_id'     => Auth::id(),
            'action'      => 'Staff Password Reset Initiated',
            'module'      => 'User Management',
            'severity'    => ActivityLog::SEVERITY_WARNING,
            'result'      => ActivityLog::RESULT_SUCCESS,
            'description' => sprintf('Password reset notification sent to [%s] by admin. Reason: %s', $user->email, $validated['reason']),
            'severity'    => ActivityLog::SEVERITY_WARNING,
            'result'      => ActivityLog::RESULT_SUCCESS,
            'logged_at'   => now(),
        ]);

        return back()->with('success', "Password reset notification dispatched to {$user->email}.");
    }
}
