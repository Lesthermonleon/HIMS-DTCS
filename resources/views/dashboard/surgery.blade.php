@extends('layouts.app')
@section('title', 'Surgery Dashboard')
@section('page-title', 'Surgery & OR Dashboard')
@section('breadcrumb')
    <li class="breadcrumb-item active">Surgery Dashboard</li>
@endsection

@section('content')

@php
    $currentUser     = auth()->user();
    $isOrStaffOnly   = $currentUser && $currentUser->hasRole('or-staff') && ! $currentUser->hasAnyRole(['or-coordinator', 'doctor', 'admin', 'super_admin']);
    $canViewRequests = $currentUser?->can('viewAny', \App\Models\SurgeryRequest::class) ?? false;
    $reqIndexUrl     = $canViewRequests ? route('surgery.requests.index') : route('surgery.schedules.index');
    $reqPendingUrl   = $canViewRequests ? route('surgery.requests.index', ['status' => 'Pending']) : route('surgery.schedules.index', ['status' => 'Scheduled']);
    $reqCompletedUrl = $canViewRequests ? route('surgery.requests.index', ['status' => 'Completed']) : route('surgery.schedules.index', ['status' => 'Completed']);
@endphp

{{-- ── Quick Action Bar ── --}}
<div class="card mb-4 border-0 shadow-sm bg-body workspace-header-card">
    <div class="card-body p-3 workspace-header-body">
        <div class="workspace-header-title">
            <span class="badge bg-danger bg-opacity-10 text-danger fs-6 px-3 py-2">
                <i class="bi bi-scissors me-1"></i>
                {{ $isOrStaffOnly ? 'OR Staff Assigned Cases & Readiness Center' : 'OR Coordinator Operational Center' }}
            </span>
            <span class="text-muted small d-none d-md-inline dashboard-description">
                {{ $isOrStaffOnly
                    ? 'Monitor assigned surgical cases and update operating-room preparation and readiness status.'
                    : 'Manage operating room allocations, surgical scheduling, and preoperative requests.' }}
            </span>
        </div>
        <div class="workspace-header-actions">
            @can('viewAny', \App\Models\SurgeryRequest::class)
            <a href="{{ route('surgery.requests.index') }}" class="btn btn-sm btn-primary">
                <i class="bi bi-file-earmark-plus me-1"></i> Surgery Requests
            </a>
            @endcan
            @if(Route::has('surgery.schedules.index'))
            <a href="{{ route('surgery.schedules.index') }}" class="btn btn-sm btn-outline-danger">
                <i class="bi bi-calendar3 me-1"></i> {{ $isOrStaffOnly ? 'Assigned OR Schedules' : 'OR Master Schedule' }}
            </a>
            @endif
        </div>
    </div>
</div>

