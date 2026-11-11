@extends('layouts.app')
@section('title', 'Radiology Dashboard')
@section('page-title', 'Radiology Dashboard')
@section('breadcrumb')
    <li class="breadcrumb-item active">Radiology Dashboard</li>
@endsection

@section('content')

{{-- ── Quick Action Bar ── --}}
<div class="card mb-4 border-0 shadow-sm bg-body workspace-header-card">
    <div class="card-body p-3 workspace-header-body">
        <div class="workspace-header-title">
            <span class="badge bg-info bg-opacity-10 text-info fs-6 px-3 py-2">
                <i class="bi bi-camera-reels me-1"></i> {{ $isRadiologist ? 'Radiologist Interpretation Center' : 'Radiologic Technologist Workspace' }}
            </span>
            <span class="text-muted small d-none d-md-inline dashboard-description">
                {{ $isRadiologist ? 'Review diagnostic imaging studies, record clinical interpretations, and authorize reports.' : 'Manage imaging procedure scheduling, technologist workflow, and image acquisition.' }}
            </span>
        </div>
        <div class="workspace-header-actions">
            <a href="{{ route('radiology.requests.index') }}" class="btn btn-sm btn-primary">
                <i class="bi bi-images me-1"></i> View Imaging Requests
            </a>
            @if(($isRadiologist || auth()->user()->hasAnyRole(['admin', 'doctor', 'rad-tech'])) && Route::has('radiology.reports.index'))
            <a href="{{ route('radiology.reports.index') }}" class="btn btn-sm btn-outline-info">
                <i class="bi bi-file-earmark-medical me-1"></i> View Radiology Reports
            </a>
            @endif
        </div>
    </div>
</div>

{{-- ── Stat Cards (Role-Aware 5 Operational KPIs) ── --}}
@if($isRadiologist)
<div class="balanced-grid balanced-grid-5 mb-4">
    {{-- Card 1: Reports Awaiting Review --}}
    <a href="{{ route('radiology.reports.index', ['status' => 'Draft']) }}" class="text-decoration-none">
        <div class="card border-0 shadow-sm h-100 card-hover-elevate transition-all">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Reports Awaiting Review</span>
                    <div class="bg-warning bg-opacity-10 text-warning rounded-circle p-2">
                        <i class="bi bi-file-earmark-medical fs-5"></i>
                    </div>
                </div>
                <h3 class="fw-bold text-dark mb-0">{{ number_format($stats['awaiting_review'] ?? 0) }}</h3>
                <div class="small text-warning mt-2">Review Studies <i class="bi bi-arrow-right"></i></div>
            </div>
        </div>
    </a>

    {{-- Card 2: Reports Under Review --}}
    <a href="{{ route('radiology.reports.index', ['status' => 'Draft']) }}" class="text-decoration-none">
        <div class="card border-0 shadow-sm h-100 card-hover-elevate transition-all">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Reports Under Review</span>
                    <div class="bg-info bg-opacity-10 text-info rounded-circle p-2">
                        <i class="bi bi-journal-text fs-5"></i>
                    </div>
                </div>
                <h3 class="fw-bold text-dark mb-0">{{ number_format($stats['under_review'] ?? 0) }}</h3>
                <div class="small text-info mt-2">Active Drafts <i class="bi bi-arrow-right"></i></div>
            </div>
        </div>
    </a>

    {{-- Card 3: Reports Returned for Correction --}}
    <a href="{{ route('radiology.reports.index') }}" class="text-decoration-none">
        <div class="card border-0 shadow-sm h-100 card-hover-elevate transition-all">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Reports Returned for Correction</span>
                    <div class="bg-danger bg-opacity-10 text-danger rounded-circle p-2">
                        <i class="bi bi-arrow-counterclockwise fs-5"></i>
                    </div>
                </div>
                <h3 class="fw-bold text-dark mb-0">{{ number_format($stats['returned_correction'] ?? 0) }}</h3>
                <div class="small text-danger mt-2">Needs Addendum <i class="bi bi-arrow-right"></i></div>
            </div>
        </div>
    </a>

    {{-- Card 4: Reports Awaiting Approval --}}
    <a href="{{ route('radiology.reports.index', ['status' => 'Approved']) }}" class="text-decoration-none">
        <div class="card border-0 shadow-sm h-100 card-hover-elevate transition-all">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Reports Awaiting Approval</span>
                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-2">
                        <i class="bi bi-patch-check fs-5"></i>
                    </div>
                </div>
                <h3 class="fw-bold text-dark mb-0">{{ number_format($stats['awaiting_approval'] ?? 0) }}</h3>
                <div class="small text-primary mt-2">Approve &amp; Sign <i class="bi bi-arrow-right"></i></div>
            </div>
        </div>
    </a>

    {{-- Card 5: Reports Released --}}
    <a href="{{ route('radiology.reports.index', ['status' => 'Released']) }}" class="text-decoration-none">
        <div class="card border-0 shadow-sm h-100 card-hover-elevate transition-all">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Reports Released</span>
                    <div class="bg-success bg-opacity-10 text-success rounded-circle p-2">
                        <i class="bi bi-check2-circle fs-5"></i>
                    </div>
                </div>
                <h3 class="fw-bold text-dark mb-0">{{ number_format($stats['released_reports'] ?? $stats['reports_released'] ?? 0) }}</h3>
                <div class="small text-success mt-2">Official Reports <i class="bi bi-arrow-right"></i></div>
            </div>
        </div>
    </a>
