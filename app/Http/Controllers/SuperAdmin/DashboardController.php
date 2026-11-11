<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\BackupRun;
use App\Models\CatalogService;
use App\Models\DietRequest;
use App\Models\LabRequest;
use App\Models\Prescription;
use App\Models\PrivilegedAccessRequest;
use App\Models\RadiologyRequest;
use App\Models\Role;
use App\Models\SurgeryRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        // 5 top-level user categories as required:
        // 1. Super Admin, 2. Admin, 3. Staff (all 7 staff roles), 4. Doctor, 5. Patient
        $superAdminCount = User::whereHas('roles', fn($q) => $q->where('slug', 'super_admin'))->count();
        $adminCount      = User::whereHas('roles', fn($q) => $q->where('slug', 'admin'))->count();
        $doctorCount     = User::whereHas('roles', fn($q) => $q->where('slug', 'doctor'))->count();
        $staffCount      = User::whereHas('roles', fn($q) => $q->whereIn('slug', [
            'med-tech', 'rad-tech', 'radiologist', 'pharmacist', 'dietitian', 'or-coordinator', 'or-staff',
        ]))->count();

        // Count patients safely (aggregate count only — no patient PII exposed)
        $patientCount = DB::table('patients')->count();

        $totalAccounts    = User::count();
        $activeAccounts   = User::where('is_active', true)->whereNull('locked_at')->count();
        $inactiveAccounts = User::where('is_active', false)->whereNull('locked_at')->count();
        $lockedAccounts   = User::whereNotNull('locked_at')->count();
        $failedAuthUsers  = User::where('failed_attempts', '>', 0)->count();

        // Recent security events (last 24h) for Security Alerts count
        $securityEvents24h = ActivityLog::where(function ($q) {
                $q->where('module', ActivityLog::MODULE_SECURITY)
                  ->orWhereIn('severity', [ActivityLog::SEVERITY_WARNING, ActivityLog::SEVERITY_CRITICAL])
                  ->orWhereIn('result', [ActivityLog::RESULT_FAILED, ActivityLog::RESULT_BLOCKED]);
            })
            ->where('logged_at', '>=', now()->subHours(24))
            ->count();

        $securityAlertsCount = $lockedAccounts + $failedAuthUsers + $securityEvents24h;

        $accountStats = [
            'total_accounts' => $totalAccounts,
            'active'         => $activeAccounts,
            'inactive'       => $inactiveAccounts,
            'locked'         => $lockedAccounts,
            'total_modules'  => 5,
            'security_alerts'=> $securityAlertsCount,
            'super_admin'    => $superAdminCount,
            'admin'          => $adminCount,
            'doctor'         => $doctorCount,
            'staff'          => $staffCount,
            'patient'        => $patientCount,
        ];

        // 1. User Account Growth (6m & 12m)
        $accountGrowth6m  = $this->getAccountGrowthTrend(6);
        $accountGrowth12m = $this->getAccountGrowthTrend(12);

        // 2. Account Distribution by Role
        $roleDistribution = Role::withCount('users')->orderBy('name')->get()->map(fn($r) => [
            'name'  => $r->name,
            'slug'  => $r->slug,
            'count' => (int) $r->users_count,
        ])->values();

        // 3. Account Status Distribution
        $statusDistribution = [
            'Active'   => $activeAccounts,
            'Inactive' => $inactiveAccounts,
            'Locked'   => $lockedAccounts,
        ];

        // 4. Login & Authentication Activity + Security Events & Lockout Trends (7 days)
        $authAndSecurity7d = collect(range(6, 0))->map(function (int $daysAgo) {
            $date = now()->subDays($daysAgo);
            $dateStr = $date->toDateString();

            $authCount = ActivityLog::whereDate('logged_at', $dateStr)
                ->where(function ($q) {
                    $q->where('module', ActivityLog::MODULE_AUTH)
                      ->orWhere('action', 'like', '%Login%')
                      ->orWhere('action', 'like', '%Logout%')
                      ->orWhere('action', 'like', '%OTP%');
                })
                ->count();

            $securityCount = ActivityLog::whereDate('logged_at', $dateStr)
                ->where(function ($q) {
                    $q->where('module', ActivityLog::MODULE_SECURITY)
                      ->orWhereIn('severity', [ActivityLog::SEVERITY_WARNING, ActivityLog::SEVERITY_CRITICAL])
                      ->orWhereIn('result', [ActivityLog::RESULT_FAILED, ActivityLog::RESULT_BLOCKED]);
                })
                ->count();

            return [
                'label'    => $date->format('M d'),
                'auth'     => $authCount,
                'security' => $securityCount,
            ];
        })->values();

        // 5. DTCS Module Activity Overview & Health (Aggregate non-PII counts)
        $moduleHealth = [
            [
                'code'        => 'LIS',
                'name'        => 'Laboratory (LIS)',
                'staff_count' => User::whereHas('roles', fn($q) => $q->where('slug', 'med-tech'))->where('is_active', true)->count(),
                'total_ops'   => LabRequest::count(),
                'pending_ops' => LabRequest::where('status', 'Pending')->count(),
                'status'      => 'Operational',
            ],
            [
                'code'        => 'RIS',
                'name'        => 'Radiology (RIS)',
                'staff_count' => User::whereHas('roles', fn($q) => $q->whereIn('slug', ['rad-tech', 'radiologist']))->where('is_active', true)->count(),
                'total_ops'   => RadiologyRequest::count(),
                'pending_ops' => RadiologyRequest::where('status', 'Pending')->count(),
                'status'      => 'Operational',
            ],
            [
                'code'        => 'PMS',
                'name'        => 'Pharmacy (PMS)',
                'staff_count' => User::whereHas('roles', fn($q) => $q->where('slug', 'pharmacist'))->where('is_active', true)->count(),
                'total_ops'   => Prescription::count(),
                'pending_ops' => Prescription::where('status', 'Pending')->count(),
                'status'      => 'Operational',
            ],
            [
                'code'        => 'SORS',
                'name'        => 'Surgery (SORS)',
                'staff_count' => User::whereHas('roles', fn($q) => $q->whereIn('slug', ['or-coordinator', 'or-staff']))->where('is_active', true)->count(),
                'total_ops'   => SurgeryRequest::count(),
                'pending_ops' => SurgeryRequest::where('status', 'Pending')->count(),
                'status'      => 'Operational',
            ],
            [
                'code'        => 'DNMS',
                'name'        => 'Nutrition (DNMS)',
                'staff_count' => $doctorCount,
                'total_ops'   => DietRequest::count(),
                'pending_ops' => DietRequest::where('status', 'Pending')->count(),
                'status'      => 'Operational',
            ],
        ];

        // Latest backup status
        $latestBackup = BackupRun::orderBy('id', 'desc')->first();

        // Pending Doctor provisioning requests
        $pendingDoctorRequests = PrivilegedAccessRequest::where('request_type', 'doctor_provisioning')
            ->where('status', 'pending')
            ->count();

        // Recent Security Events (Locked accounts, failed logins, or WARNING/CRITICAL security logs)
        $recentSecurityEvents = ActivityLog::with('user')
            ->where(function ($q) {
                $q->where('module', ActivityLog::MODULE_SECURITY)
                  ->orWhereIn('severity', [ActivityLog::SEVERITY_WARNING, ActivityLog::SEVERITY_CRITICAL])
                  ->orWhereIn('result', [ActivityLog::RESULT_FAILED, ActivityLog::RESULT_BLOCKED]);
            })
            ->orderByDesc('logged_at')
            ->take(6)
            ->get();

        // Recent Administrative Activities
        $recentAdminActivities = ActivityLog::with('user')
            ->whereIn('module', [
                'User Management', 'System Administration', 'System Settings',
                'Master Catalog', 'Clinical Codes', 'Hospital Settings', 'Backup & Maintenance',
            ])
            ->orderByDesc('logged_at')
            ->take(6)
            ->get();

        // Recent privileged security audit logs (Audit Vault)
        $recentAuditLogs = ActivityLog::with('user')
            ->whereIn('module', ActivityLog::SUPER_ADMIN_MODULES)
            ->orderBy('logged_at', 'desc')
            ->take(8)
            ->get();

        // Database engine info
        try {
            $dbVersion = DB::selectOne('SELECT VERSION() as v')->v ?? 'MySQL';
        } catch (\Throwable $e) {
            $dbVersion = 'Unknown';
        }

        $catalogCount = class_exists(CatalogService::class) ? CatalogService::count() : 0;

        return view('super_admin.dashboard', compact(
            'accountStats',
            'accountGrowth6m',
            'accountGrowth12m',
            'roleDistribution',
            'statusDistribution',
            'authAndSecurity7d',
            'moduleHealth',
            'latestBackup',
            'pendingDoctorRequests',
            'recentSecurityEvents',
            'recentAdminActivities',
            'recentAuditLogs',
            'dbVersion',
            'catalogCount'
        ));
    }

    /**
     * Build monthly user account creation trend.
     *
     * @return array<int, array{label: string, total: int}>
     */
    private function getAccountGrowthTrend(int $months): array
    {
        $result = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $date  = now()->subMonths($i);
            $start = $date->copy()->startOfMonth();
            $end   = $date->copy()->endOfMonth();
            $result[] = [
                'label' => $date->format('M Y'),
                'total' => User::withTrashed()->whereBetween('created_at', [$start, $end])->count(),
            ];
        }
        return $result;
    }
}
