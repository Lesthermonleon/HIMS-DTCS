<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\PrivilegedAccessRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class DoctorAccountController extends Controller
{
    /**
     * Display doctors list with status, lockout, and archived filters.
     */
    public function index(Request $request): View
    {
        $status = $request->query('status');
        $query = User::whereHas('roles', fn($q) => $q->where('slug', 'doctor'))
            ->withTrashed()
            ->with(['roles']);

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

        if ($request->filled('department')) {
            $query->where('department', $request->query('department'));
        }

        $doctors = $query->orderBy('name')->paginate(15)->withQueryString();

        $stats = [
            'total'    => User::whereHas('roles', fn($q) => $q->where('slug', 'doctor'))->withTrashed()->count(),
            'active'   => User::whereHas('roles', fn($q) => $q->where('slug', 'doctor'))->whereNull('deleted_at')->where('is_active', true)->whereNull('locked_at')->count(),
            'inactive' => User::whereHas('roles', fn($q) => $q->where('slug', 'doctor'))->whereNull('deleted_at')->where('is_active', false)->whereNull('locked_at')->count(),
            'locked'   => User::whereHas('roles', fn($q) => $q->where('slug', 'doctor'))->whereNull('deleted_at')->whereNotNull('locked_at')->count(),
            'archived' => User::whereHas('roles', fn($q) => $q->where('slug', 'doctor'))->onlyTrashed()->count(),
        ];

        $departments = User::whereHas('roles', fn($q) => $q->where('slug', 'doctor'))
            ->distinct()
            ->pluck('department')
            ->filter()
            ->sort()
            ->values();

        // Pending provisioning requests submitted by Admin
        $pendingRequests = PrivilegedAccessRequest::where('request_type', 'doctor_provisioning')
            ->where('status', 'pending')
            ->with('requestedBy')
            ->get();

        return view('admin.doctors.index', compact('doctors', 'stats', 'departments', 'status', 'pendingRequests'));
    }

    /**
     * Submit a doctor provisioning request to Super Admin for approval.
     */
    public function requestProvisioning(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'             => ['required', 'string', 'max:255'],
            'email'            => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'department'       => ['required', 'string', 'max:100'],
            'employee_id'      => ['nullable', 'string', 'max:50', 'unique:users,employee_id'],
            'phone'            => ['nullable', 'string', 'max:30'],
            'requested_reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $accessRequest = PrivilegedAccessRequest::create([
            'request_type'     => 'doctor_provisioning',
            'payload'          => [
                'name'        => $validated['name'],
                'email'       => $validated['email'],
                'department'  => $validated['department'],
                'employee_id' => $validated['employee_id'] ?? null,
                'phone'       => $validated['phone'] ?? null,
            ],
            'status'           => 'pending',
            'requested_by'     => Auth::id(),
            'requested_reason' => $validated['requested_reason'],
        ]);

        ActivityLog::create([
            'user_id'     => Auth::id(),
            'action'      => 'Doctor Provisioning Requested',
            'module'      => 'User Management',
            'description' => sprintf(
                'Admin submitted Doctor provisioning request #%d for Dr. %s [%s] (Dept: %s). Reason: %s',
                $accessRequest->id,
                $validated['name'],
                $validated['email'],
                $validated['department'],
                $validated['requested_reason']
            ),
            'severity'    => ActivityLog::SEVERITY_INFO,
            'result'      => ActivityLog::RESULT_SUCCESS,
            'logged_at'   => now(),
        ]);

        return back()->with('success', "Doctor provisioning request for Dr. {$validated['name']} submitted for Super Admin authorization.");
    }

    /**
     * Toggle active/inactive status for a doctor.
     */
    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        if (! $user->hasRole('doctor')) {
            abort(403, 'Unauthorized target role.');
        }

        $newStatus = ! $user->is_active;
        $user->update(['is_active' => $newStatus]);

        if (! $newStatus) {
            $user->clearActiveSession();
        }

        ActivityLog::create([
            'user_id'     => Auth::id(),
            'action'      => $newStatus ? 'Doctor Account Activated' : 'Doctor Account Deactivated',
            'module'      => 'User Management',
            'description' => sprintf(
                'Doctor account [%s] %s by admin. Reason: %s',
                $user->email,
                $newStatus ? 'activated' : 'deactivated',
                $validated['reason']
            ),
            'severity'    => ActivityLog::SEVERITY_WARNING,
            'result'      => ActivityLog::RESULT_SUCCESS,
            'logged_at'   => now(),
        ]);

        return back()->with('success', "Doctor account for Dr. {$user->name} status updated.");
    }

    /**
     * Unlock a locked doctor account.
     */
    public function unlock(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        if (! $user->hasRole('doctor')) {
            abort(403, 'Unauthorized target role.');
        }

        $user->resetLoginAttempts();
        $user->update(['is_active' => true]);

        ActivityLog::create([
            'user_id'     => Auth::id(),
            'action'      => 'Doctor Account Unlocked',
            'module'      => 'User Management',
            'description' => sprintf('Doctor account [%s] unlocked by admin. Reason: %s', $user->email, $validated['reason']),
            'severity'    => ActivityLog::SEVERITY_INFO,
            'result'      => ActivityLog::RESULT_SUCCESS,
            'logged_at'   => now(),
        ]);

        return back()->with('success', "Doctor account for Dr. {$user->name} has been unlocked.");
    }

    /**
     * Dispatch password reset link for doctor.
     */
    public function sendPasswordReset(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        if (! $user->hasRole('doctor')) {
            abort(403, 'Unauthorized target role.');
        }

        $token = Password::broker()->createToken($user);
        $user->sendPasswordResetNotification($token);

        ActivityLog::create([
            'user_id'     => Auth::id(),
            'action'      => 'Doctor Password Reset Initiated',
            'module'      => 'User Management',
            'severity'    => ActivityLog::SEVERITY_WARNING,
            'result'      => ActivityLog::RESULT_SUCCESS,
            'description' => sprintf('Password reset notification sent to Dr. [%s] by admin. Reason: %s', $user->email, $validated['reason']),
            'severity'    => ActivityLog::SEVERITY_WARNING,
            'result'      => ActivityLog::RESULT_SUCCESS,
            'logged_at'   => now(),
        ]);

        return back()->with('success', "Password reset notification dispatched to Dr. {$user->name} [{$user->email}].");
    }
}
