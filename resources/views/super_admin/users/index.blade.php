@extends('layouts.app')

@section('title', 'Super Admin — Full User Account Management')
@section('page-title', 'User Account Governance')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('super-admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active" aria-current="page">User Management</li>
@endsection

@section('content')
<div class="container-fluid px-0">

    {{-- Header Banner --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h5 class="fw-bold mb-1 text-dark">
                    <i class="bi bi-people-fill text-primary me-2"></i> Comprehensive DTCS User Account Management
                </h5>
                <p class="text-muted small mb-0">
                    Full authority to create, view, edit, configure, and maintain all user accounts across every role: Super Admin, Admin, Doctor, Staff, and Patient.
                </p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('super-admin.users.create') }}" class="btn btn-primary rounded-pill px-3">
                    <i class="bi bi-person-plus-fill me-1"></i> Create New User
                </a>
            </div>
        </div>
    </div>

    {{-- Stats Cards & Filter Pills --}}
    <div class="row g-2 mb-4">
        <div class="col-6 col-md-4 col-lg">
            <a href="{{ route('super-admin.users.index') }}" class="card border-0 shadow-sm text-decoration-none h-100 {{ !$status && !$roleSlug ? 'bg-primary text-white' : '' }}">
                <div class="card-body text-center py-2 px-1">
                    <div class="small fw-semibold {{ !$status && !$roleSlug ? 'text-white' : 'text-muted' }}">All Users</div>
                    <h4 class="fw-bold mb-0 {{ !$status && !$roleSlug ? 'text-white' : 'text-dark' }}">{{ $stats['total'] }}</h4>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg">
            <a href="{{ route('super-admin.users.index', ['role' => 'super_admin']) }}" class="card border-0 shadow-sm text-decoration-none h-100 {{ $roleSlug === 'super_admin' ? 'bg-danger text-white' : '' }}">
                <div class="card-body text-center py-2 px-1">
                    <div class="small fw-semibold {{ $roleSlug === 'super_admin' ? 'text-white' : 'text-muted' }}">Super Admin</div>
                    <h4 class="fw-bold mb-0 {{ $roleSlug === 'super_admin' ? 'text-white' : 'text-danger' }}">{{ $stats['super_admin'] }}</h4>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg">
            <a href="{{ route('super-admin.users.index', ['role' => 'admin']) }}" class="card border-0 shadow-sm text-decoration-none h-100 {{ $roleSlug === 'admin' ? 'bg-indigo text-white' : '' }}" style="{{ $roleSlug === 'admin' ? 'background-color:#6610f2!important;' : '' }}">
                <div class="card-body text-center py-2 px-1">
                    <div class="small fw-semibold {{ $roleSlug === 'admin' ? 'text-white' : 'text-muted' }}">Admin</div>
                    <h4 class="fw-bold mb-0 {{ $roleSlug === 'admin' ? 'text-white' : 'text-primary' }}">{{ $stats['admin'] }}</h4>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg">
            <a href="{{ route('super-admin.users.index', ['role' => 'doctor']) }}" class="card border-0 shadow-sm text-decoration-none h-100 {{ $roleSlug === 'doctor' ? 'bg-info text-white' : '' }}">
                <div class="card-body text-center py-2 px-1">
                    <div class="small fw-semibold {{ $roleSlug === 'doctor' ? 'text-white' : 'text-muted' }}">Doctors</div>
                    <h4 class="fw-bold mb-0 {{ $roleSlug === 'doctor' ? 'text-white' : 'text-info' }}">{{ $stats['doctor'] }}</h4>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg">
            <a href="{{ route('super-admin.users.index', ['status' => 'active']) }}" class="card border-0 shadow-sm text-decoration-none h-100 {{ $status === 'active' ? 'bg-success text-white' : '' }}">
                <div class="card-body text-center py-2 px-1">
                    <div class="small fw-semibold {{ $status === 'active' ? 'text-white' : 'text-muted' }}">Active</div>
                    <h4 class="fw-bold mb-0 {{ $status === 'active' ? 'text-white' : 'text-success' }}">{{ $stats['active'] }}</h4>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg">
            <a href="{{ route('super-admin.users.index', ['status' => 'inactive']) }}" class="card border-0 shadow-sm text-decoration-none h-100 {{ $status === 'inactive' ? 'bg-warning text-white' : '' }}">
                <div class="card-body text-center py-2 px-1">
                    <div class="small fw-semibold {{ $status === 'inactive' ? 'text-white' : 'text-muted' }}">Suspended</div>
                    <h4 class="fw-bold mb-0 {{ $status === 'inactive' ? 'text-white' : 'text-warning' }}">{{ $stats['inactive'] }}</h4>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg">
            <a href="{{ route('super-admin.users.index', ['status' => 'locked']) }}" class="card border-0 shadow-sm text-decoration-none h-100 {{ $status === 'locked' ? 'bg-danger text-white' : '' }}">
                <div class="card-body text-center py-2 px-1">
                    <div class="small fw-semibold {{ $status === 'locked' ? 'text-white' : 'text-muted' }}">Locked Out</div>
                    <h4 class="fw-bold mb-0 {{ $status === 'locked' ? 'text-white' : 'text-danger' }}">{{ $stats['locked'] }}</h4>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg">
            <a href="{{ route('super-admin.users.index', ['status' => 'archived']) }}" class="card border-0 shadow-sm text-decoration-none h-100 {{ $status === 'archived' ? 'bg-secondary text-white' : '' }}">
                <div class="card-body text-center py-2 px-1">
                    <div class="small fw-semibold {{ $status === 'archived' ? 'text-white' : 'text-muted' }}">Archived</div>
                    <h4 class="fw-bold mb-0 {{ $status === 'archived' ? 'text-white' : 'text-secondary' }}">{{ $stats['archived'] }}</h4>
                </div>
            </a>
        </div>
    </div>

    {{-- Filter & Search Card --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('super-admin.users.index') }}" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" value="{{ $search }}" class="form-control border-start-0" placeholder="Search name, email, employee ID...">
                    </div>
                </div>
                <div class="col-md-2">
                    <select name="role" class="form-select form-select-sm">
                        <option value="">All Roles</option>
                        @foreach($roles as $r)
                            <option value="{{ $r->slug }}" {{ $roleSlug === $r->slug ? 'selected' : '' }}>{{ $r->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="department" class="form-select form-select-sm">
                        <option value="">All Departments</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept }}" {{ $department === $dept ? 'selected' : '' }}>{{ $dept }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ $status === 'inactive' ? 'selected' : '' }}>Suspended / Inactive</option>
                        <option value="locked" {{ $status === 'locked' ? 'selected' : '' }}>Locked Out</option>
                        <option value="archived" {{ $status === 'archived' ? 'selected' : '' }}>Archived (Soft Deleted)</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-funnel me-1"></i> Apply Filters</button>
                    <a href="{{ route('super-admin.users.index') }}" class="btn btn-sm btn-outline-secondary" title="Reset Filters"><i class="bi bi-arrow-counterclockwise"></i></a>
                </div>
            </form>
        </div>
    </div>

    {{-- Users Table --}}
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-uppercase">
                        <tr>
                            <th class="ps-3">User</th>
                            <th>Role</th>
                            <th>Department</th>
                            <th>Employee ID / Contact</th>
                            <th>Status</th>
                            <th>Session</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $user)
                        @php
                            $primaryRole = $user->primaryRoleModel();
                            $roleBadgeColor = match($primaryRole?->slug) {
                                'super_admin'    => 'danger',
                                'admin'          => 'primary',
                                'doctor'         => 'info',
                                'med-tech', 'rad-tech', 'radiologist', 'pharmacist', 'dietitian', 'or-coordinator' => 'warning',
                                'patient'        => 'success',
                                default          => 'secondary',
                            };
                            $hasActiveSession = $user->hasActiveSession();
                            $isLastSuperAdmin = ($user->isSuperAdmin() && $user->is_active && $stats['active'] <= 1 && User::countActiveSuperAdmins() <= 1);
                        @endphp
                        <tr class="{{ $user->trashed() ? 'table-light opacity-75' : '' }}">
                            <td class="ps-3">
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center text-secondary fw-bold me-2" style="width: 38px; height: 38px; font-size: 0.85rem;">
                                        {{ strtoupper(substr($user->name, 0, 2)) }}
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark d-flex align-items-center gap-1">
                                            <a href="{{ route('super-admin.users.show', $user) }}" class="text-decoration-none text-dark hover-primary">
                                                {{ $user->name }}
                                            </a>
                                            @if($user->id === auth()->id())
                                                <span class="badge bg-light text-muted border extra-small">You</span>
                                            @endif
                                            @if($user->isSuperAdmin())
                                                <i class="bi bi-shield-fill-check text-danger" title="Super Administrator"></i>
                                            @endif
                                        </div>
                                        <div class="text-muted small">{{ $user->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if($primaryRole)
                                    <span class="badge bg-{{ $roleBadgeColor }} px-2 py-1">
                                        {{ $primaryRole->name }}
                                    </span>
                                @else
                                    <span class="badge bg-secondary">No Role</span>
                                @endif
                            </td>
                            <td>
                                <span class="small">{{ $user->department ?? '—' }}</span>
                            </td>
                            <td>
                                <div class="small fw-semibold text-secondary">{{ $user->employee_id ?? '—' }}</div>
                                @if($user->phone)
                                    <div class="text-muted extra-small"><i class="bi bi-telephone me-1"></i>{{ $user->phone }}</div>
                                @endif
                            </td>
                            <td>
                                @if($user->trashed())
                                    <span class="badge bg-secondary"><i class="bi bi-archive-fill me-1"></i>Archived</span>
                                @elseif($user->locked_at)
                                    <span class="badge bg-danger"><i class="bi bi-lock-fill me-1"></i>Locked</span>
                                @elseif($user->is_active)
                                    <span class="badge bg-success"><i class="bi bi-check-circle-fill me-1"></i>Active</span>
                                @else
                                    <span class="badge bg-warning text-dark"><i class="bi bi-pause-circle-fill me-1"></i>Suspended</span>
                                @endif
                            </td>
                            <td>
                                @if($hasActiveSession)
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                        <i class="bi bi-broadcast me-1"></i>Online
                                    </span>
                                    <div class="text-muted extra-small mt-1">
                                        {{ $user->last_activity_at ? $user->last_activity_at->diffForHumans() : 'Active' }}
                                    </div>
                                @else
                                    <span class="badge bg-light text-muted border px-2 py-1">Idle / Offline</span>
                                @endif
                            </td>
                            <td class="text-end pe-3">
                                <div class="dropdown d-inline-block">
                                    <button class="btn btn-sm btn-light border dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        Actions
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                        <li>
                                            <a class="dropdown-item" href="{{ route('super-admin.users.show', $user) }}">
                                                <i class="bi bi-eye text-primary me-2"></i> View Account Overview
                                            </a>
                                        </li>
                                        @if(!$user->trashed())
                                            <li>
                                                <a class="dropdown-item" href="{{ route('super-admin.users.edit', $user) }}">
                                                    <i class="bi bi-pencil text-secondary me-2"></i> Edit Account &amp; Role
                                                </a>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>

                                            {{-- Password Reset --}}
                                            <li>
                                                <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#resetPasswordModal"
                                                        data-user-id="{{ $user->id }}" data-user-email="{{ $user->email }}">
                                                    <i class="bi bi-key text-warning me-2"></i> Dispatch Password Reset
                                                </button>
                                            </li>

                                            {{-- Unlock account if locked --}}
                                            @if($user->locked_at)
                                                <li>
                                                    <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#unlockModal"
                                                            data-user-id="{{ $user->id }}" data-user-email="{{ $user->email }}">
                                                        <i class="bi bi-unlock text-info me-2"></i> Unlock Account
                                                    </button>
                                                </li>
                                            @endif

                                            {{-- Terminate session --}}
                                            @if($hasActiveSession)
                                                <li>
                                                    <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#terminateSessionModal"
                                                            data-user-id="{{ $user->id }}" data-user-email="{{ $user->email }}">
                                                        <i class="bi bi-box-arrow-right text-warning me-2"></i> Terminate Active Session
                                                    </button>
                                                </li>
                                            @endif

                                            {{-- Toggle active status --}}
                                            @if($user->id !== auth()->id() && !$isLastSuperAdmin)
                                                <li>
                                                    <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#toggleStatusModal"
                                                            data-user-id="{{ $user->id }}" data-user-email="{{ $user->email }}" data-current-status="{{ $user->is_active ? 'active' : 'suspended' }}">
                                                        @if($user->is_active)
                                                            <i class="bi bi-pause-circle text-warning me-2"></i> Suspend Account
                                                        @else
                                                            <i class="bi bi-play-circle text-success me-2"></i> Activate Account
                                                        @endif
                                                    </button>
                                                </li>
                                            @endif

                                            {{-- Archive --}}
                                            @if($user->id !== auth()->id() && !$isLastSuperAdmin)
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <button type="button" class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#archiveModal"
                                                            data-user-id="{{ $user->id }}" data-user-email="{{ $user->email }}">
                                                        <i class="bi bi-archive text-danger me-2"></i> Archive Account
                                                    </button>
                                                </li>
                                            @endif
                                        @else
                                            {{-- Archived Account Actions --}}
                                            <li>
                                                <button type="button" class="dropdown-item text-success" data-bs-toggle="modal" data-bs-target="#restoreModal"
                                                        data-user-id="{{ $user->id }}" data-user-email="{{ $user->email }}">
                                                    <i class="bi bi-arrow-counterclockwise text-success me-2"></i> Restore Account
                                                </button>
                                            </li>
                                            @if($user->id !== auth()->id())
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <button type="button" class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#forceDeleteModal"
                                                            data-user-id="{{ $user->id }}" data-user-email="{{ $user->email }}">
                                                        <i class="bi bi-trash3 text-danger me-2"></i> Permanently Delete (If Safe)
                                                    </button>
                                                </li>
                                            @endif
                                        @endif
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
                                No user accounts matching the selected criteria.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($users->hasPages())
            <div class="card-footer bg-white border-top py-3">
                {{ $users->links() }}
            </div>
        @endif
    </div>

</div>

{{-- ── MODAL: Toggle Active / Suspended ── --}}
<div class="modal fade" id="toggleStatusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" id="toggleStatusForm" action="">
            @csrf
            @method('PATCH')
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="toggleStatusTitle"><i class="bi bi-shield-exclamation text-warning me-2"></i>Change Account Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="toggleStatusPrompt" class="mb-3">Are you sure you want to update the status of this account?</p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Audit Justification / Reason <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="3" required placeholder="Mandatory reason for updating account status (min 5 characters)..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning" id="toggleStatusSubmitBtn">Confirm Status Change</button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- ── MODAL: Unlock Account ── --}}
<div class="modal fade" id="unlockModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" id="unlockForm" action="">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-unlock text-info me-2"></i>Unlock Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">Reset failed login attempts and unlock account <strong id="unlockUserEmail"></strong>? The user will be immediately allowed to log in.</p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Reason for Unlocking <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="2" required placeholder="Reason for unlocking account..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info text-white">Unlock Account</button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- ── MODAL: Password Reset ── --}}
<div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" id="resetPasswordForm" action="">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-key text-warning me-2"></i>Dispatch Password Reset</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">Generate a secure password reset token and email instructions to <strong id="resetUserEmail"></strong>? Any active sessions will be terminated for security.</p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Audit Justification / Reason <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="2" required placeholder="Reason for dispatching password reset..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Dispatch Reset Email</button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- ── MODAL: Terminate Session ── --}}
<div class="modal fade" id="terminateSessionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" id="terminateSessionForm" action="">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-box-arrow-right text-warning me-2"></i>Terminate Active Session</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">Terminate the current active login session for <strong id="terminateUserEmail"></strong>? The user will be immediately signed out on their active device.</p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Reason (Optional)</label>
                        <input type="text" name="reason" class="form-control" placeholder="E.g. Security revocation or session cleanup">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Terminate Session</button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- ── MODAL: Archive Account ── --}}
<div class="modal fade" id="archiveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" id="archiveForm" action="">
            @csrf
            @method('DELETE')
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger"><i class="bi bi-archive me-2"></i>Archive User Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">Soft-delete and archive user account <strong id="archiveUserEmail"></strong>? The user will no longer be able to authenticate. Associated clinical history remains intact.</p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Reason for Archiving <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="3" required placeholder="Mandatory audit justification for archiving this account..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Archive Account</button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- ── MODAL: Restore Account ── --}}
<div class="modal fade" id="restoreModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" id="restoreForm" action="">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-success"><i class="bi bi-arrow-counterclockwise me-2"></i>Restore Archived Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">Restore archived user account <strong id="restoreUserEmail"></strong> to active service?</p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Reason for Restoration <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="2" required placeholder="Reason for restoring this account..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Restore Account</button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- ── MODAL: Force Delete Account (Clinical Safety Guard) ── --}}
<div class="modal fade" id="forceDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" id="forceDeleteForm" action="">
            @csrf
            @method('DELETE')
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill me-2"></i>Permanent Deletion Guard</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger py-2 small mb-3">
                        <i class="bi bi-shield-lock-fill me-1"></i>
                        <strong>Clinical Compliance Guard:</strong> Permanent deletion is restricted by medical record retention policies. If this account has any associated clinical records (lab requests, prescriptions, surgery schedules, or medical charts), permanent deletion will be strictly rejected.
                    </div>
                    <p class="mb-3">Attempt permanent deletion of <strong id="forceDeleteUserEmail"></strong>? This operation cannot be reversed.</p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Reason for Permanent Deletion <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="3" required placeholder="Mandatory justification for permanent deletion..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Permanently Delete</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Toggle Status Modal
    const toggleStatusModal = document.getElementById('toggleStatusModal');
    if (toggleStatusModal) {
        toggleStatusModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const userId = button.getAttribute('data-user-id');
            const email = button.getAttribute('data-user-email');
            const status = button.getAttribute('data-current-status');
            const form = document.getElementById('toggleStatusForm');
            form.action = `/super-admin/users/${userId}/toggle-status`;
            
            const prompt = document.getElementById('toggleStatusPrompt');
            const btn = document.getElementById('toggleStatusSubmitBtn');
            if (status === 'active') {
                prompt.innerHTML = `Are you sure you want to <strong>suspend</strong> account <code>${email}</code>? Any active sessions will be terminated immediately.`;
                btn.className = 'btn btn-warning';
                btn.textContent = 'Suspend Account';
            } else {
                prompt.innerHTML = `Are you sure you want to <strong>activate</strong> account <code>${email}</code>?`;
                btn.className = 'btn btn-success';
                btn.textContent = 'Activate Account';
            }
        });
    }

    // Unlock Modal
    const unlockModal = document.getElementById('unlockModal');
    if (unlockModal) {
        unlockModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const userId = button.getAttribute('data-user-id');
            const email = button.getAttribute('data-user-email');
            document.getElementById('unlockForm').action = `/super-admin/users/${userId}/unlock`;
            document.getElementById('unlockUserEmail').textContent = email;
        });
    }

    // Reset Password Modal
    const resetModal = document.getElementById('resetPasswordModal');
    if (resetModal) {
        resetModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const userId = button.getAttribute('data-user-id');
            const email = button.getAttribute('data-user-email');
            document.getElementById('resetPasswordForm').action = `/super-admin/users/${userId}/reset-password`;
            document.getElementById('resetUserEmail').textContent = email;
        });
    }

    // Terminate Session Modal
    const termModal = document.getElementById('terminateSessionModal');
    if (termModal) {
        termModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const userId = button.getAttribute('data-user-id');
            const email = button.getAttribute('data-user-email');
            document.getElementById('terminateSessionForm').action = `/super-admin/users/${userId}/terminate-session`;
            document.getElementById('terminateUserEmail').textContent = email;
        });
    }

    // Archive Modal
    const archiveModal = document.getElementById('archiveModal');
    if (archiveModal) {
        archiveModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const userId = button.getAttribute('data-user-id');
            const email = button.getAttribute('data-user-email');
            document.getElementById('archiveForm').action = `/super-admin/users/${userId}`;
            document.getElementById('archiveUserEmail').textContent = email;
        });
    }

    // Restore Modal
    const restoreModal = document.getElementById('restoreModal');
    if (restoreModal) {
        restoreModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const userId = button.getAttribute('data-user-id');
            const email = button.getAttribute('data-user-email');
            document.getElementById('restoreForm').action = `/super-admin/users/${userId}/restore`;
            document.getElementById('restoreUserEmail').textContent = email;
        });
    }

    // Force Delete Modal
    const forceDeleteModal = document.getElementById('forceDeleteModal');
    if (forceDeleteModal) {
        forceDeleteModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const userId = button.getAttribute('data-user-id');
            const email = button.getAttribute('data-user-email');
            document.getElementById('forceDeleteForm').action = `/super-admin/users/${userId}/force-delete`;
            document.getElementById('forceDeleteUserEmail').textContent = email;
        });
    }
});
</script>
@endsection
