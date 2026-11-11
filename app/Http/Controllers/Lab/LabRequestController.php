<?php

namespace App\Http\Controllers\Lab;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLabRequestRequest;
use App\Models\ActivityLog;
use App\Models\LabRequest;
use App\Models\LabRequestItem;
use App\Models\LabTest;
use App\Models\Patient;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * LabRequestController — Manages laboratory request lifecycle.
 * Doctors create requests; Medical Technologists receive and process them.
 */
class LabRequestController extends Controller
{
    public function index(Request $request): View
    {
        $query = LabRequest::with('patient', 'doctor', 'items.labTest');

        // Role-based scoping: doctors only see their own requests
        /** @var User|null $currentUser */
        $currentUser = Auth::user();
        if ($currentUser && $currentUser->hasRole('doctor')) {
            $query->where('doctor_id', $currentUser->id);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('request_no', 'like', "%{$search}%")
                  ->orWhereHas('patient', fn($p) => $p->where('first_name', 'like', "%{$search}%")
                                                       ->orWhere('last_name', 'like', "%{$search}%")
                                                       ->orWhere('patient_no', 'like', "%{$search}%"));
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($priority = $request->input('priority')) {
            $query->where('priority', $priority);
        }

        $labRequests = $query->latest()->paginate(15)->withQueryString();
        $statuses    = ['Pending', 'In Progress', 'Completed', 'Cancelled'];
        $priorities  = ['Routine', 'Urgent', 'STAT'];

        return view('lab.requests.index', compact('labRequests', 'statuses', 'priorities'));
    }

    public function create(): View
    {
        /** @var User|null $user */
        $user = Auth::user();
        abort_if(! ($user?->hasRole('doctor') || $user?->hasPermission('lis.requests.create')), 403, 'Only authorized physicians can create laboratory requests.');

        $patients  = Patient::orderBy('last_name')->get(['id', 'patient_no', 'first_name', 'last_name']);
        $labTests  = LabTest::with('category')->where('is_active', true)->orderBy('name')->get();

        return view('lab.requests.create', compact('patients', 'labTests'));
    }

    public function store(StoreLabRequestRequest $request): RedirectResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        abort_if(! ($user?->hasRole('doctor') || $user?->hasPermission('lis.requests.create')), 403, 'Only authorized physicians can create laboratory requests.');

        $count = LabRequest::count() + 1;

        DB::transaction(function () use ($request, $count) {
            $labRequest = LabRequest::create([
                'request_no'     => 'LR-' . date('Y') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT),
                'patient_id'     => $request->patient_id,
                'doctor_id'      => Auth::id(),
                'priority'       => $request->priority,
                'specimen_type'  => $request->specimen_type,
                'clinical_notes' => $request->clinical_notes,
                'status'         => 'Pending',
                'requested_at'   => now(),
            ]);

            foreach ($request->tests as $testId) {
                LabRequestItem::create([
                    'lab_request_id' => $labRequest->id,
                    'lab_test_id'    => $testId,
                    'status'         => 'Pending',
                ]);
            }

            $doctorName = $request->user()?->name ?? 'Doctor';
            NotificationService::notifyRole(
                'med-tech',
                'lab_request',
                'New Laboratory Request',
                "New request {$labRequest->request_no} submitted by Dr. {$doctorName}",
                'lis',
                route('lab.requests.show', $labRequest),
                $request->priority === 'STAT' ? 'critical' : ($request->priority === 'Urgent' ? 'urgent' : 'normal')
            );
        });

        $createdRequest = LabRequest::whereDate('created_at', now()->toDateString())
            ->where('doctor_id', Auth::id())
            ->latest()->first();

        if ($createdRequest) {
            ActivityLog::create([
                'user_id'       => Auth::id(),
                'action'        => 'Lab Request Created',
                'module'        => 'Laboratory',
                'description'   => "Lab request [{$createdRequest->request_no}] created for patient.",
                'loggable_type' => LabRequest::class,
                'loggable_id'   => $createdRequest->id,
                'ip_address'    => request()->ip(),
                'logged_at'     => now(),
            ]);
        }