{{-- ── Stat Cards ── --}}
@if($isOrStaffOnly)
<div class="balanced-grid balanced-grid-5 mb-4">
    {{-- Card 1: Assigned Cases --}}
    <a href="{{ route('surgery.schedules.index') }}" class="text-decoration-none">
        <div class="card border-0 shadow-sm h-100 card-hover-elevate transition-all">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Assigned Cases</span>
                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-2">
                        <i class="bi bi-calendar2-check fs-5"></i>
                    </div>
                </div>
                <h3 class="fw-bold text-dark mb-0">{{ number_format($stats['assigned_cases'] ?? $stats['assigned_total'] ?? 0) }}</h3>
                <div class="small text-primary mt-2">
                    View Assigned <i class="bi bi-arrow-right"></i>
                </div>
            </div>
        </div>
    </a>

    {{-- Card 2: Upcoming Procedures --}}
    <a href="{{ route('surgery.schedules.index') }}" class="text-decoration-none">
        <div class="card border-0 shadow-sm h-100 card-hover-elevate transition-all">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Upcoming Procedures</span>
                    <div class="bg-info bg-opacity-10 text-info rounded-circle p-2">
                        <i class="bi bi-calendar3 fs-5"></i>
                    </div>
                </div>
                <h3 class="fw-bold text-dark mb-0">{{ number_format($stats['upcoming_procedures'] ?? 0) }}</h3>
                <div class="small text-info mt-2">
                    Scheduled Next <i class="bi bi-arrow-right"></i>
                </div>
            </div>
        </div>
    </a>

    {{-- Card 3: Preparation Tasks --}}
    <a href="{{ route('surgery.schedules.index') }}" class="text-decoration-none">
        <div class="card border-0 shadow-sm h-100 card-hover-elevate transition-all">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Preparation Tasks</span>
                    <div class="bg-warning bg-opacity-10 text-warning rounded-circle p-2">
                        <i class="bi bi-tools fs-5"></i>
                    </div>
                </div>
                <h3 class="fw-bold text-dark mb-0">{{ number_format($stats['preparation_tasks'] ?? 0) }}</h3>
                <div class="small text-warning mt-2">
                    Room Preparation <i class="bi bi-arrow-right"></i>
                </div>
            </div>
        </div>
    </a>

    {{-- Card 4: Pending Readiness Updates --}}
    <a href="{{ route('surgery.schedules.index') }}" class="text-decoration-none">
        <div class="card border-0 shadow-sm h-100 card-hover-elevate transition-all">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Pending Readiness Updates</span>
                    <div class="bg-danger bg-opacity-10 text-danger rounded-circle p-2">
                        <i class="bi bi-hourglass-split fs-5"></i>
                    </div>
                </div>
                <h3 class="fw-bold text-dark mb-0">{{ number_format($stats['pending_readiness_updates'] ?? $stats['readiness_pending_prep'] ?? 0) }}</h3>
                <div class="small text-danger mt-2">
                    Update Checklist <i class="bi bi-arrow-right"></i>
                </div>
            </div>
        </div>
    </a>

    {{-- Card 5: Ready Cases --}}
    <a href="{{ route('surgery.schedules.index') }}" class="text-decoration-none">
        <div class="card border-0 shadow-sm h-100 card-hover-elevate transition-all">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Ready Cases</span>
                    <div class="bg-success bg-opacity-10 text-success rounded-circle p-2">
                        <i class="bi bi-check2-circle fs-5"></i>
                    </div>
                </div>
                <h3 class="fw-bold text-dark mb-0">{{ number_format($stats['ready_cases'] ?? $stats['readiness_ready'] ?? 0) }}</h3>
                <div class="small text-success mt-2">
                    Cleared for OR <i class="bi bi-arrow-right"></i>
                </div>
            </div>
        </div>
    </a>
