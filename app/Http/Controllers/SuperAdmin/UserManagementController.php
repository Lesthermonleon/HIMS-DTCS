<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Notification;
use App\Models\OtpVerification;
use App\Models\Patient;
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

class UserManagementController extends Controller
{
    /**
     * Supported role slugs in DTCS subsystem.
     */
    public const SUPPORTED_ROLES = [
        'super_admin'    => 'Super Administrator',
        'admin'          => 'System Administrator',
        'doctor'         => 'Doctor / Clinician',
        'med-tech'       => 'Medical Technologist (LIS)',
        'rad-tech'       => 'Radiologic Technologist (RIS)',
        'radiologist'    => 'Radiologist (RIS)',
        'pharmacist'     => 'Pharmacist (PMS)',
        'dietitian'      => 'Dietitian / Nutritionist (DNMS)',
        'or-coordinator' => 'OR Coordinator (SORS)',
        'or-staff'       => 'OR Staff (SORS)',
        'patient'        => 'Patient Portal User',
    ];

    /**
     * Display a paginated listing of all user accounts with search and filters.
     */
    public function index(Request $request): View
    {
        $status     = $request->query('status');
        $roleSlug   = $request->query('role');
        $department = $request->query('department');
        $search     = trim((string) $request->query('search', ''));

        $query = User::with('roles');

        if ($status === 'archived') {
            $query->onlyTrashed();
        } else {
            $query->withTrashed();
            if ($status === 'active') {
                $query->whereNull('deleted_at')->where('is_active', true)->whereNull('locked_at');
            } elseif ($status === 'inactive') {
                $query->whereNull('deleted_at')->where('is_active', false)->whereNull('locked_at');
            } elseif ($status === 'locked') {
                $query->whereNull('deleted_at')->whereNotNull('locked_at');
            }
        }

        if ($roleSlug && array_key_exists($roleSlug, self::SUPPORTED_ROLES)) {
            $query->whereHas('roles', fn($q) => $q->where('slug', $roleSlug));
        }

        if ($department) {
            $query->where('department', $department);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('employee_id', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $users = $query->latest('id')->paginate(15)->withQueryString();

        $stats = [
            'total'       => User::withTrashed()->count(),
            'super_admin' => User::whereHas('roles', fn($q) => $q->where('slug', 'super_admin'))->withTrashed()->count(),
            'admin'       => User::whereHas('roles', fn($q) => $q->where('slug', 'admin'))->withTrashed()->count(),
            'doctor'      => User::whereHas('roles', fn($q) => $q->where('slug', 'doctor'))->withTrashed()->count(),
            'staff'       => User::whereHas('roles', fn($q) => $q->whereIn('slug', [
                'med-tech', 'rad-tech', 'radiologist', 'pharmacist', 'dietitian', 'or-coordinator'
            ]))->withTrashed()->count(),
            'patient'     => User::whereHas('roles', fn($q) => $q->where('slug', 'patient'))->withTrashed()->count(),
            'active'      => User::whereNull('deleted_at')->where('is_active', true)->whereNull('locked_at')->count(),
            'inactive'    => User::whereNull('deleted_at')->where('is_active', false)->whereNull('locked_at')->count(),
            'locked'      => User::whereNull('deleted_at')->whereNotNull('locked_at')->count(),
            'archived'    => User::onlyTrashed()->count(),
        ];

        $roles = Role::whereIn('slug', array_keys(self::SUPPORTED_ROLES))->get();
        $departments = User::distinct()->whereNotNull('department')->pluck('department')->filter()->sort()->values();

        return view('super_admin.users.index', compact('users', 'stats', 'roles', 'departments', 'status', 'roleSlug', 'department', 'search'));
    }

    /**
     * Show the form for creating a new user account across any supported role.
     */
    public function create(Request $request): View
    {
        $selectedRole = $request->query('role', 'admin');
        $roles        = Role::whereIn('slug', array_keys(self::SUPPORTED_ROLES))->get();
        $departments  = User::distinct()->whereNotNull('department')->pluck('department')->filter()->sort()->values();

        // Candidates for patient linking (existing patients without a portal account)
        $unlinkedPatients = Patient::whereNull('user_id')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->take(50)
            ->get();

        return view('super_admin.users.create', compact('roles', 'departments', 'selectedRole', 'unlinkedPatients'));
    }

    /**
     * Store a newly created user account for any supported role.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'role_slug'             => ['required', 'string', 'in:' . implode(',', array_keys(self::SUPPORTED_ROLES))],
            'name'                  => ['required', 'string', 'max:255'],
            'email'                 => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'employee_id'           => ['nullable', 'string', 'max:50', 'unique:users,employee_id'],
            'department'            => ['nullable', 'string', 'max:100'],
            'phone'                 => ['nullable', 'string', 'max:30'],
            'password_mode'         => ['required', 'in:invite,manual'],
            'password'              => ['required_if:password_mode,manual', 'nullable', 'string', 'min:8'],
            'patient_link_mode'     => ['nullable', 'in:none,existing,new'],
            'patient_id'            => ['nullable', 'exists:patients,id'],
            'patient_dob'           => ['nullable', 'date'],
            'patient_gender'        => ['nullable', 'in:male,female,other'],
            'reason'                => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $role = Role::where('slug', $validated['role_slug'])->firstOrFail();

        $user = DB::transaction(function () use ($validated, $role) {
            $isManual = ($validated['password_mode'] === 'manual');
            $rawSecret = $isManual ? $validated['password'] : Str::random(32);

            $department = $validated['department'] ?? null;
            if (empty($department)) {
                $department = match ($validated['role_slug']) {
                    'super_admin'    => 'Master System Governance',
                    'admin'          => 'Hospital Administration',
                    'doctor'         => 'Clinical Medicine',
                    'med-tech'       => 'Laboratory',
                    'rad-tech'       => 'Radiology',
                    'radiologist'    => 'Radiology',
                    'pharmacist'     => 'Pharmacy',
                    'dietitian'      => 'Nutrition & Dietetics',
                    'or-coordinator' => 'Surgical Suite',
                    'patient'        => 'Patient Portal',
                    default          => 'General Operations',
                };
            }

            $user = User::create([
                'name'              => $validated['name'],
                'email'             => $validated['email'],
                'password'          => Hash::make($rawSecret),
                'employee_id'       => $validated['employee_id'] ?? null,
                'department'        => $department,
                'phone'             => $validated['phone'] ?? null,
                'is_active'         => true,
                'email_verified_at' => now(),
            ]);

            $user->roles()->sync([$role->id]);

            // Handle Patient Portal Account Linking / Creation
            if ($validated['role_slug'] === 'patient') {
                if (!empty($validated['patient_id'])) {
                    $patient = Patient::findOrFail($validated['patient_id']);
                    $patient->update(['user_id' => $user->id]);
                    $user->update(['patient_id' => $patient->id]);
                } elseif (($validated['patient_link_mode'] ?? '') === 'new') {
                    $nameParts = explode(' ', $validated['name'], 2);
                    $firstName = $nameParts[0] ?? $validated['name'];
                    $lastName  = $nameParts[1] ?? 'Patient';

                    $patient = Patient::create([
                        'patient_no'    => 'PAT-' . date('Ymd') . '-' . rand(1000, 9999),
                        'user_id'       => $user->id,
                        'first_name'    => $firstName,
                        'last_name'     => $lastName,
                        'date_of_birth' => $validated['patient_dob'] ?? '1990-01-01',
                        'gender'        => $validated['patient_gender'] ?? 'other',
                        'email'         => $validated['email'],
                        'phone'         => $validated['phone'] ?? null,
                    ]);
                    $user->update(['patient_id' => $patient->id]);
                }
            }

            // Dispatch password setup notification if invite mode
            if (!$isManual) {
                $token = Password::broker()->createToken($user);
                $user->sendPasswordResetNotification($token);
            }

            ActivityLog::create([
                'user_id'     => Auth::id(),
                'action'      => 'User Account Created',
                'module'      => ActivityLog::MODULE_SECURITY,
                'description' => sprintf(
                    'User account [%s] (ID: %d) with role [%s] provisioned by Super Admin. Password mode: %s. Reason: %s',
                    $user->email,
                    $user->id,
                    $role->name,
                    $isManual ? 'Manual Initial Password' : 'Email Invitation Dispatched',
                    $validated['reason']
                ),
                'severity'    => in_array($role->slug, ['super_admin', 'admin'], true) ? ActivityLog::SEVERITY_CRITICAL : ActivityLog::SEVERITY_INFO,
                'result'      => ActivityLog::RESULT_SUCCESS,
                'ip_address'  => request()->ip(),
                'logged_at'   => now(),
            ]);

            return $user;
        });

        return redirect()->route('super-admin.users.index')
            ->with('success', "User account [{$user->email}] successfully created with role [{$role->name}].");
    }

    /**
     * Display detailed account overview, session status, and clinical relationships.
     */
    public function show(User $user): View
    {
        $user->load(['roles.permissions', 'patient']);

        $clinicalBreakdown = $user->getClinicalRecordsBreakdown();
        $hasClinical       = !empty($clinicalBreakdown);

        $activityLogs = ActivityLog::where('user_id', $user->id)
            ->orWhere('description', 'LIKE', "%[{$user->email}]%")
            ->latest('logged_at')
            ->take(30)
            ->get();

        $activeSuperAdminsCount = User::countActiveSuperAdmins();
        $isLastActiveSuperAdmin = ($user->isSuperAdmin() && $user->is_active && $activeSuperAdminsCount <= 1);

        return view('super_admin.users.show', compact('user', 'clinicalBreakdown', 'hasClinical', 'activityLogs', 'isLastActiveSuperAdmin'));
    }

    /**
     * Show the edit account form.
     */
    public function edit(User $user): View
    {
        $user->load(['roles.permissions', 'patient']);
        $roles = Role::whereIn('slug', array_keys(self::SUPPORTED_ROLES))->get();
        $departments = User::distinct()->whereNotNull('department')->pluck('department')->filter()->sort()->values();

        $activeSuperAdminsCount = User::countActiveSuperAdmins();
        $isLastActiveSuperAdmin = ($user->isSuperAdmin() && $user->is_active && $activeSuperAdminsCount <= 1);

        return view('super_admin.users.edit', compact('user', 'roles', 'departments', 'isLastActiveSuperAdmin'));
    }

    /**
     * Update user account information, status, and role.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'email'       => ['required', 'string', 'email', 'max:255', "unique:users,email,{$user->id}"],
            'employee_id' => ['nullable', 'string', 'max:50', "unique:users,employee_id,{$user->id}"],
            'department'  => ['nullable', 'string', 'max:100'],
            'phone'       => ['nullable', 'string', 'max:30'],
            'role_slug'   => ['required', 'string', 'in:' . implode(',', array_keys(self::SUPPORTED_ROLES))],
            'is_active'   => ['nullable', 'boolean'],
            'reason'      => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $newRole = Role::where('slug', $validated['role_slug'])->firstOrFail();
        $currentPrimarySlug = $user->primaryRole;

        // 1. Guard against self-deactivation and self-role demotion
        if ($user->id === Auth::id()) {
            if ($validated['role_slug'] !== $currentPrimarySlug) {
                return back()->withErrors(['role_slug' => 'You cannot change your own assigned role.'])->withInput();
            }
            if ($request->has('is_active') && !$request->boolean('is_active')) {
                return back()->withErrors(['is_active' => 'You cannot deactivate your own account.'])->withInput();
            }
        }

        // 2. Guard against deactivating or demoting the last active Super Admin
        $isLastActiveSuperAdmin = ($user->isSuperAdmin() && $user->is_active && User::countActiveSuperAdmins() <= 1);

        if ($isLastActiveSuperAdmin) {
            if ($validated['role_slug'] !== 'super_admin') {
                return back()->withErrors(['role_slug' => 'Action blocked: Cannot demote the last active Super Administrator.'])->withInput();
            }
            if ($request->has('is_active') && !$request->boolean('is_active')) {
                return back()->withErrors(['is_active' => 'Action blocked: Cannot deactivate the last active Super Administrator.'])->withInput();
            }
        }

        $targetStatus = $request->boolean('is_active');
        $statusChangedToInactive = ($user->is_active && !$targetStatus);

        $user->update([
            'name'        => $validated['name'],
            'email'       => $validated['email'],
            'employee_id' => $validated['employee_id'] ?? null,
            'department'  => $validated['department'] ?? null,
            'phone'       => $validated['phone'] ?? null,
            'is_active'   => $targetStatus,
        ]);

        $user->roles()->sync([$newRole->id]);

        if ($statusChangedToInactive) {
            $user->clearActiveSession();
        }

        ActivityLog::create([
            'user_id'     => Auth::id(),
            'action'      => 'User Account Updated',
            'module'      => ActivityLog::MODULE_SECURITY,
            'description' => sprintf(
                'User [%s] (ID: %d) updated by Super Admin. Role set to [%s], Status: %s. Reason: %s',
                $user->email,
                $user->id,
                $newRole->name,
                $targetStatus ? 'Active' : 'Inactive',
                $validated['reason']
            ),
            'severity'    => in_array($newRole->slug, ['super_admin', 'admin'], true) ? ActivityLog::SEVERITY_CRITICAL : ActivityLog::SEVERITY_WARNING,
            'result'      => ActivityLog::RESULT_SUCCESS,
            'ip_address'  => $request->ip(),
            'logged_at'   => now(),
        ]);

        return redirect()->route('super-admin.users.index')
            ->with('success', "Account [{$user->email}] successfully updated.");
    }

    /**
     * Toggle active/suspended status.
     */
    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        if ($user->id === Auth::id()) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        if ($user->isSuperAdmin() && $user->is_active && User::countActiveSuperAdmins() <= 1) {
            return back()->with('error', 'Action blocked: Cannot deactivate the last active Super Administrator.');
        }

        $newStatus = ! $user->is_active;
        $user->update(['is_active' => $newStatus]);

        if (!$newStatus) {
            $user->clearActiveSession();
        }

        ActivityLog::create([
            'user_id'     => Auth::id(),
            'action'      => $newStatus ? 'User Account Activated' : 'User Account Suspended',
            'module'      => ActivityLog::MODULE_SECURITY,
            'description' => sprintf(
                'User account [%s] %s by Super Admin. Reason: %s',
                $user->email,
                $newStatus ? 'activated' : 'suspended',
                $validated['reason']
            ),
            'severity'    => ActivityLog::SEVERITY_WARNING,
            'result'      => ActivityLog::RESULT_SUCCESS,
            'ip_address'  => $request->ip(),
            'logged_at'   => now(),
        ]);

        return back()->with('success', "Account [{$user->email}] " . ($newStatus ? 'activated.' : 'suspended.'));
    }

    /**
     * Unlock a locked account and reset failed login attempts.
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
            'action'      => 'User Account Unlocked',
            'module'      => ActivityLog::MODULE_SECURITY,
            'description' => sprintf('User account [%s] unlocked by Super Admin. Reason: %s', $user->email, $validated['reason']),
            'severity'    => ActivityLog::SEVERITY_WARNING,
            'result'      => ActivityLog::RESULT_SUCCESS,
            'ip_address'  => $request->ip(),
            'logged_at'   => now(),
        ]);

        return back()->with('success', "Account [{$user->email}] successfully unlocked.");
    }

    /**
     * Initiate secure password reset for any user account.
     */
    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $token = Password::broker()->createToken($user);
        $user->sendPasswordResetNotification($token);
        $user->clearActiveSession();

        ActivityLog::create([
            'user_id'     => Auth::id(),
            'action'      => 'Password Reset Dispatched',
            'module'      => ActivityLog::MODULE_SECURITY,
            'description' => sprintf('Password reset notification dispatched to [%s] by Super Admin. Reason: %s', $user->email, $validated['reason']),
            'severity'    => ActivityLog::SEVERITY_WARNING,
            'result'      => ActivityLog::RESULT_SUCCESS,
            'ip_address'  => $request->ip(),
            'logged_at'   => now(),
        ]);

        return back()->with('success', "Password reset notification dispatched to [{$user->email}].");
    }

