@extends('layouts.app')
@section('title', 'My Profile — Patient Portal')
@section('page-title', 'Patient Demographic Profile')

@section('content')
<div class="row g-3">
    <div class="col-lg-5">
        {{-- Protected Medical Record Card --}}
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-transparent py-3">
                <span class="fw-bold"><i class="bi bi-shield-lock me-2 text-primary"></i>Protected Demographic Master</span>
            </div>
            <div class="card-body">
                <dl class="row mb-0" style="font-size: .92rem;">
                    <dt class="col-sm-4 text-muted">Patient No</dt>
                    <dd class="col-sm-8 font-monospace fw-bold text-dark">{{ $patient->patient_no }}</dd>

                    <dt class="col-sm-4 text-muted">Full Name</dt>
                    <dd class="col-sm-8 fw-semibold text-dark">{{ $patient->full_name }}</dd>

                    <dt class="col-sm-4 text-muted">Date of Birth</dt>
                    <dd class="col-sm-8">{{ $patient->date_of_birth->format('F d, Y') }} ({{ $patient->date_of_birth->age }} years)</dd>

                    <dt class="col-sm-4 text-muted">Gender</dt>
                    <dd class="col-sm-8">{{ $patient->gender }}</dd>

                    <dt class="col-sm-4 text-muted">Blood Type</dt>
                    <dd class="col-sm-8"><span class="badge bg-danger-subtle text-danger border border-danger-subtle">{{ $patient->blood_type ?? 'N/A' }}</span></dd>

                    <dt class="col-sm-4 text-muted">Patient Type</dt>
                    <dd class="col-sm-8"><span class="badge bg-secondary">{{ $patient->patient_type }}</span></dd>

                    <dt class="col-sm-4 text-muted">Registered On</dt>
                    <dd class="col-sm-8 text-muted">{{ $patient->created_at->format('M d, Y') }}</dd>
                </dl>
            </div>
            <div class="card-footer bg-light p-3">
                <div class="d-flex align-items-center gap-2 text-muted small">
                    <i class="bi bi-info-circle text-primary"></i>
                    <span>Primary demographic corrections (Name, DOB, Gender) require in-person hospital intake verification.</span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        {{-- Editable Contact Details Form --}}
        <div class="card shadow-sm">
            <div class="card-header bg-transparent py-3">
                <span class="fw-bold"><i class="bi bi-telephone-outbound me-2 text-success"></i>Update Contact Information</span>
            </div>
            <form action="{{ route('patient.profile.update') }}" method="POST">
                @csrf
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show py-2" role="alert">
                            <i class="bi bi-check-circle me-1"></i>{{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Primary Phone Number</label>
                            <input type="text" name="phone" class="form-control" value="{{ old('phone', $patient->phone) }}" placeholder="+63 9XX XXX XXXX">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email Address (Read-only)</label>
                            <input type="email" class="form-control bg-light" value="{{ Auth::user()->email }}" disabled>
                            <small class="text-muted">Account login address</small>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Residential Address</label>
                            <textarea name="address" rows="3" class="form-control" placeholder="Complete residential address...">{{ old('address', $patient->address) }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Emergency Contact Person</label>
                            <input type="text" name="emergency_contact_name" class="form-control" value="{{ old('emergency_contact_name', $patient->emergency_contact_name) }}" placeholder="Full Name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Emergency Contact Phone</label>
                            <input type="text" name="emergency_contact_phone" class="form-control" value="{{ old('emergency_contact_phone', $patient->emergency_contact_phone) }}" placeholder="Phone / Mobile">
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent d-flex justify-content-end p-3">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-save me-1"></i>Save Contact Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