</div>
@else
<div class="balanced-grid balanced-grid-6 mb-4">
    {{-- Card 1: Pending Surgery Requests --}}
    <a href="{{ $reqPendingUrl }}" class="text-decoration-none">
        <div class="card border-0 shadow-sm h-100 card-hover-elevate transition-all">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Pending Surgery Requests</span>
                    <div class="bg-warning bg-opacity-10 text-warning rounded-circle p-2">
                        <i class="bi bi-hourglass-split fs-5"></i>
                    </div>
                </div>
                <h3 class="fw-bold text-dark mb-0">{{ number_format($stats['pending_requests'] ?? $stats['pending']) }}</h3>
                <div class="small text-warning mt-2">
                    Schedule Pending <i class="bi bi-arrow-right"></i>
                </div>
            </div>
        </div>
    </a>

    {{-- Card 2: Scheduled Operations --}}
    @if(Route::has('surgery.schedules.index'))
    <a href="{{ route('surgery.schedules.index') }}" class="text-decoration-none">
    @else
    <a href="{{ $reqIndexUrl }}" class="text-decoration-none">
    @endif
        <div class="card border-0 shadow-sm h-100 card-hover-elevate transition-all">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Scheduled Operations</span>
                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-2">
                        <i class="bi bi-calendar3 fs-5"></i>
                    </div>
                </div>
                <h3 class="fw-bold text-dark mb-0">{{ number_format($stats['scheduled'] ?? 0) }}</h3>
                <div class="small text-primary mt-2">
                    Master Schedule <i class="bi bi-arrow-right"></i>
                </div>
            </div>
        </div>
    </a>

    {{-- Card 3: Today's Surgeries --}}
    <a href="{{ route('surgery.calendar') }}" class="text-decoration-none">
        <div class="card border-0 shadow-sm h-100 card-hover-elevate transition-all">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Today's Surgeries</span>
                    <div class="bg-info bg-opacity-10 text-info rounded-circle p-2">
                        <i class="bi bi-calendar-day fs-5"></i>
                    </div>
                </div>
                <h3 class="fw-bold text-dark mb-0">{{ number_format($stats['today_surgeries'] ?? $stats['today_scheduled'] ?? 0) }}</h3>
                <div class="small text-info mt-2">
                    OR Calendar <i class="bi bi-arrow-right"></i>
                </div>
            </div>
        </div>
    </a>

    {{-- Card 4: Operations In Progress --}}
    <a href="{{ route('surgery.schedules.index', ['status' => 'In Progress']) }}" class="text-decoration-none">
        <div class="card border-0 shadow-sm h-100 card-hover-elevate transition-all">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Operations In Progress</span>
                    <div class="bg-danger bg-opacity-10 text-danger rounded-circle p-2">
                        <i class="bi bi-scissors fs-5"></i>
                    </div>
                </div>
                <h3 class="fw-bold text-dark mb-0">{{ number_format($stats['in_progress'] ?? 0) }}</h3>
                <div class="small text-danger mt-2">
                    Active Procedures <i class="bi bi-arrow-right"></i>
                </div>
            </div>
        </div>
    </a>

    {{-- Card 5: Available Operating Rooms --}}
    <a href="#or-availability-section" class="text-decoration-none">
        <div class="card border-0 shadow-sm h-100 card-hover-elevate transition-all">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Available Operating Rooms</span>
                    <div class="bg-success bg-opacity-10 text-success rounded-circle p-2">
                        <i class="bi bi-door-open-fill fs-5"></i>
                    </div>
                </div>
                <h3 class="fw-bold text-dark mb-0">{{ number_format($stats['available_rooms'] ?? 0) }}</h3>
                <div class="small text-success mt-2">
                    Room Availability <i class="bi bi-arrow-right"></i>
                </div>
            </div>
        </div>
    </a>

    {{-- Card 6: Cases Requiring Readiness Updates --}}
    <a href="#or-readiness-section" class="text-decoration-none">
        <div class="card border-0 shadow-sm h-100 card-hover-elevate transition-all">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Cases Requiring Readiness Updates</span>
                    <div class="bg-secondary bg-opacity-10 text-secondary rounded-circle p-2">
                        <i class="bi bi-clipboard-check fs-5"></i>
                    </div>
                </div>
                <h3 class="fw-bold text-dark mb-0">{{ number_format($stats['cases_requiring_readiness'] ?? 0) }}</h3>
                <div class="small text-secondary mt-2">
                    Review Readiness <i class="bi bi-arrow-right"></i>
                </div>
            </div>
        </div>
    </a>
</div>
@endif

{{-- ── 3. Main OR Schedule & Requests Queue Row ── --}}
<div class="row g-3 mb-4">
    @if(!$isOrStaffOnly)
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-scissors me-2 text-danger"></i>Surgery Requests Queue</h6>
                @can('viewAny', \App\Models\SurgeryRequest::class)
                <a href="{{ route('surgery.requests.index') }}" class="btn btn-sm btn-outline-primary">
                    View All <i class="bi bi-arrow-right ms-1"></i>
                </a>
                @endcan
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Request No</th>
                                <th>Patient</th>
                                <th>Procedure</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($recentRequests as $req)
                            <tr>
                                <td>
                                    <a href="{{ route('surgery.requests.show', $req) }}" class="fw-semibold text-decoration-none">
                                        {{ $req->request_no }}
                                    </a>
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $req->patient->last_name }}, {{ $req->patient->first_name }}</div>
                                </td>
                                <td><span class="text-muted small">{{ $req->surgery_type ?? 'General Surgery' }}</span></td>
                                <td>
                                    <span class="badge bg-{{ $req->status === 'Completed' ? 'success' : ($req->status === 'Scheduled' ? 'info' : ($req->status === 'Cancelled' ? 'danger' : 'warning text-dark')) }}">
                                        {{ $req->status }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="table-actions justify-content-end">
                                        <a href="{{ route('surgery.requests.show', $req) }}" class="table-action-btn action-view" title="Open Surgery Request" aria-label="Open Surgery Request">
                                            <i class="bi bi-folder2-open"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">
                                    <i class="bi bi-check2-circle fs-3 d-block mb-1 text-success"></i>
                                    No pending surgery requests.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    @endif
    <div class="{{ $isOrStaffOnly ? 'col-md-12' : 'col-md-6' }}">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-dark">
                    <i class="bi bi-calendar-check me-2 text-primary"></i>
                    {{ $isOrStaffOnly ? 'Assigned OR Cases & Preparation Readiness' : 'Upcoming OR Master Schedule' }}
                </h6>
                @if(Route::has('surgery.schedules.index'))
                <a href="{{ route('surgery.schedules.index') }}" class="btn btn-sm btn-outline-primary">
                    View All <i class="bi bi-arrow-right ms-1"></i>
                </a>
                @endif
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Schedule / OR</th>
                                <th>Patient / Procedure</th>
                                <th>Date & Time</th>
                                <th>OR Readiness</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($upcomingSchedules as $sch)
                            @php
                                $rStatus = $sch->readiness_status ?? 'Pending Prep';
                                $rBadge  = $rStatus === 'Ready' ? 'success' : ($rStatus === 'Preparing' ? 'warning text-dark' : 'secondary');
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('surgery.schedules.show', $sch) }}" class="fw-semibold text-decoration-none">
                                        {{ $sch->schedule_no }}
                                    </a>
                                    <div class="small text-muted">{{ $sch->operatingRoom->name ?? 'OR' }}</div>
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $sch->surgeryRequest->patient->last_name ?? 'N/A' }}, {{ $sch->surgeryRequest->patient->first_name ?? '' }}</div>
                                    <div class="small text-muted">{{ $sch->surgeryRequest->procedure_name ?? 'Procedure' }}</div>
                                </td>
                                <td class="text-muted small">{{ $sch->scheduled_at?->format('M d, H:i') ?? '—' }}</td>
                                <td>
                                    <span class="badge bg-{{ $rBadge }}">{{ $rStatus }}</span>
                                </td>
                                <td>
                                    <span class="badge bg-{{ $sch->status === 'Completed' ? 'success' : ($sch->status === 'Scheduled' ? 'info' : 'secondary') }}">
                                        {{ $sch->status }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="table-actions justify-content-end">
                                        <a href="{{ route('surgery.schedules.show', $sch) }}" class="table-action-btn action-view" title="View Schedule & Update Readiness" aria-label="View Schedule & Update Readiness">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    <i class="bi bi-calendar-event fs-3 d-block mb-1 text-info"></i>
                                    {{ $isOrStaffOnly ? 'No assigned surgical cases.' : 'No upcoming surgeries scheduled.' }}
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── 4. Operational Metrics & Overview Row ── --}}
<div class="row g-3 mb-4">
    {{-- Status Donut --}}
    <div class="col-md-4">
        <div class="chart-card h-100">
            <div class="chart-header">
                <div>
                    <h3 class="chart-title"><i class="bi bi-pie-chart-fill me-2 text-success"></i>Surgery Status Distribution</h3>
                    <p class="chart-subtitle">Status breakdown across pending, scheduled, completed, and cancelled</p>
                </div>
            </div>
            <div class="chart-container" style="height: 260px;">
                <canvas id="surgStatusDonut"></canvas>
            </div>
        </div>
    </div>

    {{-- Upcoming & Activity --}}
    <div class="col-md-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-dark">
                    <i class="bi bi-calendar-week me-2 text-primary"></i>OR Utilization &amp; Schedule Overview
                </h6>
                <span class="badge bg-light text-dark border px-2 py-1">Today &amp; Upcoming</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6 col-md-3">
                        <div class="text-center p-3 rounded-3 bg-info bg-opacity-10 border border-info border-opacity-25">
                            <div class="fs-3 fw-bold text-info">{{ $stats['today_scheduled'] }}</div>
                            <div class="small text-muted mt-1">Today's OR Cases</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-center p-3 rounded-3 bg-warning bg-opacity-10 border border-warning border-opacity-25">
                            <div class="fs-3 fw-bold text-warning-emphasis">{{ $stats['upcoming_7d'] }}</div>
                            <div class="small text-muted mt-1">Next 7 Days</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-center p-3 rounded-3 bg-success bg-opacity-10 border border-success border-opacity-25">
                            <div class="fs-3 fw-bold text-success">{{ $stats['completed'] }}</div>
                            <div class="small text-muted mt-1">Completed</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-center p-3 rounded-3 bg-danger bg-opacity-10 border border-danger border-opacity-25">
                            <div class="fs-3 fw-bold text-danger">{{ $stats['cancelled'] }}</div>
                            <div class="small text-muted mt-1">Cancelled</div>
                        </div>
                    </div>
                </div>
                @php $rate = $stats['total_requests'] > 0 ? round(($stats['completed'] / $stats['total_requests']) * 100) : 0; @endphp
                <div class="mt-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="small text-muted">Surgical Completion Rate</span>
                        <span class="small fw-bold text-success">{{ $rate }}%</span>
                    </div>
                    <div class="progress" style="--completion-rate: {{ $rate }}%; height: 8px;">
                        <div class="progress-bar bg-success" role="progressbar" style="width: var(--completion-rate);" aria-valuenow="{{ $rate }}" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script type="application/json" id="surg-chart-data">
    {"pending": {{ $stats['pending'] }}, "scheduled": {{ $stats['scheduled'] }}, "completed": {{ $stats['completed'] }}, "cancelled": {{ $stats['cancelled'] }}}
