@extends('layouts.app')

@section('title', 'Hospital Settings & Operational Rules')
@section('page-title', 'Hospital Settings & Operational Rules')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active" aria-current="page">Hospital Settings</li>
@endsection

@section('content')
<div class="container-fluid px-0">
    {{-- Alerts --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <i class="bi bi-exclamation-circle-fill me-2"></i>
            <strong>Validation Error:</strong>
            <ul class="mb-0 mt-1 small">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Header Banner --}}
    <div class="card border-0 shadow-sm mb-4" style="border-left: 4px solid #0284c7 !important;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="fw-bold mb-1 text-dark">
                        <i class="bi bi-hospital text-primary me-2"></i> Hospital Operational & Branding Governance
                    </h5>
                    <p class="text-muted small mb-0">
                        Manage facility identity, official branding for clinical printouts, operating schedules, department queue prefixes, and immutable effective-dated financial rules.
                    </p>
                </div>
                <div>
                    <span class="badge bg-light text-dark border px-3 py-2">
                        <i class="bi bi-shield-check text-success me-1"></i> Scope: Administrator
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Navigation Tabs --}}
    <ul class="nav nav-tabs nav-fill mb-4 bg-white rounded shadow-sm border-0 p-2" id="settingsTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active fw-semibold text-dark py-2" id="branding-tab" data-bs-toggle="tab" data-bs-target="#tab-branding" type="button" role="tab" aria-selected="true">
                <i class="bi bi-building me-1 text-primary"></i> Facility Identity & Branding
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-semibold text-dark py-2" id="hours-tab" data-bs-toggle="tab" data-bs-target="#tab-hours" type="button" role="tab" aria-selected="false">
                <i class="bi bi-clock-history me-1 text-info"></i> Operating Hours
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-semibold text-dark py-2" id="queue-tab" data-bs-toggle="tab" data-bs-target="#tab-queue" type="button" role="tab" aria-selected="false">
                <i class="bi bi-card-checklist me-1 text-warning"></i> Queue & TAT Thresholds
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-semibold text-dark py-2" id="financial-tab" data-bs-toggle="tab" data-bs-target="#tab-financial" type="button" role="tab" aria-selected="false">
                <i class="bi bi-currency-dollar me-1 text-success"></i> Financial & Tax Rules
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-semibold text-dark py-2" id="history-tab" data-bs-toggle="tab" data-bs-target="#tab-history" type="button" role="tab" aria-selected="false">
                <i class="bi bi-journal-text me-1 text-secondary"></i> Change History
            </button>
        </li>
    </ul>

    <div class="tab-content" id="settingsTabContent">
        {{-- TAB 1: Facility Identity & Branding --}}
        <div class="tab-pane fade show active" id="tab-branding" role="tabpanel" aria-labelledby="branding-tab">
            <form action="{{ route('admin.hospital-settings.branding') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="row g-4">
                    <div class="col-lg-8">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white border-bottom py-3">
                                <h6 class="m-0 fw-bold text-dark"><i class="bi bi-building-gear me-2 text-primary"></i> Hospital Facility Information</h6>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-8">
                                        <label class="form-label fw-semibold small">Hospital Name *</label>
                                        <input type="text" name="hospital_name" value="{{ old('hospital_name', $current['hospital.name'] ?? 'DTCS Hospital Management System') }}" class="form-control form-control-sm" required maxlength="150">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold small">Registration / Accreditation No.</label>
                                        <input type="text" name="hospital_registration_no" value="{{ old('hospital_registration_no', $current['hospital.registration_no'] ?? '') }}" class="form-control form-control-sm" placeholder="e.g. DOH-LIC-2026-901">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-semibold small">Official Tagline / Subtitle</label>
                                        <input type="text" name="hospital_tagline" value="{{ old('hospital_tagline', $current['hospital.tagline'] ?? '') }}" class="form-control form-control-sm" placeholder="e.g. Clinical Decision Support & Comprehensive Patient Care">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-semibold small">Complete Physical Address</label>
                                        <textarea name="hospital_address" class="form-control form-control-sm" rows="2" placeholder="e.g. 123 Healthcare Avenue, Medical City, Philippines">{{ old('hospital_address', $current['hospital.address'] ?? '') }}</textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold small">Primary Contact Number</label>
                                        <input type="text" name="hospital_phone" value="{{ old('hospital_phone', $current['hospital.phone'] ?? '') }}" class="form-control form-control-sm" placeholder="e.g. +63 (2) 8123-4567">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold small">Official Inquiries Email</label>
                                        <input type="email" name="hospital_email" value="{{ old('hospital_email', $current['hospital.email'] ?? '') }}" class="form-control form-control-sm" placeholder="e.g. info@dtcs-hospital.com">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Logo & Print Preview --}}
                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white border-bottom py-3">
                                <h6 class="m-0 fw-bold text-dark"><i class="bi bi-image me-2 text-primary"></i> Official Logo & Headers</h6>
                            </div>
                            <div class="card-body d-flex flex-column align-items-center justify-content-center text-center p-4">
                                <div class="p-3 bg-light rounded border mb-3 d-flex align-items-center justify-content-center" style="width: 140px; height: 140px;">
                                    <img src="{{ route('branding.logo') }}" alt="Hospital Logo" class="img-fluid" style="max-height: 110px; object-fit: contain;">
                                </div>
                                <span class="badge bg-secondary mb-3">Live Header & Print Logo</span>
                                <div class="w-100 text-start">
                                    <label class="form-label fw-semibold small">Upload Replacement Logo</label>
                                    <input type="file" name="hospital_logo" accept="image/png,image/jpeg,image/webp" class="form-control form-control-sm mb-1">
                                    <div class="form-text small">Accepted formats: PNG, JPG, WEBP. Maximum file size: 1 MB.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Mandatory Reason & Submit --}}
                    <div class="col-12">
                        <div class="card border-0 shadow-sm bg-light">
                            <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                                <div class="flex-grow-1" style="max-width: 600px;">
                                    <label class="form-label fw-semibold text-danger small mb-1">Mandatory Audit Rationale *</label>
                                    <input type="text" name="reason" class="form-control form-control-sm" placeholder="Reason for updating hospital identity or official contact information" required minlength="5">
                                </div>
                                <div>
                                    <button type="submit" class="btn btn-primary btn-sm px-4">
                                        <i class="bi bi-save me-1"></i> Save Branding Settings
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        {{-- TAB 2: Operating Hours --}}
        <div class="tab-pane fade" id="tab-hours" role="tabpanel" aria-labelledby="hours-tab">
            <form action="{{ route('admin.hospital-settings.hours') }}" method="POST">
                @csrf
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="m-0 fw-bold text-dark"><i class="bi bi-calendar3 me-2 text-info"></i> Weekly Department & Outpatient Schedule</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 small">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 25%;">Day of Week</th>
                                        <th style="width: 25%;">Opening Time</th>
                                        <th style="width: 25%;">Closing Time</th>
                                        <th style="width: 25%;" class="text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                                        $hoursData = $current['hours.weekly'] ?? [];
                                        if (is_string($hoursData)) {
                                            $hoursData = json_decode($hoursData, true) ?? [];
                                        }
                                    @endphp
                                    @foreach($days as $day)
                                        @php
                                            $dayKey = strtolower($day);
                                            $dayConfig = $hoursData[$dayKey] ?? ['open' => '08:00', 'close' => '17:00', 'is_closed' => false];
                                            $isClosed = !empty($dayConfig['is_closed']);
                                        @endphp
                                        <tr>
                                            <td class="fw-semibold">{{ $day }}</td>
                                            <td>
                                                <input type="time" name="hours[{{ $dayKey }}][open]" value="{{ $dayConfig['open'] ?? '08:00' }}" class="form-control form-control-sm font-monospace">
                                            </td>
                                            <td>
                                                <input type="time" name="hours[{{ $dayKey }}][close]" value="{{ $dayConfig['close'] ?? '17:00' }}" class="form-control form-control-sm font-monospace">
                                            </td>
                                            <td class="text-center">
                                                <div class="form-check form-switch d-inline-block">
                                                    <input class="form-check-input" type="checkbox" name="hours[{{ $dayKey }}][is_closed]" value="1" id="closed_{{ $dayKey }}" {{ $isClosed ? 'checked' : '' }}>
                                                    <label class="form-check-label small" for="closed_{{ $dayKey }}">Closed</label>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- Mandatory Reason & Submit --}}
                <div class="card border-0 shadow-sm bg-light">
                    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div class="flex-grow-1" style="max-width: 600px;">
                            <label class="form-label fw-semibold text-danger small mb-1">Mandatory Audit Rationale *</label>
                            <input type="text" name="reason" class="form-control form-control-sm" placeholder="Reason for adjusting facility operating hours" required minlength="5">
                        </div>
                        <div>
                            <button type="submit" class="btn btn-info btn-sm text-white px-4">
                                <i class="bi bi-save me-1"></i> Update Operating Hours
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        {{-- TAB 3: Queue & Operational TAT Thresholds --}}
        <div class="tab-pane fade" id="tab-queue" role="tabpanel" aria-labelledby="queue-tab">
            <form action="{{ route('admin.hospital-settings.queue') }}" method="POST">
                @csrf
                <div class="row g-4 mb-4">
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white border-bottom py-3">
                                <h6 class="m-0 fw-bold text-dark"><i class="bi bi-ticket-perforated me-2 text-warning"></i> Departmental Queue Prefixes</h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold small">Diagnostic Queue Prefix *</label>
                                    <input type="text" name="queue_prefix_diagnostic" value="{{ old('queue_prefix_diagnostic', $current['queue.prefix_diagnostic'] ?? 'DIAG-') }}" class="form-control form-control-sm font-monospace" pattern="^[A-Z]{2,6}-$" required>
                                    <div class="form-text small">Used for Laboratory (LIS) and Radiology (RIS) queue generation. Format: 2-6 uppercase letters followed by hyphen (e.g. <code>DIAG-</code> or <code>LAB-</code>).</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold small">Treatment Queue Prefix *</label>
                                    <input type="text" name="queue_prefix_treatment" value="{{ old('queue_prefix_treatment', $current['queue.prefix_treatment'] ?? 'TRT-') }}" class="form-control form-control-sm font-monospace" pattern="^[A-Z]{2,6}-$" required>
                                    <div class="form-text small">Used for Pharmacy (PMS) and Surgical (SORS) queue generation. Must differ from diagnostic prefix (e.g. <code>TRT-</code> or <code>SUR-</code>).</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white border-bottom py-3">
                                <h6 class="m-0 fw-bold text-dark"><i class="bi bi-speedometer2 me-2 text-danger"></i> Turnaround Time (TAT) Thresholds</h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold small">Operations Delay Alert Threshold (Minutes) *</label>
                                    <div class="input-group input-group-sm">
                                        <input type="number" name="queue_delay_threshold" value="{{ old('queue_delay_threshold', $current['ops.queue_delay_threshold_minutes'] ?? 60) }}" min="15" max="1440" class="form-control form-control-sm" required>
                                        <span class="input-group-text">minutes</span>
                                    </div>
                                    <div class="form-text small">Orders and queues exceeding this threshold without resolution are highlighted as delayed on the Operations Monitor. Range: 15 to 1440 minutes.</div>
                                </div>
                                <div class="p-3 bg-light rounded text-muted small">
                                    <i class="bi bi-info-circle text-primary me-1"></i> Orders flagged beyond this limit trigger visual status badges and alert counters across LIS, RIS, and SORS monitoring panels.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Mandatory Reason & Submit --}}
                <div class="card border-0 shadow-sm bg-light">
                    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div class="flex-grow-1" style="max-width: 600px;">
                            <label class="form-label fw-semibold text-danger small mb-1">Mandatory Audit Rationale *</label>
                            <input type="text" name="reason" class="form-control form-control-sm" placeholder="Reason for modifying queue prefixes or turnaround thresholds" required minlength="5">
                        </div>
                        <div>
                            <button type="submit" class="btn btn-warning btn-sm px-4">
                                <i class="bi bi-save me-1"></i> Save Queue Settings
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        {{-- TAB 4: Financial & Tax Rules --}}
        <div class="tab-pane fade" id="tab-financial" role="tabpanel" aria-labelledby="financial-tab">
            <div class="row g-4 mb-4">
                {{-- Active Rule Card --}}
                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-bottom py-3">
                            <h6 class="m-0 fw-bold text-dark"><i class="bi bi-check-circle-fill me-2 text-success"></i> Currently Active Financial Rule</h6>
                        </div>
                        <div class="card-body">
                            @if($activeFinancialRule)
                                <div class="mb-3">
                                    <span class="text-muted small d-block">Effective Since:</span>
                                    <span class="fw-bold text-dark">{{ \Carbon\Carbon::parse($activeFinancialRule->effective_from)->format('F d, Y') }}</span>
                                </div>
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <div class="p-3 bg-light rounded text-center">
                                            <span class="text-muted small d-block">Currency</span>
                                            <span class="fs-5 fw-bold text-dark">{{ $activeFinancialRule->currency_code }} ({{ $activeFinancialRule->currency_symbol }})</span>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="p-3 bg-light rounded text-center">
                                            <span class="text-muted small d-block">{{ $activeFinancialRule->tax_name ?? 'Tax' }}</span>
                                            <span class="fs-5 fw-bold text-dark">{{ bcmul((string) $activeFinancialRule->tax_rate, '100', 2) }}%</span>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="p-3 bg-light rounded text-center">
                                            <span class="text-muted small d-block">Service Charge Rate</span>
                                            <span class="fs-5 fw-bold text-dark">{{ bcmul((string) $activeFinancialRule->service_charge_rate, '100', 2) }}%</span>
                                        </div>
                                    </div>
                                </div>
                                @if($activeFinancialRule->notes)
                                    <p class="text-muted small mb-0"><strong>Notes:</strong> {{ $activeFinancialRule->notes }}</p>
                                @endif
                            @else
                                <div class="text-center py-4 text-muted">
                                    <i class="bi bi-exclamation-circle fs-3 d-block mb-2 text-warning"></i>
                                    No active financial rule found. Please create one below.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Create New Immutable Financial Rule --}}
                <div class="col-lg-7">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-bottom py-3">
                            <h6 class="m-0 fw-bold text-dark"><i class="bi bi-plus-circle me-2 text-primary"></i> Create New Effective-Dated Financial Rule</h6>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-3">
                                <i class="bi bi-lock-fill text-secondary me-1"></i> Financial rules are <strong>strictly immutable</strong> once created to guarantee billing audit integrity. Changes are registered by defining a new future or present effective date.
                            </p>
                            <form action="{{ route('admin.hospital-settings.financial-rules') }}" method="POST">
                                @csrf
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold small">Currency Code (3 Letters) *</label>
                                        <input type="text" name="currency_code" value="{{ old('currency_code', 'PHP') }}" class="form-control form-control-sm font-monospace" maxlength="3" pattern="^[A-Z]{3}$" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold small">Currency Symbol *</label>
                                        <input type="text" name="currency_symbol" value="{{ old('currency_symbol', '₱') }}" class="form-control form-control-sm" maxlength="8" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold small">Tax Name</label>
                                        <input type="text" name="tax_name" value="{{ old('tax_name', 'VAT') }}" class="form-control form-control-sm" placeholder="VAT / GST">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold small">Tax Rate (0 to 1.0) *</label>
                                        <input type="number" step="0.0001" name="tax_rate" value="{{ old('tax_rate', '0.1200') }}" min="0" max="1" class="form-control form-control-sm font-monospace" required>
                                        <div class="form-text small">e.g. 0.1200 = 12%</div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold small">Service Charge Rate *</label>
                                        <input type="number" step="0.0001" name="service_charge_rate" value="{{ old('service_charge_rate', '0.0000') }}" min="0" max="1" class="form-control form-control-sm font-monospace" required>
                                        <div class="form-text small">e.g. 0.0500 = 5%</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold small">Effective Date *</label>
                                        <input type="date" name="effective_from" value="{{ old('effective_from', now()->toDateString()) }}" class="form-control form-control-sm" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold small">Notes / Regulatory Reference</label>
                                        <input type="text" name="notes" value="{{ old('notes') }}" class="form-control form-control-sm" placeholder="e.g. Tax Reform Law 2026 update">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-semibold text-danger small">Mandatory Audit Rationale *</label>
                                        <input type="text" name="reason" value="{{ old('reason') }}" class="form-control form-control-sm" placeholder="Formal justification for financial parameter adjustment" required minlength="5">
                                    </div>
                                    <div class="col-12 text-end">
                                        <button type="submit" class="btn btn-success btn-sm px-4">
                                            <i class="bi bi-check2-circle me-1"></i> Enact Financial Rule
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                {{-- Historical & Scheduled Financial Rules --}}
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-bottom py-3">
                            <h6 class="m-0 fw-bold text-dark"><i class="bi bi-clock-history me-2 text-secondary"></i> Financial Rules History & Scheduled Versions</h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0 small">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Effective Date</th>
                                            <th>Currency</th>
                                            <th>Tax Rate</th>
                                            <th>Service Charge</th>
                                            <th>Created By</th>
                                            <th>Date Enacted</th>
                                            <th>Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($financialRules as $rule)
                                            <tr>
                                                <td class="fw-semibold">
                                                    {{ \Carbon\Carbon::parse($rule->effective_from)->format('M d, Y') }}
                                                    @if($rule->effective_from <= now()->toDateString())
                                                        <span class="badge bg-success-subtle text-success ms-1">Active / Past</span>
                                                    @else
                                                        <span class="badge bg-primary-subtle text-primary ms-1">Scheduled</span>
                                                    @endif
                                                </td>
                                                <td><span class="badge bg-light text-dark border">{{ $rule->currency_code }} ({{ $rule->currency_symbol }})</span></td>
                                                <td class="font-monospace">{{ bcmul((string) $rule->tax_rate, '100', 2) }}% ({{ $rule->tax_name ?? 'Tax' }})</td>
                                                <td class="font-monospace">{{ bcmul((string) $rule->service_charge_rate, '100', 2) }}%</td>
                                                <td>{{ $rule->createdBy?->name ?? 'System' }}</td>
                                                <td class="text-muted">{{ $rule->created_at?->format('M d, Y H:i') ?? '-' }}</td>
                                                <td class="text-muted">{{ $rule->notes ?? '-' }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="text-center text-muted py-3">No financial rule history recorded.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- TAB 5: Setting Change History --}}
        <div class="tab-pane fade" id="tab-history" role="tabpanel" aria-labelledby="history-tab">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="m-0 fw-bold text-dark"><i class="bi bi-clock-history me-2 text-primary"></i> Hospital Configuration Audit Trail</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="table-light">
                                <tr>
                                    <th>Timestamp</th>
                                    <th>Setting Key</th>
                                    <th>Previous Value</th>
                                    <th>New Value</th>
                                    <th>Modified By</th>
                                    <th>IP Address</th>
                                    <th>Stated Rationale</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($histories as $history)
                                    <tr>
                                        <td class="text-muted font-monospace">{{ $history->created_at?->format('Y-m-d H:i:s') }}</td>
                                        <td class="fw-semibold"><code>{{ $history->setting_key }}</code></td>
                                        <td class="text-muted font-monospace text-truncate" style="max-width: 140px;" title="{{ $history->old_value }}">
                                            {{ Str::limit($history->old_value ?? '(empty)', 30) }}
                                        </td>
                                        <td class="text-success font-monospace text-truncate" style="max-width: 140px;" title="{{ $history->new_value }}">
                                            {{ Str::limit($history->new_value ?? '(empty)', 30) }}
                                        </td>
                                        <td>{{ $history->changedBy?->name ?? 'System' }}</td>
                                        <td class="text-muted font-monospace">{{ $history->ip_address ?? '-' }}</td>
                                        <td>{{ $history->reason }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">No configuration modifications recorded yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
