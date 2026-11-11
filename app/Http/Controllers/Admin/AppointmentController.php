<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use App\Services\Appointments\AppointmentScheduler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * AppointmentController — Appointments & clinic queue.
 *
 * Admin: full operational view and scheduling.
 * Doctor: "My Appointments" — scoped to their own appointments only (enforced here,
 *         not just in the view), and limited to clinical status transitions.
 */
class AppointmentController extends Controller
{
    public function __construct(private readonly AppointmentScheduler $scheduler)
    {
    }

    /** Doctors (who are not also Admin) see only their own appointments. */
    private function isDoctorScope(User $user): bool
    {
        return $user->hasRole('doctor') && ! $user->hasRole('admin');
    }

    public function index(Request $request): View
    {
        /** @var User $user */
        $user = Auth::user();
        $doctorScope = $this->isDoctorScope($user);

        $base = Appointment::query();
        if ($doctorScope) {
            $base->where('doctor_id', $user->id);
        }

        $query = (clone $base)->with(['patient', 'doctor']);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if (! $doctorScope && ($doctorId = $request->input('doctor_id'))) {
            $query->where('doctor_id', $doctorId);
        }

        if ($date = $request->input('date')) {
            $query->whereDate('scheduled_at', $date);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('appointment_no', 'like', "%{$search}%")
                  ->orWhereHas('patient', function ($pq) use ($search) {
                      $pq->where('first_name', 'like', "%{$search}%")
                         ->orWhere('last_name', 'like', "%{$search}%")
                         ->orWhere('patient_no', 'like', "%{$search}%");
                  });
            });
        }

        $appointments = $query->orderByDesc('scheduled_at')->paginate(15)->withQueryString();

        $doctors = User::whereHas('roles', fn ($q) => $q->where('slug', 'doctor'))
            ->where('is_active', true)
            ->when($doctorScope, fn ($q) => $q->whereKey($user->id))
            ->orderBy('name')
            ->get();

        $patients = Patient::orderBy('last_name')->take(100)->get();

        $stats = [
            'total'       => (clone $base)->count(),
            'requested'   => (clone $base)->where('status', Appointment::STATUS_REQUESTED)->count(),
            'confirmed'   => (clone $base)->where('status', Appointment::STATUS_CONFIRMED)->count(),
            'today'       => (clone $base)->whereDate('scheduled_at', today())->count(),
            'in_progress' => (clone $base)->where('status', Appointment::STATUS_IN_CONSULTATION)->count(),
        ];

        $scheduler = $this->scheduler;

        return view('admin.appointments.index', compact('appointments', 'doctors', 'patients', 'stats', 'doctorScope', 'scheduler'));
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'patient_id'       => ['required', 'exists:patients,id'],
            'doctor_id'        => ['required', 'integer', 'exists:users,id'],
            'scheduled_at'     => ['required', 'date', 'after:-1 hour'],
            'duration_minutes' => ['nullable', 'integer', 'min:10', 'max:120'],
            'type'             => ['required', 'string', 'max:64'],
            'reason'           => ['nullable', 'string', 'max:1000'],
        ]);

        // A Doctor may only schedule against their own calendar.
        if ($this->isDoctorScope($user)) {
            $validated['doctor_id'] = $user->id;
        }

        $appointment = $this->scheduler->book($validated, Appointment::STATUS_CONFIRMED);

        ActivityLog::create([
            'user_id'     => Auth::id(),
            'action'      => 'Appointment Scheduled',
            'module'      => 'Hospital Operations',
            'description' => "Appointment [{$appointment->appointment_no}] scheduled for patient ID {$appointment->patient_id}.",
            'ip_address'  => $request->ip(),
            'severity'    => ActivityLog::SEVERITY_INFO,
            'result'      => ActivityLog::RESULT_SUCCESS,
            'logged_at'   => now(),
        ]);

        return redirect()->route('admin.appointments.index')
            ->with('success', "Appointment [{$appointment->appointment_no}] scheduled successfully.");
    }

    public function updateStatus(Request $request, Appointment $appointment): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        // Doctors may act only on their own appointments.
        if ($this->isDoctorScope($user)) {
            abort_unless((int) $appointment->doctor_id === (int) $user->id, 403, 'You can only update your own appointments.');
        }

        $validated = $request->validate([
            'status' => ['required', 'in:Requested,Confirmed,CheckedIn,In Consultation,Completed,Cancelled,No Show'],
            'notes'  => ['nullable', 'string', 'max:1000'],
        ]);

        [$updated, $oldStatus] = $this->scheduler->transition(
            $appointment,
            $validated['status'],
            $validated['notes'] ?? null,
            $user
        );

        ActivityLog::create([
            'user_id'     => Auth::id(),
            'action'      => 'Appointment Status Changed',
            'module'      => 'Hospital Operations',
            'description' => "Appointment [{$updated->appointment_no}] status updated from [{$oldStatus}] to [{$updated->status}].",
            'ip_address'  => $request->ip(),
            'severity'    => ActivityLog::SEVERITY_INFO,
            'result'      => ActivityLog::RESULT_SUCCESS,
            'logged_at'   => now(),
        ]);

        return redirect()->back()->with('success', "Appointment status updated to {$updated->status}.");
    }
}
