@extends('layouts.app')

@section('title', 'Doctor Provisioning Authorizations')
@section('page-title', 'Super Admin — Doctor Provisioning')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('super-admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active" aria-current="page">Doctor Authorizations</li>
@endsection

@section('content')
<div class="container-fluid px-0">
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h5 class="fw-bold mb-1 text-dark"><i class="bi bi-patch-check-fill text-teal me-2" style="color:#0d9488;"></i> Clinician Account Provisioning Governance</h5>
            <p class="text-muted small mb-0">
                Hospital policy requires Super Administrator authorization for clinician account provisioning. Once authorized, the Doctor account is initialized with proper clinical permissions and MediSense AI access, and an onboarding password setup link is dispatched.
            </p>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3">
            <h6 class="m-0 fw-bold text-dark"><i class="bi bi-list-task me-2 text-primary"></i> Provisioning Authorization Requests</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-uppercase">
                        <tr>
                            <th class="ps-3">Request ID</th>
                            <th>Clinician Name</th>
                            <th>Department</th>
                            <th>Requested By</th>
                            <th>Requested Date</th>
                            <th>Status</th>
                            <th class="pe-3 text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        @forelse($requests as $req)
                            @php
                                $payload = $req->payload ?? [];
                            @endphp
                            <tr>
                                <td class="ps-3 font-monospace text-primary fw-bold">#REQ-{{ str_pad($req->id, 5, '0', STR_PAD_LEFT) }}</td>
                                <td>
                                    <div class="fw-bold text-dark">Dr. {{ $payload['name'] ?? 'Unknown' }}</div>
                                    <div class="text-muted" style="font-size: 11px;">{{ $payload['email'] ?? '—' }}</div>
                                </td>
                                <td>{{ $payload['department'] ?? 'Clinical Medicine' }}</td>
                                <td>
                                    <div>{{ $req->requestedBy?->name ?? 'Admin' }}</div>
                                    <div class="text-muted" style="font-size: 10px;">{{ $req->requestedBy?->email ?? '—' }}</div>
                                </td>
                                <td class="text-muted">{{ $req->created_at ? $req->created_at->format('M d, Y H:i') : '—' }}</td>
                                <td>
                                    @if($req->status === 'approved')
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">Approved</span>
                                    @elseif($req->status === 'rejected')
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Rejected</span>
                                    @else
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle">Pending Review</span>
                                    @endif
                                </td>
                                <td class="pe-3 text-end">
                                    @if($req->status === 'pending')
                                        <button type="button" class="btn btn-sm btn-outline-success py-0 px-2 me-1"
                                                data-bs-toggle="modal" data-bs-target="#approveModal-{{ $req->id }}">
                                            Authorize
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2"
                                                data-bs-toggle="modal" data-bs-target="#rejectModal-{{ $req->id }}">
                                            Reject
                                        </button>
                                    @else
                                        <span class="text-muted small">Decided by {{ $req->decidedBy?->name ?? 'System' }}</span>
                                    @endif
                                </td>
                            </tr>

                            {{-- Approve Modal --}}
                            @if($req->status === 'pending')
                                <div class="modal fade" id="approveModal-{{ $req->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <form action="{{ route('super-admin.doctor-provisioning.approve', $req) }}" method="POST" class="modal-content">
                                            @csrf
                                            <div class="modal-header">
                                                <h6 class="modal-title fw-bold">Authorize Doctor Provisioning</h6>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body small">
                                                <p>Approve account creation for <strong>Dr. {{ $payload['name'] ?? '' }}</strong> ({{ $payload['email'] ?? '' }}). This will initialize their clinician account and dispatch a password setup link.</p>
                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold text-danger">Authorization Audit Reason *</label>
                                                    <input type="text" name="reason" class="form-control form-control-sm" placeholder="Specify authorization basis or clinical license verification" required minlength="5">
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-sm btn-success">Authorize & Provision</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                {{-- Reject Modal --}}
                                <div class="modal fade" id="rejectModal-{{ $req->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <form action="{{ route('super-admin.doctor-provisioning.reject', $req) }}" method="POST" class="modal-content">
                                            @csrf
                                            <div class="modal-header">
                                                <h6 class="modal-title fw-bold text-danger">Reject Doctor Provisioning Request</h6>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body small">
                                                <p>Reject the provisioning request for <strong>Dr. {{ $payload['name'] ?? '' }}</strong>.</p>
                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold text-danger">Rejection Reason *</label>
                                                    <input type="text" name="reason" class="form-control form-control-sm" placeholder="Specify reason for denial" required minlength="5">
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-sm btn-danger">Confirm Rejection</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            @endif
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    No clinician provisioning requests recorded yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($requests->hasPages())
                <div class="p-3 border-top">
                    {{ $requests->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
