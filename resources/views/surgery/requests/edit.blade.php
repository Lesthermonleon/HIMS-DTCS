@extends('layouts.app')
@section('title', 'Edit Surgery Request')
@section('page-title', 'Edit Surgery Request')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('surgery.dashboard') }}">Surgery (SORS)</a></li>
    <li class="breadcrumb-item"><a href="{{ route('surgery.requests.index') }}">Surgery Requests</a></li>
    <li class="breadcrumb-item"><a href="{{ route('surgery.requests.show', $surgeryRequest) }}">{{ $surgeryRequest->request_no }}</a></li>
    <li class="breadcrumb-item active">Edit</li>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-9 col-xl-8">
        <div class="card shadow-sm border-0" style="border-radius: 0.75rem; background: var(--card);">
            <div class="card-header border-bottom py-3 px-4 d-flex justify-content-between align-items-center" style="background: rgba(0,0,0,0.015);">
                <h5 class="mb-0 fw-bold d-flex align-items-center gap-2" style="font-family: var(--font-display); font-size: 1rem; color: var(--text);">
                    <i class="bi bi-pencil-square text-primary"></i>Edit Pending Surgery Request
                </h5>
                <span class="badge bg-secondary">{{ $surgeryRequest->request_no }}</span>
            </div>

            <div class="card-body p-4">
                <form action="{{ route('surgery.requests.update', $surgeryRequest) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <!-- 1. Patient Selection -->
                    <div class="mb-4">
                        <label for="patient_id" class="form-label fw-semibold">Patient <span class="text-danger">*</span></label>
                        <select name="patient_id" id="patient_id" class="form-select @error('patient_id') is-invalid @enderror" required>
                            <option value="">-- Select Patient --</option>
                            @foreach($patients as $patient)
                                <option value="{{ $patient->id }}" {{ old('patient_id', $surgeryRequest->patient_id) == $patient->id ? 'selected' : '' }}>
                                    [{{ $patient->patient_no }}] {{ $patient->last_name }}, {{ $patient->first_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('patient_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row g-3 mb-4">
                        <!-- 2. Procedure Name -->
                        <div class="col-md-8">
                            <label for="procedure_name" class="form-label fw-semibold">Procedure Name <span class="text-danger">*</span></label>
                            <input type="text" name="procedure_name" id="procedure_name" class="form-control @error('procedure_name') is-invalid @enderror" value="{{ old('procedure_name', $surgeryRequest->procedure_name) }}" required>
                            @error('procedure_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- 3. Urgency -->
                        <div class="col-md-4">
                            <label for="urgency" class="form-label fw-semibold">Urgency <span class="text-danger">*</span></label>
                            <select name="urgency" id="urgency" class="form-select @error('urgency') is-invalid @enderror" required>
                                <option value="Elective" {{ old('urgency', $surgeryRequest->urgency) == 'Elective' ? 'selected' : '' }}>Elective</option>
                                <option value="Urgent" {{ old('urgency', $surgeryRequest->urgency) == 'Urgent' ? 'selected' : '' }}>Urgent</option>
                                <option value="Emergency" {{ old('urgency', $surgeryRequest->urgency) == 'Emergency' ? 'selected' : '' }}>Emergency</option>
                            </select>
                            @error('urgency')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <!-- 4. Anesthesia Type -->
                        <div class="col-md-6">
                            <label for="anesthesia_type" class="form-label fw-semibold">Anesthesia Type</label>
                            <select name="anesthesia_type" id="anesthesia_type" class="form-select @error('anesthesia_type') is-invalid @enderror">
                                @foreach(['General Anesthesia', 'Spinal Anesthesia', 'Epidural Anesthesia', 'Local / Sedation'] as $anes)
                                    <option value="{{ $anes }}" {{ old('anesthesia_type', $surgeryRequest->anesthesia_type) == $anes ? 'selected' : '' }}>{{ $anes }}</option>
                                @endforeach
                            </select>
                            @error('anesthesia_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- 5. Estimated Duration -->
                        <div class="col-md-6">
                            <label for="estimated_duration" class="form-label fw-semibold">Estimated Duration (Minutes)</label>
                            <input type="number" name="estimated_duration" id="estimated_duration" class="form-control @error('estimated_duration') is-invalid @enderror" value="{{ old('estimated_duration', $surgeryRequest->estimated_duration ?? 60) }}" min="10" max="600">
                            @error('estimated_duration')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- 6. Diagnosis -->
                    <div class="mb-4">
                        <label for="diagnosis" class="form-label fw-semibold">Pre-operative Diagnosis</label>
                        <input type="text" name="diagnosis" id="diagnosis" class="form-control @error('diagnosis') is-invalid @enderror" value="{{ old('diagnosis', $surgeryRequest->diagnosis) }}">
                        @error('diagnosis')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- 7. Special Notes -->
                    <div class="mb-4">
                        <label for="notes" class="form-label fw-semibold">Clinical Notes / Special Requirements</label>
                        <textarea name="notes" id="notes" rows="3" class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $surgeryRequest->notes) }}</textarea>
                        @error('notes')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Actions -->
                    <div class="d-flex justify-content-end gap-2 border-top pt-3">
                        <a href="{{ route('surgery.requests.show', $surgeryRequest) }}" class="btn btn-outline-secondary px-4" style="border-radius: 0.5rem;">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4 fw-semibold d-flex align-items-center gap-1.5" style="border-radius: 0.5rem;">
                            <i class="bi bi-check2-circle"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

