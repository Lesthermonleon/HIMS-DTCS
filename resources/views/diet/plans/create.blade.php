@extends('layouts.app')
@section('title', 'Create Diet Plan')
@section('page-title', 'Design Therapeutic Diet Plan')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('diet.plans.index') }}">Diet Plans</a></li>
    <li class="breadcrumb-item active">New</li>
@endsection
@section('content')
@php
    $isLocked = $isRequestLocked ?? false;
    $activeRequest = $selectedRequest ?? null;
@endphp
<form method="POST" action="{{ route('diet.plans.store') }}">
@csrf
<div class="row g-3">
    <div class="col-lg-8">
        {{-- Plan Details Card --}}
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-file-earmark-medical me-2"></i>Plan Core Details</span>
                @if($isLocked && $activeRequest)
                    <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle">
                        <i class="bi bi-lock-fill me-1"></i>Preselected Request: {{ $activeRequest->request_no }}
                    </span>
                @endif
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="diet-request-select" class="form-label fw-semibold">
                        Select Diet Request / Patient <span class="text-danger">*</span>
                    </label>
                    @if($isLocked && $activeRequest)
                        <input type="hidden" name="diet_request_id" value="{{ $activeRequest->id }}">
                        <input type="hidden" name="preselected_request_id" value="{{ $activeRequest->id }}">
                        <select id="diet-request-select"
                                class="form-select bg-light @error('diet_request_id') is-invalid @enderror"
                                disabled
                                aria-disabled="true">
                            <option value="{{ $activeRequest->id }}" selected>
                                {{ $activeRequest->request_no }} — {{ $activeRequest->patient->last_name }}, {{ $activeRequest->patient->first_name }} ({{ $activeRequest->patient->patient_no }}) — Diet: {{ $activeRequest->diet_type }}
                            </option>
                        </select>
                        <small class="text-muted d-block mt-1">
                            <i class="bi bi-shield-check me-1"></i>This form is locked to pending request <strong>{{ $activeRequest->request_no }}</strong>.
                        </small>
                    @else
                        <select name="diet_request_id"
                                id="diet-request-select"
                                class="form-select @error('diet_request_id') is-invalid @enderror"
                                required>
                            <option value="">— Select Request —</option>
                            @foreach($pendingRequests as $req)
                                <option value="{{ $req->id }}"
                                        data-request-no="{{ $req->request_no }}"
                                        data-patient-name="{{ $req->patient->last_name }}, {{ $req->patient->first_name }}"
                                        data-patient-no="{{ $req->patient->patient_no }}"
                                        data-doctor-name="{{ $req->doctor->name ?? '—' }}"
                                        data-diet-type="{{ $req->diet_type }}"
                                        data-allergies="{{ $req->allergies ?: 'None recorded' }}"
                                        data-restrictions="{{ $req->food_restrictions ?: 'None recorded' }}"
                                        data-clinical-notes="{{ $req->clinical_notes ?: 'None recorded' }}"
                                        {{ (old('diet_request_id', request('diet_request_id')) == $req->id) ? 'selected' : '' }}>
                                    {{ $req->request_no }} — {{ $req->patient->last_name }}, {{ $req->patient->first_name }} (Diet: {{ $req->diet_type }})
                                </option>
                            @endforeach
                        </select>
                    @endif
                    @error('diet_request_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                {{-- Selected Diet Request Clinical Context Panel --}}
                <div id="selected-request-summary"
                     class="rounded-3 border p-3 mb-3 bg-light {{ $activeRequest ? '' : 'd-none' }}"
                     aria-live="polite">
                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                        <span class="fw-semibold text-dark small text-uppercase">
                            <i class="bi bi-clipboard2-pulse me-1 text-success"></i>Selected Diet Request Details
                        </span>
                        <span class="badge bg-secondary-subtle text-secondary-emphasis font-monospace" id="summary-request-no">
                            {{ $activeRequest?->request_no ?? '—' }}
                        </span>
                    </div>
                    <div class="row g-2 small">
                        <div class="col-md-4">
                            <span class="text-muted d-block">Patient</span>
                            <span class="fw-semibold text-dark" id="summary-patient-name">
                                {{ $activeRequest ? ($activeRequest->patient->last_name . ', ' . $activeRequest->patient->first_name) : '—' }}
                            </span>
                            <span class="text-muted font-monospace d-block" id="summary-patient-no">
                                {{ $activeRequest?->patient?->patient_no ?? '' }}
                            </span>
                        </div>
                        <div class="col-md-4">
                            <span class="text-muted d-block">Requesting Doctor</span>
                            <span class="fw-semibold text-dark" id="summary-doctor-name">
                                {{ $activeRequest?->doctor?->name ?? '—' }}
                            </span>
                        </div>
                        <div class="col-md-4">
                            <span class="text-muted d-block">Prescribed Diet Type</span>
                            <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle" id="summary-diet-type">
                                {{ $activeRequest?->diet_type ?? '—' }}
                            </span>
                        </div>
                        <div class="col-md-6 mt-2">
                            <span class="text-danger fw-semibold d-block">
                                <i class="bi bi-exclamation-triangle me-1"></i>Food Allergies
                            </span>
                            <div class="p-2 rounded bg-white border border-danger-subtle text-dark mt-1" id="summary-allergies">
                                {{ $activeRequest ? ($activeRequest->allergies ?: 'None recorded') : 'None recorded' }}
                            </div>
                        </div>
                        <div class="col-md-6 mt-2">
                            <span class="text-warning-emphasis fw-semibold d-block">
                                <i class="bi bi-slash-circle me-1"></i>Food Restrictions
                            </span>
                            <div class="p-2 rounded bg-white border border-warning-subtle text-dark mt-1" id="summary-restrictions">
                                {{ $activeRequest ? ($activeRequest->food_restrictions ?: 'None recorded') : 'None recorded' }}
                            </div>
                        </div>
                        <div class="col-12 mt-2">
                            <span class="text-muted fw-semibold d-block">
                                <i class="bi bi-journal-medical me-1"></i>Clinical Notes
                            </span>
                            <div class="p-2 rounded bg-white border text-dark mt-1" id="summary-clinical-notes">
                                {{ $activeRequest ? ($activeRequest->clinical_notes ?: 'None recorded') : 'None recorded' }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Nutrition Assessment &amp; Recommendations</label>
                    <textarea name="assessment_notes" class="form-control @error('assessment_notes') is-invalid @enderror" rows="3" maxlength="2000" placeholder="Nutritional status, dietary history, risks, and recommended dietary modifications... (optional)">{{ old('assessment_notes') }}</textarea>
                    @error('assessment_notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Diet Plan Details &amp; Instructions <span class="text-danger">*</span></label>
                    <textarea name="plan_details" class="form-control @error('plan_details') is-invalid @enderror" rows="5" placeholder="Enter specific breakfast, lunch, dinner guidelines, meal sizing, and prep instructions (minimum 20 characters)..." required>{{ old('plan_details') }}</textarea>
                    <small class="text-muted">Give comprehensive instructions matching the patient's therapeutic needs.</small>
                    @error('plan_details')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-0">
                    <label class="form-label fw-semibold">Additional Plan Notes</label>
                    <textarea name="notes" class="form-control @error('notes') is-invalid @enderror" rows="3" placeholder="Special remarks, scheduling instructions... (optional)">{{ old('notes') }}</textarea>
                    @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>

    {{-- Right column — Nutrition breakdown & Dates --}}
    <div class="col-lg-4">
        {{-- Macronutrient Target Card --}}
        <div class="card mb-3">
            <div class="card-header bg-success-subtle text-success-emphasis"><i class="bi bi-apple me-2"></i>Nutritional Targets</div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Daily Calories (kcal)</label>
                    <input type="number" name="total_calories" class="form-control @error('total_calories') is-invalid @enderror" value="{{ old('total_calories', 2000) }}" placeholder="e.g. 2000">
                    @error('total_calories')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label fw-semibold">Protein (g)</label>
                        <input type="number" step="0.1" name="protein_grams" class="form-control @error('protein_grams') is-invalid @enderror" value="{{ old('protein_grams') }}" placeholder="e.g. 75">
                        @error('protein_grams')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Carbs (g)</label>
                        <input type="number" step="0.1" name="carb_grams" class="form-control @error('carb_grams') is-invalid @enderror" value="{{ old('carb_grams') }}" placeholder="e.g. 250">
                        @error('carb_grams')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-6 mx-auto mt-2">
                        <label class="form-label fw-semibold">Fat (g)</label>
                        <input type="number" step="0.1" name="fat_grams" class="form-control @error('fat_grams') is-invalid @enderror" value="{{ old('fat_grams') }}" placeholder="e.g. 60">
                        @error('fat_grams')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Dates and Timeline Card --}}
        <div class="card mb-3">
            <div class="card-header bg-info-subtle text-info-emphasis"><i class="bi bi-calendar-event me-2"></i>Duration / Timeline</div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Start Date <span class="text-danger">*</span></label>
                    <input type="date" name="start_date" class="form-control @error('start_date') is-invalid @enderror" value="{{ old('start_date', date('Y-m-d')) }}" required>
                    @error('start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-0">
                    <label class="form-label fw-semibold">End Date</label>
                    <input type="date" name="end_date" class="form-control @error('end_date') is-invalid @enderror" value="{{ old('end_date') }}">
                    @error('end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>
</div>

<div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Diet Plan</button>
    <a href="{{ route('diet.plans.index') }}" class="btn btn-outline-secondary">Cancel</a>
</div>
</form>

@if(! $isLocked)
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const selectEl = document.getElementById('diet-request-select');
    const summaryEl = document.getElementById('selected-request-summary');
    if (!selectEl || !summaryEl) return;

    function syncSummary() {
        const option = selectEl.options[selectEl.selectedIndex];
        if (!option || !option.value) {
            summaryEl.classList.add('d-none');
            return;
        }

        document.getElementById('summary-request-no').textContent = option.dataset.requestNo || '—';
        document.getElementById('summary-patient-name').textContent = option.dataset.patientName || '—';
        document.getElementById('summary-patient-no').textContent = option.dataset.patientNo || '';
        document.getElementById('summary-doctor-name').textContent = option.dataset.doctorName || '—';
        document.getElementById('summary-diet-type').textContent = option.dataset.dietType || '—';
        document.getElementById('summary-allergies').textContent = option.dataset.allergies || 'None recorded';
        document.getElementById('summary-restrictions').textContent = option.dataset.restrictions || 'None recorded';
        document.getElementById('summary-clinical-notes').textContent = option.dataset.clinicalNotes || 'None recorded';

        summaryEl.classList.remove('d-none');
    }

    selectEl.addEventListener('change', syncSummary);
    syncSummary();
});
</script>
@endpush
@endif
@endsection
