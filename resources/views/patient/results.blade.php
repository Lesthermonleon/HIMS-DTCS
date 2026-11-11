@extends('layouts.app')
@section('title', 'My Diagnostic Results — Patient Portal')
@section('page-title', 'Released Diagnostic Reports')

@section('content')
<div class="row g-3">

    {{-- LABORATORY RESULTS TAB / CARD --}}
    <div class="col-12">
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center py-3">
                <div>
                    <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-clipboard2-pulse me-2 text-success"></i>Laboratory Results (Released)</h5>
                    <small class="text-muted">Validated pathology and clinical chemistry reports released by the medical technologist</small>
                </div>
                <span class="badge bg-success">{{ $labResults->total() }} Released</span>
            </div>
            <div class="card-body p-0">
                @if($labResults->isEmpty())
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-droplet-half fs-1 opacity-50"></i>
                        <p class="mt-2 mb-0">No released laboratory results available.</p>
                        <small class="text-muted">Pending tests currently in analysis are hidden until professional verification.</small>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Test Name</th>
                                    <th>Category</th>
                                    <th>Result Finding</th>
                                    <th>Reference Range</th>
                                    <th>Ordering Physician</th>
                                    <th>Released At</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($labResults as $result)
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-dark">{{ $result->requestItem?->labTest?->name ?? 'Diagnostic Test' }}</div>
                                            <small class="font-monospace text-muted">{{ $result->requestItem?->labTest?->code }}</small>
                                        </td>
                                        <td>{{ $result->requestItem?->labTest?->category?->name ?? 'Clinical Lab' }}</td>
                                        <td>
                                            <span class="fw-bold text-dark font-monospace">{{ $result->result_value ?? 'Evaluated' }}</span>
                                            @if($result->unit)
                                                <small class="text-muted ms-1">{{ $result->unit }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            <small class="text-secondary">{{ $result->requestItem?->labTest?->normal_range ?? 'Standard' }}</small>
                                        </td>
                                        <td>Dr. {{ $result->requestItem?->labRequest?->doctor?->name ?? 'Attending' }}</td>
                                        <td>
                                            <div>{{ $result->released_at ? $result->released_at->format('M d, Y') : '—' }}</div>
                                            <small class="text-muted">{{ $result->released_at ? $result->released_at->format('g:i A') : '' }}</small>
                                        </td>
                                        <td>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                <i class="bi bi-check-circle me-1"></i>Released
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($labResults->hasPages())
                        <div class="card-footer bg-transparent py-3">
                            {{ $labResults->links() }}
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>

    {{-- RADIOLOGY REPORTS TAB / CARD --}}
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center py-3">
                <div>
                    <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-camera me-2 text-info"></i>Radiology Interpretations (Released)</h5>
                    <small class="text-muted">Imaging studies and diagnostic interpretations signed off by the radiologist</small>
                </div>
                <span class="badge bg-info text-dark">{{ $radiologyReports->total() }} Released</span>
            </div>
            <div class="card-body p-0">
                @if($radiologyReports->isEmpty())
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-image fs-1 opacity-50"></i>
                        <p class="mt-2 mb-0">No released radiology interpretations on file.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Study / Procedure</th>
                                    <th>Modality</th>
                                    <th>Clinical Findings Summary</th>
                                    <th>Impression</th>
                                    <th>Attending Physician</th>
                                    <th>Released Date</th>
                                    <th>Report</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($radiologyReports as $report)
                                    <tr>
                                        <td class="fw-semibold text-dark">{{ $report->radiologyRequest?->procedure_name ?? 'Imaging Study' }}</td>
                                        <td>
                                            <span class="badge bg-light text-dark border">{{ $report->radiologyRequest?->modality ?? 'X-Ray' }}</span>
                                        </td>
                                        <td>
                                            <span class="small text-secondary">{{ Str::limit($report->findings ?? 'Study concluded within parameters.', 75) }}</span>
                                        </td>
                                        <td>
                                            <span class="small fw-semibold text-dark">{{ Str::limit($report->impression ?? 'Normal', 60) }}</span>
                                        </td>
                                        <td>Dr. {{ $report->radiologyRequest?->doctor?->name ?? 'Attending' }}</td>
                                        <td>{{ $report->released_at ? $report->released_at->format('M d, Y') : $report->updated_at->format('M d, Y') }}</td>
                                        <td>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                <i class="bi bi-file-earmark-check me-1"></i>Official
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($radiologyReports->hasPages())
                        <div class="card-footer bg-transparent py-3">
                            {{ $radiologyReports->links() }}
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>

</div>
@endsection
