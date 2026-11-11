@extends('layouts.app')
@section('title', 'Diagnostic Report Details')
@section('page-title', 'Diagnostic Report Details')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('radiology.reports.index') }}">Reports</a></li>
    <li class="breadcrumb-item active">Report Details</li>
@endsection
@section('content')
<div class="row g-3">
    {{-- Left Column — Info & Stats --}}
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header bg-light"><i class="bi bi-info-circle me-2"></i>Report Context</div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5 text-muted">Status</dt>
                    <dd class="col-7"><span class="badge bg-{{ $radiologyReport->statusBadge }}">{{ $radiologyReport->status }}</span></dd>
                    
                    @if($radiologyReport->radiologyRequest)
                        <dt class="col-5 text-muted">Request No</dt>
                        <dd class="col-7">
                            <a href="{{ route('radiology.requests.show', $radiologyReport->radiologyRequest) }}" class="fw-bold">
                                {{ $radiologyReport->radiologyRequest->request_no }}
                            </a>
                        </dd>
                        <dt class="col-5 text-muted">Patient</dt>
                        <dd class="col-7">
                            {{ $radiologyReport->radiologyRequest->patient->last_name }}, {{ $radiologyReport->radiologyRequest->patient->first_name }}<br>
                            <small class="text-muted">({{ $radiologyReport->radiologyRequest->patient->patient_no }})</small>
                        </dd>
                        <dt class="col-5 text-muted">Modality</dt>
                        <dd class="col-7 fw-semibold text-secondary">{{ $radiologyReport->radiologyRequest->modality }}</dd>
                        <dt class="col-5 text-muted">Region</dt>
                        <dd class="col-7">{{ $radiologyReport->radiologyRequest->body_part }}</dd>
                        <dt class="col-5 text-muted">Referring Doctor</dt>
                        <dd class="col-7">{{ $radiologyReport->radiologyRequest->doctor->name }}</dd>
                    @endif
                    
                    <dt class="col-5 text-muted">Radiologist</dt>
                    <dd class="col-7">{{ $radiologyReport->radiologist->name }}</dd>
                    
                    <dt class="col-5 text-muted">Created Date</dt>
                    <dd class="col-7">{{ $radiologyReport->created_at->format('M d, Y H:i') }}</dd>
                    
                    @if($radiologyReport->approved_at)
                        <dt class="col-5 text-muted">Approved By</dt>
                        <dd class="col-7">
                            {{ $radiologyReport->approvedBy->name ?? 'System' }}<br>
                            <small class="text-muted">{{ $radiologyReport->approved_at->format('M d, H:i') }}</small>
                        </dd>
                    @endif
                    
                    @if($radiologyReport->released_at)
                        <dt class="col-5 text-muted">Released By</dt>
                        <dd class="col-7">
                            {{ $radiologyReport->releasedBy->name ?? 'System' }}<br>
                            <small class="text-muted">{{ $radiologyReport->released_at->format('M d, H:i') }}</small>
                        </dd>
                    @endif
                </dl>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-light"><i class="bi bi-gear me-2"></i>Actions</div>
            <div class="card-body d-flex flex-column gap-2">
                @if($radiologyReport->status === 'Draft' && auth()->user()->hasRole('radiologist'))
                    <form method="POST" action="{{ route('radiology.reports.approve', $radiologyReport) }}">
                        @csrf @method('PATCH')
                        <button type="submit" class="btn btn-success w-100"><i class="bi bi-check2-square me-2"></i>Approve Report</button>
                    </form>
                    <button type="button" class="btn btn-outline-warning w-100" data-bs-toggle="modal" data-bs-target="#returnReportModal"><i class="bi bi-arrow-return-left me-2"></i>Return for Correction</button>
                @endif

                @if($radiologyReport->status === 'Approved' && auth()->user()->hasRole('radiologist'))
                    <form method="POST" action="{{ route('radiology.reports.release', $radiologyReport) }}">
                        @csrf @method('PATCH')
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-send-check me-2"></i>Release Report</button>
                    </form>
                @endif

                @if($radiologyReport->status === 'Draft' && (auth()->user()->hasRole('radiologist') || (auth()->user()->hasRole('rad-tech') && auth()->user()->hasPermission('ris.reports.prepare'))))
                    <a href="{{ route('radiology.reports.edit', $radiologyReport) }}" class="btn btn-outline-primary w-100"><i class="bi bi-pencil me-2"></i>Edit Report Findings</a>
                @endif

                @if($radiologyReport->status === 'Draft' && auth()->user()->hasRole('radiologist'))
                    <form method="POST" action="{{ route('radiology.reports.destroy', $radiologyReport) }}" onsubmit="return confirm('Are you sure you want to delete this draft report?');">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger w-100"><i class="bi bi-trash me-2"></i>Delete Report</button>
                    </form>
                @endif

                <a href="{{ route('radiology.reports.print', $radiologyReport) }}" target="_blank" class="btn btn-outline-info w-100"><i class="bi bi-printer me-2"></i>Print Diagnostic Report</a>
                <a href="{{ route('radiology.reports.index') }}" class="btn btn-outline-secondary w-100"><i class="bi bi-chevron-left me-2"></i>Back to Reports</a>
            </div>
        </div>
    </div>

    {{-- Right Column — Report Content --}}
    <div class="col-lg-8">
        {{-- Scan Images summary helper --}}
        @if($radiologyReport->radiologyRequest && $radiologyReport->radiologyRequest->images->isNotEmpty())
            <div class="card mb-3">
                <div class="card-header bg-lightSmall small py-2"><i class="bi bi-image me-2"></i>Uploaded Imaging Scan File(s) Reference</div>
                <div class="card-body py-2 px-3 small">
                    <ul class="list-unstyled mb-0 d-flex flex-wrap gap-3">
                        @foreach($radiologyReport->radiologyRequest->images as $img)
                            <li>
                                <i class="bi bi-file-earmark-image text-primary me-1"></i>
                                <a href="{{ route('radiology.images.view', $img) }}" target="_blank" class="fw-semibold text-break">{{ $img->file_name }}</a>
                                <span class="text-muted">({{ strtoupper($img->file_type) }})</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        {{-- Findings card --}}
        <div class="card mb-3 border-start border-primary border-4">
            <div class="card-body">
                <h5 class="card-title fw-bold text-dark mb-3"><i class="bi bi-eye text-primary me-2"></i>Findings</h5>
                <div class="p-3 bg-light rounded text-dark fs-6 text-break" style="white-space: pre-line; line-height: 1.6;">
                    {{ $radiologyReport->findings }}
                </div>
            </div>
        </div>

        {{-- Impression card --}}
        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title fw-bold text-dark mb-3"><i class="bi bi-check-circle text-success me-2"></i>Clinical Impression</h5>
                <div class="p-3 bg-light rounded text-dark fs-6 text-break fw-semibold" style="white-space: pre-line; line-height: 1.6;">
                    {{ $radiologyReport->impression }}
                </div>
            </div>
        </div>

        {{-- Recommendations (optional) --}}
        @if($radiologyReport->recommendations)
            <div class="card border-start border-secondary border-4">
                <div class="card-body">
                    <h5 class="card-title fw-bold text-dark mb-3"><i class="bi bi-journal-medical text-secondary me-2"></i>Recommendations & Notes</h5>
                    <div class="p-3 bg-light rounded text-secondary fs-6 text-break" style="white-space: pre-line; line-height: 1.5;">
                        {{ $radiologyReport->recommendations }}
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

@if($radiologyReport->status === 'Draft' && auth()->user()->hasRole('radiologist'))
<div class="modal fade" id="returnReportModal" tabindex="-1" aria-labelledby="returnReportModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('radiology.reports.return', $radiologyReport) }}" class="modal-content">
            @csrf @method('PATCH')
            <div class="modal-header">
                <h5 class="modal-title" id="returnReportModalLabel"><i class="bi bi-arrow-return-left text-warning me-2"></i>Return Report for Correction</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">Provide clinical notes and specific correction instructions for the radiology technologist / preparer.</p>
                <div class="mb-3">
                    <label for="correction_notes" class="form-label fw-semibold">Correction Notes <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="correction_notes" name="correction_notes" rows="3" required placeholder="Specify what needs to be revised or corrected..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-warning"><i class="bi bi-arrow-return-left me-1"></i>Return for Correction</button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection
