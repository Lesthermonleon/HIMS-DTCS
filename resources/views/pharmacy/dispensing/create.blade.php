@extends('layouts.app')
@section('title', 'Dispense Medication — Pharmacy')
@section('page-title', 'Dispense Medication')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 text-dark fw-bold"><i class="bi bi-capsule-pill me-2 text-primary"></i>Dispense Medication</h4>
        <p class="text-muted small mb-0">Record medication batch dispensing for verified medical prescriptions.</p>
    </div>
    <div>
        <a href="{{ route('pharmacy.dispensing.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Dispensing History
        </a>
    </div>
</div>

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="row g-4">
    {{-- Step 1: Select Prescription --}}
    <div class="col-lg-12">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-search me-2 text-primary"></i>1. Select Verified Prescription</h6>
            </div>
            <div class="card-body py-3">
                <form method="GET" action="{{ route('pharmacy.dispensing.create') }}" class="row g-2 align-items-end">
                    <div class="col-md-9">
                        <label for="rxSelect" class="form-label small text-muted fw-semibold mb-1">Select from Pending / Partially Dispensed Prescriptions:</label>
                        <select id="rxSelect" name="rx" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">-- Choose a Verified Prescription --</option>
                            @foreach($prescriptions as $rxOption)
                                <option value="{{ $rxOption->id }}" {{ ($selectedPrescription?->id == $rxOption->id) ? 'selected' : '' }}>
                                    {{ $rxOption->prescription_no }} &mdash; Patient: {{ $rxOption->patient->full_name }} ({{ $rxOption->items->where('status', 'Pending')->count() }} pending items)
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary btn-sm w-100">
                            <i class="bi bi-check2-circle me-1"></i> Load Prescription
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>

    @if($selectedPrescription)
        {{-- Step 2: Patient & Doctor Info Card --}}
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-primary text-white py-3">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-person-v me-2"></i>Patient & Rx Overview</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-3 me-3">
                            <i class="bi bi-person-fill fs-2"></i>
                        </div>
                        <div>
                            <h5 class="mb-0 fw-bold text-dark">{{ $selectedPrescription->patient->full_name }}</h5>
                            <span class="badge bg-secondary-subtle text-secondary border mt-1">ID: {{ $selectedPrescription->patient->patient_no }}</span>
                        </div>
                    </div>

                    <dl class="row small mb-0">
                        <dt class="col-5 text-muted">Rx Number</dt>
                        <dd class="col-7 fw-bold text-primary">{{ $selectedPrescription->prescription_no }}</dd>

                        <dt class="col-5 text-muted">Prescribing Doctor</dt>
                        <dd class="col-7">{{ $selectedPrescription->doctor->name }}</dd>

                        <dt class="col-5 text-muted">Diagnosis</dt>
                        <dd class="col-7">{{ $selectedPrescription->diagnosis ?? '—' }}</dd>

                        <dt class="col-5 text-muted">Status</dt>
                        <dd class="col-7">
                            <span class="badge bg-{{ $selectedPrescription->statusBadge }}">{{ $selectedPrescription->status }}</span>
                        </dd>

                        <dt class="col-5 text-muted">Prescribed Date</dt>
                        <dd class="col-7">{{ $selectedPrescription->prescribed_at?->format('M d, Y H:i') }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        {{-- Step 3: Dispensing Form --}}
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-box-seam me-2 text-primary"></i>Prescription Items</h6>
                    @php
                        $pendingItems = $selectedPrescription->items->where('status', 'Pending');
                        $pendingItemsCount = $pendingItems->count();
                        $totalItemsCount = $selectedPrescription->items->count();
                        $isFullyDispensed = ($pendingItemsCount === 0);
                        $isPartiallyDispensed = ($pendingItemsCount > 0 && $pendingItemsCount < $totalItemsCount);
                    @endphp
                    <div>
                        <span class="badge bg-{{ $isFullyDispensed ? 'success-subtle text-success border border-success' : ($isPartiallyDispensed ? 'warning-subtle text-warning border border-warning' : 'primary-subtle text-primary border border-primary') }} px-2 py-1">
                            {{ $pendingItemsCount }} of {{ $totalItemsCount }} Pending
                        </span>
                    </div>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('pharmacy.dispensing.store') }}" id="dispenseBatchForm">
                        @csrf
                        <input type="hidden" name="prescription_id" value="{{ $selectedPrescription->id }}">

                        <div class="medication-list" id="medicationListGroup">
                            @forelse($selectedPrescription->items as $item)
                                @php
                                    $isPending = ($item->status === 'Pending' && $item->remaining_quantity > 0);
                                    $dispensingRec = $item->dispensingRecords->first();
                                    $stockData = $stockDataMap[$item->id] ?? null;
                                    $availableQty = $stockData ? $stockData->availableQuantity : 0;
                                    $remainingQty = $item->remaining_quantity;
                                    $lotNumber = old("items.{$item->id}.lot_number", $stockData ? $stockData->lotNumber : '');
                                    $expiryDate = old("items.{$item->id}.expiry_date", $stockData ? $stockData->expiryDate : '');
                                    $hasStock = $stockData && $stockData->hasSufficientStock($remainingQty);
                                @endphp
                                <div class="medication-item py-3 {{ ! $loop->last ? 'border-bottom' : '' }} {{ $isPending ? 'item-card-pending' : 'opacity-75' }}"
                                     data-item-id="{{ $item->id }}"
                                     data-item-name="{{ $item->medication_name }}"
                                     data-item-qty="{{ $remainingQty }}">
                                    
                                    {{-- Item Header & Details --}}
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <h6 class="fw-bold text-dark mb-1">{{ $item->medication_name }}</h6>
                                            <div class="small text-muted">
                                                {{ $item->dosage }} &bull; {{ $item->route ?? 'Oral' }} &bull; {{ $item->frequency }} &bull; {{ $item->duration }}
                                                <span class="ms-2 text-dark fw-semibold">&bull; Prescribed: {{ $item->quantity }} {{ Str::plural('unit', $item->quantity) }}</span>
                                                @if($item->dispensed_quantity > 0)
                                                    <span class="badge bg-info-subtle text-info border border-info-subtle ms-1">
                                                        Already Dispensed: {{ $item->dispensed_quantity }} &bull; Remaining: {{ $remainingQty }}
                                                    </span>
                                                @endif
                                                @if($isPending && $stockData)
                                                    <span class="badge bg-{{ $hasStock ? 'success-subtle text-success border border-success' : 'danger-subtle text-danger border border-danger' }} ms-2">
                                                        Available: {{ $availableQty }} {{ Str::plural('unit', $availableQty) }}
                                                    </span>
                                                @endif
                                            </div>
                                            @if($item->instructions)
                                                <div class="small text-muted mt-1"><i class="bi bi-info-circle me-1 text-primary"></i>{{ $item->instructions }}</div>
                                            @endif
                                        </div>
                                        <div class="text-end text-nowrap ms-3">
                                            <span class="badge bg-{{ $isPending ? 'warning-subtle text-warning border border-warning' : 'success-subtle text-success border border-success' }} px-2 py-1">
                                                <i class="bi bi-{{ $isPending ? 'clock-history' : 'check-circle-fill' }} me-1"></i>{{ $item->status }}
                                            </span>
                                        </div>
                                    </div>

                                    {{-- Batch & Expiry Display Section (From Inventory Integration) --}}
                                    @if($isPending)
                                        <div class="mt-3 pt-2 border-top">
                                            {{-- Hidden form inputs to preserve submission payload --}}
                                            <input type="hidden" name="items[{{ $item->id }}][id]" value="{{ $item->id }}">
                                            <input type="hidden" name="items[{{ $item->id }}][lot_number]" id="lot_number_{{ $item->id }}" class="item-lot-input" value="{{ $lotNumber }}">
                                            <input type="hidden" name="items[{{ $item->id }}][expiry_date]" id="expiry_date_{{ $item->id }}" class="item-expiry-input" value="{{ $expiryDate }}">

                                            <div class="small fw-semibold text-muted mb-2 d-flex align-items-center justify-content-between">
                                                <div>
                                                    <i class="bi bi-box-seam me-1 text-primary"></i>Inventory Batch &amp; Quantity to Dispense
                                                </div>
                                                <div class="text-muted extra-small" style="font-size: 0.75rem;">
                                                    <i class="bi bi-building-check me-1"></i>Stock Integration
                                                </div>
                                            </div>
                                            <div class="row g-3 align-items-center">
                                                <div class="col-md-4">
                                                    <div class="small text-muted mb-1">
                                                        Quantity to Dispense (Max: {{ $remainingQty }})
                                                    </div>
                                                    <input type="number"
                                                           name="items[{{ $item->id }}][quantity_dispensed]"
                                                           class="form-control form-control-sm item-qty-input"
                                                           min="1"
                                                           max="{{ $remainingQty }}"
                                                           value="{{ old("items.{$item->id}.quantity_dispensed", $remainingQty) }}">
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="small text-muted mb-1">
                                                        Lot / Batch Number
                                                    </div>
                                                    <div class="fw-semibold text-dark">
                                                        {{ $lotNumber ?: '—' }}
                                                    </div>
                                                    @error("items.{$item->id}.lot_number")
                                                        <div class="text-danger small mt-1"><i class="bi bi-exclamation-circle me-1"></i>{{ $message }}</div>
                                                    @enderror
                                                </div>

                                                <div class="col-md-4">
                                                    <div class="small text-muted mb-1">
                                                        Medication Expiry Date
                                                    </div>
                                                    <div class="fw-semibold text-dark">
                                                        {{ $expiryDate ? \Carbon\Carbon::parse($expiryDate)->format('M d, Y') : '—' }}
                                                    </div>
                                                    @error("items.{$item->id}.expiry_date")
                                                        <div class="text-danger small mt-1"><i class="bi bi-exclamation-circle me-1"></i>{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                        </div>
                                    @elseif($dispensingRec)
                                        <div class="mt-2 small text-success">
                                            <i class="bi bi-check2-square me-1"></i> Dispensed on {{ $dispensingRec->dispensed_at?->format('M d, Y H:i') }} &bull; Lot: <code>{{ $dispensingRec->lot_number }}</code> &bull; Exp: {{ $dispensingRec->expiry_date?->format('M Y') }}
                                        </div>
                                    @endif
                                </div>
                            @empty
                                <div class="alert alert-warning mb-0">No medication items found for this prescription.</div>
                            @endforelse
                        </div>
                        @error('prescription_id')
                            <div class="text-danger small mt-2"><i class="bi bi-exclamation-circle me-1"></i>{{ $message }}</div>
                        @enderror

                        @if(! $isFullyDispensed)
                            <div class="pt-3 border-top mt-4">
                                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-person-badge me-2 text-primary"></i>Dispensing Pharmacist & Notes</h6>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted mb-1">Dispensing Pharmacist</label>
                                        <div class="d-flex align-items-center bg-light p-2 rounded border">
                                            <i class="bi bi-person-circle fs-5 me-2 text-primary"></i>
                                            <div>
                                                <div class="fw-bold text-dark small mb-0">{{ auth()->user()->name }}</div>
                                                <div class="text-muted" style="font-size: 0.75rem;">Pharmacist (Authenticated)</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="notes" class="form-label small text-muted mb-1">Pharmacist Notes / Patient Advisory (Optional)</label>
                                        <textarea name="notes" id="notes" rows="2" class="form-control form-control-sm @error('notes') is-invalid @enderror"
                                                  placeholder="Enter patient instructions or dispensing notes...">{{ old('notes') }}</textarea>
                                        @error('notes')
                                            <div class="invalid-feedback small">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="mt-4 pt-3 border-top d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
                                    <div class="small text-muted">
                                        <i class="bi bi-info-circle me-1 text-primary"></i>All pending medication items will be processed together.
                                    </div>
                                    <div class="d-flex gap-2 justify-content-end">
                                        <a href="{{ route('pharmacy.dispensing.index') }}" class="btn btn-outline-secondary">
                                            <i class="bi bi-x-circle me-1"></i> Cancel
                                        </a>
                                        <button type="button" class="btn btn-primary px-4 fw-bold shadow-sm" id="openConfirmModalBtn" onclick="showConfirmModal()">
                                            <i class="bi bi-box-arrow-down me-1"></i> {{ $isPartiallyDispensed ? 'Dispense Remaining' : 'Dispense Prescription' }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="alert alert-success d-flex align-items-center mb-0 mt-4 border-0 shadow-sm p-3">
                                <i class="bi bi-check-circle-fill fs-4 me-3 text-success"></i>
                                <div>
                                    <h6 class="fw-bold mb-1">All prescribed medicines have been dispensed</h6>
                                    <div class="small">Every item under Prescription <code>{{ $selectedPrescription->prescription_no }}</code> has been dispensed and logged in inventory.</div>
                                </div>
                            </div>
                            <div class="mt-3 text-end">
                                <a href="{{ route('pharmacy.prescriptions.show', $selectedPrescription) }}" class="btn btn-primary btn-sm">
                                    <i class="bi bi-eye me-1"></i> View Prescription Details
                                </a>
                            </div>
                        @endif
                    </form>
                </div>
            </div>
        </div>

        {{-- Confirmation Modal --}}
        @if(! $isFullyDispensed)
        <div class="modal fade" id="batchDispenseModal" tabindex="-1" aria-labelledby="batchDispenseModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header bg-primary text-white py-3">
                        <h6 class="modal-title fw-bold" id="batchDispenseModalLabel">
                            <i class="bi bi-shield-check me-2"></i>{{ $isPartiallyDispensed ? 'Dispense Remaining Medicines' : 'Dispense Prescription' }}
                        </h6>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="alert alert-info border-0 shadow-sm d-flex align-items-center mb-3">
                            <i class="bi bi-info-circle fs-4 me-3 text-primary"></i>
                            <div>
                                Patient: <strong>{{ $selectedPrescription->patient->full_name }}</strong> &bull; Rx: <code>{{ $selectedPrescription->prescription_no }}</code>
                            </div>
                        </div>

                        <div class="mb-3">
                            <h6 class="fw-bold text-dark small text-uppercase mb-2">Prescription Items Batch & Expiry Summary:</h6>
                            <ul class="list-group list-group-flush border rounded" id="modalMedicationList">
                                {{-- Dynamically populated by JS --}}
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-success px-4 btn-sm fw-bold" id="submitDispenseBtn" onclick="submitBatchDispensing()">
                            <i class="bi bi-check-lg me-1"></i> Confirm Dispensing
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <script>
            function showConfirmModal() {
                const list = document.getElementById('modalMedicationList');
                list.innerHTML = '';

                document.querySelectorAll('.item-card-pending').forEach(card => {
                    const name = card.dataset.itemName;
                    const qty = card.dataset.itemQty;
                    const lotInput = card.querySelector('.item-lot-input');
                    const expInput = card.querySelector('.item-expiry-input');

                    const lot = lotInput ? (lotInput.value || '—') : '—';
                    const exp = expInput ? (expInput.value || '—') : '—';

                    const li = document.createElement('li');
                    li.className = 'list-group-item py-2 px-3 small';
                    li.innerHTML = `
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span><i class="bi bi-check-lg text-success me-2 fw-bold"></i><strong>${name}</strong></span>
                            <span class="badge bg-secondary">${qty} unit(s)</span>
                        </div>
                        <div class="bg-light p-2 rounded border text-muted d-flex justify-content-between">
                            <span>Lot/Batch: <strong class="text-dark">${lot}</strong></span>
                            <span>Expiry: <strong class="text-dark">${exp}</strong></span>
                        </div>
                    `;
                    list.appendChild(li);
                });

                const modal = new bootstrap.Modal(document.getElementById('batchDispenseModal'));
                modal.show();
            }

            function submitBatchDispensing() {
                const submitBtn = document.getElementById('submitDispenseBtn');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Processing...';

                document.getElementById('dispenseBatchForm').submit();
            }
        </script>
        @endif

    @else
        <div class="col-lg-12">
            <div class="card border-0 shadow-sm text-center py-5">
                <div class="card-body">
                    <i class="bi bi-capsule fs-1 text-primary opacity-50 mb-3 d-block"></i>
                    <h5 class="fw-bold text-dark">Please select a prescription to begin dispensing</h5>
                    <p class="text-muted small">Choose a verified prescription from the dropdown above, or browse verified prescriptions directly.</p>
                    <a href="{{ route('pharmacy.prescriptions.index') }}" class="btn btn-outline-primary">
                        <i class="bi bi-list-check me-1"></i> View Prescriptions List
                    </a>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
