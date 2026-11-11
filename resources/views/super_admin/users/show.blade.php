@extends('layouts.app')

@section('title', 'Super Admin — Account Details: ' . $user->name)
@section('page-title', 'User Account Overview')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('super-admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('super-admin.users.index') }}">User Management</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $user->name }}</li>
@endsection

@section('content')
<div class="container-fluid px-0">

    {{-- Header Card --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-primary bg-opacity-10 text-primary fw-bold d-flex align-items-center justify-content-center" style="width: 56px; height: 56px; font-size: 1.25rem;">
                    {{ strtoupper(substr($user->name, 0, 2)) }}
                </div>
                <div>
                    <h4 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2">
                        {{ $user->name }}
                        @if($user->isSuperAdmin())
                            <span class="badge bg-danger fs-6"><i class="bi bi-shield-fill-check me-1"></i>Super Admin</span>
                        @else
                            <span class="badge bg-primary fs-6">{{ $user->primaryRoleModel()?->name ?? 'User' }}</span>
                        @endif
                        @if($user->trashed())
                            <span class="badge bg-secondary fs-6"><i class="bi bi-archive-fill me-1"></i>Archived</span>
                        @elseif($user->locked_at)
                            <span class="badge bg-danger fs-6"><i class="bi bi-lock-fill me-1"></i>Locked</span>
                        @elseif($user->is_active)
                            <span class="badge bg-success fs-6"><i class="bi bi-check-circle-fill me-1"></i>Active</span>
                        @else
                            <span class="badge bg-warning text-dark fs-6"><i class="bi bi-pause-circle-fill me-1"></i>Suspended</span>
                        @endif
                    </h4>
                    <div class="text-muted small">
                        <span><i class="bi bi-envelope me-1"></i>{{ $user->email }}</span>
                        @if($user->employee_id)
                            <span class="ms-3"><i class="bi bi-badge-ad me-1"></i>ID: {{ $user->employee_id }}</span>
                        @endif
                        @if($user->department)
                            <span class="ms-3"><i class="bi bi-building me-1"></i>{{ $user->department }}</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('super-admin.users.edit', $user) }}" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-pencil me-1"></i> Edit Account
                </a>
                <a href="{{ route('super-admin.users.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Back to Directory
                </a>
            </div>
        </div>
    </div>

    <div class="row g-4">
        {{-- Left: Account Info & Clinical Records Safety --}}
        <div class="col-lg-7">

            {{-- Account Information Panel --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-info-circle text-primary me-2"></i>Account &amp; Role Details</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <span class="text-muted small d-block">Full Name</span>
                            <span class="fw-semibold text-dark">{{ $user->name }}</span>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted small d-block">Email Address</span>
                            <span class="fw-semibold text-dark">{{ $user->email }}</span>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted small d-block">Employee ID</span>
                            <span class="fw-semibold text-dark">{{ $user->employee_id ?? 'Not assigned' }}</span>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted small d-block">Department</span>
                            <span class="fw-semibold text-dark">{{ $user->department ?? 'General' }}</span>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted small d-block">Contact Phone</span>
                            <span class="fw-semibold text-dark">{{ $user->phone ?? 'Not provided' }}</span>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted small d-block">Account Created</span>
                            <span class="fw-semibold text-dark">{{ $user->created_at ? $user->created_at->format('M d, Y H:i') : 'Unknown' }}</span>
                        </div>
                        @if($user->patient)
                            <div class="col-12 pt-2 border-top">
                                <span class="text-muted small d-block">Linked Master Patient Record</span>
                                <span class="fw-semibold text-success">
                                    <i class="bi bi-person-heart me-1"></i>{{ $user->patient->patient_no }} — {{ $user->patient->full_name }} (DOB: {{ $user->patient->date_of_birth?->format('Y-m-d') }})
                                </span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Clinical Records & Deletion Retention Safety Card --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-file-medical text-danger me-2"></i>Clinical Records &amp; Medical Retention Audit
                    </h6>
                    @if($hasClinical)
                        <span class="badge bg-warning text-dark"><i class="bi bi-shield-lock-fill me-1"></i>Permanent Deletion Restricted</span>
                    @else
                        <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-check-circle me-1"></i>Permanent Deletion Safe</span>
                    @endif
                </div>
                <div class="card-body">
                    @if($hasClinical)
                        <div class="alert alert-warning border-0 small mb-3">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i>
                            <strong>Statutory Medical Retention Notice:</strong> This account is linked to clinical and medical records. Under healthcare regulations, medical charts, lab orders, prescriptions, and surgical documentation cannot be destroyed. The account can be safely archived (soft-deleted), but permanent force deletion is prohibited.
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Clinical Record Category</th>
                                        <th class="text-center" style="width: 120px;">Record Count</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($clinicalBreakdown as $category => $count)
                                        <tr>
                                            <td class="fw-medium text-dark"><i class="bi bi-journal-medical text-primary me-2"></i>{{ $category }}</td>
                                            <td class="text-center"><span class="badge bg-light text-dark border fw-bold">{{ $count }}</span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="alert alert-success border-0 small mb-0">
                            <i class="bi bi-check-circle-fill me-1"></i>
                            <strong>No Linked Clinical Records:</strong> No laboratory orders, radiology reports, prescriptions, surgical schedules, or medical charts are associated with this user. Permanent deletion is permitted if required by administrator policy.
                        </div>
                    @endif
                </div>
            </div>

            {{-- Role Permissions Preview Card --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-shield-check text-success me-2"></i>Assigned Roles &amp; Granted Permissions</h6>
                </div>
                <div class="card-body">
                    @forelse($user->roles as $role)
                        <div class="mb-3 pb-3 {{ !$loop->last ? 'border-bottom' : '' }}">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="badge bg-primary fs-6 px-3 py-1.5">{{ $role->name }}</span>
                                <span class="text-muted small">Slug: <code>{{ $role->slug }}</code></span>
                            </div>
                            <p class="text-muted small mb-2">{{ $role->description ?? 'No description provided.' }}</p>
                            
                            @if($role->permissions->isNotEmpty())
                                <div class="d-flex flex-wrap gap-1">
                                    @foreach($role->permissions as $perm)
                                        <span class="badge bg-light text-secondary border extra-small">
                                            <i class="bi bi-check me-0.5 text-success"></i>{{ $perm->name }}
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-muted extra-small">No granular permissions attached.</span>
                            @endif
                        </div>
                    @empty
                        <div class="text-muted small">No roles assigned to this account.</div>
                    @endforelse
                </div>
            </div>

        </div>

        {{-- Right: Session Controls & Activity History --}}
        <div class="col-lg-5">

            {{-- Security & Session Controls --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-broadcast text-primary me-2"></i>Active Session &amp; Security Controls</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-semibold small text-secondary">Single Session Status</span>
                        @if($user->hasActiveSession())
                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                <i class="bi bi-broadcast me-1"></i>Active / Online
                            </span>
                        @else
                            <span class="badge bg-light text-muted border px-2 py-1">Idle / Offline</span>
                        @endif
                    </div>
                    <div class="text-muted extra-small mb-3">
                        Last Activity: {{ $user->last_activity_at ? $user->last_activity_at->diffForHumans() : 'Never logged in' }}
                    </div>

                    <div class="d-grid gap-2">
                        @if($user->hasActiveSession())
                            <form method="POST" action="{{ route('super-admin.users.terminate-session', $user) }}">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-warning w-100" onclick="return confirm('Terminate the active session for {{ $user->email }}?');">
                                    <i class="bi bi-box-arrow-right me-1"></i> Terminate Active Session
                                </button>
                            </form>
                        @endif

                        <form method="POST" action="{{ route('super-admin.users.reset-password', $user) }}">
                            @csrf
                            <input type="hidden" name="reason" value="Super Admin initiated reset from user profile">
                            <button type="submit" class="btn btn-sm btn-outline-primary w-100" onclick="return confirm('Dispatch secure password reset to {{ $user->email }}?');">
                                <i class="bi bi-key me-1"></i> Dispatch Secure Password Reset
                            </button>
                        </form>

                        @if($user->locked_at)
                            <form method="POST" action="{{ route('super-admin.users.unlock', $user) }}">
                                @csrf
                                <input type="hidden" name="reason" value="Super Admin unlock from user profile">
                                <button type="submit" class="btn btn-sm btn-outline-info w-100">
                                    <i class="bi bi-unlock me-1"></i> Unlock Account ({{ $user->failed_attempts }} failed attempts)
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Audit & Login History --}}
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-clock-history text-secondary me-2"></i>Recent Activity &amp; Audit Logs</h6>
                    <span class="badge bg-secondary-subtle text-secondary">{{ count($activityLogs) }} events</span>
                </div>
                <div class="card-body p-0" style="max-height: 480px; overflow-y: auto;">
                    @forelse($activityLogs as $log)
                        @php
                            $sevColor = match($log->severity) {
                                'critical' => 'danger',
                                'warning'  => 'warning',
                                'info'     => 'info',
                                default    => 'secondary',
                            };
                        @endphp
                        <div class="p-3 border-bottom">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-bold small text-dark">{{ $log->action }}</span>
                                <span class="badge bg-{{ $sevColor }} extra-small">{{ strtoupper($log->severity) }}</span>
                            </div>
                            <p class="text-muted extra-small mb-1">{{ $log->description }}</p>
                            <div class="d-flex justify-content-between extra-small text-muted">
                                <span><i class="bi bi-calendar3 me-1"></i>{{ $log->logged_at ? $log->logged_at->format('Y-m-d H:i') : $log->created_at->format('Y-m-d H:i') }}</span>
                                <span>{{ $log->ip_address ?? '—' }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="p-4 text-center text-muted small">No recorded activity logs for this user.</div>
                    @endforelse
                </div>
            </div>

        </div>
    </div>

</div>
@endsection