        return redirect()->route('lab.requests.index')
                         ->with('success', 'Laboratory request created successfully.');
    }

    public function show(LabRequest $labRequest): View
    {
        /** @var User|null $user */
        $user = Auth::user();
        if ($user && $user->hasRole('doctor') && ! $user->hasAnyRole(['admin', 'super_admin'])) {
            abort_if($labRequest->doctor_id !== $user->id, 403, 'Unauthorized access to another physician\'s laboratory request.');
        }

        $labRequest->load('patient', 'doctor', 'items.labTest.category', 'items.result.technologist');

        return view('lab.requests.show', compact('labRequest'));
    }

    public function edit(LabRequest $labRequest): View
    {
        // Only the originating Doctor may edit their own lab request
        /** @var User|null $user */
        $user = Auth::user();
        abort_if(! $user?->hasRole('doctor'), 403, 'Only physicians can edit lab requests.');
        abort_if($labRequest->doctor_id !== Auth::id(), 403, 'You can only edit your own lab requests.');
        abort_if($labRequest->status !== 'Pending', 403, 'Only pending requests can be edited.');

        $patients = Patient::orderBy('last_name')->get(['id', 'patient_no', 'first_name', 'last_name']);
        $labTests = LabTest::with('category')->where('is_active', true)->orderBy('name')->get();

        return view('lab.requests.edit', compact('labRequest', 'patients', 'labTests'));
    }

    public function update(StoreLabRequestRequest $request, LabRequest $labRequest): RedirectResponse
    {
        // Only the originating Doctor may update their own lab request
        /** @var User|null $user */
        $user = Auth::user();
        abort_if(! $user?->hasRole('doctor'), 403, 'Only physicians can update lab requests.');
        abort_if($labRequest->doctor_id !== Auth::id(), 403, 'You can only update your own lab requests.');
        abort_if($labRequest->status !== 'Pending', 403, 'Only pending requests can be edited.');

        DB::transaction(function () use ($request, $labRequest) {
            $labRequest->update([
                'priority'       => $request->priority,
                'specimen_type'  => $request->specimen_type,
                'clinical_notes' => $request->clinical_notes,
            ]);

            // Refresh items: delete existing and re-create
            $labRequest->items()->delete();
            foreach ($request->tests as $testId) {
                LabRequestItem::create([
                    'lab_request_id' => $labRequest->id,
                    'lab_test_id'    => $testId,
                    'status'         => 'Pending',
                ]);
            }
        });

        ActivityLog::create([
            'user_id'       => Auth::id(),
            'action'        => 'Lab Request Updated',
            'module'        => 'Laboratory',
            'description'   => "Lab request [{$labRequest->request_no}] was updated.",
            'loggable_type' => LabRequest::class,
            'loggable_id'   => $labRequest->id,
            'ip_address'    => request()->ip(),
            'logged_at'     => now(),
        ]);

        return redirect()->route('lab.requests.show', $labRequest)
                         ->with('success', 'Lab request updated successfully.');
    }

    public function destroy(LabRequest $labRequest): RedirectResponse
    {
        // Only the originating Doctor may cancel their own lab request
        /** @var User|null $user */
        $user = Auth::user();
        abort_if(! $user?->hasRole('doctor'), 403, 'Only physicians can cancel lab requests.');
        abort_if($labRequest->doctor_id !== Auth::id(), 403, 'You can only cancel your own lab requests.');
        abort_if($labRequest->status !== 'Pending', 403, 'Only pending requests can be cancelled.');

        $labRequest->update(['status' => 'Cancelled']);

        ActivityLog::create([
            'user_id'       => Auth::id(),
            'action'        => 'Lab Request Cancelled',
            'module'        => 'Laboratory',
            'description'   => "Lab request [{$labRequest->request_no}] was cancelled.",
            'loggable_type' => LabRequest::class,
            'loggable_id'   => $labRequest->id,
            'ip_address'    => request()->ip(),
            'logged_at'     => now(),
        ]);

        return redirect()->route('lab.requests.index')
                         ->with('success', 'Lab request cancelled.');
    }

    /** Medical Technologist marks request as received and specimen processed. */
    public function receive(Request $request, LabRequest $labRequest): RedirectResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        abort_if(! ($user?->hasRole('med-tech') && $user?->hasPermission('lis.specimens.process')), 403, 'Only authorized laboratory personnel can process specimens.');
        abort_if($labRequest->status !== 'Pending', 400, 'Only pending laboratory requests can be marked as received.');

        $specimenNotes = $request->input('specimen_notes');

        DB::transaction(function () use ($labRequest, $specimenNotes, $user) {
            $updateData = [
                'status'      => 'In Progress',
                'received_at' => now(),
            ];

            if ($specimenNotes) {
                $existing = $labRequest->clinical_notes ? $labRequest->clinical_notes . "\n" : '';
                $updateData['clinical_notes'] = $existing . "[Specimen Received Note by {$user->name}: " . trim($specimenNotes) . "]";
            }

            $labRequest->update($updateData);

            $labRequest->items()
                ->where('status', 'Pending')
                ->update(['status' => 'In Progress']);
        });

        ActivityLog::create([
            'user_id'       => Auth::id(),
            'action'        => 'Lab Specimen Received',
            'module'        => 'Laboratory',
            'description'   => "Specimen for lab request [{$labRequest->request_no}] was received and marked In Progress.",
            'loggable_type' => LabRequest::class,
            'loggable_id'   => $labRequest->id,
            'ip_address'    => request()->ip(),
            'logged_at'     => now(),
        ]);

        return back()->with('success', "Request {$labRequest->request_no} marked as received.");
    }

    /** Medical Technologist records specimen rejection and its reason. */
    public function reject(Request $request, LabRequest $labRequest): RedirectResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        abort_if(! ($user?->hasRole('med-tech') && $user?->hasPermission('lis.specimens.process')), 403, 'Only authorized laboratory personnel can reject specimens.');
        abort_if(in_array($labRequest->status, ['Completed', 'Cancelled']), 400, 'Completed or cancelled laboratory requests cannot be rejected.');

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:500'],
        ]);

        $reason = trim($validated['rejection_reason']);

        DB::transaction(function () use ($labRequest, $reason, $user) {
            $existing = $labRequest->clinical_notes ? $labRequest->clinical_notes . "\n" : '';
            $labRequest->update([
                'status'         => 'Cancelled',
                'clinical_notes' => $existing . "[Specimen Rejected by {$user->name}: {$reason}]",
            ]);

            $labRequest->items()->update(['status' => 'Cancelled']);
        });

        ActivityLog::create([
            'user_id'       => Auth::id(),
            'action'        => 'Lab Specimen Rejected',
            'module'        => 'Laboratory',
            'severity'      => ActivityLog::SEVERITY_WARNING,
            'result'        => ActivityLog::RESULT_SUCCESS,
            'description'   => "Specimen for lab request [{$labRequest->request_no}] was rejected. Reason: {$reason}",
            'loggable_type' => LabRequest::class,
            'loggable_id'   => $labRequest->id,
            'ip_address'    => request()->ip(),
            'logged_at'     => now(),
        ]);

        return back()->with('success', "Specimen for request {$labRequest->request_no} has been rejected.");
    }

    /** Print-friendly view for lab request report. */
    public function print(LabRequest $labRequest): View
    {
        $labRequest->load('patient', 'doctor', 'items.labTest.category', 'items.result');

        return view('lab.requests.print', compact('labRequest'));
    }
}