    /**
     * Terminate active session for an account.
     */
    public function terminateSession(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $user->clearActiveSession();

        ActivityLog::create([
            'user_id'     => Auth::id(),
            'action'      => 'User Session Terminated',
            'module'      => ActivityLog::MODULE_SECURITY,
            'description' => sprintf('Active session for user [%s] terminated by Super Admin. Reason: %s', $user->email, $validated['reason'] ?? 'Super Admin action'),
            'severity'    => ActivityLog::SEVERITY_WARNING,
            'result'      => ActivityLog::RESULT_SUCCESS,
            'ip_address'  => $request->ip(),
            'logged_at'   => now(),
        ]);

        return back()->with('success', "Active session for [{$user->email}] has been terminated.");
    }

    /**
     * Soft delete (archive) a user account.
     */
    public function archive(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        if ($user->id === Auth::id()) {
            return back()->with('error', 'You cannot archive your own account.');
        }

        if ($user->isSuperAdmin() && User::countActiveSuperAdmins() <= 1) {
            return back()->with('error', 'Action blocked: Cannot archive the last active Super Administrator.');
        }

        $user->clearActiveSession();
        $user->delete();

        ActivityLog::create([
            'user_id'     => Auth::id(),
            'action'      => 'User Account Archived',
            'module'      => ActivityLog::MODULE_SECURITY,
            'description' => sprintf('User account [%s] archived by Super Admin. Reason: %s', $user->email, $validated['reason']),
            'severity'    => ActivityLog::SEVERITY_CRITICAL,
            'result'      => ActivityLog::RESULT_SUCCESS,
            'ip_address'  => $request->ip(),
            'logged_at'   => now(),
        ]);

        return back()->with('success', "Account [{$user->email}] archived successfully.");
    }

