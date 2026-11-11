@extends('layouts.app')

@section('title', 'Doctor Directory & Clinician Account Operations')
@section('page-title', 'Admin — Doctor Account Management')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active" aria-current="page">Doctor Directory</li>
@endsection

@section('content')
<div class="container-fluid px-0">
    {{-- Header Banner & Request Provisioning Button --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h5 class="fw-bold mb-1 text-dark"><i class="bi bi-person-badge-fill text-teal me-2" style="color:#0d9488;"></i> Clinician & Doctor Account Management</h5>
                <p class="text-muted small mb-0">
                    Day-to-day administration of hospital doctor accounts, account activation/deactivation, and lockout recovery. New doctor accounts require Super Administrator authorization.
                </p>
            </div>
            <div>
                <button type="button" class="btn btn-teal text-white rounded-pill px-3" style="background-color: #0d9488;" data-bs-toggle="modal" data-bs-target="#requestDoctorModal">
                    <i class="bi bi-plus-circle me-1"></i> Request Doctor Provisioning
                </button>
            </div>
        </div>
    </div>

    {{-- Pending Requests Banner (if any) --}}
    @if($pendingRequests->isNotEmpty())
        <div class="alert alert-warning border-0 shadow-sm mb-4 small">
            <h6 class="fw-bold text-dark mb-2"><i class="bi bi-clock-history me-1"></i> Pending Doctor Provisioning Authorizations ({{ $pendingRequests->count() }})</h6>
            <div class="table-responsive">
                <table class="table table-sm table-borderless mb-0">
                    <thead>
                        <tr class="text-muted">
                            <th>Clinician</th>
                            <th>Department</th>
                            <th>Requested On</th>
                            <th>Reason</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pendingRequests as $pr)
                            <tr>
                                <td class="fw-semibold">Dr. {{ $pr->payload['name'] ?? '—' }} ({{ $pr->payload['email'] ?? '—' }})</td>
                                <td>{{ $pr->payload['department'] ?? '—' }}</td>
                                <td>{{ $pr->created_at ? $pr->created_at->format('M d, Y') : '—' }}</td>
                                <td>{{ $pr->requested_reason }}</td>
                                <td><span class="badge bg-warning-subtle text-warning border border-warning">Pending Super Admin Authorization</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Status Filters & Stats --}}
    <div class="row g-3 mb-4">
        <div class="col-md">
            <a href="{{ route('admin.doctors.index') }}" class="card border-0 shadow-sm text-decoration-none h-100 {{ !$status ? 'bg-primary text-white' : '' }}">
                <div class="card-body text-center py-2">
                    <div class="small fw-semibold {{ !$status ? 'text-white' : 'text-muted' }}">All Doctors</div>
                    <h4 class="fw-bold mb-0 {{ !$status ? 'text-white' : 'text-dark' }}">{{ $stats['total'] }}</h4>
                </div>
            </a>
        </div>
        <div class="col-md">
            <a href="{{ route('admin.doctors.index', ['status' => 'active']) }}" class="card border-0 shadow-sm text-decoration-none h-100 {{ $status === 'active' ? 'bg-success text-white' : '' }}">
                <div class="card-body text-center py-2">
                    <div class="small fw-semibold {{ $status === 'active' ? 'text-white' : 'text-muted' }}">Active</div>
                    <h4 class="fw-bold mb-0 {{ $status === 'active' ? 'text-white' : 'text-success' }}">{{ $stats['active'] }}</h4>
                </div>
            </a>
        </div>
        <div class="col-md">
            <a href="{{ route('admin.doctors.index', ['status' => 'inactive']) }}" class="card border-0 shadow-sm text-decoration-none h-100 {{ $status === 'inactive' ? 'bg-warning text-white' : '' }}">
                <div class="card-body text-center py-2">
                    <div class="small fw-semibold {{ $status === 'inactive' ? 'text-white' : 'text-muted' }}">Inactive</div>
                    <h4 class="fw-bold mb-0 {{ $status === 'inactive' ? 'text-white' : 'text-warning' }}">{{ $stats['inactive'] }}</h4>
                </div>
            </a>
        </div>
        <div class="col-md">
            <a href="{{ route('admin.doctors.index', ['status' => 'locked']) }}" class="card border-0 shadow-sm text-decoration-none h-100 {{ $status === 'locked' ? 'bg-danger text-white' : '' }}">
                <div class="card-body text-center py-2">
                    <div class="small fw-semibold {{ $status === 'locked' ? 'text-white' : 'text-muted' }}">Locked Out</div>
                    <h4 class="fw-bold mb-0 {{ $status === 'locked' ? 'text-white' : 'text-danger' }}">{{ $stats['locked'] }}</h4>
                </div>
            </a>
        </div>
        <div class="col-md">
            <a href="{{ route('admin.doctors.index', ['status' => 'archived']) }}" class="card border-0 shadow-sm text-decoration-none h-100 {{ $status === 'archived' ? 'bg-secondary text-white' : '' }}">
                <div class="card-body text-center py-2">
                    <div class="small fw-semibold {{ $status === 'archived' ? 'text-white' : 'text-muted' }}">Archived</div>
                    <h4 class="fw-bold mb-0 {{ $status === 'archived' ? 'text-white' : 'text-secondary' }}">{{ $stats['archived'] }}</h4>
                </div>
            </a>
        </div>
    </div>

    {{-- Doctors Table --}}
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3">
            <form method="GET" action="{{ route('admin.doctors.index') }}" class="row g-2 align-items-center">
                @if($status)
                    <input type="hidden" name="status" value="{{ $status }}">
                @endif
                <div class="col-md-5">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" value="{{ request('search') }}" class="form-control border-start-0" placeholder="Search doctor by name, email, or employee ID...">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="department" class="form-select form-select-sm">
                        <option value="">All Clinical Departments</option>
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
                            <th class="ps-3">Clinician Name</th>
                            <th>Department</th>
                            <th>Employee ID</th>
                            <th>Status</th>
                            <th>Lockout State</th>
                            <th class="pe-3 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        @forelse($doctors as $doc)
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-bold text-dark">Dr. {{ $doc->name }}</div>
                                    <div class="text-muted" style="font-size: 11px;">{{ $doc->email }}</div>
                                </td>
                                <td>{{ $doc->department ?? 'General Medicine' }}</td>
                                <td class="font-monospace text-muted">{{ $doc->employee_id ?? '—' }}</td>
                                <td>
                                    @if($doc->trashed())
                                        <span class="badge bg-secondary text-white">Archived</span>
                                    @elseif($doc->is_active)
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>
                                    @else
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle">Inactive</span>
                                    @endif
                                </td>
                                <td>
                                    @if($doc->isLockedOut())
                                        <span class="badge bg-danger text-white"><i class="bi bi-lock-fill me-1"></i> Locked Out</span>
                                    @else
                                        <span class="text-muted">Normal</span>
                                    @endif
                                </td>
                                <td class="pe-3 text-end">
                                    @if(!$doc->trashed())
                                        @if($doc->isLockedOut())
                                            <button type="button" class="btn btn-sm btn-outline-warning py-0 px-2 me-1"
                                                    data-bs-toggle="modal" data-bs-target="#unlockModal-{{ $doc->id }}">
                                                Unlock
                                            </button>
                                        @endif

                                        <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 me-1"
                                                data-bs-toggle="modal" data-bs-target="#statusModal-{{ $doc->id }}">
                                            {{ $doc->is_active ? 'Deactivate' : 'Activate' }}
                                        </button>

                                        <button type="button" class="btn btn-sm btn-outline-info py-0 px-2 me-1"
                                                data-bs-toggle="modal" data-bs-target="#resetModal-{{ $doc->id }}">
                                            Reset PW
                                        </button>
                                    @else
                                        <span class="text-muted small">Archived</span>
                                    @endif
                                </td>
                            </tr>

                            {{-- Status Change Modal --}}
                            @if(!$doc->trashed())
                                <div class="modal fade" id="statusModal-{{ $doc->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <form action="{{ route('admin.doctors.toggle-active', $doc) }}" method="POST" class="modal-content">
                                            @csrf
                                            @method('PATCH')
                                            <div class="modal-header">
                                                <h6 class="modal-title fw-bold">{{ $doc->is_active ? 'Deactivate' : 'Activate' }} Dr. {{ $doc->name }}</h6>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body small">
                                                <p>Are you sure you want to {{ $doc->is_active ? 'deactivate' : 'activate' }} the account for <strong>Dr. {{ $doc->name }}</strong>?</p>
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
                                <div class="modal fade" id="unlockModal-{{ $doc->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <form action="{{ route('admin.doctors.unlock', $doc) }}" method="POST" class="modal-content">
                                            @csrf
                                            <div class="modal-header">
                                                <h6 class="modal-title fw-bold">Unlock Doctor Account</h6>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body small">
                                                <p>Account for <strong>Dr. {{ $doc->name }}</strong> is locked due to failed authentication attempts.</p>
                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold text-danger">Mandatory Reason for Unlocking *</label>
                                                    <input type="text" name="reason" class="form-control form-control-sm" placeholder="Specify unlock authorization reason" required minlength="5">
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
                                <div class="modal fade" id="resetModal-{{ $doc->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <form action="{{ route('admin.doctors.reset-password', $doc) }}" method="POST" class="modal-content">
                                            @csrf
                                            <div class="modal-header">
                                                <h6 class="modal-title fw-bold">Dispatch Password Reset Link</h6>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body small">
                                                <p>Dispatch a secure password reset link to Dr. {{ $doc->name }} ({{ $doc->email }}).</p>
                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold text-danger">Mandatory Reason for Reset *</label>
                                                    <input type="text" name="reason" class="form-control form-control-sm" placeholder="e.g. Clinician requested password recovery" required minlength="5">
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
                                    No doctor accounts found matching the specified filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($doctors->hasPages())
                <div class="p-3 border-top">
                    {{ $doctors->links() }}
                </div>
            @endif
        </div>
    </div>

    {{-- Request Doctor Provisioning Modal --}}
    <div class="modal fade" id="requestDoctorModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form action="{{ route('admin.doctors.request-provisioning') }}" method="POST" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h6 class="modal-title fw-bold">Request Doctor Provisioning</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body small">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Doctor Full Name *</label>
                        <input type="text" name="name" class="form-control form-control-sm" placeholder="e.g. Juanita Reyes, MD" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Hospital Email Address *</label>
                        <input type="email" name="email" class="form-control form-control-sm" placeholder="e.g. jreyes@dtcs-hims.ph" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Clinical Department *</label>
                        <input type="text" name="department" class="form-control form-control-sm" placeholder="e.g. Internal Medicine / Cardiology" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Employee ID</label>
                            <input type="text" name="employee_id" class="form-control form-control-sm" placeholder="e.g. DOC-1042">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Phone</label>
                            <input type="text" name="phone" class="form-control form-control-sm" placeholder="+63 ...">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-danger">Justification / Hospital Policy Basis *</label>
                        <textarea name="requested_reason" class="form-control form-control-sm" rows="2" placeholder="Specify clinical credentials and hiring authorization" required minlength="5"></textarea>
                    </div>
                    <div class="alert alert-info border-0 small mb-0">
                        <i class="bi bi-shield-lock me-1"></i> This request will be forwarded to the Super Administrator for privileged authorization before the account is initialized.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Submit for Authorization</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
