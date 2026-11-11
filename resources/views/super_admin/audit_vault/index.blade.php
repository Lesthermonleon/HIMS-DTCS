@extends('layouts.app')

@section('title', 'Protected Audit Trail Review Vault')
@section('page-title', 'Super Admin — Protected Audit Vault')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('super-admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active" aria-current="page">Audit Vault</li>
@endsection

@section('content')
<div class="container-fluid px-0">
    {{-- Header & Governance Alert --}}
    <div class="card border-0 shadow-sm mb-4" style="border-left: 4px solid #dc2626 !important;">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h5 class="fw-bold mb-1 text-dark"><i class="bi bi-safe2-fill text-danger me-2"></i> Protected Audit Trail Vault</h5>
                <p class="text-muted small mb-0">
                    Application-level append-only audit trail repository. Records cannot be modified or deleted. Access to this vault and all record exports are strictly logged.
                </p>
            </div>
            <div>
                <a href="{{ route('super-admin.audit-vault.export', request()->query()) }}" class="btn btn-outline-danger rounded-pill px-3">
                    <i class="bi bi-file-earmark-arrow-down me-1"></i> Export Filtered CSV
                </a>
            </div>
        </div>
    </div>

    {{-- Filter Panel --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('super-admin.audit-vault.index') }}" class="row g-2 align-items-end small">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Search Action / Description</label>
                    <input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Keywords...">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Module</label>
                    <select name="module" class="form-select form-select-sm">
                        <option value="">All Modules</option>
                        @foreach($modules as $mod)
                            <option value="{{ $mod }}" {{ request('module') === $mod ? 'selected' : '' }}>{{ $mod }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Severity</label>
                    <select name="severity" class="form-select form-select-sm">
                        <option value="">All Severities</option>
                        <option value="INFO" {{ request('severity') === 'INFO' ? 'selected' : '' }}>INFO</option>
                        <option value="WARNING" {{ request('severity') === 'WARNING' ? 'selected' : '' }}>WARNING</option>
                        <option value="CRITICAL" {{ request('severity') === 'CRITICAL' ? 'selected' : '' }}>CRITICAL</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Result</label>
                    <select name="result" class="form-select form-select-sm">
                        <option value="">All Results</option>
                        <option value="SUCCESS" {{ request('result') === 'SUCCESS' ? 'selected' : '' }}>SUCCESS</option>
                        <option value="FAILED" {{ request('result') === 'FAILED' ? 'selected' : '' }}>FAILED</option>
                        <option value="BLOCKED" {{ request('result') === 'BLOCKED' ? 'selected' : '' }}>BLOCKED</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">IP Address</label>
                    <input type="text" name="ip_address" value="{{ request('ip_address') }}" class="form-control form-control-sm" placeholder="e.g. 192.168.">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Date From</label>
                    <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Date To</label>
                    <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary w-100">Apply Filters</button>
                    @if(request()->hasAny(['search', 'module', 'severity', 'result', 'ip_address', 'date_from', 'date_to']))
                        <a href="{{ route('super-admin.audit-vault.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- Audit Table --}}
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-uppercase">
                        <tr>
                            <th class="ps-3">Timestamp (UTC)</th>
                            <th>Actor / Account</th>
                            <th>Module</th>
                            <th>Action</th>
                            <th>Severity</th>
                            <th>Result</th>
                            <th>IP Address</th>
                            <th class="pe-3 text-end">Details</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        @forelse($logs as $log)
                            <tr>
                                <td class="ps-3 font-monospace text-muted">{{ $log->logged_at ? $log->logged_at->format('Y-m-d H:i:s') : '—' }}</td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $log->user?->name ?? 'System' }}</div>
                                    <div class="text-muted" style="font-size: 11px;">{{ $log->user?->email ?? 'System Engine' }}</div>
                                </td>
                                <td><span class="badge bg-secondary-subtle text-secondary border">{{ $log->module }}</span></td>
                                <td class="fw-semibold">{{ $log->action }}</td>
                                <td>
                                    <span class="badge {{ $log->severity_badge_class }}">
                                        <i class="bi {{ $log->severity_icon }} me-1"></i>{{ $log->severity }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge {{ $log->result_badge_class }}">{{ $log->result }}</span>
                                </td>
                                <td class="font-monospace text-muted">{{ $log->ip_address ?? '—' }}</td>
                                <td class="pe-3 text-end">
                                    <a href="{{ route('super-admin.audit-vault.show', $log) }}" class="btn btn-sm btn-outline-secondary py-0 px-2">
                                        View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    No audit trail records match the specified filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($logs->hasPages())
                <div class="p-3 border-top">
                    {{ $logs->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
