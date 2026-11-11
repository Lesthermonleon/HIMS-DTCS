@extends('layouts.app')
@section('title', 'Prescription ' . $prescription->prescription_no)
@section('page-title', 'Prescription: ' . $prescription->prescription_no)
@section('content')
<div class="row g-3">
    <div class="col-lg-4">
        {{-- Prescription & Patient Admission Card --}}
        <div class="card mb-3 border-0 shadow-sm">
            <div class="card-header bg-white py-3 fw-bold text-dark d-flex justify-content-between align-items-center">
                <span><i class="bi bi-info-circle me-2 text-primary"></i>Prescription Details</span>
                <span class="badge bg-{{ $prescription->statusBadge }}">{{ $prescription->status }}</span>
            </div>
            <div class="card-body">
                <dl class="row small mb-0">
                    <dt class="col-5 text-muted">Rx No</dt>
                    <dd class="col-7 fw-bold text-primary">{{ $prescription->prescription_no }}</dd>

                    <dt class="col-5 text-muted">Patient</dt>
                    <dd class="col-7 fw-semibold">{{ $prescription->patient->full_name }}</dd>

                    <dt class="col-5 text-muted">Patient No</dt>
                    <dd class="col-7">{{ $prescription->patient->patient_no }}</dd>

                    <dt class="col-5 text-muted">Admission Type</dt>
                    <dd class="col-7">
                        @if(($prescription->patient->patient_type ?? 'Outpatient') === 'Inpatient')
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Inpatient</span>
                        @else
                            <span class="badge bg-light text-secondary border">Outpatient</span>
                        @endif
                    </dd>

                    @if($prescription->patient->ward || $prescription->patient->bed_number)
                        <dt class="col-5 text-muted">Ward / Bed</dt>
                        <dd class="col-7 fw-semibold">{{ $prescription->patient->ward ?? '—' }} {{ $prescription->patient->bed_number ? '• ' . $prescription->patient->bed_number : '' }}</dd>
                    @endif

                    <dt class="col-5 text-muted">Ordering Doctor</dt>
                    <dd class="col-7">{{ $prescription->doctor->name }}</dd>

                    <dt class="col-5 text-muted">Diagnosis</dt>
                    <dd class="col-7">{{ $prescription->diagnosis ?? '—' }}</dd>

                    <dt class="col-5 text-muted">Issued</dt>
                    <dd class="col-7">{{ $prescription->prescribed_at?->format('M d, Y H:i') }}</dd>

                    @if($prescription->verifiedBy)
                        <dt class="col-5 text-muted">Verified By</dt>
                        <dd class="col-7">
                            {{ $prescription->verifiedBy->name }}<br>
                            <small class="text-muted">{{ $prescription->verified_at?->format('M d, Y H:i') }}</small>
                        </dd>
                    @endif

                    @if($prescription->verification_notes)
                        <dt class="col-5 text-muted">Pharmacist Verification Notes</dt>
                        <dd class="col-7 text-success fw-semibold">{{ $prescription->verification_notes }}</dd>
                    @endif

                    @if($prescription->rejectedBy || $prescription->rejection_reason)
                        <dt class="col-5 text-danger">{{ $prescription->status }} By</dt>
                        <dd class="col-7 text-danger">
                            {{ $prescription->rejectedBy?->name ?? 'Pharmacist' }}
                            @if($prescription->rejected_at)
                                <br><small class="text-muted">{{ $prescription->rejected_at->format('M d, Y H:i') }}</small>
                            @endif
                        </dd>
                        <dt class="col-5 text-danger">Reason</dt>
                        <dd class="col-7 text-danger fw-semibold">{{ $prescription->rejection_reason }}</dd>
                    @endif

                    @if($prescription->notes)
                        <dt class="col-5 text-muted">Doctor's Notes</dt>
                        <dd class="col-7">{{ $prescription->notes }}</dd>
                    @endif
                </dl>
            </div>
        </div>

        {{-- Separate POS Payment Status Summary (Never conflated with Dispensing Status) --}}
        @php
            $posTransactions = $prescription->posTransactions ?? collect();
            $completedPos = $posTransactions->where('status', 'Completed');
        @endphp
        <div class="card mb-3 border-0 shadow-sm">
            <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                <span class="small fw-bold text-dark"><i class="bi bi-receipt-cutoff me-1 text-teal"></i>POS Billing Status (Separate)</span>
                @if($completedPos->isNotEmpty())
                    <span class="badge bg-success-subtle text-success border border-success-subtle">Paid ({{ $completedPos->count() }} Txn)</span>
                @else
                    <span class="badge bg-light text-muted border">No POS Sale Recorded</span>
                @endif
            </div>
            <div class="card-body py-2 small">
                @if($completedPos->isNotEmpty())
                    @foreach($completedPos as $txn)
                        <div class="d-flex justify-content-between align-items-center py-1 {{ !$loop->last ? 'border-bottom' : '' }}">
                            <div>
                                <span class="fw-semibold">{{ $txn->transaction_no }}</span>
                                <span class="text-muted">({{ $txn->payment_method }})</span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="fw-bold text-dark">&#8369;{{ number_format((float)$txn->grand_total, 2) }}</span>
                                @if(Route::has('pharmacy.pos.receipt'))
                                    <a href="{{ route('pharmacy.pos.receipt', $txn) }}" class="btn btn-xs btn-outline-secondary py-0 px-1" title="View Receipt"><i class="bi bi-printer"></i></a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="text-muted">Payment status is tracked independently from clinical medication dispensing.</div>
                @endif
            </div>
        </div>

        @php
            $pendingCount = $prescription->items->where('status', 'Pending')->count();
            $totalCount   = $prescription->items->count();
            $isFullyDispensed = ($pendingCount === 0 || $prescription->status === 'Dispensed');
            $isPartiallyDispensed = ($pendingCount > 0 && $pendingCount < $totalCount) || $prescription->status === 'Partially Dispensed';
        @endphp

        <div class="d-flex flex-column gap-2">
            @if(in_array($prescription->status, ['Pending', 'Returned']) && auth()->user()?->hasRole('pharmacist'))
                <div class="card border-0 shadow-sm p-3">
                    <form method="POST" action="{{ route('pharmacy.prescriptions.verify', $prescription) }}">
                        @csrf @method('PATCH')
                        <label class="form-label small fw-semibold text-muted mb-1">Pharmacist Verification Notes (Optional)</label>
                        <textarea name="verification_notes" rows="2" class="form-control form-control-sm mb-2" placeholder="Dosage & interaction review notes...">{{ old('verification_notes', $prescription->verification_notes) }}</textarea>
                        <button class="btn btn-success w-100 shadow-sm fw-semibold">
                            <i class="bi bi-shield-check me-1"></i>Verify Prescription
                        </button>
                    </form>
                </div>
            @endif

            @if(in_array($prescription->status, ['Verified', 'Partially Dispensed']) && $pendingCount > 0 && auth()->user()?->hasRole('pharmacist'))
                <a href="{{ route('pharmacy.dispensing.create') }}?rx={{ $prescription->id }}" class="btn btn-primary shadow-sm fw-bold">
                    <i class="bi bi-box-arrow-down me-1"></i> {{ $isPartiallyDispensed ? 'Dispense Remaining' : 'Dispense Prescription' }}
                </a>
                @if(Route::has('pharmacy.pos.index'))
                <a href="{{ route('pharmacy.pos.index', ['prescription_id' => $prescription->id]) }}" class="btn btn-outline-primary shadow-sm">
                    <i class="bi bi-calculator me-1"></i> Open in Point of Sale (POS)
                </a>
                @endif
            @elseif($isFullyDispensed)
                <div class="alert alert-success d-flex align-items-center mb-0 py-2 small fw-semibold shadow-sm">
                    <i class="bi bi-check-circle-fill me-2 fs-6 text-success"></i> Prescription Fully Dispensed
                </div>
                @if(Route::has('pharmacy.pos.index') && auth()->user()?->hasRole('pharmacist') && $completedPos->isEmpty())
                <a href="{{ route('pharmacy.pos.index', ['prescription_id' => $prescription->id]) }}" class="btn btn-outline-primary btn-sm shadow-sm">
                    <i class="bi bi-calculator me-1"></i> Bill in Point of Sale (POS)
                </a>
                @endif
            @endif

            {{-- Reject / Return Action for Pharmacist (only when no items have been dispensed yet) --}}
            @if(in_array($prescription->status, ['Pending', 'Verified']) && $pendingCount === $totalCount && auth()->user()?->hasRole('pharmacist'))
                <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectPrescriptionModal">
                    <i class="bi bi-x-octagon me-1"></i> Reject / Return Prescription
                </button>
            @endif

            <a href="{{ route('pharmacy.prescriptions.print', $prescription) }}" target="_blank" class="btn btn-outline-secondary shadow-sm"><i class="bi bi-printer me-1"></i>Print Official Prescription (Rx)</a>
            <a href="{{ route('pharmacy.prescriptions.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Prescriptions</a>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 fw-bold text-dark"><i class="bi bi-capsule me-2 text-primary"></i>Prescribed Medications &amp; Dispensing Ledger</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Medication</th>
                                <th>Dosage &amp; Route</th>
                                <th>Frequency</th>
                                <th>Duration</th>
                                <th>Prescribed</th>
                                <th>Dispensed</th>
                                <th class="pe-3">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($prescription->items as $item)
                        @php
                            $dispensedSum = $item->dispensed_quantity;
                            $remainingSum = $item->remaining_quantity;
                        @endphp
                        <tr>
                            <td class="ps-3 fw-bold text-dark">{{ $item->medication_name }}</td>
                            <td>{{ $item->dosage }} <small class="text-muted">({{ $item->route ?? 'Oral' }})</small></td>
                            <td>{{ $item->frequency }}</td>
                            <td>{{ $item->duration }}</td>
                            <td><span class="fw-bold">{{ $item->quantity }}</span></td>
                            <td>
                                <span class="fw-semibold {{ $dispensedSum > 0 ? 'text-success' : 'text-muted' }}">{{ $dispensedSum }}</span>
                                @if($remainingSum > 0 && $dispensedSum > 0)
                                    <span class="badge bg-warning-subtle text-warning-emphasis ms-1">{{ $remainingSum }} rem</span>
                                @endif
                            </td>
                            <td class="pe-3">
                                <span class="badge bg-{{ $item->status==='Dispensed'?'success':($dispensedSum > 0 ? 'info text-dark' : 'warning-subtle text-warning border border-warning') }}">
                                    {{ $item->status === 'Dispensed' ? 'Dispensed' : ($dispensedSum > 0 ? 'Partial' : 'Pending') }}
                                </span>
                            </td>
                        </tr>
                        @if($item->instructions)
                            <tr>
                                <td colspan="7" class="text-muted small ps-3 pt-0 pb-2"><i class="bi bi-info-circle me-1"></i><em>Instructions: {{ $item->instructions }}</em></td>
                            </tr>
                        @endif
                        @foreach($item->dispensingRecords as $dispensingRecord)
                            <tr class="table-light">
                                <td colspan="7" class="ps-4 small text-success py-2">
                                    <i class="bi bi-check2-square me-1"></i>
                                    Dispensed <strong>{{ $dispensingRecord->quantity_dispensed }} unit(s)</strong> by <strong>{{ $dispensingRecord->pharmacist?->name ?? 'Pharmacist' }}</strong>
                                    on {{ $dispensingRecord->dispensed_at?->format('M d, Y H:i') }}
                                    &bull; Lot: <code>{{ $dispensingRecord->lot_number }}</code>
                                    &bull; Exp: {{ $dispensingRecord->expiry_date?->format('M Y') }}
                                    @if($dispensingRecord->notes)
                                        &bull; <em>Note: {{ $dispensingRecord->notes }}</em>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Reject / Return Modal --}}
@if(auth()->user()?->hasRole('pharmacist'))
<div class="modal fade" id="rejectPrescriptionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ route('pharmacy.prescriptions.reject', $prescription) }}" class="modal-content border-0 shadow">
            @csrf @method('PATCH')
            <div class="modal-header bg-danger text-white py-3">
                <h6 class="modal-title fw-bold"><i class="bi bi-x-octagon me-2"></i>Reject or Return Prescription ({{ $prescription->prescription_no }})</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Action Type</label>
                    <select name="action_type" class="form-select form-select-sm" required>
                        <option value="Returned">Return to Doctor for Clarification / Revision</option>
                        <option value="Rejected">Reject Prescription (Contraindicated / Invalid)</option>
                    </select>
                </div>
                <div class="mb-0">
                    <label class="form-label small fw-semibold">Clinical Reason <span class="text-danger">*</span></label>
                    <textarea name="rejection_reason" rows="3" class="form-control form-control-sm" required placeholder="Specify clinical reason (e.g., allergy alert, dosage clarification needed)..."></textarea>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger btn-sm fw-bold">Submit Decision</button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection
