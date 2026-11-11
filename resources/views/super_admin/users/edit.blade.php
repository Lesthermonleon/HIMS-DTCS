@extends('layouts.app')

@section('title', 'Super Admin — Edit Account: ' . $user->name)
@section('page-title', 'Edit User Account')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('super-admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('super-admin.users.index') }}">User Management</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $user->name }}</li>
@endsection

@section('content')
<div class="container-fluid px-0">

    <div class="row g-4">
        {{-- Left Column: Edit Form --}}
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-person-gear text-primary me-2"></i>Modify Account &amp; Role Settings</h5>
                        <small class="text-muted">Update personal information, departmental assignments, and role classification.</small>
                    </div>
                    <a href="{{ route('super-admin.users.show', $user) }}" class="btn btn-sm btn-outline-info">
                        <i class="bi bi-eye me-1"></i> View Overview
                    </a>
                </div>

                <form method="POST" action="{{ route('super-admin.users.update', $user) }}">
                    @csrf
                    @method('PUT')

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

                        @if($isLastActiveSuperAdmin)
                            <div class="alert alert-warning border-warning-subtle small mb-4 d-flex align-items-center">
                                <i class="bi bi-shield-lock-fill text-warning fs-4 me-2"></i>
                                <div>
                                    <strong>Last Active Super Administrator Guard:</strong> This account is the only active Super Administrator in DTCS. Demotion and deactivation are locked to protect system availability.
                                </div>
                            </div>
                        @endif

                        @if($user->id === auth()->id())
                            <div class="alert alert-info border-info-subtle small mb-4 d-flex align-items-center">
                                <i class="bi bi-info-circle-fill text-info fs-4 me-2"></i>
                                <div>
                                    <strong>Self-Account Safety:</strong> You are editing your currently authenticated account. Role modification and self-deactivation are disabled for your safety.
                                </div>
                            </div>
                        @endif

                        {{-- Identity Fields --}}
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" value="{{ old('name', $user->name) }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Employee ID</label>
                                <input type="text" name="employee_id" class="form-control" value="{{ old('employee_id', $user->employee_id) }}" placeholder="EMP-XXXX">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Department</label>
                                <input type="text" name="department" class="form-control" value="{{ old('department', $user->department) }}" list="deptList" placeholder="E.g. Internal Medicine">
                                <datalist id="deptList">
                                    @foreach($departments as $d)
                                        <option value="{{ $d }}">
                                    @endforeach
                                </datalist>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Phone</label>
                                <input type="text" name="phone" class="form-control" value="{{ old('phone', $user->phone) }}" placeholder="+1 (555) 000-0000">
                            </div>
                        </div>

                        <hr class="my-4">

                        {{-- Role and Status --}}
                        <div class="row g-3 mb-4">
                            <div class="col-md-7">
                                <label class="form-label fw-semibold">Assigned Role <span class="text-danger">*</span></label>
                                @php
                                    $currentRoleSlug = old('role_slug', $user->primaryRole);
                                    $isSelf = ($user->id === auth()->id());
                                @endphp
                                <select name="role_slug" class="form-select {{ $isSelf || $isLastActiveSuperAdmin ? 'bg-light' : '' }}" {{ $isSelf || $isLastActiveSuperAdmin ? 'readonly' : '' }}>
                                    @foreach($roles as $r)
                                        <option value="{{ $r->slug }}" {{ $currentRoleSlug === $r->slug ? 'selected' : '' }}>
                                            {{ $r->name }} ({{ $r->slug }})
                                        </option>
                                    @endforeach
                                </select>
                                @if($isSelf)
                                    <div class="form-text text-muted">Self role change is disallowed.</div>
                                @elseif($isLastActiveSuperAdmin)
                                    <div class="form-text text-danger">Cannot demote the last active Super Admin.</div>
                                @else
                                    <div class="form-text">Changing this role updates the account's accessible modules and custom RBAC permissions.</div>
                                @endif
                            </div>

                            <div class="col-md-5 d-flex align-items-center">
                                <div class="form-check form-switch pt-3">
                                    @if($isSelf || $isLastActiveSuperAdmin)
                                        <input type="checkbox" class="form-check-input" checked disabled>
                                        <input type="hidden" name="is_active" value="1">
                                    @else
                                        <input type="checkbox" class="form-check-input" name="is_active" id="isActiveSwitch" value="1" {{ old('is_active', $user->is_active) ? 'checked' : '' }}>
                                    @endif
                                    <label class="form-check-label fw-bold" for="isActiveSwitch">
                                        Account Active
                                    </label>
                                    <div class="text-muted extra-small">Deactivating suspends login and terminates any active sessions.</div>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        {{-- Audit Reason --}}
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Reason for Modification <span class="text-danger">*</span></label>
                            <textarea name="reason" class="form-control" rows="2" required placeholder="Mandatory audit justification for modifying this account...">{{ old('reason') }}</textarea>
                            <div class="form-text">Recorded in the audit vault for compliance.</div>
                        </div>

                    </div>

                    <div class="card-footer bg-light border-top py-3 d-flex justify-content-between align-items-center">
                        <a href="{{ route('super-admin.users.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-1"></i> Save Changes</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Right Column: Security, Session & Permissions Widget --}}
        <div class="col-lg-4">

            {{-- Active Session & Security Controls --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-shield-lock text-primary me-2"></i>Security &amp; Session Status</h6>
                </div>
                <div class="card-body">
                    {{-- Active Session Status --}}
                    <div class="mb-3 pb-3 border-bottom">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small fw-semibold text-secondary">Active Session</span>
                            @if($user->hasActiveSession())
                                <span class="badge bg-success-subtle text-success border border-success-subtle">
                                    <i class="bi bi-broadcast me-1"></i>Online
                                </span>
                            @else
                                <span class="badge bg-light text-muted border">Idle / Offline</span>
                            @endif
                        </div>
                        <div class="text-muted extra-small">
                            Last Activity: {{ $user->last_activity_at ? $user->last_activity_at->diffForHumans() : 'No recorded activity' }}
                        </div>
                        @if($user->hasActiveSession())
                            <form method="POST" action="{{ route('super-admin.users.terminate-session', $user) }}" class="mt-2">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-warning w-100" onclick="return confirm('Terminate the active session for {{ $user->email }}?');">
                                    <i class="bi bi-box-arrow-right me-1"></i> Terminate Active Session
                                </button>
                            </form>
                        @endif
                    </div>

                    {{-- Lockout Status --}}
                    <div class="mb-3 pb-3 border-bottom">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small fw-semibold text-secondary">Account Lockout</span>
                            @if($user->locked_at)
                                <span class="badge bg-danger">Locked Out</span>
                            @else
                                <span class="badge bg-success">Normal</span>
                            @endif
                        </div>
                        <div class="text-muted extra-small">
                            Failed Attempts: {{ $user->failed_attempts ?? 0 }}
                            @if($user->locked_at)
                                (Locked {{ $user->locked_at->diffForHumans() }})
                            @endif
                        </div>
                        @if($user->locked_at)
                            <form method="POST" action="{{ route('super-admin.users.unlock', $user) }}" class="mt-2">
                                @csrf
                                <input type="hidden" name="reason" value="Super Admin unlock from Edit screen">
                                <button type="submit" class="btn btn-sm btn-outline-info w-100">
                                    <i class="bi bi-unlock me-1"></i> Unlock Account
                                </button>
                            </form>
                        @endif
                    </div>

                    {{-- Password Reset Dispatch --}}
                    <div>
                        <div class="small fw-semibold text-secondary mb-1">Password Management</div>
                        <div class="text-muted extra-small mb-2">Send an email notification allowing this user to reset their credentials securely.</div>
                        <form method="POST" action="{{ route('super-admin.users.reset-password', $user) }}">
                            @csrf
                            <input type="hidden" name="reason" value="Super Admin reset from Edit screen">
                            <button type="submit" class="btn btn-sm btn-outline-primary w-100" onclick="return confirm('Send a secure password reset link to {{ $user->email }}?');">
                                <i class="bi bi-key me-1"></i> Dispatch Password Reset
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Role Capabilities Widget --}}
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-shield-check text-success me-2"></i>Assigned Role Capabilities</h6>
                </div>
                <div class="card-body">
                    @php
                        $role = $user->primaryRoleModel();
                    @endphp
                    @if($role && $role->permissions->isNotEmpty())
                        <div class="mb-2">
                            <span class="badge bg-primary px-2 py-1">{{ $role->name }}</span>
                            <span class="text-muted extra-small">({{ $role->permissions->count() }} permissions)</span>
                        </div>
                        <ul class="list-group list-group-flush border rounded" style="max-height: 280px; overflow-y: auto;">
                            @foreach($role->permissions as $p)
                                <li class="list-group-item d-flex justify-content-between align-items-center py-1.5 px-3">
                                    <span class="small fw-medium text-dark">{{ $p->name }}</span>
                                    <span class="badge bg-success-subtle text-success extra-small">Granted</span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="text-muted small">
                            <i class="bi bi-info-circle me-1"></i> Role has no granular permissions configured or is constrained by core route middleware.
                        </div>
                    @endif

                    <div class="alert alert-light border small text-muted mt-3 mb-0">
                        <i class="bi bi-lock me-1"></i> Clinical authority is role-bound. Super Admin or Admin roles do not automatically inherit clinical prescribing or diagnostic rights.
                    </div>
                </div>
            </div>

        </div>
    </div>

</div>
@endsection