</div>
@else
<div class="balanced-grid balanced-grid-5 mb-4">
    {{-- Card 1: Pending Imaging Requests --}}
    <a href="{{ route('radiology.requests.index', ['status' => 'Pending']) }}" class="text-decoration-none">
        <div class="card border-0 shadow-sm h-100 card-hover-elevate transition-all">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Pending Imaging Requests</span>
                    <div class="bg-warning bg-opacity-10 text-warning rounded-circle p-2">
                        <i class="bi bi-hourglass-split fs-5"></i>
                    </div>
                </div>
                <h3 class="fw-bold text-dark mb-0">{{ number_format($stats['pending_requests'] ?? $stats['pending']) }}</h3>
                <div class="small text-warning mt-2">Schedule Exams <i class="bi bi-arrow-right"></i></div>
            </div>
        </div>
    </a>

    {{-- Card 2: Scheduled Examinations --}}
    <a href="{{ route('radiology.requests.index', ['status' => 'Scheduled']) }}" class="text-decoration-none">
        <div class="card border-0 shadow-sm h-100 card-hover-elevate transition-all">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Scheduled Examinations</span>
                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-2">
                        <i class="bi bi-calendar-event fs-5"></i>
                    </div>
                </div>
                <h3 class="fw-bold text-dark mb-0">{{ number_format($stats['scheduled'] ?? 0) }}</h3>
                <div class="small text-primary mt-2">Booked Queue <i class="bi bi-arrow-right"></i></div>
            </div>
        </div>
    </a>

    {{-- Card 3: Examinations In Progress --}}
    <a href="{{ route('radiology.requests.index', ['status' => 'In Progress']) }}" class="text-decoration-none">
        <div class="card border-0 shadow-sm h-100 card-hover-elevate transition-all">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Examinations In Progress</span>
                    <div class="bg-info bg-opacity-10 text-info rounded-circle p-2">
                        <i class="bi bi-cpu-fill fs-5"></i>
                    </div>
                </div>
                <h3 class="fw-bold text-dark mb-0">{{ number_format($stats['in_progress'] ?? 0) }}</h3>
                <div class="small text-info mt-2">Active Scans <i class="bi bi-arrow-right"></i></div>
            </div>
        </div>
    </a>

    {{-- Card 4: Completed Examinations --}}
    <a href="{{ route('radiology.requests.index', ['status' => 'Completed']) }}" class="text-decoration-none">
        <div class="card border-0 shadow-sm h-100 card-hover-elevate transition-all">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Completed Examinations</span>
                    <div class="bg-success bg-opacity-10 text-success rounded-circle p-2">
                        <i class="bi bi-check2-circle fs-5"></i>
                    </div>
                </div>
                <h3 class="fw-bold text-dark mb-0">{{ number_format($stats['completed_exams'] ?? $stats['completed']) }}</h3>
                <div class="small text-success mt-2">{{ number_format($stats['today_completed'] ?? 0) }} Today <i class="bi bi-arrow-right"></i></div>
            </div>
        </div>
    </a>

    {{-- Card 5: Reports to Prepare --}}
    <a href="{{ route('radiology.reports.index') }}" class="text-decoration-none">
        <div class="card border-0 shadow-sm h-100 card-hover-elevate transition-all">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Reports to Prepare</span>
                    <div class="bg-danger bg-opacity-10 text-danger rounded-circle p-2">
                        <i class="bi bi-file-earmark-plus fs-5"></i>
                    </div>
                </div>
                <h3 class="fw-bold text-dark mb-0">{{ number_format($stats['reports_to_prepare'] ?? 0) }}</h3>
                <div class="small text-danger mt-2">Awaiting Draft <i class="bi bi-arrow-right"></i></div>
            </div>
        </div>
    </a>
</div>
@endif

@if($isRadiologist)
{{-- ── 3. Radiologist Primary Work Queue: Studies Awaiting Interpretation ── --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-file-text me-2 text-warning"></i>Studies Awaiting Interpretation</h6>
        <a href="{{ route('radiology.reports.index') }}" class="btn btn-sm btn-outline-primary">
            View All Reports <i class="bi bi-arrow-right ms-1"></i>
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Study / Request No</th>
                        <th>Patient</th>
                        <th>Ordering Doctor</th>
                        <th>Modality</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                {{-- 1. Completed requests without a report yet --}}
                @foreach($completedStudiesAwaitingReport as $req)
                    <tr>
                        <td>
                            <a href="{{ route('radiology.requests.show', $req) }}" class="fw-semibold text-decoration-none">
                                {{ $req->request_no }}
                            </a>
                        </td>
                        <td>
                            <div class="fw-semibold">{{ $req->patient->last_name }}, {{ $req->patient->first_name }}</div>
                            <span class="text-muted small">MRN: {{ $req->patient->patient_no }}</span>
                        </td>
                        <td>{{ $req->doctor->name ?? 'Dr. Staff' }}</td>
                        <td><span class="badge bg-secondary font-monospace">{{ $req->modality }}</span></td>
                        <td><span class="badge bg-success">Ready for Interpretation</span></td>
                        <td class="text-muted small">{{ $req->completed_at?->format('M d, Y H:i') ?? $req->updated_at->format('M d, Y H:i') }}</td>
                        <td class="text-end">
                            <div class="table-actions justify-content-end">
                                <a href="{{ route('radiology.reports.create') }}?radiology_request_id={{ $req->id }}" class="table-action-btn action-view" title="Interpret Study" aria-label="Interpret Study">
                                    <i class="bi bi-journal-medical"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                @endforeach

                {{-- 2. Draft / Approved reports in progress --}}
                @foreach($pendingReports as $rpt)
                    <tr>
                        <td>
                            <a href="{{ route('radiology.reports.show', $rpt) }}" class="fw-semibold text-decoration-none">
                                {{ $rpt->report_no ?? 'RPT-'.$rpt->id }}
                            </a>
                        </td>
                        <td>
                            <div class="fw-semibold">
                                {{ $rpt->radiologyRequest->patient->last_name ?? 'N/A' }}, {{ $rpt->radiologyRequest->patient->first_name ?? '' }}
                            </div>
                            <span class="text-muted small">MRN: {{ $rpt->radiologyRequest->patient->patient_no ?? 'N/A' }}</span>
                        </td>
                        <td>{{ $rpt->radiologyRequest->doctor->name ?? 'Dr. Staff' }}</td>
                        <td>
                            <span class="badge bg-secondary font-monospace">{{ $rpt->radiologyRequest->modality ?? 'General' }}</span>
                        </td>
                        <td>
                            <span class="badge bg-{{ $rpt->status === 'Released' ? 'success' : ($rpt->status === 'Draft' ? 'warning text-dark' : 'info') }}">
                                {{ $rpt->status }} Report
                            </span>
                        </td>
                        <td class="text-muted small">{{ $rpt->created_at->format('M d, Y H:i') }}</td>
                        <td class="text-end">
                            <div class="table-actions justify-content-end">
                                <a href="{{ route('radiology.reports.show', $rpt) }}" class="table-action-btn action-edit" title="Review / Edit Report" aria-label="Review / Edit Report">
                                    <i class="bi bi-pencil-square"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                @endforeach

                @if($completedStudiesAwaitingReport->isEmpty() && $pendingReports->isEmpty())
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="bi bi-file-check fs-3 d-block mb-1 text-success"></i>
                            No radiology studies currently awaiting interpretation. You're all caught up!
                        </td>
                    </tr>
                @endif
                </tbody>
            </table>
        </div>
    </div>
</div>
@else
{{-- ── 3. Radiologic Technologist Work Queue: Today's Imaging Queue ── --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-camera me-2 text-primary"></i>Today's Imaging Queue</h6>
        <a href="{{ route('radiology.requests.index') }}" class="btn btn-sm btn-outline-primary">
            View Full Queue <i class="bi bi-arrow-right ms-1"></i>
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Request No</th>
                        <th>Patient</th>
                        <th>Modality</th>
                        <th>Ordering Doctor</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($recentRequests as $req)
                    <tr>
                        <td>
                            <a href="{{ route('radiology.requests.show', $req) }}" class="fw-semibold text-decoration-none">
                                {{ $req->request_no }}
                            </a>
                        </td>
                        <td>
                            <div class="fw-semibold">{{ $req->patient->last_name }}, {{ $req->patient->first_name }}</div>
                            <span class="text-muted small">MRN: {{ $req->patient->patient_no }}</span>
                        </td>
                        <td><span class="badge bg-secondary font-monospace">{{ $req->modality }}</span></td>
                        <td>{{ $req->doctor->name ?? 'Dr. Staff' }}</td>
                        <td>
                            @if($req->priority === 'STAT')
                                <span class="badge bg-danger">STAT</span>
                            @elseif($req->priority === 'Urgent')
                                <span class="badge bg-warning text-dark">Urgent</span>
                            @else
                                <span class="badge bg-secondary">{{ $req->priority }}</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-{{ in_array($req->status, ['Completed', 'Released']) ? 'success' : ($req->status === 'In Progress' ? 'primary' : ($req->status === 'Scheduled' ? 'info' : 'warning')) }}">
                                {{ $req->status }}
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="table-actions justify-content-end">
                                <a href="{{ route('radiology.requests.show', $req) }}" class="table-action-btn action-view" title="Open Imaging Procedure" aria-label="Open Imaging Procedure">
                                    <i class="bi bi-folder2-open"></i>
                                </a>
                                @if($req->status === 'Pending' && auth()->user()->hasAnyRole(['admin', 'rad-tech']))
                                    <form method="POST" action="{{ route('radiology.requests.schedule', $req) }}" class="d-inline">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="table-action-btn action-success" title="Schedule Procedure" aria-label="Schedule Procedure"><i class="bi bi-calendar-event"></i></button>
                                    </form>
                                @elseif($req->status === 'Scheduled' && auth()->user()->hasAnyRole(['admin', 'rad-tech']))
                                    <form method="POST" action="{{ route('radiology.requests.start', $req) }}" class="d-inline">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="table-action-btn action-view" title="Start Procedure" aria-label="Start Procedure"><i class="bi bi-play-circle"></i></button>
                                    </form>
                                @elseif(in_array($req->status, ['Scheduled', 'In Progress']) && auth()->user()->hasAnyRole(['admin', 'rad-tech']))
                                    <form method="POST" action="{{ route('radiology.requests.complete', $req) }}" class="d-inline" onsubmit="return confirm('Complete procedure and send for interpretation?');">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="table-action-btn action-info" title="Complete Procedure" aria-label="Complete Procedure"><i class="bi bi-check-circle"></i></button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="bi bi-check2-circle fs-3 d-block mb-1 text-success"></i>
                            No imaging procedures in queue. You're all caught up!
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

{{-- ── 4. Operational Metrics & Activity Row ── --}}
<div class="row g-3 mb-4">
    {{-- Status Donut --}}
    <div class="col-md-4">
        <div class="chart-card h-100">
            <div class="chart-header">
                <div>
                    <h3 class="chart-title"><i class="bi bi-pie-chart-fill me-2 text-success"></i>Request Status Breakdown</h3>
                    <p class="chart-subtitle">Status breakdown across pending, scheduled, and completed</p>
                </div>
            </div>
            <div class="chart-container" style="height: 260px;">
                <canvas id="radStatusDonut"></canvas>
            </div>
        </div>
    </div>

    {{-- Today's Activity --}}
    <div class="col-md-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-dark">
                    <i class="bi bi-calendar-check me-2 text-success"></i>Today's Operational Activity
                </h6>
                <span class="badge bg-light text-dark border px-2 py-1">{{ now()->format('M d, Y') }}</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6 col-md-3">
                        <div class="text-center p-3 rounded-3 bg-info bg-opacity-10 border border-info border-opacity-25">
                            <div class="fs-3 fw-bold text-info">{{ $stats['today_scheduled'] }}</div>
                            <div class="small text-muted mt-1">Scheduled Today</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-center p-3 rounded-3 bg-success bg-opacity-10 border border-success border-opacity-25">
                            <div class="fs-3 fw-bold text-success">{{ $stats['today_completed'] }}</div>
                            <div class="small text-muted mt-1">Completed Today</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-center p-3 rounded-3 bg-warning bg-opacity-10 border border-warning border-opacity-25">
                            <div class="fs-3 fw-bold text-warning-emphasis">{{ $stats['reports_pending'] }}</div>
                            <div class="small text-muted mt-1">Awaiting Report</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-center p-3 rounded-3 bg-primary bg-opacity-10 border border-primary border-opacity-25">
                            <div class="fs-3 fw-bold text-primary">{{ $stats['reports_released'] }}</div>
                            <div class="small text-muted mt-1">Reports Released</div>
                        </div>
                    </div>
                </div>
                @php $rate = $stats['total_requests'] > 0 ? round(($stats['completed'] / $stats['total_requests']) * 100) : 0; @endphp
                <div class="mt-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="small text-muted">Radiology Procedure Completion Rate</span>
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
<script type="application/json" id="rad-chart-data">
    {"pending": {{ $stats['pending'] }}, "scheduled": {{ $stats['scheduled'] + $stats['in_progress'] }}, "completed": {{ $stats['completed'] }}}
</script>

{{-- ── Radiology Analytics: Request Trend + Modality Distribution + Report Status ── --}}
<div class="row g-4 mb-4">
    <div class="col-lg-5">
        <div class="chart-card h-100">
            <div class="chart-header">
                <div>
                    <h3 class="chart-title"><i class="bi bi-graph-up-arrow me-2 text-success"></i>{{ $isRadiologist ? 'Radiology Report Review Trends' : 'Imaging Examinations Trend' }}</h3>
                    <p class="chart-subtitle">Monthly radiology examination volume over time</p>
                </div>
                <select id="radTrendPeriod" class="form-select form-select-sm" style="width:auto;">
                    <option value="6">Last 6 Months</option>
                    <option value="12">Last 12 Months</option>
                </select>
            </div>
            <div class="chart-container" style="height: 260px;">
                <canvas id="radTrendChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="chart-card h-100">
            <div class="chart-header">
                <div>
                    <h3 class="chart-title"><i class="bi bi-bar-chart-fill me-2 text-info"></i>Modality Distribution</h3>
                    <p class="chart-subtitle">Volume by X-Ray, CT, MRI &amp; Ultrasound</p>
                </div>
            </div>
            <div class="chart-container" style="height: 260px;">
                <canvas id="radModalityChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-3">
        <div class="chart-card h-100">
            <div class="chart-header">
                <div>
                    <h3 class="chart-title"><i class="bi bi-pie-chart-fill me-2 text-success"></i>Report Status Distribution</h3>
                    <p class="chart-subtitle">Draft, Approved &amp; Released</p>
                </div>
            </div>
            <div class="chart-container" style="height: 260px;">
                <canvas id="radReportStatusChart"></canvas>
            </div>
        </div>
    </div>
</div>

{{-- ── Role-Specific Operational Queues & Recent Activities ── --}}
@if($isRadiologist)
<div class="row g-4 mb-4">
    {{-- Pending Report Reviews & Corrections --}}
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-journal-medical me-2 text-warning"></i>Pending Report Reviews</h6>
                <span class="badge bg-warning-subtle text-warning-emphasis">{{ ($pendingReportReviews ?? collect())->count() }}</span>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    @forelse($pendingReportReviews ?? [] as $rev)
                        <div class="list-group-item p-3 border-bottom d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-bold text-dark small">{{ $rev->report_no ?? 'RPT-'.$rev->id }} &bull; {{ $rev->radiologyRequest?->patient?->full_name ?? 'Patient' }}</div>
                                <div class="small text-muted">{{ $rev->radiologyRequest?->modality }} &bull; <span class="badge bg-light text-dark border">{{ $rev->status }}</span></div>
                            </div>
                            <a href="{{ route('radiology.reports.show', $rev) }}" class="btn btn-sm btn-outline-primary py-0 px-2">Review</a>
                        </div>
                    @empty
                        <div class="text-center py-4 text-muted small">No reports awaiting radiologist review.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- Recent Approved & Released Reports --}}
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-patch-check-fill me-2 text-success"></i>Recent Approved Reports</h6>
                <a href="{{ route('radiology.reports.index') }}" class="btn btn-sm btn-outline-secondary py-0">All</a>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    @forelse($recentApprovedReports ?? [] as $apr)
                        <div class="list-group-item p-3 border-bottom d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-bold text-dark small">{{ $apr->radiologyRequest?->patient?->full_name ?? 'Patient' }}</div>
                                <div class="small text-muted">{{ $apr->radiologyRequest?->modality }} &bull; {{ $apr->status }}</div>
                            </div>
                            <a href="{{ route('radiology.reports.show', $apr) }}" class="btn btn-sm btn-outline-success py-0 px-2">View</a>
                        </div>
                    @empty
                        <div class="text-center py-4 text-muted small">No approved or released reports yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- Recent Radiology Activities --}}
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-clock-history me-2 text-info"></i>Recent Radiology Activities</h6>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    @forelse($recentRadActivities ?? [] as $log)
                        <div class="list-group-item p-3 border-bottom">
                            <div class="d-flex justify-content-between align-items-start">
                                <span class="fw-semibold text-dark small">{{ $log->action }}</span>
                                <span class="small text-muted" style="font-size: 0.72rem;">{{ $log->logged_at?->diffForHumans() }}</span>
                            </div>
                            <div class="small text-muted">{{ Str::limit($log->description, 65) }}</div>
                        </div>
                    @empty
                        <div class="text-center py-4 text-muted small">No recent radiology activity logged.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@else
<div class="row g-4 mb-4">
    {{-- Today's Imaging Schedule --}}
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-calendar-event me-2 text-primary"></i>Today's Imaging Schedule</h6>
                <span class="badge bg-primary-subtle text-primary">{{ ($todaysImagingSchedule ?? collect())->count() }}</span>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    @forelse($todaysImagingSchedule ?? [] as $sched)
                        <div class="list-group-item p-3 border-bottom d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-bold text-dark small">{{ $sched->request_no }} &bull; {{ $sched->patient->full_name ?? 'Patient' }}</div>
                                <div class="small text-muted">{{ $sched->modality }} &bull; {{ $sched->status }}</div>
                            </div>
                            <a href="{{ route('radiology.requests.show', $sched) }}" class="btn btn-sm btn-outline-primary py-0 px-2">Open</a>
                        </div>
                    @empty
                        <div class="text-center py-4 text-muted small">No imaging examinations scheduled for today.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- Reports Requiring Preparation --}}
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-file-earmark-plus me-2 text-warning"></i>Reports Requiring Preparation</h6>
                <span class="badge bg-warning-subtle text-warning-emphasis">{{ ($reportsRequiringPreparation ?? collect())->count() }}</span>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    @forelse($reportsRequiringPreparation ?? [] as $prep)
                        <div class="list-group-item p-3 border-bottom d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-bold text-dark small">{{ $prep->request_no }} &bull; {{ $prep->patient->full_name ?? 'Patient' }}</div>
                                <div class="small text-muted">{{ $prep->modality }} ({{ $prep->body_part }})</div>
                            </div>
                            <a href="{{ route('radiology.reports.create', ['radiology_request_id' => $prep->id]) }}" class="btn btn-sm btn-outline-warning py-0 px-2">Prepare</a>
                        </div>
                    @empty
                        <div class="text-center py-4 text-muted small">All completed examinations have reports prepared.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- Recent Imaging Activities --}}
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-clock-history me-2 text-info"></i>Recent Imaging Activities</h6>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    @forelse($recentRadActivities ?? [] as $log)
                        <div class="list-group-item p-3 border-bottom">
                            <div class="d-flex justify-content-between align-items-start">
                                <span class="fw-semibold text-dark small">{{ $log->action }}</span>
                                <span class="small text-muted" style="font-size: 0.72rem;">{{ $log->logged_at?->diffForHumans() }}</span>
                            </div>
                            <div class="small text-muted">{{ Str::limit($log->description, 65) }}</div>
                        </div>
                    @empty
                        <div class="text-center py-4 text-muted small">No recent imaging activities logged.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endif

