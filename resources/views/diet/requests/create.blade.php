@extends('layouts.app')
@section('title', 'New Diet Request')
@section('page-title', 'New Diet & Nutrition Request')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('diet.dashboard') }}">DNMS</a></li>
    <li class="breadcrumb-item"><a href="{{ route('diet.plans.index') }}">Diet Plans</a></li>
    <li class="breadcrumb-item active">New Diet Request</li>
@endsection
@section('content')
<form method="POST" action="{{ route('diet.requests.store') }}">
@csrf
<div class="card" style="max-width: 700px;">
    <div class="card-header">
        <i class="bi bi-apple me-2"></i>Patient & Diet Details
    </div>
    <div class="card-body">
        <div class="mb-3">
            <label class="form-label fw-semibold">Patient <span class="text-danger">*</span></label>
            <select name="patient_id" class="form-select @error('patient_id') is-invalid @enderror" required>
                <option value="">— Select Patient —</option>
                @foreach($patients as $p)
                    <option value="{{ $p->id }}" {{ old('patient_id') == $p->id ? 'selected' : '' }}>
                        {{ $p->last_name }}, {{ $p->first_name }} ({{ $p->patient_no }})
                    </option>
                @endforeach
            </select>
            @error('patient_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label class="form-label fw-semibold">Diet Type <span class="text-danger">*</span></label>
            <select name="diet_type" class="form-select @error('diet_type') is-invalid @enderror" required>
                <option value="">— Select Diet Type —</option>
                @foreach($dietTypes as $type)
                    <option value="{{ $type }}" {{ old('diet_type') === $type ? 'selected' : '' }}>
                        {{ $type }}
                    </option>
                @endforeach
            </select>
            @error('diet_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label class="form-label fw-semibold">Allergies</label>
            <textarea name="allergies" class="form-control @error('allergies') is-invalid @enderror" rows="2" placeholder="List food allergies, e.g. Peanuts, Seafood... (optional)">{{ old('allergies') }}</textarea>
            @error('allergies')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label class="form-label fw-semibold">Food Restrictions</label>
            <textarea name="food_restrictions" class="form-control @error('food_restrictions') is-invalid @enderror" rows="2" placeholder="List food restrictions / preferences, e.g. Vegetarian, Halal, No red meat... (optional)">{{ old('food_restrictions') }}</textarea>
            @error('food_restrictions')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label class="form-label fw-semibold">Clinical Notes</label>
            <textarea name="clinical_notes" class="form-control @error('clinical_notes') is-invalid @enderror" rows="3" placeholder="Additional clinical notes, reason for request... (optional)">{{ old('clinical_notes') }}</textarea>
            @error('clinical_notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
    <div class="card-footer d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i>Submit Request</button>
        <a href="{{ route('diet.plans.index') }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
</div>
</form>
@endsection