</script>

{{-- ── Surgery Analytics: Role-Specific Charts ── --}}
@if($isOrStaffOnly)
<div class="row g-4 mb-4">
    <div class="col-lg-5">
        <div class="chart-card h-100">
            <div class="chart-header">
                <div>
                    <h3 class="chart-title"><i class="bi bi-pie-chart-fill me-2 text-warning"></i>Readiness Status Distribution</h3>
                    <p class="chart-subtitle">Pending Prep, Preparing &amp; Ready cases</p>
                </div>
            </div>
            <div class="chart-container" style="height: 260px;">
                <canvas id="surgReadinessChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="chart-card h-100">
            <div class="chart-header">
                <div>
                    <h3 class="chart-title"><i class="bi bi-bar-chart-fill me-2 text-primary"></i>Weekly Assigned Workload</h3>
                    <p class="chart-subtitle">Assigned operating room procedures across the current week</p>
                </div>
            </div>
            <div class="chart-container" style="height: 260px;">
                <canvas id="surgWeeklyWorkloadChart"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    {{-- Cases Requiring Preparation --}}
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-tools me-2 text-warning"></i>Cases Requiring Preparation</h6>
                <span class="badge bg-warning-subtle text-warning-emphasis">{{ ($casesRequiringPreparation ?? collect())->count() }}</span>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    @forelse($casesRequiringPreparation ?? [] as $prepCase)
                        <div class="list-group-item p-3 border-bottom d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-bold text-dark small">{{ $prepCase->schedule_no }} &bull; {{ $prepCase->operatingRoom->name ?? 'OR' }}</div>
                                <div class="small text-muted">{{ $prepCase->surgeryRequest?->patient?->full_name ?? 'Patient' }} &bull; <span class="badge bg-light text-dark border">{{ $prepCase->readiness_status ?? 'Pending Prep' }}</span></div>
                            </div>
                            <a href="{{ route('surgery.schedules.show', $prepCase) }}" class="btn btn-sm btn-outline-warning py-0 px-2">Update Readiness</a>
                        </div>
                    @empty
                        <div class="text-center py-4 text-muted small">All assigned surgical cases are ready.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- Recent Readiness Updates --}}
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-clock-history me-2 text-info"></i>Recent Readiness Updates</h6>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    @forelse($recentSurgeryActivities ?? [] as $log)
                        <div class="list-group-item p-3 border-bottom">
                            <div class="d-flex justify-content-between align-items-start">
                                <span class="fw-semibold text-dark small">{{ $log->action }}</span>
                                <span class="small text-muted" style="font-size: 0.72rem;">{{ $log->logged_at?->diffForHumans() }}</span>
                            </div>
                            <div class="small text-muted">{{ Str::limit($log->description, 75) }}</div>
                        </div>
                    @empty
                        <div class="text-center py-4 text-muted small">No recent readiness updates logged.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@else
