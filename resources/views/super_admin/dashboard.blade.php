@extends('layouts.app')

@section('title', 'Master System Authority Dashboard')
@section('page-title', 'Super Admin — Master System Authority')

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Super Admin Dashboard</li>
@endsection

@push('styles')
<style>
    .sa-shortcut-tile {
        transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
        border: 1px solid var(--line, #e2e8f0);
        background: var(--card, #ffffff);
        text-decoration: none !important;
    }
    .sa-shortcut-tile:hover {
        border-color: #0d9488 !important;
        box-shadow: 0 4px 12px rgba(13, 148, 136, 0.12) !important;
        transform: translateY(-2px);
    }
    html[data-theme="dark"] .bg-white,
    html[data-theme="dark"] .card-header.bg-white {
        background-color: var(--card, #111827) !important;
        border-color: var(--line, #374151) !important;
    }
    html[data-theme="dark"] .text-dark {
        color: #f9fafb !important;
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-0">
    {{-- Top Alert: Governance Boundary Notice --}}
    <div class="alert alert-info border-0 shadow-sm d-flex align-items-center mb-4" style="background: rgba(15, 23, 42, 0.05); border-left: 4px solid #0f766e !important;">
        <i class="bi bi-shield-check fs-4 me-3" style="color: #0f766e;"></i>
        <div>
            <div class="fw-bold text-dark">Master System Governance Scope</div>
            <div class="small text-muted">
                Super Administrator possesses master authority over system security, role structures, and master catalogs. Clinical workflows and patient master records remain strictly governed by clinical personnel.
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════
         1. PRIMARY SYSTEM ADMINISTRATION SCORECARDS (6 Cards)
         ══════════════════════════════════════════════════════════════════════ --}}
    <div class="balanced-grid balanced-grid-6 mb-4">
        {{-- Card 1: Total DTCS Accounts --}}
        <a href="{{ route('super-admin.users.index') }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-semibold text-uppercase">Total DTCS Accounts</span>
                        <div class="rounded-circle p-2" style="background: rgba(15, 118, 110, 0.1); color: #0f766e;">
                            <i class="bi bi-people-fill fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold text-dark mb-0">{{ number_format($accountStats['total_accounts']) }}</h3>
                    <div class="small mt-2" style="color: #0f766e;">
                        All Registered Users <i class="bi bi-arrow-right ms-1"></i>
                    </div>
                </div>
            </div>
        </a>

        {{-- Card 2: Active Accounts --}}
        <a href="{{ route('super-admin.users.index', ['status' => 'active']) }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-semibold text-uppercase">Active Accounts</span>
                        <div class="bg-success bg-opacity-10 text-success rounded-circle p-2">
                            <i class="bi bi-person-check-fill fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold text-dark mb-0">{{ number_format($accountStats['active']) }}</h3>
                    <div class="small text-success mt-2">
                        Enabled &amp; Unlocked <i class="bi bi-arrow-right ms-1"></i>
                    </div>
                </div>
            </div>
        </a>

        {{-- Card 3: Inactive Accounts --}}
        <a href="{{ route('super-admin.users.index', ['status' => 'inactive']) }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-semibold text-uppercase">Inactive Accounts</span>
                        <div class="bg-secondary bg-opacity-10 text-secondary rounded-circle p-2">
                            <i class="bi bi-person-dash-fill fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold text-dark mb-0">{{ number_format($accountStats['inactive']) }}</h3>
                    <div class="small text-secondary mt-2">
                        Deactivated Users <i class="bi bi-arrow-right ms-1"></i>
                    </div>
                </div>
            </div>
        </a>

        {{-- Card 4: Locked Accounts --}}
        <a href="{{ route('super-admin.users.index', ['status' => 'locked']) }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-semibold text-uppercase">Locked Accounts</span>
                        <div class="bg-danger bg-opacity-10 text-danger rounded-circle p-2">
                            <i class="bi bi-lock-fill fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-0 {{ $accountStats['locked'] > 0 ? 'text-danger' : 'text-dark' }}">{{ number_format($accountStats['locked']) }}</h3>
                    <div class="small text-danger mt-2">
                        Security Lockouts <i class="bi bi-arrow-right ms-1"></i>
                    </div>
                </div>
            </div>
        </a>

        {{-- Card 5: Total DTCS Modules --}}
        <a href="{{ route('super-admin.departments.index') }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-semibold text-uppercase">Total DTCS Modules</span>
                        <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-2">
                            <i class="bi bi-grid-3x3-gap-fill fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold text-dark mb-0">{{ number_format($accountStats['total_modules']) }}</h3>
                    <div class="small text-primary mt-2">
                        LIS, RIS, PMS, SORS, DNMS <i class="bi bi-arrow-right ms-1"></i>
                    </div>
                </div>
            </div>
        </a>

        {{-- Card 6: Security Alerts --}}
        <a href="{{ route('super-admin.audit-vault.index') }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-semibold text-uppercase">Security Alerts</span>
                        <div class="bg-warning bg-opacity-10 text-warning rounded-circle p-2">
                            <i class="bi bi-shield-exclamation fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-0 {{ $accountStats['security_alerts'] > 0 ? 'text-danger' : 'text-dark' }}">{{ number_format($accountStats['security_alerts']) }}</h3>
                    <div class="small text-warning-emphasis mt-2">
                        24h Security Watch <i class="bi bi-arrow-right ms-1"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════
         2. TOP-LEVEL ACCOUNT HIERARCHY (5 Categories)
         ══════════════════════════════════════════════════════════════════════ --}}
    <h6 class="text-uppercase fw-bold text-secondary mb-3 small" style="letter-spacing: 0.05em;">User Account Hierarchy</h6>
    <div class="row g-3 mb-4">
        {{-- 1. Super Admin --}}
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm h-100" style="border-top: 3px solid #0f172a !important;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-semibold">Super Admin</span>
                        <i class="bi bi-shield-shaded text-dark fs-5"></i>
                    </div>
                    <h3 class="fw-bold mb-1">{{ $accountStats['super_admin'] }}</h3>
                    <span class="badge bg-dark-subtle text-dark border">Master Authority</span>
                </div>
            </div>
        </div>

        {{-- 2. Admin --}}
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm h-100" style="border-top: 3px solid #0284c7 !important;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-semibold">Administrators</span>
                        <i class="bi bi-person-gear text-primary fs-5"></i>
                    </div>
                    <h3 class="fw-bold mb-1">{{ $accountStats['admin'] }}</h3>
                    <a href="{{ route('super-admin.admins.index') }}" class="small text-primary text-decoration-none">Manage Admins &rarr;</a>
                </div>
            </div>
        </div>

        {{-- 3. Doctor --}}
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm h-100" style="border-top: 3px solid #0d9488 !important;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-semibold">Doctors</span>
                        <i class="bi bi-person-badge fs-5" style="color: #0d9488;"></i>
                    </div>
                    <h3 class="fw-bold mb-1">{{ $accountStats['doctor'] }}</h3>
                    <span class="badge bg-info-subtle text-info border">Clinicians</span>
                </div>
            </div>
        </div>

        {{-- 4. Staff --}}
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm h-100" style="border-top: 3px solid #059669 !important;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-semibold">Clinical Staff</span>
                        <i class="bi bi-people text-success fs-5"></i>
                    </div>
                    <h3 class="fw-bold mb-1">{{ $accountStats['staff'] }}</h3>
                    <span class="badge bg-success-subtle text-success border">LIS/RIS/PMS/SORS/DNMS</span>
                </div>
            </div>
        </div>

        {{-- 5. Patients --}}
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm h-100" style="border-top: 3px solid #64748b !important;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-semibold">Patients</span>
                        <i class="bi bi-hospital text-secondary fs-5"></i>
                    </div>
                    <h3 class="fw-bold mb-1">{{ $accountStats['patient'] }}</h3>
                    <span class="badge bg-secondary-subtle text-secondary border">Upstream Directory</span>
                </div>
            </div>
        </div>

        {{-- 6. Security Exceptions --}}
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm h-100" style="border-top: 3px solid #dc2626 !important;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-semibold">Locked / Inactive</span>
                        <i class="bi bi-lock-fill text-danger fs-5"></i>
                    </div>
                    <h3 class="fw-bold mb-1 text-danger">{{ $accountStats['locked'] }} <span class="fs-6 text-muted font-monospace">/ {{ $accountStats['inactive'] }}</span></h3>
                    <span class="badge bg-danger-subtle text-danger border">Security Watch</span>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════
         3. GOVERNANCE ANALYTICS & CHARTS (Row 1 & Row 2)
         ══════════════════════════════════════════════════════════════════════ --}}
    <div class="row g-4 mb-4">
        {{-- Chart 1: User Account Growth --}}
        <div class="col-lg-8">
            <div class="chart-card h-100">
                <div class="chart-header">
                    <div>
                        <h3 class="chart-title"><i class="bi bi-graph-up-arrow me-2" style="color: #0f766e;"></i>User Account Growth</h3>
                        <p class="chart-subtitle">Monthly provisioned DTCS user accounts over time</p>
                    </div>
                    <select id="saGrowthPeriod" class="form-select form-select-sm" style="width:auto;">
                        <option value="6">Last 6 Months</option>
                        <option value="12">Last 12 Months</option>
                    </select>
                </div>
                <div class="chart-container" style="height: 260px;">
                    <canvas id="saAccountGrowthChart"></canvas>
                </div>
            </div>
        </div>

        {{-- Chart 2: Account Status Distribution --}}
        <div class="col-lg-4">
            <div class="chart-card h-100">
                <div class="chart-header">
                    <div>
                        <h3 class="chart-title"><i class="bi bi-pie-chart-fill me-2" style="color: #0f766e;"></i>Account Status Distribution</h3>
                        <p class="chart-subtitle">Active, Inactive, and Locked accounts</p>
                    </div>
                </div>
                <div class="chart-container" style="height: 260px;">
                    <canvas id="saAccountStatusChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        {{-- Chart 3: Account Distribution by Role --}}
        <div class="col-lg-4">
            <div class="chart-card h-100">
                <div class="chart-header">
                    <div>
                        <h3 class="chart-title"><i class="bi bi-people-fill me-2" style="color: #0f766e;"></i>Account Distribution by Role</h3>
                        <p class="chart-subtitle">Assigned accounts across all DTCS roles</p>
                    </div>
                </div>
                <div class="chart-container" style="height: 260px;">
                    <canvas id="saRoleDistChart"></canvas>
                </div>
            </div>
        </div>

        {{-- Chart 4: Login & Authentication vs Security Event Trends (7d) --}}
        <div class="col-lg-4">
            <div class="chart-card h-100">
                <div class="chart-header">
                    <div>
                        <h3 class="chart-title"><i class="bi bi-shield-lock-fill me-2" style="color: #0f766e;"></i>Authentication &amp; Security Trends</h3>
                        <p class="chart-subtitle">7-day login activity and security/lockout events</p>
                    </div>
                </div>
                <div class="chart-container" style="height: 260px;">
                    <canvas id="saAuthSecurityChart"></canvas>
                </div>
            </div>
        </div>

        {{-- Chart 5: DTCS Module Activity Overview --}}
        <div class="col-lg-4">
            <div class="chart-card h-100">
                <div class="chart-header">
                    <div>
                        <h3 class="chart-title"><i class="bi bi-bar-chart-fill me-2" style="color: #0f766e;"></i>DTCS Module Activity Overview</h3>
                        <p class="chart-subtitle">Aggregate transaction volume per subsystem (non-PII)</p>
                    </div>
                </div>
                <div class="chart-container" style="height: 260px;">
                    <canvas id="saModuleActivityChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════
         4. MODULE HEALTH & ACCOUNT MANAGEMENT SHORTCUTS
         ══════════════════════════════════════════════════════════════════════ --}}
    <div class="row g-4 mb-4">
        {{-- Module Health and Operational Status --}}
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-dark"><i class="bi bi-heart-pulse me-2" style="color: #0f766e;"></i>Module Health &amp; Operational Status</h6>
                    <span class="badge bg-success-subtle text-success border">{{ $catalogCount }} Catalog Items</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="table-light text-uppercase">
                                <tr>
                                    <th class="ps-3">Module</th>
                                    <th>Active Staff</th>
                                    <th>Total Records</th>
                                    <th>Pending Queue</th>
                                    <th class="pe-3 text-end">Health</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($moduleHealth as $mod)
                                    <tr>
                                        <td class="ps-3">
                                            <span class="badge bg-light text-dark border font-monospace me-1">{{ $mod['code'] }}</span>
                                            <strong class="text-dark">{{ $mod['name'] }}</strong>
                                        </td>
                                        <td>{{ number_format($mod['staff_count']) }} active</td>
                                        <td>{{ number_format($mod['total_ops']) }}</td>
                                        <td>{{ number_format($mod['pending_ops']) }}</td>
                                        <td class="pe-3 text-end">
                                            <span class="badge bg-success-subtle text-success border">
                                                <i class="bi bi-check-circle-fill me-1"></i>{{ $mod['status'] }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Account Management Shortcuts --}}
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="m-0 fw-bold text-dark"><i class="bi bi-lightning-charge-fill me-2" style="color: #0f766e;"></i>Account Management &amp; Governance Shortcuts</h6>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-sm-6">
                            <a href="{{ route('super-admin.users.index') }}" class="card p-3 rounded-3 sa-shortcut-tile h-100">
                                <div class="fw-bold text-dark small"><i class="bi bi-people-fill me-1 text-primary"></i>All User Accounts</div>
                                <span class="text-muted" style="font-size: 11px;">Provision, unlock, or archive users</span>
                            </a>
                        </div>
                        <div class="col-sm-6">
                            <a href="{{ route('super-admin.admins.index') }}" class="card p-3 rounded-3 sa-shortcut-tile h-100">
                                <div class="fw-bold text-dark small"><i class="bi bi-person-gear me-1 text-info"></i>Privileged Admins</div>
                                <span class="text-muted" style="font-size: 11px;">Manage system administrator accounts</span>
                            </a>
                        </div>
                        <div class="col-sm-6">
                            <a href="{{ route('admin.roles.index') }}" class="card p-3 rounded-3 sa-shortcut-tile h-100">
                                <div class="fw-bold text-dark small"><i class="bi bi-key-fill me-1 text-warning"></i>Roles &amp; Permissions</div>
                                <span class="text-muted" style="font-size: 11px;">Configure RBAC permission matrix</span>
                            </a>
                        </div>
                        <div class="col-sm-6">
                            <a href="{{ route('super-admin.doctor-provisioning.index') }}" class="card p-3 rounded-3 sa-shortcut-tile h-100">
                                <div class="fw-bold text-dark small"><i class="bi bi-patch-check-fill me-1 text-success"></i>Doctor Provisioning</div>
                                <span class="text-muted" style="font-size: 11px;">{{ $pendingDoctorRequests }} pending request(s)</span>
                            </a>
                        </div>
                        <div class="col-sm-6">
                            <a href="{{ route('super-admin.settings.index') }}" class="card p-3 rounded-3 sa-shortcut-tile h-100">
                                <div class="fw-bold text-dark small"><i class="bi bi-sliders me-1 text-secondary"></i>Security &amp; Settings</div>
                                <span class="text-muted" style="font-size: 11px;">Lockout thresholds &amp; policies</span>
                            </a>
                        </div>
                        <div class="col-sm-6">
                            <a href="{{ route('super-admin.audit-vault.index') }}" class="card p-3 rounded-3 sa-shortcut-tile h-100">
                                <div class="fw-bold text-dark small"><i class="bi bi-safe2-fill me-1 text-danger"></i>Protected Audit Vault</div>
                                <span class="text-muted" style="font-size: 11px;">Immutable security audit trail</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════
         5. RECENT SECURITY EVENTS & RECENT ADMINISTRATIVE ACTIVITIES
         ══════════════════════════════════════════════════════════════════════ --}}
    <div class="row g-4 mb-4">
        {{-- Recent Security Events --}}
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-danger"><i class="bi bi-shield-exclamation me-2"></i>Recent Security Events</h6>
                    <a href="{{ route('super-admin.audit-vault.index') }}" class="btn btn-sm btn-outline-danger rounded-pill px-3">View All</a>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush small">
                        @forelse($recentSecurityEvents as $secEvent)
                            <li class="list-group-item py-2.5 px-3">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div>
                                        <span class="badge {{ $secEvent->severity_badge_class }} me-1">{{ $secEvent->severity }}</span>
                                        <strong class="text-dark">{{ $secEvent->action }}</strong>
                                        <div class="text-muted mt-1">{{ Str::limit($secEvent->description, 90) }}</div>
                                    </div>
                                    <span class="text-muted font-monospace text-nowrap" style="font-size: 11px;">
                                        {{ $secEvent->logged_at?->diffForHumans() ?? 'Recently' }}
                                    </span>
                                </div>
                            </li>
                        @empty
                            <li class="list-group-item text-center py-4 text-muted">
                                No security warnings or lockouts recorded recently.
                            </li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>

        {{-- Recent Administrative Activities --}}
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-dark"><i class="bi bi-journal-check me-2" style="color: #0f766e;"></i>Recent Administrative Activities</h6>
                    <a href="{{ route('super-admin.users.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3">Users</a>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush small">
                        @forelse($recentAdminActivities as $admAct)
                            <li class="list-group-item py-2.5 px-3">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div>
                                        <span class="badge bg-secondary-subtle text-secondary border me-1">{{ $admAct->module }}</span>
                                        <strong class="text-dark">{{ $admAct->action }}</strong>
                                        <div class="text-muted mt-1">{{ $admAct->user?->name ?? 'System' }} — {{ Str::limit($admAct->description, 80) }}</div>
                                    </div>
                                    <span class="text-muted font-monospace text-nowrap" style="font-size: 11px;">
                                        {{ $admAct->logged_at?->diffForHumans() ?? 'Recently' }}
                                    </span>
                                </div>
                            </li>
                        @empty
                            <li class="list-group-item text-center py-4 text-muted">
                                No recent administrative events recorded.
                            </li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════
         6. DATABASE BACKUP STATUS & CLINICIAN AUTHORIZATIONS
         ══════════════════════════════════════════════════════════════════════ --}}
    <div class="row g-4 mb-4">
        {{-- Database Backup & Integrity --}}
        <div class="col-lg-6 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-dark"><i class="bi bi-database me-2 text-primary"></i> Database Backup Status</h6>
                    <a href="{{ route('super-admin.backup.index') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">Backup Hub</a>
                </div>
                <div class="card-body">
                    @if($latestBackup)
                        <div class="d-flex align-items-center mb-3">
                            <span class="badge {{ $latestBackup->status === 'success' ? 'bg-success text-white' : 'bg-danger text-white' }} px-3 py-2 fs-6 rounded-pill me-3">
                                <i class="bi {{ $latestBackup->status === 'success' ? 'bi-check-circle-fill' : 'bi-x-circle-fill' }} me-1"></i>
                                {{ strtoupper($latestBackup->status) }}
                            </span>
                            <div>
                                <div class="fw-bold">{{ basename($latestBackup->file_path ?? 'No file') }}</div>
                                <div class="small text-muted">{{ $latestBackup->finished_at ? $latestBackup->finished_at->diffForHumans() : 'In progress' }}</div>
                            </div>
                        </div>
                        <ul class="list-unstyled small text-muted mb-0 border-top pt-3">
                            <li class="mb-1 d-flex justify-content-between">
                                <span>File Size:</span>
                                <strong class="text-dark">{{ $latestBackup->formatted_size }}</strong>
                            </li>
                            <li class="mb-1 d-flex justify-content-between">
                                <span>Trigger:</span>
                                <strong class="text-dark text-capitalize">{{ $latestBackup->trigger_type }}</strong>
                            </li>
                            <li class="d-flex justify-content-between">
                                <span>Database Engine:</span>
                                <strong class="text-dark font-monospace">{{ $dbVersion }}</strong>
                            </li>
                        </ul>
                    @else
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-database-exclamation fs-1 text-secondary mb-2 d-block"></i>
                            <p class="mb-2">No database backup runs recorded yet.</p>
                            <form action="{{ route('super-admin.backup.trigger') }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-primary">Run First Backup</button>
                            </form>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Pending Doctor Authorizations --}}
        <div class="col-lg-6 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-dark"><i class="bi bi-patch-check me-2" style="color:#0d9488;"></i> Clinician Authorizations</h6>
                    <a href="{{ route('super-admin.doctor-provisioning.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3">Review</a>
                </div>
                <div class="card-body d-flex flex-column justify-content-center text-center">
                    @if($pendingDoctorRequests > 0)
                        <div class="badge bg-warning-subtle text-warning border border-warning fs-5 py-2 px-3 mb-2 rounded-pill d-inline-block">
                            <i class="bi bi-exclamation-circle-fill me-1"></i> {{ $pendingDoctorRequests }} Pending Request(s)
                        </div>
                        <p class="small text-muted mb-3">Hospital Administrators have submitted Doctor account provisioning requests requiring your authorization.</p>
                        <a href="{{ route('super-admin.doctor-provisioning.index') }}" class="btn btn-sm btn-primary rounded-pill">Process Authorizations</a>
                    @else
                        <div class="text-success mb-2">
                            <i class="bi bi-check-circle-fill fs-1"></i>
                        </div>
                        <div class="fw-semibold text-dark">All Clinician Requests Cleared</div>
                        <span class="small text-muted">No pending doctor provisioning requests from administrators.</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════
         7. PRIVILEGED GOVERNANCE AUDIT TRAIL (Recent Audit Events)
         ══════════════════════════════════════════════════════════════════════ --}}
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 fw-bold text-dark"><i class="bi bi-safe2 text-danger me-2"></i> Privileged Governance Audit Trail (Recent Audit Events)</h6>
            <a href="{{ route('super-admin.audit-vault.index') }}" class="btn btn-sm btn-outline-danger rounded-pill px-3">Open Protected Vault</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-uppercase">
                        <tr>
                            <th class="ps-3">Timestamp</th>
                            <th>User</th>
                            <th>Module</th>
                            <th>Action</th>
                            <th>Severity</th>
                            <th>Result</th>
                            <th class="pe-3">Description</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        @forelse($recentAuditLogs as $log)
                            <tr>
                                <td class="ps-3 font-monospace text-muted">{{ $log->logged_at ? $log->logged_at->format('M d, Y H:i:s') : '—' }}</td>
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
                                <td class="pe-3 text-muted text-truncate" style="max-width: 320px;">
                                    {{ $log->description }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    No privileged audit events recorded yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- ── Chart Payloads ── --}}
