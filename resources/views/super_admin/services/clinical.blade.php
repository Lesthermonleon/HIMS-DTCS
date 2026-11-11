@extends('layouts.app')

@section('title', 'Clinical Services Configuration — Super Admin')
@section('page-title', 'Super Admin — Clinical Services Configuration')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('super-admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active" aria-current="page">Clinical Services Configuration</li>
@endsection

@section('content')
<div class="container-fluid px-0">
    {{-- Header Banner --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h5 class="fw-bold mb-1 text-dark">
                    <i class="bi bi-heart-pulse text-danger me-2"></i> Clinical Services & Consultation Configuration
                </h5>
                <p class="text-muted small mb-0">
                    Configure outpatient consultation types, clinical appointment rules, baseline fee schedules, and clinical department assignments.
                </p>
            </div>
            <div>
                <button type="button" class="btn btn-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#createClinicalServiceModal">
                    <i class="bi bi-plus-circle me-1"></i> Add Consultation Service
                </button>
            </div>
        </div>
    </div>

    {{-- Clinical Departments Overview --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle p-3 bg-danger bg-opacity-10 text-danger">
                            <i class="bi bi-hospital fs-4"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Assigned Clinical Departments</div>
                            <h4 class="fw-bold mb-0 text-dark">{{ count($clinicalDepts) }}</h4>
                        </div>
                    </div>
                    <div class="mt-3 pt-2 border-top small text-muted">
                        General Medicine, Emergency Care, Nutrition
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle p-3 bg-primary bg-opacity-10 text-primary">
                            <i class="bi bi-calendar-check fs-4"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Configured Consultation Types</div>
                            <h4 class="fw-bold mb-0 text-dark">{{ count($consultationTypes) }}</h4>
                        </div>
                    </div>
                    <div class="mt-3 pt-2 border-top small text-muted">
                        Active in scheduling engine & queues
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle p-3 bg-success bg-opacity-10 text-success">
                            <i class="bi bi-cash-stack fs-4"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Standard Consultation Base Fee</div>
                            <h4 class="fw-bold mb-0 text-dark">₱500.00</h4>
                        </div>
                    </div>
                    <div class="mt-3 pt-2 border-top small text-muted">
                        Baseline pricing for primary encounters
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Consultation Services Table --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom py-3">
            <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-list-check me-2 text-primary"></i>Configured Consultation & Clinical Services</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-uppercase">
                        <tr>
                            <th class="ps-3">Service Code</th>
                            <th>Service Name</th>
                            <th>Appointment Type</th>
                            <th>Department Assignment</th>
                            <th>Default Duration</th>
                            <th>Base Fee</th>
                            <th>Status</th>
                            <th class="pe-3 text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        @foreach($consultationTypes as $type)
                            <tr>
                                <td class="ps-3 fw-bold font-monospace text-primary">{{ $type['code'] }}</td>
                                <td class="fw-semibold text-dark">{{ $type['name'] }}</td>
                                <td><span class="badge bg-light text-dark border">{{ $type['type'] }}</span></td>
                                <td><i class="bi bi-building me-1 text-muted"></i>{{ $type['department'] }}</td>
                                <td><i class="bi bi-clock me-1 text-muted"></i>{{ $type['default_duration'] }} mins</td>
                                <td class="fw-bold text-success">₱{{ number_format($type['base_fee'], 2) }}</td>
                                <td>
                                    <span class="badge bg-success-subtle text-success px-2 py-1 rounded-pill">
                                        <i class="bi bi-check-circle me-1"></i>Active
                                    </span>
                                </td>
                                <td class="pe-3 text-end">
                                    <button class="btn btn-sm btn-outline-secondary rounded-pill px-2" disabled title="Standard Core Clinical Service">
                                        <i class="bi bi-lock-fill"></i> Core
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-light py-2 text-muted small">
            <i class="bi bi-info-circle me-1 text-primary"></i>
            Clinical service definitions govern outpatient scheduling slots and consultation booking rules across clinic departments.
        </div>
    </div>
</div>

{{-- Add Clinical Service Modal (Informative placeholder for additive extension) --}}
<div class="modal fade" id="createClinicalServiceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2 text-primary"></i>Add Clinical Consultation Service</h5>
                <button type="button" class="btn-close" data-bs-toggle="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body small">
                <div class="alert alert-info py-2">
                    <i class="bi bi-shield-check me-1"></i>
                    Clinical consultation services are synchronized with the <code>AppointmentScheduler</code> engine.
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Service Code</label>
                    <input type="text" class="form-control form-control-sm" placeholder="e.g. CLIN-TEL-06" readonly value="CLIN-SPEC-06">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Service Name</label>
                    <input type="text" class="form-control form-control-sm" placeholder="e.g. Cardiology Specialist Consultation">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Clinical Department</label>
                    <select class="form-select form-select-sm">
                        <option value="General Medicine">General Medicine</option>
                        <option value="Emergency Department">Emergency Department</option>
                        <option value="Nutrition & Dietetics">Nutrition & Dietetics</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Consultation Fee (₱)</label>
                    <input type="number" class="form-control form-control-sm" value="500.00" step="0.01">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-sm btn-primary" data-bs-dismiss="modal">Save Service Definition</button>
            </div>
        </div>
    </div>
</div>
@endsection
