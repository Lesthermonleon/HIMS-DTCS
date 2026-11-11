@extends('layouts.app')
@section('title', 'Surgery Request Details')
@section('page-title', 'Surgery Request Details')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('surgery.dashboard') }}">Surgery (SORS)</a></li>
    <li class="breadcrumb-item"><a href="{{ route('surgery.requests.index') }}">Requests</a></li>
    <li class="breadcrumb-item active">{{ $surgeryRequest->request_no }}</li>
@endsection

@section('content')
<div class="row g-4">
    <!-- Main Column: Request & Patient Information -->
    <div class="col-lg-8">
        <!-- Surgery Procedure Card -->
        <div class="card shadow-sm border-0 mb-4" style="border-radius: 0.75rem; background: var(--card);">
            <div class="card-header border-bottom py-3 px-4 d-flex justify-content-between align-items-center" style="background: rgba(0,0,0,0.015);">
                <div class="d-flex align-items-center gap-2">
                    <h5 class="mb-0 fw-bold text-primary" style="font-family: var(--font-display); font-size: 1.15rem;">
                        {{ $surgeryRequest->procedure_name }}
                    </h5>
                    <span class="badge bg-{{ $surgeryRequest->statusBadge }}">
                        {{ $surgeryRequest->status }}
                    </span>
                    <span class="badge bg-{{ $surgeryRequest->urgency === 'Emergency' ? 'danger' : ($surgeryRequest->urgency === 'Urgent' ? 'warning text-dark' : 'secondary') }}">
                        {{ $surgeryRequest->urgency }}
                    </span>
                </div>
                <div class="text-muted small">
                    Req #: <span class="fw-bold text-dark">{{ $surgeryRequest->request_no }}</span>
                </div>
            </div>

            <div class="card-body p-4">
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="text-muted small mb-1" style="font-size: 0.72rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.04em;">Diagnosis / Indication</div>
                        <div class="fw-semibold" style="color: var(--text);">{{ $surgeryRequest->diagnosis ?? 'Not specified' }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted small mb-1" style="font-size: 0.72rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.04em;">Anesthesia Type</div>
                        <div class="fw-semibold" style="color: var(--text);">{{ $surgeryRequest->anesthesia_type ?? 'Standard' }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted small mb-1" style="font-size: 0.72rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.04em;">Est. Duration</div>
                        <div class="fw-semibold" style="color: var(--text);">{{ $surgeryRequest->estimated_duration ?? 60 }} mins</div>
                    </div>
                </div>

                <div class="row g-3 border-top pt-3">
                    <div class="col-md-6">
                        <div class="text-muted small mb-1" style="font-size: 0.72rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.04em;">Ordering Doctor</div>
                        <div class="fw-semibold text-primary"><i class="bi bi-person-badge me-1"></i>{{ $surgeryRequest->doctor->name ?? 'N/A' }}</div>
                        <div class="text-muted small">{{ $surgeryRequest->doctor->email ?? '' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small mb-1" style="font-size: 0.72rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.04em;">Date Requested</div>
                        <div class="fw-semibold"><i class="bi bi-calendar-event me-1"></i>{{ $surgeryRequest->requested_at ? $surgeryRequest->requested_at->format('F d, Y h:i A') : 'N/A' }}</div>
                    </div>
                </div>

                @if($surgeryRequest->notes)
                    <div class="border-top pt-3 mt-3">
                        <div class="text-muted small mb-1" style="font-size: 0.72rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.04em;">Clinical Notes & Special Instructions</div>
                        <div class="p-3 rounded" style="background: rgba(0,0,0,0.02); font-size: 0.88rem; color: var(--text);">
                            {{ $surgeryRequest->notes }}
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Patient Profile Card -->
        <div class="card shadow-sm border-0" style="border-radius: 0.75rem; background: var(--card);">
            <div class="card-header border-bottom py-3 px-4 d-flex align-items-center justify-content-between" style="background: rgba(0,0,0,0.015);">
                <h6 class="mb-0 fw-bold d-flex align-items-center gap-2" style="font-family: var(--font-display);">
                    <i class="bi bi-person text-primary"></i>Patient Information
                </h6>
                @if($surgeryRequest->patient && auth()->user()->canAccessPatients())
                    <a href="{{ route('patients.show', $surgeryRequest->patient_id) }}" class="btn btn-sm btn-outline-primary" style="font-size: 0.8rem;">
                        <i class="bi bi-person-bounding-box me-1"></i>View Full Patient Chart
                    </a>
                @endif
            </div>
            <div class="card-body p-4">
                @if($surgeryRequest->patient)
                    @php $p = $surgeryRequest->patient; @endphp
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="text-muted small">Full Name</div>
                            <div class="fw-bold fs-6">{{ $p->last_name }}, {{ $p->first_name }} {{ $p->middle_name }}</div>
                            <div class="text-muted small">Patient No: {{ $p->patient_no }}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-muted small">Gender / Type</div>
                            <div class="fw-semibold">{{ $p->gender }} ({{ ucfirst($p->patient_type ?? 'Outpatient') }})</div>
                            <div class="text-muted small">DOB: {{ $p->date_of_birth ? $p->date_of_birth->format('M d, Y') : 'N/A' }}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-muted small">Blood Type</div>
                            <span class="badge bg-danger fs-6 mt-1">{{ $p->blood_type ?? 'N/A' }}</span>
                        </div>
                    </div>
                @else
                    <div class="text-muted py-2">No patient record linked to this surgery request.</div>
                @endif
            </div>
        </div>
    </div>

    <!-- Side Column: Schedule Info & Quick Actions -->
    <div class="col-lg-4">
        <!-- Schedule Information Card -->
        <div class="card shadow-sm border-0 mb-4" style="border-radius: 0.75rem; background: var(--card);">
            <div class="card-header border-bottom py-3 px-4" style="background: rgba(0,0,0,0.015);">
                <h6 class="mb-0 fw-bold d-flex align-items-center gap-2" style="font-family: var(--font-display);">
                    <i class="bi bi-calendar-check text-primary"></i>Operating Room Schedule
                </h6>
            </div>
            <div class="card-body p-4">
                @if($surgeryRequest->schedule)
                    @php $sch = $surgeryRequest->schedule; @endphp
                    <div class="mb-3">
                        <div class="text-muted small mb-1" style="font-size: 0.72rem; text-transform: uppercase; font-weight: 600;">Status</div>
                        <span class="badge bg-{{ $sch->status === 'Completed' ? 'success' : ($sch->status === 'Scheduled' ? 'primary' : ($sch->status === 'In Progress' ? 'warning text-dark' : 'secondary')) }}">
                            {{ $sch->status }}
                        </span>
                    </div>

                    <div class="mb-3 border-top pt-3">
                        <div class="text-muted small mb-1" style="font-size: 0.72rem; text-transform: uppercase; font-weight: 600;">Operating Room</div>
                        <div class="fw-bold text-success fs-6"><i class="bi bi-door-open me-1"></i>{{ $sch->operatingRoom->name ?? 'Unassigned' }}</div>
                        <div class="text-muted small">{{ $sch->operatingRoom->location ?? '' }}</div>
                    </div>

                    <div class="mb-3 border-top pt-3">
                        <div class="text-muted small mb-1" style="font-size: 0.72rem; text-transform: uppercase; font-weight: 600;">Scheduled Date & Time</div>
                        <div class="fw-bold text-primary">{{ $sch->scheduled_at ? $sch->scheduled_at->format('F d, Y') : 'Date N/A' }}</div>
                        <div class="fw-bold fs-5">{{ $sch->scheduled_at ? $sch->scheduled_at->format('h:i A') : 'Time N/A' }}</div>
                    </div>

                    <div class="mb-3 border-top pt-3">
                        <div class="text-muted small mb-1" style="font-size: 0.72rem; text-transform: uppercase; font-weight: 600;">OR Readiness</div>
                        <span class="badge bg-{{ ($sch->readiness_status ?? 'Pending Prep') === 'Ready' ? 'success' : (($sch->readiness_status ?? 'Pending Prep') === 'Preparing' ? 'warning text-dark' : 'secondary') }}">
                            {{ $sch->readiness_status ?? 'Pending Prep' }}
                        </span>
                    </div>

                    <div class="mb-3 border-top pt-3">
                        <div class="text-muted small mb-1" style="font-size: 0.72rem; text-transform: uppercase; font-weight: 600;">Surgical Team</div>
                        <div class="fw-semibold">{{ $sch->surgicalTeam->name ?? 'Unassigned' }}</div>
                        <div class="text-muted small">Surgeon: {{ $sch->surgicalTeam->surgeon->name ?? 'N/A' }}</div>
                    </div>

                    <div class="border-top pt-3">
                        <a href="{{ route('surgery.schedules.show', $sch) }}" class="btn btn-sm btn-outline-primary w-100 fw-semibold">
                            <i class="bi bi-eye me-1"></i>View Full Schedule Details
                        </a>
                    </div>
                @else
                    <div class="p-3 mb-3 rounded border text-center" style="background: rgba(100,116,139,0.08); border-color: rgba(100,116,139,0.2) !important;">
                        <i class="bi bi-clock-history text-secondary fs-3 d-block mb-1"></i>
                        <div class="fw-semibold text-secondary">Not Yet Scheduled</div>
                        <div class="text-muted small">This surgery request is pending OR schedule assignment.</div>
                    </div>

                    @if($surgeryRequest->status === 'Pending' && auth()->user()->hasAnyRole(['or-coordinator', 'admin', 'super_admin']))
                        <a href="{{ route('surgery.schedules.create') }}?request={{ $surgeryRequest->id }}" class="btn btn-primary w-100 fw-semibold d-flex align-items-center justify-content-center gap-2">
                            <i class="bi bi-calendar-plus"></i> Schedule Surgery Now
                        </a>
                    @endif
                @endif
            </div>
        </div>

        <!-- Action Panel Card -->
        <div class="card shadow-sm border-0" style="border-radius: 0.75rem; background: var(--card);">
            <div class="card-body p-4 d-flex flex-column gap-2">
                @if($surgeryRequest->status === 'Pending')
                    @if(auth()->user()->hasRole('doctor') && (int) $surgeryRequest->doctor_id === (int) auth()->id())
                        <a href="{{ route('surgery.requests.edit', $surgeryRequest) }}" class="btn btn-outline-primary w-100 fw-semibold d-flex align-items-center justify-content-center gap-2">
                            <i class="bi bi-pencil"></i> Edit Surgery Request
                        </a>
                    @endif

                    @if((auth()->user()->hasRole('doctor') && (int) $surgeryRequest->doctor_id === (int) auth()->id()) || auth()->user()->hasAnyRole(['or-coordinator', 'admin', 'super_admin']))
                        <form action="{{ route('surgery.requests.cancel', $surgeryRequest) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn btn-outline-danger w-100 fw-semibold d-flex align-items-center justify-content-center gap-2" data-confirm="Are you sure you want to cancel surgery request {{ $surgeryRequest->request_no }}?">
                                <i class="bi bi-x-circle"></i> Cancel Request
                            </button>
                        </form>
                    @endif
                @endif

                <a href="{{ route('surgery.requests.index') }}" class="btn btn-outline-secondary w-100">
                    <i class="bi bi-arrow-left me-1"></i> Back to Requests List
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
