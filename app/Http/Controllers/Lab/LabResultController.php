<?php

namespace App\Http\Controllers\Lab;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLabResultRequest;
use App\Models\ActivityLog;
use App\Models\LabRequest;
use App\Models\LabRequestItem;
use App\Models\LabResult;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * LabResultController — Encodes, validates, and releases lab results.
 */
class LabResultController extends Controller
{
    public function index(Request $request): View
    {
        $query = LabResult::with('requestItem.labTest', 'requestItem.labRequest.patient', 'technologist');

        /** @var User|null $currentUser */
        $currentUser = Auth::user();
        if ($currentUser && $currentUser->hasRole('doctor') && ! $currentUser->hasAnyRole(['admin', 'super_admin'])) {
            // Doctors can only view Released results for their own patients
            $query->where('status', 'Released')
                  ->whereHas('requestItem.labRequest', function ($q) use ($currentUser) {
                      $q->where('doctor_id', $currentUser->id);
                  });
        } elseif ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $labResults = $query->latest()->paginate(15)->withQueryString();
        $statuses   = ['Pending', 'Encoded', 'Validated', 'Released'];

        return view('lab.results.index', compact('labResults', 'statuses'));
    }

    public function create(): View
    {
        /** @var User|null $user */
        $user = Auth::user();
        abort_if(! ($user?->hasRole('med-tech') && $user?->hasPermission('lis.results.enter')), 403, 'Only authorized laboratory personnel can encode laboratory results.');

        // List pending items that don't have a result yet
        $pendingItems = LabRequestItem::whereDoesntHave('result')
                        ->with('labRequest.patient', 'labTest')
                        ->get();

        return view('lab.results.create', compact('pendingItems'));
    }

    public function store(StoreLabResultRequest $request): RedirectResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        abort_if(! ($user?->hasRole('med-tech') && $user?->hasPermission('lis.results.enter')), 403, 'Only authorized laboratory personnel can encode laboratory results.');

        $labResult = LabResult::create([
            'lab_request_item_id' => $request->lab_request_item_id,
            'technologist_id'     => Auth::id(),
            'result_value'        => $request->result_value,
            'remarks'             => $request->remarks,
            'status'              => 'Encoded',
        ]);

        // Update parent item status
        $item = LabRequestItem::find($request->lab_request_item_id);
        $item?->update(['status' => 'Completed']);

        ActivityLog::create([
            'user_id'       => Auth::id(),
            'action'        => 'Lab Result Encoded',
            'module'        => 'Laboratory',
            'severity'      => ActivityLog::SEVERITY_INFO,
            'result'        => ActivityLog::RESULT_SUCCESS,
            'description'   => "Lab result #{$labResult->id} encoded for request item #{$request->lab_request_item_id}.",
            'loggable_type' => LabResult::class,
            'loggable_id'   => $labResult->id,
            'ip_address'    => request()->ip(),
            'logged_at'     => now(),
        ]);

