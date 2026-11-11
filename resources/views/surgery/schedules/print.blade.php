@extends('layouts.print')

@section('title', 'Surgery Schedule — ' . $surgerySchedule->schedule_no)
@section('department', 'Department of Surgery & Operating Room Suite')
@section('document-title', 'SURGERY SCHEDULE')
@section('document-no', $surgerySchedule->schedule_no)

@section('content')
<!-- Patient Identification Section -->
<div class="patient-info-box">
    <div class="row g-2">
        <div class="col-4">
            <div class="info-label">Patient Name</div>
            <div class="info-value">{{ $surgerySchedule->surgeryRequest->patient->full_name }}</div>
        </div>
        <div class="col-4">
            <div class="info-label">Patient ID / No</div>
            <div class="info-value">{{ $surgerySchedule->surgeryRequest->patient->patient_no }}</div>
        </div>
        <div class="col-4">
            <div class="info-label">Surgical Procedure</div>
            <div class="info-value text-primary">{{ $surgerySchedule->surgeryRequest->procedure_name }}</div>
        </div>
        <div class="col-4 mt-2">
            <div class="info-label">Operating Room (OR)</div>
            <div class="info-value fw-bold text-dark">{{ $surgerySchedule->operatingRoom->name ?? 'Unassigned' }}</div>
        </div>
        <div class="col-4 mt-2">
            <div class="info-label">Scheduled Date & Time</div>
            <div class="info-value">{{ $surgerySchedule->scheduled_at ? $surgerySchedule->scheduled_at->format('M d, Y — h:i A') : 'N/A' }}</div>
        </div>
        <div class="col-4 mt-2">
            <div class="info-label">Schedule Status / OR Prep</div>
            <div class="info-value text-danger fw-bold">{{ $surgerySchedule->status }} ({{ $surgerySchedule->readiness_status ?? 'Pending Prep' }})</div>
        </div>
    </div>
</div>

<!-- Surgical Team & Preparation Table -->
<h6 class="fw-bold mb-2 text-uppercase text-secondary" style="font-size: 11px; letter-spacing: 0.04em;">Surgical Team & Operating Room Assignment</h6>
<table class="table-clinical">
    <thead>
        <tr>
            <th style="width: 30%;">Surgical Role</th>
            <th style="width: 40%;">Assigned Clinical Personnel</th>
            <th style="width: 30%;">Status / Verification</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="fw-bold">Lead Surgeon</td>
            <td>Dr. {{ $surgerySchedule->surgeryRequest->doctor->name }}</td>
            <td><span class="badge bg-primary bg-opacity-10 text-primary border px-2 py-1">Confirmed</span></td>
        </tr>
        <tr>
            <td class="fw-bold">OR Coordinator / Nurse</td>
            <td>{{ $surgerySchedule->coordinator->name ?? 'OR Coordinator' }}</td>
            <td><span class="badge bg-primary bg-opacity-10 text-primary border px-2 py-1">Assigned</span></td>
        </tr>
        <tr>
            <td class="fw-bold">Anesthesiologist</td>
            <td>Dr. {{ $surgerySchedule->anesthesiologist_name ?? 'Duty Anesthesiologist' }}</td>
            <td><span class="badge bg-primary bg-opacity-10 text-primary border px-2 py-1">Assigned</span></td>
        </tr>
    </tbody>
</table>

<!-- Special Preparation Notes -->
@if($surgerySchedule->notes)
<div class="mb-4">
    <h6 class="fw-bold mb-2 text-uppercase text-secondary" style="font-size: 11px; letter-spacing: 0.04em;">Preoperative Instructions & Notes</h6>
    <div class="p-3 border rounded bg-light" style="font-size: 12.5px;">{{ $surgerySchedule->notes }}</div>
</div>
@endif

<!-- Medical Signatures -->
<div class="row pt-5 text-center">
    <div class="col-6">
        <div class="signature-line">
            {{ $surgerySchedule->coordinator->name ?? 'OR Coordinator' }}
        </div>
        <div class="text-muted small" style="font-size: 10px;">OR Master Scheduler / Coordinator</div>
    </div>
    <div class="col-6">
        <div class="signature-line">
            Dr. {{ $surgerySchedule->surgeryRequest->doctor->name }}, MD
        </div>
        <div class="text-muted small" style="font-size: 10px;">Lead Attending Surgeon</div>
    </div>
</div>
@endsection
