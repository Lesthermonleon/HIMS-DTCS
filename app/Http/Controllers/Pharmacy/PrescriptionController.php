<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePrescriptionRequest;
use App\Models\ActivityLog;
use App\Models\DispensingRecord;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * PrescriptionController — manages doctor prescriptions and pharmacist verification/rejection workflow.
 */
class PrescriptionController extends Controller
{
    public function index(Request $request): View
    {
        $baseQuery = Prescription::query();

        /** @var User|null $user */
        $user = Auth::user();
        if ($user?->hasRole('doctor') && ! $user->hasAnyRole(['admin', 'super_admin', 'pharmacist'])) {
            $baseQuery->where('doctor_id', Auth::id());
        }

        // Tab & workflow counts (respecting role scope)
        $counts = [
            'all'                  => (clone $baseQuery)->count(),
            'inpatient'            => (clone $baseQuery)->whereHas('patient', fn($p) => $p->where('patient_type', 'Inpatient'))->count(),
            'pending_verification' => (clone $baseQuery)->where('status', 'Pending')->count(),
            'verified'             => (clone $baseQuery)->where('status', 'Verified')->count(),
            'pending_dispensing'   => (clone $baseQuery)->whereIn('status', ['Verified', 'Partially Dispensed'])->count(),
            'partially_dispensed'  => (clone $baseQuery)->where('status', 'Partially Dispensed')->count(),
            'completed'            => (clone $baseQuery)->where('status', 'Dispensed')->count(),
            'rejected_returned'    => (clone $baseQuery)->whereIn('status', ['Rejected', 'Returned', 'Cancelled'])->count(),
        ];

        $query = (clone $baseQuery)->with(['patient', 'doctor', 'items.dispensingRecords', 'verifiedBy', 'rejectedBy']);

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('prescription_no', 'like', "%{$search}%")
                  ->orWhere('diagnosis', 'like', "%{$search}%")
                  ->orWhereHas('patient', fn($p) => $p->where('first_name', 'like', "%{$search}%")
                                                       ->orWhere('last_name', 'like', "%{$search}%")
                                                       ->orWhere('patient_no', 'like', "%{$search}%")
                                                       ->orWhere('ward', 'like', "%{$search}%"))
                  ->orWhereHas('items', fn($i) => $i->where('medication_name', 'like', "%{$search}%"));
            });
        }

        if ($patientType = $request->input('patient_type')) {
            if (in_array($patientType, ['Inpatient', 'Outpatient'], true)) {
                $query->whereHas('patient', fn($p) => $p->where('patient_type', $patientType));
            }
        }

        if ($queue = $request->input('queue')) {
            match ($queue) {
                'inpatient'            => $query->whereHas('patient', fn($p) => $p->where('patient_type', 'Inpatient')),
                'pending_verification' => $query->where('status', 'Pending'),
                'verified'             => $query->where('status', 'Verified'),
                'pending_dispensing'   => $query->whereIn('status', ['Verified', 'Partially Dispensed']),
                'partially_dispensed'  => $query->where('status', 'Partially Dispensed'),
                'completed'            => $query->where('status', 'Dispensed'),
                'rejected_returned'    => $query->whereIn('status', ['Rejected', 'Returned', 'Cancelled']),
                default                => null,
            };
        }

        if ($s = $request->input('status')) {
            $query->where('status', $s);
        }

        $prescriptions = $query->latest()->paginate(15)->withQueryString();
        $statuses      = ['Pending', 'Verified', 'Partially Dispensed', 'Dispensed', 'Rejected', 'Returned', 'Cancelled'];

        // Recent dispensing history for quick reference on the Prescription Management workspace
        $recentDispensings = DispensingRecord::with(['prescriptionItem.prescription.patient', 'pharmacist'])
            ->when(
                $user?->hasRole('doctor') && ! $user->hasAnyRole(['admin', 'super_admin', 'pharmacist']),
                fn($q) => $q->whereHas('prescriptionItem.prescription', fn($pq) => $pq->where('doctor_id', Auth::id()))
            )
            ->latest('dispensed_at')
            ->take(8)
            ->get();

        return view('pharmacy.prescriptions.index', compact('prescriptions', 'statuses', 'counts', 'recentDispensings'));
    }

    public function create(): View
    {
        /** @var User|null $user */
        $user = Auth::user();
        abort_if(! $user?->hasRole('doctor'), 403, 'Only physicians can create prescriptions.');
        $patients = Patient::orderBy('last_name')->get(['id', 'patient_no', 'first_name', 'last_name', 'patient_type', 'ward', 'bed_number']);

        return view('pharmacy.prescriptions.create', compact('patients'));
    }

    public function store(StorePrescriptionRequest $request): RedirectResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        abort_if(! $user?->hasRole('doctor'), 403, 'Only physicians can create prescriptions.');
        $count = Prescription::count() + 1;

        DB::transaction(function () use ($request, $count) {
            $prescription = Prescription::create([
                'prescription_no' => 'RX-' . date('Y') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT),
                'patient_id'      => $request->patient_id,
                'doctor_id'       => Auth::id(),
                'diagnosis'       => $request->diagnosis,
                'notes'           => $request->notes,
                'status'          => 'Pending',
                'prescribed_at'   => now(),
            ]);

            foreach ($request->items as $item) {
                PrescriptionItem::create(array_merge($item, ['prescription_id' => $prescription->id]));
            }
        });

        $latest = Prescription::where('doctor_id', Auth::id())->latest()->first();
        if ($latest) {
            ActivityLog::create([
                'user_id'       => Auth::id(),
                'action'        => 'Prescription Created',
                'module'        => 'Pharmacy',
                'description'   => "Prescription [{$latest->prescription_no}] was created.",
                'loggable_type' => Prescription::class,
                'loggable_id'   => $latest->id,
                'ip_address'    => request()->ip(),
                'logged_at'     => now(),
            ]);
        }

        return redirect()->route('pharmacy.prescriptions.index')
                         ->with('success', 'Prescription created successfully.');
    }

    public function show(Prescription $prescription): View
    {
        $this->authorizePrescriptionView($prescription);

        $prescription->load([
            'patient',
            'doctor',
            'items.dispensingRecords.pharmacist',
            'verifiedBy',
            'rejectedBy',
            'posTransactions.pharmacist',
        ]);

        return view('pharmacy.prescriptions.show', compact('prescription'));
    }

    public function edit(Prescription $prescription): View
    {
        /** @var User|null $user */
        $user = Auth::user();
        abort_if(! $user?->hasRole('doctor'), 403, 'Only physicians can edit prescriptions.');
        abort_if((int) $prescription->doctor_id !== (int) Auth::id(), 403, 'You can only edit your own prescriptions.');
        abort_if(! in_array($prescription->status, ['Pending', 'Returned'], true), 403, 'Only pending or returned prescriptions can be edited.');
        $patients = Patient::orderBy('last_name')->get(['id', 'patient_no', 'first_name', 'last_name']);

        return view('pharmacy.prescriptions.edit', compact('prescription', 'patients'));
    }

    public function update(StorePrescriptionRequest $request, Prescription $prescription): RedirectResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        abort_if(! $user?->hasRole('doctor'), 403, 'Only physicians can edit prescriptions.');
        abort_if((int) $prescription->doctor_id !== (int) Auth::id(), 403, 'You can only edit your own prescriptions.');
        abort_if(! in_array($prescription->status, ['Pending', 'Returned'], true), 403, 'Only pending or returned prescriptions can be edited.');

        DB::transaction(function () use ($request, $prescription) {
            $prescription->update([
                'diagnosis' => $request->diagnosis,
                'notes'     => $request->notes,
                'status'    => 'Pending',
            ]);

            $prescription->items()->delete();
            foreach ($request->items as $item) {
                PrescriptionItem::create(array_merge($item, ['prescription_id' => $prescription->id]));
            }
        });

        ActivityLog::create([
            'user_id'       => Auth::id(),
            'action'        => 'Prescription Updated',
            'module'        => 'Pharmacy',
            'description'   => "Prescription [{$prescription->prescription_no}] was updated.",
            'loggable_type' => Prescription::class,
            'loggable_id'   => $prescription->id,
            'ip_address'    => request()->ip(),
            'logged_at'     => now(),
        ]);

        return redirect()->route('pharmacy.prescriptions.show', $prescription)
                         ->with('success', 'Prescription updated.');
    }

    public function destroy(Prescription $prescription): RedirectResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        abort_if(! $user?->hasRole('doctor'), 403, 'Only physicians can cancel prescriptions.');
        abort_if((int) $prescription->doctor_id !== (int) Auth::id(), 403, 'You can only cancel your own prescriptions.');
        abort_if($prescription->status !== 'Pending', 403, 'Only pending prescriptions can be cancelled.');
        $prescription->update(['status' => 'Cancelled']);

        ActivityLog::create([
            'user_id'       => Auth::id(),
            'action'        => 'Prescription Cancelled',
            'module'        => 'Pharmacy',
            'description'   => "Prescription [{$prescription->prescription_no}] was cancelled.",
            'loggable_type' => Prescription::class,
            'loggable_id'   => $prescription->id,
            'ip_address'    => request()->ip(),
            'logged_at'     => now(),
        ]);

        return redirect()->route('pharmacy.prescriptions.index')
                         ->with('success', 'Prescription cancelled.');
    }

    /** Pharmacist verifies the prescription before dispensing. */
    public function verify(Request $request, Prescription $prescription): RedirectResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        abort_if(! $user?->hasRole('pharmacist'), 403, 'Only pharmacists can verify prescriptions.');
        abort_if(! in_array($prescription->status, ['Pending', 'Returned'], true), 403, 'Only pending or returned prescriptions can be verified.');

        $validated = $request->validate([
            'verification_notes' => 'nullable|string|max:1000',
        ]);

        $prescription->update([
            'status'             => 'Verified',
            'verified_by'        => Auth::id(),
            'verified_at'        => now(),
            'verification_notes' => $validated['verification_notes'] ?? $prescription->verification_notes,
        ]);

        ActivityLog::create([
            'user_id'       => Auth::id(),
            'action'        => 'Prescription Verified',
            'module'        => 'Pharmacy',
            'description'   => "Prescription [{$prescription->prescription_no}] was verified by pharmacist.",
            'loggable_type' => Prescription::class,
            'loggable_id'   => $prescription->id,
            'ip_address'    => request()->ip(),
            'logged_at'     => now(),
        ]);

        return back()->with('success', 'Prescription verified. Ready for dispensing.');
    }

    /** Pharmacist rejects or returns a prescription with a required clinical reason. */
    public function reject(Request $request, Prescription $prescription): RedirectResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        abort_if(! $user?->hasRole('pharmacist'), 403, 'Only pharmacists can reject or return prescriptions.');
        abort_if(! in_array($prescription->status, ['Pending', 'Verified'], true), 403, 'Only pending or un-dispensed verified prescriptions can be rejected or returned.');

        $hasDispensedItems = $prescription->items()->where('status', 'Dispensed')->exists()
            || DispensingRecord::whereIn('prescription_item_id', $prescription->items()->pluck('id'))->exists();
        abort_if($hasDispensedItems, 403, 'Prescriptions with dispensed medications cannot be rejected or returned.');

        $validated = $request->validate([
            'rejection_reason' => 'required|string|min:3|max:1000',
            'action_type'      => 'nullable|in:Rejected,Returned',
            'action'           => 'nullable|in:Rejected,Returned',
        ], [
            'rejection_reason.required' => 'Please provide a clinical reason for rejecting or returning this prescription.',
        ]);

        $targetStatus = $validated['action_type'] ?? $validated['action'] ?? 'Rejected';

        $prescription->update([
            'status'           => $targetStatus,
            'rejection_reason' => $validated['rejection_reason'],
            'rejected_by'      => Auth::id(),
            'rejected_at'      => now(),
        ]);

        ActivityLog::create([
            'user_id'       => Auth::id(),
            'action'        => "Prescription {$targetStatus}",
            'module'        => 'Pharmacy',
            'description'   => "Prescription [{$prescription->prescription_no}] was marked as {$targetStatus} by pharmacist. Reason: {$validated['rejection_reason']}",
            'loggable_type' => Prescription::class,
            'loggable_id'   => $prescription->id,
            'ip_address'    => request()->ip(),
            'logged_at'     => now(),
        ]);

        return back()->with('success', "Prescription marked as {$targetStatus}.");
    }

    /** Print-friendly view for prescription (Rx). */
    public function print(Prescription $prescription): View
    {
        $this->authorizePrescriptionView($prescription);

        $prescription->load('patient', 'doctor', 'items');

        return view('pharmacy.prescriptions.print', compact('prescription'));
    }

    protected function authorizePrescriptionView(Prescription $prescription): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user) {
            abort(403);
        }

        if ($user->hasRole('doctor') && ! $user->hasAnyRole(['admin', 'super_admin', 'pharmacist'])) {
            $isPrescribingDoctor = (int) $prescription->doctor_id === (int) $user->id;
            $isAuthorizedPatient = $prescription->patient && $prescription->patient->isAuthorizedForDoctor($user);

            abort_if(! $isPrescribingDoctor && ! $isAuthorizedPatient, 403, 'You are not authorized to view this prescription.');
        }
    }
}
