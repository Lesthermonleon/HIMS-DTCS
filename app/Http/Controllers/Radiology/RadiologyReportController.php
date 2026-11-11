<?php

namespace App\Http\Controllers\Radiology;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRadiologyReportRequest;
use App\Models\ActivityLog;
use App\Models\RadiologyReport;
use App\Models\RadiologyRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * RadiologyReportController — radiologist creates, approves, and releases diagnostic reports.
 */
class RadiologyReportController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', RadiologyReport::class);

        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        $query = RadiologyReport::with('radiologyRequest.patient', 'radiologyRequest.doctor', 'radiologist');

        if ($user && $user->hasRole('doctor') && ! $user->hasAnyRole(['admin', 'super_admin'])) {
            // Doctors can only view Approved or Released reports for their own patients
            $query->whereIn('status', ['Approved', 'Released'])
                  ->whereHas('radiologyRequest', fn($q) => $q->where('doctor_id', $user->id));
        } elseif ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $reports = $query->latest()->paginate(15)->withQueryString();

        return view('radiology.reports.index', compact('reports'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', RadiologyReport::class);

        // Show requests that are Completed or In Progress (with uploaded images) and have no report yet
        $pendingRequests = RadiologyRequest::whereIn('status', ['Completed', 'In Progress', 'Scheduled'])
                           ->whereDoesntHave('report')
                           ->with('patient', 'images')
                           ->get();

        $radiologists = User::whereHas('roles', fn($q) => $q->where('slug', 'radiologist'))
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $selectedRequestId = $request->input('radiology_request_id');

        return view('radiology.reports.create', compact('pendingRequests', 'selectedRequestId', 'radiologists'));
    }

    public function store(StoreRadiologyReportRequest $request): RedirectResponse
    {
        $this->authorize('create', RadiologyReport::class);

        /** @var User $user */
        $user = Auth::user();

        $data = $request->validated();
        if ($user->hasRole('radiologist')) {
            $data['radiologist_id'] = $user->id;
        } elseif ($request->filled('radiologist_id')) {
            $data['radiologist_id'] = $request->input('radiologist_id');
        } else {
            $defaultRad = User::whereHas('roles', fn($q) => $q->where('slug', 'radiologist'))
                ->where('is_active', true)
                ->first();
            $data['radiologist_id'] = $defaultRad?->id ?? $user->id;
        }

        $data['status'] = 'Draft';

        $report = RadiologyReport::create($data);

        // Ensure radiology request is marked as completed if it wasn't already
        $radReq = RadiologyRequest::find($request->radiology_request_id);
        if ($radReq && $radReq->status !== 'Completed') {
            $radReq->update(['status' => 'Completed', 'completed_at' => now()]);
        }

        ActivityLog::create([
            'user_id'       => Auth::id(),
            'action'        => 'Radiology Report Prepared',
            'module'        => 'Radiology',
            'description'   => "Draft diagnostic radiology report prepared for request [{$radReq?->request_no}] by {$user->name}.",
            'loggable_type' => RadiologyReport::class,
            'loggable_id'   => $report->id,
            'ip_address'    => request()->ip(),
            'logged_at'     => now(),
        ]);

        return redirect()->route('radiology.reports.show', $report)
                         ->with('success', 'Diagnostic radiology report created successfully as Draft.');
    }

    public function show(RadiologyReport $radiologyReport): View
    {
        $this->authorize('view', $radiologyReport);

        $radiologyReport->load('radiologyRequest.patient', 'radiologyRequest.doctor', 'radiologyRequest.images', 'radiologist', 'approvedBy', 'releasedBy');

        return view('radiology.reports.show', compact('radiologyReport'));
    }

    public function edit(RadiologyReport $radiologyReport): View
    {
        $this->authorize('update', $radiologyReport);

        return view('radiology.reports.edit', compact('radiologyReport'));
    }

    public function update(StoreRadiologyReportRequest $request, RadiologyReport $radiologyReport): RedirectResponse
    {
        $this->authorize('update', $radiologyReport);

        $radiologyReport->update($request->validated());

        ActivityLog::create([
            'user_id'       => Auth::id(),
            'action'        => 'Radiology Report Updated',
            'module'        => 'Radiology',
            'description'   => "Radiology report #{$radiologyReport->id} was updated.",
            'loggable_type' => RadiologyReport::class,
            'loggable_id'   => $radiologyReport->id,
            'ip_address'    => request()->ip(),
            'logged_at'     => now(),
        ]);

        return redirect()->route('radiology.reports.show', $radiologyReport)
                         ->with('success', 'Radiology report updated successfully.');
    }

    public function destroy(RadiologyReport $radiologyReport): RedirectResponse
    {
        $this->authorize('delete', $radiologyReport);

        $radiologyReport->delete();

        return redirect()->route('radiology.reports.index')
                         ->with('success', 'Draft report deleted.');
    }

    public function approve(RadiologyReport $radiologyReport): RedirectResponse
    {
        $this->authorize('approve', $radiologyReport);

        $radiologyReport->update([
            'status'      => 'Approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        ActivityLog::create([
            'user_id'       => Auth::id(),
            'action'        => 'Radiology Report Approved',
            'module'        => 'Radiology',
            'description'   => "Radiology report #{$radiologyReport->id} approved and finalized by Radiologist.",
            'loggable_type' => RadiologyReport::class,
            'loggable_id'   => $radiologyReport->id,
            'ip_address'    => request()->ip(),
            'logged_at'     => now(),
        ]);

        return back()->with('success', 'Radiology report approved and finalized.');
    }

    public function release(RadiologyReport $radiologyReport): RedirectResponse
    {
        $this->authorize('release', $radiologyReport);

        $radiologyReport->update([
            'status'      => 'Released',
            'released_by' => Auth::id(),
            'released_at' => now(),
        ]);

        ActivityLog::create([
            'user_id'       => Auth::id(),
            'action'        => 'Radiology Report Released',
            'module'        => 'Radiology',
            'description'   => "Radiology report #{$radiologyReport->id} officially released to referring doctor.",
            'loggable_type' => RadiologyReport::class,
            'loggable_id'   => $radiologyReport->id,
            'ip_address'    => request()->ip(),
            'logged_at'     => now(),
        ]);

        return back()->with('success', 'Radiology report officially released to referring doctor.');
    }

    /** Return a draft report for correction by radiology staff. */
    public function returnForCorrection(Request $request, RadiologyReport $radiologyReport): RedirectResponse
    {
        $this->authorize('returnForCorrection', $radiologyReport);

        $validated = $request->validate([
            'correction_notes' => ['required', 'string', 'max:1000'],
        ]);

        $notes = trim($validated['correction_notes']);
        $rec = $radiologyReport->recommendations ? $radiologyReport->recommendations . "\n" : '';
        $rec .= "[Returned for Correction on " . now()->format('Y-m-d H:i') . " by " . Auth::user()->name . "]: " . $notes;

        $radiologyReport->update([
            'recommendations' => $rec,
        ]);

        ActivityLog::create([
            'user_id'       => Auth::id(),
            'action'        => 'Radiology Report Returned for Correction',
            'module'        => 'Radiology',
            'severity'      => ActivityLog::SEVERITY_WARNING,
            'description'   => "Radiology report #{$radiologyReport->id} returned for correction. Notes: {$notes}",
            'loggable_type' => RadiologyReport::class,
            'loggable_id'   => $radiologyReport->id,
            'ip_address'    => request()->ip(),
            'logged_at'     => now(),
        ]);

        return back()->with('success', 'Radiology report returned for correction.');
    }

    /** Print-friendly view for radiology report. */
    public function print(RadiologyReport $radiologyReport): View
    {
        $this->authorize('view', $radiologyReport);
        $radiologyReport->load('radiologyRequest.patient', 'radiologyRequest.doctor', 'radiologist');

        return view('radiology.reports.print', compact('radiologyReport'));
    }
}