    /**
     * Restore an archived user account.
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
            'action'      => 'User Account Restored',
            'module'      => ActivityLog::MODULE_SECURITY,
            'description' => sprintf('User account [%s] restored by Super Admin. Reason: %s', $user->email, $validated['reason']),
            'severity'    => ActivityLog::SEVERITY_WARNING,
            'result'      => ActivityLog::RESULT_SUCCESS,
            'ip_address'  => $request->ip(),
            'logged_at'   => now(),
        ]);

        return back()->with('success', "Account [{$user->email}] restored successfully.");
    }

    /**
     * Permanently delete a user account only when safe and not bound to clinical records.
     */
    public function forceDelete(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $user = User::withTrashed()->findOrFail($id);

        if ($user->id === Auth::id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        if ($user->isSuperAdmin() && User::countActiveSuperAdmins() <= 1) {
            return back()->with('error', 'Action blocked: Cannot delete the last active Super Administrator.');
        }

        // Strict clinical relationship check
        if ($user->hasLinkedClinicalRecords()) {
            $breakdown = $user->getClinicalRecordsBreakdown();
            $details = collect($breakdown)->map(fn($cnt, $type) => "{$type}: {$cnt}")->implode('; ');

            ActivityLog::create([
                'user_id'     => Auth::id(),
                'action'      => 'Permanent Deletion Blocked (Clinical Records Constraint)',
                'module'      => ActivityLog::MODULE_SECURITY,
                'description' => sprintf(
                    'Super Admin attempted permanent deletion of user [%s] (ID: %d), but action was blocked due to linked clinical records [%s].',
                    $user->email,
                    $user->id,
                    $details
                ),
                'severity'    => ActivityLog::SEVERITY_CRITICAL,
                'result'      => ActivityLog::RESULT_BLOCKED,
                'ip_address'  => $request->ip(),
                'logged_at'   => now(),
            ]);

            return back()->with('error', "Cannot permanently delete user [{$user->email}]. This account is tied to clinical records ({$details}) subject to clinical compliance and statutory medical retention rules. You may archive the account instead.");
        }

        $email = $user->email;
        $name  = $user->name;

        // Cleanly detach safe non-clinical pivots
        $user->roles()->detach();
        OtpVerification::where('user_id', $user->id)->delete();
        Notification::where('user_id', $user->id)->delete();
        DB::table('conversation_participants')->where('user_id', $user->id)->delete();

        $user->forceDelete();

        ActivityLog::create([
            'user_id'     => Auth::id(),
            'action'      => 'User Account Permanently Deleted',
            'module'      => ActivityLog::MODULE_SECURITY,
            'description' => sprintf(
                'User account [%s] (%s) permanently deleted by Super Admin. Verified no linked clinical records. Reason: %s',
                $email,
                $name,
                $validated['reason']
            ),
            'severity'    => ActivityLog::SEVERITY_CRITICAL,
            'result'      => ActivityLog::RESULT_SUCCESS,
            'ip_address'  => $request->ip(),
            'logged_at'   => now(),
        ]);

        return redirect()->route('super-admin.users.index')
            ->with('success', "Account [{$email}] was permanently deleted.");
    }
}
