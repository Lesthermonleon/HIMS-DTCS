<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\DietPlan;
use App\Models\DietRequest;
use App\Models\DispensingRecord;
use App\Models\LabRequest;
use App\Models\LabResult;
use App\Models\OperatingRoom;
use App\Models\Patient;
use App\Models\PharmacyPosTransaction;
use App\Models\Prescription;
use App\Models\RadiologyReport;
use App\Models\RadiologyRequest;
use App\Models\Role;
use App\Models\SurgeryRequest;
use App\Models\SurgerySchedule;
use App\Models\User;
use App\Services\Pharmacy\Contracts\MedicationStockProviderInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * DashboardController — serves role-specific dashboard views.
 */
class DashboardController extends Controller
{
    /** System Administrator dashboard. */
    public function admin(): \Illuminate\View\View
    {
        return view('dashboard.admin', $this->getAdminDashboardData());
    }

    /**
     * Helper to build admin dashboard payload.
     *
     * @return array<string, mixed>
     */
    private function getAdminDashboardData(): array
    {
        $todayStr = now()->toDateString();

        // ── 1. Summary Statistics ──
        $unassignedUsersCount = User::whereDoesntHave('roles')->count();
        $inactiveUsersCount   = User::where('is_active', '!=', 1)->whereNull('locked_at')->count();
        $lockedAccountsCount  = User::whereNotNull('locked_at')->count();
        $activeUsersCount     = User::where('is_active', 1)->whereNull('locked_at')->count();
        $failedLoginsCount    = (int) User::where('failed_attempts', '>', 0)->sum('failed_attempts');
        $failedUsersCount     = User::where('failed_attempts', '>', 0)->count();

        // ── 2. Module Overview (Pending, In Progress, Completed, Total) ──
        $lisPending     = LabRequest::where('status', 'Pending')->count();
        $lisInProgress  = LabRequest::where('status', 'In Progress')->count();
        $lisCompleted   = LabRequest::where('status', 'Completed')->count();

        $risPending     = RadiologyRequest::where('status', 'Pending')->count();
        $risInProgress  = RadiologyRequest::whereIn('status', ['Scheduled', 'In Progress'])->count();
        $risCompleted   = RadiologyRequest::where('status', 'Completed')->count();

        $pmsPending     = Prescription::where('status', 'Pending')->count();
        $pmsInProgress  = Prescription::whereIn('status', ['Verified', 'Partially Dispensed'])->count();
        $pmsCompleted   = Prescription::where('status', 'Dispensed')->count();

        $sorsPending    = SurgeryRequest::where('status', 'Pending')->count();
        $sorsInProgress = SurgeryRequest::whereIn('status', ['Scheduled', 'In Progress'])->count();
        $sorsCompleted  = SurgeryRequest::where('status', 'Completed')->count();

        $dnmsPending    = DietRequest::where('status', 'Pending')->count();
        $dnmsInProgress = DietRequest::where('status', 'Active')->count();
        $dnmsCompleted  = DietRequest::where('status', 'Completed')->count();

        $moduleStats = [
            'lis'  => ['name' => 'Laboratory (LIS)',             'pending' => $lisPending,  'in_progress' => $lisInProgress,  'completed' => $lisCompleted,  'route' => 'lab.dashboard'],
            'ris'  => ['name' => 'Radiology (RIS)',              'pending' => $risPending,  'in_progress' => $risInProgress,  'completed' => $risCompleted,  'route' => 'radiology.dashboard'],
            'pms'  => ['name' => 'Pharmacy (PMS)',               'pending' => $pmsPending,  'in_progress' => $pmsInProgress,  'completed' => $pmsCompleted,  'route' => 'pharmacy.dashboard'],
            'sors' => ['name' => 'Surgery (SORS)',               'pending' => $sorsPending, 'in_progress' => $sorsInProgress, 'completed' => $sorsCompleted, 'route' => 'surgery.dashboard'],
            'dnms' => ['name' => 'Nutrition & Dietetics (DNMS)', 'pending' => $dnmsPending, 'in_progress' => $dnmsInProgress, 'completed' => $dnmsCompleted, 'route' => 'diet.dashboard'],
        ];

        $totalPendingClinical   = $lisPending + $risPending + $pmsPending + $sorsPending + $dnmsPending;
        $totalCompletedClinical = $lisCompleted + $risCompleted + $pmsCompleted + $sorsCompleted + $dnmsCompleted;

        // ── 3. System / Security Alerts ──
        $systemAlerts = $this->buildAdminAlerts();

        $stats = [
            'total_users'                 => User::count(),
            'active_users'                => $activeUsersCount,
            'total_patients'              => Patient::count(),
            'pending_admin_tasks'         => $unassignedUsersCount + $inactiveUsersCount,
            'pending_clinical_requests'   => $totalPendingClinical,
            'completed_clinical_requests' => $totalCompletedClinical,
            'operational_alerts'          => $systemAlerts->count(),
            'system_alerts_count'         => $failedUsersCount + $inactiveUsersCount + $unassignedUsersCount,
            'today_activity_count'        => ActivityLog::whereDate('created_at', $todayStr)->count(),
            'inactive_users'              => $inactiveUsersCount,
            'locked_accounts'             => $lockedAccountsCount,
            'unassigned_users'            => $unassignedUsersCount,
            'failed_logins'               => $failedLoginsCount,
        ];

        // ── 4. Users & Roles Breakdown ──
        $usersByRole = Role::withCount('users')->get()->map(fn($r) => [
            'name'  => $r->name,
            'slug'  => $r->slug,
            'count' => $r->users_count,
        ])->values();

        // ── 5. User Presence & System Activity ──
        ['userPresenceStats' => $userPresenceStats, 'userPresenceList' => $userPresenceList] = $this->getUserPresenceData();

        // ── 6. Recent Patients Overview ──
        $recentPatients = Patient::latest('id')->take(5)->get();

        // ── 7. System Activity (7 days) ──
        $adminActivity7d = collect(range(6, 0))->map(fn($d) => [
            'date'  => now()->subDays($d)->format('M d'),
            'count' => ActivityLog::whereDate('created_at', now()->subDays($d)->toDateString())->count(),
        ]);

        // ── 8. Monthly Service Request Trend (6m & 12m) ──
        $adminTrend6m  = $this->getAdminRequestTrend(6);
        $adminTrend12m = $this->getAdminRequestTrend(12);

        // ── 9. Module Volume (all-time totals + pending vs completed for bar chart) ──
        $adminModuleVolume = [
            ['module' => 'Laboratory', 'total' => LabRequest::count(),       'pending' => $lisPending,  'completed' => $lisCompleted],
            ['module' => 'Radiology',  'total' => RadiologyRequest::count(), 'pending' => $risPending,  'completed' => $risCompleted],
            ['module' => 'Pharmacy',   'total' => Prescription::count(),     'pending' => $pmsPending,  'completed' => $pmsCompleted],
            ['module' => 'Surgery',    'total' => SurgeryRequest::count(),   'pending' => $sorsPending, 'completed' => $sorsCompleted],
            ['module' => 'Diet',       'total' => DietRequest::count(),      'pending' => $dnmsPending, 'completed' => $dnmsCompleted],
        ];

        // ── 10. Global Request Status Totals (All 5 DTCS Modules) ──
        $allStatuses = [
            'Pending'     => $totalPendingClinical,
            'In Progress' => $lisInProgress + $risInProgress + $pmsInProgress + $sorsInProgress + $dnmsInProgress,
            'Completed'   => $totalCompletedClinical,
            'Cancelled'   => LabRequest::where('status', 'Cancelled')->count()
                           + RadiologyRequest::where('status', 'Cancelled')->count()
                           + Prescription::where('status', 'Cancelled')->count()
                           + SurgeryRequest::where('status', 'Cancelled')->count()
                           + DietRequest::where('status', 'Cancelled')->count(),
        ];

        // ── 11. Recent Operational Activities ──
        $recentOperationalActivities = ActivityLog::with('user')
            ->whereNotIn('module', ActivityLog::SUPER_ADMIN_MODULES)
            ->orderByDesc('created_at')
            ->take(8)
            ->get();

        return compact(
            'stats',
            'usersByRole',
            'moduleStats',
            'systemAlerts',
            'userPresenceStats',
            'userPresenceList',
            'recentPatients',
            'adminActivity7d',
            'adminTrend6m',
            'adminTrend12m',
            'adminModuleVolume',
            'allStatuses',
            'recentOperationalActivities'
        );
    }

