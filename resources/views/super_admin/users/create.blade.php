@extends('layouts.app')

@section('title', 'Super Admin — Create New User Account')
@section('page-title', 'Create User Account')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('super-admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('super-admin.users.index') }}">User Management</a></li>
    <li class="breadcrumb-item active" aria-current="page">Create Account</li>
@endsection

@section('content')
<div class="container-fluid px-0">

    <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-9">

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-person-plus-fill text-primary me-2"></i>Provision New User Account</h5>
                            <small class="text-muted">Create system access for any role across the DTCS subsystem with custom RBAC and security audit trail.</small>
                        </div>
                        <a href="{{ route('super-admin.users.index') }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Back to Directory
                        </a>
                    </div>
                </div>

                <form method="POST" action="{{ route('super-admin.users.store') }}" id="createUserForm">
                    @csrf
                    <div class="card-body p-4">

                        @if ($errors->any())
                            <div class="alert alert-danger border-0 shadow-sm mb-4">
                                <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-2"></i>Please correct the following errors:</div>
                                <ul class="mb-0 ps-3">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        {{-- Step 1: Select Role --}}
                        <div class="mb-4">
                            <label class="form-label fw-bold text-uppercase small text-muted mb-2">1. Account Role &amp; Classification <span class="text-danger">*</span></label>
                            <div class="row g-2">
                                @php
                                    $currentRoleSlug = old('role_slug', $selectedRole ?? 'admin');
                                @endphp
                                @foreach($roles as $r)
                                    @php
                                        $badgeColor = match($r->slug) {
                                            'super_admin'    => 'danger',
                                            'admin'          => 'primary',
                                            'doctor'         => 'info',
                                            'med-tech', 'rad-tech', 'radiologist', 'pharmacist', 'dietitian', 'or-coordinator' => 'warning',
                                            'patient'        => 'success',
                                            default          => 'secondary',
                                        };
                                    @endphp
                                    <div class="col-md-6 col-lg-4">
                                        <label class="card h-100 border p-3 cursor-pointer role-card {{ $currentRoleSlug === $r->slug ? 'border-primary bg-light' : '' }}" style="cursor: pointer;">
                                            <div class="d-flex align-items-start gap-2">
                                                <input type="radio" name="role_slug" value="{{ $r->slug }}" class="form-check-input mt-1" {{ $currentRoleSlug === $r->slug ? 'checked' : '' }} required>
                                                <div>
                                                    <div class="fw-bold text-dark">{{ $r->name }}</div>
                                                    <span class="badge bg-{{ $badgeColor }} extra-small mt-1">{{ $r->slug }}</span>
                                                    @if($r->description)
                                                        <div class="text-muted extra-small mt-1">{{ Str::limit($r->description, 60) }}</div>
                                                    @endif
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <hr class="my-4">

                        {{-- Step 2: Basic Account Details --}}
                        <div class="mb-4">
                            <label class="form-label fw-bold text-uppercase small text-muted mb-2">2. Identity &amp; Contact Information</label>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="bi bi-person text-muted"></i></span>
                                        <input type="text" name="name" class="form-control" value="{{ old('name') }}" placeholder="E.g. Dr. Jane Doe or John Smith" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="bi bi-envelope text-muted"></i></span>
                                        <input type="email" name="email" class="form-control" value="{{ old('email') }}" placeholder="user@dtcs.hospital" required>
                                    </div>
                                    <div class="form-text">Must be unique across all system accounts.</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Employee ID / Clinician ID</label>
                                    <input type="text" name="employee_id" class="form-control" value="{{ old('employee_id') }}" placeholder="E.g. EMP-10492">
                                    <div class="form-text">Optional for patient accounts.</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Department / Specialty</label>
                                    <input type="text" name="department" id="departmentInput" class="form-control" value="{{ old('department') }}" list="departmentList" placeholder="E.g. Internal Medicine, Laboratory">
                                    <datalist id="departmentList">
                                        @foreach($departments as $dept)
                                            <option value="{{ $dept }}">
                                        @endforeach
                                    </datalist>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Phone Number</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="bi bi-telephone text-muted"></i></span>
                                        <input type="text" name="phone" class="form-control" value="{{ old('phone') }}" placeholder="+1 (555) 000-0000">
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Step 3: Role-Specific Profile Section (Dynamic for Patient) --}}
                        <div id="patientSpecificSection" class="mb-4 p-3 bg-light rounded border {{ $currentRoleSlug === 'patient' ? '' : 'd-none' }}">
                            <label class="form-label fw-bold text-success mb-2"><i class="bi bi-person-heart me-1"></i> Patient Portal Integration</label>
                            <p class="text-muted small mb-3">Link this portal user to an existing hospital patient record or register a new patient chart profile.</p>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Link to Existing Patient Record</label>
                                    <select name="patient_id" class="form-select">
                                        <option value="">-- Choose Existing Unlinked Patient --</option>
                                        @foreach($unlinkedPatients as $p)
                                            <option value="{{ $p->id }}" {{ old('patient_id') == $p->id ? 'selected' : '' }}>
                                                {{ $p->patient_no }} — {{ $p->last_name }}, {{ $p->first_name }} (DOB: {{ $p->date_of_birth?->format('Y-m-d') ?? 'N/A' }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="form-text">If left unselected, a new master patient profile will be created.</div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Date of Birth</label>
                                    <input type="date" name="patient_dob" class="form-control" value="{{ old('patient_dob', '1990-01-01') }}">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Gender</label>
                                    <select name="patient_gender" class="form-select">
                                        <option value="male" {{ old('patient_gender') === 'male' ? 'selected' : '' }}>Male</option>
                                        <option value="female" {{ old('patient_gender') === 'female' ? 'selected' : '' }}>Female</option>
                                        <option value="other" {{ old('patient_gender') === 'other' ? 'selected' : '' }}>Other</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        {{-- Step 4: Password & Authentication Configuration --}}
                        <div class="mb-4">
                            <label class="form-label fw-bold text-uppercase small text-muted mb-2">3. Authentication &amp; Password Provisioning <span class="text-danger">*</span></label>
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="card border p-3 h-100">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="password_mode" id="pwdModeInvite" value="invite" {{ old('password_mode', 'invite') === 'invite' ? 'checked' : '' }}>
                                            <label class="form-check-label fw-bold" for="pwdModeInvite">
                                                <i class="bi bi-send-check text-primary me-1"></i> Email Invitation &amp; Secure Setup (Recommended)
                                            </label>
                                        </div>
                                        <div class="text-muted small mt-2 ps-4">
                                            Generates a cryptographically random initial secret and automatically sends a secure password creation link to the user's email address.
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card border p-3 h-100">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="password_mode" id="pwdModeManual" value="manual" {{ old('password_mode') === 'manual' ? 'checked' : '' }}>
                                            <label class="form-check-label fw-bold" for="pwdModeManual">
                                                <i class="bi bi-key-fill text-warning me-1"></i> Specify Initial Password Manually
                                            </label>
                                        </div>
                                        <div class="mt-2 ps-4">
                                            <input type="password" name="password" id="manualPasswordField" class="form-control form-control-sm" placeholder="Minimum 8 characters..." {{ old('password_mode') === 'manual' ? '' : 'disabled' }}>
                                            <div class="form-text">Must be at least 8 characters long.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        {{-- Step 5: Audit Justification --}}
                        <div class="mb-3">
                            <label class="form-label fw-bold text-uppercase small text-muted mb-2">4. Audit Trail &amp; Governance Justification <span class="text-danger">*</span></label>
                            <textarea name="reason" class="form-control" rows="3" required placeholder="State the business / clinical purpose for provisioning this user account (mandatory security audit log)...">{{ old('reason') }}</textarea>
                            <div class="form-text">This action is permanently recorded in the immutable Super Admin Security Audit Vault with your user ID and IP address.</div>
                        </div>

                    </div>

                    <div class="card-footer bg-light border-top py-3 d-flex justify-content-between align-items-center">
                        <span class="text-muted small"><i class="bi bi-shield-check me-1"></i>Custom DTCS RBAC and OTP enforcement applied automatically.</span>
                        <div class="d-flex gap-2">
                            <a href="{{ route('super-admin.users.index') }}" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i> Create User Account</button>
                        </div>
                    </div>
                </form>

            </div>

        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const roleCards = document.querySelectorAll('.role-card');
    const roleRadios = document.querySelectorAll('input[name="role_slug"]');
    const patientSection = document.getElementById('patientSpecificSection');
    const deptInput = document.getElementById('departmentInput');
    const manualRadio = document.getElementById('pwdModeManual');
    const inviteRadio = document.getElementById('pwdModeInvite');
    const manualField = document.getElementById('manualPasswordField');

    // Default departments per role
    const defaultDepts = {
        'super_admin': 'Master System Governance',
        'admin': 'Hospital Administration',
        'doctor': 'Clinical Medicine',
        'med-tech': 'Laboratory',
        'rad-tech': 'Radiology',
        'radiologist': 'Radiology',
        'pharmacist': 'Pharmacy',
        'dietitian': 'Nutrition & Dietetics',
        'or-coordinator': 'Surgical Suite',
        'patient': 'Patient Portal'
    };

    function handleRoleChange(selectedSlug) {
        roleCards.forEach(card => {
            const radio = card.querySelector('input[type="radio"]');
            if (radio && radio.value === selectedSlug) {
                card.classList.add('border-primary', 'bg-light');
            } else {
                card.classList.remove('border-primary', 'bg-light');
            }
        });

        if (selectedSlug === 'patient') {
            patientSection.classList.remove('d-none');
        } else {
            patientSection.classList.add('d-none');
        }

        if (defaultDepts[selectedSlug] && (!deptInput.value || Object.values(defaultDepts).includes(deptInput.value))) {
            deptInput.value = defaultDepts[selectedSlug];
        }
    }

    roleRadios.forEach(radio => {
        radio.addEventListener('change', function () {
            handleRoleChange(this.value);
        });
    });

    // Password mode toggle
    function togglePasswordFields() {
        if (manualRadio.checked) {
            manualField.disabled = false;
            manualField.required = true;
            manualField.focus();
        } else {
            manualField.disabled = true;
            manualField.required = false;
            manualField.value = '';
        }
    }

    manualRadio.addEventListener('change', togglePasswordFields);
    inviteRadio.addEventListener('change', togglePasswordFields);
});
</script>
@endsection
