@extends('layouts.app')
@section('title', 'Lab Request ' . $labRequest->request_no)
@section('page-title', 'Lab Request: ' . $labRequest->request_no)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('lab.requests.index') }}">Lab Requests</a></li>
    <li class="breadcrumb-item active">{{ $labRequest->request_no }}</li>
@endsection
@section('content')
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-info-circle me-2"></i>Request Information</div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5 text-muted">Request No</dt><dd class="col-7 fw-semibold">{{ $labRequest->request_no }}</dd>
                    <dt class="col-5 text-muted">Patient</dt><dd class="col-7">{{ $labRequest->patient?->full_name ?? 'Unspecified Patient' }}</dd>
                    <dt class="col-5 text-muted">Patient No</dt><dd class="col-7">{{ $labRequest->patient?->patient_no ?? 'N/A' }}</dd>
                    <dt class="col-5 text-muted">Doctor</dt><dd class="col-7">{{ $labRequest->doctor?->name ?? 'Unassigned Doctor' }}</dd>
                    <dt class="col-5 text-muted">Priority</dt>
                    <dd class="col-7"><span class="badge bg-{{ $labRequest->priority==='STAT'?'danger':($labRequest->priority==='Urgent'?'warning text-dark':'secondary') }}">{{ $labRequest->priority }}</span></dd>
                    <dt class="col-5 text-muted">Specimen</dt><dd class="col-7">{{ $labRequest->specimen_type }}</dd>
                    <dt class="col-5 text-muted">Status</dt><dd class="col-7"><span class="badge bg-{{ $labRequest->statusBadge }}">{{ $labRequest->status }}</span></dd>
                    <dt class="col-5 text-muted">Requested</dt><dd class="col-7">{{ $labRequest->requested_at?->format('M d, Y H:i') ?? 'N/A' }}</dd>
                    @if($labRequest->received_at)<dt class="col-5 text-muted">Received</dt><dd class="col-7">{{ $labRequest->received_at->format('M d, Y H:i') }}</dd>@endif
                    @if($labRequest->clinical_notes)<dt class="col-5 text-muted">Notes</dt><dd class="col-7">{{ $labRequest->clinical_notes }}</dd>@endif
                </dl>
            </div>
        </div>
        <div class="d-flex flex-column gap-2">
            @if($labRequest->status==='Pending')
                @if(auth()->user()?->hasRole('med-tech') && auth()->user()->hasPermission('lis.specimens.process'))
                    <form method="POST" action="{{ route('lab.requests.receive', $labRequest) }}">
                        @csrf @method('PATCH')
                        <button type="submit" class="btn btn-success w-100" data-confirm="Are you sure you want to mark lab request {{ $labRequest->request_no }} as received?"><i class="bi bi-inbox-fill me-1"></i>Mark as Received</button>
                    </form>
                    <button type="button" class="btn btn-outline-danger w-100" data-bs-toggle="modal" data-bs-target="#rejectSpecimenModal"><i class="bi bi-x-circle me-1"></i>Reject Specimen</button>
                @endif
                @if(auth()->user()?->hasRole('doctor'))
                    <a href="{{ route('lab.requests.edit', $labRequest) }}" class="btn btn-outline-warning"><i class="bi bi-pencil me-1"></i>Edit Request</a>
                @endif
            @elseif($labRequest->status==='In Progress' && auth()->user()?->hasRole('med-tech') && auth()->user()->hasPermission('lis.specimens.process'))
                <button type="button" class="btn btn-outline-danger w-100" data-bs-toggle="modal" data-bs-target="#rejectSpecimenModal"><i class="bi bi-x-circle me-1"></i>Reject Specimen</button>
            @endif
            <a href="{{ route('lab.requests.print', ['labRequest' => $labRequest]) }}" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-printer me-1"></i>Print Report</a>
            <a href="{{ route('lab.requests.index') }}" class="btn btn-outline-primary"><i class="bi bi-arrow-left me-1"></i>Back to List</a>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><i class="bi bi-list-task me-2"></i>Tests Ordered</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                    <thead><tr><th>Test</th><th>Category</th><th>Normal Range</th><th>Unit</th><th>Status</th><th>Result</th></tr></thead>
                    <tbody>
                    @foreach($labRequest->items as $item)
                    <tr>
                        <td class="fw-semibold">{{ $item->labTest?->name ?? 'Unknown Test' }}<br><small class="text-muted">{{ $item->labTest?->code ?? '-' }}</small></td>
                        <td>{{ $item->labTest?->category?->name ?? 'Uncategorized' }}</td>
                        <td>{{ $item->labTest?->normal_range ?? '-' }}</td>
                        <td>{{ $item->labTest?->unit ?? '-' }}</td>
                        <td><span class="badge bg-{{ $item->status === 'Completed' ? 'success' : ($item->status === 'In Progress' ? 'info' : ($item->status === 'Cancelled' ? 'danger' : 'secondary')) }}">{{ $item->status }}</span></td>
                        <td>
                        @if($item->result)
                            <strong>{{ $item->result->result_value }} {{ $item->labTest->unit }}</strong><br>
                            <small class="text-muted">{{ $item->result->remarks }}</small><br>
                            <span class="badge bg-{{ $item->result->statusBadge }}">{{ $item->result->status }}</span>
                            @if(auth()->user()?->hasRole('med-tech') && in_array($item->result->status, ['Encoded','Validated']))
                                @if($item->result->status === 'Encoded' && auth()->user()->hasPermission('lis.results.verify'))
                                    <form method="POST" action="{{ route('lab.results.validate', $item->result) }}" class="d-inline">
                                        @csrf @method('PATCH')
                                        <button class="btn btn-xs btn-outline-primary ms-1" style="font-size:.7rem;padding:2px 6px">Validate</button>
                                    </form>
                                @elseif($item->result->status === 'Validated' && auth()->user()->hasPermission('lis.results.release'))
                                    <form method="POST" action="{{ route('lab.results.release', $item->result) }}" class="d-inline">
                                        @csrf @method('PATCH')
                                        <button class="btn btn-xs btn-outline-success ms-1" style="font-size:.7rem;padding:2px 6px">Release</button>
                                    </form>
                                @endif
                            @endif
                        @elseif(auth()->user()?->hasRole('med-tech') && auth()->user()->hasPermission('lis.results.enter') && $labRequest->status==='In Progress')
                            <a href="{{ route('lab.results.create') }}?item_id={{ $item->id }}" class="btn btn-sm btn-outline-info">Encode Result</a>
                        @else
                            <span class="text-muted small">Awaiting result</span>
                        @endif
                        </td>
                    </tr>
                    @endforeach
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</div>

@if(auth()->user()?->hasRole('med-tech') && auth()->user()->hasPermission('lis.specimens.process') && !in_array($labRequest->status, ['Completed', 'Cancelled']))
<div class="modal fade" id="rejectSpecimenModal" tabindex="-1" aria-labelledby="rejectSpecimenModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('lab.requests.reject', $labRequest) }}" class="modal-content">
            @csrf @method('PATCH')
            <div class="modal-header">
                <h5 class="modal-title" id="rejectSpecimenModalLabel"><i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>Reject Specimen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">Please record the clinical reason for rejecting the specimen for request <strong>{{ $labRequest->request_no }}</strong> (e.g., hemolyzed sample, insufficient volume, incorrect container, unlabelled tube).</p>
                <div class="mb-3">
                    <label for="rejection_reason" class="form-label fw-semibold">Rejection Reason <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="3" required placeholder="Specify reason for specimen rejection..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger"><i class="bi bi-x-circle me-1"></i>Confirm Rejection</button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection
