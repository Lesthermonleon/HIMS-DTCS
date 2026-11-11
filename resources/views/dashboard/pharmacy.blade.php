@extends('layouts.app')
@section('title', 'Pharmacy Dashboard')
@section('page-title', 'Pharmacy Dashboard')
@section('breadcrumb')
    <li class="breadcrumb-item active">Pharmacy Dashboard</li>
@endsection

@section('content')

{{-- ── Quick Action Bar & Date Filter ── --}}
<div class="card mb-4 border-0 shadow-sm bg-body workspace-header-card">
    <div class="card-body p-3 workspace-header-body">
        <div class="workspace-header-title">
            <span class="badge bg-success bg-opacity-10 text-success fs-6 px-3 py-2">
                <i class="bi bi-capsule me-1"></i> Pharmacist Operational Workspace
            </span>
            <span class="text-muted small d-none d-md-inline dashboard-description">Perform prescription verification, medication safety checks, POS billing, and patient dispensing.</span>
        </div>
        <div class="workspace-header-actions d-flex flex-wrap gap-2 align-items-center">
            <form method="GET" action="{{ route('pharmacy.dashboard') }}" class="d-flex align-items-center gap-1">
                <input type="date" name="date_from" value="{{ $dateFrom ?? '' }}" class="form-control form-control-sm" style="width: 135px;" title="Date From">
                <input type="date" name="date_to" value="{{ $dateTo ?? '' }}" class="form-control form-control-sm" style="width: 135px;" title="Date To">
                <button type="submit" class="btn btn-sm btn-outline-secondary" title="Filter Dashboard"><i class="bi bi-funnel"></i></button>
                @if(!empty($dateFrom) || !empty($dateTo))
                    <a href="{{ route('pharmacy.dashboard') }}" class="btn btn-sm btn-link text-decoration-none p-1">Reset</a>
                @endif
            </form>
            <a href="{{ route('pharmacy.prescriptions.index') }}" class="btn btn-sm btn-primary">
                <i class="bi bi-prescription2 me-1"></i> Prescriptions Queue
            </a>
            @if(Route::has('pharmacy.pos.index') && auth()->user()?->hasRole('pharmacist'))
            <a href="{{ route('pharmacy.pos.index') }}" class="btn btn-sm btn-success">
                <i class="bi bi-calculator me-1"></i> Point of Sale (POS)
            </a>
            @endif
            @if(Route::has('pharmacy.dispensing.index') && (auth()->user()?->hasRole('pharmacist') || auth()->user()?->hasRole('admin')))
            <a href="{{ route('pharmacy.dispensing.index') }}" class="btn btn-sm btn-outline-success">
                <i class="bi bi-bag-check me-1"></i> Dispensing Records
            </a>
            @endif
            @if(Route::has('pharmacy.inventory.items') && (auth()->user()?->hasRole('pharmacist') || auth()->user()?->hasRole('admin')))
            <a href="{{ route('pharmacy.inventory.items') }}" class="btn btn-sm btn-outline-dark">
                <i class="bi bi-boxes me-1"></i> Inventory
            </a>
            @endif
        </div>
    </div>
</div>

{{-- ── Operational & Stock Safety Alerts Banner ── --}}
@if(($stockStats['out_of_stock'] ?? 0) > 0 || ($stockStats['expired'] ?? 0) > 0 || ($stockStats['near_expiry'] ?? 0) > 0 || ($stockStats['low_stock'] ?? 0) > 0 || ($stats['pending_prescriptions'] ?? 0) > 0 || ($stats['pending_dispensing'] ?? 0) > 0)
<div class="row g-2 mb-4">
    @if(($stockStats['out_of_stock'] ?? 0) > 0 || ($stockStats['expired'] ?? 0) > 0)
        <div class="col-md-4">
            <div class="alert alert-danger mb-0 py-2 px-3 d-flex align-items-center justify-content-between small border-0 shadow-xs">
                <div>
                    <i class="bi bi-exclamation-octagon-fill me-1"></i>
                    <strong>Critical Stock &amp; Expiry Notice:</strong>
                    {{ $stockStats['out_of_stock'] ?? 0 }} Out-of-Stock, {{ $stockStats['expired'] ?? 0 }} Expired
                </div>
                @if(Route::has('pharmacy.inventory.items'))
                    <a href="{{ route('pharmacy.inventory.items', ['filter' => 'out']) }}" class="fw-bold text-danger text-decoration-underline ms-2">Inspect</a>
                @endif
            </div>
        </div>
    @endif
    @if(($stockStats['low_stock'] ?? 0) > 0 || ($stockStats['near_expiry'] ?? 0) > 0)
        <div class="col-md-4">
            <div class="alert alert-warning mb-0 py-2 px-3 d-flex align-items-center justify-content-between small border-0 shadow-xs">
                <div>
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    <strong>Stock &amp; Near-Expiry Watch:</strong>
                    {{ $stockStats['low_stock'] ?? 0 }} Low Stock Medicines, {{ $stockStats['near_expiry'] ?? 0 }} Near-Expiry
                </div>
                @if(Route::has('pharmacy.inventory.replenishment.index'))
                    <a href="{{ route('pharmacy.inventory.replenishment.index') }}" class="fw-bold text-dark text-decoration-underline ms-2">Demo Restock</a>
                @endif
            </div>
        </div>
    @endif
    @if(($stats['pending_prescriptions'] ?? 0) > 0 || ($stats['pending_dispensing'] ?? 0) > 0)
        <div class="col-md-4">
            <div class="alert alert-info mb-0 py-2 px-3 d-flex align-items-center justify-content-between small border-0 shadow-xs">
                <div>
                    <i class="bi bi-clipboard2-pulse-fill me-1"></i>
                    <strong>Clinical Queue Active:</strong>
                    {{ $stats['pending_prescriptions'] ?? 0 }} Awaiting Verification, {{ $stats['pending_dispensing'] ?? 0 }} Awaiting Dispensing
                </div>
                <a href="{{ route('pharmacy.prescriptions.index') }}" class="fw-bold text-info-emphasis text-decoration-underline ms-2">Queue</a>
            </div>
        </div>
    @endif
</div>
@endif

{{-- ── Stat Cards (6 Operational & POS Sales KPIs) ── --}}
<div class="row g-3 mb-4">
    {{-- Card 1: Pending Prescriptions --}}
    <div class="col-6 col-md-4 col-xl-2">
        <a href="{{ route('pharmacy.prescriptions.index', ['status' => 'Pending']) }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 card-hover-elevate transition-all">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-semibold text-uppercase">Pending Prescriptions</span>
                        <div class="bg-warning bg-opacity-10 text-warning rounded-circle p-2">
                            <i class="bi bi-hourglass-split fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold text-dark mb-0">{{ number_format($stats['pending_rx'] ?? $stats['pending_prescriptions']) }}</h3>
                    <div class="small text-warning mt-2">
                        New Orders <i class="bi bi-arrow-right"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>

    {{-- Card 2: Prescriptions for Verification --}}
    <div class="col-6 col-md-4 col-xl-2">
        <a href="{{ route('pharmacy.prescriptions.index', ['status' => 'Pending']) }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 card-hover-elevate transition-all">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-semibold text-uppercase">Prescriptions for Verification</span>
                        <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-2">
                            <i class="bi bi-shield-check fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold text-dark mb-0">{{ number_format($stats['for_verification'] ?? $stats['pending_prescriptions']) }}</h3>
                    <div class="small text-primary mt-2">
                        Pending Verification <i class="bi bi-arrow-right"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>

    {{-- Card 3: Pending Dispensing --}}
    <div class="col-6 col-md-4 col-xl-2">
        <a href="{{ route('pharmacy.prescriptions.index', ['status' => 'Verified']) }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 card-hover-elevate transition-all">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-semibold text-uppercase">Pending Dispensing</span>
                        <div class="bg-info bg-opacity-10 text-info rounded-circle p-2">
                            <i class="bi bi-box-arrow-right fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold text-dark mb-0">{{ number_format($stats['pending_dispensing'] ?? 0) }}</h3>
                    <div class="small text-info mt-2">
                        Awaiting Dispense <i class="bi bi-arrow-right"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>

    {{-- Card 4: Dispensed Today --}}
    <div class="col-6 col-md-4 col-xl-2">
        @if(Route::has('pharmacy.dispensing.index'))
        <a href="{{ route('pharmacy.dispensing.index') }}" class="text-decoration-none">
        @else
        <a href="{{ route('pharmacy.prescriptions.index') }}" class="text-decoration-none">
        @endif
            <div class="card border-0 shadow-sm h-100 card-hover-elevate transition-all">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-semibold text-uppercase">Dispensed Today</span>
                        <div class="bg-success bg-opacity-10 text-success rounded-circle p-2">
                            <i class="bi bi-bag-check-fill fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold text-dark mb-0">{{ number_format($stats['dispensed_today']) }}</h3>
                    <div class="small text-success mt-2">
                        View Dispensed <i class="bi bi-arrow-right"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>

    {{-- Card 5: Completed Prescriptions --}}
    <div class="col-6 col-md-4 col-xl-2">
        <a href="{{ route('pharmacy.prescriptions.index', ['status' => 'Dispensed']) }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 card-hover-elevate transition-all">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-semibold text-uppercase">Completed Prescriptions</span>
                        <div class="bg-secondary bg-opacity-10 text-secondary rounded-circle p-2">
                            <i class="bi bi-check2-all fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold text-dark mb-0">{{ number_format($stats['completed_prescriptions'] ?? $stats['dispensed_total'] ?? 0) }}</h3>
                    <div class="small text-secondary mt-2">
                        {{ number_format($stats['total_prescriptions'] ?? 0) }} Total Prescriptions <i class="bi bi-arrow-right"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>

    {{-- Card 6: Sales Today (POS) --}}
    <div class="col-6 col-md-4 col-xl-2">
        <a href="{{ Route::has('pharmacy.pos.index') && auth()->user()?->hasRole('pharmacist') ? route('pharmacy.pos.index') : route('reports.pharmacy.activity') }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 card-hover-elevate transition-all">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-semibold text-uppercase">Sales Today</span>
                        <div class="bg-success bg-opacity-10 text-success rounded-circle p-2">
                            <i class="bi bi-cash-coin fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold text-dark mb-0">₱{{ number_format($stats['sales_today'] ?? 0, 2) }}</h3>
                    <div class="small text-success mt-2">
                        {{ number_format($stats['sales_today_count'] ?? 0) }} POS Sales <i class="bi bi-arrow-right"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>
</div>

{{-- ── 3. Main Prescription Verification & Dispensing Work Queue Table ── --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-prescription2 me-2 text-success"></i>Prescription Verification &amp; Dispensing Queue</h6>
        <a href="{{ route('pharmacy.prescriptions.index') }}" class="btn btn-sm btn-outline-primary">
            View Full Queue <i class="bi bi-arrow-right ms-1"></i>
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Rx Number</th>
                        <th>Patient</th>
                        <th>Ordering Doctor</th>
                        <th>Status</th>
                        <th>Prescribed Date</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($pendingPrescriptionsList as $rx)
                    <tr>
                        <td>
                            <a href="{{ route('pharmacy.prescriptions.show', $rx) }}" class="fw-semibold text-decoration-none">
                                {{ $rx->prescription_no }}
                            </a>
                        </td>
                        <td>
                            <div class="fw-semibold">{{ $rx->patient->last_name }}, {{ $rx->patient->first_name }}</div>
                            <span class="text-muted small">Patient No: {{ $rx->patient->patient_no ?? 'N/A' }}</span>
                        </td>
                        <td>{{ $rx->doctor->name ?? 'Dr. Staff' }}</td>
                        <td>
                            <span class="badge bg-{{ $rx->status === 'Dispensed' ? 'success' : ($rx->status === 'Verified' ? 'primary' : 'warning text-dark') }}">
                                {{ $rx->status }}
                            </span>
                        </td>
                        <td class="text-muted small">{{ $rx->created_at->format('M d, Y H:i') }}</td>
                        <td class="text-end">
                            <div class="table-actions justify-content-end">
                                <a href="{{ route('pharmacy.prescriptions.show', $rx) }}" class="table-action-btn action-view" title="{{ $rx->status === 'Verified' ? 'Dispense Prescription' : 'Verify & Dispense Prescription' }}" aria-label="{{ $rx->status === 'Verified' ? 'Dispense Prescription' : 'Verify & Dispense Prescription' }}">
                                    <i class="bi bi-capsule"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">
                            <i class="bi bi-check2-circle fs-3 d-block mb-1 text-success"></i>
                            No pending prescriptions in queue. All prescriptions are verified/dispensed!
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ── 4. Pharmacy Stock Directory Summary Section (6 Stock & Expiry Scorecards) ── --}}
<div class="mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h6 class="fw-bold text-dark mb-1">
                <i class="bi bi-box-seam me-2 text-success"></i>Pharmacy Stock Directory
            </h6>
            <p class="text-muted small mb-0">Read-only medication stock status, lot/batch information, and expiration monitoring.</p>
        </div>
    </div>

    <div class="row g-3">
        {{-- Card 1: Total Monitored / Total Medicines --}}
        <div class="col-6 col-md-4 col-xl-2">
            <a href="{{ route('pharmacy.medicines.index') }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100 card-hover-elevate transition-all">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted small fw-semibold text-uppercase">Total Monitored</span>
                            <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-2">
                                <i class="bi bi-capsule fs-5"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold text-dark mb-0">{{ number_format($stockStats['total_monitored'] ?? 0) }}</h3>
                        <div class="small text-primary mt-2">
                            All Medicines <i class="bi bi-arrow-right"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        {{-- Card 2: In Stock / Available Stock --}}
        <div class="col-6 col-md-4 col-xl-2">
            <a href="{{ route('pharmacy.medicines.index', ['stock' => 'in_stock']) }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100 card-hover-elevate transition-all">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted small fw-semibold text-uppercase">In Stock</span>
                            <div class="bg-success bg-opacity-10 text-success rounded-circle p-2">
                                <i class="bi bi-check-circle-fill fs-5"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold text-dark mb-0">{{ number_format($stockStats['in_stock'] ?? 0) }}</h3>
                        <div class="small text-success mt-2">
                            Available Stock <i class="bi bi-arrow-right"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        {{-- Card 3: Low Stock Medicines --}}
        <div class="col-6 col-md-4 col-xl-2">
            <a href="{{ route('pharmacy.medicines.index', ['stock' => 'low']) }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100 card-hover-elevate transition-all">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted small fw-semibold text-uppercase">Low Stock</span>
                            <div class="bg-warning bg-opacity-10 text-warning rounded-circle p-2">
                                <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold text-dark mb-0">{{ number_format($stockStats['low_stock'] ?? 0) }}</h3>
                        <div class="small text-warning-emphasis mt-2">
                            Replenishment Needed <i class="bi bi-arrow-right"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        {{-- Card 4: Out-of-Stock Medicines --}}
        <div class="col-6 col-md-4 col-xl-2">
            <a href="{{ route('pharmacy.medicines.index', ['stock' => 'out']) }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100 card-hover-elevate transition-all">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted small fw-semibold text-uppercase">Out-of-Stock Medicines</span>
                            <div class="bg-danger bg-opacity-10 text-danger rounded-circle p-2">
                                <i class="bi bi-slash-circle-fill fs-5"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold text-dark mb-0">{{ number_format($stockStats['out_of_stock'] ?? 0) }}</h3>
                        <div class="small text-danger mt-2">
                            Stock Exhausted <i class="bi bi-arrow-right"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        {{-- Card 5: Near-Expiry Medicines --}}
        <div class="col-6 col-md-4 col-xl-2">
            <a href="{{ route('pharmacy.medicines.index', ['expiry' => 'expiring_soon']) }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100 card-hover-elevate transition-all">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted small fw-semibold text-uppercase">Near-Expiry Medicines</span>
                            <div class="bg-warning bg-opacity-10 text-warning rounded-circle p-2">
                                <i class="bi bi-hourglass-bottom fs-5"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold text-dark mb-0">{{ number_format($stockStats['near_expiry'] ?? 0) }}</h3>
                        <div class="small text-warning-emphasis mt-2">
                            Within 90 Days <i class="bi bi-arrow-right"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        {{-- Card 6: Attention Required / Expired Medicines --}}
        <div class="col-6 col-md-4 col-xl-2">
            <a href="{{ route('pharmacy.medicines.index', ['stock' => 'out']) }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100 card-hover-elevate transition-all">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted small fw-semibold text-uppercase">Attention Required</span>
                            <div class="bg-danger bg-opacity-10 text-danger rounded-circle p-2">
                                <i class="bi bi-x-circle-fill fs-5"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold text-dark mb-0">{{ number_format($stockStats['attention_required'] ?? 0) }}</h3>
                        <div class="small text-danger mt-2">
                            Out / Expired / Expiring ({{ $stockStats['expired'] ?? 0 }} Expired) <i class="bi bi-arrow-right"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    </div>
</div>

{{-- ── 5. Operational Metrics & Summary Row ── --}}
<div class="row g-3 mb-4">
    {{-- Prescription Funnel Donut --}}
    <div class="col-md-4">
        <div class="chart-card h-100">
            <div class="chart-header">
                <div>
                    <h3 class="chart-title"><i class="bi bi-pie-chart-fill me-2 text-success"></i>Prescription Pipeline</h3>
                    <p class="chart-subtitle">Status breakdown across pending, verified, and dispensed</p>
                </div>
            </div>
            <div class="chart-container" style="height: 260px;">
                <canvas id="rxPipelineDonut"></canvas>
            </div>
        </div>
    </div>

    {{-- Today's Summary & Payment Method Distribution --}}
    <div class="col-md-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-dark">
                    <i class="bi bi-calendar-day me-2 text-success"></i>Today's Operational &amp; Payment Summary
                </h6>
                <span class="badge bg-light text-dark border px-2 py-1">{{ now()->format('M d, Y') }}</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6 col-md-3">
                        <div class="text-center p-3 rounded-3 bg-success bg-opacity-10 border border-success border-opacity-25">
                            <div class="fs-3 fw-bold text-success">{{ $stats['dispensed_today'] }}</div>
                            <div class="small text-muted mt-1">Dispensed Today</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-center p-3 rounded-3 bg-info bg-opacity-10 border border-info border-opacity-25">
                            <div class="fs-3 fw-bold text-info">{{ $stats['verified_today'] }}</div>
                            <div class="small text-muted mt-1">Verified Today</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-center p-3 rounded-3 bg-warning bg-opacity-10 border border-warning border-opacity-25">
                            <div class="fs-3 fw-bold text-warning-emphasis">{{ $stats['pending_prescriptions'] }}</div>
                            <div class="small text-muted mt-1">Pending Queue</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-center p-3 rounded-3 bg-secondary bg-opacity-10 border border-secondary border-opacity-25">
                            <div class="fs-3 fw-bold text-dark">{{ $stats['pending_rate'] }}%</div>
                            <div class="small text-muted mt-1">Pending Rate</div>
                        </div>
                    </div>
                </div>
                @php $dispRate = $stats['total_prescriptions'] > 0 ? round(($stats['dispensed_total'] / $stats['total_prescriptions']) * 100) : 0; @endphp
                <div class="mt-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="small text-muted">Overall Dispensing Rate</span>
                        <span class="small fw-bold text-success">{{ $dispRate }}%</span>
                    </div>
                    <div class="progress" style="--completion-rate: {{ $dispRate }}%; height: 8px;">
                        <div class="progress-bar bg-success" role="progressbar" style="width: var(--completion-rate);" aria-valuenow="{{ $dispRate }}" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>

                {{-- Payment Method Distribution Mini-Grid --}}
                <div class="mt-3 pt-3 border-top">
                    <div class="small fw-bold text-muted text-uppercase mb-2">POS Payment Method Distribution</div>
                    <div class="row g-2 text-center">
                        @foreach($paymentBreakdown ?? ['Cash' => 0, 'GCash' => 0, 'Card' => 0, 'Hospital Billing' => 0] as $method => $amt)
                            <div class="col-6 col-sm-3">
                                <div class="p-2 rounded bg-light border">
                                    <div class="text-muted" style="font-size: 0.7rem;">{{ $method }}</div>
                                    <div class="fw-bold text-dark small">₱{{ number_format((float) $amt, 2) }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script type="application/json" id="rx-chart-data">
    {"pending": {{ $stats['pending_prescriptions'] }}, "verified": {{ $stats['verified'] }}, "dispensed": {{ $stats['dispensed_total'] }}}
</script>

{{-- ── Pharmacy Analytics: Prescription Trend + Medication Dispensing Trends ── --}}
<div class="row g-4 mb-4">
    <div class="col-lg-7">
        <div class="chart-card h-100">
            <div class="chart-header">
                <div>
                    <h3 class="chart-title"><i class="bi bi-graph-up-arrow me-2 text-success"></i>Prescription Trend</h3>
                    <p class="chart-subtitle">Monthly prescription activity over time</p>
                </div>
                <select id="rxTrendPeriod" class="form-select form-select-sm" style="width:auto;">
                    <option value="6">Last 6 Months</option>
                    <option value="12">Last 12 Months</option>
                </select>
            </div>
            <div class="chart-container" style="height: 260px;">
                <canvas id="rxTrendChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="chart-card h-100">
            <div class="chart-header">
                <div>
                    <h3 class="chart-title"><i class="bi bi-bar-chart-fill me-2 text-primary"></i>Medication Dispensing Trends</h3>
                    <p class="chart-subtitle">Daily dispensing events (last 7 days)</p>
                </div>
            </div>
            <div class="chart-container" style="height: 260px;">
                <canvas id="rxDispensingTrendChart"></canvas>
            </div>
        </div>
    </div>
</div>

{{-- ── Actionable Pharmacy Queues, POS Sales, & Expiry Watch ── --}}
<div class="row g-4 mb-4">
    {{-- Pending Prescription Verification --}}
    <div class="col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-shield-check me-2 text-warning"></i>Pending Verification</h6>
                <span class="badge bg-warning-subtle text-warning-emphasis">{{ ($pendingVerificationQueue ?? collect())->count() }}</span>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    @forelse($pendingVerificationQueue ?? [] as $prx)
                        <div class="list-group-item p-3 border-bottom d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-bold text-dark small">{{ $prx->prescription_no }} &bull; {{ $prx->patient->full_name ?? 'Patient' }}</div>
                                <div class="small text-muted">{{ $prx->doctor->name ?? 'Doctor' }}</div>
                            </div>
                            <a href="{{ route('pharmacy.prescriptions.show', $prx) }}" class="btn btn-sm btn-outline-warning py-0 px-2">Verify</a>
                        </div>
                    @empty
                        <div class="text-center py-4 text-muted small">No prescriptions awaiting verification.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- Prescriptions Awaiting Dispensing --}}
    <div class="col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-box-arrow-right me-2 text-info"></i>Awaiting Dispensing</h6>
                <span class="badge bg-info-subtle text-info">{{ ($awaitingDispensingQueue ?? collect())->count() }}</span>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    @forelse($awaitingDispensingQueue ?? [] as $drx)
                        <div class="list-group-item p-3 border-bottom d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-bold text-dark small">{{ $drx->prescription_no }} &bull; {{ $drx->patient->full_name ?? 'Patient' }}</div>
                                <div class="small text-muted">{{ $drx->status }}</div>
                            </div>
                            <a href="{{ route('pharmacy.prescriptions.show', $drx) }}" class="btn btn-sm btn-outline-info py-0 px-2">Dispense</a>
                        </div>
                    @empty
                        <div class="text-center py-4 text-muted small">No verified prescriptions awaiting dispensing.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- Recent Dispensing Activities --}}
    <div class="col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-bag-check-fill me-2 text-success"></i>Recent Dispensing Activities</h6>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    @forelse($recentDispensing ?? [] as $disp)
                        <div class="list-group-item p-3 border-bottom">
                            <div class="d-flex justify-content-between align-items-start">
                                <span class="fw-semibold text-dark small">{{ $disp->dispensing_no ?? 'DISP-'.$disp->id }}</span>
                                <span class="small text-muted" style="font-size: 0.72rem;">{{ $disp->dispensed_at?->diffForHumans() ?? 'Recently' }}</span>
                            </div>
                            <div class="small text-muted">
                                {{ $disp->prescription?->patient?->full_name ?? 'Patient' }} &bull; By {{ $disp->pharmacist?->name ?? 'Pharmacist' }}
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-4 text-muted small">No recent dispensing records.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- Recent POS Transactions --}}
    <div class="col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-receipt me-2 text-primary"></i>Recent POS Sales</h6>
                @if(Route::has('pharmacy.pos.index') && auth()->user()?->hasRole('pharmacist'))
                    <a href="{{ route('pharmacy.pos.index') }}" class="small text-decoration-none">POS</a>
                @endif
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    @forelse($recentPosTransactions ?? [] as $ptxn)
                        <div class="list-group-item p-3 border-bottom d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-bold text-dark small">{{ $ptxn->transaction_no }}</div>
                                <div class="small text-muted">{{ $ptxn->customer_name ?? 'Walk-In' }} ({{ $ptxn->payment_method }})</div>
                            </div>
                            <span class="fw-bold text-success small">₱{{ number_format((float) $ptxn->grand_total, 2) }}</span>
                        </div>
                    @empty
                        <div class="text-center py-4 text-muted small">No POS transactions recorded yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