<div class="row g-4 mb-4">
    <div class="col-lg-7">
        <div class="chart-card h-100">
            <div class="chart-header">
                <div>
                    <h3 class="chart-title"><i class="bi bi-graph-up-arrow me-2 text-success"></i>Surgery Request Trend</h3>
                    <p class="chart-subtitle">Monthly surgery requests over time</p>
                </div>
                <select id="surgTrendPeriod" class="form-select form-select-sm" style="width:auto;">
                    <option value="6">Last 6 Months</option>
                    <option value="12">Last 12 Months</option>
                </select>
            </div>
            <div class="chart-container" style="height: 260px;">
                <canvas id="surgTrendChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="chart-card h-100">
            <div class="chart-header">
                <div>
                    <h3 class="chart-title"><i class="bi bi-building-check me-2 text-primary"></i>Operating Room Utilization</h3>
                    <p class="chart-subtitle">Scheduled &amp; completed procedures per OR</p>
                </div>
            </div>
            <div class="chart-container" style="height: 260px;">
                <canvas id="surgOrUtilizationChart"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    {{-- Room Availability --}}
    <div class="col-lg-4" id="or-availability-section">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-door-open me-2 text-success"></i>Room Availability</h6>
                <span class="badge bg-success-subtle text-success">{{ $stats['available_rooms'] ?? 0 }} Available</span>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    @forelse($operatingRooms ?? [] as $room)
                        <div class="list-group-item p-3 border-bottom d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-bold text-dark small">{{ $room->name }}</div>
                                <div class="small text-muted">{{ $room->location ?? 'Surgical Suite' }} &bull; {{ $room->schedules_count ?? $room->total_cases_count ?? 0 }} Cases</div>
                            </div>
                            <span class="badge {{ $room->is_active ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                {{ $room->is_active ? 'Available' : 'In Use / Maintenance' }}
                            </span>
                        </div>
                    @empty
                        <div class="text-center py-4 text-muted small">No operating rooms configured.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- OR Readiness Overview --}}
    <div class="col-lg-4" id="or-readiness-section">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-clipboard-check me-2 text-warning"></i>OR Readiness Overview</h6>
                <span class="badge bg-warning-subtle text-warning-emphasis">{{ ($casesRequiringReadiness ?? collect())->count() }} Pending</span>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    @forelse($casesRequiringReadiness ?? [] as $rCase)
                        <div class="list-group-item p-3 border-bottom d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-bold text-dark small">{{ $rCase->schedule_no }} &bull; {{ $rCase->operatingRoom->name ?? 'OR' }}</div>
                                <div class="small text-muted">{{ $rCase->surgeryRequest?->patient?->full_name ?? 'Patient' }} &bull; {{ $rCase->readiness_status ?? 'Pending Prep' }}</div>
                            </div>
                            <a href="{{ route('surgery.schedules.show', $rCase) }}" class="btn btn-sm btn-outline-primary py-0 px-2">View</a>
                        </div>
                    @empty
                        <div class="text-center py-4 text-muted small">All scheduled OR cases are marked Ready.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- Recent Scheduling Activities --}}
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-clock-history me-2 text-info"></i>Recent Scheduling Activities</h6>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    @forelse($recentSurgeryActivities ?? [] as $log)
                        <div class="list-group-item p-3 border-bottom">
                            <div class="d-flex justify-content-between align-items-start">
                                <span class="fw-semibold text-dark small">{{ $log->action }}</span>
                                <span class="small text-muted" style="font-size: 0.72rem;">{{ $log->logged_at?->diffForHumans() }}</span>
                            </div>
                            <div class="small text-muted">{{ Str::limit($log->description, 65) }}</div>
                        </div>
                    @empty
                        <div class="text-center py-4 text-muted small">No recent surgery scheduling activities logged.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endif