<script type="application/json" id="sa-growth-6m">@json($accountGrowth6m)</script>
<script type="application/json" id="sa-growth-12m">@json($accountGrowth12m)</script>
<script type="application/json" id="sa-role-dist">@json($roleDistribution)</script>
<script type="application/json" id="sa-status-dist">@json($statusDistribution)</script>
<script type="application/json" id="sa-auth-sec-7d">@json($authAndSecurity7d)</script>
<script type="application/json" id="sa-module-health">@json($moduleHealth)</script>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    if (typeof Chart === 'undefined') return;

    const growth6m  = JSON.parse(document.getElementById('sa-growth-6m')?.textContent || '[]');
    const growth12m = JSON.parse(document.getElementById('sa-growth-12m')?.textContent || '[]');
    const roleDist  = JSON.parse(document.getElementById('sa-role-dist')?.textContent || '[]');
    const statusObj = JSON.parse(document.getElementById('sa-status-dist')?.textContent || '{}');
    const authSec7d = JSON.parse(document.getElementById('sa-auth-sec-7d')?.textContent || '[]');
    const modHealth = JSON.parse(document.getElementById('sa-module-health')?.textContent || '[]');

    // 1. User Account Growth Line Chart
    let growthChart = null;
    function renderGrowth(data) {
        const ctx = document.getElementById('saAccountGrowthChart');
        if (!ctx || !data.length) return;
        if (growthChart) { growthChart.destroy(); growthChart = null; }
        if (typeof window.HIMSChart !== 'undefined') {
            growthChart = window.HIMSChart.createLineChart(
                ctx,
                'New Accounts',
                data.map(d => d.label),
                data.map(d => d.total),
                'accounts'
            );
        } else {
            growthChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.map(d => d.label),
                    datasets: [{
                        label: 'New Accounts',
                        data: data.map(d => d.total),
                        borderColor: '#0f766e',
                        backgroundColor: 'rgba(15, 118, 110, 0.12)',
                        fill: true,
                        tension: 0.3
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false }
            });
        }
    }
    renderGrowth(growth6m);
    const growthSel = document.getElementById('saGrowthPeriod');
    if (growthSel) {
        growthSel.addEventListener('change', function () {
            renderGrowth(this.value === '12' ? growth12m : growth6m);
        });
    }

    // 2. Account Status Distribution Doughnut
    const statusCtx = document.getElementById('saAccountStatusChart');
    if (statusCtx) {
        const sLabels = Object.keys(statusObj);
        const sValues = Object.values(statusObj);
        if (typeof window.HIMSChart !== 'undefined' && sValues.some(v => v > 0)) {
            window.HIMSChart.createDoughnutChart(
                statusCtx,
                sLabels,
                sValues,
                { 'Active': '#0d9488', 'Inactive': '#64748b', 'Locked': '#dc2626' }
            );
        }
    }

    // 3. Account Distribution by Role Doughnut
    const roleCtx = document.getElementById('saRoleDistChart');
    if (roleCtx && roleDist.length > 0 && typeof window.HIMSChart !== 'undefined') {
        window.HIMSChart.createDoughnutChart(
            roleCtx,
            roleDist.map(r => r.name),
            roleDist.map(r => r.count),
            ['#0f766e', '#0284c7', '#059669', '#6366f1', '#f59e0b', '#64748b', '#dc2626', '#8b5cf6', '#0ea5e9', '#14b8a6']
        );
    }

    // 4. Login & Authentication vs Security Event Trends (7d)
    const authCtx = document.getElementById('saAuthSecurityChart');
    if (authCtx && authSec7d.length > 0) {
        new Chart(authCtx, {
            type: 'bar',
            data: {
                labels: authSec7d.map(d => d.label),
                datasets: [
                    {
                        label: 'Auth Events',
                        data: authSec7d.map(d => d.auth),
                        backgroundColor: 'rgba(13, 148, 136, 0.75)',
                        borderRadius: 4
                    },
                    {
                        label: 'Security / Lockout Events',
                        data: authSec7d.map(d => d.security),
                        backgroundColor: 'rgba(220, 38, 38, 0.75)',
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
            }
        });
    }

    // 5. DTCS Module Activity Overview Bar Chart
    const modCtx = document.getElementById('saModuleActivityChart');
    if (modCtx && modHealth.length > 0) {
        new Chart(modCtx, {
            type: 'bar',
            data: {
                labels: modHealth.map(m => m.code),
                datasets: [
                    {
                        label: 'Total Records',
                        data: modHealth.map(m => m.total_ops),
                        backgroundColor: 'rgba(15, 118, 110, 0.78)',
                        borderRadius: 4
                    },
                    {
                        label: 'Pending Queue',
                        data: modHealth.map(m => m.pending_ops),
                        backgroundColor: 'rgba(245, 158, 11, 0.78)',
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
            }
        });
    }
})();
</script>
@endpush
@endsection
