@extends('layouts.app')

@section('title', 'System, Security, and Environment Settings')
@section('page-title', 'Super Admin — Security & System Settings')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('super-admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active" aria-current="page">Security & System Settings</li>
@endsection

@section('content')
<div class="container-fluid px-0">
    {{-- Header Banner --}}
    <div class="card border-0 shadow-sm mb-4" style="border-left: 4px solid #0f172a !important;">
        <div class="card-body">
            <h5 class="fw-bold mb-1 text-dark"><i class="bi bi-sliders text-dark me-2"></i> System & Security Environment Governance</h5>
            <p class="text-muted small mb-0">
                Configure hospital-wide authentication policies, server-side session timeouts, maintenance mode, and backup automation. Settings are validated, cached, and audited in an append-only change log. Ordinary Administrators cannot access or modify these settings.
            </p>
        </div>
    </div>

    <form action="{{ route('super-admin.settings.update') }}" method="POST">
        @csrf
        <div class="row g-4 mb-4">
            {{-- 1. Password Policy --}}
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="m-0 fw-bold text-dark"><i class="bi bi-shield-lock me-2 text-primary"></i> Global Password Policy</h6>
                    </div>
                    <div class="card-body small">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Minimum Password Length (Characters) *</label>
                            <input type="number" name="security_password_min_length" value="{{ $current['security.password_min_length'] ?? 8 }}" min="8" max="64" class="form-control form-control-sm" required>
                            <div class="form-text">Enforced on all password updates, resets, and registrations. Must be between 8 and 64 characters.</div>
                        </div>

                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="security_password_require_mixed_case" value="1" id="sw_mixed" {{ !empty($current['security.password_require_mixed_case']) ? 'checked' : '' }}>
                            <label class="form-check-label fw-semibold" for="sw_mixed">Require Mixed Case (Uppercase and Lowercase letters)</label>
                        </div>

                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="security_password_require_numbers" value="1" id="sw_num" {{ !empty($current['security.password_require_numbers']) ? 'checked' : '' }}>
                            <label class="form-check-label fw-semibold" for="sw_num">Require Numbers (At least one numeric digit 0-9)</label>
                        </div>

                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="security_password_require_symbols" value="1" id="sw_sym" {{ !empty($current['security.password_require_symbols']) ? 'checked' : '' }}>
                            <label class="form-check-label fw-semibold" for="sw_sym">Require Special Symbols (!@#$%^&* etc.)</label>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 2. Session Inactivity & OTP Policy --}}
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="m-0 fw-bold text-dark"><i class="bi bi-hourglass-split me-2 text-info"></i> Session & Inactivity Limits</h6>
                    </div>
                    <div class="card-body small">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Server Session Inactivity Timeout (Minutes)</label>
                            <input type="number" name="security_session_inactivity_minutes" value="{{ $current['security.session_inactivity_minutes'] ?? '' }}" min="5" max="1440" class="form-control form-control-sm" placeholder="e.g. 30 (Leave blank for standard session lifetime)">
                            <div class="form-text">Server-enforced session expiration. Separate from the client-side 3-minute screen lock.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Email OTP Expiry Override (Minutes)</label>
                            <input type="number" name="security_otp_expires_minutes" value="{{ $current['security.otp_expires_minutes'] ?? '' }}" min="1" max="15" class="form-control form-control-sm" placeholder="e.g. 5 (Default: from config)">
                            <div class="form-text">Lifespan of two-factor verification codes sent via email.</div>
                        </div>

                        <div class="p-3 bg-light rounded text-muted">
                            <i class="bi bi-info-circle me-1 text-primary"></i> <strong>Note:</strong> Single active session replacement and client-side screen-lock remain continuously active.
                        </div>
                    </div>
                </div>
            </div>

            {{-- 3. Maintenance Mode --}}
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="m-0 fw-bold text-dark"><i class="bi bi-exclamation-octagon me-2 text-warning"></i> Global Maintenance Mode</h6>
                    </div>
                    <div class="card-body small">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="maintenance_enabled" value="1" id="sw_maint" {{ !empty($current['maintenance.enabled']) ? 'checked' : '' }}>
                            <label class="form-check-label fw-bold text-danger" for="sw_maint">Enable Maintenance Mode</label>
                        </div>
                        <p class="text-muted small">When active, only Super Administrators can access the system. Other staff and patients will receive a 503 maintenance notice.</p>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Maintenance Notice Message *</label>
                            <textarea name="maintenance_message" class="form-control form-control-sm" rows="2" required>{{ $current['maintenance.message'] ?? 'The hospital information system is undergoing scheduled maintenance. Please check back shortly.' }}</textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Expected Completion Time</label>
                            <input type="text" name="maintenance_expected_back" value="{{ $current['maintenance.expected_back'] ?? '' }}" class="form-control form-control-sm" placeholder="e.g. Today at 04:00 AM UTC">
                        </div>
                    </div>
                </div>
            </div>

            {{-- 4. Database Backup Schedule --}}
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 fw-bold text-dark"><i class="bi bi-database me-2 text-success"></i> Backup Schedule & Retention</h6>
                        <a href="{{ route('super-admin.backup.index') }}" class="btn btn-sm btn-outline-secondary py-0 px-2">History</a>
                    </div>
                    <div class="card-body small">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="backup_enabled" value="1" id="sw_bk" {{ !empty($current['backup.enabled']) ? 'checked' : '' }}>
                            <label class="form-check-label fw-semibold" for="sw_bk">Enable Automatic Daily Backup</label>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-semibold">Scheduled Time (HH:MM) *</label>
                                <input type="text" name="backup_time" value="{{ $current['backup.time'] ?? '02:00' }}" pattern="^(?:[01]\d|2[0-3]):[0-5]\d$" class="form-control form-control-sm font-monospace" placeholder="02:00" required>
                                <div class="form-text">24-hour server time format.</div>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold">Retention Policy (Days) *</label>
                                <input type="number" name="backup_retention_days" value="{{ $current['backup.retention_days'] ?? 30 }}" min="1" max="365" class="form-control form-control-sm" required>
                                <div class="form-text">The 3 latest backups are always kept.</div>
                            </div>
                        </div>

                        <div class="p-3 bg-light rounded text-muted">
                            <i class="bi bi-check-circle me-1 text-success"></i> Backups execute via <code>mysqldump</code> and are stored on encrypted private local disk storage.
                        </div>
                    </div>
                </div>
            </div>

            {{-- Mandatory Audit Reason & Submit --}}
            <div class="col-12">
                <div class="card border-0 shadow-sm" style="background: #f8fafc;">
                    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div class="flex-grow-1" style="max-width: 600px;">
                            <label class="form-label fw-semibold text-danger mb-1">Mandatory Audit Reason for Configuration Change *</label>
                            <input type="text" name="reason" class="form-control form-control-sm" placeholder="Specify governance or security rationale for modifying system settings" required minlength="5">
                        </div>
                        <div>
                            <button type="submit" class="btn btn-primary px-4 rounded-pill">
                                <i class="bi bi-check2-circle me-1"></i> Save & Enforce Settings
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    {{-- Settings Change History Audit Table --}}
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3">
            <h6 class="m-0 fw-bold text-dark"><i class="bi bi-clock-history me-2 text-primary"></i> System Settings Change Audit Log</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-uppercase">
                        <tr>
                            <th class="ps-3">Timestamp (UTC)</th>
                            <th>Changed By</th>
                            <th>Setting Key</th>
                            <th>Old Value</th>
                            <th>New Value</th>
                            <th>IP Address</th>
                            <th class="pe-3">Reason</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        @forelse($histories as $h)
                            <tr>
                                <td class="ps-3 font-monospace text-muted">{{ $h->created_at ? $h->created_at->format('M d, Y H:i:s') : '—' }}</td>
                                <td class="fw-semibold">{{ $h->changedBy?->name ?? 'System' }}</td>
                                <td><span class="badge bg-secondary-subtle text-secondary font-monospace">{{ $h->setting_key }}</span></td>
                                <td class="font-monospace text-muted">{{ json_encode($h->old_value) }}</td>
                                <td class="font-monospace text-dark fw-bold">{{ json_encode($h->new_value) }}</td>
                                <td class="font-monospace text-muted">{{ $h->ip_address ?? '—' }}</td>
                                <td class="pe-3 text-muted">{{ $h->reason ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    No system settings modifications recorded yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($histories->hasPages())
                <div class="p-3 border-top">
                    {{ $histories->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