    /**
     * Build user presence stats and list for the admin dashboard.
     *
     * @return array{userPresenceStats: array<string, int>, userPresenceList: \Illuminate\Support\Collection}
     */
    private function getUserPresenceData(): array
    {
        $onlineThreshold = now()->subMinutes(5);
        $recentThreshold = now()->subHours(3);

        $activeUsers = User::with(['roles', 'activityLogs' => fn($q) => $q->latest('created_at')])
            ->where('is_active', true)
            ->get();

        $onlineUsers         = collect();
        $recentlyActiveUsers = collect();
        $otherUsers          = collect();

        foreach ($activeUsers as $user) {
            $lastActive = $user->last_activity_at;
            if (! $lastActive && $user->activityLogs->isNotEmpty()) {
                $lastActive = $user->activityLogs->first()->created_at;
                $user->updateQuietly(['last_activity_at' => $lastActive]);
            }

            $user->effective_last_active = $lastActive;

            $isOnline = $user->active_session_id !== null
                && $lastActive !== null
                && $lastActive->gte($onlineThreshold);

            if ($isOnline) {
                $onlineUsers->push($user);
            } elseif ($lastActive !== null && $lastActive->gte($recentThreshold)) {
                $recentlyActiveUsers->push($user);
            } else {
                $otherUsers->push($user);
            }
        }

        $onlineUsers         = $onlineUsers->sortByDesc(fn($u) => $u->effective_last_active?->timestamp ?? 0)->values();
        $recentlyActiveUsers = $recentlyActiveUsers->sortByDesc(fn($u) => $u->effective_last_active?->timestamp ?? 0)->values();

        $userPresenceStats = [
            'online_count'          => $onlineUsers->count(),
            'recently_active_count' => $recentlyActiveUsers->count(),
            'total_users_count'     => $activeUsers->count(),
        ];

        $userPresenceList = collect();

        foreach ($onlineUsers as $user) {
            $userPresenceList->push([
                'id'                => $user->id,
                'name'              => $user->name,
                'role_name'         => $user->role_name,
                'is_online'         => true,
                'is_recently_active'=> false,
                'last_active_human' => 'Online',
            ]);
        }

        foreach ($recentlyActiveUsers as $user) {
            /** @var \Carbon\Carbon|null $lastActive */
            $lastActive = $user->effective_last_active;
            $userPresenceList->push([
                'id'                => $user->id,
                'name'              => $user->name,
                'role_name'         => $user->role_name,
                'is_online'         => false,
                'is_recently_active'=> true,
                'last_active_human' => $lastActive ? $lastActive->diffForHumans() : 'Recently',
            ]);
        }

        return [
            'userPresenceStats' => $userPresenceStats,
            'userPresenceList'  => $userPresenceList->take(8),
        ];
    }

