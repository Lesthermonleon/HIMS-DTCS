<?php

namespace App\Http\Controllers\PatientPortal;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\DietPlan;
use App\Models\Invoice;
use App\Models\LabRequest;
use App\Models\LabResult;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\RadiologyReport;
use App\Models\RadiologyRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * PatientPortalController — Personal Healthcare Portal for Patients.
 * Enforces zero-trust record ownership (IDOR prevention) on every action.
 */
class PatientPortalController extends Controller
{
    public function __construct(private readonly \App\Services\Appointments\AppointmentScheduler $scheduler)
    {
    }

    /**
     * Resolve the authenticated patient profile or abort.
     */
    protected function getAuthenticatedPatient(): Patient
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$user || !$user->isPatient()) {
            abort(403, 'Unauthorized access. Patient credentials required.');
        }

        $patient = $user->patient ?? Patient::where('user_id', $user->id)->first();

        if (!$patient) {
            abort(403, 'No patient medical record is linked to this portal account. Please contact hospital intake.');
        }

        return $patient;
    }

    /**
     * Patient Portal Dashboard
     */
    public function dashboard(): View
    {
        $patient = $this->getAuthenticatedPatient();

        // Summary Statistics (Strictly Patient-Scoped)
        $upcomingAppointments = Appointment::where('patient_id', $patient->id)
            ->whereIn('status', [Appointment::STATUS_REQUESTED, Appointment::STATUS_CONFIRMED])
            ->where('scheduled_at', '>=', now())
            ->orderBy('scheduled_at')
            ->take(5)
            ->get();

        $releasedLabCount = LabResult::whereHas('requestItem.labRequest', fn($q) => $q->where('patient_id', $patient->id))
            ->where('status', 'Released')
            ->count();

        $pendingLabCount = LabRequest::where('patient_id', $patient->id)
            ->whereIn('status', ['Pending', 'In Progress'])
            ->count();

        $releasedRadCount = RadiologyReport::whereHas('radiologyRequest', fn($q) => $q->where('patient_id', $patient->id))
            ->where('status', 'Released')
            ->count();

        $pendingRadCount = RadiologyRequest::where('patient_id', $patient->id)
            ->whereIn('status', ['Pending', 'Scheduled', 'In Progress'])
            ->count();

        $activePrescriptionsCount = Prescription::where('patient_id', $patient->id)
            ->whereIn('status', ['Verified', 'Dispensed', 'Partially Dispensed'])
            ->count();

        $activeDietPlans = DietPlan::with(['dietRequest', 'createdBy'])
            ->whereHas('dietRequest', fn($sub) => $sub->where('patient_id', $patient->id))
            ->whereIn('status', ['Active', 'Revised', 'Completed'])
            ->latest()
            ->take(4)
            ->get();

        $availableClinicalReportsCount = $releasedLabCount + $releasedRadCount + $activeDietPlans->count();

        $outstandingBalance = Invoice::where('patient_id', $patient->id)
            ->whereIn('status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PARTIAL])
            ->sum('balance');

        $recentResults = LabResult::with(['requestItem.labTest', 'requestItem.labRequest.doctor'])
            ->whereHas('requestItem.labRequest', fn($q) => $q->where('patient_id', $patient->id))
            ->where('status', 'Released')
            ->latest('released_at')
            ->take(4)
            ->get();

        $recentRadReports = RadiologyReport::with(['radiologyRequest.doctor'])
            ->whereHas('radiologyRequest', fn($q) => $q->where('patient_id', $patient->id))
            ->where('status', 'Released')
            ->latest('released_at')
            ->take(4)
            ->get();

        $recentPrescriptions = Prescription::with(['items', 'doctor'])
            ->where('patient_id', $patient->id)
            ->whereIn('status', ['Verified', 'Dispensed', 'Partially Dispensed'])
            ->latest()
            ->take(4)
            ->get();

        // 6-Month Personal Clinical Activity Trend
        $activityLabels = [];
        $appointmentSeries = [];
        $diagnosticSeries = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $start = $month->copy()->startOfMonth();
            $end = $month->copy()->endOfMonth();
            $activityLabels[] = $month->format('M Y');

            $appointmentSeries[] = Appointment::where('patient_id', $patient->id)
                ->whereBetween('scheduled_at', [$start, $end])
                ->count();

            $diagnosticSeries[] = LabRequest::where('patient_id', $patient->id)->whereBetween('created_at', [$start, $end])->count()
                + RadiologyRequest::where('patient_id', $patient->id)->whereBetween('created_at', [$start, $end])->count()
                + Prescription::where('patient_id', $patient->id)->whereBetween('created_at', [$start, $end])->count();
        }
        $patientActivityTrend6m = [
            'labels'       => $activityLabels,
            'appointments' => $appointmentSeries,
            'diagnostics'  => $diagnosticSeries,
        ];

        $resultHistoryBreakdown = [
            'Released Lab Results'  => $releasedLabCount,
            'Released Imaging'      => $releasedRadCount,
            'Active Prescriptions'  => $activePrescriptionsCount,
            'Clinical Diet Plans'   => $activeDietPlans->count(),
        ];

        // Available doctors for booking modal
        $doctors = User::whereHas('roles', fn($q) => $q->where('slug', 'doctor'))
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'department']);

        return view('patient.dashboard', compact(
            'patient',
            'upcomingAppointments',
            'releasedLabCount',
            'pendingLabCount',
            'releasedRadCount',
            'pendingRadCount',
            'activePrescriptionsCount',
            'activeDietPlans',
            'availableClinicalReportsCount',
            'outstandingBalance',
            'recentResults',
            'recentRadReports',
            'recentPrescriptions',
            'patientActivityTrend6m',
            'resultHistoryBreakdown',
            'doctors'
        ));
    }

    /**
     * Patient Profile & Permitted Contact Updates
     */
    public function profile(): View
    {
        $patient = $this->getAuthenticatedPatient();
        return view('patient.profile', compact('patient'));
    }

    /**
     * Update permitted contact details only (phone, email, emergency contact, address).
     */
    public function updateProfile(Request $request): RedirectResponse
    {
        $patient = $this->getAuthenticatedPatient();

        $validated = $request->validate([
            'phone'                   => ['nullable', 'string', 'max:20'],
            'address'                 => ['nullable', 'string', 'max:500'],
            'emergency_contact_name'  => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
        ]);

        $patient->update($validated);

        ActivityLog::create([
            'user_id'       => Auth::id(),
            'action'        => 'Patient Profile Contact Updated',
            'module'        => 'Patient Portal',
            'description'   => "Patient [{$patient->patient_no}] updated contact demographic information.",
            'ip_address'    => $request->ip(),
            'severity'      => ActivityLog::SEVERITY_INFO,
            'result'        => ActivityLog::RESULT_SUCCESS,
            'logged_at'     => now(),
        ]);

        return redirect()->route('patient.profile')->with('success', 'Contact details updated successfully.');
    }

    /**
     * View Appointments
     */
    public function appointments(): View
    {
        $patient = $this->getAuthenticatedPatient();

        $appointments = Appointment::with('doctor')
            ->where('patient_id', $patient->id)
            ->orderByDesc('scheduled_at')
            ->paginate(15);

        $doctors = User::whereHas('roles', fn($q) => $q->where('slug', 'doctor'))
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'department']);

        return view('patient.appointments', compact('patient', 'appointments', 'doctors'));
    }

    /**
     * Request / Book an Appointment
     */
    public function bookAppointment(Request $request): RedirectResponse
    {
        $patient = $this->getAuthenticatedPatient();

        // NOTE: any client-supplied patient_id is deliberately ignored; ownership comes from the session.
        $validated = $request->validate([
            'doctor_id'    => ['required', 'integer', 'exists:users,id'],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'type'         => ['required', 'string', 'in:In-Person Consultation,Follow-up Visit,Diagnostic Review'],
            'reason'       => ['nullable', 'string', 'max:1000'],
        ]);

        $appointment = $this->scheduler->book([
            'patient_id'       => $patient->id,
            'doctor_id'        => $validated['doctor_id'],
            'scheduled_at'     => $validated['scheduled_at'],
            'duration_minutes' => 30,
            'type'             => $validated['type'],
            'reason'           => $validated['reason'] ?? null,
        ], Appointment::STATUS_REQUESTED);

        ActivityLog::create([
            'user_id'       => Auth::id(),
            'action'        => 'Appointment Requested by Patient',
            'module'        => 'Appointments',
            'description'   => "Patient requested appointment [{$appointment->appointment_no}] with doctor ID {$appointment->doctor_id} on {$appointment->scheduled_at->format('Y-m-d H:i')}.",
            'ip_address'    => $request->ip(),
            'severity'      => ActivityLog::SEVERITY_INFO,
            'result'        => ActivityLog::RESULT_SUCCESS,
            'logged_at'     => now(),
        ]);

        return redirect()->route('patient.appointments')->with('success', "Appointment request [{$appointment->appointment_no}] submitted successfully.");
    }

    /**
     * Cancel one of the signed-in patient's own appointments (Requested/Confirmed only).
     * Returns 404 for appointments that belong to anyone else so existence is not disclosed.
     */
    public function cancelAppointment(Request $request, Appointment $appointment): RedirectResponse
    {
        $patient = $this->getAuthenticatedPatient();

        abort_unless((int) $appointment->patient_id === (int) $patient->id, 404);

        if (! in_array($appointment->status, [Appointment::STATUS_REQUESTED, Appointment::STATUS_CONFIRMED], true)) {
            return redirect()->route('patient.appointments')
                ->withErrors(['status' => 'Only requested or confirmed appointments can be cancelled online. Please contact the hospital.']);
        }

        $appointment->update(['status' => Appointment::STATUS_CANCELLED]);

        ActivityLog::create([
            'user_id'     => Auth::id(),
            'action'      => 'Appointment Cancelled by Patient',
            'module'      => 'Appointments',
            'description' => "Patient cancelled appointment [{$appointment->appointment_no}].",
            'ip_address'  => $request->ip(),
            'severity'    => ActivityLog::SEVERITY_INFO,
            'result'      => ActivityLog::RESULT_SUCCESS,
            'logged_at'   => now(),
        ]);

        return redirect()->route('patient.appointments')->with('success', "Appointment [{$appointment->appointment_no}] was cancelled.");
    }

    /**
     * View Diagnostic Results (Strictly Released Only)
     */
    public function results(): View
    {
        $patient = $this->getAuthenticatedPatient();

        // 1. Released Laboratory Results
        $labResults = LabResult::with(['requestItem.labRequest.doctor'])
            ->whereHas('requestItem.labRequest', fn($q) => $q->where('patient_id', $patient->id))
            ->where('status', 'Released')
            ->latest('released_at')
            ->paginate(10, ['*'], 'lab_page');

        // 2. Released Radiology Reports
        $radiologyReports = RadiologyReport::with(['radiologyRequest.doctor'])
            ->whereHas('radiologyRequest', fn($q) => $q->where('patient_id', $patient->id))
            ->where('status', 'Released')
            ->latest('released_at')
            ->paginate(10, ['*'], 'rad_page');

        return view('patient.results', compact('patient', 'labResults', 'radiologyReports'));
    }

    /**
     * View Prescriptions
     */
    public function prescriptions(): View
    {
        $patient = $this->getAuthenticatedPatient();

        $prescriptions = Prescription::with(['items', 'doctor'])
            ->where('patient_id', $patient->id)
            ->whereIn('status', ['Verified', 'Dispensed', 'Partially Dispensed'])
            ->latest()
            ->paginate(15);

        return view('patient.prescriptions', compact('patient', 'prescriptions'));
    }

    /**
     * View Treatment Plans
     */
    public function treatments(): View
    {
        $patient = $this->getAuthenticatedPatient();

        $dietPlans = DietPlan::whereHas('dietRequest', fn($sub) => $sub->where('patient_id', $patient->id))
            ->latest()
            ->get();

        return view('patient.treatments', compact('patient', 'dietPlans'));
    }

    /**
     * View Bills & Receipts
     */
    public function billing(): View
    {
        $patient = $this->getAuthenticatedPatient();

        $invoices = Invoice::where('patient_id', $patient->id)
            ->latest()
            ->paginate(15);

        $totalBilled = Invoice::where('patient_id', $patient->id)->where('status', '!=', Invoice::STATUS_VOIDED)->sum('total_amount');
        $totalPaid   = Invoice::where('patient_id', $patient->id)->where('status', '!=', Invoice::STATUS_VOIDED)->sum('paid_amount');
        $balanceDue  = Invoice::where('patient_id', $patient->id)->whereIn('status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PARTIAL])->sum('balance');

        return view('patient.billing', compact('patient', 'invoices', 'totalBilled', 'totalPaid', 'balanceDue'));
    }
}
