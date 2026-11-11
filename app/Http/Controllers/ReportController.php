<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Patient;
use App\Models\OperatingRoom;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    public function __construct(protected ReportService $reportService) {}

    /**
     * Reports hub — shows accessible report categories.
     */
    public function index()
    {
        /** @var User|null $user */
        $user = Auth::user();
        $role = $user?->primaryRole;

        // Specialized clinical staff get redirected directly to their focused department report
        if (in_array($role, ['med-tech', 'rad-tech', 'radiologist', 'pharmacist', 'or-coordinator'], true)) {
            return match ($role) {
                'med-tech'                => redirect()->route('reports.laboratory.activity'),
                'rad-tech', 'radiologist' => redirect()->route('reports.radiology.activity'),
                'pharmacist'              => redirect()->route('reports.pharmacy.activity'),
                'or-coordinator'          => redirect()->route('reports.surgery.activity'),
                default                   => redirect()->route('dashboard'),
            };
        }

        $categories = $this->reportService->accessibleCategories($role);

        return view('reports.index', compact('categories', 'role'));
    }

    // ═══════════════════════════════════════════════════════════════
    // LABORATORY REPORTS
    // ═══════════════════════════════════════════════════════════════

    public function labActivity(Request $request)
    {
        $this->authorizeCategory('laboratory');
        [$from, $to] = $this->reportService->parseDateRange($request);
        $summary = $this->reportService->labActivitySummary($from, $to);
        $records = $this->reportService->labActivityDetails($from, $to, $request);
        $doctors = $this->doctorsForFilter();

        return view('reports.laboratory.activity', compact('summary', 'records', 'from', 'to', 'doctors'));
    }

    public function labVolume(Request $request)
    {
        $this->authorizeCategory('laboratory');
        [$from, $to] = $this->reportService->parseDateRange($request);
        $summary = $this->reportService->labActivitySummary($from, $to);
        $volume = $this->reportService->labTestVolume($from, $to);

        return view('reports.laboratory.volume', compact('summary', 'volume', 'from', 'to'));
    }

    public function labCompleted(Request $request)
    {
        $this->authorizeCategory('laboratory');
        [$from, $to] = $this->reportService->parseDateRange($request);
        $request->merge(['status' => 'Completed']);
        $summary = $this->reportService->labActivitySummary($from, $to);
        $records = $this->reportService->labActivityDetails($from, $to, $request);
        $doctors = $this->doctorsForFilter();

        return view('reports.laboratory.completed', compact('summary', 'records', 'from', 'to', 'doctors'));
    }

    public function labPending(Request $request)
    {
        $this->authorizeCategory('laboratory');
        [$from, $to] = $this->reportService->parseDateRange($request);
        $request->merge(['status' => 'Pending']);
        $summary = $this->reportService->labActivitySummary($from, $to);
        $records = $this->reportService->labActivityDetails($from, $to, $request);
        $doctors = $this->doctorsForFilter();

        return view('reports.laboratory.pending', compact('summary', 'records', 'from', 'to', 'doctors'));
    }

    // ═══════════════════════════════════════════════════════════════
    // RADIOLOGY REPORTS
    // ═══════════════════════════════════════════════════════════════

    public function radiologyActivity(Request $request)
    {
        $this->authorizeCategory('radiology');
        [$from, $to] = $this->reportService->parseDateRange($request);
        $summary = $this->reportService->radiologyActivitySummary($from, $to);
        $records = $this->reportService->radiologyActivityDetails($from, $to, $request);
        $doctors = $this->doctorsForFilter();

        return view('reports.radiology.activity', compact('summary', 'records', 'from', 'to', 'doctors'));
    }

    public function radiologyVolume(Request $request)
    {
        $this->authorizeCategory('radiology');
        [$from, $to] = $this->reportService->parseDateRange($request);
        $summary = $this->reportService->radiologyActivitySummary($from, $to);
        $volume = $this->reportService->radiologyVolumeByModality($from, $to);

        return view('reports.radiology.volume', compact('summary', 'volume', 'from', 'to'));
    }

    public function radiologyCompleted(Request $request)
    {
        $this->authorizeCategory('radiology');
        [$from, $to] = $this->reportService->parseDateRange($request);
        $request->merge(['status' => 'Completed']);
        $summary = $this->reportService->radiologyActivitySummary($from, $to);
        $records = $this->reportService->radiologyActivityDetails($from, $to, $request);
        $doctors = $this->doctorsForFilter();

        return view('reports.radiology.completed', compact('summary', 'records', 'from', 'to', 'doctors'));
    }

    public function radiologyPending(Request $request)
    {
        $this->authorizeCategory('radiology');
        [$from, $to] = $this->reportService->parseDateRange($request);
        $summary = $this->reportService->radiologyActivitySummary($from, $to);
        $records = $this->reportService->radiologyPendingInterpretations($from, $to, $request);

        return view('reports.radiology.pending', compact('summary', 'records', 'from', 'to'));
    }

    // ═══════════════════════════════════════════════════════════════
    // PHARMACY REPORTS
    // ═══════════════════════════════════════════════════════════════

    public function pharmacyActivity(Request $request)
    {
        $this->authorizeCategory('pharmacy');
        [$from, $to] = $this->reportService->parseDateRange($request);
        $summary = $this->reportService->pharmacyActivitySummary($from, $to);
        $records = $this->reportService->pharmacyActivityDetails($from, $to, $request);
        $pmsReport = $this->reportService->pharmacyComprehensiveReport($from, $to, $request);
        $doctors = $this->doctorsForFilter();

        return view('reports.pharmacy.activity', compact('summary', 'records', 'pmsReport', 'from', 'to', 'doctors'));
    }

    public function pharmacyDispensing(Request $request)
    {
        $this->authorizeCategory('pharmacy');
        [$from, $to] = $this->reportService->parseDateRange($request);
        $summary = $this->reportService->pharmacyActivitySummary($from, $to);
        $records = $this->reportService->dispensingDetails($from, $to, $request);
        $pharmacists = User::whereHas('roles', fn($q) => $q->where('slug', 'pharmacist'))->orderBy('name')->get(['id', 'name']);

        return view('reports.pharmacy.dispensing', compact('summary', 'records', 'from', 'to', 'pharmacists'));
    }

    public function pharmacyPending(Request $request)
    {
        $this->authorizeCategory('pharmacy');
        [$from, $to] = $this->reportService->parseDateRange($request);
        $request->merge(['status' => 'Pending']);
        $summary = $this->reportService->pharmacyActivitySummary($from, $to);
        $records = $this->reportService->pharmacyActivityDetails($from, $to, $request);

        return view('reports.pharmacy.pending', compact('summary', 'records', 'from', 'to'));
    }

    // ═══════════════════════════════════════════════════════════════
    // SURGERY REPORTS
    // ═══════════════════════════════════════════════════════════════

    public function surgeryActivity(Request $request)
    {
        $this->authorizeCategory('surgery');
        [$from, $to] = $this->reportService->parseDateRange($request);
        $summary = $this->reportService->surgeryActivitySummary($from, $to);
        $records = $this->reportService->surgeryActivityDetails($from, $to, $request);
        $doctors = $this->doctorsForFilter();

        return view('reports.surgery.activity', compact('summary', 'records', 'from', 'to', 'doctors'));
    }

    public function surgeryCompleted(Request $request)
    {
        $this->authorizeCategory('surgery');
        [$from, $to] = $this->reportService->parseDateRange($request);
        $request->merge(['status' => 'Completed']);
        $summary = $this->reportService->surgeryActivitySummary($from, $to);
        $records = $this->reportService->surgeryActivityDetails($from, $to, $request);
        $doctors = $this->doctorsForFilter();

        return view('reports.surgery.completed', compact('summary', 'records', 'from', 'to', 'doctors'));
    }

    public function surgeryCancelled(Request $request)
    {
        $this->authorizeCategory('surgery');
        [$from, $to] = $this->reportService->parseDateRange($request);
        $request->merge(['status' => 'Cancelled']);
        $summary = $this->reportService->surgeryActivitySummary($from, $to);
        $records = $this->reportService->surgeryActivityDetails($from, $to, $request);

        return view('reports.surgery.cancelled', compact('summary', 'records', 'from', 'to'));
    }

    public function surgeryOrUtilization(Request $request)
    {
        $this->authorizeCategory('surgery');
        [$from, $to] = $this->reportService->parseDateRange($request);
        $summary = $this->reportService->surgeryActivitySummary($from, $to);
        $utilization = $this->reportService->orUtilization($from, $to);
        $rooms = OperatingRoom::where('is_active', true)->get();

        return view('reports.surgery.or-utilization', compact('summary', 'utilization', 'from', 'to', 'rooms'));
    }

    // ═══════════════════════════════════════════════════════════════
    // DIET & NUTRITION REPORTS
    // ═══════════════════════════════════════════════════════════════

    public function dietActivity(Request $request)
    {
        $this->authorizeCategory('diet');
        [$from, $to] = $this->reportService->parseDateRange($request);
        $summary = $this->reportService->dietActivitySummary($from, $to);
        $records = $this->reportService->dietActivityDetails($from, $to, $request);

        return view('reports.diet.activity', compact('summary', 'records', 'from', 'to'));
    }

    public function dietActive(Request $request)
    {
        $this->authorizeCategory('diet');
        [$from, $to] = $this->reportService->parseDateRange($request);
        $summary = $this->reportService->dietActivitySummary($from, $to);
        $records = $this->reportService->dietPlansByStatus($from, $to, 'Active', $request);
        $dietitians = User::whereHas('roles', fn($q) => $q->where('slug', 'doctor'))
            ->orWhereIn('id', \App\Models\DietPlan::select('dietitian_id'))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('reports.diet.active', compact('summary', 'records', 'from', 'to', 'dietitians'));
    }

    public function dietCompleted(Request $request)
    {
        $this->authorizeCategory('diet');
        [$from, $to] = $this->reportService->parseDateRange($request);
        $summary = $this->reportService->dietActivitySummary($from, $to);
        $records = $this->reportService->dietPlansByStatus($from, $to, 'Completed', $request);
        $dietitians = User::whereHas('roles', fn($q) => $q->where('slug', 'doctor'))
            ->orWhereIn('id', \App\Models\DietPlan::select('dietitian_id'))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('reports.diet.completed', compact('summary', 'records', 'from', 'to', 'dietitians'));
    }

    // ═══════════════════════════════════════════════════════════════
    // CLINICAL SUMMARY REPORTS
    // ═══════════════════════════════════════════════════════════════

    public function clinicalPatientActivity(Request $request)
    {
        $this->authorizeCategory('clinical');
        [$from, $to] = $this->reportService->parseDateRange($request);

        /** @var User|null $user */
        $user = Auth::user();
        $isDoctor = $user?->primaryRole === 'doctor';
        $doctorId = $isDoctor ? (int) $user->id : null;

        $query = Patient::query();
        if ($isDoctor && $user) {
            $query->authorizedForDoctor($user);
        }

        $query->withCount([
            'labRequests' => fn($q) => $q->whereBetween('requested_at', [$from, $to])
                ->when($doctorId, fn($sq) => $sq->where('doctor_id', $doctorId)),
            'radiologyRequests' => fn($q) => $q->whereBetween('requested_at', [$from, $to])
                ->when($doctorId, fn($sq) => $sq->where('doctor_id', $doctorId)),
            'prescriptions' => fn($q) => $q->whereBetween('prescribed_at', [$from, $to])
                ->when($doctorId, fn($sq) => $sq->where('doctor_id', $doctorId)),
            'surgeryRequests' => fn($q) => $q->whereBetween('requested_at', [$from, $to])
                ->when($isDoctor && $user, fn($sq) => $sq->visibleTo($user)),
            'dietRequests' => fn($q) => $q->whereBetween('requested_at', [$from, $to])
                ->when($isDoctor && $user, fn($sq) => $sq->visibleTo($user)),
        ])->havingRaw('(lab_requests_count + radiology_requests_count + prescriptions_count + surgery_requests_count + diet_requests_count) > 0');

        $records = $query->orderBy('last_name')->paginate(25)->withQueryString();

        return view('reports.clinical.patient-activity', compact('records', 'from', 'to'));
    }

    public function clinicalDoctorActivity(Request $request)
    {
        $this->authorizeCategory('clinical');
        [$from, $to] = $this->reportService->parseDateRange($request);

        /** @var User|null $user */
        $user = Auth::user();
        $isDoctor = $user?->primaryRole === 'doctor';

        $doctorsQuery = User::whereHas('roles', fn($q) => $q->where('slug', 'doctor'));
        if ($isDoctor && $user) {
            $doctorsQuery->whereKey($user->id);
        }

        $doctors = $doctorsQuery
            ->withCount([
                'labRequestsAsDoctor' => fn($q) => $q->whereBetween('requested_at', [$from, $to]),
                'radiologyRequestsAsDoctor' => fn($q) => $q->whereBetween('requested_at', [$from, $to]),
                'prescriptionsAsDoctor' => fn($q) => $q->whereBetween('prescribed_at', [$from, $to]),
                'surgeryRequestsAsDoctor' => fn($q) => $q->whereBetween('requested_at', [$from, $to]),
            ])->orderBy('name')->paginate(25)->withQueryString();

        return view('reports.clinical.doctor-activity', compact('doctors', 'from', 'to'));
    }

    public function clinicalServicesSummary(Request $request)
    {
        $this->authorizeCategory('clinical');
        [$from, $to] = $this->reportService->parseDateRange($request);
        $summary = $this->reportService->clinicalServicesSummary($from, $to);

        return view('reports.clinical.services-summary', compact('summary', 'from', 'to'));
    }

    // ── Authorization & filter helpers ───────────────────────────

    private function doctorsForFilter()
    {
        if (Auth::user()?->primaryRole === 'doctor') {
            return collect();
        }

        return User::whereHas('roles', fn($q) => $q->where('slug', 'doctor'))->orderBy('name')->get(['id', 'name']);
    }

    private function authorizeCategory(string $category): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        $role = $user?->primaryRole;
        if (!$this->reportService->canAccess($category, $role)) {
            abort(403, 'You are not authorized to access this report.');
        }
    }
}
