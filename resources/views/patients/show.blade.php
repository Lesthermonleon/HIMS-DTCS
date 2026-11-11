@extends('layouts.app')
@section('title', 'Patient: ' . $patient->last_name . ', ' . $patient->first_name)
@section('page-title', 'Patient Profile')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('patients.index') }}">Patients</a></li>
    <li class="breadcrumb-item active">{{ $patient->last_name }}, {{ $patient->first_name }}</li>
@endsection

@push('styles')
<style>
    /* ══════════════════════════════════════════════════════════════════
       PATIENT PROFILE WORKSPACE — DTCS HIMS CLINICAL DESIGN SYSTEM
    ══════════════════════════════════════════════════════════════════ */
    .patient-profile-workspace {
        --pp-teal: #0f766e;
        --pp-teal-hover: #115e59;
        --pp-teal-subtle: #f0fdfa;
        --pp-teal-border: #99f6e4;
        --pp-surface: #ffffff;
        --pp-surface-alt: #f8fafc;
        --pp-border: #e2e8f0;
        --pp-border-strong: #cbd5e1;
        --pp-text: #0f172a;
        --pp-text-secondary: #334155;
        --pp-text-muted: #64748b;
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    html[data-theme="dark"] .patient-profile-workspace,
    html[data-bs-theme="dark"] .patient-profile-workspace {
        --pp-teal: #14b8a6;
        --pp-teal-hover: #0d9488;
        --pp-teal-subtle: rgba(20, 184, 166, 0.12);
        --pp-teal-border: rgba(20, 184, 166, 0.35);
        --pp-surface: #111827;
        --pp-surface-alt: #1f2937;
        --pp-border: #374151;
        --pp-border-strong: #4b5563;
        --pp-text: #f9fafb;
        --pp-text-secondary: #e5e7eb;
        --pp-text-muted: #9ca3af;
    }

    .patient-profile-workspace .pp-card {
        background: var(--pp-surface) !important;
        border: 1px solid var(--pp-border) !important;
        border-radius: 0.75rem !important;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04) !important;
        overflow: hidden;
    }

    /* ── A. Patient Profile Header ── */
    .patient-profile-workspace .pp-header-card {
        padding: 1rem 1.25rem !important;
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: wrap !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 0.75rem 1.25rem !important;
    }

    .pp-identity {
        display: flex;
        flex-direction: row;
        align-items: center;
        gap: 0.9rem;
        min-width: 0;
        flex: 1 1 auto;
    }

    .patient-avatar {
        width: 48px;
        height: 48px;
        border-radius: 0.75rem;
        background: var(--pp-teal);
        color: #ffffff;
        font-family: var(--font-display);
        font-size: 1.15rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        letter-spacing: 0.02em;
        box-shadow: 0 2px 6px rgba(15, 118, 110, 0.2);
    }

    .pp-identity-text {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        justify-content: center;
        gap: 0.25rem;
        min-width: 0;
    }

    .pp-title-row {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.45rem 0.6rem;
    }

    .patient-profile-workspace .patient-name {
        font-family: var(--font-display);
        font-size: 1.15rem;
        font-weight: 700;
        color: var(--pp-text);
        line-height: 1.25;
        margin: 0;
        max-width: none !important;
    }

    .pp-meta-row {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.35rem 0.65rem;
        font-size: 0.8rem;
        color: var(--pp-text-muted);
        line-height: 1.3;
    }

    .patient-id {
        font-family: var(--font-mono);
        font-size: 0.76rem;
        font-weight: 600;
        color: var(--pp-teal);
        background: var(--pp-teal-subtle);
        border: 1px solid var(--pp-teal-border);
        padding: 0.12rem 0.5rem;
        border-radius: 0.375rem;
        letter-spacing: 0.03em;
        white-space: nowrap;
    }

    .patient-type-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        font-size: 0.73rem;
        font-weight: 600;
        padding: 0.2rem 0.6rem;
        border-radius: 9999px;
        line-height: 1.2;
        white-space: nowrap;
    }

    .patient-type-pill.type-inpatient {
        background: #eff6ff;
        color: #1d4ed8;
        border: 1px solid #bfdbfe;
    }

    .patient-type-pill.type-outpatient {
        background: #f0fdf4;
        color: #15803d;
        border: 1px solid #bbf7d0;
    }

    .patient-type-pill.type-emergency {
        background: #fef2f2;
        color: #b91c1c;
        border: 1px solid #fecaca;
    }

    html[data-theme="dark"] .patient-type-pill.type-inpatient {
        background: rgba(29, 78, 216, 0.22);
        color: #93c5fd;
        border-color: rgba(59, 130, 246, 0.35);
    }

    html[data-theme="dark"] .patient-type-pill.type-outpatient {
        background: rgba(21, 128, 61, 0.22);
        color: #86efac;
        border-color: rgba(34, 197, 94, 0.35);
    }

    .pp-header-right {
        display: flex;
        flex-direction: row;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.6rem;
        flex-shrink: 0;
        margin-left: auto;
    }

    .pp-summary-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.35rem 0.7rem;
        background: var(--pp-surface-alt);
        border: 1px solid var(--pp-border);
        border-radius: 0.5rem;
        font-size: 0.78rem;
        color: var(--pp-text-secondary);
        white-space: nowrap;
    }

    .pp-summary-chip strong {
        color: var(--pp-text);
        font-weight: 600;
    }

    .pp-btn-back {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        height: 2.125rem;
        padding: 0 0.75rem;
        font-size: 0.78rem;
        font-weight: 600;
        border-radius: 0.5rem;
        background: var(--pp-surface);
        border: 1px solid var(--pp-border-strong);
        color: var(--pp-text-secondary);
        text-decoration: none;
        white-space: nowrap;
        transition: all 0.15s ease;
    }

    .pp-btn-back:hover {
        border-color: var(--pp-teal);
        color: var(--pp-teal);
        background: var(--pp-teal-subtle);
    }

    /* ── Main Balanced Two-Column Grid (34% Info / 66% Clinical Workspace) ── */
    .pp-main-grid {
        display: grid;
        grid-template-columns: minmax(270px, 34%) minmax(0, 66%);
        gap: 1rem;
        align-items: start;
        min-width: 0;
    }

    /* ── B. Patient Information Sections & Clean Info Grid ── */
    .pp-card-header {
        padding: 0.8rem 1.125rem;
        background: var(--pp-surface);
        border-bottom: 1px solid var(--pp-border);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
    }

    .pp-card-title {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--pp-text);
        margin: 0;
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
    }

    .pp-card-title i {
        color: var(--pp-teal);
    }

    .pp-readonly-tag {
        font-size: 0.7rem;
        font-weight: 500;
        color: var(--pp-text-muted);
        background: var(--pp-surface-alt);
        border: 1px solid var(--pp-border);
        padding: 0.15rem 0.5rem;
        border-radius: 0.375rem;
        white-space: nowrap;
    }

    .pp-info-section {
        padding: 0.9rem 1.125rem;
        border-bottom: 1px solid var(--pp-border);
    }

    .pp-info-section:last-child {
        border-bottom: none;
    }

    .pp-section-heading {
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--pp-text-muted);
        margin: 0 0 0.65rem 0;
        display: flex;
        align-items: center;
        gap: 0.4rem;
    }

    .pp-section-heading i {
        color: var(--pp-teal);
        font-size: 0.8rem;
    }

    .pp-info-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.65rem 1rem;
    }

    .pp-info-item {
        display: flex;
        flex-direction: column;
        gap: 0.15rem;
        min-width: 0;
    }

    .pp-info-item.pp-col-span-2 {
        grid-column: 1 / -1;
    }

    .pp-info-label {
        font-size: 0.72rem;
        font-weight: 500;
        color: var(--pp-text-muted);
        line-height: 1.2;
    }

    .pp-info-value {
        font-size: 0.835rem;
        font-weight: 600;
        color: var(--pp-text);
        line-height: 1.35;
        word-break: break-word;
    }

    .pp-info-value-muted {
        color: var(--pp-text-muted);
        font-weight: 400;
    }

    .pp-blood-badge {
        display: inline-flex;
        align-items: center;
        font-family: var(--font-mono);
        font-size: 0.78rem;
        font-weight: 700;
        color: #b91c1c;
        background: #fef2f2;
        border: 1px solid #fecaca;
        padding: 0.1rem 0.45rem;
        border-radius: 0.375rem;
        width: fit-content;
    }

    html[data-theme="dark"] .pp-blood-badge {
        background: rgba(185, 28, 28, 0.22);
        color: #fca5a5;
        border-color: rgba(239, 68, 68, 0.35);
    }

    /* ── C. Clinical Records Workspace & Module Tabs ── */
    .pp-tabs-wrapper {
        padding: 0.45rem 0.65rem;
        background: var(--pp-surface-alt);
        border-bottom: 1px solid var(--pp-border);
        overflow-x: hidden;
    }

    .patient-tabs {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.25rem;
        margin: 0;
        padding: 0;
        border: none;
        width: 100%;
        list-style: none;
    }

    .patient-tabs .nav-item {
        flex: 1 1 auto;
        min-width: 0;
    }

    .patient-tabs .nav-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        gap: 0.28rem;
        padding: 0.38rem 0.45rem;
        font-size: 0.75rem;
        font-weight: 500;
        line-height: 1.2;
        letter-spacing: -0.01em;
        color: var(--pp-text-secondary) !important;
        background: transparent !important;
        border: 1px solid transparent !important;
        border-radius: 0.5rem !important;
        white-space: nowrap;
        transition: all 0.15s ease;
    }

    .patient-tabs .nav-link i {
        font-size: 0.8rem;
        line-height: 1;
        flex-shrink: 0;
    }

    .patient-tabs .nav-link:hover {
        color: var(--pp-text) !important;
        background: var(--pp-surface) !important;
        border-color: var(--pp-border) !important;
    }

    .patient-tabs .nav-link.active {
        background: var(--pp-teal) !important;
        border-color: var(--pp-teal) !important;
        color: #ffffff !important;
        font-weight: 600;
        box-shadow: 0 1px 2px rgba(15, 118, 110, 0.22);
    }

    .patient-tabs .nav-link .tab-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 1.2rem;
        height: 1.125rem;
        padding: 0 0.32rem;
        border-radius: 9999px;
        font-size: 0.68rem;
        font-weight: 600;
        font-family: var(--font-mono);
        background: #e2e8f0;
        color: #1e293b;
        line-height: 1;
        flex-shrink: 0;
    }

    html[data-theme="dark"] .patient-tabs .nav-link .tab-count {
        background: #374151;
        color: #f3f4f6;
    }

    .patient-tabs .nav-link.active .tab-count {
        background: rgba(255, 255, 255, 0.22);
        color: #ffffff;
    }

    /* ── D. Clinical Records Tables ── */
    .patient-profile-workspace .clinical-table {
        width: 100%;
        margin-bottom: 0;
        border-collapse: collapse;
    }

    .patient-profile-workspace .clinical-table thead th {
        background-color: var(--pp-surface-alt) !important;
        color: var(--pp-text-muted) !important;
        font-size: 0.72rem !important;
        font-weight: 600 !important;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        padding: 0.65rem 0.9rem !important;
        border-bottom: 1px solid var(--pp-border) !important;
        white-space: nowrap !important;
        overflow-wrap: normal !important;
    }

    .patient-profile-workspace .clinical-table tbody td {
        padding: 0.7rem 0.9rem !important;
        font-size: 0.83rem;
        color: var(--pp-text) !important;
        border-bottom: 1px solid var(--pp-border) !important;
        vertical-align: middle !important;
        overflow-wrap: normal !important;
    }

    .patient-profile-workspace .clinical-table tbody tr:last-child td {
        border-bottom: none !important;
    }

    .patient-profile-workspace .clinical-table tbody tr:hover td {
        background-color: var(--pp-surface-alt) !important;
    }

    .pp-code-link {
        color: var(--pp-teal) !important;
        font-weight: 600;
        font-family: var(--font-mono);
        font-size: 0.8rem;
        text-decoration: none;
        white-space: nowrap;
    }

    .pp-code-link:hover {
        color: var(--pp-teal-hover) !important;
        text-decoration: underline;
    }

    .pp-cell-nowrap {
        white-space: nowrap !important;
    }

    .pp-cell-truncate {
        max-width: 220px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    /* ── Semantic Status & Priority Badges ── */
    .record-status {
        display: inline-flex;
        align-items: center;
        gap: 0.32rem;
        font-size: 0.73rem;
        font-weight: 600;
        padding: 0.22rem 0.6rem;
        border-radius: 9999px;
        white-space: nowrap;
        line-height: 1.2;
    }

    .record-status-dot {
        width: 0.38rem;
        height: 0.38rem;
        border-radius: 9999px;
        flex-shrink: 0;
    }

    .status-amber {
        background: #fffbeb !important;
        color: #b45309 !important;
        border: 1px solid #fde68a !important;
    }
    .status-amber .record-status-dot { background: #d97706; }

    .status-green {
        background: #f0fdf4 !important;
        color: #15803d !important;
        border: 1px solid #bbf7d0 !important;
    }
    .status-green .record-status-dot { background: #16a34a; }

    .status-blue {
        background: #eff6ff !important;
        color: #1d4ed8 !important;
        border: 1px solid #bfdbfe !important;
    }
    .status-blue .record-status-dot { background: #2563eb; }

    .status-red {
        background: #fef2f2 !important;
        color: #b91c1c !important;
        border: 1px solid #fecaca !important;
    }
    .status-red .record-status-dot { background: #dc2626; }

    html[data-theme="dark"] .status-amber {
        background: rgba(180, 83, 9, 0.2) !important;
        color: #fcd34d !important;
        border-color: rgba(245, 158, 11, 0.35) !important;
    }
    html[data-theme="dark"] .status-green {
        background: rgba(21, 128, 61, 0.22) !important;
        color: #86efac !important;
        border-color: rgba(34, 197, 94, 0.35) !important;
    }
    html[data-theme="dark"] .status-blue {
        background: rgba(29, 78, 216, 0.22) !important;
        color: #93c5fd !important;
        border-color: rgba(59, 130, 246, 0.35) !important;
    }
    html[data-theme="dark"] .status-red {
        background: rgba(185, 28, 28, 0.22) !important;
        color: #fca5a5 !important;
        border-color: rgba(239, 68, 68, 0.35) !important;
    }

    .pp-priority-badge {
        display: inline-flex;
        align-items: center;
        font-size: 0.72rem;
        font-weight: 600;
        padding: 0.18rem 0.5rem;
        border-radius: 0.375rem;
        white-space: nowrap;
        background: var(--pp-surface-alt);
        color: var(--pp-text-secondary);
        border: 1px solid var(--pp-border);
    }

    .pp-priority-urgent {
        background: #fffbeb;
        color: #b45309;
        border-color: #fde68a;
    }

    .pp-priority-stat {
        background: #fef2f2;
        color: #b91c1c;
        border-color: #fecaca;
    }

    .pp-action-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.3rem;
        height: 1.85rem;
        padding: 0 0.6rem;
        font-size: 0.75rem;
        font-weight: 600;
        border-radius: 0.375rem;
        border: 1px solid var(--pp-border);
        background: var(--pp-surface);
        color: var(--pp-text-secondary);
        text-decoration: none;
        white-space: nowrap;
        transition: all 0.15s ease;
    }

    .pp-action-btn:hover {
        border-color: var(--pp-teal);
        background: var(--pp-teal-subtle);
        color: var(--pp-teal);
    }

    .clinical-table .empty-row td {
        color: var(--pp-text-muted) !important;
        font-size: 0.82rem;
        padding: 2.25rem 1rem !important;
        text-align: center;
    }

    /* ── Responsive Breakpoints (1440px, 1366px, 1024px, 768px, 375px) ── */
    @media (max-width: 991.98px) {
        .pp-main-grid {
            grid-template-columns: 1fr;
        }
        .pp-tabs-wrapper {
            padding: 0.55rem 0.85rem;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
        }
        .patient-tabs {
            display: inline-flex;
            flex-wrap: nowrap;
            min-width: 100%;
            width: max-content;
            gap: 0.35rem;
        }
        .patient-tabs .nav-item {
            flex: 0 0 auto;
        }
        .patient-tabs .nav-link {
            width: auto;
            padding: 0.42rem 0.7rem;
            font-size: 0.78rem;
            gap: 0.35rem;
        }
    }

    @media (max-width: 575.98px) {
        .patient-profile-workspace .pp-header-card {
            padding: 0.875rem 1rem !important;
            flex-direction: column !important;
            align-items: stretch !important;
        }
        .pp-header-right {
            width: 100%;
            margin-left: 0;
            justify-content: space-between;
        }
        .pp-info-grid {
            grid-template-columns: 1fr;
        }
        .patient-profile-workspace .patient-name {
            font-size: 1.02rem;
        }
    }
</style>
@endpush

@section('content')

@php
    $labCount   = $patient->labRequests->count();
    $radCount   = $patient->radiologyRequests->count();
    $rxCount    = $patient->prescriptions->count();
    $surCount   = $patient->surgeryRequests->count();
    $dietCount  = $patient->dietRequests->count();
    $totalClinicalRecords = $labCount + $radCount + $rxCount + $surCount + $dietCount;

    $firstInitial = strtoupper(substr((string) $patient->first_name, 0, 1));
    $lastInitial  = strtoupper(substr((string) $patient->last_name, 0, 1));
    $initials     = ($firstInitial . $lastInitial) ?: 'PT';

    $dobFormatted = $patient->date_of_birth ? \Carbon\Carbon::parse($patient->date_of_birth)->format('M d, Y') : '—';
    $patientAge   = $patient->date_of_birth ? \Carbon\Carbon::parse($patient->date_of_birth)->age : null;

    $statusClassFor = function (?string $status): string {
        $s = strtolower((string) $status);
        if (in_array($s, ['completed', 'approved', 'released', 'dispensed'], true)) {
            return 'status-green';
        }
        if (in_array($s, ['cancelled', 'rejected'], true)) {
            return 'status-red';
        }
        if (in_array($s, ['in progress', 'scheduled', 'active', 'revised', 'encoded', 'validated'], true)) {
            return 'status-blue';
        }
        return 'status-amber';
    };

    $priorityClassFor = function (?string $priority): string {
        $p = strtolower((string) $priority);
        if (in_array($p, ['stat', 'emergency'], true)) {
            return 'pp-priority-badge pp-priority-stat';
        }
        if ($p === 'urgent') {
            return 'pp-priority-badge pp-priority-urgent';
        }
        return 'pp-priority-badge';
    };
@endphp

<div class="patient-profile-workspace">

    {{-- ══════════ A. PATIENT PROFILE HEADER (Full Width) ══════════ --}}
    <div class="card pp-card pp-header-card">
        <div class="pp-identity">
            <div class="patient-avatar" aria-hidden="true">{{ $initials }}</div>
            <div class="pp-identity-text">
                <div class="pp-title-row">
                    <h1 class="patient-name">
                        {{ $patient->last_name }}, {{ $patient->first_name }}@if($patient->middle_name) {{ $patient->middle_name }}@endif
                    </h1>
                    <span class="patient-id">{{ $patient->patient_no }}</span>
                    <span class="patient-type-pill type-{{ strtolower($patient->patient_type ?? 'outpatient') }}">
                        <i class="bi bi-person-badge"></i>
                        <span>{{ $patient->patient_type ?? 'Outpatient' }}</span>
                    </span>
                </div>
                <div class="pp-meta-row">
                    <span>
                        <i class="bi bi-calendar3 me-1"></i>{{ $dobFormatted }}@if($patientAge !== null) ({{ $patientAge }} yrs)@endif
                    </span>
                    <span>&bull;</span>
                    <span>{{ $patient->gender ?: '—' }}</span>
                    @if($patient->blood_type)
                        <span>&bull;</span>
                        <span>Blood Type: <strong class="text-danger font-monospace">{{ $patient->blood_type }}</strong></span>
                    @endif
                </div>
            </div>
        </div>

        <div class="pp-header-right">
            @if($patient->ward)
                <div class="pp-summary-chip">
                    <i class="bi bi-hospital" style="color: var(--pp-teal);"></i>
                    <span>Ward / Bed: <strong>{{ $patient->ward }} / {{ $patient->bed_number ?: 'N/A' }}</strong></span>
                </div>
            @endif
            <a href="{{ route('patients.index') }}" class="pp-btn-back">
                <i class="bi bi-arrow-left"></i>
                <span>Patient Directory</span>
            </a>
        </div>
    </div>

    {{-- ══════════ MAIN CONTENT GRID (38% Patient Info / 62% Clinical Workspace) ══════════ --}}
    <div class="pp-main-grid">

        {{-- ══════════ B. PATIENT INFORMATION COLUMN ══════════ --}}
        <div class="card pp-card">
            <div class="pp-card-header">
                <h2 class="pp-card-title">
                    <i class="bi bi-person-vcard"></i>
                    <span>Patient Information</span>
                </h2>
                <span class="pp-readonly-tag">
                    <i class="bi bi-lock me-1"></i>Read-Only
                </span>
            </div>

            {{-- 1. Personal Information --}}
            <div class="pp-info-section">
                <h3 class="pp-section-heading">
                    <i class="bi bi-person-lines-fill"></i>
                    <span>Personal Information</span>
                </h3>
                <div class="pp-info-grid">
                    <div class="pp-info-item">
                        <span class="pp-info-label">Date of Birth</span>
                        <span class="pp-info-value">
                            {{ $dobFormatted }}
                            @if($patientAge !== null)
                                <span class="pp-info-value-muted">({{ $patientAge }} yrs)</span>
                            @endif
                        </span>
                    </div>
                    <div class="pp-info-item">
                        <span class="pp-info-label">Gender</span>
                        <span class="pp-info-value">{{ $patient->gender ?: '—' }}</span>
                    </div>
                    <div class="pp-info-item">
                        <span class="pp-info-label">Blood Type</span>
                        <span class="pp-info-value">
                            @if($patient->blood_type)
                                <span class="pp-blood-badge">{{ $patient->blood_type }}</span>
                            @else
                                <span class="pp-info-value-muted">—</span>
                            @endif
                        </span>
                    </div>
                    <div class="pp-info-item">
                        <span class="pp-info-label">Phone</span>
                        <span class="pp-info-value">{{ $patient->phone ?: '—' }}</span>
                    </div>
                    <div class="pp-info-item pp-col-span-2">
                        <span class="pp-info-label">Email</span>
                        <span class="pp-info-value">{{ $patient->email ?: '—' }}</span>
                    </div>
                </div>
            </div>

            {{-- 2. Admission Information --}}
            <div class="pp-info-section">
                <h3 class="pp-section-heading">
                    <i class="bi bi-hospital"></i>
                    <span>Admission Information</span>
                </h3>
                <div class="pp-info-grid">
                    <div class="pp-info-item">
                        <span class="pp-info-label">Ward / Bed</span>
                        <span class="pp-info-value">
                            @if($patient->ward)
                                {{ $patient->ward }} / {{ $patient->bed_number ?: 'N/A' }}
                            @else
                                <span class="pp-info-value-muted">—</span>
                            @endif
                        </span>
                    </div>
                    <div class="pp-info-item">
                        <span class="pp-info-label">Patient Type</span>
                        <span class="pp-info-value">{{ $patient->patient_type ?: '—' }}</span>
                    </div>
                    @if($patient->emergency_contact_name || $patient->emergency_contact_phone)
                        <div class="pp-info-item">
                            <span class="pp-info-label">Emergency Contact</span>
                            <span class="pp-info-value">{{ $patient->emergency_contact_name ?: '—' }}</span>
                        </div>
                        <div class="pp-info-item">
                            <span class="pp-info-label">Emergency Phone</span>
                            <span class="pp-info-value">{{ $patient->emergency_contact_phone ?: '—' }}</span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- 3. Address --}}
            <div class="pp-info-section">
                <h3 class="pp-section-heading">
                    <i class="bi bi-geo-alt"></i>
                    <span>Address</span>
                </h3>
                <div class="pp-info-grid">
                    <div class="pp-info-item pp-col-span-2">
                        <span class="pp-info-label">Patient Address</span>
                        <span class="pp-info-value">
                            @if($patient->address)
                                {{ $patient->address }}
                            @else
                                <span class="pp-info-value-muted">No address recorded</span>
                            @endif
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- ══════════ C & D. CLINICAL RECORDS WORKSPACE ══════════ --}}
        <div class="card pp-card">
            <div class="pp-card-header">
                <h2 class="pp-card-title">
                    <i class="bi bi-folder2-open"></i>
                    <span>Clinical Module Records</span>
                </h2>
                <span class="pp-readonly-tag">
                    Total Records: <strong>{{ $totalClinicalRecords }}</strong>
                </span>
            </div>

            <div class="pp-tabs-wrapper">
                <ul class="nav patient-tabs" id="patientTabs" role="tablist" aria-label="Clinical module records">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link active" data-bs-toggle="tab" href="#tab-lab" role="tab" aria-selected="true">
                            <i class="bi bi-clipboard2-pulse"></i>
                            <span>Laboratory (LIS)</span>
                            <span class="tab-count">{{ $labCount }}</span>
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link" data-bs-toggle="tab" href="#tab-radiology" role="tab" aria-selected="false">
                            <i class="bi bi-activity"></i>
                            <span>Radiology (RIS)</span>
                            <span class="tab-count">{{ $radCount }}</span>
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link" data-bs-toggle="tab" href="#tab-rx" role="tab" aria-selected="false">
                            <i class="bi bi-capsule"></i>
                            <span>Prescriptions (PMS)</span>
                            <span class="tab-count">{{ $rxCount }}</span>
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link" data-bs-toggle="tab" href="#tab-surgery" role="tab" aria-selected="false">
                            <i class="bi bi-heart-pulse"></i>
                            <span>Surgery (SORS)</span>
                            <span class="tab-count">{{ $surCount }}</span>
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link" data-bs-toggle="tab" href="#tab-diet" role="tab" aria-selected="false">
                            <i class="bi bi-apple"></i>
                            <span>Diet (DNMS)</span>
                            <span class="tab-count">{{ $dietCount }}</span>
                        </a>
                    </li>
                </ul>
            </div>

            <div class="tab-content">

                {{-- 1. Laboratory (LIS) --}}
                <div class="tab-pane show active" id="tab-lab" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table clinical-table mb-0">
                            <thead>
                                <tr>
                                    <th>Request No</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($patient->labRequests as $lr)
                                <tr>
                                    <td class="pp-cell-nowrap">
                                        <a href="{{ route('lab.requests.show', $lr) }}" class="pp-code-link">{{ $lr->request_no }}</a>
                                    </td>
                                    <td class="pp-cell-nowrap">
                                        <span class="{{ $priorityClassFor($lr->priority) }}">{{ $lr->priority ?? 'Routine' }}</span>
                                    </td>
                                    <td class="pp-cell-nowrap">
                                        <span class="record-status {{ $statusClassFor($lr->status) }}">
                                            <span class="record-status-dot"></span>
                                            {{ $lr->status }}
                                        </span>
                                    </td>
                                    <td class="pp-cell-nowrap">{{ $lr->requested_at?->format('M d, Y') ?? '—' }}</td>
                                    <td class="pp-cell-nowrap text-end">
                                        <a href="{{ route('lab.requests.show', $lr) }}" class="pp-action-btn" title="View Laboratory Request">
                                            <i class="bi bi-eye"></i>
                                            <span>View</span>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr class="empty-row">
                                    <td colspan="5">
                                        <i class="bi bi-clipboard2-pulse me-1 opacity-50"></i>No laboratory records
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- 2. Radiology (RIS) --}}
                <div class="tab-pane" id="tab-radiology" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table clinical-table mb-0">
                            <thead>
                                <tr>
                                    <th>Request No</th>
                                    <th>Modality / Body Part</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($patient->radiologyRequests as $rr)
                                <tr>
                                    <td class="pp-cell-nowrap">
                                        <a href="{{ route('radiology.requests.show', $rr) }}" class="pp-code-link">{{ $rr->request_no }}</a>
                                    </td>
                                    <td class="pp-cell-nowrap">
                                        <span class="fw-semibold">{{ $rr->modality }}</span>
                                        @if($rr->body_part)
                                            <span class="text-muted small"> &middot; {{ $rr->body_part }}</span>
                                        @endif
                                    </td>
                                    <td class="pp-cell-nowrap">
                                        <span class="{{ $priorityClassFor($rr->priority) }}">{{ $rr->priority ?? 'Routine' }}</span>
                                    </td>
                                    <td class="pp-cell-nowrap">
                                        <span class="record-status {{ $statusClassFor($rr->status) }}">
                                            <span class="record-status-dot"></span>
                                            {{ $rr->status }}
                                        </span>
                                    </td>
                                    <td class="pp-cell-nowrap">{{ $rr->requested_at?->format('M d, Y') ?? '—' }}</td>
                                    <td class="pp-cell-nowrap text-end">
                                        <a href="{{ route('radiology.requests.show', $rr) }}" class="pp-action-btn" title="View Radiology Request">
                                            <i class="bi bi-eye"></i>
                                            <span>View</span>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr class="empty-row">
                                    <td colspan="6">
                                        <i class="bi bi-activity me-1 opacity-50"></i>No radiology records
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- 3. Prescriptions (PMS) --}}
                <div class="tab-pane" id="tab-rx" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table clinical-table mb-0">
                            <thead>
                                <tr>
                                    <th>Rx No</th>
                                    <th>Diagnosis</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($patient->prescriptions as $rx)
                                <tr>
                                    <td class="pp-cell-nowrap">
                                        <a href="{{ route('pharmacy.prescriptions.show', $rx) }}" class="pp-code-link">{{ $rx->prescription_no }}</a>
                                    </td>
                                    <td class="pp-cell-truncate" title="{{ $rx->diagnosis }}">{{ $rx->diagnosis ?: '—' }}</td>
                                    <td class="pp-cell-nowrap">
                                        <span class="record-status {{ $statusClassFor($rx->status) }}">
                                            <span class="record-status-dot"></span>
                                            {{ $rx->status }}
                                        </span>
                                    </td>
                                    <td class="pp-cell-nowrap">{{ $rx->prescribed_at?->format('M d, Y') ?? '—' }}</td>
                                    <td class="pp-cell-nowrap text-end">
                                        <a href="{{ route('pharmacy.prescriptions.show', $rx) }}" class="pp-action-btn" title="View Prescription">
                                            <i class="bi bi-eye"></i>
                                            <span>View</span>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr class="empty-row">
                                    <td colspan="5">
                                        <i class="bi bi-capsule me-1 opacity-50"></i>No prescription records
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- 4. Surgery (SORS) --}}
                <div class="tab-pane" id="tab-surgery" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table clinical-table mb-0">
                            <thead>
                                <tr>
                                    <th>Request No</th>
                                    <th>Procedure</th>
                                    <th>Urgency</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($patient->surgeryRequests as $sr)
                                <tr>
                                    <td class="pp-cell-nowrap">
                                        <a href="{{ route('surgery.requests.show', $sr) }}" class="pp-code-link">{{ $sr->request_no }}</a>
                                    </td>
                                    <td class="pp-cell-truncate" title="{{ $sr->procedure_name }}">{{ $sr->procedure_name }}</td>
                                    <td class="pp-cell-nowrap">
                                        <span class="{{ $priorityClassFor($sr->urgency) }}">{{ $sr->urgency ?? 'Elective' }}</span>
                                    </td>
                                    <td class="pp-cell-nowrap">
                                        <span class="record-status {{ $statusClassFor($sr->status) }}">
                                            <span class="record-status-dot"></span>
                                            {{ $sr->status }}
                                        </span>
                                    </td>
                                    <td class="pp-cell-nowrap">{{ $sr->requested_at?->format('M d, Y') ?? '—' }}</td>
                                    <td class="pp-cell-nowrap text-end">
                                        <a href="{{ route('surgery.requests.show', $sr) }}" class="pp-action-btn" title="View Surgery Request">
                                            <i class="bi bi-eye"></i>
                                            <span>View</span>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr class="empty-row">
                                    <td colspan="6">
                                        <i class="bi bi-heart-pulse me-1 opacity-50"></i>No surgery records
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- 5. Diet (DNMS) --}}
                <div class="tab-pane" id="tab-diet" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table clinical-table mb-0">
                            <thead>
                                <tr>
                                    <th>Request No</th>
                                    <th>Diet Type</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($patient->dietRequests as $dr)
                                <tr>
                                    <td class="pp-cell-nowrap">
                                        <a href="{{ route('diet.requests.show', $dr) }}" class="pp-code-link">{{ $dr->request_no }}</a>
                                    </td>
                                    <td class="pp-cell-nowrap">{{ $dr->diet_type }}</td>
                                    <td class="pp-cell-nowrap">
                                        <span class="record-status {{ $statusClassFor($dr->status) }}">
                                            <span class="record-status-dot"></span>
                                            {{ $dr->status }}
                                        </span>
                                    </td>
                                    <td class="pp-cell-nowrap">{{ $dr->requested_at?->format('M d, Y') ?? '—' }}</td>
                                    <td class="pp-cell-nowrap text-end">
                                        <a href="{{ route('diet.requests.show', $dr) }}" class="pp-action-btn" title="View Diet Request">
                                            <i class="bi bi-eye"></i>
                                            <span>View</span>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr class="empty-row">
                                    <td colspan="5">
                                        <i class="bi bi-apple me-1 opacity-50"></i>No diet records
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>{{-- /tab-content --}}
        </div>{{-- /card --}}

    </div>{{-- /pp-main-grid --}}
</div>{{-- /patient-profile-workspace --}}
@endsection