<script type="application/json" id="rad-trend-6m">@json($radTrend6m)</script>
<script type="application/json" id="rad-trend-12m">@json($radTrend12m)</script>
<script type="application/json" id="rad-modality-data">@json($radModalityDistribution ?? [])</script>
<script type="application/json" id="rad-report-breakdown">@json($radReportBreakdown)</script>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    function initRadChart() {
        if (typeof Chart === 'undefined' || typeof window.HIMSChart === 'undefined') return;
        const jsonEl = document.getElementById('rad-chart-data');
        const canvas = document.getElementById('radStatusDonut');
        if (!jsonEl || !canvas) return;

        const _d = JSON.parse(jsonEl.textContent);
        const total = (_d.pending || 0) + (_d.scheduled || 0) + (_d.completed || 0);

        if (total > 0) {
            window.HIMSChart.createDoughnutChart(
                canvas,
                ['Pending', 'Scheduled/In Progress', 'Completed'],
                [_d.pending, _d.scheduled, _d.completed],
                ['#2196F3', '#FFB300', '#4CAF50']
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
        document.addEventListener('DOMContentLoaded', initRadChart);
    } else {
        initRadChart();
    }
})();
</script>
<script>
(function () {
    if (typeof window.HIMSChart === 'undefined') return;
    const t6  = JSON.parse(document.getElementById('rad-trend-6m').textContent);
    const t12 = JSON.parse(document.getElementById('rad-trend-12m').textContent);
    const rpt = JSON.parse(document.getElementById('rad-report-breakdown').textContent);

    let radTrendChart = null;
    function renderRadTrend(data) {
        const ctx = document.getElementById('radTrendChart');
        if (!ctx) return;
        if (radTrendChart) { radTrendChart.destroy(); radTrendChart = null; }
        if (!data || data.length === 0) return;
        radTrendChart = window.HIMSChart.createLineChart(ctx, 'Radiology Requests', data.map(d => d.label), data.map(d => d.total), 'requests');
    }
    renderRadTrend(t6);
    const sel = document.getElementById('radTrendPeriod');
    if (sel) sel.addEventListener('change', () => renderRadTrend(sel.value === '12' ? t12 : t6));

    const rptCtx = document.getElementById('radReportStatusChart');
    if (rptCtx && rpt.some(r => r.count > 0)) {
        window.HIMSChart.createDoughnutChart(
            rptCtx,
            rpt.map(r => r.label),
            rpt.map(r => r.count),
            { 'Draft': '#9E9E9E', 'Approved': '#1976D2', 'Released': '#4CAF50' }
        );
    }

    const modEl = document.getElementById('rad-modality-data');
    const modCtx = document.getElementById('radModalityChart');
    if (modEl && modCtx && typeof Chart !== 'undefined') {
        const modData = JSON.parse(modEl.textContent || '[]');
        if (modData.length > 0) {
            new Chart(modCtx, {
                type: 'bar',
                data: {
                    labels: modData.map(m => m.label),
                    datasets: [{
                        label: 'Examinations',
                        data: modData.map(m => m.count),
                        backgroundColor: ['rgba(14, 165, 233, 0.85)', 'rgba(99, 102, 241, 0.85)', 'rgba(13, 148, 136, 0.85)', 'rgba(245, 158, 11, 0.85)', 'rgba(236, 72, 153, 0.85)'],
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