        return redirect()->route('lab.results.index')
                         ->with('success', 'Lab result encoded successfully.');
    }

    public function show(LabResult $labResult): View
    {
        /** @var User|null $currentUser */
        $currentUser = Auth::user();
        if ($currentUser && $currentUser->hasRole('doctor') && ! $currentUser->hasAnyRole(['admin', 'super_admin'])) {
            $labRequest = $labResult->requestItem?->labRequest;
            abort_if(! $labRequest || $labRequest->doctor_id !== $currentUser->id, 403, 'Unauthorized access to another physician\'s patient results.');
            abort_if($labResult->status !== 'Released', 403, 'Laboratory result has not been released yet.');
        }

        $labResult->load('requestItem.labTest.category', 'requestItem.labRequest.patient', 'technologist', 'validatedBy', 'releasedBy');

        return view('lab.results.show', compact('labResult'));
    }

    public function edit(LabResult $labResult): View
    {
        /** @var User|null $user */
        $user = Auth::user();
        abort_if(! ($user?->hasRole('med-tech') && $user?->hasPermission('lis.results.enter')), 403, 'Only authorized laboratory personnel can edit laboratory results.');
        abort_if($labResult->status === 'Released', 403, 'Released results cannot be edited.');

        return view('lab.results.edit', compact('labResult'));
    }

    public function update(StoreLabResultRequest $request, LabResult $labResult): RedirectResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        abort_if(! ($user?->hasRole('med-tech') && $user?->hasPermission('lis.results.enter')), 403, 'Only authorized laboratory personnel can edit laboratory results.');
        abort_if($labResult->status === 'Released', 403, 'Released results cannot be edited.');

        $labResult->update([
            'result_value' => $request->result_value,
            'remarks'      => $request->remarks,
            'status'       => 'Encoded',
        ]);

        ActivityLog::create([
            'user_id'       => Auth::id(),
            'action'        => 'Lab Result Updated',
            'module'        => 'Laboratory',
            'severity'      => ActivityLog::SEVERITY_INFO,
            'result'        => ActivityLog::RESULT_SUCCESS,
            'description'   => "Lab result #{$labResult->id} updated.",
            'loggable_type' => LabResult::class,
            'loggable_id'   => $labResult->id,
            'ip_address'    => request()->ip(),
            'logged_at'     => now(),
        ]);

        return redirect()->route('lab.results.show', $labResult)
                         ->with('success', 'Result updated successfully.');
    }

    public function destroy(LabResult $labResult): RedirectResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        abort_if(! ($user?->hasRole('med-tech') && $user?->hasPermission('lis.results.enter')), 403, 'Only authorized laboratory personnel can delete laboratory results.');
        abort_if($labResult->status !== 'Encoded', 403, 'Only encoded results can be deleted.');

        ActivityLog::create([
            'user_id'       => Auth::id(),
            'action'        => 'Lab Result Deleted',
            'module'        => 'Laboratory',
            'severity'      => ActivityLog::SEVERITY_WARNING,
            'result'        => ActivityLog::RESULT_SUCCESS,
            'description'   => "Lab result #{$labResult->id} deleted.",
            'loggable_type' => LabResult::class,
            'loggable_id'   => $labResult->id,
            'ip_address'    => request()->ip(),
            'logged_at'     => now(),
        ]);

        $labResult->delete();

        return redirect()->route('lab.results.index')
                         ->with('success', 'Result deleted.');
    }

    /** Validate (approve) a lab result before release. */
    public function validate(LabResult $labResult): RedirectResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        abort_if(! ($user?->hasRole('med-tech') && $user?->hasPermission('lis.results.verify')), 403, 'Only authorized personnel with verification authority can validate laboratory results.');
        abort_if($labResult->status !== 'Encoded', 403, 'Only encoded results can be validated.');

        $labResult->update([
            'status'       => 'Validated',
            'validated_by' => Auth::id(),
            'validated_at' => now(),
        ]);

        ActivityLog::create([
            'user_id'       => Auth::id(),
            'action'        => 'Lab Result Validated',
            'module'        => 'Laboratory',
            'severity'      => ActivityLog::SEVERITY_INFO,
            'result'        => ActivityLog::RESULT_SUCCESS,
            'description'   => "Lab result #{$labResult->id} validated and approved for release.",
            'loggable_type' => LabResult::class,
            'loggable_id'   => $labResult->id,
            'ip_address'    => request()->ip(),
            'logged_at'     => now(),
        ]);

        return back()->with('success', 'Result validated successfully.');
    }

    /** Release the validated result to the ordering doctor. */
    public function release(LabResult $labResult): RedirectResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        abort_if(! ($user?->hasRole('med-tech') && $user?->hasPermission('lis.results.release')), 403, 'Only authorized personnel with release authority can release laboratory results.');
        abort_if($labResult->status !== 'Validated', 403, 'Only validated results can be released.');

        $labResult->update([
            'status'       => 'Released',
            'released_by'  => Auth::id(),
            'released_at'  => now(),
        ]);

        // Check if all items are completed → update request status
        $labRequest = $labResult->requestItem->labRequest;
        $allReleased = $labRequest->items()
                       ->whereDoesntHave('result', fn($q) => $q->where('status', 'Released'))
                       ->doesntExist();

        if ($allReleased) {
            $labRequest->update(['status' => 'Completed', 'completed_at' => now()]);
        }

        // Notify ordering doctor
        if ($labRequest->doctor_id) {
            \App\Services\NotificationService::notifyUser(
                $labRequest->doctor_id,
                'lab_result',
                'Laboratory Result Released',
                "Lab result for Request {$labRequest->request_no} is now available.",
                'lis',
                route('lab.results.show', $labResult),
                'urgent'
            );
        }

        ActivityLog::create([
            'user_id'       => Auth::id(),
            'action'        => 'Lab Result Released',
            'module'        => 'Laboratory',
            'severity'      => ActivityLog::SEVERITY_INFO,
            'result'        => ActivityLog::RESULT_SUCCESS,
            'description'   => "Lab result #{$labResult->id} released to ordering physician.",
            'loggable_type' => LabResult::class,
            'loggable_id'   => $labResult->id,
            'ip_address'    => request()->ip(),
            'logged_at'     => now(),
        ]);

        return back()->with('success', 'Result released successfully.');
    }

    /** Print-friendly view for individual lab result. */
    public function print(LabResult $labResult): View
    {
        /** @var User|null $currentUser */
        $currentUser = Auth::user();
        if ($currentUser && $currentUser->hasRole('doctor') && ! $currentUser->hasAnyRole(['admin', 'super_admin'])) {
            $labRequest = $labResult->requestItem?->labRequest;
            abort_if(! $labRequest || $labRequest->doctor_id !== $currentUser->id, 403, 'Unauthorized access.');
            abort_if($labResult->status !== 'Released', 403, 'Laboratory result has not been released yet.');
        }

        $labRequest = $labResult->requestItem->labRequest;
        $labRequest->load('patient', 'doctor', 'items.labTest.category', 'items.result.technologist');

        return view('lab.requests.print', compact('labRequest'));
    }
}
