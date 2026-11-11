<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\DietRequest;
use App\Models\DispensingRecord;
use App\Models\LabRequest;
use App\Models\Prescription;
use App\Models\RadiologyRequest;
use App\Models\SurgeryRequest;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OperationsMonitorController extends Controller
{
    public function __construct(
        protected SettingsService $settings
    ) {}

    /**
     * Display hospital operational activity and workflow monitoring dashboard.
     */
    public function index(): View
    {
        $todayStart = now()->startOfDay();
        $weekStart  = now()->startOfWeek();
        $monthStart = now()->startOfMonth();

        // 1. Operational Volume Summaries across all active clinical modules
        $volumes = [
            'lab' => [
                'today' => LabRequest::where('created_at', '>=', $todayStart)->count(),
                'week'  => LabRequest::where('created_at', '>=', $weekStart)->count(),
                'month' => LabRequest::where('created_at', '>=', $monthStart)->count(),
                'total' => LabRequest::count(),
            ],
            'radiology' => [
                'today' => RadiologyRequest::where('created_at', '>=', $todayStart)->count(),
                'week'  => RadiologyRequest::where('created_at', '>=', $weekStart)->count(),
                'month' => RadiologyRequest::where('created_at', '>=', $monthStart)->count(),
                'total' => RadiologyRequest::count(),
            ],
            'pharmacy' => [
                'today' => Prescription::where('created_at', '>=', $todayStart)->count(),
                'week'  => Prescription::where('created_at', '>=', $weekStart)->count(),
                'month' => Prescription::where('created_at', '>=', $monthStart)->count(),
                'total' => Prescription::count(),
            ],
            'dispensing' => [
                'today' => DispensingRecord::where('created_at', '>=', $todayStart)->count(),
                'week'  => DispensingRecord::where('created_at', '>=', $weekStart)->count(),
                'month' => DispensingRecord::where('created_at', '>=', $monthStart)->count(),
                'total' => DispensingRecord::count(),
            ],
            'surgery' => [
                'today' => SurgeryRequest::where('created_at', '>=', $todayStart)->count(),
                'week'  => SurgeryRequest::where('created_at', '>=', $weekStart)->count(),
                'month' => SurgeryRequest::where('created_at', '>=', $monthStart)->count(),
                'total' => SurgeryRequest::count(),
            ],
            'diet' => [
                'today' => DietRequest::where('created_at', '>=', $todayStart)->count(),
                'week'  => DietRequest::where('created_at', '>=', $weekStart)->count(),
                'month' => DietRequest::where('created_at', '>=', $monthStart)->count(),
                'total' => DietRequest::count(),
            ],
        ];

        // 2. Turnaround Time (TAT) Monitoring — computed ONLY from real workflow timestamps
        // Lab TAT: from received_at to completed_at
        $labTatMinutes = DB::table('lab_requests')
            ->whereNotNull('received_at')
            ->whereNotNull('completed_at')
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, received_at, completed_at)) as avg_tat')
            ->value('avg_tat');

        // Radiology TAT: from scheduled_at to completed_at
        $radTatMinutes = DB::table('radiology_requests')
            ->whereNotNull('scheduled_at')
            ->whereNotNull('completed_at')
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, scheduled_at, completed_at)) as avg_tat')
            ->value('avg_tat');

        $tatStats = [
            'lab_avg_minutes' => $labTatMinutes ? round((float) $labTatMinutes, 1) : null,
            'rad_avg_minutes' => $radTatMinutes ? round((float) $radTatMinutes, 1) : null,
        ];

        // 3. Queue Delays & Aging Threshold
        $thresholdMinutes = (int) $this->settings->get('ops.queue_delay_threshold_minutes', 240);
        $delayCutoff = now()->subMinutes($thresholdMinutes);

        $delayedLabRequests = LabRequest::whereNotIn('status', ['completed', 'cancelled'])
            ->where('created_at', '<', $delayCutoff)
            ->with(['doctor'])
            ->orderBy('created_at')
            ->take(10)
            ->get();

        $delayedRadRequests = RadiologyRequest::whereNotIn('status', ['completed', 'cancelled'])
            ->where('created_at', '<', $delayCutoff)
            ->with(['doctor'])
            ->orderBy('created_at')
            ->take(10)
            ->get();

        // 4. Read-only Priority Queue (STAT, Urgent, Emergency requests)
        $priorityLab = LabRequest::whereIn('priority', ['stat', 'urgent'])
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->with(['doctor'])
            ->orderBy('created_at')
            ->take(10)
            ->get();

        $priorityRad = RadiologyRequest::whereIn('priority', ['stat', 'urgent'])
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->with(['doctor'])
            ->orderBy('created_at')
            ->take(10)
            ->get();

        // 5. Account Status Overview
        $accountStats = [
            'total'    => User::withTrashed()->count(),
            'active'   => User::whereNull('deleted_at')->where('is_active', true)->whereNull('locked_at')->count(),
            'inactive' => User::whereNull('deleted_at')->where('is_active', false)->whereNull('locked_at')->count(),
            'locked'   => User::whereNull('deleted_at')->whereNotNull('locked_at')->count(),
            'archived' => User::onlyTrashed()->count(),
        ];

        return view('admin.operations.index', compact(
            'volumes',
            'tatStats',
            'thresholdMinutes',
            'delayedLabRequests',
            'delayedRadRequests',
            'priorityLab',
            'priorityRad',
            'accountStats'
        ));
    }
}
