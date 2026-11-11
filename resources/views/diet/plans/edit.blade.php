@extends('layouts.app')
@section('title', 'Edit Diet Plan')
@section('page-title', 'Modify Diet Plan: ' . $dietPlan->plan_no)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('diet.plans.index') }}">Diet Plans</a></li>
    <li class="breadcrumb-item"><a href="{{ route('diet.plans.show', $dietPlan) }}">{{ $dietPlan->plan_no }}</a></li>
    <li class="breadcrumb-item active">Edit</li>
@endsection
@section('content')
<form method="POST" action="{{ route('diet.plans.update', $dietPlan) }}">
@csrf @method('PUT')
<div class="row g-3">
    <div class="col-lg-8">
        {{-- Plan Details Card --}}
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-file-earmark-medical me-2"></i>Plan Core Details</div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Associated Request / Patient</label>
                    <input type="text" class="form-control bg-light" value="{{ $dietPlan->dietRequest->request_no }} — {{ $dietPlan->dietRequest->patient->last_name }}, {{ $dietPlan->dietRequest->patient->first_name }} (Diet: {{ $dietPlan->dietRequest->diet_type }})" readonly disabled>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Nutrition Assessment &amp; Recommendations</label>
                    <textarea name="assessment_notes" class="form-control @error('assessment_notes') is-invalid @enderror" rows="3" maxlength="2000" placeholder="Nutritional status, dietary history, risks, and recommended dietary modifications... (optional)">{{ old('assessment_notes', $dietPlan->assessment_notes) }}</textarea>
                    @error('assessment_notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Diet Plan Details &amp; Instructions <span class="text-danger">*</span></label>
                    <textarea name="plan_details" class="form-control @error('plan_details') is-invalid @enderror" rows="5" placeholder="Enter specific breakfast, lunch, dinner guidelines, meal sizing, and prep instructions..." required>{{ old('plan_details', $dietPlan->plan_details) }}</textarea>
                    <small class="text-muted">Give comprehensive instructions matching the patient's therapeutic needs.</small>
                    @error('plan_details')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-0">
                    <label class="form-label fw-semibold">Additional Plan Notes</label>
                    <textarea name="notes" class="form-control @error('notes') is-invalid @enderror" rows="3" placeholder="Special remarks, scheduling instructions... (optional)">{{ old('notes', $dietPlan->notes) }}</textarea>
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
                    <input type="number" name="total_calories" class="form-control @error('total_calories') is-invalid @enderror" value="{{ old('total_calories', $dietPlan->total_calories) }}" placeholder="e.g. 2000">
                    @error('total_calories')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label fw-semibold">Protein (g)</label>
                        <input type="number" step="0.1" name="protein_grams" class="form-control @error('protein_grams') is-invalid @enderror" value="{{ old('protein_grams', $dietPlan->protein_grams) }}" placeholder="e.g. 75">
                        @error('protein_grams')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Carbs (g)</label>
                        <input type="number" step="0.1" name="carb_grams" class="form-control @error('carb_grams') is-invalid @enderror" value="{{ old('carb_grams', $dietPlan->carb_grams) }}" placeholder="e.g. 250">
                        @error('carb_grams')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-6 mx-auto mt-2">
                        <label class="form-label fw-semibold">Fat (g)</label>
                        <input type="number" step="0.1" name="fat_grams" class="form-control @error('fat_grams') is-invalid @enderror" value="{{ old('fat_grams', $dietPlan->fat_grams) }}" placeholder="e.g. 60">
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
                    <input type="date" name="start_date" class="form-control @error('start_date') is-invalid @enderror" value="{{ old('start_date', $dietPlan->start_date?->format('Y-m-d')) }}" required>
                    @error('start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-0">
                    <label class="form-label fw-semibold">End Date</label>
                    <input type="date" name="end_date" class="form-control @error('end_date') is-invalid @enderror" value="{{ old('end_date', $dietPlan->end_date?->format('Y-m-d')) }}">
                    @error('end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>
</div>

<div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i>Save Diet Plan</button>
    <a href="{{ route('diet.plans.show', $dietPlan) }}" class="btn btn-outline-secondary">Cancel</a>
</div>
</form>
@endsection
