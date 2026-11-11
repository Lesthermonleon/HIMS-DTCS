@extends('layouts.app')
@section('title', 'My Appointments — Patient Portal')
@section('page-title', 'My Consultation Appointments')

@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-transparent d-flex flex-wrap justify-content-between align-items-center py-3 gap-2">
        <div>
            <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-calendar-check me-2 text-primary"></i>Appointment Schedule</h5>
            <small class="text-muted">Review your requested, confirmed, and historical doctor consultations</small>
        </div>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#bookAppointmentModal">
            <i class="bi bi-calendar-plus me-1"></i>Request New Appointment
        </button>
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
                <p class="mt-2 mb-3">You have no appointment records on file.</p>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bookAppointmentModal">
                    <i class="bi bi-calendar-plus me-1"></i>Book Your First Consultation
                </button>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Appointment No</th>
                            <th>Attending Physician</th>
                            <th>Department</th>
                            <th>Date & Time</th>
                            <th>Consultation Type</th>
                            <th>Reason / Symptoms</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($appointments as $apt)
                            <tr>
                                <td class="font-monospace fw-semibold">{{ $apt->appointment_no }}</td>
                                <td>
                                    <div class="fw-bold text-dark">Dr. {{ $apt->doctor?->name ?? 'Physician Assigned' }}</div>
                                </td>
                                <td>{{ $apt->department ?? 'General Clinic' }}</td>
                                <td>
                                    <div>{{ $apt->scheduled_at->format('M d, Y') }}</div>
                                    <small class="text-muted">{{ $apt->scheduled_at->format('g:i A') }} ({{ $apt->duration_minutes }}m)</small>
                                </td>
                                <td>{{ $apt->type }}</td>
                                <td>
                                    <span class="text-secondary small">{{ Str::limit($apt->reason ?? 'None specified', 60) }}</span>
                                </td>
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
                                    @if(in_array($apt->status, ['Requested', 'Confirmed'], true))
                                        <form action="{{ route('patient.appointments.cancel', $apt) }}" method="POST" onsubmit="return confirm('Cancel this appointment?');">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Cancel</button>
                                        </form>
                                    @else
                                        <span class="small text-muted">&mdash;</span>
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

{{-- MODAL: BOOK APPOINTMENT --}}
<div class="modal fade" id="bookAppointmentModal" tabindex="-1" aria-labelledby="bookAppointmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('patient.appointments.book') }}" method="POST">
                @csrf
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold" id="bookAppointmentModalLabel">
                        <i class="bi bi-calendar-plus text-primary me-2"></i>Book Consultation Appointment
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Select Doctor / Specialist <span class="text-danger">*</span></label>
                        <select name="doctor_id" class="form-select" required>
                            <option value="">Select a Doctor...</option>
                            @foreach($doctors as $doc)
                                <option value="{{ $doc->id }}">Dr. {{ $doc->name }} ({{ $doc->department ?? 'General Medicine' }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Preferred Date & Time <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="scheduled_at" class="form-control" min="{{ now()->addHours(2)->format('Y-m-d\TH:i') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Consultation Type <span class="text-danger">*</span></label>
                            <select name="type" class="form-select" required>
                                <option value="In-Person Consultation">In-Person Consultation</option>
                                <option value="Follow-up Visit">Follow-up Visit</option>
                                <option value="Diagnostic Review">Diagnostic Results Review</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-semibold">Reason for Visit / Symptoms</label>
                        <textarea name="reason" rows="3" class="form-control" placeholder="Describe symptoms or clinical concern..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i>Confirm Booking Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
