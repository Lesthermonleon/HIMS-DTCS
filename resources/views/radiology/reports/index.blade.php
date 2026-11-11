@extends('layouts.app')
@section('title', 'Radiology Reports')
@section('page-title', 'Radiology Reports')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('radiology.dashboard') }}">RIS</a></li>
    <li class="breadcrumb-item active">Reports</li>
@endsection
@section('content')
<div class="card">
    <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
        <h6 class="mb-0 fw-semibold text-secondary"><i class="bi bi-file-earmark-medical me-2"></i>Diagnostic Reports Archive</h6>
        @if(auth()->user()?->hasRole('radiologist') || (auth()->user()?->hasRole('rad-tech') && auth()->user()->hasPermission('ris.reports.prepare')))
            <a href="{{ route('radiology.reports.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle me-1"></i>New Draft Report</a>
        @endif
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Report No</th>
                        <th>Request No</th>
                        <th>Patient</th>
                        <th>Modality (Region)</th>
                        <th>Radiologist</th>
                        <th>Status</th>
                        <th>Created Date</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reports as $rpt)
                        <tr>
                            <td><a href="{{ route('radiology.reports.show', $rpt) }}" class="fw-bold">{{ $rpt->report_no ?? ('ID: ' . $rpt->id) }}</a></td>
                            <td>
                                @if($rpt->radiologyRequest)
                                    <a href="{{ route('radiology.requests.show', $rpt->radiologyRequest) }}">{{ $rpt->radiologyRequest->request_no }}</a>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if($rpt->radiologyRequest && $rpt->radiologyRequest->patient)
                                    {{ $rpt->radiologyRequest->patient->last_name }}, {{ $rpt->radiologyRequest->patient->first_name }}<br>
                                    <small class="text-muted">{{ $rpt->radiologyRequest->patient->patient_no }}</small>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if($rpt->radiologyRequest)
                                    <span class="fw-semibold text-secondary">{{ $rpt->radiologyRequest->modality }}</span><br>
                                    <small class="text-muted">{{ $rpt->radiologyRequest->body_part }}</small>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>{{ $rpt->radiologist->name }}</td>
                            <td><span class="badge bg-{{ $rpt->statusBadge }}">{{ $rpt->status }}</span></td>
                            <td><small class="text-muted">{{ $rpt->created_at->format('M d, Y H:i') }}</small></td>
                            <td class="text-end">
                                <div class="table-actions justify-content-end">
                                    <a href="{{ route('radiology.reports.show', $rpt) }}" class="table-action-btn action-view" title="View Diagnostic Report" aria-label="View Diagnostic Report"><i class="bi bi-eye"></i></a>
                                    @if($rpt->status === 'Draft' && (auth()->user()?->hasRole('radiologist') || (auth()->user()?->hasRole('rad-tech') && auth()->user()->hasPermission('ris.reports.prepare'))))
                                        <a href="{{ route('radiology.reports.edit', $rpt) }}" class="table-action-btn action-edit" title="Edit Diagnostic Report" aria-label="Edit Diagnostic Report"><i class="bi bi-pencil"></i></a>
                                    @endif
                                    @if($rpt->status === 'Draft' && auth()->user()?->hasRole('radiologist'))
                                        <form method="POST" action="{{ route('radiology.reports.destroy', $rpt) }}" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this draft report?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="table-action-btn action-danger" title="Delete Draft Report" aria-label="Delete Draft Report"><i class="bi bi-trash"></i></button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">No diagnostic reports found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($reports->hasPages())
        <div class="card-footer">{{ $reports->links() }}</div>
    @endif
</div>
@endsection
