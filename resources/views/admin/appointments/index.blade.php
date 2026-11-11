@extends('layouts.app')
@section('title', 'Outpatient Appointments & Scheduling — DTCS HIMS')
@section('page-title', 'Appointments & Clinic Queue')

@section('content')
<div class="row g-3">

    {{-- STATS CARDS --}}
    <div class="col-md-3 col-sm-6">
        <div class="card shadow-sm border-0">
            <div class="card-body p-3">
                <span class="small text-muted text-uppercase fw-semibold">Total Bookings</span>
                <h3 class="mb-0 mt-1 fw-bold text-dark">{{ $stats['total'] }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card shadow-sm border-0">
            <div class="card-body p-3">
                <span class="small text-muted text-uppercase fw-semibold">Pending Requests</span>
                <h3 class="mb-0 mt-1 fw-bold text-warning">{{ $stats['requested'] }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card shadow-sm border-0">
            <div class="card-body p-3">
                <span class="small text-muted text-uppercase fw-semibold">Scheduled Today</span>
                <h3 class="mb-0 mt-1 fw-bold text-primary">{{ $stats['today'] }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card shadow-sm border-0">
            <div class="card-body p-3">
                <span class="small text-muted text-uppercase fw-semibold">In Consultation</span>
                <h3 class="mb-0 mt-1 fw-bold text-success">{{ $stats['in_progress'] }}</h3>
            </div>
        </div>
    </div>

    {{-- APPOINTMENTS TABLE --}}
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-transparent d-flex flex-wrap justify-content-between align-items-center py-3 gap-2">
                <div>
                    <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-calendar3 me-2 text-primary"></i>Consultation Schedule & Queue</h5>
                    <small class="text-muted">Manage patient bookings, doctor queues, and consultation status transitions</small>
                </div>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newAppointmentModal">
                    <i class="bi bi-calendar-plus me-1"></i>Schedule Appointment
                </button>
            </div>

            {{-- Filter Bar --}}
            <div class="card-body border-bottom bg-light py-2">
                <form method="GET" action="{{ route('admin.appointments.index') }}" class="row g-2 align-items-center">
                    <div class="col-md-3">
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by patient name, no, apt..." value="{{ request('search') }}">
                    </div>
                    <div class="col-md-2">
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All Statuses</option>
                            <option value="Requested" {{ request('status') === 'Requested' ? 'selected' : '' }}>Requested</option>
                            <option value="Confirmed" {{ request('status') === 'Confirmed' ? 'selected' : '' }}>Confirmed</option>
                            <option value="CheckedIn" {{ request('status') === 'CheckedIn' ? 'selected' : '' }}>CheckedIn</option>
                            <option value="In Consultation" {{ request('status') === 'In Consultation' ? 'selected' : '' }}>In Consultation</option>
                            <option value="Completed" {{ request('status') === 'Completed' ? 'selected' : '' }}>Completed</option>
                            <option value="Cancelled" {{ request('status') === 'Cancelled' ? 'selected' : '' }}>Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="doctor_id" class="form-select form-select-sm">
                            <option value="">All Doctors</option>
                            @foreach($doctors as $doc)
                                <option value="{{ $doc->id }}" {{ request('doctor_id') == $doc->id ? 'selected' : '' }}>Dr. {{ $doc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="date" name="date" class="form-control form-control-sm" value="{{ request('date') }}">
                    </div>
                    <div class="col-md-2 d-flex gap-1">
                        <button type="submit" class="btn btn-sm btn-secondary w-100">Filter</button>
                        @if(request()->hasAny(['search', 'status', 'doctor_id', 'date']))
                            <a href="{{ route('admin.appointments.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                        @endif
                    </div>
                </form>
            </div>

            <div class="card-body p-0">
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show m-3 py-2" role="alert">
                        <i class="bi bi-check-circle me-1"></i>{{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
                @if($errors->any())
                    <div class="alert alert-danger m-3 py-2" role="alert">
                        <ul class="mb-0 small">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if($appointments->isEmpty())
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-calendar4-week fs-1 opacity-50"></i>
                        <p class="mt-2 mb-0">No appointment records match the query.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Apt No</th>
                                    <th>Patient</th>
                                    <th>Attending Physician</th>
                                    <th>Date & Time</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($appointments as $apt)
                                    <tr>
                                        <td class="font-monospace fw-semibold">{{ $apt->appointment_no }}</td>
                                        <td>
                                            <div class="fw-bold text-dark">{{ $apt->patient?->full_name ?? 'Patient' }}</div>
                                            <small class="font-monospace text-muted">{{ $apt->patient?->patient_no }}</small>
                                        </td>
                                        <td>Dr. {{ $apt->doctor?->name ?? 'Unassigned' }}</td>
                                        <td>
                                            <div>{{ $apt->scheduled_at->format('M d, Y') }}</div>
                                            <small class="text-muted">{{ $apt->scheduled_at->format('g:i A') }}</small>
                                        </td>
                                        <td>{{ $apt->type }}</td>
                                        <td>
                                            @php
                                                $badgeClass = match($apt->status) {
                                                    'Confirmed'       => 'bg-success',
                                                    'Requested'       => 'bg-warning text-dark',
                                                    'CheckedIn'       => 'bg-info text-dark',
                                                    'In Consultation' => 'bg-primary',
                                                    'Completed'       => 'bg-secondary',
                                                    'Cancelled'       => 'bg-danger',
                                                    default           => 'bg-light text-dark border',
                                                };
                                            @endphp
                                            <span class="badge {{ $badgeClass }}">{{ $apt->status }}</span>
                                        </td>
                                        <td class="text-end">
                                            @php
                                                $allowed = $scheduler->allowedTransitions($apt->status, auth()->user());
                                                $labels = [
                                                    'Confirmed'       => ['Confirm Schedule', 'bi-check-circle', 'text-success'],
                                                    'CheckedIn'       => ['Mark Checked-In', 'bi-box-arrow-in-right', 'text-info'],
                                                    'In Consultation' => ['Call into Consultation', 'bi-arrow-right-circle', 'text-primary'],
                                                    'Completed'       => ['Complete Visit', 'bi-check-all', 'text-success'],
                                                    'Cancelled'       => ['Cancel Appointment', 'bi-x-circle', 'text-danger'],
                                                    'No Show'         => ['Mark No Show', 'bi-person-x', 'text-muted'],
                                                ];
                                            @endphp
                                            @if(empty($allowed))
                                                <span class="small text-muted">No actions</span>
                                            @else
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                        Status
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end">
                                                        @foreach($allowed as $target)
                                                            @if(isset($labels[$target]))
                                                                <li>
                                                                    <form action="{{ route('admin.appointments.status', $apt) }}" method="POST">
                                                                        @csrf @method('PATCH')
                                                                        <input type="hidden" name="status" value="{{ $target }}">
                                                                        <button type="submit" class="dropdown-item {{ $labels[$target][2] }}"><i class="bi {{ $labels[$target][1] }} me-2"></i>{{ $labels[$target][0] }}</button>
                                                                    </form>
                                                                </li>
                                                            @endif
                                                        @endforeach
                                                    </ul>
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($appointments->hasPages())
                        <div class="card-footer bg-transparent py-3">
                            {{ $appointments->links() }}
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>

</div>

{{-- MODAL: NEW APPOINTMENT --}}
<div class="modal fade" id="newAppointmentModal" tabindex="-1" aria-labelledby="newAppointmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('admin.appointments.store') }}" method="POST">
                @csrf
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold" id="newAppointmentModalLabel">
                        <i class="bi bi-calendar-plus text-primary me-2"></i>Schedule Consultation Appointment
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Patient <span class="text-danger">*</span></label>
                        <select name="patient_id" class="form-select" required>
                            <option value="">Select a registered patient...</option>
                            @foreach($patients as $p)
                                <option value="{{ $p->id }}">{{ $p->full_name }} ({{ $p->patient_no }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Attending Doctor <span class="text-danger">*</span></label>
                        <select name="doctor_id" class="form-select" required>
                            <option value="">Select a Doctor...</option>
                            @foreach($doctors as $doc)
                                <option value="{{ $doc->id }}">Dr. {{ $doc->name }} ({{ $doc->department ?? 'General Clinic' }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Date & Time <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="scheduled_at" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Duration (Mins)</label>
                            <input type="number" name="duration_minutes" class="form-control" value="30" min="10" max="120">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Consultation Type <span class="text-danger">*</span></label>
                        <select name="type" class="form-select" required>
                            <option value="In-Person Consultation">In-Person Consultation</option>
                            <option value="Follow-up Visit">Follow-up Visit</option>
                            <option value="Diagnostic Review">Diagnostic Results Review</option>
                            <option value="Pre-Operative Clearance">Pre-Operative Clearance</option>
                        </select>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-semibold">Reason / Clinical Notes</label>
                        <textarea name="reason" rows="2" class="form-control" placeholder="Clinical purpose or chief complaint..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-calendar-check me-1"></i>Save Appointment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