<script type="application/json" id="rx-trend-6m">@json($rxTrend6m)</script>
<script type="application/json" id="rx-trend-12m">@json($rxTrend12m)</script>
<script type="application/json" id="rx-dispensing-7d">@json($dispensingTrend7d ?? [])</script>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    function initRxChart() {
        if (typeof Chart === 'undefined' || typeof window.HIMSChart === 'undefined') return;
        const jsonEl = document.getElementById('rx-chart-data');
        const canvas = document.getElementById('rxPipelineDonut');
        if (!jsonEl || !canvas) return;

        const _d = JSON.parse(jsonEl.textContent);
        const total = (_d.pending || 0) + (_d.verified || 0) + (_d.dispensed || 0);

        if (total > 0) {
            window.HIMSChart.createDoughnutChart(
                canvas,
                ['Pending', 'Verified', 'Dispensed'],
                [_d.pending, _d.verified, _d.dispensed],
                ['#2196F3', '#009688', '#4CAF50']
            );
        } else {
            window.HIMSChart.createDoughnutChart(
                canvas,
                ['No Data'],
                [1],
                ['#9E9E9E']
            );
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initRxChart);
    } else {
        initRxChart();
    }
})();
</script>
<script>
(function () {
    if (typeof window.HIMSChart === 'undefined') return;
    const t6  = JSON.parse(document.getElementById('rx-trend-6m').textContent);
    const t12 = JSON.parse(document.getElementById('rx-trend-12m').textContent);

    let rxTrendChart = null;
    function renderRxTrend(data) {
        const ctx = document.getElementById('rxTrendChart');
        if (!ctx) return;
        if (rxTrendChart) { rxTrendChart.destroy(); rxTrendChart = null; }
        if (!data || data.length === 0) return;
        rxTrendChart = window.HIMSChart.createLineChart(ctx, 'Prescriptions', data.map(d => d.label), data.map(d => d.total), 'prescriptions');
    }
    renderRxTrend(t6);
    const sel = document.getElementById('rxTrendPeriod');
    if (sel) sel.addEventListener('change', () => renderRxTrend(sel.value === '12' ? t12 : t6));

    const dispEl = document.getElementById('rx-dispensing-7d');
    const dispCtx = document.getElementById('rxDispensingTrendChart');
    if (dispEl && dispCtx && typeof Chart !== 'undefined') {
        const dispData = JSON.parse(dispEl.textContent || '[]');
        if (dispData.length > 0) {
            new Chart(dispCtx, {
                type: 'bar',
                data: {
                    labels: dispData.map(d => d.label),
                    datasets: [{
                        label: 'Dispensed Events',
                        data: dispData.map(d => d.total ?? 0),
                        backgroundColor: 'rgba(16, 185, 129, 0.85)',
                        borderRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                }
            });
        }
    }
})();
</script>
@endpush
