@extends('layouts.app')
@section('title', 'Patient Healthcare Portal — DTCS HIMS')
@section('page-title', 'My Health Dashboard')

@section('content')
<div class="row g-3">

    {{-- ══════════════════════════════════════════════════════════════
         TOP WELCOME BANNER & PATIENT IDENTITY
    ══════════════════════════════════════════════════════════════════ --}}
    <div class="col-12">
        <div class="card bg-success-subtle border-success-subtle shadow-sm mb-1">
            <div class="card-body p-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center shadow" style="width: 56px; height: 56px; font-size: 1.5rem;">
                        <i class="bi bi-person-heart"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <h4 class="mb-0 fw-bold text-dark">Welcome, {{ $patient->full_name }}</h4>
                            <span class="badge bg-success">{{ $patient->patient_type }}</span>
                        </div>
                        <p class="text-muted mb-0 small mt-1">
                            Patient Record ID: <strong class="text-dark font-monospace">{{ $patient->patient_no }}</strong> · 
                            DOB: <span class="text-dark">{{ $patient->date_of_birth?->format('M d, Y') ?? 'N/A' }}{{ $patient->date_of_birth ? ' (' . $patient->date_of_birth->age . ' yrs)' : '' }}</span> · 
                            Blood Type: <span class="text-dark">{{ $patient->blood_type ?? 'Unspecified' }}</span>
                        </p>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bookAppointmentModal">
                        <i class="bi bi-calendar-plus me-1"></i>Book Appointment
                    </button>
                    <a href="{{ route('patient.profile') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-person-gear me-1"></i>My Profile
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         SUMMARY METRIC CARDS (5 Patient Portal KPIs)
    ══════════════════════════════════════════════════════════════════ --}}
    <div class="col-12">
        <div class="balanced-grid balanced-grid-5">
            {{-- Card 1: Upcoming Appointments --}}
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted small fw-semibold text-uppercase">Upcoming Appointments</span>
                            <h3 class="mb-0 mt-1 fw-bold text-dark">{{ $upcomingAppointments->count() }}</h3>
                        </div>
                        <div class="bg-primary-subtle text-primary p-3 rounded-circle">
                            <i class="bi bi-calendar-check fs-4"></i>
                        </div>
                    </div>
                    <div class="mt-2 pt-2 border-top">
                        <a href="{{ route('patient.appointments') }}" class="text-decoration-none small text-primary fw-medium">
                            View schedule <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            {{-- Card 2: Pending Laboratory Results --}}
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted small fw-semibold text-uppercase">Pending Laboratory Results</span>
                            <h3 class="mb-0 mt-1 fw-bold text-info">{{ $pendingLabCount ?? 0 }}</h3>
                        </div>
                        <div class="bg-info-subtle text-info p-3 rounded-circle">
                            <i class="bi bi-clipboard2-pulse fs-4"></i>
                        </div>
                    </div>
                    <div class="mt-2 pt-2 border-top d-flex justify-content-between align-items-center">
                        <a href="{{ route('patient.results') }}" class="text-decoration-none small text-info fw-medium">
                            {{ $releasedLabCount }} Released <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            {{-- Card 3: Pending Imaging Reports --}}
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted small fw-semibold text-uppercase">Pending Imaging Reports</span>
                            <h3 class="mb-0 mt-1 fw-bold text-primary">{{ $pendingRadCount ?? 0 }}</h3>
                        </div>
                        <div class="bg-primary-subtle text-primary p-3 rounded-circle">
                            <i class="bi bi-camera fs-4"></i>
                        </div>
                    </div>
                    <div class="mt-2 pt-2 border-top">
                        <a href="{{ route('patient.results') }}" class="text-decoration-none small text-primary fw-medium">
                            {{ $releasedRadCount }} Released <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            {{-- Card 4: Active Prescriptions --}}
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted small fw-semibold text-uppercase">Active Prescriptions</span>
                            <h3 class="mb-0 mt-1 fw-bold text-warning">{{ $activePrescriptionsCount }}</h3>
                        </div>
                        <div class="bg-warning-subtle text-warning p-3 rounded-circle">
                            <i class="bi bi-capsule fs-4"></i>
                        </div>
                    </div>
                    <div class="mt-2 pt-2 border-top">
                        <a href="{{ route('patient.prescriptions') }}" class="text-decoration-none small text-warning fw-medium">
                            View medications <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            {{-- Card 5: Available Clinical Reports --}}
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted small fw-semibold text-uppercase">Available Clinical Reports</span>
                            <h3 class="mb-0 mt-1 fw-bold text-success">{{ $availableClinicalReportsCount ?? ($releasedLabCount + $releasedRadCount) }}</h3>
                        </div>
                        <div class="bg-success-subtle text-success p-3 rounded-circle">
                            <i class="bi bi-file-earmark-medical fs-4"></i>
                        </div>
                    </div>
                    <div class="mt-2 pt-2 border-top">
                        <a href="{{ route('patient.results') }}" class="text-decoration-none small text-success fw-medium">
                            View all reports <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         PERSONAL CLINICAL ANALYTICS CHARTS
    ══════════════════════════════════════════════════════════════════ --}}
    <div class="col-lg-8">
        <div class="chart-card h-100">
            <div class="chart-header">
                <div>
                    <h3 class="chart-title"><i class="bi bi-graph-up-arrow me-2 text-success"></i>Personal Clinical Activity</h3>
                    <p class="chart-subtitle">6-month history of your appointments and diagnostic requests</p>
                </div>
            </div>
            <div class="chart-container" style="height: 240px;">
                <canvas id="patientActivityChart"></canvas>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="chart-card h-100">
            <div class="chart-header">
                <div>
                    <h3 class="chart-title"><i class="bi bi-pie-chart-fill me-2 text-primary"></i>Available Result History</h3>
                    <p class="chart-subtitle">Released results, prescriptions &amp; diet plans</p>
                </div>
            </div>
            <div class="chart-container" style="height: 240px;">
                <canvas id="patientResultHistoryChart"></canvas>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         MAIN CONTENT: UPCOMING APPOINTMENTS & RECENT RESULTS
    ══════════════════════════════════════════════════════════════════ --}}
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center py-3">
                <span class="fw-bold"><i class="bi bi-calendar-event me-2 text-primary"></i>My Upcoming Consultations</span>
                <a href="{{ route('patient.appointments') }}" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                @if($upcomingAppointments->isEmpty())
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-calendar-x fs-1 opacity-50"></i>
                        <p class="mt-2 mb-3">No upcoming appointments scheduled.</p>
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#bookAppointmentModal">
                            <i class="bi bi-plus-circle me-1"></i>Request Consultation
                        </button>
                    </div>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach($upcomingAppointments as $apt)
                            <li class="list-group-item p-3">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="fw-bold text-dark">{{ $apt->type }}</span>
                                            <span class="badge {{ $apt->status === 'Confirmed' ? 'bg-success' : 'bg-warning text-dark' }}">{{ $apt->status }}</span>
                                        </div>
                                        <p class="small text-muted mb-1 mt-1">
                                            <i class="bi bi-person-badge me-1"></i>Attending: <strong>Dr. {{ $apt->doctor?->name ?? 'Assigned Physician' }}</strong> ({{ $apt->department ?? 'General Clinic' }})
                                        </p>
                                        @if($apt->reason)
                                            <p class="small text-secondary mb-0 fst-italic">"{{ Str::limit($apt->reason, 80) }}"</p>
                                        @endif
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-light text-dark border font-monospace">{{ $apt->scheduled_at->format('M d, Y') }}</span>
                                        <div class="small fw-semibold text-primary mt-1">{{ $apt->scheduled_at->format('g:i A') }}</div>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center py-3">
                <span class="fw-bold"><i class="bi bi-file-earmark-medical me-2 text-success"></i>Recent Diagnostic Findings (Released)</span>
                <a href="{{ route('patient.results') }}" class="btn btn-sm btn-outline-success">View All</a>
            </div>
            <div class="card-body p-0">
                @if($recentResults->isEmpty())
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-clipboard-check fs-1 opacity-50"></i>
                        <p class="mt-2 mb-0">No released laboratory results on file.</p>
                        <small class="text-muted">Unreleased lab orders undergoing verification are hidden for patient safety.</small>
                    </div>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach($recentResults as $res)
                            <li class="list-group-item p-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="fw-bold text-dark">{{ $res->requestItem?->labTest?->name ?? 'Diagnostic Test' }}</span>
                                        <div class="small text-muted mt-1">
                                            Ordered by: Dr. {{ $res->requestItem?->labRequest?->doctor?->name ?? 'Attending' }} · 
                                            Released: {{ $res->released_at ? $res->released_at->format('M d, Y g:i A') : 'Recently' }}
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                                            <i class="bi bi-check-circle me-1"></i>Released
                                        </span>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         RECENT ACTIVE PRESCRIPTIONS
    ══════════════════════════════════════════════════════════════ --}}
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center py-3">
                <span class="fw-bold"><i class="bi bi-capsule-pill me-2 text-warning"></i>Active Medication Regimens</span>
                <a href="{{ route('patient.prescriptions') }}" class="btn btn-sm btn-outline-warning">View All Prescriptions</a>
            </div>
            <div class="card-body p-0">
                @if($recentPrescriptions->isEmpty())
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-check2-circle fs-2 text-success"></i>
                        <p class="mt-2 mb-0">No active prescriptions currently recorded.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Prescription No</th>
                                    <th>Prescribing Doctor</th>
                                    <th>Medications</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recentPrescriptions as $rx)
                                    <tr>
                                        <td class="font-monospace fw-semibold">{{ $rx->prescription_no }}</td>
                                        <td>Dr. {{ $rx->doctor?->name ?? 'Attending' }}</td>
                                        <td>
                                            @foreach($rx->items as $item)
                                                <span class="badge bg-light text-dark border me-1">
                                                    {{ $item->medicine_name }} ({{ $item->dosage }})
                                                </span>
                                            @endforeach
                                        </td>
                                        <td>{{ $rx->created_at->format('M d, Y') }}</td>
                                        <td>
                                            <span class="badge {{ $rx->status === 'Dispensed' ? 'bg-success' : 'bg-primary' }}">
                                                {{ $rx->status }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         RECENT RADIOLOGY REPORTS & AVAILABLE CLINICAL REPORTS
    ══════════════════════════════════════════════════════════════ --}}
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center py-3">
                <span class="fw-bold"><i class="bi bi-camera me-2 text-info"></i>Recent Radiology Reports</span>
                <a href="{{ route('patient.results') }}" class="btn btn-sm btn-outline-info">View All</a>
            </div>
            <div class="card-body p-0">
                @if(($recentRadReports ?? collect())->isEmpty())
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-camera fs-2 opacity-50"></i>
                        <p class="mt-2 mb-0 small">No released radiology reports on file yet.</p>
                    </div>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach($recentRadReports as $rad)
                            <li class="list-group-item p-3 d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="fw-bold text-dark">{{ $rad->radiologyRequest?->modality ?? 'Imaging' }} — {{ $rad->radiologyRequest?->body_part ?? 'Study' }}</span>
                                    <div class="small text-muted mt-1">
                                        Ordered by: Dr. {{ $rad->radiologyRequest?->doctor?->name ?? 'Attending' }} · 
                                        Released: {{ $rad->released_at ? $rad->released_at->format('M d, Y g:i A') : 'Recently' }}
                                    </div>
                                </div>
                                <span class="badge bg-info-subtle text-info border border-info-subtle">Released</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center py-3">
                <span class="fw-bold"><i class="bi bi-apple me-2 text-success"></i>Available Clinical Reports &amp; Diet Plans</span>
                <a href="{{ route('patient.treatments') }}" class="btn btn-sm btn-outline-success">View Treatment Plans</a>
            </div>
            <div class="card-body p-0">
                @if(($activeDietPlans ?? collect())->isEmpty())
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-journal-medical fs-2 opacity-50"></i>
                        <p class="mt-2 mb-0 small">No active clinical nutrition or treatment plans assigned yet.</p>
                    </div>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach($activeDietPlans as $plan)
                            <li class="list-group-item p-3 d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="fw-bold text-dark">{{ $plan->plan_no ?? 'Diet Plan' }} — {{ $plan->diet_type }}</span>
                                    <div class="small text-muted mt-1">
                                        Calories: {{ $plan->calories_target ? $plan->calories_target.' kcal' : 'Standard' }} · Status: {{ $plan->status }}
                                    </div>
                                </div>
                                <span class="badge bg-success-subtle text-success border border-success-subtle">{{ $plan->status }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>

</div>

{{-- ══════════════════════════════════════════════════════════════
     MODAL: BOOK APPOINTMENT
══════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="bookAppointmentModal" tabindex="-1" aria-labelledby="bookAppointmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('patient.appointments.book') }}" method="POST">
                @csrf
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold" id="bookAppointmentModalLabel">
                        <i class="bi bi-calendar-plus text-primary me-2"></i>Request Consultation Appointment
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 small mb-3">
                        <i class="bi bi-info-circle me-1"></i>Your request will be confirmed by clinic administration within operating hours.
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Attending Physician <span class="text-danger">*</span></label>
                        <select name="doctor_id" class="form-select" required>
                            <option value="">Select a Doctor / Specialist...</option>
                            @foreach($doctors as $doc)
                                <option value="{{ $doc->id }}">Dr. {{ $doc->name }} ({{ $doc->department ?? 'General Clinic' }})</option>
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
                        <textarea name="reason" rows="3" class="form-control" placeholder="Briefly describe your symptoms or reason for this appointment..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i>Submit Appointment Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script type="application/json" id="patient-activity-6m">@json($patientActivityTrend6m ?? ['labels' => [], 'appointments' => [], 'diagnostics' => []])</script>
<script type="application/json" id="patient-result-history">@json($resultHistoryBreakdown ?? [])</script>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    if (typeof Chart === 'undefined') return;

    const actEl = document.getElementById('patient-activity-6m');
    const actCtx = document.getElementById('patientActivityChart');
    if (actEl && actCtx) {
        const actData = JSON.parse(actEl.textContent || '{}');
        if (actData.labels && actData.labels.length > 0) {
            new Chart(actCtx, {
                type: 'line',
                data: {
                    labels: actData.labels,
                    datasets: [
                        {
                            label: 'Appointments',
                            data: actData.appointments || [],
                            borderColor: '#0284c7',
                            backgroundColor: 'rgba(2, 132, 199, 0.12)',
                            tension: 0.35,
                            fill: true
                        },
                        {
                            label: 'Diagnostic & Rx Orders',
                            data: actData.diagnostics || [],
                            borderColor: '#0d9488',
                            backgroundColor: 'rgba(13, 148, 136, 0.12)',
                            tension: 0.35,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } },
                    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                }
            });
        }
    }

    const histEl = document.getElementById('patient-result-history');
    const histCtx = document.getElementById('patientResultHistoryChart');
    if (histEl && histCtx) {
        const histData = JSON.parse(histEl.textContent || '{}');
        const labels = Object.keys(histData);
        const values = Object.values(histData);
        if (labels.length > 0) {
            new Chart(histCtx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values.some(v => v > 0) ? values : [1, 0, 0, 0],
                        backgroundColor: ['#10b981', '#0ea5e9', '#f59e0b', '#0d9488'],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } }
                }
            });
        }
    }
})();
</script>
@endpush
@endsection
