@extends('layouts.app')

@section('title', 'Treatment Services Configuration — Super Admin')
@section('page-title', 'Super Admin — Treatment Services Configuration')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('super-admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active" aria-current="page">Treatment Services Configuration</li>
@endsection

@section('content')
<div class="container-fluid px-0">
    {{-- Header Banner --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h5 class="fw-bold mb-1 text-dark">
                    <i class="bi bi-capsule text-warning me-2"></i> Treatment &amp; Therapeutic Services Configuration
                </h5>
                <p class="text-muted small mb-0">
                    Configure surgical procedures, operating theater allocations (SORS), pharmacy dispensing fees (PMS), and clinical dietary regimens (DNMS).
                </p>
            </div>
            <div>
                <a href="{{ route('super-admin.catalog.index') }}" class="btn btn-outline-primary rounded-pill px-3">
                    <i class="bi bi-journals me-1"></i> Open Master Catalog
                </a>
            </div>
        </div>
    </div>

    {{-- Tabs --}}
    <ul class="nav nav-pills mb-4 gap-2">
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'surgery' ? 'active bg-primary text-white' : 'bg-white text-secondary shadow-sm' }} rounded-pill px-4 fw-semibold"
               href="{{ route('super-admin.services.treatment', ['tab' => 'surgery']) }}">
                <i class="bi bi-heart-pulse me-1"></i> Surgical &amp; OR Procedures (SORS)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'pharmacy' ? 'active bg-primary text-white' : 'bg-white text-secondary shadow-sm' }} rounded-pill px-4 fw-semibold"
               href="{{ route('super-admin.services.treatment', ['tab' => 'pharmacy']) }}">
                <i class="bi bi-capsule me-1"></i> Pharmacy &amp; Medication Services (PMS)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'dietary' ? 'active bg-primary text-white' : 'bg-white text-secondary shadow-sm' }} rounded-pill px-4 fw-semibold"
               href="{{ route('super-admin.services.treatment', ['tab' => 'dietary']) }}">
                <i class="bi bi-apple me-1"></i> Dietary &amp; Nutrition Services (DNMS)
            </a>
        </li>
    </ul>

    @if($tab === 'surgery')
    {{-- Operating Rooms Status Sub-card --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-door-open me-2 text-primary"></i>Operating Theaters &amp; Surgery Suites</h6>
            <span class="badge bg-primary-subtle text-primary">{{ count($operatingRooms) }} Active Theaters</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-uppercase">
                        <tr>
                            <th class="ps-3">Room Name</th>
                            <th>Code</th>
                            <th>Location</th>
                            <th>Specialization</th>
                            <th>Current Status</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        @forelse($operatingRooms as $or)
                            <tr>
                                <td class="ps-3 fw-bold text-dark">{{ $or->name }}</td>
                                <td class="font-monospace text-primary">{{ $or->code ?? 'OR-' . $or->id }}</td>
                                <td>{{ $or->location ?? 'Main Surgery Complex' }}</td>
                                <td>{{ $or->specialization ?? 'General & Laparoscopic' }}</td>
                                <td>
                                    <span class="badge bg-success-subtle text-success px-2 py-1 rounded-pill">
                                        <i class="bi bi-check-circle me-1"></i>Operational
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-3 text-muted">No operating rooms registered.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    {{-- Treatment Services Table --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom py-3">
            <form method="GET" action="{{ route('super-admin.services.treatment') }}" class="row g-2 align-items-center">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <div class="col-md-6">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" value="{{ request('search') }}" class="form-control border-start-0" placeholder="Search {{ strtoupper($activeModule) }} treatment services...">
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-sm btn-primary w-100">Filter</button>
                </div>
                @if(request()->filled('search'))
                    <div class="col-md-2">
                        <a href="{{ route('super-admin.services.treatment', ['tab' => $tab]) }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                    </div>
                @endif
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-uppercase">
                        <tr>
                            <th class="ps-3">Service Code</th>
                            <th>Service Description</th>
                            <th>Module</th>
                            <th>Category</th>
                            <th>Base Procedure Fee</th>
                            <th>Status</th>
                            <th class="pe-3 text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        @forelse($services as $svc)
                            <tr>
                                <td class="ps-3 fw-bold font-monospace text-primary">{{ $svc->code }}</td>
                                <td class="fw-semibold text-dark">{{ $svc->name }}</td>
                                <td><span class="badge bg-secondary-subtle text-secondary">{{ $svc->module }}</span></td>
                                <td>{{ $svc->category ?? 'General' }}</td>
                                <td class="fw-bold text-success">₱{{ number_format($svc->base_price, 2) }}</td>
                                <td>
                                    <span class="badge bg-{{ $svc->status === 'active' ? 'success' : 'secondary' }}-subtle text-{{ $svc->status === 'active' ? 'success' : 'secondary' }} px-2 py-1 rounded-pill">
                                        {{ ucfirst($svc->status) }}
                                    </span>
                                </td>
                                <td class="pe-3 text-end">
                                    <button class="btn btn-sm btn-outline-secondary rounded-pill px-2" disabled>
                                        <i class="bi bi-gear"></i> Managed
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <p class="mb-1">No services defined under {{ $activeModule }} yet.</p>
                                    <a href="{{ route('super-admin.catalog.index') }}" class="btn btn-sm btn-outline-primary rounded-pill">
                                        <i class="bi bi-plus-circle me-1"></i> Add {{ $activeModule }} Service in Master Catalog
                                    </a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($services->hasPages())
            <div class="card-footer bg-white border-top py-2">
                {{ $services->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
