<?php

namespace App\Http\Controllers\Surgery;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSurgeryRequestRequest;
use App\Models\ActivityLog;
use App\Models\Patient;
use App\Models\SurgeryRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * SurgeryRequestController — Doctors submit surgery requests; OR Coordinators review and manage them.
 */
class SurgeryRequestController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', SurgeryRequest::class);

        /** @var User $user */
        $user = Auth::user();

        $query = SurgeryRequest::with('patient', 'doctor', 'schedule.operatingRoom')
            ->visibleTo($user);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('request_no', 'like', "%{$search}%")
                    ->orWhere('procedure_name', 'like', "%{$search}%")
                    ->orWhereHas('patient', fn ($p) => $p->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%"));
            });
        }

        if ($s = $request->input('status')) {
            $query->where('status', $s);
        }
        if ($u = $request->input('urgency')) {
            $query->where('urgency', $u);
        }

        $surgeryRequests = $query->latest()->paginate(15)->withQueryString();
        $statuses        = ['Pending', 'Scheduled', 'In Progress', 'Completed', 'Cancelled'];
        $urgencies       = ['Elective', 'Urgent', 'Emergency'];

        return view('surgery.requests.index', compact('surgeryRequests', 'statuses', 'urgencies'));
    }

    public function create(): View
    {
        $this->authorize('create', SurgeryRequest::class);

        /** @var User $user */
        $user = Auth::user();

        $patients = Patient::authorizedForDoctor($user)
            ->orderBy('last_name')
            ->get(['id', 'patient_no', 'first_name', 'last_name']);

        return view('surgery.requests.create', compact('patients'));
    }

    public function store(StoreSurgeryRequestRequest $request): RedirectResponse
    {
        $this->authorize('create', SurgeryRequest::class);

        $validated = $request->validated();
        $patient   = Patient::findOrFail($validated['patient_id']);

        // Verify the doctor is authorized to create a request for this specific patient
        $this->authorize('create', [SurgeryRequest::class, $patient]);

        $nextSequence = ((int) SurgeryRequest::max('id')) + 1;
        $requestNo    = 'SR-' . date('Y') . '-' . str_pad((string) $nextSequence, 4, '0', STR_PAD_LEFT);
        while (SurgeryRequest::where('request_no', $requestNo)->exists()) {
            $nextSequence++;
            $requestNo = 'SR-' . date('Y') . '-' . str_pad((string) $nextSequence, 4, '0', STR_PAD_LEFT);
        }

        $created = SurgeryRequest::create(array_merge($validated, [
            'request_no'   => $requestNo,
            'doctor_id'    => Auth::id(),
            'status'       => 'Pending',
            'requested_at' => now(),
        ]));

        ActivityLog::create([
            'user_id'       => Auth::id(),
            'action'        => 'Surgery Request Created',
            'module'        => 'Surgery',
            'severity'      => ActivityLog::SEVERITY_INFO,
            'result'        => ActivityLog::RESULT_SUCCESS,
            'description'   => "Surgery request [{$created->request_no}] for {$created->procedure_name} was submitted.",
            'loggable_type' => SurgeryRequest::class,
            'loggable_id'   => $created->id,
            'ip_address'    => request()->ip(),
            'logged_at'     => now(),
        ]);

        return redirect()->route('surgery.requests.index')
            ->with('success', 'Surgery request submitted successfully.');
    }

    public function show(SurgeryRequest $surgeryRequest): View
    {
        $this->authorize('view', $surgeryRequest);

        $surgeryRequest->load(
            'patient',
            'doctor',
            'schedule.operatingRoom',
            'schedule.surgicalTeam.surgeon',
            'schedule.surgicalTeam.members.user',
            'schedule.scheduledBy',
            'schedule.readinessUpdatedBy'
        );

        return view('surgery.requests.show', compact('surgeryRequest'));
    }

    public function edit(SurgeryRequest $surgeryRequest): View
    {
        abort_if($surgeryRequest->status !== 'Pending', 422, 'Only pending surgery requests can be edited.');
        $this->authorize('update', $surgeryRequest);

        /** @var User $user */
        $user = Auth::user();

        $patients = Patient::authorizedForDoctor($user)
            ->orderBy('last_name')
            ->get(['id', 'patient_no', 'first_name', 'last_name']);

        return view('surgery.requests.edit', compact('surgeryRequest', 'patients'));
    }

    public function update(StoreSurgeryRequestRequest $request, SurgeryRequest $surgeryRequest): RedirectResponse
    {
        abort_if($surgeryRequest->status !== 'Pending', 422, 'Only pending surgery requests can be edited.');
        $this->authorize('update', $surgeryRequest);

        $validated = $request->validated();
        $patient   = Patient::findOrFail($validated['patient_id']);
        $this->authorize('create', [SurgeryRequest::class, $patient]);

        $surgeryRequest->update($validated);

        ActivityLog::create([
            'user_id'       => Auth::id(),
            'action'        => 'Surgery Request Updated',
            'module'        => 'Surgery',
            'severity'      => ActivityLog::SEVERITY_INFO,
            'result'        => ActivityLog::RESULT_SUCCESS,
            'description'   => "Surgery request [{$surgeryRequest->request_no}] was updated.",
            'loggable_type' => SurgeryRequest::class,
            'loggable_id'   => $surgeryRequest->id,
            'ip_address'    => request()->ip(),
            'logged_at'     => now(),
        ]);

        return redirect()->route('surgery.requests.show', $surgeryRequest)
            ->with('success', 'Surgery request updated.');
    }

    public function destroy(SurgeryRequest $surgeryRequest): RedirectResponse
    {
        abort_if($surgeryRequest->status !== 'Pending', 422, 'Only pending surgery requests can be cancelled.');
        $this->authorize('delete', $surgeryRequest);

        $surgeryRequest->update(['status' => 'Cancelled']);

        ActivityLog::create([
            'user_id'       => Auth::id(),
            'action'        => 'Surgery Request Cancelled',
            'module'        => 'Surgery',
            'severity'      => ActivityLog::SEVERITY_WARNING,
            'result'        => ActivityLog::RESULT_SUCCESS,
            'description'   => "Surgery request [{$surgeryRequest->request_no}] was cancelled by requesting physician.",
            'loggable_type' => SurgeryRequest::class,
            'loggable_id'   => $surgeryRequest->id,
            'ip_address'    => request()->ip(),
            'logged_at'     => now(),
        ]);

        return redirect()->route('surgery.requests.index')
            ->with('success', 'Surgery request cancelled.');
    }

    public function cancel(SurgeryRequest $surgeryRequest): RedirectResponse
    {
        if (in_array($surgeryRequest->status, ['In Progress', 'Completed', 'Cancelled'], true)) {
            abort(422, "Cannot cancel a surgery request with status [{$surgeryRequest->status}].");
        }

        $this->authorize('cancel', $surgeryRequest);

        $surgeryRequest->update(['status' => 'Cancelled']);

        if ($surgeryRequest->schedule && ! in_array($surgeryRequest->schedule->status, ['Completed', 'Cancelled'], true)) {
            $surgeryRequest->schedule->update(['status' => 'Cancelled']);
        }

        ActivityLog::create([
            'user_id'       => Auth::id(),
            'action'        => 'Surgery Request Cancelled',
            'module'        => 'Surgery',
            'severity'      => ActivityLog::SEVERITY_WARNING,
            'result'        => ActivityLog::RESULT_SUCCESS,
            'description'   => "Surgery request [{$surgeryRequest->request_no}] was cancelled.",
            'loggable_type' => SurgeryRequest::class,
            'loggable_id'   => $surgeryRequest->id,
            'ip_address'    => request()->ip(),
            'logged_at'     => now(),
        ]);

        return back()->with('success', 'Surgery request cancelled.');
    }
}