<script type="application/json" id="surg-trend-6m">@json($surgTrend6m)</script>
<script type="application/json" id="surg-trend-12m">@json($surgTrend12m)</script>
<script type="application/json" id="surg-or-utilization">@json($orUtilization ?? [])</script>
<script type="application/json" id="surg-readiness-breakdown">@json($readinessBreakdown ?? [])</script>
<script type="application/json" id="surg-weekly-workload">@json($weeklyAssignedWorkload ?? [])</script>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    function initSurgChart() {
        if (typeof Chart === 'undefined' || typeof window.HIMSChart === 'undefined') return;
        const jsonEl = document.getElementById('surg-chart-data');
        const canvas = document.getElementById('surgStatusDonut');
        if (!jsonEl || !canvas) return;

        const _d = JSON.parse(jsonEl.textContent);
        const total = (_d.pending || 0) + (_d.scheduled || 0) + (_d.completed || 0) + (_d.cancelled || 0);

        if (total > 0) {
            window.HIMSChart.createDoughnutChart(
                canvas,
                ['Pending', 'Scheduled', 'Completed', 'Cancelled'],
                [_d.pending, _d.scheduled, _d.completed, _d.cancelled],
                ['#2196F3', '#00838F', '#4CAF50', '#D32F2F']
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
        document.addEventListener('DOMContentLoaded', initSurgChart);
    } else {
        initSurgChart();
    }
})();
</script>
<script>
(function () {
    if (typeof window.HIMSChart === 'undefined') return;
    const t6  = JSON.parse(document.getElementById('surg-trend-6m').textContent);
    const t12 = JSON.parse(document.getElementById('surg-trend-12m').textContent);

    let surgTrendChart = null;
    function renderSurgTrend(data) {
        const ctx = document.getElementById('surgTrendChart');
        if (!ctx) return;
        if (surgTrendChart) { surgTrendChart.destroy(); surgTrendChart = null; }
        if (!data || data.length === 0) return;
        surgTrendChart = window.HIMSChart.createLineChart(ctx, 'Surgery Requests', data.map(d => d.label), data.map(d => d.total), 'requests');
    }
    renderSurgTrend(t6);
    const sel = document.getElementById('surgTrendPeriod');
    if (sel) sel.addEventListener('change', () => renderSurgTrend(sel.value === '12' ? t12 : t6));

    // OR Utilization Chart (Coordinator)
    const orEl = document.getElementById('surg-or-utilization');
    const orCtx = document.getElementById('surgOrUtilizationChart');
    if (orEl && orCtx && typeof Chart !== 'undefined') {
        const orData = JSON.parse(orEl.textContent || '[]');
        if (orData.length > 0) {
            new Chart(orCtx, {
                type: 'bar',
                data: {
                    labels: orData.map(r => r.label),
                    datasets: [{
                        label: 'Scheduled Cases',
                        data: orData.map(r => r.count),
                        backgroundColor: 'rgba(13, 148, 136, 0.85)',
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

    // Readiness Breakdown Chart (OR Staff)
    const rdyEl = document.getElementById('surg-readiness-breakdown');
    const rdyCtx = document.getElementById('surgReadinessChart');
    if (rdyEl && rdyCtx && typeof window.HIMSChart !== 'undefined') {
        const rdyData = JSON.parse(rdyEl.textContent || '[]');
        if (rdyData.some(r => r.count > 0)) {
            window.HIMSChart.createDoughnutChart(
                rdyCtx,
                rdyData.map(r => r.label),
                rdyData.map(r => r.count),
                ['#64748b', '#f59e0b', '#10b981']
            );
        }
    }

    // Weekly Workload Chart (OR Staff)
    const wkEl = document.getElementById('surg-weekly-workload');
    const wkCtx = document.getElementById('surgWeeklyWorkloadChart');
    if (wkEl && wkCtx && typeof Chart !== 'undefined') {
        const wkData = JSON.parse(wkEl.textContent || '[]');
        if (wkData.length > 0) {
            new Chart(wkCtx, {
                type: 'bar',
                data: {
                    labels: wkData.map(w => w.label),
                    datasets: [{
                        label: 'Assigned Cases',
                        data: wkData.map(w => w.total ?? 0),
                        backgroundColor: 'rgba(14, 165, 233, 0.85)',
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
