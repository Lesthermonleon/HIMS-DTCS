@extends('layouts.app')

@section('title', 'Master Diagnostic and Services Catalog')
@section('page-title', 'Super Admin — Master Services Catalog')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('super-admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active" aria-current="page">Master Catalog</li>
@endsection

@section('content')
<div class="container-fluid px-0">
    {{-- Header Banner & Notice --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h5 class="fw-bold mb-1 text-dark"><i class="bi bi-journals text-primary me-2"></i> Master Clinical Services & Diagnostics Catalog</h5>
                <p class="text-muted small mb-0">
                    Maintain standardized service definitions, diagnostic test codes, and baseline pricing across LIS, RIS, PMS, SORS, and DNMS. Definitions are distinct from individual clinical orders or patient results.
                </p>
            </div>
            <div>
                <button type="button" class="btn btn-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#createServiceModal">
                    <i class="bi bi-plus-circle me-1"></i> Add Service Definition
                </button>
            </div>
        </div>
    </div>

    {{-- Tabs --}}
    <ul class="nav nav-pills mb-4 gap-2">
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'services' ? 'active bg-primary text-white' : 'bg-white text-secondary shadow-sm' }} rounded-pill px-4 fw-semibold"
               href="{{ route('super-admin.catalog.index', ['tab' => 'services']) }}">
                <i class="bi bi-grid-fill me-1"></i> Clinical Services (LIS/RIS/PMS/SORS/DNMS)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'lab_tests' ? 'active bg-primary text-white' : 'bg-white text-secondary shadow-sm' }} rounded-pill px-4 fw-semibold"
               href="{{ route('super-admin.catalog.index', ['tab' => 'lab_tests']) }}">
                <i class="bi bi-clipboard2-pulse-fill me-1"></i> Laboratory Diagnostic Tests (LIS)
            </a>
        </li>
    </ul>

    @if($tab === 'services')
    {{-- ── TAB 1: MASTER CLINICAL SERVICES ── --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom py-3">
            <form method="GET" action="{{ route('super-admin.catalog.index') }}" class="row g-2 align-items-center">
                <input type="hidden" name="tab" value="services">
                <div class="col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" value="{{ request('search') }}" class="form-control border-start-0" placeholder="Search by service code or name...">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="module" class="form-select form-select-sm">
                        <option value="">All Clinical Modules</option>
                        @foreach(['LIS','RIS','PMS','SORS','DNMS'] as $mod)
                            <option value="{{ $mod }}" {{ request('module') === $mod ? 'selected' : '' }}>{{ $mod }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Lifecycle Statuses</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                        <option value="retired" {{ request('status') === 'retired' ? 'selected' : '' }}>Retired (Terminal)</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary w-100">Filter</button>
                    @if(request()->hasAny(['search', 'module', 'status']))
                        <a href="{{ route('super-admin.catalog.index', ['tab' => 'services']) }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                    @endif
                </div>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-uppercase">
                        <tr>
                            <th class="ps-3">Code</th>
                            <th>Service Name</th>
                            <th>Module</th>
                            <th>Category</th>
                            <th>Base Price</th>
                            <th>Version</th>
                            <th>Status</th>
                            <th class="pe-3 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        @forelse($services as $svc)
                            <tr>
                                <td class="ps-3 fw-bold font-monospace text-primary">{{ $svc->code }}</td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $svc->name }}</div>
                                    @if($svc->description)
                                        <div class="text-muted text-truncate" style="max-width: 250px;">{{ $svc->description }}</div>
                                    @endif
                                </td>
                                <td><span class="badge bg-primary-subtle text-primary border">{{ $svc->module }}</span></td>
                                <td>{{ $svc->category ?? 'General' }}</td>
                                <td class="font-monospace fw-semibold">{{ $svc->base_price ? '₱ ' . number_format($svc->base_price, 2) : '—' }}</td>
                                <td><span class="badge bg-secondary-subtle text-secondary font-monospace">v{{ $svc->version }}</span></td>
                                <td>
                                    @if($svc->status === 'active')
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>
                                    @elseif($svc->status === 'inactive')
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle">Inactive</span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Retired</span>
                                    @endif
                                </td>
                                <td class="pe-3 text-end">
                                    @if($svc->status !== 'retired')
                                        <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2"
                                                data-bs-toggle="modal" data-bs-target="#editServiceModal-{{ $svc->id }}">
                                            Edit
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2"
                                                data-bs-toggle="modal" data-bs-target="#statusModal-{{ $svc->id }}">
                                            Status
                                        </button>
                                    @else
                                        <span class="text-muted small">Locked</span>
                                    @endif
                                </td>
                            </tr>

                            {{-- Edit Service Modal --}}
                            <div class="modal fade" id="editServiceModal-{{ $svc->id }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <form action="{{ route('super-admin.catalog.services.update', $svc) }}" method="POST" class="modal-content">
                                        @csrf
                                        @method('PUT')
                                        <div class="modal-header">
                                            <h6 class="modal-title fw-bold">Edit Service Definition [{{ $svc->code }}]</h6>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body small">
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Service Name</label>
                                                <input type="text" name="name" value="{{ $svc->name }}" class="form-control form-control-sm" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Category</label>
                                                <input type="text" name="category" value="{{ $svc->category }}" class="form-control form-control-sm">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Base Price (PHP)</label>
                                                <input type="number" step="0.01" name="base_price" value="{{ $svc->base_price }}" class="form-control form-control-sm">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Description</label>
                                                <textarea name="description" class="form-control form-control-sm" rows="2">{{ $svc->description }}</textarea>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold text-danger">Audit Reason for Change *</label>
                                                <input type="text" name="reason" class="form-control form-control-sm" placeholder="Specify clinical/administrative reason for updating" required minlength="5">
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-sm btn-primary">Save Changes & Bump Version</button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            {{-- Status Change Modal --}}
                            <div class="modal fade" id="statusModal-{{ $svc->id }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <form action="{{ route('super-admin.catalog.services.status', $svc) }}" method="POST" class="modal-content">
                                        @csrf
                                        @method('PATCH')
                                        <div class="modal-header">
                                            <h6 class="modal-title fw-bold">Update Lifecycle Status [{{ $svc->code }}]</h6>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body small">
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Target Status</label>
                                                <select name="status" class="form-select form-select-sm" required>
                                                    <option value="active" {{ $svc->status === 'active' ? 'selected' : '' }}>Active</option>
                                                    <option value="inactive" {{ $svc->status === 'inactive' ? 'selected' : '' }}>Inactive</option>
                                                    <option value="retired" {{ $svc->status === 'retired' ? 'selected' : '' }}>Retired (Permanent Terminal State)</option>
                                                </select>
                                                <div class="form-text text-danger">Warning: Retiring a service makes it permanently read-only. Existing historical orders preserve this service.</div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold text-danger">Mandatory Reason for Status Change *</label>
                                                <input type="text" name="reason" class="form-control form-control-sm" placeholder="Reason for activation/deactivation/retirement" required minlength="5">
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-sm btn-danger">Confirm Status Update</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    No clinical service definitions match your search criteria.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($services->hasPages())
                <div class="p-3 border-top">
                    {{ $services->links() }}
                </div>
            @endif
        </div>
    </div>

    @else
    {{-- ── TAB 2: LIS DIAGNOSTIC TESTS (Live lab_tests table + catalog_item_states) ── --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom py-3">
            <form method="GET" action="{{ route('super-admin.catalog.index') }}" class="row g-2 align-items-center">
                <input type="hidden" name="tab" value="lab_tests">
                <div class="col-md-6">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="lab_search" value="{{ request('lab_search') }}" class="form-control border-start-0" placeholder="Search diagnostic test code or name...">
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-sm btn-primary w-100">Search LIS Tests</button>
                </div>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-uppercase">
                        <tr>
                            <th class="ps-3">Test Code</th>
                            <th>Diagnostic Test Name</th>
                            <th>LIS Category</th>
                            <th>Base Price</th>
                            <th>Lifecycle Status</th>
                            <th class="pe-3 text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        @forelse($labTests as $test)
                            @php
                                $state = $sideStates->get($test->id);
                                $status = $state?->status ?? ($test->is_active ? 'active' : 'inactive');
                            @endphp
                            <tr>
                                <td class="ps-3 fw-bold font-monospace text-primary">{{ $test->code }}</td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $test->name }}</div>
                                    <div class="text-muted" style="font-size: 11px;">Specimen: {{ $test->specimen_type ?? 'N/A' }}</div>
                                </td>
                                <td>{{ $test->category?->name ?? 'Uncategorized' }}</td>
                                <td class="font-monospace fw-semibold">₱ {{ number_format($test->price ?? 0, 2) }}</td>
                                <td>
                                    @if($status === 'active')
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>
                                    @elseif($status === 'inactive')
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle">Inactive</span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Retired</span>
                                    @endif
                                </td>
                                <td class="pe-3 text-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2"
                                            data-bs-toggle="modal" data-bs-target="#editLabTestModal-{{ $test->id }}">
                                        Lifecycle
                                    </button>
                                </td>
                            </tr>

                            {{-- Lab Test Lifecycle Modal --}}
                            <div class="modal fade" id="editLabTestModal-{{ $test->id }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <form action="{{ route('super-admin.catalog.lab-tests.lifecycle', $test) }}" method="POST" class="modal-content">
                                        @csrf
                                        @method('PATCH')
                                        <div class="modal-header">
                                            <h6 class="modal-title fw-bold">Update LIS Test Lifecycle [{{ $test->code }}]</h6>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body small">
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Test Name</label>
                                                <input type="text" class="form-control form-control-sm" value="{{ $test->name }}" disabled>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Lifecycle Status</label>
                                                <select name="status" class="form-select form-select-sm" required>
                                                    <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Active (Available for ordering)</option>
                                                    <option value="inactive" {{ $status === 'inactive' ? 'selected' : '' }}>Inactive (Temporarily suspended)</option>
                                                    <option value="retired" {{ $status === 'retired' ? 'selected' : '' }}>Retired (Permanently decommissioned)</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Baseline Test Price (PHP)</label>
                                                <input type="number" step="0.01" name="base_price" value="{{ $test->price }}" class="form-control form-control-sm" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold text-danger">Mandatory Reason for Change *</label>
                                                <input type="text" name="reason" class="form-control form-control-sm" placeholder="Specify clinical/administrative reason" required minlength="5">
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-sm btn-primary">Save Lifecycle Update</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    No diagnostic tests found in the laboratory information system.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($labTests->hasPages())
                <div class="p-3 border-top">
                    {{ $labTests->links() }}
                </div>
            @endif
        </div>
    </div>
    @endif

    {{-- Create Service Definition Modal --}}
    <div class="modal fade" id="createServiceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form action="{{ route('super-admin.catalog.services.store') }}" method="POST" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h6 class="modal-title fw-bold">Register Master Service Definition</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body small">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Standard Service Code *</label>
                        <input type="text" name="code" class="form-control form-control-sm font-monospace text-uppercase" placeholder="e.g. SRV-XR-CHEST" required>
                        <div class="form-text">Must be unique across the hospital master catalog.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Service Name *</label>
                        <input type="text" name="name" class="form-control form-control-sm" placeholder="e.g. Chest Radiograph PA/Lateral" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Governing Module *</label>
                            <select name="module" class="form-select form-select-sm" required>
                                <option value="LIS">Laboratory (LIS)</option>
                                <option value="RIS">Radiology (RIS)</option>
                                <option value="PMS">Pharmacy (PMS)</option>
                                <option value="SORS">Surgical (SORS)</option>
                                <option value="DNMS">Diet & Nutrition (DNMS)</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Category</label>
                            <input type="text" name="category" class="form-control form-control-sm" placeholder="e.g. Diagnostic Imaging">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Baseline Price (PHP)</label>
                        <input type="number" step="0.01" name="base_price" class="form-control form-control-sm" placeholder="0.00">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Service Description</label>
                        <textarea name="description" class="form-control form-control-sm" rows="2" placeholder="Clinical purpose or service scope"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Create Service Definition</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
