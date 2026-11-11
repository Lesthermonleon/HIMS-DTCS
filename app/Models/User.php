<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * User model extended with RBAC helpers and hospital-specific fields.
 */
class User extends Authenticatable
{
    use Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'employee_id',
        'department',
        'patient_id',
        'phone',
        'avatar',
        'is_active',
        'login_token',
        'active_session_id',
        'last_activity_at',
        'failed_attempts',
        'locked_at',
        'lockout_until',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
            'locked_at'         => 'datetime',
            'lockout_until'     => 'datetime',
            'last_activity_at'  => 'datetime',
        ];
    }

    // ──────────────────────────── Relationships ────────────────────────────

    /**
     * Roles assigned to this user (many-to-many).
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    // Clinical & System relationships
    public function labRequests(): HasMany           { return $this->hasMany(LabRequest::class, 'doctor_id'); }
    public function radiologyRequests(): HasMany     { return $this->hasMany(RadiologyRequest::class, 'doctor_id'); }
    public function prescriptions(): HasMany         { return $this->hasMany(Prescription::class, 'doctor_id'); }
    public function surgeryRequests(): HasMany       { return $this->hasMany(SurgeryRequest::class, 'doctor_id'); }
    public function dietRequests(): HasMany          { return $this->hasMany(DietRequest::class, 'doctor_id'); }

    // Aliases for report withCount (same FK, distinct names for clarity)
    public function labRequestsAsDoctor(): HasMany         { return $this->hasMany(LabRequest::class, 'doctor_id'); }
    public function radiologyRequestsAsDoctor(): HasMany   { return $this->hasMany(RadiologyRequest::class, 'doctor_id'); }
    public function prescriptionsAsDoctor(): HasMany       { return $this->hasMany(Prescription::class, 'doctor_id'); }
    public function surgeryRequestsAsDoctor(): HasMany     { return $this->hasMany(SurgeryRequest::class, 'doctor_id'); }

    // Patient & Appointment relationships
    public function patient(): BelongsTo             { return $this->belongsTo(Patient::class, 'patient_id'); }
    public function appointmentsAsDoctor(): HasMany  { return $this->hasMany(Appointment::class, 'doctor_id'); }

    public function activityLogs(): HasMany          { return $this->hasMany(ActivityLog::class); }
    public function notifications(): HasMany         { return $this->hasMany(Notification::class); }
    public function conversations(): BelongsToMany   { return $this->belongsToMany(Conversation::class, 'conversation_participants')->withPivot('last_read_at')->withTimestamps(); }

    // ──────────────────────────── RBAC Helpers ────────────────────────────

    /**
     * Check if the user has a specific role by slug.
     */
    public function hasRole(string $roleSlug): bool
    {
        return $this->roles()->where('roles.slug', $roleSlug)->exists();
    }

    /**
     * Check if the user has any of the given roles.
     */
    public function hasAnyRole(array $roleSlugs): bool
    {
        return $this->roles()->whereIn('roles.slug', $roleSlugs)->exists();
    }

    /**
     * Check if the user has a specific permission by slug via their assigned roles.
     */
    public function hasPermission(string $permissionSlug): bool
    {
        return $this->roles()->whereHas('permissions', function ($query) use ($permissionSlug) {
            $query->where('permissions.slug', $permissionSlug);
        })->exists();
    }

    /**
     * Check if the user is a Super Administrator.
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    /**
     * Check if the user is a System Administrator.
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * Check if the user is a Doctor.
     */
    public function isDoctor(): bool
    {
        return $this->hasRole('doctor');
    }

    /**
     * Check if the user is a Patient.
     */
    public function isPatient(): bool
    {
        return $this->hasRole('patient');
    }

    /**
     * Check if this user account is a protected privileged account.
     * Protected accounts (super_admin, admin) cannot be modified, unlocked,
     * archived, or reset by ordinary admins or unprivileged users.
     */
    public function isProtectedPrivilegedAccount(): bool
    {
        return $this->hasAnyRole(['super_admin', 'admin']);
    }

    /**
     * Count currently active, non-archived Super Administrator accounts.
     */
    public static function countActiveSuperAdmins(): int
    {
        return static::whereHas('roles', fn($q) => $q->where('slug', 'super_admin'))
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->count();
    }

    /**
     * Check and summarize all clinical and medical records associated with this account.
     * Clinical records are subject to statutory medical retention rules and prevent permanent deletion.
     */
    public function getClinicalRecordsBreakdown(): array
    {
        $breakdown = [];

        $labReq = LabRequest::where('doctor_id', $this->id)->count();
        if ($labReq > 0) $breakdown['Lab Requests (as Clinician)'] = $labReq;

        $labRes = LabResult::where('technologist_id', $this->id)
            ->orWhere('validated_by', $this->id)
            ->orWhere('released_by', $this->id)
            ->count();
        if ($labRes > 0) $breakdown['Lab Results (as Technologist/Validator)'] = $labRes;

        $radReq = RadiologyRequest::where('doctor_id', $this->id)->count();
        if ($radReq > 0) $breakdown['Radiology Requests (as Clinician)'] = $radReq;

        $radRep = RadiologyReport::where('radiologist_id', $this->id)
            ->orWhere('approved_by', $this->id)
            ->orWhere('released_by', $this->id)
            ->count();
        if ($radRep > 0) $breakdown['Radiology Reports (as Radiologist/Approver)'] = $radRep;

        $rx = Prescription::where('doctor_id', $this->id)
            ->orWhere('verified_by', $this->id)
            ->count();
        if ($rx > 0) $breakdown['Prescriptions (as Clinician/Verifier)'] = $rx;

        $dispense = DispensingRecord::where('pharmacist_id', $this->id)->count();
        if ($dispense > 0) $breakdown['Dispensing Records (as Pharmacist)'] = $dispense;

        $surgeryReq = SurgeryRequest::where('doctor_id', $this->id)->count();
        if ($surgeryReq > 0) $breakdown['Surgery Requests (as Clinician)'] = $surgeryReq;

        $surgicalTeam = SurgicalTeamMember::where('user_id', $this->id)->count();
        if ($surgicalTeam > 0) $breakdown['Surgical Team Assignments'] = $surgicalTeam;

        $dietReq = DietRequest::where('doctor_id', $this->id)->count();
        if ($dietReq > 0) $breakdown['Diet Requests (as Clinician)'] = $dietReq;

        $dietPlans = DietPlan::where('dietitian_id', $this->id)->count();
        if ($dietPlans > 0) $breakdown['Diet Plans (as Dietitian)'] = $dietPlans;

        $appointments = Appointment::where('doctor_id', $this->id)->count();
        if ($appointments > 0) $breakdown['Outpatient Appointments (as Doctor)'] = $appointments;

        $patientRecords = Patient::where('user_id', $this->id)->count();
        if ($this->patient_id) $patientRecords++;
        if ($patientRecords > 0) $breakdown['Patient Master Records'] = $patientRecords;

        return $breakdown;
    }

    /**
     * Determine if this user account has any linked clinical records.
     */
    public function hasLinkedClinicalRecords(): bool
    {
        return array_sum($this->getClinicalRecordsBreakdown()) > 0;
    }

    /**
     * Check if the user can access the full Patient Information module (Admin or Doctor).
     * Super Admin explicitly does NOT inherit automatic clinical or patient access.
     */
    public function canAccessPatients(): bool
    {
        return $this->hasAnyRole(['admin', 'doctor']);
    }

    /**
     * Resolve the user's primary role model deterministically.
     * Precedence: super_admin > admin > doctor > staff roles > patient.
     */
    public function primaryRoleModel(): ?Role
    {
        $roles = $this->roles;
        if ($roles->isEmpty()) {
            return null;
        }

        if ($superAdmin = $roles->firstWhere('slug', 'super_admin')) {
            return $superAdmin;
        }
        if ($admin = $roles->firstWhere('slug', 'admin')) {
            return $admin;
        }
        if ($doctor = $roles->firstWhere('slug', 'doctor')) {
            return $doctor;
        }

        // Professional staff roles take precedence over patient role if dual-assigned
        $staffRole = $roles->first(fn($r) => $r->slug !== 'patient');
        if ($staffRole) {
            return $staffRole;
        }

        return $roles->first();
    }

    /**
     * Get the primary role slug for the user.
     */
    public function getPrimaryRoleAttribute(): ?string
    {
        return $this->primaryRoleModel()?->slug;
    }

    /**
     * Get the primary role name for display.
     */
    public function getRoleNameAttribute(): string
    {
        return $this->primaryRoleModel()?->name ?? 'No Role';
    }

    // ──────────────────────── Login Security Helpers ────────────────────────

    /**
     * Check if the account is permanently locked (requires admin unlock).
     */
    public function isLockedOut(): bool
    {
        return $this->locked_at !== null;
    }

    /**
     * Check if the account is in a timed cooldown period.
     */
    public function isCoolingDown(): bool
    {
        return $this->lockout_until !== null && $this->lockout_until->isFuture();
    }

    /**
     * Get remaining cooldown seconds.
     */
    public function getCooldownSeconds(): int
    {
        if (! $this->isCoolingDown()) {
            return 0;
        }

        return (int) now()->diffInSeconds($this->lockout_until, false);
    }

    /**
     * Reset all login attempt tracking (called on successful login or admin unlock).
     */
    public function resetLoginAttempts(): void
    {
        $this->update([
            'failed_attempts' => 0,
            'locked_at'       => null,
            'lockout_until'   => null,
        ]);
    }

    // ──────────────────────── Single Active Session Helpers ────────────────────────

    /**
     * Check if the user account currently has a valid active session on another browser/device.
     */
    public function hasActiveSession(?string $currentSessionId = null): bool
    {
        if (empty($this->active_session_id)) {
            return false;
        }

        // If checking from the same session/browser, it is NOT a duplicate session
        if ($currentSessionId !== null && $this->active_session_id === $currentSessionId) {
            return false;
        }

        // Check session expiration against SESSION_LIFETIME configuration (default 120 mins)
        $lifetimeMinutes = (int) config('session.lifetime', 120);

        if ($this->last_activity_at === null || $this->last_activity_at->lt(now()->subMinutes($lifetimeMinutes))) {
            // Previous session has expired / dead — clean stale record
            $this->clearActiveSession();
            return false;
        }

        return true;
    }

    /**
     * Register a new active session for this user.
     */
    public function setActiveSession(string $sessionId): void
    {
        $this->update([
            'active_session_id' => $sessionId,
            'last_activity_at'  => now(),
        ]);
    }

    /**
     * Clear active session registration on logout or expiration.
     */
    public function clearActiveSession(?string $currentSessionId = null): void
    {
        // Prevent an old or stale session from clearing a newer active session
        if ($currentSessionId !== null && $this->active_session_id !== $currentSessionId) {
            return;
        }

        $this->update([
            'active_session_id' => null,
            // Keep last_activity_at timestamp intact so recent activity remains accurate
        ]);
    }

    /**
     * Update the last activity timestamp for the active session.
     */
    public function touchLastActivity(?string $sessionId = null): void
    {
        if ($sessionId !== null && $this->active_session_id !== $sessionId) {
            return;
        }

        $this->update([
            'last_activity_at' => now(),
        ]);
    }

    /**
     * Send password reset notification using DTCS HIMS custom notification & Gmail SMTP.
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new \App\Notifications\ResetPasswordNotification($token));
    }
}

