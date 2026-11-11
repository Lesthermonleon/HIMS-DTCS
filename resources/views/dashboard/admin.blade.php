@extends('layouts.app')
@section('title', 'System Administrator Dashboard')
@section('page-title', 'System Administrator Dashboard')

@push('styles')
<style>
    .module-card-clickable,
    .admin-tool-tile {
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease, background-color 0.2s ease;
        cursor: pointer;
    }
    .module-card-clickable:hover,
    .module-card-clickable:focus-visible,
    .admin-tool-tile:hover,
    .admin-tool-tile:focus-visible {
        border-color: #0d9488 !important;
        background-color: rgba(13, 148, 136, 0.04) !important;
        box-shadow: 0 4px 14px rgba(13, 148, 136, 0.12) !important;
        transform: translateY(-2px);
    }
    .module-card-clickable:hover .icon-shape,
    .module-card-clickable:focus-visible .icon-shape,
    .admin-tool-tile:hover .icon-shape,
    .admin-tool-tile:focus-visible .icon-shape {
        background-color: #0d9488 !important;
        color: #ffffff !important;
    }
    .card-hover-elevate {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .card-hover-elevate:hover {
        transform: translateY(-3px);
        box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.08) !important;
    }

    /* ── Dark Theme Overrides for Admin Dashboard ── */
    html[data-theme="dark"] .bg-white {
        background-color: var(--card, #111827) !important;
    }
    html[data-theme="dark"] .card-header.bg-white {
        background-color: var(--card, #111827) !important;
        border-bottom-color: var(--line, #374151) !important;
    }
    html[data-theme="dark"] .list-group-item {
        background-color: var(--card, #111827) !important;
        color: var(--text, #f3f4f6) !important;
        border-color: var(--line, #374151) !important;
    }
    html[data-theme="dark"] .table-light,
    html[data-theme="dark"] .table-light th {
        background-color: #1f2937 !important;
        color: #e5e7eb !important;
        border-bottom-color: var(--line, #374151) !important;
    }
    html[data-theme="dark"] .bg-light,
    html[data-theme="dark"] .bg-light.bg-opacity-50 {
        background-color: #1f2937 !important;
        color: var(--text, #f3f4f6) !important;
    }
    html[data-theme="dark"] .border-light-subtle {
        border-color: var(--line, #374151) !important;
    }
    html[data-theme="dark"] .text-dark {
        color: #f9fafb !important;
    }
    html[data-theme="dark"] .badge.bg-light {
        background-color: #1f2937 !important;
        color: #f3f4f6 !important;
        border-color: var(--line, #374151) !important;
    }
    html[data-theme="dark"] .badge.bg-danger-subtle {
        background-color: rgba(239, 68, 68, 0.2) !important;
        color: #fca5a5 !important;
    }
    html[data-theme="dark"] .badge.bg-warning-subtle {
        background-color: rgba(245, 158, 11, 0.2) !important;
        color: #fcd34d !important;
    }
    html[data-theme="dark"] .badge.bg-info-subtle {
        background-color: rgba(14, 165, 233, 0.2) !important;
        color: #7dd3fc !important;
    }
    html[data-theme="dark"] .badge.bg-secondary-subtle {
        background-color: rgba(107, 114, 128, 0.2) !important;
        color: #d1d5db !important;
    }
    html[data-theme="dark"] .badge.bg-success-subtle {
        background-color: rgba(13, 148, 136, 0.2) !important;
        color: #5eead4 !important;
    }
</style>
@endpush

@section('content')

{{-- ══════════════════════════════════════════════════════════════════════
     SECTION 1: ACCOUNT & PATIENT OVERVIEW (KPI CARDS)
     ══════════════════════════════════════════════════════════════════════ --}}
<div class="row g-3 mb-4">
    {{-- Card 1: Total Users --}}
    <div class="col-6 col-md-4 col-xl-2">
        <a href="{{ route('admin.users.index') }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 card-hover-elevate">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-semibold text-uppercase">Total Users</span>
                        <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-2">
                            <i class="bi bi-people-fill fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold text-dark mb-0">{{ number_format($stats['total_users']) }}</h3>
                    <div class="small text-primary mt-2 d-flex align-items-center justify-content-between">
                        <span>Manage Users</span>
                        <i class="bi bi-arrow-right"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>

    {{-- Card 2: Active Users --}}
    <div class="col-6 col-md-4 col-xl-2">
        <a href="{{ route('admin.users.index', ['status' => 'active']) }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 card-hover-elevate">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-semibold text-uppercase">Active Users</span>
                        <div class="bg-success bg-opacity-10 text-success rounded-circle p-2">
                            <i class="bi bi-person-check-fill fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold text-dark mb-0">{{ number_format($stats['active_users']) }}</h3>
                    <div class="small text-success mt-2 d-flex align-items-center justify-content-between">
                        <span>Active Accounts</span>
                        <i class="bi bi-arrow-right"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>

    {{-- Card 3: Total Patients --}}
    <div class="col-6 col-md-4 col-xl-2">
        <a href="{{ route('patients.index') }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 card-hover-elevate">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-semibold text-uppercase">Total Patients</span>
                        <div class="bg-info bg-opacity-10 text-info rounded-circle p-2">
                            <i class="bi bi-folder2-open fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold text-dark mb-0">{{ number_format($stats['total_patients']) }}</h3>
                    <div class="small text-info mt-2 d-flex align-items-center justify-content-between">
                        <span>Patient Directory</span>
                        <i class="bi bi-arrow-right"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>

    {{-- Card 4: Inactive Users --}}
    <div class="col-6 col-md-4 col-xl-2">
        <a href="{{ route('admin.users.index', ['status' => 'inactive']) }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 card-hover-elevate">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-semibold text-uppercase">Inactive Users</span>
                        <div class="bg-secondary bg-opacity-10 text-secondary rounded-circle p-2">
                            <i class="bi bi-person-x fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold text-dark mb-0">{{ number_format($stats['inactive_users']) }}</h3>
                    <div class="small text-secondary mt-2 d-flex align-items-center justify-content-between">
                        <span>View Inactive Users</span>
                        @if(($stats['locked_accounts'] ?? 0) > 0)
                            <span class="badge bg-danger-subtle text-danger" style="font-size: 0.7rem;">{{ $stats['locked_accounts'] }} Locked</span>
                        @else
                            <i class="bi bi-arrow-right"></i>
                        @endif
                    </div>
                </div>
            </div>
        </a>
    </div>

    {{-- Card 5: Pending Clinical Requests --}}
    <div class="col-6 col-md-4 col-xl-2">
        <a href="{{ route('admin.operations.index') }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 card-hover-elevate">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-semibold text-uppercase">Pending Clinical Requests</span>
                        <div class="bg-warning bg-opacity-10 text-warning rounded-circle p-2">
                            <i class="bi bi-hourglass-split fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold text-dark mb-0">{{ number_format($stats['pending_clinical_requests'] ?? 0) }}</h3>
                    <div class="small text-warning mt-2 d-flex align-items-center justify-content-between">
                        <span>Operations Monitor</span>
                        <i class="bi bi-arrow-right"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>

    {{-- Card 6: Operational Alerts --}}
    <div class="col-6 col-md-4 col-xl-2">
        <a href="#system-alerts-section" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 card-hover-elevate">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-semibold text-uppercase">Operational Alerts</span>
                        <div class="bg-danger bg-opacity-10 text-danger rounded-circle p-2">
                            <i class="bi bi-shield-exclamation fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold text-dark mb-0">{{ number_format($stats['operational_alerts'] ?? $systemAlerts->count()) }}</h3>
                    <div class="small text-danger mt-2 d-flex align-items-center justify-content-between">
                        <span>Review Alerts</span>
                        <i class="bi bi-arrow-right"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════════════
     SECTION 2: SYSTEM & SECURITY MONITORING & ADMINISTRATIVE TOOLS
     ══════════════════════════════════════════════════════════════════════ --}}
<div class="row g-4 mb-4">
    {{-- Critical System & Security Alerts --}}
    <div class="col-lg-7" id="system-alerts-section">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-danger d-flex align-items-center">
                    <i class="bi bi-shield-alert me-2"></i>Critical System & Security Alerts
                </h6>
                <span class="badge {{ $systemAlerts->isNotEmpty() ? 'bg-danger-subtle text-danger border border-danger-subtle' : 'bg-success-subtle text-success border border-success-subtle' }} px-2 py-1">
                    {{ $systemAlerts->count() }} {{ $systemAlerts->count() === 1 ? 'Alert' : 'Alerts' }}
                </span>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush" style="max-height: 290px; overflow-y: auto;">
                    @forelse($systemAlerts as $alert)
                        <div class="list-group-item p-3 border-bottom">
                            <div class="d-flex align-items-start gap-3">
                                <div class="p-2 rounded bg-{{ $alert['type'] === 'danger' ? 'danger' : ($alert['type'] === 'warning' ? 'warning' : 'info') }} bg-opacity-10 text-{{ $alert['type'] === 'danger' ? 'danger' : ($alert['type'] === 'warning' ? 'warning' : 'info') }}">
                                    <i class="bi {{ $alert['icon'] }} fs-4"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <div class="d-flex align-items-center gap-2">
                                            <h6 class="fw-bold text-dark mb-0">{{ $alert['title'] }}</h6>
                                            @if(isset($alert['severity']))
                                                <span class="badge bg-{{ $alert['type'] === 'danger' ? 'danger' : ($alert['type'] === 'warning' ? 'warning' : 'info') }}-subtle text-{{ $alert['type'] === 'danger' ? 'danger' : ($alert['type'] === 'warning' ? 'warning' : 'info') }}" style="font-size: 0.68rem;">
                                                    {{ $alert['severity'] }}
                                                </span>
                                            @endif
                                        </div>
                                        @if(isset($alert['timestamp']))
                                            <span class="small text-muted" style="font-size: 0.75rem;">{{ $alert['timestamp'] }}</span>
                                        @endif
                                    </div>
                                    <p class="small text-muted mb-2">{{ $alert['description'] }}</p>
                                    <a href="{{ $alert['action_route'] }}" class="btn btn-sm btn-outline-{{ $alert['type'] === 'danger' ? 'danger' : ($alert['type'] === 'warning' ? 'warning' : 'primary') }}">
                                        {{ $alert['action_label'] }} <i class="bi bi-arrow-right ms-1"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-shield-check fs-1 text-teal opacity-75 d-block mb-2" style="color: #0d9488;"></i>
                            <h6 class="fw-bold text-dark mb-1">Security Status Normal</h6>
                            <p class="small text-muted mb-0">No active account lockouts, rate limit blocks, or security alerts detected in the last 24 hours.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- Administrative Operational Tools (Admin Scope Only) --}}
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold text-dark d-flex align-items-center">
                    <i class="bi bi-tools me-2 text-teal" style="color: #0d9488;"></i>Administrative Operational Tools
                </h6>
                <small class="text-muted d-block mt-1" style="font-size: 0.8rem;">
                    Authorized daily administration, monitoring, and audit utilities
                </small>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    {{-- Tool 1: User Accounts --}}
                    <div class="col-12">
                        <a href="{{ route('admin.users.index') }}"
                           class="card border border-light-subtle shadow-xs p-3 bg-white text-decoration-none admin-tool-tile d-flex flex-row align-items-center justify-content-between">
                            <div class="d-flex align-items-center">
                                <div class="icon-shape bg-teal bg-opacity-10 rounded-3 p-2 me-3 d-flex align-items-center justify-content-center transition-all" style="width: 36px; height: 36px; color: #0d9488; background-color: rgba(13, 148, 136, 0.1);">
                                    <i class="bi bi-people-fill fs-5"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark small">User Accounts Oversight</div>
                                    <p class="text-muted mb-0" style="font-size: 11px;">View hospital user directory, unlock accounts, reset passwords</p>
                                </div>
                            </div>
                            <i class="bi bi-chevron-right text-muted small"></i>
                        </a>
                    </div>

                    {{-- Tool 2: Operations Monitor --}}
                    <div class="col-12">
                        <a href="{{ route('admin.operations.index') }}"
                           class="card border border-light-subtle shadow-xs p-3 bg-white text-decoration-none admin-tool-tile d-flex flex-row align-items-center justify-content-between">
                            <div class="d-flex align-items-center">
                                <div class="icon-shape bg-info bg-opacity-10 text-info rounded-3 p-2 me-3 d-flex align-items-center justify-content-center transition-all" style="width: 36px; height: 36px;">
                                    <i class="bi bi-activity fs-5"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark small">Operations Monitor</div>
                                    <p class="text-muted mb-0" style="font-size: 11px;">Track turnaround times (TAT), delays, and volume metrics</p>
                                </div>
                            </div>
                            <i class="bi bi-chevron-right text-muted small"></i>
                        </a>
                    </div>

                    {{-- Tool 3: Patient Directory (Read-Only) --}}
                    <div class="col-12">
                        <a href="{{ route('patients.index') }}"
                           class="card border border-light-subtle shadow-xs p-3 bg-white text-decoration-none admin-tool-tile d-flex flex-row align-items-center justify-content-between">
                            <div class="d-flex align-items-center">
                                <div class="icon-shape bg-primary bg-opacity-10 text-primary rounded-3 p-2 me-3 d-flex align-items-center justify-content-center transition-all" style="width: 36px; height: 36px;">
                                    <i class="bi bi-folder2-open fs-5"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark small">Patient Directory (Read-Only)</div>
                                    <p class="text-muted mb-0" style="font-size: 11px;">Lookup patient profiles and correlate DTCS clinical records</p>
                                </div>
                            </div>
                            <i class="bi bi-chevron-right text-muted small"></i>
                        </a>
                    </div>

                    {{-- Tool 4: System Audit Logs --}}
                    <div class="col-12">
                        <a href="{{ route('admin.audit-logs.index') }}"
                           class="card border border-light-subtle shadow-xs p-3 bg-white text-decoration-none admin-tool-tile d-flex flex-row align-items-center justify-content-between">
                            <div class="d-flex align-items-center">
                                <div class="icon-shape bg-secondary bg-opacity-10 text-secondary rounded-3 p-2 me-3 d-flex align-items-center justify-content-center transition-all" style="width: 36px; height: 36px;">
                                    <i class="bi bi-journal-text fs-5"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark small">System Audit Logs</div>
                                    <p class="text-muted mb-0" style="font-size: 11px;">Review system activity and security-significant events</p>
                                </div>
                            </div>
                            <i class="bi bi-chevron-right text-muted small"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════════════
     SECTION 3: CLINICAL MODULE OPERATIONAL OVERVIEW (LIS, RIS, PMS, SORS, DNMS)
     ══════════════════════════════════════════════════════════════════════ --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <div>
            <h6 class="mb-0 fw-bold text-dark d-flex align-items-center">
                <i class="bi bi-grid-3x3-gap-fill me-2 text-teal" style="color: #0d9488;"></i>Clinical Module Operational Overview
            </h6>
            <small class="text-muted">Real-time pending request volume across all 5 DTCS operational services</small>
        </div>
        <a href="{{ route('reports.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-bar-chart-line me-1"></i> Reports & Analytics
        </a>
    </div>
    <div class="card-body">
        <div class="balanced-grid balanced-grid-5">
            {{-- LIS Card --}}
            <a href="{{ route($moduleStats['lis']['route']) }}"
               class="card border border-light-subtle shadow-xs h-100 p-3 bg-white text-center text-decoration-none module-card-clickable">
                <div class="icon-shape rounded-3 p-2 mx-auto mb-2 d-flex align-items-center justify-content-center transition-all" style="width: 40px; height: 40px; background-color: rgba(13, 148, 136, 0.1); color: #0d9488;">
                    <i class="bi bi-clipboard2-pulse fs-5"></i>
                </div>
                <div class="fw-bold text-dark mb-1">Laboratory (LIS)</div>
                <div class="d-flex justify-content-center flex-wrap gap-1">
                    <span class="badge {{ $moduleStats['lis']['pending'] > 0 ? 'bg-primary-subtle text-primary border border-primary-subtle' : 'bg-light text-muted border' }} py-1 px-2" style="font-size: 0.72rem;">
                        {{ number_format($moduleStats['lis']['pending']) }} Pending
                    </span>
                    <span class="badge bg-success-subtle text-success border border-success-subtle py-1 px-2" style="font-size: 0.72rem;">
                        {{ number_format($moduleStats['lis']['completed'] ?? 0) }} Done
                    </span>
                </div>
            </a>

            {{-- RIS Card --}}
            <a href="{{ route($moduleStats['ris']['route']) }}"
               class="card border border-light-subtle shadow-xs h-100 p-3 bg-white text-center text-decoration-none module-card-clickable">
                <div class="icon-shape rounded-3 p-2 mx-auto mb-2 d-flex align-items-center justify-content-center transition-all" style="width: 40px; height: 40px; background-color: rgba(14, 165, 233, 0.1); color: #0ea5e9;">
                    <i class="bi bi-activity fs-5"></i>
                </div>
                <div class="fw-bold text-dark mb-1">Radiology (RIS)</div>
                <div class="d-flex justify-content-center flex-wrap gap-1">
                    <span class="badge {{ $moduleStats['ris']['pending'] > 0 ? 'bg-info-subtle text-info border border-info-subtle' : 'bg-light text-muted border' }} py-1 px-2" style="font-size: 0.72rem;">
                        {{ number_format($moduleStats['ris']['pending']) }} Pending
                    </span>
                    <span class="badge bg-success-subtle text-success border border-success-subtle py-1 px-2" style="font-size: 0.72rem;">
                        {{ number_format($moduleStats['ris']['completed'] ?? 0) }} Done
                    </span>
                </div>
            </a>

            {{-- PMS Card --}}
            <a href="{{ route($moduleStats['pms']['route']) }}"
               class="card border border-light-subtle shadow-xs h-100 p-3 bg-white text-center text-decoration-none module-card-clickable">
                <div class="icon-shape rounded-3 p-2 mx-auto mb-2 d-flex align-items-center justify-content-center transition-all" style="width: 40px; height: 40px; background-color: rgba(99, 102, 241, 0.1); color: #6366f1;">
                    <i class="bi bi-capsule fs-5"></i>
                </div>
                <div class="fw-bold text-dark mb-1">Pharmacy (PMS)</div>
                <div class="d-flex justify-content-center flex-wrap gap-1">
                    <span class="badge {{ $moduleStats['pms']['pending'] > 0 ? 'bg-primary-subtle text-primary border border-primary-subtle' : 'bg-light text-muted border' }} py-1 px-2" style="font-size: 0.72rem;">
                        {{ number_format($moduleStats['pms']['pending']) }} Pending
                    </span>
                    <span class="badge bg-success-subtle text-success border border-success-subtle py-1 px-2" style="font-size: 0.72rem;">
                        {{ number_format($moduleStats['pms']['completed'] ?? 0) }} Done
                    </span>
                </div>
            </a>

            {{-- SORS Card --}}
            <a href="{{ route($moduleStats['sors']['route']) }}"
               class="card border border-light-subtle shadow-xs h-100 p-3 bg-white text-center text-decoration-none module-card-clickable">
                <div class="icon-shape rounded-3 p-2 mx-auto mb-2 d-flex align-items-center justify-content-center transition-all" style="width: 40px; height: 40px; background-color: rgba(239, 68, 68, 0.1); color: #ef4444;">
                    <i class="bi bi-scissors fs-5"></i>
                </div>
                <div class="fw-bold text-dark mb-1">Surgery (SORS)</div>
                <div class="d-flex justify-content-center flex-wrap gap-1">
                    <span class="badge {{ $moduleStats['sors']['pending'] > 0 ? 'bg-danger-subtle text-danger border border-danger-subtle' : 'bg-light text-muted border' }} py-1 px-2" style="font-size: 0.72rem;">
                        {{ number_format($moduleStats['sors']['pending']) }} Pending
                    </span>
                    <span class="badge bg-success-subtle text-success border border-success-subtle py-1 px-2" style="font-size: 0.72rem;">
                        {{ number_format($moduleStats['sors']['completed'] ?? 0) }} Done
                    </span>
                </div>
            </a>

            {{-- DNMS Card --}}
            <a href="{{ route($moduleStats['dnms']['route']) }}"
               class="card border border-light-subtle shadow-xs h-100 p-3 bg-white text-center text-decoration-none module-card-clickable">
                <div class="icon-shape rounded-3 p-2 mx-auto mb-2 d-flex align-items-center justify-content-center transition-all" style="width: 40px; height: 40px; background-color: rgba(245, 158, 11, 0.1); color: #f59e0b;">
                    <i class="bi bi-apple fs-5"></i>
                </div>
                <div class="fw-bold text-dark mb-1">Nutrition (DNMS)</div>
                <div class="d-flex justify-content-center flex-wrap gap-1">
                    <span class="badge {{ $moduleStats['dnms']['pending'] > 0 ? 'bg-warning-subtle text-warning border border-warning-subtle' : 'bg-light text-muted border' }} py-1 px-2" style="font-size: 0.72rem;">
                        {{ number_format($moduleStats['dnms']['pending']) }} Pending
                    </span>
                    <span class="badge bg-success-subtle text-success border border-success-subtle py-1 px-2" style="font-size: 0.72rem;">
                        {{ number_format($moduleStats['dnms']['completed'] ?? 0) }} Done
                    </span>
                </div>
            </a>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════════════
     SECTION 4: OPERATIONAL REPORTS & CHARTS
     ══════════════════════════════════════════════════════════════════════ --}}
{{-- Row 1: Service Request Trend & Requests by Module --}}
<div class="row g-4 mb-4">
    {{-- Chart A: Service Request Trend --}}
    <div class="col-lg-8">
        <div class="chart-card h-100">
            <div class="chart-header">
                <div>
                    <h3 class="chart-title"><i class="bi bi-graph-up-arrow me-2 text-teal" style="color: #0d9488;"></i>Service Request Trend</h3>
                    <p class="chart-subtitle">Monthly aggregate service request volume across all 5 DTCS clinical modules</p>
                </div>
                <select id="adminTrendPeriod" class="form-select form-select-sm" style="width:auto;">
                    <option value="6">Last 6 Months</option>
                    <option value="12">Last 12 Months</option>
                </select>
            </div>
            <div class="chart-container" style="height: 260px;">
                <canvas id="adminSvcTrendChart"></canvas>
            </div>
        </div>
    </div>

    {{-- Chart B: Requests by Module --}}
    <div class="col-lg-4">
        <div class="chart-card h-100">
            <div class="chart-header">
                <div>
                    <h3 class="chart-title"><i class="bi bi-bar-chart-fill me-2 text-teal" style="color: #0d9488;"></i>Requests by Module</h3>
                    <p class="chart-subtitle">All-time request volume per clinical module</p>
                </div>
            </div>
            <div class="chart-container" style="height: 260px;">
                <canvas id="adminModuleBarChart"></canvas>
            </div>
        </div>
    </div>
</div>

{{-- Row 2: Global Request Status & User Distribution by Role --}}
<div class="row g-4 mb-4">
    {{-- Chart C: Global Request Status --}}
    <div class="col-lg-6">
        <div class="chart-card h-100">
            <div class="chart-header">
                <div>
                    <h3 class="chart-title"><i class="bi bi-pie-chart-fill me-2 text-teal" style="color: #0d9488;"></i>Global Request Status</h3>
                    <p class="chart-subtitle">All-time status distribution across every clinical module</p>
                </div>
            </div>
            <div class="chart-container" style="height: 280px;">
                <canvas id="adminStatusDonut"></canvas>
            </div>
        </div>
    </div>

    {{-- Chart D: User Distribution by Role --}}
    <div class="col-lg-6">
        <div class="chart-card h-100">
            <div class="chart-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div>
                    <h3 class="chart-title"><i class="bi bi-people-fill me-2 text-teal" style="color: #0d9488;"></i>User Distribution by Role</h3>
                    <p class="chart-subtitle">Breakdown of system accounts across active roles</p>
                </div>
                <a href="{{ route('admin.users.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-person-gear me-1"></i> User Accounts
                </a>
            </div>
            <div class="chart-container" style="height: 280px;">
                <canvas id="adminRoleChart"></canvas>
            </div>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════════════
     SECTION 5: ACTIVITY MONITORING & USER PRESENCE
     ══════════════════════════════════════════════════════════════════════ --}}
<div class="row g-4 mb-4">
    {{-- 7-Day Activity Event Trend --}}
    <div class="col-lg-7">
        <div class="chart-card h-100">
            <div class="chart-header">
                <div>
                    <h3 class="chart-title"><i class="bi bi-activity me-2 text-teal" style="color: #0d9488;"></i>System Activity Log Trend</h3>
                    <p class="chart-subtitle">Daily activity events recorded in the last 7 days</p>
                </div>
            </div>
            <div class="chart-container" style="height: 280px;">
                <canvas id="adminActivityChart"></canvas>
            </div>
        </div>
    </div>

    {{-- User Presence & Session Status --}}
    <div class="col-lg-5" id="recent-activity-section">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                <h6 class="mb-0 fw-bold text-dark d-flex align-items-center">
                    <i class="bi bi-person-badge-fill me-2 text-teal" style="color: #0d9488;"></i>User Presence & Activity
                </h6>
                <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-journal-text me-1"></i> Audit Logs
                </a>
            </div>

            {{-- Presence Summary Header --}}
            <div class="p-3 bg-light border-bottom">
                <div class="d-flex align-items-center justify-content-around text-center">
                    <div>
                        <i class="bi bi-circle-fill text-success fs-6 me-1"></i>
                        <span class="fw-bold text-dark fs-6">{{ $userPresenceStats['online_count'] }}</span>
                        <span class="small text-muted ms-1">Online</span>
                    </div>
                    <div class="vr opacity-25" style="height: 20px;"></div>
                    <div>
                        <i class="bi bi-circle-fill text-warning fs-6 me-1"></i>
                        <span class="fw-bold text-dark fs-6">{{ $userPresenceStats['recently_active_count'] }}</span>
                        <span class="small text-muted ms-1">Active Recently</span>
                    </div>
                    <div class="vr opacity-25" style="height: 20px;"></div>
                    <div>
                        <i class="bi bi-people-fill text-secondary fs-6 me-1"></i>
                        <span class="fw-bold text-dark fs-6">{{ $userPresenceStats['total_users_count'] }}</span>
                        <span class="small text-muted ms-1">Active Users</span>
                    </div>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="user-presence-list-wrapper" style="max-height: 280px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0">
                        <tbody>
                            @forelse($userPresenceList as $userItem)
                                <tr>
                                    <td class="ps-3" style="width: 32px;">
                                        @if($userItem['is_online'])
                                            <i class="bi bi-circle-fill text-success fs-6" title="Online (Active Session)"></i>
                                        @else
                                            <i class="bi bi-circle-fill text-warning fs-6" title="Active Recently"></i>
                                        @endif
                                    </td>
                                    <td class="fw-semibold text-dark small" style="min-width: 130px; max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        {{ $userItem['name'] }}
                                    </td>
                                    <td class="small" style="min-width: 100px;">
                                        <span class="badge" style="background-color: rgba(13, 148, 136, 0.1); color: #0f766e; font-weight: 500; font-size: 0.72rem;">
                                            {{ $userItem['role_name'] }}
                                        </span>
                                    </td>
                                    <td class="pe-3 text-end small" style="min-width: 100px; white-space: nowrap;">
                                        @if($userItem['is_online'])
                                            <span class="fw-semibold text-success">Online</span>
                                        @else
                                            <span class="text-muted">{{ $userItem['last_active_human'] }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted small">
                                        <i class="bi bi-people fs-3 d-block mb-1 opacity-50"></i>
                                        No active users recorded.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════════════
     SECTION 6: PATIENT INFORMATION & DIRECTORY OVERVIEW (READ-ONLY)
     ══════════════════════════════════════════════════════════════════════ --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h6 class="mb-0 fw-bold text-dark d-flex align-items-center">
                <i class="bi bi-person-lines-fill me-2 text-primary"></i>Patient Information & Directory Overview (Read-Only)
            </h6>
            <small class="text-muted">
                Authoritative patient master records are maintained by the external patient subsystem. Displaying authorized DTCS clinical directory.
            </small>
        </div>
        <a href="{{ route('patients.index') }}" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-folder2-open me-1"></i> View Patient Directory
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Patient ID</th>
                        <th>Patient Name</th>
                        <th>Gender / DOB</th>
                        <th>Type / Ward</th>
                        <th>Date Registered</th>
                        <th class="text-end pe-3">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentPatients as $patient)
                        <tr>
                            <td class="ps-3">
                                <span class="badge bg-light text-dark border">
                                    <i class="bi bi-card-text me-1"></i>{{ $patient->patient_no }}
                                </span>
                            </td>
                            <td>
                                <div class="fw-bold text-dark">{{ $patient->full_name }}</div>
                                <div class="small text-muted">{{ $patient->email ?? 'No email recorded' }}</div>
                            </td>
                            <td>
                                <span class="small">{{ $patient->gender }} &bull; {{ $patient->date_of_birth?->format('M d, Y') ?? 'N/A' }}</span>
                            </td>
                            <td>
                                <span class="badge hims-badge-{{ strtolower($patient->patient_type ?? '') === 'inpatient' ? 'blue' : (strtolower($patient->patient_type ?? '') === 'emergency' ? 'red' : 'green') }}">
                                    {{ $patient->patient_type ?? 'Outpatient' }}
                                </span>
                                @if($patient->ward)
                                    <small class="text-muted d-block">{{ $patient->ward }} (Bed {{ $patient->bed_number ?? '-' }})</small>
                                @endif
                            </td>
                            <td>
                                <span class="small text-muted"><i class="bi bi-clock me-1"></i>{{ $patient->created_at?->format('M d, Y') ?? 'N/A' }}</span>
                            </td>
                            <td class="text-end pe-3">
                                <a href="{{ route('patients.show', $patient) }}" class="btn btn-sm btn-outline-secondary" title="View Patient Profile">
                                    <i class="bi bi-person-badge me-1"></i> View Profile
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                <i class="bi bi-folder2-open fs-2 d-block mb-1 opacity-50"></i>
                                No patient records available.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════════════
     SECTION 7: RECENT OPERATIONAL ACTIVITIES
     ══════════════════════════════════════════════════════════════════════ --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h6 class="mb-0 fw-bold text-dark d-flex align-items-center">
                <i class="bi bi-clock-history me-2 text-teal" style="color: #0d9488;"></i>Recent Operational Activities
            </h6>
            <small class="text-muted">Latest clinical and operational events recorded across hospital services</small>
        </div>
        <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-journal-text me-1"></i> Full Audit Log
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Action / Event</th>
                        <th>Module</th>
                        <th>Actor</th>
                        <th>Description</th>
                        <th class="text-end pe-3">Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentOperationalActivities ?? [] as $log)
                        <tr>
                            <td class="ps-3 fw-semibold text-dark small">{{ $log->action }}</td>
                            <td>
                                <span class="badge bg-light text-dark border small">{{ $log->module ?? 'System' }}</span>
                            </td>
                            <td class="small">{{ $log->user->name ?? 'System' }}</td>
                            <td class="small text-muted">{{ Str::limit($log->description, 85) }}</td>
                            <td class="text-end pe-3 small text-muted">{{ $log->logged_at?->diffForHumans() ?? 'Recently' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted small">
                                No operational activity events recorded yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ── Chart Data Payloads ── --}}
<script type="application/json" id="admin-roles-data">@json($usersByRole)</script>
<script type="application/json" id="admin-svc-trend-6m">@json($adminTrend6m)</script>
<script type="application/json" id="admin-svc-trend-12m">@json($adminTrend12m)</script>
<script type="application/json" id="admin-module-volume">@json($adminModuleVolume)</script>
<script type="application/json" id="admin-all-statuses">@json($allStatuses)</script>
<script type="application/json" id="admin-activity-data">@json($adminActivity7d ?? [])</script>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    if (typeof window.HIMSChart === 'undefined') return;

    const rolesData = JSON.parse(document.getElementById('admin-roles-data').textContent || '[]');
    const trend6m   = JSON.parse(document.getElementById('admin-svc-trend-6m').textContent || '[]');
    const trend12m  = JSON.parse(document.getElementById('admin-svc-trend-12m').textContent || '[]');
    const modVol    = JSON.parse(document.getElementById('admin-module-volume').textContent || '[]');
    const statuses  = JSON.parse(document.getElementById('admin-all-statuses').textContent || '{}');
    const actData   = JSON.parse(document.getElementById('admin-activity-data').textContent || '[]');

    // ── 1. Role Distribution Donut Chart ──
    const roleCtx = document.getElementById('adminRoleChart');
    if (roleCtx && rolesData.length > 0) {
        window.HIMSChart.createDoughnutChart(
            roleCtx,
            rolesData.map(r => r.name),
            rolesData.map(r => r.count),
            ['#0d9488', '#0284c7', '#6366f1', '#f59e0b', '#64748b', '#10b981', '#ec4899']
        );
    }

    // ── 2. Service Request Trend (Line with Period Toggle) ──
    let svcTrendChart = null;
    function renderSvcTrend(data) {
        const ctx = document.getElementById('adminSvcTrendChart');
        if (!ctx) return;
        if (svcTrendChart) { svcTrendChart.destroy(); svcTrendChart = null; }
        if (!data || data.length === 0) return;
        svcTrendChart = window.HIMSChart.createLineChart(
            ctx,
            'Total Service Requests',
            data.map(d => d.label),
            data.map(d => d.total),
            'requests'
        );
    }
    renderSvcTrend(trend6m);

    const periodSel = document.getElementById('adminTrendPeriod');
    if (periodSel) {
        periodSel.addEventListener('change', function () {
            renderSvcTrend(this.value === '12' ? trend12m : trend6m);
        });
    }

    // ── 3. Module Volume Bar Chart ──
    const modCtx = document.getElementById('adminModuleBarChart');
    if (modCtx && modVol.length > 0) {
        const isDark = window.HIMSChart.isDarkMode();
        const gridColor = isDark ? '#374151' : '#eef1f5';
        const tickColor = isDark ? '#94A3B8' : '#64748b';
        new Chart(modCtx, {
            type: 'bar',
            data: {
                labels: modVol.map(m => m.module),
                datasets: [
                    {
                        label: 'Pending',
                        data: modVol.map(m => m.pending ?? 0),
                        backgroundColor: 'rgba(245, 158, 11, 0.85)',
                        borderRadius: 4,
                    },
                    {
                        label: 'Completed',
                        data: modVol.map(m => m.completed ?? 0),
                        backgroundColor: 'rgba(13, 148, 136, 0.85)',
                        borderRadius: 4,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 1000, easing: 'easeOutQuart' },
                plugins: {
                    legend: { display: true, position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } },
                    tooltip: { backgroundColor: '#0f172a', padding: 12, cornerRadius: 8 }
                },
                scales: {
                    x: { ticks: { color: tickColor, font: { size: 12 } }, grid: { display: false }, border: { display: false } },
                    y: { beginAtZero: true, ticks: { color: tickColor, precision: 0, font: { size: 12 } }, grid: { color: gridColor }, border: { display: false } }
                }
            }
        });
    }

    // ── 4. Global Request Status Donut ──
    const statusCtx = document.getElementById('adminStatusDonut');
    const statusLabels = Object.keys(statuses);
    const statusValues = Object.values(statuses);
    if (statusCtx && statusValues.some(v => v > 0)) {
        window.HIMSChart.createDoughnutChart(
            statusCtx,
            statusLabels,
            statusValues,
            { 'Pending': '#0284c7', 'In Progress': '#f59e0b', 'Completed': '#0d9488', 'Cancelled': '#ef4444' }
        );
    }

    // ── 5. System Activity Line Chart (7 days) ──
    const actCtx = document.getElementById('adminActivityChart');
    if (actCtx && actData.length > 0) {
        window.HIMSChart.createLineChart(
            actCtx,
            'Activity Events',
            actData.map(d => d.date),
            actData.map(d => d.count),
            'events'
        );
    }
})();
</script>
@endpush
@endsection
