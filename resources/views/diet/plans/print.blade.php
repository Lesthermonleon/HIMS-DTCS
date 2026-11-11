@extends('layouts.print')

@section('title', 'Diet Plan — ' . $dietPlan->plan_no)
@section('department', 'Department of Clinical Dietetics & Nutrition')
@section('document-title', 'THERAPEUTIC DIET PLAN')
@section('document-no', $dietPlan->plan_no)

@section('content')
<!-- Patient Identification Section -->
<div class="patient-info-box">
    <div class="row g-2">
        <div class="col-4">
            <div class="info-label">Patient Name</div>
            <div class="info-value">{{ $dietPlan->dietRequest->patient->full_name }}</div>
        </div>
        <div class="col-4">
            <div class="info-label">Patient ID / No</div>
            <div class="info-value">{{ $dietPlan->dietRequest->patient->patient_no }}</div>
        </div>
        <div class="col-4">
            <div class="info-label">Diet Type / Category</div>
            <div class="info-value text-success fw-bold">{{ $dietPlan->diet_type }}</div>
        </div>
        <div class="col-4 mt-2">
            <div class="info-label">Created By</div>
            <div class="info-value">{{ $dietPlan->createdBy->name ?? ($dietPlan->dietitian->name ?? ($dietPlan->dietRequest->doctor->name ?? 'Attending Physician')) }}</div>
        </div>
        <div class="col-4 mt-2">
            <div class="info-label">Effective Dates</div>
            <div class="info-value">{{ $dietPlan->start_date ? \Carbon\Carbon::parse($dietPlan->start_date)->format('M d, Y') : 'Immediate' }} – {{ $dietPlan->end_date ? \Carbon\Carbon::parse($dietPlan->end_date)->format('M d, Y') : 'Ongoing' }}</div>
        </div>
        <div class="col-4 mt-2">
            <div class="info-label">Plan Status</div>
            <div class="info-value text-primary">{{ $dietPlan->status }}</div>
        </div>
    </div>
</div>

<!-- Nutritional Targets Table -->
<h6 class="fw-bold mb-2 text-uppercase text-secondary" style="font-size: 11px; letter-spacing: 0.04em;">Macronutrient & Caloric Targets</h6>
<table class="table-clinical mb-3">
    <thead>
        <tr>
            <th style="width: 25%;">Daily Caloric Target</th>
            <th style="width: 25%;">Protein (g)</th>
            <th style="width: 25%;">Carbohydrates (g)</th>
            <th style="width: 25%;">Dietary Fats (g)</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="fw-bold text-success" style="font-size: 14px;">{{ $dietPlan->calories ? $dietPlan->calories . ' kcal' : 'Standard' }}</td>
            <td class="fw-bold">{{ $dietPlan->protein ? $dietPlan->protein . ' g' : 'Standard' }}</td>
            <td class="fw-bold">{{ $dietPlan->carbohydrates ? $dietPlan->carbohydrates . ' g' : 'Standard' }}</td>
            <td class="fw-bold">{{ $dietPlan->fats ? $dietPlan->fats . ' g' : 'Standard' }}</td>
        </tr>
    </tbody>
</table>

<!-- Clinical Instructions & Meal Schedule -->
@if($dietPlan->assessment_notes)
<div class="mb-3">
    <h6 class="fw-bold mb-2 text-uppercase text-secondary" style="font-size: 11px; letter-spacing: 0.04em;">Nutrition Assessment &amp; Recommendations</h6>
    <div class="p-3 border rounded" style="font-size: 12.5px; white-space: pre-line;">{{ $dietPlan->assessment_notes }}</div>
</div>
@endif
<div class="mb-4">
    <h6 class="fw-bold mb-2 text-uppercase text-secondary" style="font-size: 11px; letter-spacing: 0.04em;">Therapeutic Diet Notes & Food Allergies</h6>
    <div class="p-3 border rounded bg-light" style="font-size: 12.5px;">{{ $dietPlan->instructions ?? 'No special meal instructions or allergy warnings.' }}</div>
</div>

<!-- Signatures -->
<div class="row pt-5 text-center">
    <div class="col-6">
        <div class="signature-line">
            {{ $dietPlan->dietitian->name ?? 'Clinical Dietitian' }}, RND
        </div>
        <div class="text-muted small" style="font-size: 10px;">Registered Nutritionist-Dietitian</div>
    </div>
    <div class="col-6">
        <div class="signature-line">
            Dr. {{ $dietPlan->dietRequest->doctor->name }}, MD
        </div>
        <div class="text-muted small" style="font-size: 10px;">Attending Physician</div>
    </div>
</div>
@endsection