    /** Build monthly service request totals for the admin trend chart. */
    private function getAdminRequestTrend(int $months): array
    {
        $result = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $date  = now()->subMonths($i);
            $start = $date->copy()->startOfMonth();
            $end   = $date->copy()->endOfMonth();
            $total = LabRequest::whereBetween('created_at', [$start, $end])->count()
                   + RadiologyRequest::whereBetween('created_at', [$start, $end])->count()
                   + Prescription::whereBetween('created_at', [$start, $end])->count()
                   + SurgeryRequest::whereBetween('created_at', [$start, $end])->count()
                   + DietRequest::whereBetween('created_at', [$start, $end])->count();
            $result[] = ['label' => $date->format('M Y'), 'total' => $total];
        }
        return $result;
    }

    /** Build monthly doctor clinical request totals. */
    private function getDoctorRequestTrend(int $doctorId, int $months): array
    {
        $result = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $date  = now()->subMonths($i);
            $start = $date->copy()->startOfMonth();
            $end   = $date->copy()->endOfMonth();
            $total = LabRequest::where('doctor_id', $doctorId)->whereBetween('created_at', [$start, $end])->count()
                   + RadiologyRequest::where('doctor_id', $doctorId)->whereBetween('created_at', [$start, $end])->count()
                   + Prescription::where('doctor_id', $doctorId)->whereBetween('created_at', [$start, $end])->count()
                   + SurgeryRequest::where('doctor_id', $doctorId)->whereBetween('created_at', [$start, $end])->count()
                   + DietRequest::where('doctor_id', $doctorId)->whereBetween('created_at', [$start, $end])->count();
            $result[] = ['label' => $date->format('M Y'), 'total' => $total];
        }
        return $result;
    }

    /** Build monthly doctor consultation/appointment trend. */
    private function getDoctorConsultationTrend(int $doctorId, int $months): array
    {
        $result = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $date  = now()->subMonths($i);
            $start = $date->copy()->startOfMonth();
            $end   = $date->copy()->endOfMonth();
            $count = Appointment::where('doctor_id', $doctorId)
                ->whereBetween('scheduled_at', [$start, $end])
                ->count();
            $result[] = ['label' => $date->format('M Y'), 'total' => $count];
        }
        return $result;
    }

    /** Build monthly lab request trend (scoped when a doctor accesses the lab dashboard). */
    private function getLabRequestTrend(int $months, ?int $doctorId = null): array
    {
        $result = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $date  = now()->subMonths($i);
            $start = $date->copy()->startOfMonth();
            $end   = $date->copy()->endOfMonth();
            $q = LabRequest::whereBetween('created_at', [$start, $end]);
            if ($doctorId !== null) {
                $q->where('doctor_id', $doctorId);
            }
            $result[] = ['label' => $date->format('M Y'), 'total' => $q->count()];
        }
        return $result;
    }

    /** Build monthly radiology request trend (scoped when a doctor accesses the radiology dashboard). */
    private function getRadiologyRequestTrend(int $months, ?int $doctorId = null): array
    {
        $result = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $date  = now()->subMonths($i);
            $start = $date->copy()->startOfMonth();
            $end   = $date->copy()->endOfMonth();
            $q = RadiologyRequest::whereBetween('created_at', [$start, $end]);
            if ($doctorId !== null) {
                $q->where('doctor_id', $doctorId);
            }
            $result[] = ['label' => $date->format('M Y'), 'total' => $q->count()];
        }
        return $result;
    }

    /** Build monthly pharmacy prescription trend (scoped when a doctor accesses the pharmacy dashboard). */
    private function getPharmacyTrend(int $months, ?int $doctorId = null): array
    {
        $result = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $date  = now()->subMonths($i);
            $start = $date->copy()->startOfMonth();
            $end   = $date->copy()->endOfMonth();
            $q = Prescription::whereBetween('created_at', [$start, $end]);
            if ($doctorId !== null) {
                $q->where('doctor_id', $doctorId);
            }
            $result[] = ['label' => $date->format('M Y'), 'total' => $q->count()];
        }
        return $result;
    }

    /** Build monthly surgery trend (respecting SORS role visibility). */
    private function getSurgeryTrend(int $months, ?User $user = null, bool $useSchedules = false): array
    {
        $result = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $date  = now()->subMonths($i);
            $start = $date->copy()->startOfMonth();
            $end   = $date->copy()->endOfMonth();

            if ($useSchedules && $user) {
                $count = SurgerySchedule::visibleTo($user)
                    ->whereBetween('scheduled_at', [$start, $end])
                    ->count();
            } else {
                $q = SurgeryRequest::whereBetween('created_at', [$start, $end]);
                if ($user) {
                    $q->visibleTo($user);
                }
                $count = $q->count();
            }

            $result[] = ['label' => $date->format('M Y'), 'total' => $count];
        }
        return $result;
    }

    /** Build monthly diet request trend. */
    private function getDietTrend(int $months, ?User $user = null): array
    {
        $result = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $date  = now()->subMonths($i);
            $start = $date->copy()->startOfMonth();
            $end   = $date->copy()->endOfMonth();
            $query = DietRequest::whereBetween('created_at', [$start, $end]);
            if ($user) {
                $query->visibleTo($user);
            }
            $result[] = ['label' => $date->format('M Y'), 'total' => $query->count()];
        }
        return $result;
    }

    /**
     * Build system & security alerts for the admin dashboard.
     *
     * @return \Illuminate\Support\Collection
     */
    private function buildAdminAlerts(): \Illuminate\Support\Collection
    {
        $alerts = collect();

        // 1. CRITICAL: Account Locked users (where locked_at is set)
        $lockedUsers = User::with('roles')
            ->whereNotNull('locked_at')
            ->orderBy('locked_at', 'desc')
            ->get();

        $lockedUserIds = $lockedUsers->pluck('id')->all();

        foreach ($lockedUsers as $user) {
            $attemptsInfo = $user->failed_attempts > 0 ? " ({$user->failed_attempts} failed login attempts)" : '';
            $roleStr = $user->role_name ? " ({$user->role_name})" : '';

            $alerts->push([
                'id'           => 'user_locked_' . $user->id,
                'type'         => 'danger',
                'severity'     => 'Critical',
                'icon'         => 'bi-lock-fill',
                'title'        => 'Account Locked',
                'user_name'    => $user->name,
                'role_name'    => $user->role_name,
                'description'  => "{$user->name}{$roleStr} is currently locked due to failed login attempts{$attemptsInfo}.",
                'timestamp'    => $user->locked_at ? $user->locked_at->diffForHumans() : 'Recently',
                'action_label' => 'Review Account',
                'action_route' => route('admin.users.index'),
            ]);
        }

        // 2. WARNING / CRITICAL: Excessive Failed Login Attempts
        $failedUsers = User::with('roles')
            ->whereNotIn('id', $lockedUserIds)
            ->where(function ($q) {
                $q->where('failed_attempts', '>', 0)
                  ->orWhere(function ($q2) {
                      $q2->whereNotNull('lockout_until')
                         ->where('lockout_until', '>', now());
                  });
            })
            ->orderBy('updated_at', 'desc')
            ->get();

        foreach ($failedUsers as $user) {
            $roleStr = $user->role_name ? " ({$user->role_name})" : '';
            $attempts = $user->failed_attempts;
            $isCooldown = $user->lockout_until && $user->lockout_until->isFuture();

            $alerts->push([
                'id'           => 'user_failed_' . $user->id,
                'type'         => $attempts >= 3 ? 'danger' : 'warning',
                'severity'     => $attempts >= 3 ? 'Critical' : 'Warning',
                'icon'         => 'bi-shield-exclamation',
                'title'        => $isCooldown ? 'Temporary Login Cooldown' : 'Repeated Failed Logins',
                'user_name'    => $user->name,
                'role_name'    => $user->role_name,
                'description'  => "{$user->name}{$roleStr} — {$attempts} failed login attempt(s)" . ($isCooldown ? " (active cooldown until {$user->lockout_until->format('H:i:s')})" : '') . '.',
                'timestamp'    => $user->updated_at ? $user->updated_at->diffForHumans() : 'Recently',
                'action_label' => 'Review Account',
                'action_route' => route('admin.users.index'),
            ]);
        }

        // 3. SECURITY & AUDIT EVENTS: Security changes or critical logs (last 24 hours from ActivityLog)
        $secChangeLogs = ActivityLog::with('user')
            ->where(function ($q) {
                $q->whereIn('action', [
                    'Role Assignment Changed',
                    'Account Unlocked',
                    'Password Reset',
                    'Privileged Account Modification Blocked',
                    'Privilege Escalation Blocked',
                ])
                ->orWhere('severity', ActivityLog::SEVERITY_CRITICAL);
            })
            ->where('created_at', '>=', now()->subHours(24))
            ->orderBy('created_at', 'desc')
            ->take(6)
            ->get();

        foreach ($secChangeLogs as $log) {
            $userName = $log->user?->name ?? 'System';
            $isCritical = $log->severity === ActivityLog::SEVERITY_CRITICAL;
            $alerts->push([
                'id'           => 'log_sec_change_' . $log->id,
                'type'         => $isCritical ? 'danger' : 'warning',
                'severity'     => $isCritical ? 'Critical' : 'Warning',
                'icon'         => $isCritical ? 'bi-shield-slash-fill' : 'bi-shield-lock-fill',
                'title'        => $log->action,
                'user_name'    => $userName,
                'role_name'    => $log->user?->role_name ?? null,
                'description'  => $log->description ?: "Security/Role modification logged for {$userName}.",
                'timestamp'    => $log->created_at ? $log->created_at->diffForHumans() : 'Recently',
                'action_label' => 'View System Audit Logs',
                'action_route' => route('admin.audit-logs.index'),
            ]);
        }

        return $alerts;
    }

    /** Doctor dashboard. */
    public function doctor(): \Illuminate\View\View
    {
        /** @var User $doctor */
        $doctor = Auth::user();
        return view('dashboard.doctor', $this->getDoctorDashboardData($doctor ? $doctor->id : 0));
    }

    /** Helper to build doctor dashboard payload. */
    private function getDoctorDashboardData(int $doctorId): array
    {
        $weekStart = now()->startOfWeek();

        // 1. My Patients Query (Scoped strictly to patients who have clinical interactions with this doctor)
        $myPatientsQuery = Patient::where(function ($query) use ($doctorId) {
            $query->whereHas('labRequests', fn($q) => $q->where('doctor_id', $doctorId))
                  ->orWhereHas('radiologyRequests', fn($q) => $q->where('doctor_id', $doctorId))
                  ->orWhereHas('prescriptions', fn($q) => $q->where('doctor_id', $doctorId))
                  ->orWhereHas('surgeryRequests', fn($q) => $q->where('doctor_id', $doctorId))
                  ->orWhereHas('dietRequests', fn($q) => $q->where('doctor_id', $doctorId))
                  ->orWhereHas('appointments', fn($q) => $q->where('doctor_id', $doctorId));
        });

        $myPatientsCount = (clone $myPatientsQuery)->count();

        // 2. Pending Tasks (Scoped strictly to doctor's pending requests)
        $pendingLabRequests  = LabRequest::with('patient')->where('doctor_id', $doctorId)->where('status', 'Pending')->latest()->get();
        $pendingRadRequests  = RadiologyRequest::with('patient')->where('doctor_id', $doctorId)->where('status', 'Pending')->latest()->get();
        $pendingRx           = Prescription::with('patient')->where('doctor_id', $doctorId)->where('status', 'Pending')->latest()->get();
        $pendingSurgery      = SurgeryRequest::with('patient')->where('doctor_id', $doctorId)->where('status', 'Pending')->latest()->get();
        $pendingDiet         = DietRequest::with('patient')->where('doctor_id', $doctorId)->where('status', 'Pending')->latest()->get();

        $pendingTasksCount = $pendingLabRequests->count() + $pendingRadRequests->count() + $pendingRx->count() + $pendingSurgery->count() + $pendingDiet->count();

        // Active clinical requests (Pending + In Progress / Scheduled / Verified / Active)
        $activeClinicalRequestsCount = $pendingTasksCount
            + LabRequest::where('doctor_id', $doctorId)->where('status', 'In Progress')->count()
            + RadiologyRequest::where('doctor_id', $doctorId)->whereIn('status', ['Scheduled', 'In Progress'])->count()
            + Prescription::where('doctor_id', $doctorId)->whereIn('status', ['Verified', 'Partially Dispensed'])->count()
            + SurgeryRequest::where('doctor_id', $doctorId)->whereIn('status', ['Scheduled', 'In Progress'])->count()
            + DietRequest::where('doctor_id', $doctorId)->where('status', 'Active')->count();

        // Pending Lab Results & Pending Imaging Reports for this Doctor's orders
        $pendingLabResultsCount = LabRequest::where('doctor_id', $doctorId)
            ->whereIn('status', ['Pending', 'In Progress'])
            ->count();

        $pendingImagingReportsCount = RadiologyRequest::where('doctor_id', $doctorId)
            ->whereIn('status', ['Pending', 'Scheduled', 'In Progress'])
            ->count();

        // Upcoming Appointments & Patients Waiting for Consultation (Doctor-scoped)
        $upcomingAppointments = Appointment::with('patient')
            ->where('doctor_id', $doctorId)
            ->whereIn('status', [
                Appointment::STATUS_REQUESTED,
                Appointment::STATUS_CONFIRMED,
                Appointment::STATUS_CHECKED_IN,
                Appointment::STATUS_IN_CONSULTATION,
            ])
            ->where('scheduled_at', '>=', now()->startOfDay())
            ->orderBy('scheduled_at')
            ->take(8)
            ->get();

        $waitingPatients = Appointment::with('patient')
            ->where('doctor_id', $doctorId)
            ->whereIn('status', [
                Appointment::STATUS_REQUESTED,
                Appointment::STATUS_CONFIRMED,
                Appointment::STATUS_CHECKED_IN,
                Appointment::STATUS_IN_CONSULTATION,
            ])
            ->orderBy('scheduled_at')
            ->take(6)
            ->get();

        // Unified & Priority-Sorted Pending Tasks List
        $allPendingTasks = collect();
        foreach ($pendingLabRequests as $req) {
            $allPendingTasks->push([
                'type'        => 'Laboratory',
                'badge_class' => 'hims-badge-blue',
                'request_no'  => $req->request_no,
                'patient'     => $req->patient,
                'summary'     => 'Lab Specimen Analysis',
                'priority'    => $req->priority ?? 'Routine',
                'status'      => $req->status,
                'created_at'  => $req->created_at,
                'url'         => route('lab.requests.show', $req),
            ]);
        }
        foreach ($pendingRadRequests as $req) {
            $allPendingTasks->push([
                'type'        => 'Radiology',
                'badge_class' => 'hims-badge-purple',
                'request_no'  => $req->request_no,
                'patient'     => $req->patient,
                'summary'     => ($req->modality ?? 'Imaging') . ' - ' . ($req->body_part ?? 'Procedure'),
                'priority'    => $req->priority ?? 'Routine',
                'status'      => $req->status,
                'created_at'  => $req->created_at,
                'url'         => route('radiology.requests.show', $req),
            ]);
        }
        foreach ($pendingRx as $req) {
            $allPendingTasks->push([
                'type'        => 'Prescription',
                'badge_class' => 'hims-badge-green',
                'request_no'  => $req->prescription_no,
                'patient'     => $req->patient,
                'summary'     => $req->diagnosis ? \Illuminate\Support\Str::limit($req->diagnosis, 35) : 'Medication Order',
                'priority'    => 'Routine',
                'status'      => $req->status,
                'created_at'  => $req->created_at,
                'url'         => route('pharmacy.prescriptions.show', $req),
            ]);
        }
        foreach ($pendingSurgery as $req) {
            $allPendingTasks->push([
                'type'        => 'Surgery',
                'badge_class' => 'hims-badge-amber',
                'request_no'  => $req->request_no,
                'patient'     => $req->patient,
                'summary'     => $req->procedure_name,
                'priority'    => $req->urgency ?? 'Routine',
                'status'      => $req->status,
                'created_at'  => $req->created_at,
                'url'         => route('surgery.requests.show', $req),
            ]);
        }
        foreach ($pendingDiet as $req) {
            $allPendingTasks->push([
                'type'        => 'Diet',
                'badge_class' => 'hims-badge-teal',
                'request_no'  => $req->request_no,
                'patient'     => $req->patient,
                'summary'     => 'Diet Plan: ' . $req->diet_type,
                'priority'    => 'Routine',
                'status'      => $req->status,
                'created_at'  => $req->created_at,
                'url'         => route('diet.requests.show', $req),
            ]);
        }

        // Sort pending tasks: STAT first, Urgent second, Routine/other third, then latest date
        $priorityRank = ['STAT' => 1, 'Emergency' => 1, 'Urgent' => 2, 'Routine' => 3];
        $sortedPendingTasks = $allPendingTasks->sortBy([
            fn($a, $b) => ($priorityRank[$a['priority']] ?? 3) <=> ($priorityRank[$b['priority']] ?? 3),
            fn($a, $b) => $b['created_at'] <=> $a['created_at'],
        ])->values();

        // 3. Diagnostic Results & Clinical Alerts Triage
        $releasedLabResults = LabResult::with(['requestItem.labRequest.patient', 'requestItem.labRequest.doctor'])
            ->whereHas('requestItem.labRequest', fn($q) => $q->where('doctor_id', $doctorId))
            ->whereIn('status', ['Released', 'Validated'])
            ->latest('released_at')
            ->get();

        $releasedRadReports = RadiologyReport::with(['radiologyRequest.patient', 'radiologyRequest.doctor'])
            ->whereHas('radiologyRequest', fn($q) => $q->where('doctor_id', $doctorId))
            ->whereIn('status', ['Approved', 'Released'])
            ->latest('released_at')
            ->get();

        // Construct Triaged Clinical Alerts Collection
        $clinicalAlerts = collect();

        foreach ($releasedLabResults as $lr) {
            $req = $lr->requestItem?->labRequest;
            $priority = $req?->priority ?? 'Routine';
            $remarks  = (string) $lr->remarks;
            $val      = (string) $lr->result_value;

            $isCritical = (strtoupper($priority) === 'STAT')
                || (stripos($remarks, 'critical') !== false)
                || (stripos($remarks, 'critically') !== false)
                || (stripos($val, 'panic') !== false);

            $isUrgent = !$isCritical && (strtoupper($priority) === 'URGENT' || stripos($remarks, 'urgent') !== false);

            $severity = $isCritical ? 'Critical' : ($isUrgent ? 'Urgent' : 'Informational');

            $clinicalAlerts->push([
                'source'      => 'Laboratory',
                'severity'    => $severity,
                'patient'     => $req?->patient,
                'title'       => $lr->requestItem?->test_name ?? 'Laboratory Test',
                'finding'     => $val ?: 'Result Released',
                'remarks'     => $remarks,
                'date'        => $lr->released_at ?? $lr->created_at,
                'url'         => route('lab.results.show', $lr),
            ]);
        }

        foreach ($releasedRadReports as $rr) {
            $req = $rr->radiologyRequest;
            $priority = $req?->priority ?? 'Routine';
            $impression = (string) $rr->impression;

            $isCritical = (strtoupper($priority) === 'STAT')
                || (stripos($impression, 'critical') !== false)
                || (stripos($impression, 'critically') !== false)
                || (stripos($impression, 'infarct') !== false)
                || (stripos($impression, 'hemorrhage') !== false);

            $isUrgent = !$isCritical && (strtoupper($priority) === 'URGENT' || stripos($impression, 'urgent') !== false);

            $severity = $isCritical ? 'Critical' : ($isUrgent ? 'Urgent' : 'Informational');

            $clinicalAlerts->push([
                'source'      => 'Radiology',
                'severity'    => $severity,
                'patient'     => $req?->patient,
                'title'       => ($req?->modality ?? 'Radiology') . ' (' . ($req?->body_part ?? 'Exam') . ')',
                'finding'     => $impression ?: 'Report Approved',
                'remarks'     => $rr->findings,
                'date'        => $rr->released_at ?? $rr->approved_at ?? $rr->created_at,
                'url'         => route('radiology.reports.show', $rr),
            ]);
        }

        $severityRank = ['Critical' => 1, 'Urgent' => 2, 'Informational' => 3];
        $sortedAlerts = $clinicalAlerts->sortBy([
            fn($a, $b) => ($severityRank[$a['severity']] ?? 3) <=> ($severityRank[$b['severity']] ?? 3),
            fn($a, $b) => $b['date'] <=> $a['date'],
        ])->values();

        $criticalCount = $clinicalAlerts->where('severity', 'Critical')->count();
        $urgentCount   = $clinicalAlerts->where('severity', 'Urgent')->count();

        // 4. Upcoming Surgeries (Doctor's scheduled or approved surgical procedures)
        $upcomingSurgeriesCount = SurgeryRequest::where('doctor_id', $doctorId)
            ->whereIn('status', ['Scheduled', 'Approved'])
            ->count();

        // Active Diet Plans for this Doctor's patients
        $activeDietPlansCount = DietPlan::whereHas('dietRequest', fn($q) => $q->where('doctor_id', $doctorId))
            ->whereIn('status', ['Active', 'Revised'])
            ->count();

        // 5. Statistics Payload
        $stats = [
            'my_patients'              => $myPatientsCount,
            'pending_tasks'            => $pendingTasksCount,
            'upcoming_appointments'    => $upcomingAppointments->count(),
            'waiting_patients'         => $waitingPatients->count(),
            'pending_lab_results'      => $pendingLabResultsCount,
            'pending_imaging_reports'  => $pendingImagingReportsCount,
            'active_clinical_requests' => $activeClinicalRequestsCount,
            'active_diet_plans'        => $activeDietPlansCount,
            'critical_alerts'          => $criticalCount,
            'urgent_alerts'            => $urgentCount,
            'lab_awaiting'             => $releasedLabResults->count(),
            'rad_awaiting'             => $releasedRadReports->count(),
            'upcoming_surgeries'       => $upcomingSurgeriesCount,

            'my_lab_requests'          => LabRequest::where('doctor_id', $doctorId)->count(),
            'my_radiology'             => RadiologyRequest::where('doctor_id', $doctorId)->count(),
            'my_prescriptions'         => Prescription::where('doctor_id', $doctorId)->count(),
            'my_surgeries'             => SurgeryRequest::where('doctor_id', $doctorId)->count(),
            'my_diet_requests'         => DietRequest::where('doctor_id', $doctorId)->count(),

            'lab_this_week'            => LabRequest::where('doctor_id', $doctorId)->where('created_at', '>=', $weekStart)->count(),
            'rx_this_week'             => Prescription::where('doctor_id', $doctorId)->where('created_at', '>=', $weekStart)->count(),
            'rad_this_week'            => RadiologyRequest::where('doctor_id', $doctorId)->where('created_at', '>=', $weekStart)->count(),
            'surg_this_week'           => SurgeryRequest::where('doctor_id', $doctorId)->where('created_at', '>=', $weekStart)->count(),
            'diet_this_week'           => DietRequest::where('doctor_id', $doctorId)->where('created_at', '>=', $weekStart)->count(),
        ];

        // 6. Recent Patients (Scoped strictly to patients who have clinical interactions with this doctor)
        $recentPatients = (clone $myPatientsQuery)
            ->latest('updated_at')
            ->take(5)
            ->get();

        // 7. Doctor monthly request trend (6m & 12m) & consultation trend (6m)
        $doctorTrend6m             = $this->getDoctorRequestTrend($doctorId, 6);
        $doctorTrend12m            = $this->getDoctorRequestTrend($doctorId, 12);
        $consultationTrend6m       = $this->getDoctorConsultationTrend($doctorId, 6);
        $doctorConsultationTrend6m = $consultationTrend6m;
        $upcomingAppointmentsList  = $upcomingAppointments;

        // 8. Doctor requests by service (all-time for bar chart)
        $doctorServiceBreakdown = [
            ['service' => 'Laboratory', 'total' => $stats['my_lab_requests']],
            ['service' => 'Radiology',  'total' => $stats['my_radiology']],
            ['service' => 'Pharmacy',   'total' => $stats['my_prescriptions']],
            ['service' => 'Surgery',    'total' => $stats['my_surgeries']],
            ['service' => 'Diet',       'total' => $stats['my_diet_requests']],
        ];

        return compact(
            'stats',
            'upcomingAppointments',
            'upcomingAppointmentsList',
            'waitingPatients',
            'pendingLabRequests',
            'pendingRadRequests',
            'pendingRx',
            'pendingSurgery',
            'pendingDiet',
            'sortedPendingTasks',
            'releasedLabResults',
            'releasedRadReports',
            'sortedAlerts',
            'recentPatients',
            'doctorTrend6m',
            'doctorTrend12m',
            'consultationTrend6m',
            'doctorConsultationTrend6m',
            'doctorServiceBreakdown'
        );
    }

    /** Lab / Medical Technologist dashboard. */
    public function lab()
    {
        $today = now()->toDateString();
        /** @var User|null $user */
        $user = Auth::user();

        // Scope strictly to the logged-in Doctor when accessed by a Doctor without Admin/Med-Tech role
        $scopedDoctorId = ($user && $user->hasRole('doctor') && !$user->hasAnyRole(['admin', 'super_admin', 'med-tech']))
            ? $user->id
            : null;

        $reqQuery = LabRequest::query();
        $resQuery = LabResult::query();

        if ($scopedDoctorId !== null) {
            $reqQuery->where('doctor_id', $scopedDoctorId);
            $resQuery->whereHas('requestItem.labRequest', fn($q) => $q->where('doctor_id', $scopedDoctorId));
        }

        $awaitingValidationCount = (clone $resQuery)->where('status', 'Draft')->count();
        $awaitingReleaseCount    = (clone $resQuery)->where('status', 'Validated')->count();
        $specimensReceivedCount  = (clone $reqQuery)
            ->where(function ($q) {
                $q->whereNotNull('received_at')
                  ->orWhereIn('status', ['In Progress', 'Completed']);
            })
            ->count();

        $stats = [
            'total_requests'           => (clone $reqQuery)->count(),
            'pending'                  => (clone $reqQuery)->where('status', 'Pending')->count(),
            'pending_requests'         => (clone $reqQuery)->where('status', 'Pending')->count(),
            'specimens_received'       => $specimensReceivedCount,
            'specimen_received'        => $specimensReceivedCount,
            'in_progress'              => (clone $reqQuery)->where('status', 'In Progress')->count(),
            'awaiting_validation'      => $awaitingValidationCount,
            'results_pending'          => $awaitingValidationCount,
            'awaiting_release'         => $awaitingReleaseCount,
            'results_awaiting_release' => $awaitingReleaseCount,
            'completed'                => (clone $reqQuery)->where('status', 'Completed')->count(),
            'completed_tests'          => (clone $reqQuery)->where('status', 'Completed')->count(),
            'stat_count'               => (clone $reqQuery)->where('priority', 'STAT')->count(),
            'today_received'           => (clone $reqQuery)->whereDate('created_at', $today)->count(),
            'today_completed'          => (clone $reqQuery)->whereDate('updated_at', $today)->where('status', 'Completed')->count(),
            'completed_today'          => (clone $reqQuery)->whereDate('updated_at', $today)->where('status', 'Completed')->count(),
            'stat_pending'             => (clone $reqQuery)->where('priority', 'STAT')->where('status', 'Pending')->count(),
            'pending_release'          => $awaitingValidationCount + $awaitingReleaseCount,
        ];

        $recentRequests = (clone $reqQuery)
            ->with('patient', 'doctor')
            ->latest()
            ->take(15)
            ->get();

        $pendingSpecimens = (clone $reqQuery)
            ->with('patient', 'doctor')
            ->where('status', 'Pending')
            ->latest()
            ->take(8)
            ->get();

        $urgentRequests = (clone $reqQuery)
            ->with('patient', 'doctor')
            ->whereIn('priority', ['STAT', 'Urgent'])
            ->whereIn('status', ['Pending', 'In Progress'])
            ->latest()
            ->take(8)
            ->get();

        $resultsAwaitingValidation = (clone $resQuery)
            ->with(['requestItem.labRequest.patient', 'technologist'])
            ->whereIn('status', ['Draft', 'Validated'])
            ->latest()
            ->take(8)
            ->get();

        $recentLabActivities = ActivityLog::with('user')
            ->where('module', 'Laboratory')
            ->when($scopedDoctorId !== null, fn($q) => $q->where('user_id', $scopedDoctorId))
            ->orderByDesc('logged_at')
            ->take(6)
            ->get();

        // Lab trend + 7d daily processing + priority breakdown
        $labTrend6m  = $this->getLabRequestTrend(6, $scopedDoctorId);
        $labTrend12m = $this->getLabRequestTrend(12, $scopedDoctorId);
        $labDailyTrend7d = collect(range(6, 0))->map(function (int $daysAgo) use ($scopedDoctorId) {
            $d = now()->subDays($daysAgo);
            $dStr = $d->toDateString();
            $recQ = LabRequest::whereDate('created_at', $dStr);
            $cmpQ = LabRequest::whereDate('updated_at', $dStr)->where('status', 'Completed');
            if ($scopedDoctorId !== null) {
                $recQ->where('doctor_id', $scopedDoctorId);
                $cmpQ->where('doctor_id', $scopedDoctorId);
            }
            return [
                'label'     => $d->format('M d'),
                'received'  => $recQ->count(),
                'completed' => $cmpQ->count(),
            ];
        })->values();

        $labPriority = [
            ['label' => 'Routine', 'count' => (clone $reqQuery)->where('priority', 'Routine')->count()],
            ['label' => 'Urgent',  'count' => (clone $reqQuery)->where('priority', 'Urgent')->count()],
            ['label' => 'STAT',    'count' => (clone $reqQuery)->where('priority', 'STAT')->count()],
        ];

        $urgentLabRequests          = $urgentRequests;
        $resultsRequiringValidation = $resultsAwaitingValidation;

        return view('dashboard.lab', compact(
            'stats',
            'recentRequests',
            'pendingSpecimens',
            'urgentRequests',
            'urgentLabRequests',
            'resultsAwaitingValidation',
            'resultsRequiringValidation',
            'recentLabActivities',
            'labTrend6m',
            'labTrend12m',
            'labDailyTrend7d',
            'labPriority'
        ));
    }

    /** Radiology dashboard (differentiating Radiologic Technologist & Radiologist). */
    public function radiology()
    {
        $today = now()->toDateString();
        /** @var User|null $user */
        $user = Auth::user();
        $isRadiologist = $user ? $user->hasRole('radiologist') : false;

        // Scope strictly to the logged-in Doctor when accessed by a Doctor without Admin/RIS staff role
        $scopedDoctorId = ($user && $user->hasRole('doctor') && !$user->hasAnyRole(['admin', 'super_admin', 'rad-tech', 'radiologist']))
            ? $user->id
            : null;

        $reqQuery = RadiologyRequest::query();
        $rptQuery = RadiologyReport::query();

        if ($scopedDoctorId !== null) {
            $reqQuery->where('doctor_id', $scopedDoctorId);
            $rptQuery->whereHas('radiologyRequest', fn($q) => $q->where('doctor_id', $scopedDoctorId));
        }

        $studiesWithoutReportCount = (clone $reqQuery)
            ->where('status', 'Completed')
            ->whereDoesntHave('report')
            ->count();

        $draftReportsCount = (clone $rptQuery)->where('status', 'Draft')->count();

        $returnedReportsCount = (clone $rptQuery)
            ->where('status', 'Draft')
            ->where('recommendations', 'like', '%[Returned for Correction%')
            ->count();

        $awaitingApprovalCount = max(0, $draftReportsCount - $returnedReportsCount);
        $approvedReportsCount  = (clone $rptQuery)->where('status', 'Approved')->count();
        $releasedReportsCount  = (clone $rptQuery)->where('status', 'Released')->count();

        // Accurate, non-double-counted pending interpretation count
        $reportsPendingTotal = $studiesWithoutReportCount + $draftReportsCount + $approvedReportsCount;

        $stats = [
            'total_requests'            => (clone $reqQuery)->count(),
            'pending'                   => (clone $reqQuery)->where('status', 'Pending')->count(),
            'pending_requests'          => (clone $reqQuery)->where('status', 'Pending')->count(),
            'scheduled'                 => (clone $reqQuery)->where('status', 'Scheduled')->count(),
            'in_progress'               => (clone $reqQuery)->where('status', 'In Progress')->count(),
            'completed'                 => (clone $reqQuery)->where('status', 'Completed')->count(),
            'completed_exams'           => (clone $reqQuery)->where('status', 'Completed')->count(),
            'reports_to_prepare'        => $studiesWithoutReportCount,
            'reports_awaiting_review'   => $studiesWithoutReportCount + $awaitingApprovalCount,
            'awaiting_review'           => $studiesWithoutReportCount + $awaitingApprovalCount,
            'reports_under_review'      => $draftReportsCount,
            'under_review'              => $draftReportsCount,
            'reports_returned'          => $returnedReportsCount,
            'returned_correction'       => $returnedReportsCount,
            'reports_awaiting_approval' => $awaitingApprovalCount + $approvedReportsCount,
            'awaiting_approval'         => $awaitingApprovalCount + $approvedReportsCount,
            'reports_approved'          => $approvedReportsCount,
            'reports_pending'           => $reportsPendingTotal,
            'today_scheduled'           => (clone $reqQuery)->whereDate('updated_at', $today)->where('status', 'Scheduled')->count(),
            'today_completed'           => (clone $reqQuery)->whereDate('updated_at', $today)->where('status', 'Completed')->count(),
            'reports_released'          => $releasedReportsCount,
            'released_reports'          => $releasedReportsCount,
            'reports_today'             => (clone $rptQuery)->whereDate('created_at', $today)->count(),
        ];

        // Technologist procedure queue (Pending, Scheduled, In Progress, Completed)
        $recentRequests = (clone $reqQuery)
            ->with('patient', 'doctor', 'images')
            ->latest()
            ->take(15)
            ->get();

        // Today's & active scheduled imaging examinations for Rad-Tech
        $todaysSchedule = (clone $reqQuery)
            ->with('patient', 'doctor')
            ->whereIn('status', ['Scheduled', 'In Progress'])
            ->latest('scheduled_at')
            ->take(8)
            ->get();

        // Radiologist interpretation queue (Draft/Approved reports)
        $pendingReports = (clone $rptQuery)
            ->whereIn('status', ['Draft', 'Approved'])
            ->with('radiologyRequest.patient', 'radiologyRequest.doctor', 'radiologyRequest.images')
            ->latest()
            ->take(15)
            ->get();

        // Reports returned for correction
        $returnedReportsList = (clone $rptQuery)
            ->where('status', 'Draft')
            ->where('recommendations', 'like', '%[Returned for Correction%')
            ->with('radiologyRequest.patient', 'radiologyRequest.doctor')
            ->latest('updated_at')
            ->take(8)
            ->get();

        // Recent approved / released reports
        $recentApprovedReports = (clone $rptQuery)
            ->whereIn('status', ['Approved', 'Released'])
            ->with('radiologyRequest.patient', 'radiologyRequest.doctor', 'approvedBy')
            ->latest('approved_at')
            ->take(8)
            ->get();

        $completedStudiesAwaitingReport = (clone $reqQuery)
            ->where('status', 'Completed')
            ->whereDoesntHave('report')
            ->with('patient', 'doctor', 'images')
            ->latest()
            ->take(15)
            ->get();

        $recentRadActivities = ActivityLog::with('user')
            ->where('module', 'Radiology')
            ->when($scopedDoctorId !== null, fn($q) => $q->where('user_id', $scopedDoctorId))
            ->orderByDesc('logged_at')
            ->take(6)
            ->get();

        // Modality distribution for Rad-Tech analytics
        $modalityDistribution = (clone $reqQuery)
            ->selectRaw('COALESCE(modality, "Unspecified") as modality_label, COUNT(*) as total')
            ->groupBy('modality_label')
            ->orderByDesc('total')
            ->get()
            ->map(fn($row) => [
                'label' => $row->modality_label,
                'count' => (int) $row->total,
            ])->values();

        // Radiology trend + examination/report status breakdown
        $radTrend6m  = $this->getRadiologyRequestTrend(6, $scopedDoctorId);
        $radTrend12m = $this->getRadiologyRequestTrend(12, $scopedDoctorId);
        $radStatusBreakdown = [
            ['label' => 'Pending',     'count' => $stats['pending']],
            ['label' => 'Scheduled',   'count' => $stats['scheduled']],
            ['label' => 'In Progress', 'count' => $stats['in_progress']],
            ['label' => 'Completed',   'count' => $stats['completed']],
        ];
        $radReportBreakdown = [
            ['label' => 'Draft',    'count' => $awaitingApprovalCount],
            ['label' => 'Returned', 'count' => $returnedReportsCount],
            ['label' => 'Approved', 'count' => $approvedReportsCount],
            ['label' => 'Released', 'count' => $releasedReportsCount],
        ];

        $todaysImagingSchedule       = $todaysSchedule;
        $pendingReportReviews        = $pendingReports;
        $reportsRequiringCorrection  = $returnedReportsList;
        $reportsRequiringPreparation = $completedStudiesAwaitingReport;
        $radModalityDistribution     = $modalityDistribution;
        $reportStatusBreakdown       = $radReportBreakdown;

        return view('dashboard.radiology', compact(
            'stats',
            'recentRequests',
            'todaysSchedule',
            'todaysImagingSchedule',
            'pendingReports',
            'pendingReportReviews',
            'returnedReportsList',
            'reportsRequiringCorrection',
            'recentApprovedReports',
            'completedStudiesAwaitingReport',
            'reportsRequiringPreparation',
            'recentRadActivities',
            'modalityDistribution',
            'radModalityDistribution',
            'isRadiologist',
            'radTrend6m',
            'radTrend12m',
            'radStatusBreakdown',
            'radReportBreakdown',
            'reportStatusBreakdown'
        ));
    }

    /** Pharmacy dashboard. */
    public function pharmacy(?Request $request = null)
    {
        $request = $request ?? request();
        $today = now()->toDateString();
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');

        /** @var User|null $user */
        $user = Auth::user();

        // Scope strictly to the logged-in Doctor when accessed by a Doctor without Admin/Pharmacist role
        $scopedDoctorId = ($user && $user->hasRole('doctor') && !$user->hasAnyRole(['admin', 'super_admin', 'pharmacist']))
            ? $user->id
            : null;

        $rxQuery = Prescription::query();
        if ($scopedDoctorId !== null) {
            $rxQuery->where('doctor_id', $scopedDoctorId);
        }
        if ($dateFrom) {
            $rxQuery->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $rxQuery->whereDate('created_at', '<=', $dateTo);
        }

        $totalPrescriptions         = (clone $rxQuery)->count();
        $pendingPrescriptions       = (clone $rxQuery)->where('status', 'Pending')->count();
        $verifiedPrescriptions      = (clone $rxQuery)->where('status', 'Verified')->count();
        $partiallyDispensedCount    = (clone $rxQuery)->where('status', 'Partially Dispensed')->count();
        $dispensedTotal             = (clone $rxQuery)->where('status', 'Dispensed')->count();

        $dispensedToday = class_exists(DispensingRecord::class)
            ? DispensingRecord::when(
                $scopedDoctorId !== null,
                fn($q) => $q->whereHas('prescriptionItem.prescription', fn($pq) => $pq->where('doctor_id', $scopedDoctorId))
            )->whereDate('created_at', $today)->count()
            : (clone $rxQuery)->where('status', 'Dispensed')->whereDate('updated_at', $today)->count();

        $verifiedToday = (clone $rxQuery)->where('status', 'Verified')->whereDate('updated_at', $today)->count();

        // Query medication stock provider for stock directory stats
        $stockStats = [
            'total_monitored'    => 0,
            'in_stock'           => 0,
            'low_stock'          => 0,
            'out_of_stock'       => 0,
            'near_expiry'        => 0,
            'expired'            => 0,
            'attention_required' => 0,
        ];
        $nearExpiryList = collect();
        $expiredList    = collect();
        $lowStockList   = collect();
        $outOfStockList = collect();

        try {
            /** @var MedicationStockProviderInterface $stockProvider */
            $stockProvider = app(MedicationStockProviderInterface::class);
            $allStock = collect($stockProvider->getAllMedicationStock());

            $todayTs = strtotime(date('Y-m-d'));
            $expiringThresholdTs = strtotime(date('Y-m-d', strtotime('+90 days')));

            $processedItems = $allStock->map(function ($item) use ($todayTs, $expiringThresholdTs) {
                $qty = $item->availableQuantity;

                if ($qty > 20) {
                    $stockKey = 'in_stock';
                } elseif ($qty >= 1 && $qty <= 20) {
                    $stockKey = 'low';
                } else {
                    $stockKey = 'out';
                }

                $expTs = !empty($item->expiryDate) ? strtotime($item->expiryDate) : null;
                if (!$expTs || $expTs <= $todayTs) {
                    $expKey = 'expired';
                } elseif ($expTs <= $expiringThresholdTs) {
                    $expKey = 'expiring_soon';
                } else {
                    $expKey = 'unexpired';
                }

                return [
                    'name'       => $item->medicationName,
                    'qty'        => $qty,
                    'lot_number' => $item->lotNumber ?? 'N/A',
                    'expiry'     => $item->expiryDate,
                    'stock_key'  => $stockKey,
                    'exp_key'    => $expKey,
                ];
            });

            $stockStats = [
                'total_monitored'    => $processedItems->count(),
                'in_stock'           => $processedItems->where('stock_key', 'in_stock')->where('exp_key', '!=', 'expired')->count(),
                'low_stock'          => $processedItems->where('stock_key', 'low')->count(),
                'out_of_stock'       => $processedItems->where('stock_key', 'out')->count(),
                'near_expiry'        => $processedItems->where('exp_key', 'expiring_soon')->count(),
                'expired'            => $processedItems->where('exp_key', 'expired')->count(),
                'attention_required' => $processedItems->filter(function ($item) {
                    return $item['stock_key'] === 'out'
                        || $item['exp_key'] === 'expiring_soon'
                        || $item['exp_key'] === 'expired';
                })->count(),
            ];

            $nearExpiryList = $processedItems->where('exp_key', 'expiring_soon')->values();
            $expiredList    = $processedItems->where('exp_key', 'expired')->values();
            $lowStockList   = $processedItems->where('stock_key', 'low')->values();
            $outOfStockList = $processedItems->where('stock_key', 'out')->values();
        } catch (\Throwable $e) {
            Log::error('Pharmacy dashboard stock provider failure: ' . $e->getMessage());
        }

        // POS Sales Metrics
        $salesTodayAmount = 0.00;
        $salesTodayCount  = 0;
        $paymentBreakdown = [
            'Cash'             => 0.00,
            'GCash'            => 0.00,
            'Card'             => 0.00,
            'Hospital Billing' => 0.00,
        ];
        $recentPosTransactions = collect();
        $salesTrend7d = collect();

        if (class_exists(PharmacyPosTransaction::class)) {
            $posTodayQuery = PharmacyPosTransaction::where('status', 'Completed')
                ->whereDate('transacted_at', $today);

            $salesTodayAmount = (float) (clone $posTodayQuery)->sum('grand_total');
            $salesTodayCount  = (clone $posTodayQuery)->count();

            $posPeriodQuery = PharmacyPosTransaction::where('status', 'Completed');
            if ($dateFrom) {
                $posPeriodQuery->whereDate('transacted_at', '>=', $dateFrom);
            }
            if ($dateTo) {
                $posPeriodQuery->whereDate('transacted_at', '<=', $dateTo);
            }

            foreach (array_keys($paymentBreakdown) as $method) {
                $paymentBreakdown[$method] = (float) (clone $posPeriodQuery)
                    ->where('payment_method', $method)
                    ->sum('grand_total');
            }

            $recentPosTransactions = PharmacyPosTransaction::with(['pharmacist', 'prescription'])
                ->latest('transacted_at')
                ->take(8)
                ->get();

            $salesTrend7d = collect(range(6, 0))->map(function (int $daysAgo) {
                $d = now()->subDays($daysAgo);
                $dStr = $d->toDateString();
                $amt = (float) PharmacyPosTransaction::where('status', 'Completed')
                    ->whereDate('transacted_at', $dStr)
                    ->sum('grand_total');
                return [
                    'label' => $d->format('M d'),
                    'total' => round($amt, 2),
                ];
            })->values();
        }

        $pendingDispensing = $verifiedPrescriptions + $partiallyDispensedCount;

        $stats = [
            'total_prescriptions'     => $totalPrescriptions,
            'pending_prescriptions'   => $pendingPrescriptions,
            'pending_rx'              => $pendingPrescriptions,
            'for_verification'        => $pendingPrescriptions,
            'verified'                => $verifiedPrescriptions,
            'partially_dispensed'     => $partiallyDispensedCount,
            'pending_dispensing'      => $pendingDispensing,
            'dispensed_today'         => $dispensedToday,
            'dispensed_total'         => $dispensedTotal,
            'completed_prescriptions' => $dispensedTotal,
            'verified_today'          => $verifiedToday,
            'low_stock'               => $stockStats['low_stock'],
            'out_of_stock'            => $stockStats['out_of_stock'],
            'near_expiry'             => $stockStats['near_expiry'],
            'expired_medicines'       => $stockStats['expired'],
            'sales_today'             => $salesTodayAmount,
            'sales_today_count'       => $salesTodayCount,
            'pending_rate'            => $totalPrescriptions > 0
                                            ? round(($pendingPrescriptions / $totalPrescriptions) * 100)
                                            : 0,
        ];

        $pendingPrescriptionsList = (clone $rxQuery)
            ->with('patient', 'doctor')
            ->whereIn('status', ['Pending', 'Verified', 'Partially Dispensed'])
            ->latest()
            ->take(15)
            ->get();

        $verificationQueue = (clone $rxQuery)
            ->with('patient', 'doctor')
            ->where('status', 'Pending')
            ->latest()
            ->take(10)
            ->get();

        $dispensingQueue = (clone $rxQuery)
            ->with('patient', 'doctor')
            ->whereIn('status', ['Verified', 'Partially Dispensed'])
            ->latest()
            ->take(10)
            ->get();

        $pendingVerificationQueue = $verificationQueue;
        $awaitingDispensingQueue  = $dispensingQueue;

        $recentDispensing = class_exists(DispensingRecord::class)
            ? DispensingRecord::with('prescriptionItem.prescription.patient', 'pharmacist')
                ->when(
                    $scopedDoctorId !== null,
                    fn($q) => $q->whereHas('prescriptionItem.prescription', fn($pq) => $pq->where('doctor_id', $scopedDoctorId))
                )
                ->latest()
                ->take(8)
                ->get()
            : collect();

        // 7-day medication dispensing trend
        $dispensingTrend7d = collect(range(6, 0))->map(function (int $daysAgo) use ($scopedDoctorId) {
            $d = now()->subDays($daysAgo);
            $dStr = $d->toDateString();
            $cnt = class_exists(DispensingRecord::class)
                ? DispensingRecord::when(
                    $scopedDoctorId !== null,
                    fn($q) => $q->whereHas('prescriptionItem.prescription', fn($pq) => $pq->where('doctor_id', $scopedDoctorId))
                )->whereDate('created_at', $dStr)->count()
                : 0;
            return [
                'label' => $d->format('M d'),
                'count' => $cnt,
                'total' => $cnt,
            ];
        })->values();

        // Pharmacy trend and status breakdown
        $rxTrend6m  = $this->getPharmacyTrend(6, $scopedDoctorId);
        $rxTrend12m = $this->getPharmacyTrend(12, $scopedDoctorId);
        $rxStatusBreakdown = [
            ['label' => 'Pending',             'count' => $stats['pending_prescriptions']],
            ['label' => 'Verified',            'count' => $stats['verified']],
            ['label' => 'Partially Dispensed', 'count' => $stats['partially_dispensed']],
            ['label' => 'Dispensed',           'count' => $stats['dispensed_total']],
        ];

        return view('dashboard.pharmacy', compact(
            'stats',
            'pendingPrescriptionsList',
            'verificationQueue',
            'pendingVerificationQueue',
            'dispensingQueue',
            'awaitingDispensingQueue',
            'recentDispensing',
            'recentPosTransactions',
            'dispensingTrend7d',
            'salesTrend7d',
            'paymentBreakdown',
            'rxTrend6m',
            'rxTrend12m',
            'rxStatusBreakdown',
            'stockStats',
            'nearExpiryList',
            'expiredList',
            'lowStockList',
            'outOfStockList',
            'dateFrom',
            'dateTo'
        ));
    }

    /** Surgery / OR Coordinator / OR Staff dashboard. */
    public function surgery()
    {
        /** @var \App\Models\User $user */
        $user     = Auth::user();
        $today    = now()->toDateString();
        $next7end = now()->addDays(7)->toDateString();

        $isOrStaffOnly = $user && $user->hasRole('or-staff') && ! $user->hasAnyRole(['or-coordinator', 'doctor', 'admin', 'super_admin']);

        $reqQuery   = SurgeryRequest::visibleTo($user);
        $schedQuery = SurgerySchedule::visibleTo($user);

        $readinessPendingPrep = (clone $schedQuery)
            ->whereIn('status', ['Scheduled', 'In Progress'])
            ->where(fn ($q) => $q->where('readiness_status', 'Pending Prep')->orWhereNull('readiness_status'))
            ->count();

        $readinessPreparing = (clone $schedQuery)
            ->whereIn('status', ['Scheduled', 'In Progress'])
            ->where('readiness_status', 'Preparing')
            ->count();

        $readinessReady = (clone $schedQuery)
            ->whereIn('status', ['Scheduled', 'In Progress'])
            ->where('readiness_status', 'Ready')
            ->count();

        $schedScheduled  = (clone $schedQuery)->where('status', 'Scheduled')->count();
        $schedInProgress = (clone $schedQuery)->where('status', 'In Progress')->count();
        $schedCompleted  = (clone $schedQuery)->where('status', 'Completed')->count();
        $schedCancelled  = (clone $schedQuery)->where('status', 'Cancelled')->count();

        $availableRoomsCount = OperatingRoom::where('is_active', true)->count();

        $stats = [
            'total_requests'            => $isOrStaffOnly ? (clone $schedQuery)->count() : (clone $reqQuery)->count(),
            'pending'                   => $isOrStaffOnly ? $readinessPendingPrep : (clone $reqQuery)->where('status', 'Pending')->count(),
            'pending_requests'          => $isOrStaffOnly ? $readinessPendingPrep : (clone $reqQuery)->where('status', 'Pending')->count(),
            'scheduled'                 => $isOrStaffOnly ? $schedScheduled : (clone $reqQuery)->where('status', 'Scheduled')->count(),
            'in_progress'               => $schedInProgress,
            'completed'                 => $isOrStaffOnly ? $schedCompleted : (clone $reqQuery)->where('status', 'Completed')->count(),
            'cancelled'                 => $isOrStaffOnly ? $schedCancelled : (clone $reqQuery)->where('status', 'Cancelled')->count(),
            'upcoming_7d'               => (clone $schedQuery)
                                            ->whereBetween('scheduled_at', [$today, $next7end])
                                            ->whereIn('status', ['Scheduled', 'In Progress'])->count(),
            'today_scheduled'           => (clone $schedQuery)
                                            ->whereDate('scheduled_at', $today)->count(),
            'today_surgeries'           => (clone $schedQuery)
                                            ->whereDate('scheduled_at', $today)->count(),
            'assigned_total'            => (clone $schedQuery)->count(),
            'assigned_cases'            => (clone $schedQuery)->count(),
            'upcoming_procedures'       => $schedScheduled + $schedInProgress,
            'preparation_tasks'         => $readinessPendingPrep + $readinessPreparing,
            'pending_readiness_updates' => $readinessPendingPrep + $readinessPreparing,
            'cases_requiring_readiness' => $readinessPendingPrep + $readinessPreparing,
            'available_rooms'           => $availableRoomsCount,
            'readiness_pending_prep'    => $readinessPendingPrep,
            'readiness_preparing'       => $readinessPreparing,
            'readiness_ready'           => $readinessReady,
            'ready_cases'               => $readinessReady,
        ];

        $recentRequests = (clone $reqQuery)
            ->with('patient', 'doctor')
            ->latest()
            ->take(15)
            ->get();

        $upcomingSchedules = (clone $schedQuery)
            ->with('surgeryRequest.patient', 'operatingRoom', 'surgicalTeam.surgeon')
            ->whereIn('status', ['Scheduled', 'In Progress'])
            ->orderBy('scheduled_at')
            ->take(10)
            ->get();

        $casesRequiringPrep = (clone $schedQuery)
            ->with('surgeryRequest.patient', 'operatingRoom')
            ->whereIn('status', ['Scheduled', 'In Progress'])
            ->where(fn($q) => $q->whereIn('readiness_status', ['Pending Prep', 'Preparing'])->orWhereNull('readiness_status'))
            ->orderBy('scheduled_at')
            ->take(8)
            ->get();

        $casesRequiringPreparation = $casesRequiringPrep;
        $casesRequiringReadiness   = $casesRequiringPrep;

        $recentReadinessUpdates = (clone $schedQuery)
            ->with('surgeryRequest.patient', 'operatingRoom', 'readinessUpdatedBy')
            ->whereNotNull('readiness_updated_at')
            ->latest('readiness_updated_at')
            ->take(6)
            ->get();

        // Operating Room Availability & Utilization (for OR Coordinator / Admin)
        $operatingRooms = OperatingRoom::withCount([
                'surgerySchedules as active_cases_count' => fn($q) => $q->whereIn('status', ['Scheduled', 'In Progress']),
                'surgerySchedules as total_cases_count',
                'surgerySchedules as schedules_count',
            ])
            ->orderBy('name')
            ->get();

        $orUtilizationData = $operatingRooms->map(fn($room) => [
            'room'   => $room->name,
            'label'  => $room->name,
            'active' => (int) $room->active_cases_count,
            'total'  => (int) $room->total_cases_count,
            'count'  => (int) $room->total_cases_count,
        ])->values();

        $orUtilization = $orUtilizationData;

        // Weekly assigned workload (7 days)
        $weeklyWorkload7d = collect(range(0, 6))->map(function (int $daysAhead) use ($schedQuery) {
            $d = now()->addDays($daysAhead);
            $cnt = (clone $schedQuery)->whereDate('scheduled_at', $d->toDateString())->count();
            return [
                'label' => $d->format('D M d'),
                'count' => $cnt,
                'total' => $cnt,
            ];
        })->values();

        $weeklyAssignedWorkload = $weeklyWorkload7d;

        $recentSurgeryActivities = ActivityLog::with('user')
            ->where('module', 'Surgery')
            ->when($isOrStaffOnly && $user, fn($q) => $q->where('user_id', $user->id))
            ->orderByDesc('logged_at')
            ->take(6)
            ->get();

        // Surgery trend and status breakdown (scoped to user role)
        $surgTrend6m  = $this->getSurgeryTrend(6, $user, $isOrStaffOnly);
        $surgTrend12m = $this->getSurgeryTrend(12, $user, $isOrStaffOnly);
        $surgStatusBreakdown = [
            ['label' => 'Pending',   'count' => $stats['pending']],
            ['label' => 'Scheduled', 'count' => $stats['scheduled']],
            ['label' => 'Completed', 'count' => $stats['completed']],
            ['label' => 'Cancelled', 'count' => $stats['cancelled']],
        ];

        $readinessBreakdown = [
            ['label' => 'Pending Prep', 'count' => $readinessPendingPrep],
            ['label' => 'Preparing',    'count' => $readinessPreparing],
            ['label' => 'Ready',        'count' => $readinessReady],
        ];

        return view('dashboard.surgery', compact(
            'stats',
            'recentRequests',
            'upcomingSchedules',
            'casesRequiringPrep',
            'casesRequiringPreparation',
            'casesRequiringReadiness',
            'recentReadinessUpdates',
            'operatingRooms',
            'orUtilizationData',
            'orUtilization',
            'weeklyWorkload7d',
            'weeklyAssignedWorkload',
            'recentSurgeryActivities',
            'surgTrend6m',
            'surgTrend12m',
            'surgStatusBreakdown',
            'readinessBreakdown'
        ));
    }

    /** Diet / Nutrition dashboard. */
    public function diet()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        $today = now()->toDateString();

        // Doctors only see their own requests/plans; admin/super_admin see all.
        $reqs  = fn () => DietRequest::visibleTo($user);
        $plans = fn () => DietPlan::visibleTo($user);

        $totalRequests  = $reqs()->count();
        $pendingCount   = $reqs()->where('status', 'Pending')->count();
        $activePlans    = $plans()->whereIn('status', ['Active', 'Revised'])->count();
        $completedToday = $reqs()->where('status', 'Completed')
                            ->whereDate('updated_at', $today)->count();
        $completedTotal = $reqs()->where('status', 'Completed')->count();

        $stats = [
            'total_requests'  => $totalRequests,
            'pending'         => $pendingCount,
            'active_plans'    => $activePlans,
            'completed_today' => $completedToday,
            'completed_total' => $completedTotal,
            'in_progress'     => $reqs()->where('status', 'Active')->count(),
        ];

        $recentRequests  = $reqs()->with('patient', 'doctor')
                            ->latest()->take(15)->get();

        $activePlansList = $plans()->with('dietRequest.patient')->whereIn('status', ['Active', 'Revised'])->latest()->take(10)->get();

        // Diet trend and status breakdown
        $dietTrend6m  = $this->getDietTrend(6, $user);
        $dietTrend12m = $this->getDietTrend(12, $user);
        $dietStatusBreakdown = [
            ['label' => 'Pending',     'count' => $stats['pending']],
            ['label' => 'In Progress', 'count' => $stats['in_progress']],
            ['label' => 'Completed',   'count' => $stats['completed_total']],
        ];

        return view('dashboard.diet', compact('stats', 'recentRequests', 'activePlansList', 'dietTrend6m', 'dietTrend12m', 'dietStatusBreakdown'));
    }
}
