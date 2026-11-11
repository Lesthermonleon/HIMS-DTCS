@extends('layouts.app')
@section('title', 'Doctor Dashboard')
@section('page-title', 'Doctor Clinical Dashboard')

@push('styles')
<style>
    /* Clinical Card and Tile Styling */
    .clinical-action-tile {
        transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        cursor: pointer;
        border: 1px solid var(--line, #e2e8f0);
        background-color: var(--card, #ffffff);
        text-decoration: none !important;
    }
    .clinical-action-tile:hover,
    .clinical-action-tile:focus-visible {
        border-color: #0d9488 !important;
        background-color: #f0fdfa !important;
        box-shadow: 0 6px 16px rgba(13, 148, 136, 0.12) !important;
        transform: translateY(-2px);
    }
    .clinical-action-tile:hover .tile-icon,
    .clinical-action-tile:focus-visible .tile-icon {
        background-color: #0d9488 !important;
        color: #ffffff !important;
    }

    .card-hover-elevate {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .card-hover-elevate:hover {
        transform: translateY(-3px);
        box-shadow: 0 .5rem 1.5rem rgba(0,0,0,.08)!important;
    }

    /* Urgency & Severity Badges */
    .severity-pill-critical {
        background-color: #fee2e2;
        color: #b91c1c;
        border: 1px solid #fca5a5;
        font-weight: 700;
        letter-spacing: 0.03em;
        text-transform: uppercase;
    }
    .severity-pill-urgent {
        background-color: #fef3c7;
        color: #b45309;
        border: 1px solid #fcd34d;
        font-weight: 700;
        letter-spacing: 0.03em;
        text-transform: uppercase;
    }
    .severity-pill-info {
        background-color: #e0f2fe;
        color: #0369a1;
        border: 1px solid #bae6fd;
        font-weight: 600;
    }

    /* Dark Theme Overrides */
    html[data-theme="dark"] .bg-white {
        background-color: var(--card, #171717) !important;
    }
    html[data-theme="dark"] .card-header.bg-white {
        background-color: var(--card, #171717) !important;
        border-bottom-color: var(--line, #262626) !important;
    }
    html[data-theme="dark"] .list-group-item {
        background-color: var(--card, #171717) !important;
        color: var(--text, #e2e8f0) !important;
        border-color: var(--line, #262626) !important;
    }
    html[data-theme="dark"] .table-light,
    html[data-theme="dark"] .table-light th {
        background-color: #1a1a1a !important;
        color: #e2e8f0 !important;
        border-bottom-color: var(--line, #262626) !important;
    }
    html[data-theme="dark"] .clinical-action-tile {
        background-color: var(--card, #171717) !important;
        border-color: var(--line, #262626) !important;
    }
    html[data-theme="dark"] .clinical-action-tile:hover,
    html[data-theme="dark"] .clinical-action-tile:focus-visible {
        background-color: rgba(20, 184, 166, 0.12) !important;
        border-color: #14b8a6 !important;
    }
    html[data-theme="dark"] .text-dark {
        color: #f8fafc !important;
    }
    html[data-theme="dark"] .border-light-subtle {
        border-color: var(--line, #262626) !important;
    }
    html[data-theme="dark"] .severity-pill-critical {
        background-color: rgba(239, 68, 68, 0.2);
        color: #fca5a5;
        border-color: rgba(239, 68, 68, 0.4);
    }
    html[data-theme="dark"] .severity-pill-urgent {
        background-color: rgba(245, 158, 11, 0.2);
        color: #fcd34d;
        border-color: rgba(245, 158, 11, 0.4);
    }
    html[data-theme="dark"] .severity-pill-info {
        background-color: rgba(14, 165, 233, 0.2);
        color: #7dd3fc;
        border-color: rgba(14, 165, 233, 0.4);
    }
</style>
@endpush

@section('content')

{{-- ── 1. Doctor Clinical Header & Shift Info ── --}}
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-2 border-bottom">
    <div>
        <h4 class="fw-bold text-dark mb-1 d-flex align-items-center">
            <i class="bi bi-person-badge me-2 text-primary"></i>
            Clinical Workspace — Dr. {{ Auth::user()->name }}
        </h4>
        <div class="small text-muted d-flex align-items-center flex-wrap gap-2">
            <span><i class="bi bi-hospital me-1"></i>{{ Auth::user()->department ?? 'Attending Physician' }}</span>
            <span>&bull;</span>
            <span><i class="bi bi-shield-check me-1 text-success"></i>DTCS Authorized Practitioner</span>
            <span>&bull;</span>
            <span><i class="bi bi-clock me-1"></i>{{ now()->format('l, F j, Y — g:i A') }}</span>
        </div>
    </div>
    <div class="d-flex align-items-center gap-2 mt-2 mt-md-0">
        <a href="{{ route('medisense.index') }}" class="btn btn-sm btn-outline-teal d-flex align-items-center gap-1 shadow-sm" style="border-color: #0d9488; color: #0d9488;">
            <i class="bi bi-cpu-fill"></i>
            <span>MediSense AI</span>
        </a>
        <a href="{{ route('patients.index') }}" class="btn btn-sm btn-primary d-flex align-items-center gap-1 shadow-sm">
            <i class="bi bi-folder2-open"></i>
            <span>Patient Directory</span>
        </a>
    </div>
</div>

{{-- ── 2. Clinical Overview Cards (Section A) ── --}}
<div class="balanced-grid balanced-grid-6 mb-4">
    {{-- Card 1: My Patients --}}
    <a href="{{ route('patients.index') }}" class="text-decoration-none">
        <div class="card border-0 shadow-sm h-100 card-hover-elevate">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">My Patients</span>
                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-2">
                        <i class="bi bi-people-fill fs-5"></i>
                    </div>
                </div>
                <h3 class="fw-bold text-dark mb-0">{{ number_format($stats['my_patients']) }}</h3>
                <div class="small text-primary mt-2 d-flex align-items-center">
                    Authorized Records <i class="bi bi-arrow-right ms-1"></i>
                </div>
            </div>
        </div>
    </a>

    {{-- Card 2: Pending Tasks --}}
    <a href="#pending-tasks-section" class="text-decoration-none">
        <div class="card border-0 shadow-sm h-100 card-hover-elevate">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Pending Tasks</span>
                    <div class="bg-warning bg-opacity-10 text-warning rounded-circle p-2">
                        <i class="bi bi-hourglass-split fs-5"></i>
                    </div>
                </div>
                <h3 class="fw-bold text-dark mb-0">{{ number_format($stats['pending_tasks']) }}</h3>
                <div class="small text-warning mt-2 d-flex align-items-center">
                    Action Required <i class="bi bi-arrow-right ms-1"></i>
                </div>
            </div>
        </div>
    </a>

    {{-- Card 3: Upcoming Appointments --}}
    <a href="{{ route('admin.appointments.index') }}" class="text-decoration-none">
        <div class="card border-0 shadow-sm h-100 card-hover-elevate">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Upcoming Appointments</span>
                    <div class="bg-success bg-opacity-10 text-success rounded-circle p-2">
                        <i class="bi bi-calendar-check-fill fs-5"></i>
                    </div>
                </div>
                <h3 class="fw-bold text-dark mb-0">{{ number_format($stats['upcoming_appointments'] ?? 0) }}</h3>
                <div class="small text-success mt-2 d-flex align-items-center">
                    Appointments Queue <i class="bi bi-arrow-right ms-1"></i>
                </div>
            </div>
        </div>
    </a>

    {{-- Card 4: Pending Lab Results --}}
    <a href="{{ route('lab.results.index') }}" class="text-decoration-none">
        <div class="card border-0 shadow-sm h-100 card-hover-elevate">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Pending Lab Results</span>
                    <div class="bg-info bg-opacity-10 text-info rounded-circle p-2">
                        <i class="bi bi-clipboard2-pulse-fill fs-5"></i>
                    </div>
                </div>
                <h3 class="fw-bold text-dark mb-0">{{ number_format($stats['pending_lab_results'] ?? 0) }}</h3>
                <div class="small text-info mt-2 d-flex align-items-center">
                    {{ number_format($stats['lab_awaiting'] ?? 0) }} Released <i class="bi bi-arrow-right ms-1"></i>
                </div>
            </div>
        </div>
    </a>

    {{-- Card 5: Pending Imaging Reports --}}
    <a href="{{ route('radiology.reports.index') }}" class="text-decoration-none">
        <div class="card border-0 shadow-sm h-100 card-hover-elevate">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Pending Imaging Reports</span>
                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-2">
                        <i class="bi bi-activity fs-5"></i>
                    </div>
                </div>
                <h3 class="fw-bold text-dark mb-0">{{ number_format($stats['pending_imaging_reports'] ?? 0) }}</h3>
                <div class="small text-primary mt-2 d-flex align-items-center">
                    {{ number_format($stats['rad_awaiting'] ?? 0) }} Released <i class="bi bi-arrow-right ms-1"></i>
                </div>
            </div>
        </div>
    </a>

    {{-- Card 6: Active Clinical Requests --}}
    <a href="#pending-tasks-section" class="text-decoration-none">
        <div class="card border-0 shadow-sm h-100 card-hover-elevate">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Active Clinical Requests</span>
                    <div class="bg-danger bg-opacity-10 text-danger rounded-circle p-2">
                        <i class="bi bi-clipboard-data-fill fs-5"></i>
                    </div>
                </div>
                <h3 class="fw-bold text-dark mb-0">{{ number_format($stats['active_clinical_requests'] ?? $stats['pending_tasks']) }}</h3>
                <div class="small {{ ($stats['critical_alerts'] ?? 0) > 0 ? 'text-danger fw-bold' : 'text-muted' }} mt-2 d-flex align-items-center">
                    {{ ($stats['critical_alerts'] ?? 0) > 0 ? $stats['critical_alerts'] . ' Critical Alerts' : ($stats['upcoming_surgeries'] . ' OR Schedules') }} <i class="bi bi-arrow-right ms-1"></i>
                </div>
            </div>
        </div>
    </a>
</div>

{{-- ── 3. Critical Alerts (Section B) & Pending Tasks (Section C) ── --}}
<div class="row g-4 mb-4">
    {{-- Section B: Critical Clinical Alerts --}}
    <div class="col-lg-6" id="critical-alerts-section">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0 fw-bold text-danger d-flex align-items-center">
                        <i class="bi bi-bell-fill me-2"></i>Critical Clinical Alerts
                    </h6>
                    <small class="text-muted d-block mt-1" style="font-size: 0.8rem;">
                        Clinically triaged diagnostic findings for your patients
                    </small>
                </div>
                <div class="d-flex gap-1">
                    @if($stats['critical_alerts'] > 0)
                        <span class="badge bg-danger text-white px-2 py-1">
                            {{ $stats['critical_alerts'] }} Critical
                        </span>
                    @endif
                    @if($stats['urgent_alerts'] > 0)
                        <span class="badge bg-warning text-dark px-2 py-1">
                            {{ $stats['urgent_alerts'] }} Urgent
                        </span>
                    @endif
                    @if($stats['critical_alerts'] === 0 && $stats['urgent_alerts'] === 0)
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                            Clear
                        </span>
                    @endif
                </div>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    @forelse($sortedAlerts->take(5) as $alert)
                        <div class="list-group-item p-3 border-bottom">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                        @if($alert['severity'] === 'Critical')
                                            <span class="badge severity-pill-critical px-2 py-1">
                                                <i class="bi bi-exclamation-triangle-fill me-1"></i>Critical
                                            </span>
                                        @elseif($alert['severity'] === 'Urgent')
                                            <span class="badge severity-pill-urgent px-2 py-1">
                                                <i class="bi bi-clock-history me-1"></i>Urgent
                                            </span>
                                        @else
                                            <span class="badge severity-pill-info px-2 py-1">
                                                <i class="bi bi-info-circle me-1"></i>Routine
                                            </span>
                                        @endif

                                        <span class="badge bg-light text-dark border">{{ $alert['source'] }}</span>
                                        <h6 class="mb-0 fw-bold text-dark">{{ $alert['patient']?->full_name ?? 'Authorized Patient' }}</h6>
                                        @if($alert['patient']?->patient_no)
                                            <small class="text-muted">({{ $alert['patient']->patient_no }})</small>
                                        @endif
                                    </div>
                                    <p class="small text-muted mb-1">
                                        <strong>{{ $alert['title'] }}</strong> &bull; Finding:
                                        <span class="{{ $alert['severity'] === 'Critical' ? 'text-danger fw-bold' : ($alert['severity'] === 'Urgent' ? 'text-warning fw-semibold' : 'text-dark') }}">
                                            {{ Str::limit($alert['finding'], 65) }}
                                        </span>
                                    </p>
                                    @if(!empty($alert['remarks']))
                                        <div class="small text-secondary fst-italic mb-1" style="font-size: 0.78rem;">
                                            <i class="bi bi-chat-left-text me-1"></i>Remarks: {{ Str::limit($alert['remarks'], 70) }}
                                        </div>
                                    @endif
                                    <div class="small text-muted" style="font-size: 0.75rem;">
                                        <i class="bi bi-clock me-1"></i>{{ $alert['date']?->diffForHumans() ?? 'Recently' }}
                                    </div>
                                </div>
                                <div class="text-end align-self-center">
                                    <a href="{{ $alert['url'] }}" class="btn btn-sm {{ $alert['severity'] === 'Critical' ? 'btn-outline-danger' : 'btn-outline-primary' }} shadow-sm">
                                        <i class="bi bi-eye me-1"></i>Review
                                    </a>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-shield-check fs-1 text-success opacity-50 d-block mb-2"></i>
                            <h6 class="fw-bold text-dark mb-1">No Critical Alerts</h6>
                            <p class="small text-muted mb-0">All diagnostic findings are within routine thresholds. No emergency action required.</p>
                        </div>
                    @endforelse
                </div>
            </div>
            @if($sortedAlerts->count() > 5)
                <div class="card-footer bg-white text-center py-2 border-top">
                    <a href="{{ route('lab.results.index') }}" class="small text-decoration-none fw-semibold">
                        View All {{ $sortedAlerts->count() }} Diagnostic Findings <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            @endif
        </div>
    </div>

    {{-- Section C: Pending Clinical Tasks --}}
    <div class="col-lg-6" id="pending-tasks-section">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0 fw-bold text-dark d-flex align-items-center">
                        <i class="bi bi-check2-square me-2 text-warning"></i>Pending Clinical Tasks
                    </h6>
                    <small class="text-muted d-block mt-1" style="font-size: 0.8rem;">
                        Actionable clinical orders requiring attention or departmental processing
                    </small>
                </div>
                <span class="badge bg-warning text-dark px-2 py-1">
                    {{ number_format($stats['pending_tasks']) }} Actionable
                </span>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    @forelse($sortedPendingTasks->take(5) as $task)
                        <div class="list-group-item p-3 border-bottom">
                            <div class="d-flex justify-content-between align-items-center gap-2">
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                        <span class="badge {{ $task['badge_class'] ?? 'bg-secondary' }}">{{ $task['type'] }}</span>

                                        @if(strtoupper($task['priority']) === 'STAT' || strtoupper($task['priority']) === 'EMERGENCY')
                                            <span class="badge bg-danger text-white px-2 py-0.5" style="font-size: 0.72rem;">STAT</span>
                                        @elseif(strtoupper($task['priority']) === 'URGENT')
                                            <span class="badge bg-warning text-dark px-2 py-0.5" style="font-size: 0.72rem;">URGENT</span>
                                        @else
                                            <span class="badge bg-light text-muted border px-2 py-0.5" style="font-size: 0.72rem;">Routine</span>
                                        @endif

                                        <span class="fw-bold text-dark">{{ $task['patient']?->full_name ?? 'Patient' }}</span>
                                    </div>
                                    <div class="small text-muted mb-1">
                                        <strong>{{ $task['summary'] }}</strong> &bull; Order #: <span class="font-monospace text-dark">{{ $task['request_no'] }}</span>
                                    </div>
                                    <div class="small text-muted" style="font-size: 0.75rem;">
                                        <i class="bi bi-clock me-1"></i>Ordered {{ $task['created_at']?->diffForHumans() }}
                                    </div>
                                </div>
                                <div class="text-end">
                                    <a href="{{ $task['url'] }}" class="btn btn-sm btn-outline-primary shadow-sm">
                                        <i class="bi bi-arrow-right-circle me-1"></i>Review
                                    </a>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-clipboard-check fs-1 text-primary opacity-50 d-block mb-2"></i>
                            <h6 class="fw-bold text-dark mb-1">No Pending Tasks</h6>
                            <p class="small text-muted mb-0">All submitted orders are up to date. No pending actions require your intervention.</p>
                        </div>
                    @endforelse
                </div>
            </div>
            @if($sortedPendingTasks->count() > 5)
                <div class="card-footer bg-white text-center py-2 border-top">
                    <span class="small text-muted">Showing 5 of {{ $sortedPendingTasks->count() }} actionable tasks</span>
                </div>
            @endif
        </div>
    </div>
</div>

{{-- ── 4. Clinical Request Trend & Service Analytics (Sections D & E) ── --}}
<div class="row g-4 mb-4">
    {{-- Section D: Clinical Request Trend --}}
    <div class="col-lg-8">
        <div class="chart-card h-100 p-3 bg-white rounded-3 shadow-sm border-0">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h6 class="mb-0 fw-bold text-dark d-flex align-items-center">
                        <i class="bi bi-graph-up-arrow me-2 text-teal" style="color: #0d9488;"></i>Clinical Request Trend
                    </h6>
                    <small class="text-muted" style="font-size: 0.8rem;">
                        Monthly orders authored by you across all 5 DTCS clinical services
                    </small>
                </div>
                <select id="doctorTrendPeriod" class="form-select form-select-sm" style="width: auto;">
                    <option value="6">Last 6 Months</option>
                    <option value="12">Last 12 Months</option>
                </select>
            </div>
            <div class="chart-container position-relative" style="height: 250px;">
                <canvas id="doctorTrendChart"></canvas>
            </div>
        </div>
    </div>

    {{-- Section E: Requests by Service --}}
    <div class="col-lg-4">
        <div class="chart-card h-100 p-3 bg-white rounded-3 shadow-sm border-0">
            <div class="mb-3">
                <h6 class="mb-0 fw-bold text-dark d-flex align-items-center">
                    <i class="bi bi-bar-chart-fill me-2 text-teal" style="color: #0d9488;"></i>Requests by Service
                </h6>
                <small class="text-muted" style="font-size: 0.8rem;">
                    Volume distribution across clinical departments
                </small>
            </div>
            <div class="chart-container position-relative" style="height: 250px;">
                <canvas id="doctorServiceChart"></canvas>
            </div>
        </div>
    </div>
</div>

{{-- ── 5. Quick Actions (Section F) & Clinical Orders Breakdown (Section G) ── --}}
<div class="row g-4 mb-4">
    {{-- Section F: Quick Clinical Actions --}}
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold text-dark d-flex align-items-center">
                    <i class="bi bi-lightning-charge-fill me-2 text-teal" style="color: #0d9488;"></i>Quick Clinical Actions
                </h6>
                <small class="text-muted d-block mt-1" style="font-size: 0.8rem;">
                    Authorized physician request creation workflows (attending physician recorded on submit)
                </small>
            </div>
            <div class="card-body p-3">
                <div class="row g-2">
                    {{-- Action 1: New Lab Request --}}
                    <div class="col-sm-6">
                        <a href="{{ route('lab.requests.create') }}" class="card p-3 rounded-3 clinical-action-tile h-100">
                            <div class="d-flex align-items-center mb-1">
                                <div class="tile-icon bg-info bg-opacity-10 text-info rounded-3 p-2 me-2 d-flex align-items-center justify-content-center" style="width: 34px; height: 34px;">
                                    <i class="bi bi-clipboard2-pulse-fill fs-5"></i>
                                </div>
                                <div class="fw-bold text-dark small">New Lab Request</div>
                            </div>
                            <p class="text-muted mb-0" style="font-size: 11px; line-height: 1.3;">
                                Requisition hematology, chemistry, or microbiology
                            </p>
                        </a>
                    </div>

                    {{-- Action 2: New Imaging Request --}}
                    <div class="col-sm-6">
                        <a href="{{ route('radiology.requests.create') }}" class="card p-3 rounded-3 clinical-action-tile h-100">
                            <div class="d-flex align-items-center mb-1">
                                <div class="tile-icon bg-primary bg-opacity-10 text-primary rounded-3 p-2 me-2 d-flex align-items-center justify-content-center" style="width: 34px; height: 34px;">
                                    <i class="bi bi-activity fs-5"></i>
                                </div>
                                <div class="fw-bold text-dark small">New Imaging Request</div>
                            </div>
                            <p class="text-muted mb-0" style="font-size: 11px; line-height: 1.3;">
                                Order X-Ray, CT, MRI, or Ultrasound exam
                            </p>
                        </a>
                    </div>

                    {{-- Action 3: New Prescription --}}
                    <div class="col-sm-6">
                        <a href="{{ route('pharmacy.prescriptions.create') }}" class="card p-3 rounded-3 clinical-action-tile h-100">
                            <div class="d-flex align-items-center mb-1">
                                <div class="tile-icon bg-success bg-opacity-10 text-success rounded-3 p-2 me-2 d-flex align-items-center justify-content-center" style="width: 34px; height: 34px;">
                                    <i class="bi bi-capsule fs-5"></i>
                                </div>
                                <div class="fw-bold text-dark small">New Prescription</div>
                            </div>
                            <p class="text-muted mb-0" style="font-size: 11px; line-height: 1.3;">
                                Prescribe pharmaceutical regimen with dosage
                            </p>
                        </a>
                    </div>

                    {{-- Action 4: New Surgery Request --}}
                    <div class="col-sm-6">
                        <a href="{{ route('surgery.requests.create') }}" class="card p-3 rounded-3 clinical-action-tile h-100">
                            <div class="d-flex align-items-center mb-1">
                                <div class="tile-icon bg-warning bg-opacity-10 text-warning rounded-3 p-2 me-2 d-flex align-items-center justify-content-center" style="width: 34px; height: 34px;">
                                    <i class="bi bi-scissors fs-5"></i>
                                </div>
                                <div class="fw-bold text-dark small">New Surgery Request</div>
                            </div>
                            <p class="text-muted mb-0" style="font-size: 11px; line-height: 1.3;">
                                Book operating room procedure and surgical team
                            </p>
                        </a>
                    </div>

                    {{-- Action 5: New Diet Request --}}
                    <div class="col-sm-6">
                        <a href="{{ route('diet.requests.create') }}" class="card p-3 rounded-3 clinical-action-tile h-100">
                            <div class="d-flex align-items-center mb-1">
                                <div class="tile-icon bg-teal bg-opacity-10 rounded-3 p-2 me-2 d-flex align-items-center justify-content-center" style="width: 34px; height: 34px; color: #0d9488; background-color: rgba(13, 148, 136, 0.1);">
                                    <i class="bi bi-apple fs-5"></i>
                                </div>
                                <div class="fw-bold text-dark small">New Diet Requisition</div>
                            </div>
                            <p class="text-muted mb-0" style="font-size: 11px; line-height: 1.3;">
                                Order clinical nutrition regimen (NPO, Diabetic, Low Sodium, Renal)
                            </p>
                        </a>
                    </div>

                    {{-- Action 6: Appointments --}}
                    <div class="col-sm-6">
                        <a href="{{ route('admin.appointments.index') }}" class="card p-3 rounded-3 clinical-action-tile h-100">
                            <div class="d-flex align-items-center mb-1">
                                <div class="tile-icon bg-primary bg-opacity-10 text-primary rounded-3 p-2 me-2 d-flex align-items-center justify-content-center" style="width: 34px; height: 34px;">
                                    <i class="bi bi-calendar-check fs-5"></i>
                                </div>
                                <div class="fw-bold text-dark small">Appointments</div>
                            </div>
                            <p class="text-muted mb-0" style="font-size: 11px; line-height: 1.3;">
                                Manage consultation queue, scheduled visits, and patient arrivals
                            </p>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Section G: Clinical Orders Breakdown & Consultation Trends --}}
    <div class="col-lg-6">
        <div class="row g-3 h-100">
            <div class="col-md-6">
                <div class="chart-card h-100 p-3 bg-white rounded-3 shadow-sm border-0">
                    <div class="mb-2">
                        <h6 class="mb-0 fw-bold text-dark d-flex align-items-center">
                            <i class="bi bi-pie-chart-fill me-2 text-teal" style="color: #0d9488;"></i>Orders Breakdown
                        </h6>
                        <small class="text-muted" style="font-size: 0.75rem;">
                            Active pending workload
                        </small>
                    </div>
                    <div class="chart-container position-relative" style="height: 210px;">
                        <canvas id="doctorOrdersChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="chart-card h-100 p-3 bg-white rounded-3 shadow-sm border-0">
                    <div class="mb-2">
                        <h6 class="mb-0 fw-bold text-dark d-flex align-items-center">
                            <i class="bi bi-calendar-week me-2 text-teal" style="color: #0d9488;"></i>Consultation Trends
                        </h6>
                        <small class="text-muted" style="font-size: 0.75rem;">
                            6-month patient consultations
                        </small>
                    </div>
                    <div class="chart-container position-relative" style="height: 210px;">
                        <canvas id="doctorConsultationChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── 6. Clinical Module Overview & Boundaries (Section H) ── --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h6 class="mb-0 fw-bold text-dark d-flex align-items-center">
                    <i class="bi bi-grid-3x3-gap-fill me-2 text-teal" style="color: #0d9488;"></i>Clinical Module Overview
                </h6>
                <small class="text-muted d-block mt-1" style="font-size: 0.8rem;">
                    Departmental workload and clinical order totals authored under your physician credentials
                </small>
            </div>
            <span class="badge bg-light text-muted border px-2 py-1 small">
                <i class="bi bi-info-circle me-1"></i>Clinical Authority Separated from Departmental Operations
            </span>
        </div>
    </div>
    <div class="card-body p-3">
        <div class="balanced-grid balanced-grid-6">
            {{-- MediSense AI Tile --}}
            <a href="{{ route('medisense.index') }}" class="card p-3 rounded-3 clinical-action-tile h-100" style="border-color: rgba(13, 148, 136, 0.3) !important;">
                <div class="d-flex align-items-center mb-2">
                    <div class="tile-icon bg-teal text-white rounded-3 p-2 me-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; background-color: #0d9488;">
                        <i class="bi bi-cpu-fill fs-5"></i>
                    </div>
                    <div class="fw-bold small" style="color: #0d9488;">MediSense AI</div>
                </div>
                <p class="text-muted mb-0" style="font-size: 11px; line-height: 1.3;">
                    Clinical Decision Support System (Doctor Only)
                </p>
            </a>

            {{-- LIS Tile --}}
            <a href="{{ route('lab.dashboard') }}" class="card p-3 rounded-3 clinical-action-tile h-100">
                <div class="d-flex align-items-center mb-2">
                    <div class="tile-icon bg-info bg-opacity-10 text-info rounded-3 p-2 me-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i class="bi bi-clipboard2-pulse fs-5"></i>
                    </div>
                    <div class="fw-bold text-dark small">Laboratory (LIS)</div>
                </div>
                <p class="text-muted mb-0" style="font-size: 11px; line-height: 1.3;">
                    <strong>{{ number_format($stats['my_lab_requests']) }}</strong> lab requests authored
                </p>
            </a>

            {{-- RIS Tile --}}
            <a href="{{ route('radiology.dashboard') }}" class="card p-3 rounded-3 clinical-action-tile h-100">
                <div class="d-flex align-items-center mb-2">
                    <div class="tile-icon bg-primary bg-opacity-10 text-primary rounded-3 p-2 me-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i class="bi bi-activity fs-5"></i>
                    </div>
                    <div class="fw-bold text-dark small">Radiology (RIS)</div>
                </div>
                <p class="text-muted mb-0" style="font-size: 11px; line-height: 1.3;">
                    <strong>{{ number_format($stats['my_radiology']) }}</strong> imaging orders authored
                </p>
            </a>

            {{-- PMS Tile --}}
            <a href="{{ route('pharmacy.dashboard') }}" class="card p-3 rounded-3 clinical-action-tile h-100">
                <div class="d-flex align-items-center mb-2">
                    <div class="tile-icon bg-success bg-opacity-10 text-success rounded-3 p-2 me-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i class="bi bi-capsule fs-5"></i>
                    </div>
                    <div class="fw-bold text-dark small">Pharmacy (PMS)</div>
                </div>
                <p class="text-muted mb-0" style="font-size: 11px; line-height: 1.3;">
                    <strong>{{ number_format($stats['my_prescriptions']) }}</strong> prescriptions written
                </p>
            </a>

            {{-- SORS Tile --}}
            <a href="{{ route('surgery.dashboard') }}" class="card p-3 rounded-3 clinical-action-tile h-100">
                <div class="d-flex align-items-center mb-2">
                    <div class="tile-icon bg-warning bg-opacity-10 text-warning rounded-3 p-2 me-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i class="bi bi-scissors fs-5"></i>
                    </div>
                    <div class="fw-bold text-dark small">Surgery (SORS)</div>
                </div>
                <p class="text-muted mb-0" style="font-size: 11px; line-height: 1.3;">
                    <strong>{{ number_format($stats['my_surgeries']) }}</strong> surgical requests
                </p>
            </a>

            {{-- DNMS Tile --}}
            <a href="{{ route('diet.dashboard') }}" class="card p-3 rounded-3 clinical-action-tile h-100">
                <div class="d-flex align-items-center mb-2">
                    <div class="tile-icon bg-teal bg-opacity-10 rounded-3 p-2 me-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; color: #0d9488; background-color: rgba(13, 148, 136, 0.1);">
                        <i class="bi bi-apple fs-5"></i>
                    </div>
                    <div class="fw-bold text-dark small">Nutrition (DNMS)</div>
                </div>
                <p class="text-muted mb-0" style="font-size: 11px; line-height: 1.3;">
                    <strong>{{ number_format($stats['my_diet_requests']) }}</strong> diet orders requested
                </p>
            </a>
        </div>
    </div>
</div>

{{-- ── 7. MediSense AI Clinical Decision Support Panel (Section I) ── --}}
<div class="card border-0 shadow-sm mb-4" style="background: linear-gradient(135deg, #f0fdfa 0%, #e0f2fe 100%);">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-start gap-3">
                <div class="rounded-circle p-3 d-flex align-items-center justify-content-center" style="background-color: #0d9488; color: #ffffff; width: 50px; height: 50px;">
                    <i class="bi bi-cpu-fill fs-4"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-1" style="color: #0f766e;">
                        Virtual MediSense AI — Clinical Decision Support
                    </h5>
                    <p class="mb-2 text-secondary small" style="max-width: 720px; line-height: 1.5;">
                        Specialized physician assistance across 4 evidence-based modes: <strong>Symptom Assessment</strong>, <strong>Diagnostic Assistance</strong>, <strong>Treatment Recommendation</strong>, and <strong>Clinical Service Assistance</strong>.
                    </p>
                    <div class="small d-flex align-items-center text-muted gap-2">
                        <span class="badge bg-light text-dark border"><i class="bi bi-shield-lock me-1 text-success"></i>Zero PII Over Wire</span>
                        <span class="badge bg-light text-dark border"><i class="bi bi-person-check me-1 text-primary"></i>Physician-in-the-Loop</span>
                        <span class="badge bg-light text-dark border"><i class="bi bi-slash-circle me-1 text-danger"></i>Non-Autonomous</span>
                    </div>
                </div>
            </div>
            <div>
                <a href="{{ route('medisense.index') }}" class="btn btn-teal text-white px-4 py-2 shadow-sm d-flex align-items-center gap-2" style="background-color: #0d9488; border: none;">
                    <i class="bi bi-arrow-right-circle fs-5"></i>
                    <span class="fw-semibold">Launch MediSense Workspace</span>
                </a>
            </div>
        </div>
    </div>
</div>

{{-- ── 8. Upcoming Appointments, Recent Lab Results & Recent Radiology Reports ── --}}
<div class="row g-4 mb-4">
    {{-- Upcoming Appointments & Waiting Patients --}}
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0 fw-bold text-dark d-flex align-items-center">
                        <i class="bi bi-calendar-check me-2 text-success"></i>Upcoming Appointments
                    </h6>
                    <small class="text-muted" style="font-size: 0.78rem;">Scheduled visits & waiting queue</small>
                </div>
                <a href="{{ route('admin.appointments.index') }}" class="btn btn-sm btn-outline-secondary">Queue</a>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    @forelse($upcomingAppointmentsList ?? [] as $apt)
                        <div class="list-group-item p-3 border-bottom">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-bold text-dark small">{{ $apt->patient->full_name ?? 'Patient' }}</div>
                                    <div class="small text-muted">{{ $apt->type }} &bull; {{ $apt->scheduled_at?->format('M d, h:i A') }}</div>
                                </div>
                                <span class="badge bg-light text-dark border small">{{ $apt->status }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-4 text-muted small">
                            No upcoming appointments scheduled.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- Recent Laboratory Results --}}
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0 fw-bold text-dark d-flex align-items-center">
                        <i class="bi bi-clipboard2-pulse me-2 text-info"></i>Recent Laboratory Results
                    </h6>
                    <small class="text-muted" style="font-size: 0.78rem;">Released diagnostic results</small>
                </div>
                <a href="{{ route('lab.results.index') }}" class="btn btn-sm btn-outline-secondary">All</a>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    @forelse($releasedLabResults->take(4) as $res)
                        <div class="list-group-item p-3 border-bottom">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-bold text-dark small">{{ $res->requestItem?->labRequest?->patient?->full_name ?? 'Patient' }}</div>
                                    <div class="small text-muted">{{ $res->requestItem?->test_name ?? 'Lab Test' }}: <strong class="text-dark">{{ Str::limit($res->result_value, 28) }}</strong></div>
                                </div>
                                <a href="{{ route('lab.results.show', $res) }}" class="btn btn-sm btn-outline-primary py-0 px-2">View</a>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-4 text-muted small">
                            No released laboratory results yet.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- Recent Radiology Reports --}}
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0 fw-bold text-dark d-flex align-items-center">
                        <i class="bi bi-activity me-2 text-primary"></i>Recent Radiology Reports
                    </h6>
                    <small class="text-muted" style="font-size: 0.78rem;">Approved & released imaging findings</small>
                </div>
                <a href="{{ route('radiology.reports.index') }}" class="btn btn-sm btn-outline-secondary">All</a>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    @forelse($releasedRadReports->take(4) as $rad)
                        <div class="list-group-item p-3 border-bottom">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-bold text-dark small">{{ $rad->radiologyRequest?->patient?->full_name ?? 'Patient' }}</div>
                                    <div class="small text-muted">{{ $rad->radiologyRequest?->modality }} ({{ $rad->radiologyRequest?->body_part }})</div>
                                </div>
                                <a href="{{ route('radiology.reports.show', $rad) }}" class="btn btn-sm btn-outline-primary py-0 px-2">View</a>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-4 text-muted small">
                            No released radiology reports yet.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── 9. Recent Patient Access (Section J) ── --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h6 class="mb-0 fw-bold text-dark d-flex align-items-center">
                <i class="bi bi-person-lines-fill me-2 text-primary"></i>Recent Patient Access
            </h6>
            <small class="text-muted d-block mt-1" style="font-size: 0.8rem;">
                Patients with clinical orders, diagnostic requisitions, or appointments authorized under your care
            </small>
        </div>
        <a href="{{ route('patients.index') }}" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-folder2-open me-1"></i>View Full Patient Directory
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Patient ID</th>
                        <th>Patient Name</th>
                        <th>Gender / DOB</th>
                        <th>Type / Ward</th>
                        <th>Last Activity</th>
                        <th class="text-end pe-3">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentPatients as $patient)
                        <tr>
                            <td class="ps-3">
                                <span class="badge bg-light text-dark border">
                                    <i class="bi bi-card-text me-1 text-muted"></i>{{ $patient->patient_no }}
                                </span>
                            </td>
                            <td>
                                <div class="fw-bold text-dark">{{ $patient->full_name }}</div>
                                <div class="small text-muted">{{ $patient->phone ? $patient->phone : 'Contact on file' }}</div>
                            </td>
                            <td>
                                <span class="small">{{ $patient->gender ?? 'N/A' }} &bull; {{ $patient->date_of_birth?->format('M d, Y') ?? 'N/A' }}</span>
                            </td>
                            <td>
                                <span class="badge hims-badge-{{ strtolower($patient->patient_type ?? '') === 'inpatient' ? 'blue' : (strtolower($patient->patient_type ?? '') === 'emergency' ? 'red' : 'green') }}">
                                    {{ $patient->patient_type ?? 'Outpatient' }}
                                </span>
                                @if($patient->ward)
                                    <small class="text-muted d-block">{{ $patient->ward }}{{ $patient->bed_number ? ' (Bed ' . $patient->bed_number . ')' : '' }}</small>
                                @endif
                            </td>
                            <td>
                                <span class="small text-muted">
                                    <i class="bi bi-clock me-1"></i>Updated {{ $patient->updated_at?->diffForHumans() }}
                                </span>
                            </td>
                            <td class="text-end pe-3">
                                <div class="d-flex justify-content-end gap-1">
                                    <a href="{{ route('patients.show', $patient) }}" class="btn btn-sm btn-outline-primary" title="View Patient Chart" aria-label="View Patient Chart">
                                        <i class="bi bi-person-vcard me-1"></i>Chart
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-people fs-2 d-block mb-2 opacity-50"></i>
                                <h6 class="fw-semibold text-dark mb-1">No Authorized Patient Records Yet</h6>
                                <p class="small text-muted mb-0">Patients will appear here once you author clinical orders, write prescriptions, or schedule appointments.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ── 9. Client-Side Chart Data JSON ── --}}
<script type="application/json" id="doctor-orders-data">
    {"lab": {{ $pendingLabRequests->count() }}, "rad": {{ $pendingRadRequests->count() }}, "pms": {{ $pendingRx->count() }}, "sors": {{ $pendingSurgery->count() }}, "dnms": {{ $pendingDiet->count() }}}
</script>
<script type="application/json" id="doctor-trend-6m">@json($doctorTrend6m)</script>
<script type="application/json" id="doctor-trend-12m">@json($doctorTrend12m)</script>
<script type="application/json" id="doctor-service-breakdown">@json($doctorServiceBreakdown)</script>
<script type="application/json" id="doctor-consultation-trend-6m">@json($doctorConsultationTrend6m ?? [])</script>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    // 1. Orders Breakdown Doughnut Chart
    const ordersDataElem = document.getElementById('doctor-orders-data');
    const ordersCtx = document.getElementById('doctorOrdersChart');

    if (ordersDataElem && ordersCtx) {
        const ordersData = JSON.parse(ordersDataElem.textContent);
        const totalPending = ordersData.lab + ordersData.rad + ordersData.pms + ordersData.sors + ordersData.dnms;

        if (typeof window.HIMSChart !== 'undefined') {
            window.HIMSChart.createDoughnutChart(
                ordersCtx,
                ['Pending Lab', 'Pending Radiology', 'Pending Prescriptions', 'Pending Surgeries', 'Pending Diets'],
                [ordersData.lab, ordersData.rad, ordersData.pms, ordersData.sors, ordersData.dnms],
                ['#0ea5e9', '#8b5cf6', '#10b981', '#f59e0b', '#0d9488']
            );
        } else if (typeof Chart !== 'undefined') {
            new Chart(ordersCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Pending Lab', 'Pending Radiology', 'Pending Prescriptions', 'Pending Surgeries', 'Pending Diets'],
                    datasets: [{
                        data: [ordersData.lab, ordersData.rad, ordersData.pms, ordersData.sors, ordersData.dnms],
                        backgroundColor: ['#0ea5e9', '#8b5cf6', '#10b981', '#f59e0b', '#0d9488'],
                        borderWidth: 2,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } }
                    }
                }
            });
        }
    }

    // 2. Doctor Trend Line Chart (6m / 12m Toggle)
    const trend6mElem  = document.getElementById('doctor-trend-6m');
    const trend12mElem = document.getElementById('doctor-trend-12m');
    const trendCtx     = document.getElementById('doctorTrendChart');

    if (trend6mElem && trend12mElem && trendCtx) {
        const trend6m  = JSON.parse(trend6mElem.textContent);
        const trend12m = JSON.parse(trend12mElem.textContent);

        let doctorTrendChart = null;

        function renderDoctorTrend(data) {
            if (!trendCtx) return;
            if (doctorTrendChart) {
                doctorTrendChart.destroy();
                doctorTrendChart = null;
            }
            if (!data || data.length === 0) return;

            if (typeof window.HIMSChart !== 'undefined') {
                doctorTrendChart = window.HIMSChart.createLineChart(
                    trendCtx,
                    'Authored Requests',
                    data.map(d => d.label),
                    data.map(d => d.total),
                    'requests'
                );
            } else if (typeof Chart !== 'undefined') {
                doctorTrendChart = new Chart(trendCtx, {
                    type: 'line',
                    data: {
                        labels: data.map(d => d.label),
                        datasets: [{
                            label: 'Authored Requests',
                            data: data.map(d => d.total),
                            borderColor: '#0d9488',
                            backgroundColor: 'rgba(13, 148, 136, 0.12)',
                            tension: 0.3,
                            fill: true,
                            pointBackgroundColor: '#0d9488',
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

        renderDoctorTrend(trend6m);

        const periodSel = document.getElementById('doctorTrendPeriod');
        if (periodSel) {
            periodSel.addEventListener('change', function () {
                renderDoctorTrend(this.value === '12' ? trend12m : trend6m);
            });
        }
    }

    // 3. Service Breakdown Bar Chart
    const svcElem = document.getElementById('doctor-service-breakdown');
    const svcCtx  = document.getElementById('doctorServiceChart');

    if (svcElem && svcCtx) {
        const svcData = JSON.parse(svcElem.textContent);
        const isDark = (typeof window.HIMSChart !== 'undefined') ? window.HIMSChart.isDarkMode() : false;
        const gridColor = isDark ? '#262626' : '#f1f5f9';
        const tickColor = isDark ? '#94a3b8' : '#64748b';

        if (typeof Chart !== 'undefined') {
            new Chart(svcCtx, {
                type: 'bar',
                data: {
                    labels: svcData.map(s => s.service),
                    datasets: [{
                        label: 'Authored Orders',
                        data: svcData.map(s => s.total),
                        backgroundColor: [
                            'rgba(14, 165, 233, 0.75)',
                            'rgba(139, 92, 246, 0.75)',
                            'rgba(16, 185, 129, 0.75)',
                            'rgba(245, 158, 11, 0.75)',
                            'rgba(13, 148, 136, 0.75)'
                        ],
                        borderColor: [
                            '#0ea5e9',
                            '#8b5cf6',
                            '#10b981',
                            '#f59e0b',
                            '#0d9488'
                        ],
                        borderWidth: 1,
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: { duration: 1000, easing: 'easeOutQuart' },
                    plugins: {
                        legend: { display: false },
                        tooltip: { backgroundColor: '#0f172a', padding: 10, cornerRadius: 6 }
                    },
                    scales: {
                        x: {
                            ticks: { color: tickColor, font: { size: 11 } },
                            grid: { display: false },
                            border: { display: false }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: { color: tickColor, precision: 0 },
                            grid: { color: gridColor },
                            border: { display: false }
                        }
                    }
                }
            });
        }
    }

    // 4. Patient Consultation Trends Chart
    const consultElem = document.getElementById('doctor-consultation-trend-6m');
    const consultCtx  = document.getElementById('doctorConsultationChart');
    if (consultElem && consultCtx) {
        const consultData = JSON.parse(consultElem.textContent || '[]');
        if (consultData.length > 0 && typeof Chart !== 'undefined') {
            new Chart(consultCtx, {
                type: 'line',
                data: {
                    labels: consultData.map(c => c.label),
                    datasets: [{
                        label: 'Consultations',
                        data: consultData.map(c => c.total),
                        borderColor: '#0284c7',
                        backgroundColor: 'rgba(2, 132, 199, 0.12)',
                        tension: 0.35,
                        fill: true,
                        pointRadius: 3
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
@endsection
