@extends('layouts.app')
@section('title', 'Diet Request Details')
@section('page-title', 'Diet Request: ' . $dietRequest->request_no)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('diet.dashboard') }}">DNMS</a></li>
    <li class="breadcrumb-item"><a href="{{ route('diet.plans.index') }}">Diet Plans</a></li>
    <li class="breadcrumb-item active">{{ $dietRequest->request_no }}</li>
@endsection
@section('content')
<div class="row g-3">
    <div class="col-md-8">
        {{-- Request Details Card --}}
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-info-circle me-2"></i>Request Information</span>
                <span class="badge bg-{{ $dietRequest->statusBadge }}">{{ $dietRequest->status }}</span>
            </div>
            <div class="card-body">
                <div class="row g-2 mb-3">
                    <div class="col-sm-6">
                        <div class="text-muted small">Request Number</div>
                        <div class="fw-semibold">{{ $dietRequest->request_no }}</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted small">Diet Type</div>
                        <div class="fw-semibold text-primary"><i class="bi bi-apple me-1"></i>{{ $dietRequest->diet_type }}</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted small">Requested By (Doctor)</div>
                        <div class="fw-semibold">{{ $dietRequest->doctor->name }}</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted small">Requested At</div>
                        <div class="fw-semibold">{{ $dietRequest->requested_at->format('M d, Y H:i') }}</div>
                    </div>
                </div>

                <hr class="my-3">

                <div class="mb-3">
                    <label class="fw-bold text-danger d-block mb-1"><i class="bi bi-exclamation-triangle me-1"></i>Food Allergies</label>
                    <div class="p-2 rounded bg-light border-start border-danger border-3">
                        {!! $dietRequest->allergies ? e($dietRequest->allergies) : '<span class="text-muted">No allergies recorded.</span>' !!}
                    </div>
                </div>

                <div class="mb-3">
                    <label class="fw-bold text-warning d-block mb-1"><i class="bi bi-slash-circle me-1"></i>Food Restrictions</label>
                    <div class="p-2 rounded bg-light border-start border-warning border-3">
                        {!! $dietRequest->food_restrictions ? e($dietRequest->food_restrictions) : '<span class="text-muted">No specific restrictions recorded.</span>' !!}
                    </div>
                </div>

                <div>
                    <label class="fw-bold text-secondary d-block mb-1"><i class="bi bi-file-text me-1"></i>Clinical Notes</label>
                    <div class="p-2 rounded bg-light border-start border-secondary border-3">
                        {!! $dietRequest->clinical_notes ? nl2br(e($dietRequest->clinical_notes)) : '<span class="text-muted">No clinical notes.</span>' !!}
                    </div>
                </div>
            </div>
        </div>

        {{-- Associated Diet Plan Card --}}
        @if($dietRequest->dietPlan)
            <div class="card border-success">
                <div class="card-header bg-success-subtle text-success-emphasis d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-clipboard2-heart me-2"></i>Active Diet Plan Linked</span>
                    <span class="badge bg-{{ $dietRequest->dietPlan->statusBadge }}">{{ $dietRequest->dietPlan->status }}</span>
                </div>
                <div class="card-body">
                    <div class="row g-2 mb-3">
                        <div class="col-sm-6">
                            <span class="text-muted small">Plan details / Instructions:</span>
                            <div class="fw-semibold mt-1">{{ Str::limit($dietRequest->dietPlan->plan_details, 150) }}</div>
                        </div>
                        <div class="col-sm-3">
                            <span class="text-muted small">Total Daily Calories:</span>
                            <div class="fw-bold mt-1 text-success">{{ $dietRequest->dietPlan->total_calories ?? 'Not specified' }} kcal</div>
                        </div>
                        <div class="col-sm-3">
                            <span class="text-muted small">Created By:</span>
                            <div class="fw-semibold mt-1">{{ $dietRequest->dietPlan->createdBy->name ?? ($dietRequest->dietPlan->dietitian->name ?? $dietRequest->doctor->name) }}</div>
                        </div>
                    </div>
                    <a href="{{ route('diet.plans.show', $dietRequest->dietPlan) }}" class="btn btn-sm btn-success"><i class="bi bi-box-arrow-in-right me-1"></i>Open Full Diet Plan</a>
                </div>
            </div>
        @else
            @if($dietRequest->status === 'Pending' && auth()->user()->can('create', [\App\Models\DietPlan::class, $dietRequest]))
                <div class="alert alert-info d-flex align-items-center justify-content-between mb-0">
                    <div>
                        <i class="bi bi-info-circle-fill me-2 fs-5"></i>
                        No therapeutic diet plan has been designed for this request yet.
                    </div>
                    <a href="{{ route('diet.plans.create', ['diet_request_id' => $dietRequest->id]) }}" class="btn btn-sm btn-success"><i class="bi bi-file-earmark-plus me-1"></i>Draw Diet Plan</a>
                </div>
            @else
                <div class="alert alert-secondary mb-0">
                    <i class="bi bi-info-circle me-1"></i> No therapeutic diet plan has been designed yet.
                </div>
            @endif
        @endif
    </div>

    {{-- Right column — Patient context --}}
    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-person me-2"></i>Patient Profile</div>
            <div class="card-body">
                <h5 class="card-title fw-bold text-primary">{{ $dietRequest->patient->last_name }}, {{ $dietRequest->patient->first_name }}</h5>
                <div class="text-muted small mb-3">Patient No: <strong>{{ $dietRequest->patient->patient_no }}</strong></div>

                <div class="row g-2 small">
                    <div class="col-6">
                        <span class="text-muted d-block">Age / Sex:</span>
                        <span class="fw-semibold">{{ $dietRequest->patient->date_of_birth ? $dietRequest->patient->date_of_birth->age : '—' }} / {{ ucfirst($dietRequest->patient->gender ?? '—') }}</span>
                    </div>
                    <div class="col-6">
                        <span class="text-muted d-block">Blood Type:</span>
                        <span class="fw-semibold text-danger">{{ $dietRequest->patient->blood_type ?? '—' }}</span>
                    </div>
                    <div class="col-12">
                        <span class="text-muted d-block">Contact Info:</span>
                        <span class="fw-semibold">{{ $dietRequest->patient->phone ?? 'No phone' }}</span>
                    </div>
                </div>
                <hr>
                <a href="{{ route('patients.show', $dietRequest->patient_id) }}" class="btn btn-xs btn-outline-secondary w-100"><i class="bi bi-person-lines-fill me-1"></i>Go to EMR Details</a>
            </div>
        </div>

        {{-- Actions panel --}}
        <div class="card">
            <div class="card-header"><i class="bi bi-gear me-2"></i>Available Actions</div>
            <div class="card-body d-flex flex-column gap-2">
                @if($dietRequest->status === 'Pending')
                    @can('update', $dietRequest)
                        <a href="{{ route('diet.requests.edit', $dietRequest) }}" class="btn btn-warning w-100 text-start"><i class="bi bi-pencil me-2"></i>Edit Request details</a>
                    @endcan
                    @can('delete', $dietRequest)
                        <form method="POST" action="{{ route('diet.requests.destroy', $dietRequest) }}" onsubmit="return confirm('Cancel this diet request?');">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger w-100 text-start"><i class="bi bi-x-circle me-2"></i>Cancel/Revoke Request</button>
                        </form>
                    @endcan
                @endif
                <a href="{{ route('diet.plans.index') }}" class="btn btn-outline-secondary w-100 text-start"><i class="bi bi-chevron-left me-2"></i>Back to Diet Plans</a>
            </div>
        </div>
    </div>
</div>
@endsection
