@extends('layouts.app')

@section('title', 'Staff Directory & Operations')
@section('page-title', 'Admin — Hospital Staff Management')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active" aria-current="page">Staff Directory</li>
@endsection

@section('content')
<div class="container-fluid px-0">
    {{-- Header Banner & Create Button --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h5 class="fw-bold mb-1 text-dark"><i class="bi bi-people-fill text-success me-2"></i> Clinical & Operational Staff Directory</h5>
                <p class="text-muted small mb-0">
                    Manage operational staff across the 6 hospital roles: Medical Technologists, Radiologic Technologists, Radiologists, Pharmacists, Dietitians, and OR Coordinators.
                </p>
            </div>
            <div>
                <button type="button" class="btn btn-success rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#createStaffModal">
                    <i class="bi bi-person-plus-fill me-1"></i> Add Staff Account
                </button>
            </div>
        </div>
    </div>

    {{-- Stats Cards & Filter Pills --}}
    <div class="row g-3 mb-4">
        <div class="col-md">
            <a href="{{ route('admin.staff.index') }}" class="card border-0 shadow-sm text-decoration-none h-100 {{ !$status && !$roleSlug ? 'bg-primary text-white' : '' }}">
                <div class="card-body text-center py-2">
                    <div class="small fw-semibold {{ !$status && !$roleSlug ? 'text-white' : 'text-muted' }}">All Staff</div>
                    <h4 class="fw-bold mb-0 {{ !$status && !$roleSlug ? 'text-white' : 'text-dark' }}">{{ $stats['total'] }}</h4>
                </div>
            </a>
        </div>
        <div class="col-md">
            <a href="{{ route('admin.staff.index', ['status' => 'active']) }}" class="card border-0 shadow-sm text-decoration-none h-100 {{ $status === 'active' ? 'bg-success text-white' : '' }}">
                <div class="card-body text-center py-2">
                    <div class="small fw-semibold {{ $status === 'active' ? 'text-white' : 'text-muted' }}">Active</div>
                    <h4 class="fw-bold mb-0 {{ $status === 'active' ? 'text-white' : 'text-success' }}">{{ $stats['active'] }}</h4>
                </div>
            </a>
        </div>
        <div class="col-md">
            <a href="{{ route('admin.staff.index', ['status' => 'inactive']) }}" class="card border-0 shadow-sm text-decoration-none h-100 {{ $status === 'inactive' ? 'bg-warning text-white' : '' }}">
                <div class="card-body text-center py-2">
                    <div class="small fw-semibold {{ $status === 'inactive' ? 'text-white' : 'text-muted' }}">Inactive</div>
                    <h4 class="fw-bold mb-0 {{ $status === 'inactive' ? 'text-white' : 'text-warning' }}">{{ $stats['inactive'] }}</h4>
                </div>
            </a>
        </div>
        <div class="col-md">
            <a href="{{ route('admin.staff.index', ['status' => 'locked']) }}" class="card border-0 shadow-sm text-decoration-none h-100 {{ $status === 'locked' ? 'bg-danger text-white' : '' }}">
                <div class="card-body text-center py-2">
                    <div class="small fw-semibold {{ $status === 'locked' ? 'text-white' : 'text-muted' }}">Locked Out</div>
                    <h4 class="fw-bold mb-0 {{ $status === 'locked' ? 'text-white' : 'text-danger' }}">{{ $stats['locked'] }}</h4>
                </div>
            </a>
        </div>
        <div class="col-md">
            <a href="{{ route('admin.staff.index', ['status' => 'archived']) }}" class="card border-0 shadow-sm text-decoration-none h-100 {{ $status === 'archived' ? 'bg-secondary text-white' : '' }}">
                <div class="card-body text-center py-2">
                    <div class="small fw-semibold {{ $status === 'archived' ? 'text-white' : 'text-muted' }}">Archived</div>
                    <h4 class="fw-bold mb-0 {{ $status === 'archived' ? 'text-white' : 'text-secondary' }}">{{ $stats['archived'] }}</h4>
                </div>
            </a>
        </div>
    </div>

    {{-- Staff Table --}}
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3">
            <form method="GET" action="{{ route('admin.staff.index') }}" class="row g-2 align-items-center">
                @if($status)
                    <input type="hidden" name="status" value="{{ $status }}">
                @endif
                <div class="col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" value="{{ request('search') }}" class="form-control border-start-0" placeholder="Search staff member...">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="role" class="form-select form-select-sm">
                        <option value="">All Staff Roles</option>
                        @foreach($staffRoles as $r)
                            <option value="{{ $r->slug }}" {{ request('role') === $r->slug ? 'selected' : '' }}>{{ $r->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="department" class="form-select form-select-sm">
                        <option value="">All Departments</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept }}" {{ request('department') === $dept ? 'selected' : '' }}>{{ $dept }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-sm btn-primary w-100">Filter</button>
                </div>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-uppercase">
                        <tr>
                            <th class="ps-3">Staff Member</th>
                            <th>Role</th>
                            <th>Department</th>
                            <th>Status</th>
                            <th>Lockout State</th>
                            <th class="pe-3 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        @forelse($staff as $st)
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-bold text-dark">{{ $st->name }}</div>
                                    <div class="text-muted" style="font-size: 11px;">{{ $st->email }}</div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary-subtle text-secondary border">
                                        {{ $st->roles->first()?->name ?? 'Staff' }}
                                    </span>
                                </td>
                                <td>{{ $st->department ?? 'General' }}</td>
                                <td>
                                    @if($st->trashed())
                                        <span class="badge bg-secondary text-white">Archived</span>
                                    @elseif($st->is_active)
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>
                                    @else
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle">Inactive</span>
                                    @endif
                                </td>
                                <td>
                                    @if($st->isLockedOut())
                                        <span class="badge bg-danger text-white"><i class="bi bi-lock-fill me-1"></i> Locked Out</span>
                                    @else
                                        <span class="text-muted">Normal</span>
                                    @endif
                                </td>
                                <td class="pe-3 text-end">
                                    @if(!$st->trashed())
                                        @if($st->isLockedOut())
                                            <button type="button" class="btn btn-sm btn-outline-warning py-0 px-2 me-1"
                                                    data-bs-toggle="modal" data-bs-target="#unlockModal-{{ $st->id }}">
                                                Unlock
                                            </button>
                                        @endif

                                        <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 me-1"
                                                data-bs-toggle="modal" data-bs-target="#statusModal-{{ $st->id }}">
                                            {{ $st->is_active ? 'Deactivate' : 'Activate' }}
                                        </button>

                                        <button type="button" class="btn btn-sm btn-outline-info py-0 px-2"
                                                data-bs-toggle="modal" data-bs-target="#resetModal-{{ $st->id }}">
                                            Reset PW
                                        </button>
                                    @else
                                        <span class="text-muted small">Archived</span>
                                    @endif
                                </td>
                            </tr>

                            {{-- Status Change Modal --}}
                            @if(!$st->trashed())
                                <div class="modal fade" id="statusModal-{{ $st->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <form action="{{ route('admin.staff.toggle-active', $st) }}" method="POST" class="modal-content">
                                            @csrf
                                            @method('PATCH')
                                            <div class="modal-header">
                                                <h6 class="modal-title fw-bold">{{ $st->is_active ? 'Deactivate' : 'Activate' }} Staff [{{ $st->name }}]</h6>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body small">
                                                <p>Are you sure you want to {{ $st->is_active ? 'deactivate' : 'activate' }} the account for <strong>{{ $st->name }}</strong>?</p>
                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold text-danger">Mandatory Reason *</label>
                                                    <input type="text" name="reason" class="form-control form-control-sm" placeholder="Specify operational reason" required minlength="5">
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-sm btn-primary">Confirm</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                {{-- Unlock Modal --}}
                                <div class="modal fade" id="unlockModal-{{ $st->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <form action="{{ route('admin.staff.unlock', $st) }}" method="POST" class="modal-content">
                                            @csrf
                                            <div class="modal-header">
                                                <h6 class="modal-title fw-bold">Unlock Staff Account</h6>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body small">
                                                <p>Account for <strong>{{ $st->name }}</strong> is locked due to repeated authentication failures.</p>
                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold text-danger">Mandatory Reason for Unlocking *</label>
                                                    <input type="text" name="reason" class="form-control form-control-sm" placeholder="Specify unlock recovery reason" required minlength="5">
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-sm btn-warning">Unlock Account</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                {{-- Reset Password Modal --}}
                                <div class="modal fade" id="resetModal-{{ $st->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <form action="{{ route('admin.staff.reset-password', $st) }}" method="POST" class="modal-content">
                                            @csrf
                                            <div class="modal-header">
                                                <h6 class="modal-title fw-bold">Dispatch Password Reset Link</h6>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body small">
                                                <p>A secure password reset notification will be dispatched to <strong>{{ $st->email }}</strong>.</p>
                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold text-danger">Mandatory Reason for Reset *</label>
                                                    <input type="text" name="reason" class="form-control form-control-sm" placeholder="e.g. Staff member requested password reset" required minlength="5">
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-sm btn-info text-white">Send Reset Link</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            @endif
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    No staff accounts found matching the specified filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($staff->hasPages())
                <div class="p-3 border-top">
                    {{ $staff->links() }}
                </div>
            @endif
        </div>
    </div>

    {{-- Create Staff Modal --}}
    <div class="modal fade" id="createStaffModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form action="{{ route('admin.staff.store') }}" method="POST" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h6 class="modal-title fw-bold">Create Staff Account</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body small">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Staff Full Name *</label>
                        <input type="text" name="name" class="form-control form-control-sm" placeholder="e.g. Roberto Gomez" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Hospital Email Address *</label>
                        <input type="email" name="email" class="form-control form-control-sm" placeholder="e.g. rgomez@dtcs-hims.ph" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Assigned Staff Role *</label>
                        <select name="role_slug" class="form-select form-select-sm" required>
                            @foreach($staffRoles as $r)
                                <option value="{{ $r->slug }}">{{ $r->name }} ({{ strtoupper($r->slug) }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Department *</label>
                        <input type="text" name="department" class="form-control form-control-sm" placeholder="e.g. Clinical Laboratory / Radiology / Pharmacy" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Employee ID</label>
                            <input type="text" name="employee_id" class="form-control form-control-sm" placeholder="e.g. STF-2041">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Phone</label>
                            <input type="text" name="phone" class="form-control form-control-sm" placeholder="+63 ...">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-danger">Mandatory Audit Reason *</label>
                        <input type="text" name="reason" class="form-control form-control-sm" placeholder="Specify hiring reference or staffing order" required minlength="5">
                    </div>
                    <div class="alert alert-info border-0 small mb-0">
                        <i class="bi bi-shield-check me-1"></i> A secure password creation invitation link will be dispatched to the staff member's email.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Create & Send Invitation</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
