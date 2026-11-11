@extends('layouts.app')
@section('title', 'Prescription Management')
@section('page-title', 'Prescription Management')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('pharmacy.dashboard') }}" class="text-decoration-none">Pharmacy</a></li>
    <li class="breadcrumb-item active">Prescription Management</li>
@endsection

@section('content')

{{-- ── Workspace Header ── --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body p-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h5 class="fw-bold text-dark mb-1">
                <i class="bi bi-prescription2 me-2 text-primary"></i>Inpatient &amp; Clinical Prescription Management
            </h5>
            <p class="text-muted small mb-0">
                Review, verify, and dispense physician-ordered prescriptions for admitted inpatients and authorized clinical care.
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @if(auth()->user()?->hasRole('pharmacist'))
                <a href="{{ route('pharmacy.dispensing.create') }}" class="btn btn-outline-success btn-sm">
                    <i class="bi bi-capsule-pill me-1"></i>Dispense Queue
                </a>
                @if(Route::has('pharmacy.pos.index'))
                <a href="{{ route('pharmacy.pos.index') }}" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-calculator me-1"></i>Open POS
                </a>
                @endif
            @endif
            @if(auth()->user()?->hasRole('doctor'))
                <a href="{{ route('pharmacy.prescriptions.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle me-1"></i>New Prescription
                </a>
            @endif
        </div>
    </div>
</div>

{{-- ── Queue Navigation Tabs ── --}}
@php
    $activeQueue = request('queue', '');
    $queueTabs = [
        ''                     => ['label' => 'All Prescriptions',     'count' => $counts['all'] ?? 0,                  'icon' => 'bi-collection'],
        'inpatient'            => ['label' => 'Inpatient Queue',       'count' => $counts['inpatient'] ?? 0,            'icon' => 'bi-hospital'],
        'pending_verification' => ['label' => 'Pending Verification',  'count' => $counts['pending_verification'] ?? 0, 'icon' => 'bi-hourglass-split'],
        'verified'             => ['label' => 'Verified',              'count' => $counts['verified'] ?? 0,             'icon' => 'bi-shield-check'],
        'pending_dispensing'   => ['label' => 'Pending Dispensing',    'count' => $counts['pending_dispensing'] ?? 0,   'icon' => 'bi-box-arrow-right'],
        'partially_dispensed'  => ['label' => 'Partially Dispensed',   'count' => $counts['partially_dispensed'] ?? 0,  'icon' => 'bi-pie-chart'],
        'completed'            => ['label' => 'Completed Dispensing',  'count' => $counts['completed'] ?? 0,            'icon' => 'bi-check2-all'],
        'rejected_returned'    => ['label' => 'Rejected / Returned',   'count' => $counts['rejected_returned'] ?? 0,    'icon' => 'bi-x-octagon'],
    ];
@endphp

<div class="d-flex flex-wrap gap-1 mb-3">
    @foreach($queueTabs as $qKey => $tab)
        @php
            $isActiveTab = ($activeQueue === $qKey && !request()->filled('status'));
            $tabUrl = $qKey === ''
                ? route('pharmacy.prescriptions.index', array_filter(['search' => request('search')]))
                : route('pharmacy.prescriptions.index', array_filter(['queue' => $qKey, 'search' => request('search')]));
        @endphp
        <a href="{{ $tabUrl }}"
           class="btn btn-sm {{ $isActiveTab ? 'btn-primary shadow-sm' : 'btn-light border text-secondary' }} d-inline-flex align-items-center gap-1">
            <i class="bi {{ $tab['icon'] }}"></i>
            <span>{{ $tab['label'] }}</span>
            <span class="badge {{ $isActiveTab ? 'bg-white text-primary' : 'bg-secondary bg-opacity-10 text-dark' }} ms-1">{{ $tab['count'] }}</span>
        </a>
    @endforeach
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex flex-wrap gap-2 align-items-center">
        <form class="d-flex flex-wrap gap-2 flex-grow-1" method="GET" id="filter-form">
            @if(request('queue'))
                <input type="hidden" name="queue" value="{{ request('queue') }}">
            @endif
            <div class="input-group input-group-sm" style="max-width:280px">
                <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="search" id="filter-search" class="form-control" placeholder="Rx no, patient, ward, medicine…" value="{{ request('search') }}">
            </div>
            <select name="patient_type" id="filter-patient-type" class="form-select form-select-sm" style="max-width:160px">
                <option value="">All Admission Types</option>
                <option value="Inpatient" {{ request('patient_type') === 'Inpatient' ? 'selected' : '' }}>Inpatient (Admitted)</option>
                <option value="Outpatient" {{ request('patient_type') === 'Outpatient' ? 'selected' : '' }}>Outpatient</option>
            </select>
            <select name="status" id="filter-status" class="form-select form-select-sm" style="max-width:180px">
                <option value="">All Statuses</option>
                @foreach($statuses as $s)<option value="{{ $s }}" {{ request('status')===$s?'selected':'' }}>{{ $s }}</option>@endforeach
            </select>
            <button class="btn btn-primary btn-sm">Filter</button>
            @if(request()->hasAny(['search','status','patient_type','queue']))
                <a href="{{ route('pharmacy.prescriptions.index') }}" class="btn btn-outline-secondary btn-sm" id="filter-clear">Clear</a>
            @endif
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Rx No</th>
                        <th>Patient &amp; Admission</th>
                        <th>Ordering Doctor</th>
                        <th>Diagnosis &amp; Medications</th>
                        <th>Items</th>
                        <th>Status</th>
                        <th>Prescribed</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody id="prescriptions-table-body">
                @forelse($prescriptions as $rx)
                <tr>
                    <td class="ps-3 fw-semibold">
                        <a href="{{ route('pharmacy.prescriptions.show', $rx) }}" class="text-decoration-none">{{ $rx->prescription_no }}</a>
                    </td>
                    <td>
                        <div class="fw-semibold text-dark">{{ $rx->patient->last_name }}, {{ $rx->patient->first_name }}</div>
                        <div class="d-flex flex-wrap gap-1 align-items-center mt-1">
                            <span class="text-muted extra-small" style="font-size:0.75rem;">{{ $rx->patient->patient_no }}</span>
                            @if(($rx->patient->patient_type ?? 'Outpatient') === 'Inpatient')
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size:0.7rem;">
                                    <i class="bi bi-hospital me-1"></i>Inpatient {{ $rx->patient->ward ? '• '.$rx->patient->ward : '' }} {{ $rx->patient->bed_number ? '('.$rx->patient->bed_number.')' : '' }}
                                </span>
                            @else
                                <span class="badge bg-light text-secondary border" style="font-size:0.7rem;">Outpatient</span>
                            @endif
                        </div>
                    </td>
                    <td>
                        <div class="small fw-semibold">{{ $rx->doctor->name ?? '—' }}</div>
                        @if($rx->verifiedBy)
                            <div class="text-muted" style="font-size:0.72rem;">Verified: {{ $rx->verifiedBy->name }}</div>
                        @endif
                    </td>
                    <td>
                        <div class="small fw-semibold text-dark">{{ Str::limit($rx->diagnosis ?: 'Clinical Prescription', 36) }}</div>
                        <div class="text-muted small" style="font-size:0.75rem;">
                            {{ $rx->items->pluck('medication_name')->take(2)->implode(', ') }}{{ $rx->items->count() > 2 ? ' +' . ($rx->items->count() - 2) . ' more' : '' }}
                        </div>
                    </td>
                    <td><span class="badge bg-info bg-opacity-10 text-info border border-info-subtle">{{ $rx->items->count() }}</span></td>
                    <td>
                        <span class="badge bg-{{ $rx->statusBadge }}">{{ $rx->status }}</span>
                        @if($rx->rejection_reason && in_array($rx->status, ['Rejected', 'Returned']))
                            <div class="text-danger extra-small mt-1" style="font-size:0.7rem;" title="{{ $rx->rejection_reason }}">
                                {{ Str::limit($rx->rejection_reason, 28) }}
                            </div>
                        @endif
                    </td>
                    <td><small class="text-muted">{{ $rx->prescribed_at?->format('M d, Y H:i') }}</small></td>
                    <td class="text-end pe-3">
                        @php
                            $rowActions = [
                                [
                                    'type' => 'link',
                                    'url' => route('pharmacy.prescriptions.show', $rx),
                                    'title' => 'View Prescription',
                                    'icon' => 'bi-eye',
                                    'btn_class' => 'action-view',
                                    'text_class' => 'text-primary'
                                ]
                            ];
                            if (in_array($rx->status, ['Pending', 'Returned']) && auth()->user()->hasRole('pharmacist')) {
                                $rowActions[] = [
                                    'type' => 'form',
                                    'url' => route('pharmacy.prescriptions.verify', $rx),
                                    'method' => 'PATCH',
                                    'title' => 'Verify Prescription',
                                    'icon' => 'bi-check-lg',
                                    'btn_class' => 'action-success',
                                    'text_class' => 'text-success'
                                ];
                            }
                            if (in_array($rx->status, ['Verified', 'Partially Dispensed']) && auth()->user()->hasRole('pharmacist')) {
                                $rowActions[] = [
                                    'type' => 'link',
                                    'url' => route('pharmacy.dispensing.create', ['rx' => $rx->id]),
                                    'title' => 'Dispense Medication',
                                    'icon' => 'bi-capsule-pill',
                                    'btn_class' => 'action-success',
                                    'text_class' => 'text-success'
                                ];
                            }
                            if (in_array($rx->status, ['Pending', 'Returned']) && auth()->user()->hasRole('doctor') && (int)$rx->doctor_id === (int)auth()->id()) {
                                $rowActions[] = [
                                    'type' => 'link',
                                    'url' => route('pharmacy.prescriptions.edit', $rx),
                                    'title' => 'Edit Prescription',
                                    'icon' => 'bi-pencil',
                                    'btn_class' => 'action-edit',
                                    'text_class' => 'text-success'
                                ];
                            }
                            $actionCount = count($rowActions);
                            $directLimit = ($actionCount <= 3) ? $actionCount : 2;
                        @endphp
                        <div class="table-actions justify-content-end">
                            @foreach(array_slice($rowActions, 0, $directLimit) as $act)
                                @if($act['type'] === 'link')
                                    <a href="{{ $act['url'] }}" class="table-action-btn {{ $act['btn_class'] }}" title="{{ $act['title'] }}" aria-label="{{ $act['title'] }}">
                                        <i class="bi {{ $act['icon'] }}"></i>
                                    </a>
                                @elseif($act['type'] === 'form')
                                    <form action="{{ $act['url'] }}" method="POST" class="d-inline">
                                        @csrf
                                        @method($act['method'] ?? 'POST')
                                        <button type="submit" class="table-action-btn {{ $act['btn_class'] }}" title="{{ $act['title'] }}" aria-label="{{ $act['title'] }}" @if(!empty($act['confirm'])) data-confirm="{{ $act['confirm'] }}" @endif>
                                            <i class="bi {{ $act['icon'] }}"></i>
                                        </button>
                                    </form>
                                @endif
                            @endforeach
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center py-4 text-muted">No prescriptions found matching the selected filter.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div id="prescriptions-pagination-container">
        @if($prescriptions->hasPages())<div class="card-footer bg-white">{{ $prescriptions->links() }}</div>@endif
    </div>
</div>

{{-- ── Recent Dispensing History Panel ── --}}
@if(isset($recentDispensings) && $recentDispensings->isNotEmpty())
<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-clock-history me-2 text-success"></i>Recent Dispensing History</h6>
        @if(auth()->user()?->hasAnyRole(['pharmacist', 'admin']))
            <a href="{{ route('pharmacy.dispensing.index') }}" class="btn btn-sm btn-outline-secondary">Full Dispensing Log</a>
        @endif
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Dispensing ID</th>
                        <th>Rx No</th>
                        <th>Patient</th>
                        <th>Medication</th>
                        <th>Qty Dispensed</th>
                        <th>Lot / Expiry</th>
                        <th>Pharmacist</th>
                        <th class="pe-3">Dispensed At</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentDispensings as $disp)
                        <tr>
                            <td class="ps-3 fw-semibold">#DSP-{{ str_pad($disp->id, 5, '0', STR_PAD_LEFT) }}</td>
                            <td>
                                @if($disp->prescriptionItem?->prescription)
                                    <a href="{{ route('pharmacy.prescriptions.show', $disp->prescriptionItem->prescription) }}" class="text-decoration-none">
                                        {{ $disp->prescriptionItem->prescription->prescription_no }}
                                    </a>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $disp->prescriptionItem?->prescription?->patient?->full_name ?? '—' }}</td>
                            <td class="fw-semibold">{{ $disp->prescriptionItem?->medication_name ?? '—' }}</td>
                            <td><span class="badge bg-success-subtle text-success">{{ $disp->quantity_dispensed }} unit(s)</span></td>
                            <td class="small text-muted"><code>{{ $disp->lot_number }}</code> &bull; {{ $disp->expiry_date?->format('M Y') }}</td>
                            <td class="small">{{ $disp->pharmacist?->name ?? '—' }}</td>
                            <td class="pe-3 small text-muted">{{ $disp->dispensed_at?->format('M d, Y H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

@endsection
