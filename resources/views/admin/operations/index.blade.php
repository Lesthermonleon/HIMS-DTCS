@extends('layouts.app')

@section('title', 'Hospital Operations & Workflow Monitoring')
@section('page-title', 'Admin — Operations Monitor')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active" aria-current="page">Operations Monitor</li>
@endsection

@section('content')
<div class="container-fluid px-0">
    {{-- Operational Boundary & Governance Alert --}}
    <div class="card border-0 shadow-sm mb-4" style="border-left: 4px solid #0284c7 !important;">
        <div class="card-body">
            <h5 class="fw-bold mb-1 text-dark"><i class="bi bi-activity text-primary me-2"></i> Daily Hospital Operations & Workflow Monitoring</h5>
            <p class="text-muted small mb-0">
                Operational status across clinical modules (LIS, RIS, PMS, SORS, DNMS). Turnaround times and queue delays are calculated solely from verified database workflow timestamps. Triage and clinical priority decisions remain strictly with licensed clinicians.
            </p>
        </div>
    </div>

    {{-- Operational Volume Metric Cards --}}
    <h6 class="text-uppercase fw-bold text-secondary mb-3 small" style="letter-spacing: 0.05em;">Clinical Service Volumes</h6>
    <div class="row g-3 mb-4">
        {{-- Laboratory --}}
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-muted small fw-semibold">Laboratory (LIS)</span>
                        <i class="bi bi-clipboard2-pulse text-primary fs-5"></i>
                    </div>
                    <h3 class="fw-bold mb-0 text-dark">{{ $volumes['lab']['today'] }} <span class="fs-6 text-muted fw-normal">today</span></h3>
                    <div class="text-muted small mt-2" style="font-size: 11px;">
                        <span>Week: <strong>{{ $volumes['lab']['week'] }}</strong></span> · <span>Month: <strong>{{ $volumes['lab']['month'] }}</strong></span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Radiology --}}
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-muted small fw-semibold">Radiology (RIS)</span>
                        <i class="bi bi-activity text-info fs-5"></i>
                    </div>
                    <h3 class="fw-bold mb-0 text-dark">{{ $volumes['radiology']['today'] }} <span class="fs-6 text-muted fw-normal">today</span></h3>
                    <div class="text-muted small mt-2" style="font-size: 11px;">
                        <span>Week: <strong>{{ $volumes['radiology']['week'] }}</strong></span> · <span>Month: <strong>{{ $volumes['radiology']['month'] }}</strong></span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Pharmacy Prescriptions --}}
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-muted small fw-semibold">Prescriptions</span>
                        <i class="bi bi-prescription2 text-success fs-5"></i>
                    </div>
                    <h3 class="fw-bold mb-0 text-dark">{{ $volumes['pharmacy']['today'] }} <span class="fs-6 text-muted fw-normal">today</span></h3>
                    <div class="text-muted small mt-2" style="font-size: 11px;">
                        <span>Week: <strong>{{ $volumes['pharmacy']['week'] }}</strong></span> · <span>Month: <strong>{{ $volumes['pharmacy']['month'] }}</strong></span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Pharmacy Dispensing --}}
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-muted small fw-semibold">Dispensing</span>
                        <i class="bi bi-bag-check text-teal fs-5" style="color: #0d9488;"></i>
                    </div>
                    <h3 class="fw-bold mb-0 text-dark">{{ $volumes['dispensing']['today'] }} <span class="fs-6 text-muted fw-normal">today</span></h3>
                    <div class="text-muted small mt-2" style="font-size: 11px;">
                        <span>Week: <strong>{{ $volumes['dispensing']['week'] }}</strong></span> · <span>Month: <strong>{{ $volumes['dispensing']['month'] }}</strong></span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Surgical / OR --}}
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-muted small fw-semibold">Surgery (SORS)</span>
                        <i class="bi bi-heart-pulse text-danger fs-5"></i>
                    </div>
                    <h3 class="fw-bold mb-0 text-dark">{{ $volumes['surgery']['today'] }} <span class="fs-6 text-muted fw-normal">today</span></h3>
                    <div class="text-muted small mt-2" style="font-size: 11px;">
                        <span>Week: <strong>{{ $volumes['surgery']['week'] }}</strong></span> · <span>Month: <strong>{{ $volumes['surgery']['month'] }}</strong></span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Diet / Nutrition --}}
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-muted small fw-semibold">Nutrition (DNMS)</span>
                        <i class="bi bi-apple text-warning fs-5"></i>
                    </div>
                    <h3 class="fw-bold mb-0 text-dark">{{ $volumes['diet']['today'] }} <span class="fs-6 text-muted fw-normal">today</span></h3>
                    <div class="text-muted small mt-2" style="font-size: 11px;">
                        <span>Week: <strong>{{ $volumes['diet']['week'] }}</strong></span> · <span>Month: <strong>{{ $volumes['diet']['month'] }}</strong></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Turnaround Time (TAT) & Performance --}}
    <div class="row g-4 mb-4">
        {{-- Lab Turnaround Time --}}
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-dark"><i class="bi bi-stopwatch me-2 text-primary"></i> Laboratory Turnaround Time (TAT)</h6>
                    <span class="badge bg-light text-muted border">Past 30 Days</span>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-baseline mb-2">
                        <h2 class="fw-bold text-dark mb-0 me-2">
                            {{ $tatStats['lab_avg_minutes'] !== null ? $tatStats['lab_avg_minutes'] : '—' }}
                        </h2>
                        <span class="text-muted">minutes (average)</span>
                    </div>
                    <p class="small text-muted mb-0">
                        Calculated from specimen receipt (<code>lab_requests.received_at</code>) to medical technologist result completion (<code>completed_at</code>).
                    </p>
                </div>
            </div>
        </div>

        {{-- Radiology Turnaround Time --}}
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-dark"><i class="bi bi-stopwatch me-2 text-info"></i> Radiology Turnaround Time (TAT)</h6>
                    <span class="badge bg-light text-muted border">Past 30 Days</span>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-baseline mb-2">
                        <h2 class="fw-bold text-dark mb-0 me-2">
                            {{ $tatStats['rad_avg_minutes'] !== null ? $tatStats['rad_avg_minutes'] : '—' }}
                        </h2>
                        <span class="text-muted">minutes (average)</span>
                    </div>
                    <p class="small text-muted mb-0">
                        Calculated from examination scheduling (<code>radiology_requests.scheduled_at</code>) to radiologist report finalization (<code>completed_at</code>).
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- Urgent Priority & Queue Delay Monitoring Grid --}}
    <div class="row g-4 mb-4">
        {{-- Aging Queue Delays --}}
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i> Aging Queue Delays (> {{ $thresholdMinutes }} min)</h6>
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">{{ $delayedLabRequests->count() + $delayedRadRequests->count() }} Delayed</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Request No</th>
                                    <th>Module</th>
                                    <th>Doctor</th>
                                    <th>Waiting Time</th>
                                    <th class="pe-3">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($delayedLabRequests as $dl)
                                    <tr>
                                        <td class="ps-3 fw-bold font-monospace text-primary">{{ $dl->request_number }}</td>
                                        <td><span class="badge bg-primary-subtle text-primary border">Lab</span></td>
                                        <td>Dr. {{ $dl->doctor?->name ?? '—' }}</td>
                                        <td class="text-danger fw-semibold">{{ $dl->created_at ? $dl->created_at->diffForHumans(null, true) : '—' }}</td>
                                        <td class="pe-3"><span class="badge bg-warning-subtle text-warning border">{{ $dl->status }}</span></td>
                                    </tr>
                                @endforeach
                                @foreach($delayedRadRequests as $dr)
                                    <tr>
                                        <td class="ps-3 fw-bold font-monospace text-info">{{ $dr->request_number }}</td>
                                        <td><span class="badge bg-info-subtle text-info border">Radiology</span></td>
                                        <td>Dr. {{ $dr->doctor?->name ?? '—' }}</td>
                                        <td class="text-danger fw-semibold">{{ $dr->created_at ? $dr->created_at->diffForHumans(null, true) : '—' }}</td>
                                        <td class="pe-3"><span class="badge bg-warning-subtle text-warning border">{{ $dr->status }}</span></td>
                                    </tr>
                                @endforeach
                                @if($delayedLabRequests->isEmpty() && $delayedRadRequests->isEmpty())
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">
                                            <i class="bi bi-check-circle text-success me-1"></i> No uncompleted requests exceed the delay threshold.
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- STAT & Urgent Priority Requests --}}
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-dark"><i class="bi bi-lightning-charge-fill me-2 text-warning"></i> Read-Only Priority Queue (STAT / Urgent)</h6>
                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle">{{ $priorityLab->count() + $priorityRad->count() }} Urgent</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Request No</th>
                                    <th>Department</th>
                                    <th>Ordering Clinician</th>
                                    <th>Priority</th>
                                    <th class="pe-3">Requested At</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($priorityLab as $pl)
                                    <tr>
                                        <td class="ps-3 fw-bold font-monospace text-primary">{{ $pl->request_number }}</td>
                                        <td>Laboratory</td>
                                        <td>Dr. {{ $pl->doctor?->name ?? '—' }}</td>
                                        <td><span class="badge bg-danger text-white text-uppercase">{{ $pl->priority }}</span></td>
                                        <td class="pe-3 text-muted">{{ $pl->created_at ? $pl->created_at->format('M d, H:i') : '—' }}</td>
                                    </tr>
                                @endforeach
                                @foreach($priorityRad as $pr)
                                    <tr>
                                        <td class="ps-3 fw-bold font-monospace text-info">{{ $pr->request_number }}</td>
                                        <td>Radiology</td>
                                        <td>Dr. {{ $pr->doctor?->name ?? '—' }}</td>
                                        <td><span class="badge bg-danger text-white text-uppercase">{{ $pr->priority }}</span></td>
                                        <td class="pe-3 text-muted">{{ $pr->created_at ? $pr->created_at->format('M d, H:i') : '—' }}</td>
                                    </tr>
                                @endforeach
                                @if($priorityLab->isEmpty() && $priorityRad->isEmpty())
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">
                                            No active STAT or Urgent requests pending in clinical queues.
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Architectural & Scope Honesty Documentation Card --}}
    <div class="card border-0 shadow-sm" style="background: #f8fafc;">
        <div class="card-body small">
            <h6 class="fw-bold text-dark mb-2"><i class="bi bi-info-circle-fill text-primary me-2"></i> Operational Boundaries & Documented Integrations</h6>
            <div class="row g-3 text-muted">
                <div class="col-md-4">
                    <strong>1. Clinical Triage:</strong> Clinical decisions remain exclusively with licensed medical staff. Administrator role provides queue coordination only.
                </div>
                <div class="col-md-4">
                    <strong>2. Critical Laboratory Alerts:</strong> Panic-value thresholds require approved laboratory governance protocols; external SMS routing is active only when provider credentials are configured.
                </div>
                <div class="col-md-4">
                    <strong>3. Bed & Ward Census:</strong> Patient admission and master records belong to the upstream hospital registration system; bed census is documented as a future module extension.
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
