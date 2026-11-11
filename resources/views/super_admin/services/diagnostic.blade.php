@extends('layouts.app')

@section('title', 'Diagnostic Services Configuration — Super Admin')
@section('page-title', 'Super Admin — Diagnostic Services Configuration')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('super-admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active" aria-current="page">Diagnostic Services Configuration</li>
@endsection

@section('content')
<div class="container-fluid px-0">
    {{-- Header Banner --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h5 class="fw-bold mb-1 text-dark">
                    <i class="bi bi-clipboard2-pulse text-primary me-2"></i> Diagnostic Services Configuration (LIS &amp; RIS)
                </h5>
                <p class="text-muted small mb-0">
                    Configure laboratory diagnostic test definitions, standard baseline pricing, and test lifecycle states (LIS) and monitor active radiology modalities (RIS).
                </p>
            </div>
            <div>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2">
                    <i class="bi bi-shield-check me-1"></i>Live LIS &amp; RIS Operational Data
                </span>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4 py-2" role="alert">
            <i class="bi bi-check-circle me-1"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show mb-4 py-2" role="alert">
            <i class="bi bi-exclamation-triangle me-1"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Tabs --}}
    <ul class="nav nav-pills mb-4 gap-2">
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'laboratory' ? 'active bg-primary text-white' : 'bg-white text-secondary shadow-sm' }} rounded-pill px-4 fw-semibold"
               href="{{ route('super-admin.services.diagnostic', ['tab' => 'laboratory']) }}">
                <i class="bi bi-eyedropper me-1"></i> Laboratory Diagnostic Tests (LIS)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'radiology' ? 'active bg-primary text-white' : 'bg-white text-secondary shadow-sm' }} rounded-pill px-4 fw-semibold"
               href="{{ route('super-admin.services.diagnostic', ['tab' => 'radiology']) }}">
                <i class="bi bi-activity me-1"></i> Radiology &amp; Imaging Modalities (RIS)
            </a>
        </li>
    </ul>

    @if($tab === 'laboratory')
    {{-- LIS Diagnostic Tests Table --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom py-3">
            <form method="GET" action="{{ route('super-admin.services.diagnostic') }}" class="row g-2 align-items-center">
                <input type="hidden" name="tab" value="laboratory">
                <div class="col-md-6">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" value="{{ request('search') }}" class="form-control border-start-0" placeholder="Search diagnostic tests by code or name...">
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-sm btn-primary w-100">Filter</button>
                </div>
                @if(request()->filled('search'))
                    <div class="col-md-2">
                        <a href="{{ route('super-admin.services.diagnostic', ['tab' => 'laboratory']) }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                    </div>
                @endif
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-uppercase">
                        <tr>
                            <th class="ps-3">Test Code</th>
                            <th>Diagnostic Test Name</th>
                            <th>Category</th>
                            <th>Specimen Type</th>
                            <th>Standard Price</th>
                            <th>Lifecycle Status</th>
                            <th class="pe-3 text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        @forelse($labTests as $test)
                            @php
                                $sideState = isset($sideStates) ? $sideStates->get($test->id) : null;
                                $lifecycleStatus = $sideState?->status ?? ($test->is_active ? 'active' : 'inactive');
                            @endphp
                            <tr>
                                <td class="ps-3 fw-bold font-monospace text-primary">{{ $test->code }}</td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $test->name }}</div>
                                    <div class="text-muted" style="font-size: 11px;">TAT: {{ $test->turnaround_hours ? $test->turnaround_hours . ' Hours' : 'Standard' }}</div>
                                </td>
                                <td><span class="badge bg-light text-dark border">{{ $test->category?->name ?? 'General' }}</span></td>
                                <td><i class="bi bi-droplet me-1 text-danger"></i>{{ $test->specimen_type ?? 'Blood' }}</td>
                                <td class="fw-bold text-success font-monospace">₱{{ number_format($test->price, 2) }}</td>
                                <td>
                                    @if($lifecycleStatus === 'active')
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill">Active</span>
                                    @elseif($lifecycleStatus === 'inactive')
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1 rounded-pill">Inactive</span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 rounded-pill">Retired</span>
                                    @endif
                                </td>
                                <td class="pe-3 text-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3"
                                            data-bs-toggle="modal" data-bs-target="#editLabTestModal-{{ $test->id }}">
                                        <i class="bi bi-pencil-square me-1"></i>Price &amp; Lifecycle
                                    </button>
                                </td>
                            </tr>

                            {{-- Modal: Update Diagnostic Test Lifecycle & Pricing --}}
                            <div class="modal fade" id="editLabTestModal-{{ $test->id }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <form action="{{ route('super-admin.catalog.lab-tests.lifecycle', $test) }}" method="POST" class="modal-content">
                                        @csrf
                                        @method('PATCH')
                                        <div class="modal-header border-bottom">
                                            <h6 class="modal-title fw-bold text-dark">
                                                <i class="bi bi-sliders text-primary me-2"></i>Configure Test: [{{ $test->code }}]
                                            </h6>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body small">
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Diagnostic Test Name</label>
                                                <input type="text" class="form-control form-control-sm" value="{{ $test->name }}" disabled>
                                            </div>
                                            <div class="row g-2 mb-3">
                                                <div class="col-6">
                                                    <label class="form-label fw-semibold">Category</label>
                                                    <input type="text" class="form-control form-control-sm" value="{{ $test->category?->name ?? 'General' }}" disabled>
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label fw-semibold">Specimen Type</label>
                                                    <input type="text" class="form-control form-control-sm" value="{{ $test->specimen_type ?? 'Blood' }}" disabled>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Standard Base Price (₱) <span class="text-danger">*</span></label>
                                                <input type="number" step="0.01" min="0" name="base_price" class="form-control form-control-sm"
                                                       value="{{ number_format($test->price, 2, '.', '') }}" required>
                                                <div class="form-text text-muted">Applied when physicians select this test in new laboratory requests.</div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Lifecycle Status <span class="text-danger">*</span></label>
                                                <select name="status" class="form-select form-select-sm" {{ $lifecycleStatus === 'retired' ? 'disabled' : '' }} required>
                                                    <option value="active" {{ $lifecycleStatus === 'active' ? 'selected' : '' }}>Active (Available for Physician Orders)</option>
                                                    <option value="inactive" {{ $lifecycleStatus === 'inactive' ? 'selected' : '' }}>Inactive (Temporarily Unavailable)</option>
                                                    <option value="retired" {{ $lifecycleStatus === 'retired' ? 'selected' : '' }}>Retired (Permanently Phased Out)</option>
                                                </select>
                                                <div class="form-text text-muted">Deactivating a test preserves all past clinical orders and test results.</div>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label fw-semibold text-danger">Mandatory Audit Justification <span class="text-danger">*</span></label>
                                                <input type="text" name="reason" class="form-control form-control-sm" placeholder="e.g. Annual fee schedule update or reagent shortage" required minlength="5">
                                            </div>
                                        </div>
                                        <div class="modal-footer border-top">
                                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-sm btn-primary">
                                                <i class="bi bi-check-circle me-1"></i>Save Configuration
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No diagnostic laboratory tests found matching criteria.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($labTests->hasPages())
            <div class="card-footer bg-white border-top py-2">
                {{ $labTests->links() }}
            </div>
        @endif
    </div>

    @else
    {{-- RIS Radiology Imaging Modalities Table --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom py-3">
            <h6 class="fw-bold mb-0 text-dark">
                <i class="bi bi-activity text-primary me-2"></i>Standardized Radiology &amp; Imaging Modalities (RIS)
            </h6>
        </div>

        <div class="p-3 bg-light border-bottom">
            <div class="d-flex align-items-center gap-2 text-muted small">
                <i class="bi bi-info-circle text-primary fs-5 flex-shrink-0"></i>
                <span>
                    <strong>RIS Operational Architecture:</strong> Radiology imaging requests operate through physician order entry with standardized modalities and anatomic specifications. Technologists and radiologists process and report cases directly within the RIS module. Procedure fees are not decoupled into an ad-hoc catalog.
                </span>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-uppercase">
                        <tr>
                            <th class="ps-3">Modality Code</th>
                            <th>Modality Classification</th>
                            <th>Clinical Scope &amp; Imaging Targets</th>
                            <th>Expected Turnaround</th>
                            <th>Active Workload</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        @foreach($risModalities as $modalityKey => $modalityData)
                            <tr>
                                <td class="ps-3 fw-bold font-monospace text-primary">{{ $modalityData['code'] }}</td>
                                <td class="fw-semibold text-dark">
                                    {{ $modalityData['name'] }}
                                </td>
                                <td>
                                    <small class="text-secondary">{{ $modalityData['description'] }}</small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">{{ $modalityData['turnaround'] }}</span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-primary border fw-semibold">
                                        {{ $modalityData['count'] }} Imaging Studies
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill">
                                        Operational
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection
