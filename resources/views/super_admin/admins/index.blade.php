@extends('layouts.app')

@section('title', 'Privileged Administrator Accounts')
@section('page-title', 'Super Admin — Administrator Accounts')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('super-admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active" aria-current="page">Admin Accounts</li>
@endsection

@section('content')
<div class="container-fluid px-0">
    {{-- Header Banner --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h5 class="fw-bold mb-1 text-dark"><i class="bi bi-person-lock text-primary me-2"></i> Privileged Administrator Account Governance</h5>
                <p class="text-muted small mb-0">
                    Manage privileged hospital administrative accounts. Ordinary Administrators cannot create, modify, or escalate accounts to Administrator or Super Administrator status.
                </p>
            </div>
            <div>
                <button type="button" class="btn btn-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#createAdminModal">
                    <i class="bi bi-person-plus-fill me-1"></i> Provision Administrator
                </button>
            </div>
        </div>
    </div>

    {{-- Stats Cards & Filter Pills --}}
    <div class="row g-3 mb-4">
        <div class="col-md">
            <a href="{{ route('super-admin.admins.index') }}" class="card border-0 shadow-sm text-decoration-none h-100 {{ !$status ? 'bg-primary text-white' : '' }}">
                <div class="card-body text-center py-2">
                    <div class="small fw-semibold {{ !$status ? 'text-white' : 'text-muted' }}">All Admins</div>
                    <h4 class="fw-bold mb-0 {{ !$status ? 'text-white' : 'text-dark' }}">{{ $stats['total'] }}</h4>
                </div>
            </a>
        </div>
        <div class="col-md">
            <a href="{{ route('super-admin.admins.index', ['status' => 'active']) }}" class="card border-0 shadow-sm text-decoration-none h-100 {{ $status === 'active' ? 'bg-success text-white' : '' }}">
                <div class="card-body text-center py-2">
                    <div class="small fw-semibold {{ $status === 'active' ? 'text-white' : 'text-muted' }}">Active</div>
                    <h4 class="fw-bold mb-0 {{ $status === 'active' ? 'text-white' : 'text-success' }}">{{ $stats['active'] }}</h4>
                </div>
            </a>
        </div>
        <div class="col-md">
            <a href="{{ route('super-admin.admins.index', ['status' => 'inactive']) }}" class="card border-0 shadow-sm text-decoration-none h-100 {{ $status === 'inactive' ? 'bg-warning text-white' : '' }}">
                <div class="card-body text-center py-2">
                    <div class="small fw-semibold {{ $status === 'inactive' ? 'text-white' : 'text-muted' }}">Inactive</div>
                    <h4 class="fw-bold mb-0 {{ $status === 'inactive' ? 'text-white' : 'text-warning' }}">{{ $stats['inactive'] }}</h4>
                </div>
            </a>
        </div>
        <div class="col-md">
            <a href="{{ route('super-admin.admins.index', ['status' => 'locked']) }}" class="card border-0 shadow-sm text-decoration-none h-100 {{ $status === 'locked' ? 'bg-danger text-white' : '' }}">
                <div class="card-body text-center py-2">
                    <div class="small fw-semibold {{ $status === 'locked' ? 'text-white' : 'text-muted' }}">Locked Out</div>
                    <h4 class="fw-bold mb-0 {{ $status === 'locked' ? 'text-white' : 'text-danger' }}">{{ $stats['locked'] }}</h4>
                </div>
            </a>
        </div>
        <div class="col-md">
            <a href="{{ route('super-admin.admins.index', ['status' => 'archived']) }}" class="card border-0 shadow-sm text-decoration-none h-100 {{ $status === 'archived' ? 'bg-secondary text-white' : '' }}">
                <div class="card-body text-center py-2">
                    <div class="small fw-semibold {{ $status === 'archived' ? 'text-white' : 'text-muted' }}">Archived</div>
                    <h4 class="fw-bold mb-0 {{ $status === 'archived' ? 'text-white' : 'text-secondary' }}">{{ $stats['archived'] }}</h4>
                </div>
            </a>
        </div>
    </div>

    {{-- Admins Table --}}
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3">
            <form method="GET" action="{{ route('super-admin.admins.index') }}" class="row g-2 align-items-center">
                @if($status)
                    <input type="hidden" name="status" value="{{ $status }}">
                @endif
                <div class="col-md-5">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" value="{{ request('search') }}" class="form-control border-start-0" placeholder="Search administrator by name or email...">
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-sm btn-primary w-100">Search</button>
                </div>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-uppercase">
                        <tr>
                            <th class="ps-3">Administrator</th>
                            <th>Department</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Lockout State</th>
                            <th class="pe-3 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        @forelse($admins as $admin)
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-bold text-dark">{{ $admin->name }}</div>
                                    <div class="text-muted" style="font-size: 11px;">{{ $admin->email }}</div>
                                </td>
                                <td>{{ $admin->department ?? 'Hospital Administration' }}</td>
                                <td>{{ $admin->phone ?? '—' }}</td>
                                <td>
                                    @if($admin->trashed())
                                        <span class="badge bg-secondary text-white">Archived</span>
                                    @elseif($admin->is_active)
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>
                                    @else
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle">Inactive</span>
                                    @endif
                                </td>
                                <td>
                                    @if($admin->isLockedOut())
                                        <span class="badge bg-danger text-white"><i class="bi bi-lock-fill me-1"></i> Locked Out</span>
                                    @else
                                        <span class="text-muted">Normal</span>
                                    @endif
                                </td>
                                <td class="pe-3 text-end">
                                    @if($admin->trashed())
                                        <button type="button" class="btn btn-sm btn-outline-success py-0 px-2" data-bs-toggle="modal" data-bs-target="#restoreModal-{{ $admin->id }}">
                                            Restore
                                        </button>
                                    @else
                                        @if($admin->isLockedOut())
                                            <button type="button" class="btn btn-sm btn-outline-warning py-0 px-2 me-1" data-bs-toggle="modal" data-bs-target="#unlockModal-{{ $admin->id }}">
                                                Unlock
                                            </button>
                                        @endif

                                        <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 me-1" data-bs-toggle="modal" data-bs-target="#statusModal-{{ $admin->id }}" {{ $admin->id === auth()->id() ? 'disabled' : '' }}>
                                            {{ $admin->is_active ? 'Deactivate' : 'Activate' }}
                                        </button>

                                        <button type="button" class="btn btn-sm btn-outline-info py-0 px-2 me-1" data-bs-toggle="modal" data-bs-target="#resetModal-{{ $admin->id }}">
                                            Reset PW
                                        </button>

                                        <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2" data-bs-toggle="modal" data-bs-target="#archiveModal-{{ $admin->id }}" {{ $admin->id === auth()->id() ? 'disabled' : '' }}>
                                            Archive
                                        </button>
                                    @endif
                                </td>
                            </tr>

                            {{-- Toggle Status Modal --}}
                            @if(!$admin->trashed())
                                <div class="modal fade" id="statusModal-{{ $admin->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <form action="{{ route('super-admin.admins.toggle-active', $admin) }}" method="POST" class="modal-content">
                                            @csrf
                                            @method('PATCH')
                                            <div class="modal-header">
                                                <h6 class="modal-title fw-bold">{{ $admin->is_active ? 'Deactivate' : 'Activate' }} Admin [{{ $admin->name }}]</h6>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body small">
                                                <p>Are you sure you want to {{ $admin->is_active ? 'deactivate' : 'activate' }} the privileged account for <strong>{{ $admin->email }}</strong>?</p>
                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold text-danger">Mandatory Audit Reason *</label>
                                                    <input type="text" name="reason" class="form-control form-control-sm" placeholder="Specify governance reason" required minlength="5">
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
                                <div class="modal fade" id="unlockModal-{{ $admin->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <form action="{{ route('super-admin.admins.unlock', $admin) }}" method="POST" class="modal-content">
                                            @csrf
                                            <div class="modal-header">
                                                <h6 class="modal-title fw-bold">Unlock Administrator Account</h6>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body small">
                                                <p>Account for <strong>{{ $admin->name }}</strong> ({{ $admin->email }}) is locked due to repeated authentication failures.</p>
                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold text-danger">Mandatory Reason for Unlocking *</label>
                                                    <input type="text" name="reason" class="form-control form-control-sm" placeholder="Specify recovery authorization reason" required minlength="5">
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
                                <div class="modal fade" id="resetModal-{{ $admin->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <form action="{{ route('super-admin.admins.reset-password', $admin) }}" method="POST" class="modal-content">
                                            @csrf
                                            <div class="modal-header">
                                                <h6 class="modal-title fw-bold">Dispatch Password Reset Link</h6>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body small">
                                                <p>A secure, time-limited password reset link will be sent to <strong>{{ $admin->email }}</strong>. No default passwords will be assigned.</p>
                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold text-danger">Mandatory Reason for Reset *</label>
                                                    <input type="text" name="reason" class="form-control form-control-sm" placeholder="e.g. Administrator requested password recovery" required minlength="5">
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-sm btn-info text-white">Dispatch Reset Link</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                {{-- Archive Modal --}}
                                <div class="modal fade" id="archiveModal-{{ $admin->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <form action="{{ route('super-admin.admins.archive', $admin) }}" method="POST" class="modal-content">
                                            @csrf
                                            @method('DELETE')
                                            <div class="modal-header">
                                                <h6 class="modal-title fw-bold text-danger">Archive Administrator Account</h6>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body small">
                                                <p class="text-danger fw-semibold">This will revoke all administrative access for {{ $admin->name }} ({{ $admin->email }}) and soft-delete the record.</p>
                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold text-danger">Mandatory Audit Reason for Archiving *</label>
                                                    <input type="text" name="reason" class="form-control form-control-sm" placeholder="Specify termination or decommissioning reason" required minlength="5">
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-sm btn-danger">Confirm Archival</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            @else
                                {{-- Restore Modal --}}
                                <div class="modal fade" id="restoreModal-{{ $admin->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <form action="{{ route('super-admin.admins.restore', $admin->id) }}" method="POST" class="modal-content">
                                            @csrf
                                            <div class="modal-header">
                                                <h6 class="modal-title fw-bold">Restore Archived Administrator Account</h6>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body small">
                                                <p>Reinstate the administrator account for <strong>{{ $admin->name }}</strong> ({{ $admin->email }}).</p>
                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold text-danger">Mandatory Reason for Restoration *</label>
                                                    <input type="text" name="reason" class="form-control form-control-sm" placeholder="Specify reason for restoring account" required minlength="5">
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-sm btn-success">Restore Account</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            @endif
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    No administrator accounts match the selected criteria.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($admins->hasPages())
                <div class="p-3 border-top">
                    {{ $admins->links() }}
                </div>
            @endif
        </div>
    </div>

    {{-- Provision Admin Modal --}}
    <div class="modal fade" id="createAdminModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form action="{{ route('super-admin.admins.store') }}" method="POST" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h6 class="modal-title fw-bold">Provision Administrator Account</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body small">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Full Name *</label>
                        <input type="text" name="name" class="form-control form-control-sm" placeholder="e.g. Maria Santos" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Hospital Email Address *</label>
                        <input type="email" name="email" class="form-control form-control-sm" placeholder="e.g. msantos@dtcs-hims.ph" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Department</label>
                        <input type="text" name="department" class="form-control form-control-sm" value="Hospital Administration">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Contact Phone</label>
                        <input type="text" name="phone" class="form-control form-control-sm" placeholder="+63 ...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-danger">Mandatory Reason for Provisioning *</label>
                        <input type="text" name="reason" class="form-control form-control-sm" placeholder="Specify authorization reference or hospital appointment order" required minlength="5">
                    </div>
                    <div class="alert alert-info border-0 small mb-0">
                        <i class="bi bi-shield-check me-1"></i> A secure password creation link will be dispatched to the provided email. No default credentials are used.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Provision & Dispatch Link</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
