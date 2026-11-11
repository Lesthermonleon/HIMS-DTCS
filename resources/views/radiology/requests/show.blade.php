@extends('layouts.app')
@section('title', 'Request Details ' . $radiologyRequest->request_no)
@section('page-title', 'Radiology Request: ' . $radiologyRequest->request_no)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('radiology.requests.index') }}">Requests</a></li>
    <li class="breadcrumb-item active">{{ $radiologyRequest->request_no }}</li>
@endsection
@section('content')
<div class="row g-3">
    {{-- Left Column — Info & Actions --}}
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-info-circle me-2"></i>Request Details</div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5 text-muted">Request No</dt><dd class="col-7 fw-bold text-primary">{{ $radiologyRequest->request_no }}</dd>
                    <dt class="col-5 text-muted">Patient</dt>
                    <dd class="col-7">
                        <span class="fw-semibold">
                            {{ $radiologyRequest->patient?->last_name ?? 'N/A' }}, {{ $radiologyRequest->patient?->first_name ?? 'Patient' }}
                        </span>
                        <br><small class="text-muted">({{ $radiologyRequest->patient?->patient_no ?? 'N/A' }})</small>
                    </dd>
                    <dt class="col-5 text-muted">Ordering Doctor</dt><dd class="col-7">{{ $radiologyRequest->doctor?->name ?? 'Ordering Physician' }}</dd>
                    <dt class="col-5 text-muted">Modality</dt><dd class="col-7 fw-semibold text-secondary">{{ $radiologyRequest->modality }}</dd>
                    <dt class="col-5 text-muted">Region</dt><dd class="col-7">{{ $radiologyRequest->body_part }}</dd>
                    <dt class="col-5 text-muted">Priority</dt>
                    <dd class="col-7">
                        <span class="badge bg-{{ $radiologyRequest->priority === 'STAT' ? 'danger' : ($radiologyRequest->priority === 'Urgent' ? 'warning text-dark' : 'secondary') }}">
                            {{ $radiologyRequest->priority }}
                        </span>
                    </dd>
                    <dt class="col-5 text-muted">Status</dt>
                    <dd class="col-7"><span class="badge bg-{{ $radiologyRequest->statusBadge }}">{{ $radiologyRequest->status }}</span></dd>
                    <dt class="col-5 text-muted">Requested At</dt><dd class="col-7">{{ $radiologyRequest->requested_at?->format('M d, Y H:i') }}</dd>
                    @if($radiologyRequest->scheduled_at)
                        <dt class="col-5 text-muted">Scheduled At</dt><dd class="col-7">{{ $radiologyRequest->scheduled_at->format('M d, Y H:i') }}</dd>
                    @endif
                    @if($radiologyRequest->completed_at)
                        <dt class="col-5 text-muted">Completed At</dt><dd class="col-7">{{ $radiologyRequest->completed_at->format('M d, Y H:i') }}</dd>
                    @endif
                </dl>
                @if($radiologyRequest->clinical_information)
                    <div class="mt-3 p-2 bg-light rounded text-secondary small">
                        <div class="fw-bold mb-1">Clinical Notes:</div>
                        {{ $radiologyRequest->clinical_information }}
                    </div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="bi bi-gear me-2"></i>Actions</div>
            <div class="card-body d-flex flex-column gap-2">
                {{-- Technologist Procedure Lifecycle Actions --}}
                @if($radiologyRequest->status === 'Pending' && auth()->user()->hasRole('rad-tech'))
                    <form method="POST" action="{{ route('radiology.requests.schedule', $radiologyRequest) }}">
                        @csrf @method('PATCH')
                        <button type="submit" class="btn btn-success w-100"><i class="bi bi-calendar-event me-2"></i>Schedule Procedure</button>
                    </form>
                @endif

                @if($radiologyRequest->status === 'Scheduled' && auth()->user()->hasRole('rad-tech'))
                    <form method="POST" action="{{ route('radiology.requests.start', $radiologyRequest) }}">
                        @csrf @method('PATCH')
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-play-circle me-2"></i>Start Imaging Procedure</button>
                    </form>
                @endif

                @if(in_array($radiologyRequest->status, ['Scheduled', 'In Progress']) && auth()->user()->hasRole('rad-tech'))
                    <form method="POST" action="{{ route('radiology.requests.complete', $radiologyRequest) }}" onsubmit="return confirm('Complete imaging procedure and send study for radiologist interpretation?');">
                        @csrf @method('PATCH')
                        <button type="submit" class="btn btn-info text-white w-100"><i class="bi bi-check-circle me-2"></i>Complete Procedure & Send to Radiologist</button>
                    </form>
                @endif

                {{-- Doctor / Admin Request Management --}}
                @if($radiologyRequest->status === 'Pending' && auth()->user()->hasRole('doctor'))
                    <a href="{{ route('radiology.requests.edit', $radiologyRequest) }}" class="btn btn-success w-100"><i class="bi bi-pencil me-2"></i>Edit Request</a>
                    <form method="POST" action="{{ route('radiology.requests.destroy', $radiologyRequest) }}" onsubmit="return confirm('Are you sure you want to cancel this request?');">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger w-100"><i class="bi bi-x-circle me-2"></i>Cancel Request</button>
                    </form>
                @endif

                {{-- Radiologist / Staff Diagnostic Reporting Actions --}}
                @if(in_array($radiologyRequest->status, ['Completed', 'In Progress']) && (auth()->user()->hasRole('radiologist') || (auth()->user()->hasRole('rad-tech') && auth()->user()->hasPermission('ris.reports.prepare'))) && !$radiologyRequest->report)
                    <a href="{{ route('radiology.reports.create') }}?radiology_request_id={{ $radiologyRequest->id }}" class="btn btn-primary w-100"><i class="bi bi-journal-medical me-2"></i>Prepare Diagnostic Report</a>
                @endif

                @if($radiologyRequest->report && auth()->user()->can('view', $radiologyRequest->report))
                    <a href="{{ route('radiology.reports.show', $radiologyRequest->report) }}" class="btn btn-success w-100"><i class="bi bi-file-earmark-medical me-2"></i>View Diagnostic Report</a>
                @endif

                <a href="{{ route('radiology.requests.index') }}" class="btn btn-outline-secondary w-100"><i class="bi bi-chevron-left me-2"></i>Back to Requests</a>
            </div>
        </div>
    </div>

    {{-- Right Column — Images & Reports --}}
    <div class="col-lg-7">
        {{-- Imaging Files / Scans Gallery --}}
        <div class="card mb-3 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <span class="fw-bold text-dark"><i class="bi bi-images text-primary me-2"></i>Uploaded Imaging Study File(s)</span>
                <span class="badge bg-primary rounded-pill px-3">{{ $radiologyRequest->images->count() }} Scans</span>
            </div>
            <div class="card-body p-3">
                @if($radiologyRequest->images->isNotEmpty())
                    <div class="row g-3">
                        @foreach($radiologyRequest->images as $img)
                            @php
                                $isImage = in_array(strtolower($img->file_type), ['jpg', 'jpeg', 'png', 'webp', 'gif']);
                                $viewUrl = route('radiology.images.view', $img);
                            @endphp
                            <div class="col-md-6 col-lg-4">
                                <div class="card h-100 border text-center overflow-hidden position-relative shadow-xs">
                                    <div class="bg-dark d-flex align-items-center justify-content-center overflow-hidden" style="height: 140px;">
                                        @if($isImage)
                                            <img src="{{ $viewUrl }}" alt="{{ $img->file_name }}" class="w-100 h-100 object-fit-cover hover-zoom" style="cursor: pointer;" data-bs-toggle="modal" data-bs-target="#imgModal{{ $img->id }}">
                                        @else
                                            <div class="text-white text-center">
                                                <i class="bi bi-file-earmark-pdf fs-1 text-danger"></i>
                                                <small class="d-block text-white-50 mt-1">{{ strtoupper($img->file_type) }}</small>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="card-body p-2 text-start small">
                                        <div class="fw-semibold text-truncate" title="{{ $img->file_name }}">{{ $img->file_name }}</div>
                                        <div class="text-muted extra-small">
                                            {{ round($img->file_size / 1024) }} KB · {{ strtoupper($img->file_type) }}
                                        </div>
                                        <div class="mt-1 extra-small text-secondary">
                                            <i class="bi bi-person-check me-1"></i>{{ $img->uploadedBy?->name ?? 'Technologist' }}<br>
                                            <i class="bi bi-clock me-1"></i>{{ $img->uploaded_at?->format('M d, Y H:i') }}
                                        </div>
                                        @if($img->notes)
                                            <div class="mt-2 p-1 bg-light rounded text-dark extra-small">
                                                <i class="bi bi-sticky me-1 text-warning"></i>{{ $img->notes }}
                                            </div>
                                        @endif
                                    </div>
                                    <div class="card-footer bg-light p-2 d-flex justify-content-between align-items-center">
                                        <a href="{{ $viewUrl }}" target="_blank" class="btn btn-xs btn-outline-primary w-100 me-1"><i class="bi bi-eye me-1"></i>View File</a>
                                    </div>
                                </div>
                            </div>

                            {{-- Modal Viewer for Images --}}
                            @if($isImage)
                                <div class="modal fade" id="imgModal{{ $img->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered modal-xl">
                                        <div class="modal-content bg-dark text-white border-0">
                                            <div class="modal-header border-secondary">
                                                <h6 class="modal-title"><i class="bi bi-image me-2"></i>{{ $img->file_name }}</h6>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body text-center p-2 bg-black">
                                                <img src="{{ $viewUrl }}" class="img-fluid rounded" style="max-height: 80vh;" alt="{{ $img->file_name }}">
                                            </div>
                                            <div class="modal-footer border-secondary justify-content-between small">
                                                <div>
                                                    <span class="text-white-50">Uploaded by:</span> {{ $img->uploadedBy?->name ?? 'Technologist' }} ({{ $img->uploaded_at?->format('M d, Y H:i') }})
                                                    @if($img->notes) — <em>{{ $img->notes }}</em>@endif
                                                </div>
                                                <a href="{{ $viewUrl }}" target="_blank" class="btn btn-sm btn-outline-light"><i class="bi bi-box-arrow-up-right me-1"></i>Open Full Size</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @else
                    <div class="p-4 text-center text-muted border border-dashed rounded bg-light">
                        <i class="bi bi-cloud-slash fs-1 d-block mb-2 text-secondary"></i>
                        No scanning images or study files have been uploaded yet for this request.
                    </div>
                @endif
            </div>
        </div>

        {{-- Multi-File Upload Form for Rad-Tech / Admin --}}
        @if(in_array($radiologyRequest->status, ['Scheduled', 'In Progress']) && auth()->user()->hasRole('rad-tech'))
            <div class="card mb-3 border-primary shadow-sm">
                <div class="card-header bg-primary text-white font-semibold d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-cloud-arrow-up-fill me-2"></i>Upload Imaging Study & Documents</span>
                    <span class="badge bg-white text-primary">Procedure Execution</span>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('radiology.requests.upload', $radiologyRequest) }}" enctype="multipart/form-data">
                        @csrf
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Modality / Procedure</label>
                                <input type="text" class="form-control form-control-sm bg-light" value="{{ $radiologyRequest->modality }} — {{ $radiologyRequest->body_part }}" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Patient Name</label>
                                <input type="text" class="form-control form-control-sm bg-light" value="{{ $radiologyRequest->patient?->last_name ?? 'N/A' }}, {{ $radiologyRequest->patient?->first_name ?? 'Patient' }}" readonly>
                            </div>
                        </div>

                        {{-- Section 1: Upload Imaging Study --}}
                        <div class="mb-4">
                            <label class="form-label small fw-semibold text-primary">
                                <i class="bi bi-images me-1"></i>Upload Imaging Study
                                @if($radiologyRequest->images->count() > 0)
                                    <span class="text-muted fw-normal">(Optional if completing procedure with existing scans)</span>
                                @else
                                    <span class="text-danger">*</span>
                                @endif
                            </label>
                            <div class="border border-2 border-primary border-dashed rounded p-3 text-center bg-light">
                                <i class="bi bi-file-earmark-medical fs-2 text-primary d-block mb-2"></i>
                                <div class="small fw-semibold text-dark mb-1">Select one or multiple imaging files. Supported formats: DICOM, JPEG, PNG, WEBP.</div>
                                <input type="file" id="imaging_files_input" name="images[]" accept=".dcm,.dicom,.jpg,.jpeg,.png,.webp,image/*" class="form-control form-control-sm @error('images') is-invalid @enderror @error('images.*') is-invalid @enderror" multiple @if($radiologyRequest->images->count() == 0) required @endif>
                                <div id="imaging_files_feedback" class="mt-2 small text-start d-none"></div>
                                @error('images')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                @error('images.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        {{-- Section 2: Supporting Documents (Optional) --}}
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">
                                <i class="bi bi-file-earmark-pdf me-1"></i>Supporting Documents (Optional)
                            </label>
                            <div class="border border-2 border-secondary border-dashed rounded p-3 text-center bg-light">
                                <i class="bi bi-file-earmark-pdf fs-2 text-danger d-block mb-2"></i>
                                <div class="small fw-semibold text-dark mb-1">Upload PDF documents related to this imaging study.</div>
                                <input type="file" id="document_files_input" name="documents[]" accept=".pdf,application/pdf" class="form-control form-control-sm @error('documents') is-invalid @enderror @error('documents.*') is-invalid @enderror" multiple>
                                <div id="document_files_feedback" class="mt-2 small text-start d-none"></div>
                                @error('documents')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                @error('documents.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <script>
                            document.addEventListener('DOMContentLoaded', function () {
                                const imgInput = document.getElementById('imaging_files_input');
                                const docInput = document.getElementById('document_files_input');

                                if (imgInput) {
                                    imgInput.addEventListener('change', function () {
                                        const feedback = document.getElementById('imaging_files_feedback');
                                        if (!feedback) return;
                                        feedback.innerHTML = '';
                                        const allowed = ['dcm', 'dicom', 'jpg', 'jpeg', 'png', 'webp', 'gif'];
                                        let validCount = 0;
                                        let invalidFiles = [];

                                        Array.from(this.files).forEach(file => {
                                            const ext = file.name.split('.').pop().toLowerCase();
                                            if (allowed.includes(ext)) {
                                                validCount++;
                                            } else {
                                                invalidFiles.push(file.name);
                                            }
                                        });

                                        if (invalidFiles.length > 0) {
                                            feedback.className = 'mt-2 alert alert-warning p-2 small mb-0 d-block';
                                            feedback.innerHTML = `<i class="bi bi-exclamation-triangle-fill me-1"></i>Warning: <strong>${invalidFiles.join(', ')}</strong> may not be a supported imaging format. Supported: DICOM, JPEG, PNG, WEBP.`;
                                        } else if (validCount > 0) {
                                            feedback.className = 'mt-2 alert alert-success p-2 small mb-0 d-block';
                                            feedback.innerHTML = `<i class="bi bi-check-circle-fill me-1"></i>Selected ${validCount} valid imaging study file(s).`;
                                        } else {
                                            feedback.className = 'mt-2 small text-start d-none';
                                        }
                                    });
                                }

                                if (docInput) {
                                    docInput.addEventListener('change', function () {
                                        const feedback = document.getElementById('document_files_feedback');
                                        if (!feedback) return;
                                        feedback.innerHTML = '';
                                        let validCount = 0;
                                        let invalidFiles = [];

                                        Array.from(this.files).forEach(file => {
                                            const ext = file.name.split('.').pop().toLowerCase();
                                            if (ext === 'pdf') {
                                                validCount++;
                                            } else {
                                                invalidFiles.push(file.name);
                                            }
                                        });

                                        if (invalidFiles.length > 0) {
                                            feedback.className = 'mt-2 alert alert-warning p-2 small mb-0 d-block';
                                            feedback.innerHTML = `<i class="bi bi-exclamation-triangle-fill me-1"></i>Supporting documents must be in PDF format.`;
                                        } else if (validCount > 0) {
                                            feedback.className = 'mt-2 alert alert-success p-2 small mb-0 d-block';
                                            feedback.innerHTML = `<i class="bi bi-check-circle-fill me-1"></i>Selected ${validCount} valid PDF document(s).`;
                                        } else {
                                            feedback.className = 'mt-2 small text-start d-none';
                                        }
                                    });
                                }
                            });
                        </script>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Technologist Observation Notes (Optional)</label>
                            <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="Note any study quality details, patient positioning, contrast agents used, etc."></textarea>
                        </div>

                        <div class="d-flex flex-wrap gap-2 pt-2 border-top">
                            <button type="submit" name="action" value="upload_only" class="btn btn-sm btn-primary">
                                <i class="bi bi-upload me-1"></i>Upload Study & Continue Procedure
                            </button>
                            <button type="submit" name="action" value="upload_complete" class="btn btn-sm btn-success" onclick="return confirm('Upload study file(s) and mark procedure as COMPLETED for Radiologist interpretation?');">
                                <i class="bi bi-check-circle-fill me-1"></i>Upload & Complete Procedure (Send to Radiologist)
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        {{-- Linked Diagnostic Report Summary --}}
        @if($radiologyRequest->report)
            <div class="card border-success shadow-sm">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-file-earmark-medical-fill me-2"></i>Diagnostic Report Summary</span>
                    <span class="badge bg-white text-success font-semibold">{{ $radiologyRequest->report->status }}</span>
                </div>
                <div class="card-body small">
                    <div class="mb-2">
                        <span class="text-muted">Radiologist:</span> <span class="fw-bold">{{ $radiologyRequest->report->radiologist->name }}</span>
                    </div>
                    <div class="mb-2">
                        <div class="text-muted fw-semibold">Impression:</div>
                        <div class="p-2 border rounded bg-light text-break" style="white-space: pre-line;">{{ Str::limit($radiologyRequest->report->impression, 250) }}</div>
                    </div>
                    <a href="{{ route('radiology.reports.show', $radiologyRequest->report) }}" class="btn btn-sm btn-success w-100 mt-2">
                        <i class="bi bi-eye-fill me-2"></i>View Full Diagnostic Report
                    </a>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
