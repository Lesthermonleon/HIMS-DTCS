@extends('layouts.app')

@section('title', 'Audit Record Detail #' . $auditLog->id)
@section('page-title', 'Super Admin — Audit Record Detail')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('super-admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('super-admin.audit-vault.index') }}">Audit Vault</a></li>
    <li class="breadcrumb-item active" aria-current="page">Record #{{ $auditLog->id }}</li>
@endsection

@section('content')
<div class="container-fluid px-0">
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 fw-bold text-dark"><i class="bi bi-file-earmark-lock text-primary me-2"></i> Audit Trail Event Record #{{ $auditLog->id }}</h6>
            <a href="{{ route('super-admin.audit-vault.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3">&larr; Back to Vault</a>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label text-muted small fw-semibold">Timestamp (UTC)</label>
                    <div class="font-monospace text-dark fw-bold">{{ $auditLog->logged_at ? $auditLog->logged_at->format('Y-m-d H:i:s') : '—' }}</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label text-muted small fw-semibold">IP Address</label>
                    <div class="font-monospace text-dark fw-bold">{{ $auditLog->ip_address ?? '—' }}</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label text-muted small fw-semibold">Governing Module</label>
                    <div><span class="badge bg-secondary-subtle text-secondary border fs-6">{{ $auditLog->module }}</span></div>
                </div>
                <div class="col-md-4">
                    <label class="form-label text-muted small fw-semibold">Severity Level</label>
                    <div>
                        <span class="badge {{ $auditLog->severity_badge_class }} fs-6">
                            <i class="bi {{ $auditLog->severity_icon }} me-1"></i>{{ $auditLog->severity }}
                        </span>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label text-muted small fw-semibold">Action Outcome</label>
                    <div><span class="badge {{ $auditLog->result_badge_class }} fs-6">{{ $auditLog->result }}</span></div>
                </div>
                <div class="col-12 border-top pt-3">
                    <label class="form-label text-muted small fw-semibold">Action / Event Name</label>
                    <h5 class="fw-bold text-dark mb-0">{{ $auditLog->action }}</h5>
                </div>
                <div class="col-12">
                    <label class="form-label text-muted small fw-semibold">Event Description & Context</label>
                    <div class="p-3 bg-light rounded text-dark border font-monospace small">
                        {{ $auditLog->description }}
                    </div>
                </div>
                <div class="col-md-6 border-top pt-3">
                    <label class="form-label text-muted small fw-semibold">Responsible User / Actor</label>
                    <div class="fw-bold">{{ $auditLog->user?->name ?? 'System Process' }}</div>
                    <div class="text-muted small">{{ $auditLog->user?->email ?? 'System Engine (Non-user)' }}</div>
                </div>
                <div class="col-md-6 border-top pt-3">
                    <label class="form-label text-muted small fw-semibold">Assigned Roles at Runtime</label>
                    <div>
                        @if($auditLog->user && $auditLog->user->roles->isNotEmpty())
                            @foreach($auditLog->user->roles as $r)
                                <span class="badge bg-primary-subtle text-primary border me-1">{{ $r->name }}</span>
                            @endforeach
                        @else
                            <span class="text-muted small">No Role / System Actor</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
