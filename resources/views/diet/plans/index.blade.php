@extends('layouts.app')
@section('title', 'Diet Plans & Requests')
@section('page-title', 'Diet Plans & Clinical Nutrition Workspace')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('diet.dashboard') }}">DNMS</a></li>
    <li class="breadcrumb-item active">Diet Plans</li>
@endsection

@push('styles')
<style>
    /* ══════════════════════════════════════════════════════════════════
       DNMS WORKSPACE UI REFINEMENT (SCOPED TO /diet/plans)
    ══════════════════════════════════════════════════════════════════ */
    .dnms-workspace {
        --dnms-teal: #0f766e;
        --dnms-teal-hover: #115e59;
        --dnms-teal-subtle: #f0fdfa;
        --dnms-teal-border: #99f6e4;
        --dnms-surface: #ffffff;
        --dnms-surface-alt: #f8fafc;
        --dnms-border: #e2e8f0;
        --dnms-border-strong: #cbd5e1;
        --dnms-text: #0f172a;
        --dnms-text-secondary: #334155;
        --dnms-text-muted: #64748b;
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
    }

    html[data-theme="dark"] .dnms-workspace,
    html[data-bs-theme="dark"] .dnms-workspace {
        --dnms-teal: #14b8a6;
        --dnms-teal-hover: #0d9488;
        --dnms-teal-subtle: rgba(20, 184, 166, 0.12);
        --dnms-teal-border: rgba(20, 184, 166, 0.35);
        --dnms-surface: #111827;
        --dnms-surface-alt: #1f2937;
        --dnms-border: #374151;
        --dnms-border-strong: #4b5563;
        --dnms-text: #f9fafb;
        --dnms-text-secondary: #e5e7eb;
        --dnms-text-muted: #9ca3af;
    }

    /* ── Top Toolbar: Status Tabs + Primary Actions ── */
    .dnms-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem 1rem;
        background: var(--dnms-surface);
        border: 1px solid var(--dnms-border);
        border-radius: 0.75rem;
        padding: 0.625rem 0.875rem;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    }

    .dnms-tabs-scroll {
        overflow-x: auto;
        max-width: 100%;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
    }

    #dnms-workflow-tabs {
        display: inline-flex;
        flex-wrap: nowrap;
        align-items: center;
        gap: 0.25rem;
        background: var(--dnms-surface-alt);
        border: 1px solid var(--dnms-border);
        border-radius: 0.5rem;
        padding: 0.25rem;
        margin: 0;
    }

    #dnms-workflow-tabs .nav-item {
        flex: 0 0 auto;
    }

    #dnms-workflow-tabs .nav-link {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        padding: 0.4rem 0.75rem;
        border-radius: 0.375rem;
        font-size: 0.8125rem;
        font-weight: 500;
        line-height: 1.25;
        color: var(--dnms-text-secondary) !important;
        background: transparent !important;
        border: 1px solid transparent !important;
        white-space: nowrap;
        transition: all 0.15s ease;
    }

    #dnms-workflow-tabs .nav-link:hover {
        color: var(--dnms-text) !important;
        background: var(--dnms-surface) !important;
    }

    #dnms-workflow-tabs .nav-link.active {
        background: var(--dnms-teal) !important;
        color: #ffffff !important;
        font-weight: 600;
        box-shadow: 0 1px 2px rgba(15, 118, 110, 0.25);
    }

    .dnms-tab-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 1.35rem;
        height: 1.25rem;
        padding: 0 0.4rem !important;
        border-radius: 9999px !important;
        font-size: 0.72rem !important;
        font-weight: 600 !important;
        line-height: 1;
        background: #e2e8f0 !important;
        color: #1e293b !important;
        border: none !important;
    }

    html[data-theme="dark"] .dnms-tab-count {
        background: #374151 !important;
        color: #f3f4f6 !important;
    }

    .dnms-tab-count.dnms-tab-count-pending {
        background: #fef3c7 !important;
        color: #92400e !important;
    }

    #dnms-workflow-tabs .nav-link.active .dnms-tab-count {
        background: rgba(255, 255, 255, 0.22) !important;
        color: #ffffff !important;
    }

    /* ── Primary Workspace Buttons ── */
    .dnms-primary-actions {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .dnms-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.4rem;
        height: 2.25rem;
        padding: 0 0.875rem;
        font-size: 0.8125rem;
        font-weight: 600;
        border-radius: 0.5rem !important;
        white-space: nowrap;
        text-decoration: none;
        transition: all 0.15s ease;
    }

    .dnms-btn-primary {
        background-color: var(--dnms-teal) !important;
        border: 1px solid var(--dnms-teal) !important;
        color: #ffffff !important;
    }

    .dnms-btn-primary:hover,
    .dnms-btn-primary:focus {
        background-color: var(--dnms-teal-hover) !important;
        border-color: var(--dnms-teal-hover) !important;
        color: #ffffff !important;
        box-shadow: 0 2px 6px rgba(15, 118, 110, 0.2) !important;
    }

    .dnms-btn-outline {
        background-color: var(--dnms-surface) !important;
        border: 1px solid var(--dnms-teal) !important;
        color: var(--dnms-teal) !important;
    }

    .dnms-btn-outline:hover,
    .dnms-btn-outline:focus {
        background-color: var(--dnms-teal-subtle) !important;
        border-color: var(--dnms-teal-hover) !important;
        color: var(--dnms-teal-hover) !important;
    }

    /* ── Workspace Cards & Section Headers ── */
    .dnms-workspace .dnms-card {
        background: var(--dnms-surface) !important;
        border: 1px solid var(--dnms-border) !important;
        border-radius: 0.75rem !important;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04) !important;
        overflow: hidden;
    }

    .dnms-workspace .dnms-card-header {
        background: var(--dnms-surface) !important;
        border-bottom: 1px solid var(--dnms-border) !important;
        padding: 0.875rem 1.125rem;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem 1rem;
    }

    .dnms-section-heading {
        display: flex;
        flex-direction: column;
        gap: 0.15rem;
        min-width: 0;
    }

    .dnms-section-title-row {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .dnms-section-title {
        font-size: 0.9375rem;
        font-weight: 600;
        color: var(--dnms-text);
        margin: 0;
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
    }

    .dnms-section-subtitle {
        font-size: 0.78rem;
        color: var(--dnms-text-muted);
        margin: 0;
    }

    /* ── Filter Toolbar inside Diet Plans Card ── */
    .dnms-filter-form {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex-wrap: wrap;
        margin: 0;
    }

    .dnms-search-wrap {
        position: relative;
        width: 250px;
        max-width: 100%;
    }

    .dnms-search-wrap .dnms-search-icon {
        position: absolute;
        left: 0.7rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--dnms-text-muted);
        font-size: 0.82rem;
        pointer-events: none;
    }

    .dnms-workspace #filter-search {
        height: 2.25rem;
        padding: 0.35rem 0.75rem 0.35rem 2rem !important;
        font-size: 0.8125rem;
        border-radius: 0.5rem !important;
        border: 1px solid var(--dnms-border-strong) !important;
        background-color: var(--dnms-surface) !important;
        color: var(--dnms-text) !important;
        width: 100%;
    }

    .dnms-workspace #filter-status {
        height: 2.25rem;
        padding: 0.35rem 2rem 0.35rem 0.75rem !important;
        font-size: 0.8125rem;
        border-radius: 0.5rem !important;
        border: 1px solid var(--dnms-border-strong) !important;
        background-color: var(--dnms-surface) !important;
        color: var(--dnms-text) !important;
        width: 175px;
        max-width: 100%;
    }

    .dnms-workspace #filter-search:focus,
    .dnms-workspace #filter-status:focus {
        border-color: var(--dnms-teal) !important;
        box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.12) !important;
        outline: none;
    }

    .dnms-workspace #filter-clear {
        height: 2.25rem;
        padding: 0 0.75rem;
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        font-size: 0.8125rem;
        font-weight: 500;
        border-radius: 0.5rem !important;
        background: var(--dnms-surface-alt) !important;
        border: 1px solid var(--dnms-border-strong) !important;
        color: var(--dnms-text-secondary) !important;
    }

    .dnms-workspace #filter-clear:hover {
        background: #e2e8f0 !important;
        color: var(--dnms-text) !important;
    }

    /* ── Clinical Tables ── */
    .dnms-workspace .dnms-table {
        margin-bottom: 0;
        border-collapse: collapse;
        width: 100%;
    }

    .dnms-workspace .dnms-table thead th {
        background-color: var(--dnms-surface-alt) !important;
        color: var(--dnms-text-muted) !important;
        font-size: 0.72rem !important;
        font-weight: 600 !important;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        padding: 0.65rem 0.875rem !important;
        border-bottom: 1px solid var(--dnms-border) !important;
        white-space: nowrap !important;
        overflow-wrap: normal !important;
    }

    .dnms-workspace .dnms-table tbody td {
        padding: 0.7rem 0.875rem !important;
        vertical-align: middle !important;
        border-bottom: 1px solid var(--dnms-border) !important;
        color: var(--dnms-text) !important;
        font-size: 0.835rem;
        overflow-wrap: normal !important;
    }

    .dnms-workspace .dnms-table tbody tr:last-child td {
        border-bottom: none !important;
    }

    .dnms-workspace .dnms-table tbody tr:hover td {
        background-color: var(--dnms-surface-alt) !important;
    }

    .dnms-cell-nowrap {
        white-space: nowrap !important;
    }

    .dnms-code-link {
        font-family: var(--font-mono);
        font-size: 0.8125rem;
        font-weight: 600;
        color: var(--dnms-teal) !important;
        text-decoration: none;
        white-space: nowrap;
    }

    .dnms-code-link:hover {
        color: var(--dnms-teal-hover) !important;
        text-decoration: underline;
    }

    .dnms-req-link {
        font-family: var(--font-mono);
        font-size: 0.78rem;
        color: var(--dnms-text-muted) !important;
        text-decoration: none;
        white-space: nowrap;
    }

    .dnms-req-link:hover {
        color: var(--dnms-teal) !important;
        text-decoration: underline;
    }

    .dnms-patient-cell {
        min-width: 165px;
        white-space: nowrap;
    }

    .dnms-patient-name {
        font-weight: 600;
        color: var(--dnms-text);
        line-height: 1.25;
        display: block;
    }

    .dnms-patient-no {
        font-family: var(--font-mono);
        font-size: 0.73rem;
        color: var(--dnms-text-muted);
        display: block;
        margin-top: 0.1rem;
    }

    .dnms-allergies-cell {
        max-width: 260px;
        min-width: 180px;
    }

    .dnms-allergies-summary {
        font-size: 0.78rem;
        line-height: 1.35;
        color: var(--dnms-text-secondary);
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        cursor: help;
    }

    .dnms-calories {
        font-family: var(--font-mono);
        font-weight: 600;
        font-size: 0.82rem;
        color: var(--dnms-text);
        white-space: nowrap;
    }

    .dnms-calories-unit {
        font-weight: 400;
        color: var(--dnms-text-muted);
        font-size: 0.75rem;
    }

    /* ── Semantic Status & Diet Type Badges ── */
    .dnms-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.25rem 0.625rem !important;
        border-radius: 9999px !important;
        font-size: 0.74rem !important;
        font-weight: 600 !important;
        line-height: 1.2;
        white-space: nowrap;
    }

    .dnms-badge-dot {
        width: 0.4rem;
        height: 0.4rem;
        border-radius: 9999px;
        flex-shrink: 0;
    }

    .dnms-badge-diet {
        background-color: var(--dnms-teal-subtle) !important;
        color: var(--dnms-teal) !important;
        border: 1px solid var(--dnms-teal-border) !important;
    }

    .dnms-badge-Pending {
        background-color: #fffbeb !important;
        color: #b45309 !important;
        border: 1px solid #fde68a !important;
    }
    .dnms-badge-Pending .dnms-badge-dot { background-color: #d97706; }

    .dnms-badge-Active {
        background-color: #f0fdf4 !important;
        color: #15803d !important;
        border: 1px solid #bbf7d0 !important;
    }
    .dnms-badge-Active .dnms-badge-dot { background-color: #16a34a; }

    .dnms-badge-Revised {
        background-color: #eff6ff !important;
        color: #1d4ed8 !important;
        border: 1px solid #bfdbfe !important;
    }
    .dnms-badge-Revised .dnms-badge-dot { background-color: #2563eb; }

    .dnms-badge-Completed {
        background-color: #ecfdf5 !important;
        color: #047857 !important;
        border: 1px solid #a7f3d0 !important;
    }
    .dnms-badge-Completed .dnms-badge-dot { background-color: #059669; }

    .dnms-badge-Cancelled {
        background-color: #fef2f2 !important;
        color: #b91c1c !important;
        border: 1px solid #fecaca !important;
    }
    .dnms-badge-Cancelled .dnms-badge-dot { background-color: #dc2626; }

    html[data-theme="dark"] .dnms-badge-Pending {
        background-color: rgba(180, 83, 9, 0.2) !important;
        color: #fcd34d !important;
        border-color: rgba(245, 158, 11, 0.35) !important;
    }
    html[data-theme="dark"] .dnms-badge-Active,
    html[data-theme="dark"] .dnms-badge-Completed {
        background-color: rgba(21, 128, 61, 0.22) !important;
        color: #86efac !important;
        border-color: rgba(34, 197, 94, 0.35) !important;
    }
    html[data-theme="dark"] .dnms-badge-Revised {
        background-color: rgba(29, 78, 216, 0.22) !important;
        color: #93c5fd !important;
        border-color: rgba(59, 130, 246, 0.35) !important;
    }
    html[data-theme="dark"] .dnms-badge-Cancelled {
        background-color: rgba(185, 28, 28, 0.22) !important;
        color: #fca5a5 !important;
        border-color: rgba(239, 68, 68, 0.35) !important;
    }

    /* ── Table Action Buttons ── */
    .dnms-workspace .table-actions {
        display: inline-flex;
        align-items: center;
        justify-content: flex-end;
        gap: 0.35rem;
        white-space: nowrap;
    }

    .dnms-workspace .table-action-btn {
        width: 1.875rem;
        height: 1.875rem;
        padding: 0 !important;
        border: 1px solid var(--dnms-border) !important;
        background: var(--dnms-surface) !important;
        color: var(--dnms-text-secondary) !important;
        border-radius: 0.375rem !important;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.85rem;
        transition: all 0.15s ease;
    }

    .dnms-workspace .table-action-btn:hover,
    .dnms-workspace .table-action-btn:focus {
        border-color: var(--dnms-teal) !important;
        background: var(--dnms-teal-subtle) !important;
        color: var(--dnms-teal) !important;
    }

    .dnms-btn-create-plan {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        height: 1.875rem;
        padding: 0 0.65rem !important;
        font-size: 0.76rem !important;
        font-weight: 600 !important;
        border-radius: 0.375rem !important;
        background-color: var(--dnms-teal) !important;
        border: 1px solid var(--dnms-teal) !important;
        color: #ffffff !important;
        text-decoration: none;
        white-space: nowrap;
    }

    .dnms-btn-create-plan:hover,
    .dnms-btn-create-plan:focus {
        background-color: var(--dnms-teal-hover) !important;
        border-color: var(--dnms-teal-hover) !important;
        color: #ffffff !important;
    }

    /* ── Responsive Adjustments ── */
    @media (max-width: 991.98px) {
        .dnms-toolbar {
            flex-direction: column;
            align-items: stretch;
        }
        .dnms-primary-actions {
            width: 100%;
            justify-content: flex-start;
        }
        .dnms-workspace .dnms-card-header {
            flex-direction: column;
            align-items: stretch;
        }
        .dnms-filter-form {
            width: 100%;
        }
        .dnms-search-wrap {
            flex: 1 1 200px;
            width: auto;
        }
    }

    @media (max-width: 575.98px) {
        .dnms-primary-actions {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .dnms-primary-actions .dnms-btn {
            width: 100%;
        }
        .dnms-filter-form {
            flex-direction: column;
            align-items: stretch;
        }
        .dnms-search-wrap,
        .dnms-workspace #filter-status,
        .dnms-workspace #filter-clear {
            width: 100%;
        }
    }
</style>
@endpush

@section('content')
@php
    $activeStatus = request('status', '');
    $activeTab    = request('tab', '');
    $pendingFallbackCount = isset($pendingRequests) ? $pendingRequests->count() : 0;
    $counts       = $tabCounts ?? ['all' => $plans->total() + $pendingFallbackCount, 'pending' => $pendingFallbackCount, 'Active' => 0, 'Revised' => 0, 'Completed' => 0];
@endphp

<div class="dnms-workspace">
    {{-- ── Workflow Summary Tabs & Primary Actions Toolbar ── --}}
    <div class="dnms-toolbar">
        <div class="dnms-tabs-scroll">
            <ul class="nav nav-pills" id="dnms-workflow-tabs" role="tablist" aria-label="Diet workflow status filters">
                <li class="nav-item" role="presentation">
                    <a class="nav-link {{ $activeStatus === '' && $activeTab !== 'pending' ? 'active' : '' }}"
                       href="{{ route('diet.plans.index') }}">
                        <i class="bi bi-layers"></i>
                        <span>All Plans &amp; Requests</span>
                        <span class="badge dnms-tab-count">{{ $counts['all'] }}</span>
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link {{ $activeTab === 'pending' ? 'active' : '' }}"
                       href="{{ route('diet.plans.index', ['tab' => 'pending']) }}#pending-requests-section">
                        <i class="bi bi-hourglass-split"></i>
                        <span>Pending Requests</span>
                        <span class="badge dnms-tab-count {{ $counts['pending'] > 0 ? 'dnms-tab-count-pending' : '' }}">{{ $counts['pending'] }}</span>
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link {{ $activeStatus === 'Active' ? 'active' : '' }}"
                       href="{{ route('diet.plans.index', ['status' => 'Active']) }}">
                        <i class="bi bi-check2-circle"></i>
                        <span>Active Plans</span>
                        <span class="badge dnms-tab-count">{{ $counts['Active'] }}</span>
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link {{ $activeStatus === 'Revised' ? 'active' : '' }}"
                       href="{{ route('diet.plans.index', ['status' => 'Revised']) }}">
                        <i class="bi bi-arrow-repeat"></i>
                        <span>Revised Plans</span>
                        <span class="badge dnms-tab-count">{{ $counts['Revised'] }}</span>
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link {{ $activeStatus === 'Completed' ? 'active' : '' }}"
                       href="{{ route('diet.plans.index', ['status' => 'Completed']) }}">
                        <i class="bi bi-archive"></i>
                        <span>Completed Plans</span>
                        <span class="badge dnms-tab-count">{{ $counts['Completed'] }}</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="dnms-primary-actions">
            @can('create', \App\Models\DietRequest::class)
                <a href="{{ route('diet.requests.create') }}" class="btn dnms-btn dnms-btn-outline">
                    <i class="bi bi-journal-plus"></i>
                    <span>New Diet Request</span>
                </a>
            @endcan
            @can('create', \App\Models\DietPlan::class)
                <a href="{{ route('diet.plans.create') }}" class="btn dnms-btn dnms-btn-primary">
                    <i class="bi bi-plus-circle"></i>
                    <span>New Diet Plan</span>
                </a>
            @endcan
        </div>
    </div>

    {{-- ── Section 1: Pending Diet Requests Awaiting a Diet Plan ── --}}
    @if($activeStatus === '' || $activeTab === 'pending')
    <div class="card dnms-card" id="pending-requests-section">
        <div class="card-header dnms-card-header">
            <div class="dnms-section-heading">
                <div class="dnms-section-title-row">
                    <h2 class="dnms-section-title">
                        <i class="bi bi-hourglass-split" style="color: #d97706;"></i>
                        <span>Pending Requests Awaiting Diet Plan</span>
                    </h2>
                    <span class="dnms-badge dnms-badge-Pending">{{ isset($pendingRequests) ? $pendingRequests->count() : 0 }}</span>
                </div>
                <p class="dnms-section-subtitle">Select an eligible pending request below to record a nutrition assessment and create a therapeutic diet plan.</p>
            </div>
        </div>
        <div class="card-body p-0">
            @if(isset($pendingRequests) && $pendingRequests->isNotEmpty())
                <div class="table-responsive">
                    <table class="table table-hover align-middle dnms-table">
                        <thead>
                            <tr>
                                <th>Request No</th>
                                <th>Patient</th>
                                <th>Doctor</th>
                                <th>Diet Type</th>
                                <th>Allergies &amp; Restrictions</th>
                                <th>Status</th>
                                <th>Requested At</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pendingRequests as $req)
                            @php
                                $allergyTooltip = trim(
                                    ($req->allergies ? 'Allergies: ' . $req->allergies : '') .
                                    ($req->allergies && $req->food_restrictions ? ' | ' : '') .
                                    ($req->food_restrictions ? 'Restrictions: ' . $req->food_restrictions : '')
                                );
                            @endphp
                            <tr>
                                <td class="dnms-cell-nowrap">
                                    <a href="{{ route('diet.requests.show', $req) }}" class="dnms-code-link">{{ $req->request_no }}</a>
                                </td>
                                <td class="dnms-patient-cell">
                                    <span class="dnms-patient-name">{{ $req->patient->last_name ?? '—' }}, {{ $req->patient->first_name ?? '' }}</span>
                                    <span class="dnms-patient-no">{{ $req->patient->patient_no ?? '—' }}</span>
                                </td>
                                <td class="dnms-cell-nowrap">{{ $req->doctor->name ?? '—' }}</td>
                                <td class="dnms-cell-nowrap">
                                    <span class="dnms-badge dnms-badge-diet">{{ $req->diet_type }}</span>
                                </td>
                                <td class="dnms-allergies-cell">
                                    @if($req->allergies || $req->food_restrictions)
                                        <div class="dnms-allergies-summary" title="{{ $allergyTooltip }}" tabindex="0" aria-label="{{ $allergyTooltip }}">
                                            @if($req->allergies)
                                                <strong class="text-danger">Allergies:</strong> {{ $req->allergies }}
                                            @endif
                                            @if($req->allergies && $req->food_restrictions)
                                                <span class="text-muted mx-1">&bull;</span>
                                            @endif
                                            @if($req->food_restrictions)
                                                <strong>Restrictions:</strong> {{ $req->food_restrictions }}
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-muted small">None recorded</span>
                                    @endif
                                </td>
                                <td class="dnms-cell-nowrap">
                                    <span class="dnms-badge dnms-badge-{{ $req->status }}">
                                        <span class="dnms-badge-dot"></span>
                                        {{ $req->status }}
                                    </span>
                                </td>
                                <td class="dnms-cell-nowrap">
                                    <span class="text-muted small">{{ $req->requested_at?->format('M d, Y H:i') }}</span>
                                </td>
                                <td class="dnms-cell-nowrap text-end">
                                    <div class="table-actions">
                                        @can('create', [\App\Models\DietPlan::class, $req])
                                            <a href="{{ route('diet.plans.create', ['diet_request_id' => $req->id]) }}"
                                               class="dnms-btn-create-plan"
                                               title="Create Diet Plan for {{ $req->request_no }}"
                                               aria-label="Create Diet Plan for {{ $req->request_no }}">
                                                <i class="bi bi-file-earmark-plus"></i>
                                                <span>Create Plan</span>
                                            </a>
                                        @endcan
                                        <a href="{{ route('diet.requests.show', $req) }}"
                                           class="table-action-btn action-view"
                                           title="View Request Details"
                                           aria-label="View Request Details">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        @can('update', $req)
                                            <a href="{{ route('diet.requests.edit', $req) }}"
                                               class="table-action-btn action-edit"
                                               title="Edit Pending Request"
                                               aria-label="Edit Pending Request">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="p-4 text-center text-muted">
                    <i class="bi bi-check2-all fs-4 d-block mb-1" style="color: var(--dnms-teal);"></i>
                    No pending diet requests awaiting a diet plan.
                </div>
            @endif
        </div>
    </div>
    @endif

    {{-- ── Section 2: Therapeutic Diet Plans (Active, Revised & Completed) ── --}}
    <div class="card dnms-card">
        <div class="card-header dnms-card-header">
            <div class="dnms-section-heading">
                <div class="dnms-section-title-row">
                    <h2 class="dnms-section-title">
                        <i class="bi bi-clipboard2-heart" style="color: var(--dnms-teal);"></i>
                        <span>
                            @if($activeStatus === 'Active')
                                Active Diet Plans
                            @elseif($activeStatus === 'Revised')
                                Revised Diet Plans
                            @elseif($activeStatus === 'Completed')
                                Completed Diet Plans &amp; History
                            @else
                                Therapeutic Diet Plans (Active, Revised &amp; Completed)
                            @endif
                        </span>
                    </h2>
                </div>
            </div>

            {{-- Search & Status Filters --}}
            <form class="dnms-filter-form" method="GET" id="filter-form" role="search" aria-label="Filter therapeutic diet plans">
                <div class="dnms-search-wrap">
                    <i class="bi bi-search dnms-search-icon"></i>
                    <input type="text"
                           name="search"
                           id="filter-search"
                           class="form-control form-control-sm"
                           placeholder="Search plan/request no, patient…"
                           value="{{ request('search') }}"
                           aria-label="Search plan or request number or patient">
                </div>
                <select name="status"
                        id="filter-status"
                        class="form-select form-select-sm"
                        aria-label="Filter by plan status">
                    <option value="">All Plan Statuses</option>
                    <option value="Active" {{ request('status') === 'Active' ? 'selected' : '' }}>Active Plans</option>
                    <option value="Revised" {{ request('status') === 'Revised' ? 'selected' : '' }}>Revised Plans</option>
                    <option value="Completed" {{ request('status') === 'Completed' ? 'selected' : '' }}>Completed Plans</option>
                </select>
                <button class="btn btn-primary btn-sm d-none" type="submit">Filter</button>
                @if(request()->hasAny(['search','status','tab']))
                    <a href="{{ route('diet.plans.index') }}" class="btn btn-outline-secondary btn-sm" id="filter-clear" title="Clear active filters">
                        <i class="bi bi-x-lg"></i>
                        <span>Clear</span>
                    </a>
                @endif
            </form>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle dnms-table">
                    <thead>
                        <tr>
                            <th>Plan No</th>
                            <th>Request No</th>
                            <th>Patient</th>
                            <th>Diet Type</th>
                            <th>Created By</th>
                            <th>Daily Calories</th>
                            <th>Duration</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="diet-plans-table-body">
                    @forelse($plans as $plan)
                    <tr>
                        <td class="dnms-cell-nowrap">
                            <a href="{{ route('diet.plans.show', $plan) }}" class="dnms-code-link">{{ $plan->plan_no }}</a>
                        </td>
                        <td class="dnms-cell-nowrap">
                            @if($plan->dietRequest)
                                <a href="{{ route('diet.requests.show', $plan->dietRequest) }}" class="dnms-req-link">{{ $plan->dietRequest->request_no }}</a>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td class="dnms-patient-cell">
                            <span class="dnms-patient-name">{{ $plan->dietRequest->patient->last_name ?? '—' }}, {{ $plan->dietRequest->patient->first_name ?? '' }}</span>
                            <span class="dnms-patient-no">{{ $plan->dietRequest->patient->patient_no ?? '—' }}</span>
                        </td>
                        <td class="dnms-cell-nowrap">
                            <span class="dnms-badge dnms-badge-diet">{{ $plan->dietRequest->diet_type ?? '—' }}</span>
                        </td>
                        <td class="dnms-cell-nowrap">
                            {{ $plan->createdBy->name ?? ($plan->dietitian->name ?? ($plan->dietRequest->doctor->name ?? '—')) }}
                        </td>
                        <td class="dnms-cell-nowrap">
                            <span class="dnms-calories">
                                {{ $plan->total_calories ?? '—' }}
                                <span class="dnms-calories-unit">kcal</span>
                            </span>
                        </td>
                        <td class="dnms-cell-nowrap">
                            <span class="text-muted small">
                                {{ $plan->start_date?->format('M d, Y') }} &mdash;
                                {{ $plan->end_date ? $plan->end_date->format('M d, Y') : 'Ongoing' }}
                            </span>
                        </td>
                        <td class="dnms-cell-nowrap">
                            <span class="dnms-badge dnms-badge-{{ $plan->status }}">
                                <span class="dnms-badge-dot"></span>
                                {{ $plan->status }}
                            </span>
                        </td>
                        <td class="dnms-cell-nowrap text-end">
                            <div class="table-actions">
                                <a href="{{ route('diet.plans.show', $plan) }}"
                                   class="table-action-btn action-view"
                                   title="View Diet Plan Details"
                                   aria-label="View Diet Plan Details">
                                    <i class="bi bi-eye"></i>
                                </a>
                                @can('update', $plan)
                                    <a href="{{ route('diet.plans.edit', $plan) }}"
                                       class="table-action-btn action-edit"
                                       title="Edit / Revise Diet Plan"
                                       aria-label="Edit / Revise Diet Plan">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                @endcan
                                <a href="{{ route('diet.plans.print', $plan) }}"
                                   target="_blank"
                                   class="table-action-btn action-view"
                                   title="Print Diet Plan"
                                   aria-label="Print Diet Plan">
                                    <i class="bi bi-printer"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center py-4 text-muted">
                            No diet plans found matching the current filter.
                        </td>
                    </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div id="diet-plans-pagination-container">
            @if($plans->hasPages())
                <div class="card-footer">{{ $plans->links() }}</div>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const searchInput = document.getElementById('filter-search');
        const statusSelect = document.getElementById('filter-status');
        const filterForm = document.getElementById('filter-form');
        
        let searchTimeout;

        function performFilter(resetPage = true) {
            if (resetPage) {
                let pageInput = filterForm.querySelector('input[name="page"]');
                if (pageInput) {
                    pageInput.value = '1';
                }
            }
            const formData = new FormData(filterForm);
            const params = new URLSearchParams(formData);
            const newUrl = `${window.location.pathname}?${params.toString()}`;
            
            window.history.replaceState(null, '', newUrl);

            fetch(newUrl, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                
                document.getElementById('diet-plans-table-body').innerHTML = doc.getElementById('diet-plans-table-body').innerHTML;
                document.getElementById('diet-plans-pagination-container').innerHTML = doc.getElementById('diet-plans-pagination-container').innerHTML;
                
                const clearBtn = document.getElementById('filter-clear');
                const newClearBtn = doc.getElementById('filter-clear');
                if (clearBtn && !newClearBtn) {
                    clearBtn.remove();
                } else if (!clearBtn && newClearBtn) {
                    filterForm.appendChild(newClearBtn);
                    setupClearListener();
                }
            })
            .catch(err => console.error('Error filtering diet plans:', err));
        }

        function setupClearListener() {
            const clearBtn = document.getElementById('filter-clear');
            if (clearBtn) {
                clearBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    searchInput.value = '';
                    statusSelect.value = '';
                    performFilter(true);
                });
            }
        }

        filterForm.addEventListener('submit', function (e) {
            e.preventDefault();
            performFilter(true);
        });

        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => performFilter(true), 300);
        });

        statusSelect.addEventListener('change', () => performFilter(true));
        setupClearListener();

        document.addEventListener('click', function (e) {
            const pageLink = e.target.closest('#diet-plans-pagination-container a');
            if (pageLink) {
                e.preventDefault();
                const urlObj = new URL(pageLink.href);
                const page = urlObj.searchParams.get('page');
                
                let pageInput = filterForm.querySelector('input[name="page"]');
                if (!pageInput) {
                    pageInput = document.createElement('input');
                    pageInput.type = 'hidden';
                    pageInput.name = 'page';
                    filterForm.appendChild(pageInput);
                }
                pageInput.value = page;
                performFilter(false);
            }
        });
    });
</script>
@endpush
@endsection
