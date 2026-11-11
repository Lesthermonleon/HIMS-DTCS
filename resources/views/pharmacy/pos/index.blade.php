@extends('layouts.app')

@section('title', 'Pharmacy Point of Sale (POS) — DTCS HIMS')

@php
    // Prepare preloaded cart items if a prescription was linked via ?prescription_id=...
    $initialCart = [];
    if ($linkedPrescription && $linkedPrescription->items->isNotEmpty()) {
        foreach ($linkedPrescription->items as $rxItem) {
            $matched = $catalog->first(function ($c) use ($rxItem) {
                return strcasecmp($c['medication_name'], $rxItem->medication_name) === 0
                    || stripos($c['medication_name'], $rxItem->medication_name) !== false;
            });
            if ($matched && $matched['can_sell']) {
                $qtyToBill = max(1, min((int) ($rxItem->remaining_quantity ?: $rxItem->quantity), (int) $matched['available_quantity']));
                $initialCart[] = [
                    'cartItemId'           => $matched['code'] . '-' . time() . '-' . rand(100, 999),
                    'code'                 => $matched['code'],
                    'medication_name'      => $matched['medication_name'],
                    'generic_name'         => $matched['generic_name'],
                    'brand_name'           => $matched['brand_name'],
                    'strength'             => $matched['strength'],
                    'dosage_form'          => $matched['dosage_form'],
                    'unit_price'           => (float) $matched['unit_price'],
                    'available_quantity'   => (int) $matched['available_quantity'],
                    'qty'                  => $qtyToBill,
                    'prescription_item_id' => $rxItem->id,
                ];
            }
        }
    }

    $currentPatientId    = $linkedPrescription ? $linkedPrescription->patient_id : old('patient_id');
    $currentPatientType  = $linkedPrescription ? ($linkedPrescription->patient?->patient_type ?? 'Inpatient') : old('patient_type');
    $currentCustomerName = $linkedPrescription ? ($linkedPrescription->patient?->full_name ?? 'Walk-In Customer') : old('customer_name', '');
    $selectedPatient     = $currentPatientId ? $patients->firstWhere('id', $currentPatientId) : null;
@endphp

@push('styles')
<style>
    /* ══════════════════════════════════════════════════════════════════
       DTCS PHARMACY POS WORKSPACE — DESIGN SYSTEM TOKENS
       Scoped to .dtcs-pos-workspace (Supports Light & Dark Modes)
    ══════════════════════════════════════════════════════════════════ */
    .dtcs-pos-workspace {
        --pos-primary: #0f766e;        /* DTCS deep teal */
        --pos-primary-hover: #115e59;
        --pos-primary-dark: #0f5265;   /* Deep teal/slate */
        --pos-primary-light: #f0fdfa;  /* Very soft teal tint */
        --pos-accent: #0284c7;         /* DTCS deep blue accent */
        --pos-accent-hover: #0369a1;

        --pos-shell: #f8fafc;
        --pos-surface: #ffffff;
        --pos-surface-soft: #f8fafc;
        --pos-surface-card: #ffffff;
        --pos-border: #e2e8f0;
        --pos-border-subtle: #edf2f7;

        --pos-ink: #0f172a;            /* Slate 900 text */
        --pos-ink-secondary: #334155;  /* Slate 700 */
        --pos-muted: #64748b;          /* Slate 500 */

        --pos-success: #15803d;        /* Muted green */
        --pos-success-bg: #f0fdf4;
        --pos-success-border: #bbf7d0;

        --pos-warning: #b45309;        /* Muted amber */
        --pos-warning-bg: #fffbeb;
        --pos-warning-border: #fde68a;

        --pos-danger: #dc2626;         /* Crimson error */
        --pos-danger-bg: #fef2f2;
        --pos-danger-border: #fecaca;

        --pos-radius: 10px;
        --pos-radius-sm: 7px;
        --pos-shadow: 0 1px 3px rgba(15, 23, 42, 0.05), 0 1px 2px rgba(15, 23, 42, 0.03);
        --pos-shadow-md: 0 4px 6px -1px rgba(15, 23, 42, 0.08), 0 2px 4px -2px rgba(15, 23, 42, 0.04);
        display: flex;
        flex-direction: column;
        gap: 12px;
        color: var(--pos-ink);
    }

    /* Dark Mode Overrides (Strictly dark/charcoal/black surfaces, zero green backgrounds) */
    html[data-theme="dark"] .dtcs-pos-workspace,
    html[data-bs-theme="dark"] .dtcs-pos-workspace {
        --pos-primary: #14b8a6;
        --pos-primary-hover: #2dd4bf;
        --pos-primary-dark: #0f766e;
        --pos-primary-light: rgba(20, 184, 166, 0.12);
        --pos-accent: #38bdf8;
        --pos-accent-hover: #7dd3fc;

        --pos-shell: #090d16;
        --pos-surface: #111827;         /* Deep slate/charcoal */
        --pos-surface-soft: #1f2937;    /* Charcoal soft */
        --pos-surface-card: #111827;
        --pos-border: #374151;
        --pos-border-subtle: #2d3748;

        --pos-ink: #f9fafb;             /* Crisp light text */
        --pos-ink-secondary: #e5e7eb;
        --pos-muted: #9ca3af;

        --pos-success: #4ade80;
        --pos-success-bg: rgba(22, 163, 74, 0.16);
        --pos-success-border: rgba(34, 197, 94, 0.35);

        --pos-warning: #fbbf24;
        --pos-warning-bg: rgba(217, 119, 6, 0.16);
        --pos-warning-border: rgba(245, 158, 11, 0.35);

        --pos-danger: #f87171;
        --pos-danger-bg: rgba(220, 38, 38, 0.16);
        --pos-danger-border: rgba(239, 68, 68, 0.35);

        --pos-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
        --pos-shadow-md: 0 4px 12px rgba(0, 0, 0, 0.4);
    }

    /* ── Top Bar (Integrated into #page-titlebar) ── */
    #page-titlebar:has(.pos-top-bar),
    #page-titlebar.has-custom-titlebar {
        height: auto;
        min-height: 54px;
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
        padding: 0.45rem 1.25rem;
    }

    #page-titlebar .pos-top-bar {
        background: transparent !important;
        border: none !important;
        border-radius: 0 !important;
        padding: 0 !important;
        box-shadow: none !important;
        width: 100%;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
    }

    /* ── Main POS Layout Grid ── */
    .dtcs-pos-shell {
        width: 100%;
        min-height: 700px;
        display: grid;
        grid-template-columns: minmax(0, 1.85fr) minmax(320px, 0.95fr) 66px;
        gap: 12px;
        align-items: stretch;
    }

    .pos-catalog-panel,
    .pos-order-panel {
        min-height: 0;
        background: var(--pos-surface);
        border: 1px solid var(--pos-border);
        border-radius: var(--pos-radius);
        box-shadow: var(--pos-shadow);
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    /* ── Catalog Header & Navigation ── */
    .pos-catalog-header {
        min-height: 55px;
        padding: 12px 18px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 1px solid var(--pos-border);
        gap: 12px;
        background: var(--pos-surface);
    }

    .pos-catalog-title {
        font-size: 15px;
        font-weight: 800;
        color: var(--pos-ink);
        letter-spacing: -0.01em;
    }

    .pos-catalog-subtitle {
        color: var(--pos-muted);
        font-size: 11px;
        margin-top: 2px;
    }

    .pos-terminal-badge {
        padding: 4px 9px;
        border-radius: 20px;
        background: var(--pos-primary-light);
        color: var(--pos-primary);
        border: 1px solid var(--pos-border);
        font-size: 11px;
        font-weight: 700;
        font-family: 'IBM Plex Mono', monospace;
    }

    .pos-category-bar {
        min-height: 48px;
        display: flex;
        align-items: center;
        gap: 20px;
        padding: 0 18px;
        background: var(--pos-surface);
        border-bottom: 1px solid var(--pos-border);
        overflow-x: auto;
        white-space: nowrap;
    }

    .pos-category-btn {
        position: relative;
        flex-shrink: 0;
        border: 0;
        padding: 13px 0;
        background: transparent;
        color: var(--pos-muted);
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        cursor: pointer;
        transition: color 0.15s ease;
    }

    .pos-category-btn:hover {
        color: var(--pos-primary);
    }

    .pos-category-btn.active {
        color: var(--pos-primary);
        font-weight: 800;
    }

    .pos-category-btn.active::after {
        content: "";
        position: absolute;
        left: 0;
        right: 0;
        bottom: 0;
        height: 3px;
        border-radius: 3px 3px 0 0;
        background: var(--pos-primary);
    }

    .pos-catalog-body {
        padding: 16px;
        overflow-y: auto;
        flex: 1;
        min-height: 0;
    }

    /* ── Search Toolbar ── */
    .pos-search-toolbar {
        display: flex;
        gap: 10px;
        margin-bottom: 16px;
    }

    .pos-barcode-btn {
        min-width: 96px;
        padding: 0 12px;
        border: 1px solid var(--pos-border);
        border-radius: var(--pos-radius-sm);
        background: var(--pos-surface);
        color: var(--pos-primary);
        font-size: 12px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        cursor: pointer;
        transition: all 0.15s ease;
    }

    .pos-barcode-btn:hover {
        background: var(--pos-primary-light);
        border-color: var(--pos-primary);
    }

    .pos-search-wrap {
        position: relative;
        flex: 1;
    }

    .pos-search-icon {
        position: absolute;
        left: 13px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--pos-muted);
        font-size: 14px;
        pointer-events: none;
    }

    .pos-search-input {
        width: 100%;
        height: 40px;
        padding: 8px 12px 8px 36px;
        border: 1px solid var(--pos-border);
        border-radius: var(--pos-radius-sm);
        outline: none;
        background: var(--pos-surface-soft);
        color: var(--pos-ink);
        font-size: 12px;
        transition: all 0.15s ease;
    }

    .pos-search-input:focus {
        border-color: var(--pos-primary);
        background: var(--pos-surface);
        box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.12);
    }

    /* ── Product Grid ── */
    .pos-product-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
    }

    .pos-product-card {
        min-width: 0;
        min-height: 155px;
        position: relative;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        align-items: stretch;
        padding: 13px;
        border: 1px solid var(--pos-border);
        border-radius: 9px;
        background: var(--pos-surface);
        color: var(--pos-ink);
        text-align: left;
        cursor: pointer;
        transition: all 0.16s ease;
    }

    .pos-product-card:hover:not(:disabled) {
        border-color: var(--pos-primary);
        box-shadow: var(--pos-shadow-md);
        transform: translateY(-2px);
    }

    .pos-product-card:disabled {
        background: var(--pos-surface-soft);
        opacity: 0.65;
        cursor: not-allowed;
    }

    .pos-product-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 6px;
    }

    .pos-product-icon {
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        background: var(--pos-primary-light);
        color: var(--pos-primary);
        font-size: 16px;
    }

    .pos-stock-badge {
        padding: 3px 8px;
        border-radius: 20px;
        font-size: 10px;
        font-weight: 700;
        white-space: nowrap;
        font-family: 'IBM Plex Mono', monospace;
    }

    .pos-stock-good {
        color: var(--pos-success);
        background: var(--pos-success-bg);
        border: 1px solid var(--pos-success-border);
    }

    .pos-stock-low {
        color: var(--pos-warning);
        background: var(--pos-warning-bg);
        border: 1px solid var(--pos-warning-border);
    }

    .pos-stock-out {
        color: var(--pos-danger);
        background: var(--pos-danger-bg);
        border: 1px solid var(--pos-danger-border);
    }

    .pos-product-name {
        display: block;
        margin-top: 8px;
        font-size: 13px;
        line-height: 1.35;
        font-weight: 700;
        color: var(--pos-ink);
        overflow-wrap: anywhere;
    }

    .pos-product-strength {
        margin-top: 2px;
        color: var(--pos-muted);
        font-size: 11px;
    }

    .pos-product-bottom {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 6px;
        margin-top: 12px;
    }

    .pos-product-price {
        color: var(--pos-primary);
        font-size: 13px;
        font-weight: 800;
    }

    .pos-product-add {
        width: 24px;
        height: 24px;
        border-radius: 6px;
        background: var(--pos-primary-light);
        color: var(--pos-primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        font-weight: 800;
    }

    /* ── Order Panel (Right) ── */
    .pos-order-heading {
        min-height: 55px;
        padding: 12px 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 1px solid var(--pos-border);
        background: var(--pos-surface);
    }

    .pos-order-heading strong {
        color: var(--pos-ink);
        font-size: 14px;
        font-weight: 800;
    }

    .pos-order-items {
        flex: 1 1 0;
        min-height: 200px;
        max-height: 380px;
        overflow-y: auto;
        padding: 12px 16px;
    }

    .pos-order-item {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 8px;
        padding-bottom: 12px;
        margin-bottom: 12px;
        border-bottom: 1px solid var(--pos-border-subtle);
    }

    .pos-order-item-heading {
        display: flex;
        align-items: center;
        gap: 6px;
        min-width: 0;
    }

    .pos-order-item strong {
        display: block;
        min-width: 0;
        color: var(--pos-ink);
        font-size: 12px;
        font-weight: 700;
        overflow-wrap: anywhere;
    }

    .pos-order-item small {
        display: block;
        margin-top: 4px;
        color: var(--pos-muted);
        font-size: 11px;
    }

    .pos-edit-item-btn {
        flex-shrink: 0;
        width: 24px;
        height: 24px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid var(--pos-border);
        border-radius: 6px;
        background: var(--pos-surface);
        color: var(--pos-primary);
        cursor: pointer;
        transition: all 0.15s ease;
    }

    .pos-edit-item-btn:hover {
        background: var(--pos-primary-light);
        border-color: var(--pos-primary);
    }

    .pos-qty-stepper-btn {
        width: 24px;
        height: 24px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid var(--pos-border);
        border-radius: 5px;
        background: var(--pos-surface);
        color: var(--pos-ink);
        font-size: 13px;
        font-weight: 700;
        line-height: 1;
        cursor: pointer;
        padding: 0;
        transition: all 0.15s ease;
    }

    .pos-qty-stepper-btn:hover {
        background: var(--pos-primary-light);
        border-color: var(--pos-primary);
        color: var(--pos-primary);
    }

    .pos-qty-badge {
        display: inline-block;
        min-width: 24px;
        text-align: center;
        font-weight: 700;
        font-size: 12px;
        color: var(--pos-ink);
    }

    .pos-item-price {
        align-self: center;
        color: var(--pos-ink);
        font-size: 12px;
        font-weight: 800;
        white-space: nowrap;
    }

    .pos-summary {
        flex-shrink: 0;
        padding: 12px 16px;
        border-top: 1px solid var(--pos-border);
        background: var(--pos-surface-soft);
    }

    .pos-summary-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        margin: 6px 0;
        color: var(--pos-muted);
        font-size: 12px;
    }

    .pos-summary-row strong {
        color: var(--pos-ink);
        font-weight: 700;
    }

    .pos-total-row {
        flex-shrink: 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 16px;
        background: var(--pos-primary-light);
        color: var(--pos-primary);
        font-size: 14px;
        font-weight: 800;
        border-top: 1px solid var(--pos-border);
    }

    .pos-total-row span:last-child {
        font-size: 18px;
    }

    .pos-pay-wrap {
        flex-shrink: 0;
        padding: 12px 16px 16px;
        background: var(--pos-surface-soft);
    }

    .pos-pay-btn {
        width: 100%;
        min-height: 44px;
        border: 0;
        border-radius: var(--pos-radius-sm);
        background: var(--pos-primary);
        color: #ffffff;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: 0.3px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        cursor: pointer;
        transition: all 0.15s ease;
    }

    .pos-pay-btn:hover {
        background: var(--pos-primary-hover);
    }

    /* ── Action Toolbar (Far Right) ── */
    .dtcs-action-tabs {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .dtcs-action-btn {
        min-height: 62px;
        padding: 8px 3px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 5px;
        border: 1px solid var(--pos-border);
        border-radius: 9px;
        background: var(--pos-surface);
        color: var(--pos-primary);
        box-shadow: var(--pos-shadow);
        cursor: pointer;
        transition: all 0.15s ease;
    }

    .dtcs-action-btn:hover:not(:disabled) {
        background: var(--pos-primary-light);
        border-color: var(--pos-primary);
    }

    .dtcs-action-btn i {
        font-size: 18px;
    }

    .dtcs-action-label {
        color: var(--pos-muted);
        font-size: 9px;
        font-weight: 700;
        text-transform: uppercase;
    }

    /* ══════════════════════════════════════════════════════════════════
       WHITE POP-UP MODALS — BULLETPROOF LIGHT & DARK MODE COMPATIBILITY
       Ensures all POS popup modals are crisp white (#ffffff) with high-
       contrast dark typography, sharp borders, and legible controls
       regardless of whether the application is in Light or Dark Mode.
    ══════════════════════════════════════════════════════════════════ */
    .pos-modal-backdrop {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 1050;
        background: rgba(15, 23, 42, 0.72) !important;
        backdrop-filter: blur(4px) !important;
        -webkit-backdrop-filter: blur(4px) !important;
        align-items: center;
        justify-content: center;
        padding: 16px;
    }

    .pos-modal-backdrop.show {
        display: flex !important;
    }

    /* Modal Card Shell: Always Crisp White */
    .pos-modal-card {
        width: min(460px, 100%);
        max-height: calc(100vh - 40px);
        overflow-y: auto;
        padding: 24px;
        border: 1px solid #cbd5e1 !important;
        border-radius: 14px !important;
        background-color: #ffffff !important;
        background: #ffffff !important;
        color: #0f172a !important;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.45), 0 0 0 1px rgba(0, 0, 0, 0.05) !important;
        color-scheme: light !important;
    }

    /* Headings & Text inside White Modal */
    .pos-modal-card h1,
    .pos-modal-card h2,
    .pos-modal-card h3,
    .pos-modal-card h4,
    .pos-modal-card h5,
    .pos-modal-card h6,
    .pos-modal-card .h2 {
        margin: 0 0 6px;
        color: #0f172a !important;
        font-size: 18px;
        font-weight: 800;
        letter-spacing: -0.01em;
    }

    .pos-modal-card p {
        color: #475569 !important;
        font-size: 12.5px;
        line-height: 1.5;
        margin-bottom: 14px;
    }

    .pos-modal-card label,
    .pos-modal-card .form-label {
        display: block;
        margin: 12px 0 5px;
        color: #1e293b !important;
        font-size: 11.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.02em;
    }

    /* Inputs, Dropdowns, and Search in White Modal */
    .pos-modal-card input,
    .pos-modal-card select,
    .pos-modal-card textarea,
    .pos-modal-card .form-control,
    .pos-modal-card .form-select,
    .pos-modal-card .pos-search-input {
        width: 100%;
        min-height: 38px;
        padding: 8px 12px;
        border: 1.5px solid #cbd5e1 !important;
        border-radius: 8px !important;
        background-color: #ffffff !important;
        background: #ffffff !important;
        color: #0f172a !important;
        font-size: 13px !important;
        outline: none !important;
        box-shadow: none !important;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }

    .pos-modal-card input:focus,
    .pos-modal-card select:focus,
    .pos-modal-card textarea:focus,
    .pos-modal-card .form-control:focus,
    .pos-modal-card .form-select:focus,
    .pos-modal-card .pos-search-input:focus {
        border-color: #0f766e !important;
        background-color: #ffffff !important;
        color: #0f172a !important;
        box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.15) !important;
    }

    .pos-modal-card input::placeholder,
    .pos-modal-card .form-control::placeholder,
    .pos-modal-card .pos-search-input::placeholder {
        color: #94a3b8 !important;
        opacity: 1 !important;
    }

    .pos-modal-card input[readonly] {
        background-color: #f1f5f9 !important;
        background: #f1f5f9 !important;
        color: #475569 !important;
        border-color: #e2e8f0 !important;
        cursor: not-allowed;
    }

    .pos-modal-card select option {
        background-color: #ffffff !important;
        color: #0f172a !important;
    }

    .pos-modal-card select option:disabled {
        background-color: #f8fafc !important;
        color: #94a3b8 !important;
    }

    /* Soft Info Callouts & Alert Boxes */
    .pos-modal-info {
        margin-top: 10px;
        padding: 10px 14px;
        border: 1px solid #99f6e4 !important;
        border-radius: 8px !important;
        background-color: #f0fdfa !important;
        color: #0f766e !important;
        font-size: 12px;
        line-height: 1.45;
    }

    .pos-modal-error {
        display: none;
        margin: 8px 0 0;
        color: #dc2626 !important;
        font-size: 11.5px;
        font-weight: 700;
    }

    /* Modal Action Buttons */
    .pos-modal-actions {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 10px;
        margin-top: 22px;
    }

    .pos-modal-actions button,
    .pos-modal-actions .btn {
        min-height: 38px;
        padding: 8px 16px;
        border: 1px solid #cbd5e1 !important;
        border-radius: 8px !important;
        background-color: #f8fafc !important;
        background: #f8fafc !important;
        color: #334155 !important;
        font-size: 12.5px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.15s ease;
    }

    .pos-modal-actions button:hover:not(.btn-primary-action):not(.btn-primary) {
        background-color: #f1f5f9 !important;
        color: #0f172a !important;
        border-color: #94a3b8 !important;
    }

    .pos-modal-actions .btn-primary-action,
    .pos-modal-actions .btn-primary {
        border-color: #0f766e !important;
        background-color: #0f766e !important;
        background: #0f766e !important;
        color: #ffffff !important;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    }

    .pos-modal-actions .btn-primary-action:hover,
    .pos-modal-actions .btn-primary:hover {
        background-color: #115e59 !important;
        background: #115e59 !important;
        border-color: #115e59 !important;
        color: #ffffff !important;
    }

    /* ── Modal 2 Specific: Select Discount Modal ── */
    #discountModal .pos-modal-card {
        width: min(780px, 96vw) !important;
        padding: 24px;
        position: relative;
    }

    .pos-discount-close {
        position: absolute;
        top: 18px;
        right: 18px;
        background: transparent !important;
        border: none !important;
        font-size: 16px;
        line-height: 1;
        color: #64748b !important;
        cursor: pointer;
        padding: 6px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.15s ease;
    }

    .pos-discount-close:hover {
        color: #0f172a !important;
        background-color: #f1f5f9 !important;
    }

    .pos-discount-header {
        margin-bottom: 20px;
        padding-right: 32px;
    }

    .pos-discount-header h2 {
        font-size: 20px !important;
        font-weight: 800 !important;
        margin-bottom: 4px;
        color: #0f172a !important;
    }

    .pos-discount-header p {
        margin-bottom: 0;
        color: #64748b !important;
        font-size: 13px;
    }

    .pos-discount-layout {
        display: grid;
        grid-template-columns: minmax(0, 1.25fr) minmax(0, 0.95fr);
        gap: 24px;
        align-items: start;
    }

    @media (max-width: 640px) {
        .pos-discount-layout {
            grid-template-columns: 1fr;
            gap: 18px;
        }
    }

    .pos-discount-section-title {
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #475569 !important;
        margin-bottom: 3px;
    }

    .pos-discount-section-subtitle {
        font-size: 11.5px;
        color: #64748b !important;
        margin-bottom: 12px;
    }

    /* Rate Selector Banner (e.g. Senior / PWD discount rate: 5% vs 20%) */
    .pos-discount-rate-banner {
        border: 1px solid #bae6fd !important;
        background-color: #f0f9ff !important;
        background: #f0f9ff !important;
        border-radius: 8px;
        padding: 8px 14px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
    }

    .pos-discount-rate-banner span,
    #rateBannerLabel {
        color: #0369a1 !important;
        font-weight: 700 !important;
        font-size: 12px;
    }

    .pos-rate-btn-group {
        display: inline-flex;
        gap: 6px;
    }

    .pos-rate-pill {
        border: 1px solid #cbd5e1 !important;
        background-color: #ffffff !important;
        background: #ffffff !important;
        color: #334155 !important;
        border-radius: 6px;
        padding: 4px 14px;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.15s ease;
    }

    .pos-rate-pill:hover {
        border-color: #0284c7 !important;
        color: #0284c7 !important;
    }

    .pos-rate-pill.active {
        border-color: #0284c7 !important;
        background-color: #e0f2fe !important;
        background: #e0f2fe !important;
        color: #0369a1 !important;
    }

    /* Grid of Discount Option Cards */
    .pos-discount-card-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }

    .pos-discount-type-card {
        border: 1.5px solid #e2e8f0 !important;
        border-radius: 10px;
        padding: 12px 10px;
        background-color: #ffffff !important;
        background: #ffffff !important;
        text-align: center;
        cursor: pointer;
        transition: all 0.15s ease;
        user-select: none;
    }

    .pos-discount-type-card:hover {
        border-color: #38bdf8 !important;
        background-color: #f8fafc !important;
        background: #f8fafc !important;
    }

    .pos-discount-type-card.active {
        border-color: #0284c7 !important;
        background-color: #f0f9ff !important;
        background: #f0f9ff !important;
        box-shadow: 0 0 0 1px #0284c7 !important;
    }

    .pos-discount-type-card .card-icon {
        font-size: 20px;
        color: #64748b !important;
        margin-bottom: 4px;
        display: block;
        transition: color 0.15s ease;
    }

    .pos-discount-type-card.active .card-icon {
        color: #0284c7 !important;
    }

    .pos-discount-type-card .card-title {
        font-size: 13px;
        font-weight: 700;
        color: #0f172a !important;
        line-height: 1.2;
    }

    .pos-discount-type-card.active .card-title {
        color: #0284c7 !important;
    }

    .pos-discount-type-card .card-subtitle {
        font-size: 10.5px;
        color: #64748b !important;
        margin-top: 2px;
    }

    .pos-discount-type-card.active .card-subtitle {
        color: #0369a1 !important;
    }

    #customDiscountInputWrap {
        background-color: #f8fafc !important;
        border: 1px solid #e2e8f0 !important;
    }

    #discountSummaryNote {
        background-color: #f8fafc !important;
        border: 1px solid #e2e8f0 !important;
        color: #475569 !important;
    }

    #discountSummaryText {
        color: #334155 !important;
    }

    .pos-discount-apply-btn {
        width: 100%;
        padding: 12px;
        border-radius: 8px;
        background: linear-gradient(180deg, #38bdf8 0%, #0284c7 100%) !important;
        color: #ffffff !important;
        font-weight: 700;
        font-size: 14px;
        border: none !important;
        cursor: pointer;
        transition: opacity 0.15s ease;
    }

    .pos-discount-apply-btn:hover {
        opacity: 0.92;
    }

    /* ── Modal 3 Specific: Patient Type Modal & Table ── */
    #patientRegistryWrap {
        border-color: #e2e8f0 !important;
    }

    #patientTypeModal table {
        background-color: #ffffff !important;
        color: #0f172a !important;
    }

    #patientTypeModal thead {
        background-color: #f8fafc !important;
        border-bottom: 1px solid #cbd5e1 !important;
    }

    #patientTypeModal thead th {
        color: #475569 !important;
        background-color: #f8fafc !important;
        font-weight: 700 !important;
    }

    #patientTypeModal tbody tr {
        background-color: #ffffff !important;
        border-bottom: 1px solid #f1f5f9 !important;
    }

    #patientTypeModal tbody tr:hover {
        background-color: #f8fafc !important;
    }

    #patientTypeModal td {
        color: #0f172a !important;
    }

    #patientTypeModal td .font-monospace {
        color: #0f766e !important;
    }

    #patientTypeModal td .badge.bg-warning-subtle {
        background-color: #fef3c7 !important;
        color: #92400e !important;
        border: 1px solid #fde68a !important;
    }

    #patientTypeModal td .badge.bg-info-subtle {
        background-color: #e0f2fe !important;
        color: #0369a1 !important;
        border: 1px solid #bae6fd !important;
    }

    /* ── Modal 4 Specific: Payment Tender Row ── */
    #paymentModal #cashTenderRow .bg-body-tertiary {
        background-color: #f8fafc !important;
        border: 1px solid #e2e8f0 !important;
        color: #0f172a !important;
    }

    #paymentChangeDue {
        color: #15803d !important;
        font-weight: 800 !important;
    }

    /* ── Modal 5 Specific: Add Product Steppers & Search ── */
    #addProductModal .btn-outline-secondary {
        background-color: #f8fafc !important;
        color: #1e293b !important;
        border: 1.5px solid #cbd5e1 !important;
        border-radius: 6px !important;
    }

    #addProductModal .btn-outline-secondary:hover {
        background-color: #f1f5f9 !important;
        color: #0f172a !important;
        border-color: #0f766e !important;
    }

    #addProductModal .pos-search-wrap .pos-search-icon {
        color: #64748b !important;
    }

    #addProductMaxHint {
        color: #64748b !important;
    }

    /* ── Responsive Adaptations ── */
    @media (max-width: 1100px) {
        .dtcs-pos-shell {
            grid-template-columns: minmax(0, 1.5fr) minmax(290px, 1fr) 60px;
            gap: 10px;
        }
        .pos-product-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    @media (max-width: 850px) {
        .dtcs-pos-shell {
            grid-template-columns: minmax(0, 1fr) minmax(280px, 0.9fr);
            grid-template-rows: auto auto;
        }
        .dtcs-action-tabs {
            grid-column: 1 / -1;
            flex-direction: row;
            overflow-x: auto;
        }
        .dtcs-action-btn {
            flex: 1;
            min-height: 56px;
        }
    }

    @media (max-width: 640px) {
        .dtcs-pos-shell {
            display: flex;
            flex-direction: column;
        }
        .pos-catalog-panel {
            min-height: 540px;
        }
        .pos-order-panel {
            min-height: 480px;
        }
        .dtcs-action-tabs {
            order: -1;
            width: 100%;
        }
        .pos-product-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
</style>
@endpush

@section('page-titlebar-custom')
<div class="pos-top-bar w-100">
    <div class="d-flex align-items-center gap-2">
        <div class="rounded-3 p-2 bg-success bg-opacity-10 text-success d-flex align-items-center justify-content-center">
            <i class="bi bi-display fs-5"></i>
        </div>
        <div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <h1 class="fs-6 fw-bold mb-0 text-body">Point of Sale</h1>
                <span class="text-muted small">&bull;</span>
                <span class="small fw-semibold text-body-secondary">Main Store | POS #1 (DTCS-PHARM-MAIN)</span>
                <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle">
                    Shift Active
                </span>
            </div>
            <div class="small text-muted">
                Pharmacist: <strong class="text-body">{{ auth()->user()->name }}</strong> &bull; Terminal: <span class="font-monospace">POS-TERM-01</span>
            </div>
        </div>
    </div>

    <div class="d-flex align-items-center gap-2 flex-wrap">
        <div class="px-3 py-1 rounded bg-body-tertiary border small">
            <span class="text-muted">Today's Shift:</span>
            <span class="fw-bold text-success ms-1">₱{{ number_format($shiftStats['net_sales'], 2) }}</span>
            <span class="small text-muted ms-1">({{ $shiftStats['completed_count'] }} sales)</span>
        </div>
        <a href="{{ route('pharmacy.prescriptions.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-file-earmark-medical me-1 text-primary"></i> Prescriptions
        </a>
        <a href="{{ route('pharmacy.dispensing.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-capsule me-1 text-success"></i> Dispensing Queue
        </a>
    </div>
</div>
@endsection

@section('content')
<div class="dtcs-pos-workspace">

    {{-- Validation Errors Alert --}}
    @if ($errors->any())
        <div class="alert alert-danger mb-0 py-2 px-3 small border-0 shadow-xs">
            <div class="fw-bold d-flex align-items-center gap-1 mb-1">
                <i class="bi bi-exclamation-octagon-fill"></i> Unable to complete POS transaction:
            </div>
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ── 2. Main POS Workspace: Product Catalog + Order Summary + Action Tabs ── --}}
    <main class="dtcs-pos-shell">

        {{-- LEFT COLUMN: Product Catalog --}}
        <section class="pos-catalog-panel">
            <header class="pos-catalog-header">
                <div>
                    <div class="pos-catalog-title">Pharmacy Point of Sale</div>
                    <div class="pos-catalog-subtitle">Medicine Catalog and Dispensing Sales</div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button"
                            class="btn btn-sm btn-outline-primary py-1 px-2.5 rounded-2 d-inline-flex align-items-center gap-1.5"
                            style="font-size: 11.5px; font-weight: 700;"
                            onclick="posOpenAddProductModal()"
                            title="Add product to order">
                        <i class="bi bi-plus-circle"></i> Add Product
                    </button>
                    <span class="pos-terminal-badge">POS #01</span>
                </div>
            </header>

            {{-- Category Navigation Underline Tabs --}}
            <nav class="pos-category-bar" id="posCategoryTabs">
                @foreach($categories as $cat)
                    @php $isActive = strcasecmp($selectedCategory, $cat) === 0; @endphp
                    <button type="button"
                            class="pos-category-btn {{ $isActive ? 'active' : '' }}"
                            data-category="{{ strtoupper($cat) }}"
                            onclick="posFilterCategory('{{ addslashes($cat) }}')">
                        {{ $cat }}
                    </button>
                @endforeach
            </nav>

            <div class="pos-catalog-body">
                {{-- Search & Barcode Toolbar --}}
                <div class="pos-search-toolbar">
                    <button class="pos-barcode-btn" id="barcodeButton" type="button" onclick="posTriggerBarcodeScan()">
                        <i class="bi bi-upc-scan"></i> Barcode
                    </button>

                    <div class="pos-search-wrap">
                        <i class="bi bi-search pos-search-icon"></i>
                        <input class="pos-search-input"
                               id="search"
                               name="search"
                               type="search"
                               value="{{ $searchQuery }}"
                               oninput="posFilterCatalogClientSide()"
                               onkeydown="posOnSearchKeyDown(event)"
                               placeholder="Search medicine or scan barcode (press Enter to add)..."
                               aria-label="Search product">
                    </div>
                </div>

                {{-- Product Cards Grid --}}
                <div class="pos-product-grid" id="productGrid">
                    @forelse($filteredCatalog as $med)
                        @php
                            $qty = (int) $med['available_quantity'];
                            if ($med['stock_status_key'] === 'out' || $qty <= 0) {
                                $stockBadgeClass = 'pos-stock-out';
                                $stockBadgeText  = 'Out of Stock';
                            } elseif ($med['stock_status_key'] === 'low' || $qty <= $med['reorder_level']) {
                                $stockBadgeClass = 'pos-stock-low';
                                $stockBadgeText  = $qty . ' Available';
                            } else {
                                $stockBadgeClass = 'pos-stock-good';
                                $stockBadgeText  = $qty . ' Available';
                            }

                            $form = strtolower($med['dosage_form'] ?? 'tablet');
                            $iconClass = match(true) {
                                str_contains($form, 'syrup') || str_contains($form, 'bottle') || str_contains($form, 'liquid') => 'bi-droplet-half',
                                str_contains($form, 'capsule') => 'bi-capsule',
                                str_contains($form, 'ointment') || str_contains($form, 'cream') => 'bi-bandaid',
                                str_contains($form, 'spray') || str_contains($form, 'inhaler') => 'bi-wind',
                                default => 'bi-capsule-pill',
                            };
                        @endphp

                        <button class="pos-product-card"
                                type="button"
                                id="card_product_{{ $med['code'] }}"
                                data-code="{{ strtolower($med['code']) }}"
                                data-barcode="{{ strtolower($med['barcode']) }}"
                                data-name="{{ strtolower($med['medication_name']) }}"
                                data-generic="{{ strtolower($med['generic_name']) }}"
                                data-brand="{{ strtolower($med['brand_name']) }}"
                                data-category="{{ strtoupper($med['category']) }}"
                                {{ ! $med['can_sell'] ? 'disabled' : '' }}
                                onclick="posAddProductToCart('{{ $med['code'] }}')">
                            <div class="pos-product-top">
                                <span class="pos-product-icon">
                                    <i class="bi {{ $iconClass }}"></i>
                                </span>
                                <span class="pos-stock-badge {{ $stockBadgeClass }}">
                                    {{ $stockBadgeText }}
                                </span>
                            </div>
                            <div>
                                <span class="pos-product-name">{{ $med['medication_name'] }}</span>
                                <span class="pos-product-strength">{{ $med['strength'] ?: $med['dosage_form'] }}</span>
                            </div>
                            <div class="pos-product-bottom">
                                <span class="pos-product-price">₱ {{ number_format($med['unit_price'], 2) }}</span>
                                <span class="pos-product-add">+</span>
                            </div>
                        </button>
                    @empty
                        <div class="col-span-full p-4 text-center text-muted small">
                            No products match your search or category filter.
                        </div>
                    @endforelse
                </div>
            </div>
        </section>

        {{-- CENTER-RIGHT COLUMN: Order Summary --}}
        <aside class="pos-order-panel">
            <div class="pos-order-heading">
                <strong>Order Summary</strong>
                <span class="pos-terminal-badge" id="orderCount">0 Items</span>
            </div>

            {{-- Selected Cart Items List --}}
            <div class="pos-order-items" id="orderItems">
                {{-- Rendered dynamically by JavaScript --}}
            </div>

            {{-- Summary Breakdown --}}
            <div class="pos-summary">
                <div class="pos-summary-row">
                    <span>Patient Type</span>
                    <strong id="patientTypeDisplay">Not selected</strong>
                </div>

                <div class="pos-summary-row">
                    <span>Subtotal</span>
                    <strong id="subtotal">₱ 0.00</strong>
                </div>

                <div class="pos-summary-row">
                    <span>VAT</span>
                    <strong id="vat">₱ 0.00</strong>
                </div>

                <div class="pos-summary-row">
                    <span id="discountLabel">Discount</span>
                    <strong id="discount">₱ 0.00</strong>
                </div>
            </div>

            <div class="pos-total-row">
                <span>Total</span>
                <span id="total">₱ 0.00</span>
            </div>

            <div class="pos-pay-wrap">
                <button class="pos-pay-btn" id="payButton" type="button" onclick="posHandleProceedToPayment()">
                    Proceed to Payment &rarr;
                </button>
            </div>
        </aside>

        {{-- FAR-RIGHT COLUMN: Action Toolbar --}}
        <nav class="dtcs-action-tabs" aria-label="Order actions">
            <button class="dtcs-action-btn" id="discountButton" type="button" title="Apply discount" onclick="posOpenDiscountModal()">
                <i class="bi bi-percent"></i>
                <span class="dtcs-action-label">Discount</span>
            </button>

            <button class="dtcs-action-btn" id="patientTypeButton" type="button" title="Select patient type" onclick="posOpenPatientModal()">
                <i class="bi bi-hospital"></i>
                <span class="dtcs-action-label">Patient Type</span>
            </button>

            <button class="dtcs-action-btn" id="clearButton" type="button" title="Clear current order" onclick="posClearOrder()">
                <i class="bi bi-trash3"></i>
                <span class="dtcs-action-label">Clear</span>
            </button>
        </nav>
    </main>

    {{-- Hidden Form for Backend Payment Settlement Submission --}}
    <form method="POST" action="{{ route('pharmacy.pos.checkout') }}" id="posCheckoutForm" class="d-none">
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
        <input type="hidden" name="sale_type" id="posFormSaleType" value="{{ $linkedPrescription ? 'prescription' : 'walk_in' }}">
        <input type="hidden" name="patient_type" id="posFormPatientType" value="{{ $currentPatientType ?? 'Outpatient' }}">
        <input type="hidden" name="patient_id" id="posFormPatientId" value="{{ $currentPatientId ?? '' }}">
        <input type="hidden" name="customer_name" id="posFormCustomerName" value="{{ $currentCustomerName }}">
        <input type="hidden" name="prescription_id" id="posFormPrescriptionId" value="{{ $linkedPrescription?->id }}">
        <input type="hidden" name="discount_type" id="posFormDiscountType" value="none">
        <input type="hidden" name="discount_reference" id="posFormDiscountRef" value="">
        <input type="hidden" name="custom_discount_rate" id="posFormCustomDiscountRate" value="0">
        <input type="hidden" name="custom_discount_amount" id="posFormCustomDiscountAmount" value="0">
        <input type="hidden" name="payment_method" id="posFormPaymentMethod" value="Cash">
        <input type="hidden" name="amount_tendered" id="posFormAmountTendered" value="0.00">
        <input type="hidden" name="payment_reference" id="posFormPaymentRef" value="">
        <input type="hidden" name="notes" id="posFormNotes" value="">
        <div id="posCartHiddenInputs"></div>
    </form>
</div>

{{-- ── MODAL 1: EDIT ORDER ITEM MODAL (From pos.html reference) ── --}}
<div class="pos-modal-backdrop" id="editItemModal">
    <div class="pos-modal-card" role="dialog" aria-modal="true" aria-labelledby="editItemTitle">
        <h2 id="editItemTitle">Edit Order Item</h2>
        <p>Only the available stock strength and order quantity can be changed.</p>

        <label for="editItemName">Product / Medicine Name</label>
        <input id="editItemName" type="text" readonly>

        <label for="editItemStrength">Available Strength / Dosage</label>
        <select id="editItemStrength" onchange="posUpdateEditStockInfo()"></select>

        <div class="pos-modal-info" id="editItemStockInfo">
            Select an available stock variant.
        </div>

        <label for="editItemQty">Quantity</label>
        <input id="editItemQty" type="number" min="1" step="1" value="1" oninput="posOnEditQtyChange()">

        <p class="pos-modal-error" id="editItemError">
            Select an available strength and enter a valid quantity.
        </p>

        <div class="pos-modal-actions">
            <button id="cancelEditItem" type="button" onclick="posCloseEditItemModal()">Cancel</button>
            <button class="btn-primary-action" id="saveEditItem" type="button" onclick="posSaveEditItem()">Save Changes</button>
        </div>
    </div>
</div>

{{-- ── MODAL 2: SELECT DISCOUNT MODAL (Styled per user reference image) ── --}}
<div class="pos-modal-backdrop" id="discountModal">
    <div class="pos-modal-card pos-discount-dialog" role="dialog" aria-modal="true" aria-labelledby="discountTitle">
        {{-- Close button in top-right --}}
        <button type="button" class="pos-discount-close" onclick="posCloseDiscountModal()" aria-label="Close modal">
            <i class="bi bi-x-lg"></i>
        </button>

        {{-- Header --}}
        <div class="pos-discount-header">
            <h2 id="discountTitle">Select Discount</h2>
            <p>This will be applied to all line items</p>
        </div>

        {{-- Two-column Layout --}}
        <div class="pos-discount-layout">
            {{-- Column 1: Left - Discount Selection --}}
            <div class="pos-discount-col-options">
                {{-- Government Discounts Section --}}
                <div class="pos-discount-section-title">Government Discounts</div>
                <div class="pos-discount-section-subtitle">For eligible customers with valid government-issued proof.</div>

                {{-- Dynamic Rate Selector Banner (Shown when Senior or PWD is selected) --}}
                <div class="pos-discount-rate-banner" id="discountRateBanner" style="display: flex;">
                    <span class="small fw-semibold text-primary" id="rateBannerLabel">Senior discount rate</span>
                    <div class="pos-rate-btn-group">
                        <button type="button" class="pos-rate-pill" id="ratePill5" onclick="posSelectRate(5)">5%</button>
                        <button type="button" class="pos-rate-pill active" id="ratePill20" onclick="posSelectRate(20)">20%</button>
                    </div>
                </div>

                {{-- Government Discount Cards (2x2 Grid) --}}
                <div class="pos-discount-card-grid mb-3">
                    <div class="pos-discount-type-card active" id="card_senior" onclick="posSelectDiscountType('senior')">
                        <i class="bi bi-person card-icon"></i>
                        <div class="card-title">Senior</div>
                        <div class="card-subtitle">5% or 20% off</div>
                    </div>

                    <div class="pos-discount-type-card" id="card_pwd" onclick="posSelectDiscountType('pwd')">
                        <i class="bi bi-person-wheelchair card-icon"></i>
                        <div class="card-title">PWD</div>
                        <div class="card-subtitle">5% or 20% off</div>
                    </div>

                    <div class="pos-discount-type-card" id="card_athlete" onclick="posSelectDiscountType('athlete')">
                        <i class="bi bi-award card-icon"></i>
                        <div class="card-title">National Athlete</div>
                        <div class="card-subtitle">20% off</div>
                    </div>

                    <div class="pos-discount-type-card" id="card_solo_parent" onclick="posSelectDiscountType('solo_parent')">
                        <i class="bi bi-people card-icon"></i>
                        <div class="card-title">Solo Parent</div>
                        <div class="card-subtitle">10% off</div>
                    </div>
                </div>

                {{-- Custom Discounts Section --}}
                <div class="pos-discount-section-title mt-3">Custom Discounts</div>
                <div class="pos-discount-section-subtitle">Manual discount value for promos and discretionary cases.</div>

                <div class="pos-discount-card-grid">
                    <div class="pos-discount-type-card" id="card_custom_percentage" onclick="posSelectDiscountType('custom_percentage')">
                        <i class="bi bi-percent card-icon"></i>
                        <div class="card-title">Percentage</div>
                        <div class="card-subtitle">% off item total</div>
                    </div>

                    <div class="pos-discount-type-card" id="card_custom_amount" onclick="posSelectDiscountType('custom_amount')">
                        <span class="card-icon fw-bold">₱</span>
                        <div class="card-title">Amount</div>
                        <div class="card-subtitle">Fixed peso amount</div>
                    </div>
                </div>

                {{-- Dynamic Custom Input Box (Visible only when Percentage or Amount is chosen) --}}
                <div id="customDiscountInputWrap" class="mt-2 p-2 rounded border bg-light small" style="display: none;">
                    <div id="customPercentField" style="display: none;">
                        <label class="form-label small mb-1 fw-semibold">Discount Percentage (%)</label>
                        <input type="number" min="1" max="100" step="1" id="customPercentValue" class="form-control form-control-sm" placeholder="e.g. 5, 10, 15" value="5" oninput="posOnCustomValueChange()">
                    </div>
                    <div id="customAmountField" style="display: none;">
                        <label class="form-label small mb-1 fw-semibold">Discount Amount (₱)</label>
                        <input type="number" min="1" step="0.50" id="customAmountValue" class="form-control form-control-sm" placeholder="e.g. 50.00" value="50" oninput="posOnCustomValueChange()">
                    </div>
                </div>
            </div>

            {{-- Column 2: Right - Customer Information --}}
            <div class="pos-discount-col-info">
                <div class="pos-discount-section-title mb-2">Customer Information</div>

                <div class="mb-3">
                    <label for="customerName" class="form-label small fw-semibold mb-1">Customer Name</label>
                    <input id="customerName" type="text" class="form-control" placeholder="Full name as on ID">
                </div>

                <div class="mb-3">
                    <label for="governmentIdNumber" id="governmentIdLabel" class="form-label small fw-semibold mb-1">Senior Citizen ID Number</label>
                    <input id="governmentIdNumber" type="text" class="form-control" placeholder="Enter ID number">
                </div>

                <div class="mb-3">
                    <label for="customerTin" class="form-label small fw-semibold mb-1 text-muted">
                        TIN Number <span class="fw-normal">(optional)</span>
                    </label>
                    <input id="customerTin" type="text" class="form-control" placeholder="Enter TIN number">
                </div>

                <div class="p-2 rounded bg-body-tertiary border small text-muted mt-2" id="discountSummaryNote">
                    <i class="bi bi-info-circle text-primary me-1"></i>
                    <span id="discountSummaryText">Senior Citizen discount (20% + VAT Exemption) will be applied.</span>
                </div>
            </div>
        </div>

        {{-- Error message --}}
        <p class="pos-modal-error mt-3" id="discountError" style="display: none;">
            Please complete the required customer information.
        </p>

        {{-- Modal Actions --}}
        <div class="mt-4 pt-2">
            <button class="pos-discount-apply-btn" id="applyDiscount" type="button" onclick="posApplyDiscount()">
                Apply Discount
            </button>
            <div class="text-center mt-2" id="clearDiscountWrap" style="display: none;">
                <button type="button" class="btn btn-link btn-sm text-danger text-decoration-none p-0 small" onclick="posClearDiscount()">
                    <i class="bi bi-trash3 me-1"></i> Remove Active Discount
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ── MODAL 3: PATIENT TYPE MODAL (From pos.html reference + DTCS Hospital Registry Picker) ── --}}
<div class="pos-modal-backdrop" id="patientTypeModal">
    <div class="pos-modal-card" style="width: min(560px, 100%);" role="dialog" aria-modal="true" aria-labelledby="patientTypeTitle">
        <h2 id="patientTypeTitle">Select Patient Type</h2>
        <p>Choose whether this transaction is for an outpatient or an inpatient.</p>

        <label for="patientTypeSelect">Patient Type</label>
        <select id="patientTypeSelect" onchange="posOnPatientTypeDropdownChange()">
            <option value="">Select patient type</option>
            <option value="outpatient">Outpatient</option>
            <option value="inpatient">Inpatient</option>
        </select>

        <p class="pos-modal-error" id="patientTypeError">
            Please select Outpatient or Inpatient.
        </p>

        {{-- DTCS Inpatient / Hospital Registered Patient Selector --}}
        <div id="patientRegistryWrap" class="mt-3 pt-3 border-top">
            <label class="mb-1">Link Hospital Patient (Optional / Inpatient Master)</label>
            <input type="text"
                   id="patientSearchInput"
                   placeholder="Search by patient name, patient no, ward, or bed..."
                   class="mb-2"
                   oninput="posFilterPatientRegistry()">

            <div class="border rounded overflow-hidden" style="max-height: 180px; overflow-y: auto;">
                <table class="w-100 text-start small border-collapse">
                    <thead class="bg-body-tertiary sticky-top border-bottom small text-muted">
                        <tr>
                            <th class="p-2">Patient No.</th>
                            <th class="p-2">Name</th>
                            <th class="p-2">Type</th>
                            <th class="p-2">Ward / Bed</th>
                            <th class="p-2 text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody id="patientRegistryTableBody">
                        @foreach($patients as $p)
                            <tr class="patient-picker-row border-bottom"
                                data-id="{{ $p->id }}"
                                data-no="{{ strtolower($p->patient_no) }}"
                                data-name="{{ strtolower($p->first_name . ' ' . $p->last_name) }}"
                                data-type="{{ strtolower($p->patient_type) }}"
                                data-ward="{{ strtolower($p->ward ?? '') }}"
                                data-bed="{{ strtolower($p->bed_number ?? '') }}">
                                <td class="p-2 font-monospace fw-bold">{{ $p->patient_no }}</td>
                                <td class="p-2 fw-semibold">{{ $p->first_name }} {{ $p->last_name }}</td>
                                <td class="p-2"><span class="badge {{ $p->patient_type === 'Inpatient' ? 'bg-warning-subtle text-warning-emphasis' : 'bg-info-subtle text-info-emphasis' }}">{{ $p->patient_type }}</span></td>
                                <td class="p-2 text-muted">{{ $p->ward ? ($p->ward . ($p->bed_number ? ' / ' . $p->bed_number : '')) : '—' }}</td>
                                <td class="p-2 text-end">
                                    <button type="button"
                                            class="btn btn-xs btn-outline-primary py-0 px-2"
                                            onclick="posPickPatientRecord({{ $p->id }}, '{{ addslashes($p->patient_no) }}', '{{ addslashes($p->first_name . ' ' . $p->last_name) }}', '{{ addslashes($p->patient_type) }}', '{{ addslashes($p->ward ?? '') }}', '{{ addslashes($p->bed_number ?? '') }}')">
                                        Select
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="small text-muted mt-1">
                Patient master records belong to the external Patient Directory and cannot be altered from POS.
            </div>
        </div>

        <div class="pos-modal-actions">
            <button id="cancelPatientType" type="button" onclick="posClosePatientTypeModal()">Cancel</button>
            <button class="btn-primary-action" id="applyPatientType" type="button" onclick="posApplyPatientType()">Apply</button>
        </div>
    </div>
</div>

{{-- ── MODAL 4: PAYMENT CONFIRMATION & SETTLEMENT MODAL (From pos.html reference) ── --}}
<div class="pos-modal-backdrop" id="paymentModal">
    <div class="pos-modal-card" role="dialog" aria-modal="true" aria-labelledby="paymentTitle">
        <h2 id="paymentTitle">Proceed to Payment</h2>
        <p id="paymentMessage">Confirm this order and continue to payment.</p>

        {{-- Payment Methods & Cash Tender --}}
        <div id="paymentOptionsWrap" class="space-y-3 mb-3">
            <div>
                <label for="paymentMethodSelect">Payment Method</label>
                <select id="paymentMethodSelect" onchange="posOnPaymentMethodChange()">
                    <option value="Cash">Cash</option>
                    <option value="GCash">GCash (E-Wallet)</option>
                    <option value="Card">Debit / Credit Card</option>
                    <option value="Hospital Billing">Charge to Hospital Bill (Inpatients)</option>
                </select>
            </div>

            <div class="row g-2 align-items-end" id="cashTenderRow">
                <div class="col-6">
                    <label for="paymentTenderedInput">Amount Tendered (₱)</label>
                    <input type="number" step="0.01" min="0" id="paymentTenderedInput" placeholder="0.00" oninput="posUpdateTenderChange()">
                </div>
                <div class="col-6">
                    <div class="p-2 rounded bg-body-tertiary border text-center">
                        <span class="small text-muted d-block" style="font-size: 10px;">Change Due</span>
                        <strong class="text-success" id="paymentChangeDue">₱ 0.00</strong>
                    </div>
                </div>
            </div>

            <div>
                <label for="paymentRefInput">Reference / Remarks <span class="small text-muted font-normal">(Optional)</span></label>
                <input type="text" id="paymentRefInput" placeholder="Approval code, GCash ref, or bill notes">
            </div>
        </div>

        <div class="pos-modal-actions">
            <button id="cancelPayment" type="button" onclick="posClosePaymentModal()">Cancel</button>
            <button class="btn-primary-action" id="confirmPayment" type="button" onclick="posSubmitPayment()">Confirm &amp; Record Payment</button>
        </div>
    </div>
</div>

{{-- ── MODAL 5: ADD PRODUCT MODAL ── --}}
<div class="pos-modal-backdrop" id="addProductModal">
    <div class="pos-modal-card" style="width: min(520px, 96vw);" role="dialog" aria-modal="true" aria-labelledby="addProductTitle">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h2 id="addProductTitle" class="mb-0">Add Product to Order</h2>
            <button type="button" class="btn btn-sm btn-link text-muted p-0" onclick="posCloseAddProductModal()" aria-label="Close modal">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <p class="text-muted small mb-3">Select a medication from the active catalog and specify the quantity to add.</p>

        <label for="addProductFilter" class="form-label small fw-bold mb-1">Filter Medicine</label>
        <div class="pos-search-wrap mb-2">
            <i class="bi bi-search pos-search-icon"></i>
            <input type="text"
                   id="addProductFilter"
                   class="pos-search-input"
                   placeholder="Type medicine name or generic to filter list..."
                   oninput="posFilterAddProductOptions()">
        </div>

        <label for="addProductSelect" class="form-label small fw-bold mb-1">Medication &amp; Available Stock</label>
        <select id="addProductSelect" class="form-select mb-2" onchange="posOnAddProductSelectChange()">
            <option value="">-- Choose a medication --</option>
            @foreach($catalog as $item)
                <option value="{{ $item['code'] }}"
                        data-code="{{ strtolower($item['code']) }}"
                        data-name="{{ strtolower($item['medication_name']) }}"
                        data-generic="{{ strtolower($item['generic_name'] ?? '') }}"
                        data-brand="{{ strtolower($item['brand_name'] ?? '') }}"
                        data-price="{{ $item['unit_price'] }}"
                        data-stock="{{ $item['available_quantity'] }}"
                        {{ !$item['can_sell'] || $item['available_quantity'] <= 0 ? 'disabled' : '' }}>
                    {{ $item['medication_name'] }} ({{ $item['strength'] ?: $item['dosage_form'] }}) &bull; ₱{{ number_format($item['unit_price'], 2) }} [{{ $item['available_quantity'] }} in stock]
                </option>
            @endforeach
        </select>

        <div class="pos-modal-info mb-3" id="addProductStockInfo">
            Select a product to view stock details.
        </div>

        <label for="addProductQty" class="form-label small fw-bold mb-1">Quantity to Add</label>
        <div class="d-flex align-items-center gap-2 mb-2">
            <button type="button" class="btn btn-outline-secondary btn-sm px-3" onclick="posStepAddProductModalQty(-1)" style="font-weight: 800;">-</button>
            <input id="addProductQty" type="number" min="1" step="1" value="1" class="form-control text-center fw-bold" style="max-width: 120px;" oninput="posOnAddProductQtyChange()">
            <button type="button" class="btn btn-outline-secondary btn-sm px-3" onclick="posStepAddProductModalQty(1)" style="font-weight: 800;">+</button>
            <span class="small text-muted ms-1" id="addProductMaxHint"></span>
        </div>

        <p class="pos-modal-error text-danger small mt-2" id="addProductError" style="display: none;"></p>

        <div class="pos-modal-actions">
            <button type="button" onclick="posCloseAddProductModal()">Cancel</button>
            <button type="button" class="btn-primary-action" id="btnAddProductConfirm" onclick="posSubmitAddProductFromModal()">
                <i class="bi bi-cart-plus me-1"></i> Add to Order
            </button>
        </div>
    </div>
</div>

{{-- ── POS Interactive JavaScript Engine ── --}}
<script>
    const POS_VAT_RATE = {{ (float) $vatRate }};
    const POS_CATALOG = @json($catalog);

    // Discount Definitions per reference UI
    const discountDefinitions = {
        senior: {
            name: "Senior Citizen",
            rates: [5, 20],
            defaultRate: 20,
            hasRateToggle: true,
            rateLabel: "Senior discount rate",
            idLabel: "Senior Citizen ID Number",
            placeholder: "Enter ID number",
            isVatExempt: true,
            summary: "Senior Citizen discount (20% + 12% VAT Exemption) will be applied."
        },
        pwd: {
            name: "PWD",
            rates: [5, 20],
            defaultRate: 20,
            hasRateToggle: true,
            rateLabel: "PWD discount rate",
            idLabel: "PWD ID Number",
            placeholder: "Enter ID number",
            isVatExempt: true,
            summary: "PWD discount (20% + 12% VAT Exemption) will be applied."
        },
        athlete: {
            name: "National Athlete",
            rates: [20],
            defaultRate: 20,
            hasRateToggle: false,
            idLabel: "National Athlete ID Number",
            placeholder: "Enter ID number",
            isVatExempt: true,
            summary: "National Athlete statutory discount (20% + 12% VAT Exemption) will be applied."
        },
        solo_parent: {
            name: "Solo Parent",
            rates: [10],
            defaultRate: 10,
            hasRateToggle: false,
            idLabel: "Solo Parent ID Number",
            placeholder: "Enter ID number",
            isVatExempt: false,
            summary: "Solo Parent statutory discount (10%) will be applied."
        },
        custom_percentage: {
            name: "Percentage",
            rates: [],
            defaultRate: 5,
            hasRateToggle: false,
            idLabel: "Reference / Reason",
            placeholder: "Enter reference or promo note",
            isVatExempt: false,
            summary: "Custom percentage discount will be deducted from order subtotal."
        },
        custom_amount: {
            name: "Amount",
            rates: [],
            defaultRate: 0,
            hasRateToggle: false,
            idLabel: "Reference / Reason",
            placeholder: "Enter reference or promo note",
            isVatExempt: false,
            summary: "Fixed peso discount will be deducted from order subtotal."
        }
    };

    let currentSelectedDiscountType = 'senior';
    let currentSelectedDiscountRate = 20;
    let currentCustomPercent = 5;
    let currentCustomAmount = 50;

    // State Variables
    let cart = @json($initialCart);
    let activeCategory = @json(strtoupper($selectedCategory ?? 'ALL ITEMS'));
    let discountPercent = 0;
    let appliedDiscount = null;
    let editingCartItemId = null;
    let patientType = @json(strtolower($currentPatientType ?? ''));
    let selectedPatientRecord = @json($selectedPatient);

    const money = value => "₱ " + parseFloat(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    // ── Helper Lookups ──
    function getCatalogItemByCode(code) {
        return POS_CATALOG.find(c => c.code.toLowerCase() === (code || '').toLowerCase());
    }

    function getVariantsForMedicine(item) {
        if (!item) return [];
        const generic = (item.generic_name || '').toLowerCase();
        const brand   = (item.brand_name || '').toLowerCase();

        return POS_CATALOG.filter(c => {
            const cGen   = (c.generic_name || '').toLowerCase();
            const cBrand = (c.brand_name || '').toLowerCase();
            return (generic && cGen === generic)
                || (brand && cBrand === brand)
                || c.code.toLowerCase() === item.code.toLowerCase();
        });
    }

    function getCartItemQuantity(code, excludeCartItemId = null) {
        return cart.reduce((total, entry) => {
            if (entry.code.toLowerCase() === code.toLowerCase() && entry.cartItemId !== excludeCartItemId) {
                return total + entry.qty;
            }
            return total;
        }, 0);
    }

    function getAvailableStock(code, excludeCartItemId = null) {
        const item = getCatalogItemByCode(code);
        if (!item) return 0;
        return Math.max(0, item.available_quantity - getCartItemQuantity(code, excludeCartItemId));
    }

    // ── Product Addition & Cart Engine ──
    function posAddProductToCart(codeOrMed, qtyToAdd = 1) {
        qtyToAdd = parseInt(qtyToAdd, 10);
        if (isNaN(qtyToAdd) || qtyToAdd < 1) qtyToAdd = 1;

        let med = null;
        if (typeof codeOrMed === 'object' && codeOrMed !== null) {
            med = codeOrMed;
        } else if (typeof codeOrMed === 'string') {
            med = getCatalogItemByCode(codeOrMed);
        }

        if (!med) {
            alert('Product could not be found in the catalog.');
            return false;
        }

        const totalStock = parseInt(med.available_quantity, 10) || 0;
        if (!med.can_sell || totalStock <= 0) {
            alert(`"${med.medication_name}" is currently out of stock or unavailable for sale.`);
            return false;
        }

        const currentQtyInCart = getCartItemQuantity(med.code);
        const requestedTotal = currentQtyInCart + qtyToAdd;

        if (requestedTotal > totalStock) {
            const remaining = Math.max(0, totalStock - currentQtyInCart);
            alert(`Cannot add ${qtyToAdd} unit(s) of "${med.medication_name}". Only ${remaining} remaining in stock (${currentQtyInCart} already in order).`);
            return false;
        }

        const existing = cart.find(entry => entry.code.toLowerCase() === med.code.toLowerCase());
        if (existing) {
            existing.qty += qtyToAdd;
        } else {
            cart.push({
                cartItemId: `${med.code}-${Date.now()}-${Math.random().toString(16).slice(2)}`,
                code: med.code,
                medication_name: med.medication_name,
                generic_name: med.generic_name || '',
                brand_name: med.brand_name || '',
                strength: med.strength || '',
                dosage_form: med.dosage_form || med.dosageForm || '',
                unit_price: parseFloat(med.unit_price),
                available_quantity: totalStock,
                qty: qtyToAdd,
                prescription_item_id: null
            });
        }

        renderCart();
        return true;
    }

    // Global aliases for event handlers & legacy calls
    window.posAddProductToCart = posAddProductToCart;
    window.addProduct = posAddProductToCart;
    window.addToCart = posAddProductToCart;
    window.posHandleCardClick = function(event, codeOrMed) {
        if (event && event.stopPropagation) event.stopPropagation();
        posAddProductToCart(codeOrMed, 1);
    };

    function posStepCartItemQty(cartItemId, delta) {
        const entry = cart.find(i => i.cartItemId === cartItemId);
        if (!entry) return;

        if (delta < 0) {
            if (entry.qty <= 1) {
                posRemoveCartItem(cartItemId);
            } else {
                entry.qty -= 1;
                renderCart();
            }
        } else if (delta > 0) {
            const available = getAvailableStock(entry.code, entry.cartItemId);
            if (available < 1) {
                alert(`No additional stock available for ${entry.medication_name}.`);
                return;
            }
            entry.qty += 1;
            renderCart();
        }
    }

    // ── Render Cart & Calculations ──
    function renderCart() {
        const orderItemsEl = document.getElementById('orderItems');
        const hiddenInputsEl = document.getElementById('posCartHiddenInputs');
        const orderCountEl = document.getElementById('orderCount');

        orderItemsEl.innerHTML = '';

        if (cart.length === 0) {
            orderItemsEl.innerHTML = '<p class="text-center text-muted small py-4 my-auto">No items in this order.</p>';
            hiddenInputsEl.innerHTML = '';
            orderCountEl.textContent = '0 Items';
        } else {
            let html = '';
            let inputsHtml = '';
            let totalQty = 0;

            cart.forEach((entry, idx) => {
                totalQty += entry.qty;
                const lineTotal = entry.unit_price * entry.qty;

                html += `
                    <div class="pos-order-item" id="cart_item_${entry.cartItemId}">
                        <div>
                            <div class="pos-order-item-heading">
                                <strong>${entry.medication_name} ${entry.strength || ''}</strong>
                                <button class="pos-edit-item-btn" type="button"
                                        title="Edit quantity or available strength"
                                        aria-label="Edit order item"
                                        onclick="openEditItem('${entry.cartItemId}')">
                                    <i class="bi bi-pencil-square" style="font-size: 11px;"></i>
                                </button>
                                <button class="pos-edit-item-btn text-danger" type="button"
                                        title="Remove item"
                                        aria-label="Remove order item"
                                        onclick="posRemoveCartItem('${entry.cartItemId}')">
                                    <i class="bi bi-x-lg" style="font-size: 10px;"></i>
                                </button>
                            </div>
                            <div class="d-flex align-items-center gap-1 mt-1">
                                <button class="pos-qty-stepper-btn" type="button" title="Decrease quantity" onclick="posStepCartItemQty('${entry.cartItemId}', -1)">-</button>
                                <span class="pos-qty-badge">${entry.qty}</span>
                                <button class="pos-qty-stepper-btn" type="button" title="Increase quantity" onclick="posStepCartItemQty('${entry.cartItemId}', 1)">+</button>
                                <small class="text-muted ms-1">&times; ${money(entry.unit_price)}</small>
                            </div>
                        </div>
                        <span class="pos-item-price">${money(lineTotal)}</span>
                    </div>
                `;

                inputsHtml += `
                    <input type="hidden" name="items[${idx}][medication_name]" value="${entry.medication_name.replace(/"/g, '&quot;')}">
                    <input type="hidden" name="items[${idx}][medicine_code]" value="${entry.code}">
                    <input type="hidden" name="items[${idx}][quantity]" value="${entry.qty}">
                    ${entry.prescription_item_id ? `<input type="hidden" name="items[${idx}][prescription_item_id]" value="${entry.prescription_item_id}">` : ''}
                `;
            });

            orderItemsEl.innerHTML = html;
            hiddenInputsEl.innerHTML = inputsHtml;
            orderCountEl.textContent = `${totalQty} Item${totalQty === 1 ? '' : 's'}`;
        }

        // Update product card in-cart indicator badges in catalog grid
        document.querySelectorAll('.pos-product-card').forEach(card => {
            const cardCode = card.dataset.code;
            const inCart = cart.find(c => c.code.toLowerCase() === (cardCode || '').toLowerCase());
            const addBadge = card.querySelector('.pos-product-add');
            if (addBadge) {
                if (inCart && inCart.qty > 0) {
                    addBadge.innerHTML = `<span style="font-size: 11px; font-weight: 800;">${inCart.qty}</span>`;
                    addBadge.style.background = 'var(--pos-primary)';
                    addBadge.style.color = '#ffffff';
                } else {
                    addBadge.textContent = '+';
                    addBadge.style.background = '';
                    addBadge.style.color = '';
                }
            }
        });

        // Calculations matching BIR & DTCS Financial Architecture
        const subtotal = cart.reduce((sum, entry) => sum + (entry.unit_price * entry.qty), 0);
        const isVatExempt = (appliedDiscount && (
            appliedDiscount.type === 'senior' ||
            appliedDiscount.type === 'senior_citizen' ||
            appliedDiscount.type === 'pwd' ||
            appliedDiscount.type === 'athlete' ||
            appliedDiscount.type === 'national_athlete'
        ) && (appliedDiscount.rate >= 20));

        let vat = 0;
        let discount = 0;
        let total = 0;

        if (appliedDiscount && appliedDiscount.rawType === 'custom_amount') {
            discount = Math.min(subtotal, Math.round(parseFloat(appliedDiscount.amount || 0) * 100) / 100);
            total = Math.max(0, Math.round((subtotal - discount) * 100) / 100);
            const vatableNet = Math.round((total / (1 + POS_VAT_RATE)) * 100) / 100;
            vat = Math.round((total - vatableNet) * 100) / 100;
        } else if (isVatExempt) {
            // Net of 12% VAT, then statutory discount
            const netBase = Math.round((subtotal / (1 + POS_VAT_RATE)) * 100) / 100;
            discount = Math.round((netBase * (discountPercent / 100)) * 100) / 100;
            vat = 0.00;
            total = Math.max(0, Math.round((netBase - discount) * 100) / 100);
        } else {
            discount = Math.round((subtotal * (discountPercent / 100)) * 100) / 100;
            total = Math.max(0, Math.round((subtotal - discount) * 100) / 100);
            const vatableNet = Math.round((total / (1 + POS_VAT_RATE)) * 100) / 100;
            vat = Math.round((total - vatableNet) * 100) / 100;
        }

        document.getElementById('patientTypeDisplay').textContent =
            patientType ? patientType.toUpperCase() : 'Not selected';

        document.getElementById('subtotal').textContent = money(subtotal);
        document.getElementById('vat').textContent = money(vat);

        let discountDisplayLabel = "Discount";
        if (appliedDiscount) {
            if (appliedDiscount.rawType === 'custom_amount') {
                discountDisplayLabel = `${appliedDiscount.name.toUpperCase()} (₱${parseFloat(appliedDiscount.amount).toFixed(2)})`;
            } else {
                discountDisplayLabel = `${appliedDiscount.name.toUpperCase()} (${appliedDiscount.rate}%)`;
            }
        }
        document.getElementById('discountLabel').textContent = discountDisplayLabel;

        document.getElementById('discount').textContent = money(discount);
        document.getElementById('total').textContent = money(total);

        // Sync hidden checkout form values
        document.getElementById('posFormPatientType').value = patientType ? (patientType.charAt(0).toUpperCase() + patientType.slice(1)) : 'Outpatient';
        document.getElementById('posFormDiscountType').value = appliedDiscount ? (appliedDiscount.type === 'senior' ? 'senior_citizen' : appliedDiscount.type) : 'none';
        document.getElementById('posFormDiscountRef').value = appliedDiscount ? (appliedDiscount.governmentId || '') : '';
        if (document.getElementById('posFormCustomDiscountRate')) {
            document.getElementById('posFormCustomDiscountRate').value = appliedDiscount && appliedDiscount.rawType === 'custom_percentage' ? appliedDiscount.rate : 0;
        }
        if (document.getElementById('posFormCustomDiscountAmount')) {
            document.getElementById('posFormCustomDiscountAmount').value = appliedDiscount && appliedDiscount.rawType === 'custom_amount' ? appliedDiscount.amount : 0;
        }
    }

    function posRemoveCartItem(cartItemId) {
        cart = cart.filter(item => item.cartItemId !== cartItemId);
        renderCart();
    }

    // ── EDIT ORDER ITEM MODAL ──
    function openEditItem(cartItemId) {
        const entry = cart.find(item => item.cartItemId === cartItemId);
        if (!entry) return;

        editingCartItemId = cartItemId;
        const catalogItem = getCatalogItemByCode(entry.code) || entry;

        document.getElementById('editItemName').value = catalogItem.medication_name;

        const strengthSelect = document.getElementById('editItemStrength');
        strengthSelect.innerHTML = '';

        const variants = getVariantsForMedicine(catalogItem);
        const displayVariants = variants.length > 0 ? variants : [catalogItem];

        displayVariants.forEach(v => {
            const opt = document.createElement('option');
            opt.value = v.code;
            const avail = getAvailableStock(v.code, entry.cartItemId);
            opt.textContent = `${v.medication_name} (${v.strength || v.dosage_form}) — ${avail} available [₱${parseFloat(v.unit_price).toFixed(2)}]`;
            if (v.code.toLowerCase() === entry.code.toLowerCase()) {
                opt.selected = true;
            }
            if (avail <= 0 && v.code.toLowerCase() !== entry.code.toLowerCase()) {
                opt.disabled = true;
            }
            strengthSelect.appendChild(opt);
        });

        document.getElementById('editItemQty').value = entry.qty;
        document.getElementById('editItemError').style.display = 'none';

        posUpdateEditStockInfo();
        document.getElementById('editItemModal').classList.add('show');
    }

    function posUpdateEditStockInfo() {
        const selectedCode = document.getElementById('editItemStrength').value;
        const entry = cart.find(item => item.cartItemId === editingCartItemId);
        const available = getAvailableStock(selectedCode, entry ? entry.cartItemId : null);
        const item = getCatalogItemByCode(selectedCode);

        const infoEl = document.getElementById('editItemStockInfo');
        const qtyInput = document.getElementById('editItemQty');

        if (!item || available <= 0) {
            qtyInput.max = 0;
            infoEl.textContent = 'No available stock for this variant. Choose another strength.';
        } else {
            qtyInput.max = available;
            infoEl.textContent = `${available} unit(s) available for this strength. Unit price: ${money(item.unit_price)}.`;
            if (parseInt(qtyInput.value, 10) > available) {
                qtyInput.value = available;
            }
        }
    }

    function posOnEditQtyChange() {
        const qtyInput = document.getElementById('editItemQty');
        const max = parseInt(qtyInput.max || '1', 10);
        let val = parseInt(qtyInput.value || '1', 10);
        if (isNaN(val) || val < 1) val = 1;
        if (val > max) val = max;
        qtyInput.value = val;
    }

    function posSaveEditItem() {
        const entry = cart.find(item => item.cartItemId === editingCartItemId);
        if (!entry) return;

        const selectedCode = document.getElementById('editItemStrength').value;
        const newCatalogItem = getCatalogItemByCode(selectedCode);
        const qtyInput = document.getElementById('editItemQty');
        const qty = parseInt(qtyInput.value, 10);
        const available = getAvailableStock(selectedCode, entry.cartItemId);
        const errorEl = document.getElementById('editItemError');

        if (!newCatalogItem || isNaN(qty) || qty < 1 || qty > available) {
            errorEl.textContent = newCatalogItem
                ? `Enter a quantity from 1 to ${available} for the selected stock strength.`
                : 'Select an available stock strength.';
            errorEl.style.display = 'block';
            return;
        }

        // Update or merge line item
        const existingTarget = cart.find(i => i.cartItemId !== entry.cartItemId && i.code.toLowerCase() === selectedCode.toLowerCase());
        if (existingTarget) {
            existingTarget.qty += qty;
            cart = cart.filter(i => i.cartItemId !== entry.cartItemId);
        } else {
            entry.code = newCatalogItem.code;
            entry.medication_name = newCatalogItem.medication_name;
            entry.strength = newCatalogItem.strength;
            entry.dosage_form = newCatalogItem.dosageForm || newCatalogItem.dosage_form;
            entry.unit_price = parseFloat(newCatalogItem.unit_price);
            entry.available_quantity = newCatalogItem.available_quantity;
            entry.qty = qty;
        }

        posCloseEditItemModal();
        renderCart();
    }

    function posCloseEditItemModal() {
        document.getElementById('editItemModal').classList.remove('show');
        editingCartItemId = null;
    }

    // ── SELECT DISCOUNT MODAL (Styled per user reference image) ──
    function posOpenDiscountModal() {
        if (cart.length === 0) {
            alert('Add products to the order before applying a discount.');
            return;
        }

        const nameInput = document.getElementById('customerName');
        const idInput   = document.getElementById('governmentIdNumber');
        const tinInput  = document.getElementById('customerTin');
        const clearWrap = document.getElementById('clearDiscountWrap');

        if (appliedDiscount) {
            const rawType = appliedDiscount.rawType || (
                appliedDiscount.type.startsWith('senior') ? 'senior' :
                (appliedDiscount.type.startsWith('pwd') ? 'pwd' :
                (appliedDiscount.type.includes('athlete') ? 'athlete' : appliedDiscount.type))
            );
            posSelectDiscountType(rawType);
            if (appliedDiscount.rate) {
                posSelectRate(appliedDiscount.rate);
            }
            if (rawType === 'custom_percentage' && document.getElementById('customPercentValue')) {
                document.getElementById('customPercentValue').value = appliedDiscount.rate;
            }
            if (rawType === 'custom_amount' && document.getElementById('customAmountValue')) {
                document.getElementById('customAmountValue').value = appliedDiscount.amount;
            }
            nameInput.value = appliedDiscount.customerName || '';
            idInput.value   = appliedDiscount.governmentId || '';
            tinInput.value  = appliedDiscount.tin || '';
            if (clearWrap) clearWrap.style.display = 'block';
        } else {
            posSelectDiscountType('senior');
            posSelectRate(20);
            nameInput.value = selectedPatientRecord ? `${selectedPatientRecord.first_name} ${selectedPatientRecord.last_name}` : '';
            idInput.value   = '';
            tinInput.value  = '';
            if (clearWrap) clearWrap.style.display = 'none';
        }

        document.getElementById('discountError').style.display = 'none';
        document.getElementById('discountModal').classList.add('show');
        nameInput.focus();
    }

    function posSelectDiscountType(type) {
        currentSelectedDiscountType = type;
        const def = discountDefinitions[type];
        if (!def) return;

        // Highlight selected card
        document.querySelectorAll('.pos-discount-type-card').forEach(c => c.classList.remove('active'));
        const activeCard = document.getElementById(`card_${type}`);
        if (activeCard) activeCard.classList.add('active');

        // Toggle rate banner
        const banner = document.getElementById('discountRateBanner');
        const customWrap = document.getElementById('customDiscountInputWrap');
        const customPercentField = document.getElementById('customPercentField');
        const customAmountField = document.getElementById('customAmountField');

        if (def.hasRateToggle) {
            banner.style.display = 'flex';
            document.getElementById('rateBannerLabel').textContent = def.rateLabel;
            posSelectRate(currentSelectedDiscountRate || def.defaultRate);
        } else {
            banner.style.display = 'none';
            if (def.rates.length > 0) {
                currentSelectedDiscountRate = def.rates[0];
            }
        }

        // Custom inputs
        if (type === 'custom_percentage') {
            customWrap.style.display = 'block';
            customPercentField.style.display = 'block';
            customAmountField.style.display = 'none';
            currentSelectedDiscountRate = parseFloat(document.getElementById('customPercentValue').value) || 5;
        } else if (type === 'custom_amount') {
            customWrap.style.display = 'block';
            customPercentField.style.display = 'none';
            customAmountField.style.display = 'block';
            currentCustomAmount = parseFloat(document.getElementById('customAmountValue').value) || 50;
        } else {
            customWrap.style.display = 'none';
            customPercentField.style.display = 'none';
            customAmountField.style.display = 'none';
        }

        // Dynamic labels & placeholders
        const labelEl = document.getElementById('governmentIdLabel');
        const inputEl = document.getElementById('governmentIdNumber');
        labelEl.textContent = def.idLabel;
        inputEl.placeholder = def.placeholder;

        posUpdateDiscountSummaryNote();
    }

    function posSelectRate(rate) {
        currentSelectedDiscountRate = rate;
        const pill5 = document.getElementById('ratePill5');
        const pill20 = document.getElementById('ratePill20');
        if (rate === 5) {
            pill5.classList.add('active');
            pill20.classList.remove('active');
        } else {
            pill20.classList.add('active');
            pill5.classList.remove('active');
        }
        posUpdateDiscountSummaryNote();
    }

    function posOnCustomValueChange() {
        if (currentSelectedDiscountType === 'custom_percentage') {
            currentSelectedDiscountRate = parseFloat(document.getElementById('customPercentValue').value) || 0;
        } else if (currentSelectedDiscountType === 'custom_amount') {
            currentCustomAmount = parseFloat(document.getElementById('customAmountValue').value) || 0;
        }
        posUpdateDiscountSummaryNote();
    }

    function posUpdateDiscountSummaryNote() {
        const def = discountDefinitions[currentSelectedDiscountType];
        const textEl = document.getElementById('discountSummaryText');
        if (!def || !textEl) return;

        if (currentSelectedDiscountType === 'senior' || currentSelectedDiscountType === 'pwd') {
            if (currentSelectedDiscountRate === 20) {
                textEl.textContent = `${def.name} statutory discount (20% + 12% VAT Exemption) will be applied.`;
            } else {
                textEl.textContent = `${def.name} special discount (5% rate) will be applied.`;
            }
        } else if (currentSelectedDiscountType === 'custom_percentage') {
            textEl.textContent = `Custom discount (${currentSelectedDiscountRate}% off) will be applied.`;
        } else if (currentSelectedDiscountType === 'custom_amount') {
            textEl.textContent = `Custom discount (₱${currentCustomAmount.toFixed(2)} off) will be applied.`;
        } else {
            textEl.textContent = def.summary;
        }
    }

    function posApplyDiscount() {
        const name = document.getElementById('customerName').value.trim();
        const id   = document.getElementById('governmentIdNumber').value.trim();
        const tin  = document.getElementById('customerTin').value.trim();
        const def  = discountDefinitions[currentSelectedDiscountType];
        const errEl = document.getElementById('discountError');

        if (!name) {
            errEl.textContent = "Please enter the customer's full name as shown on their ID.";
            errEl.style.display = 'block';
            return;
        }

        if (!id) {
            errEl.textContent = `Please enter the ${def ? def.idLabel : 'ID Number'}.`;
            errEl.style.display = 'block';
            return;
        }

        errEl.style.display = 'none';

        // Map to backend discount_type
        let backendType = currentSelectedDiscountType;
        let rate = currentSelectedDiscountRate;

        if (currentSelectedDiscountType === 'senior') {
            backendType = (rate === 5) ? 'senior_5' : 'senior_citizen';
        } else if (currentSelectedDiscountType === 'pwd') {
            backendType = (rate === 5) ? 'pwd_5' : 'pwd';
        } else if (currentSelectedDiscountType === 'athlete') {
            backendType = 'national_athlete';
        }

        appliedDiscount = {
            type: backendType,
            rawType: currentSelectedDiscountType,
            name: def ? def.name : currentSelectedDiscountType,
            rate: rate,
            amount: currentCustomAmount,
            customerName: name,
            governmentId: id,
            tin: tin
        };

        discountPercent = rate;
        document.getElementById('posFormCustomerName').value = name;

        posCloseDiscountModal();
        renderCart();
    }

    function posClearDiscount() {
        appliedDiscount = null;
        discountPercent = 0;
        document.getElementById('customerName').value = '';
        document.getElementById('governmentIdNumber').value = '';
        document.getElementById('customerTin').value = '';
        const clearWrap = document.getElementById('clearDiscountWrap');
        if (clearWrap) clearWrap.style.display = 'none';
        posCloseDiscountModal();
        renderCart();
    }

    function posCloseDiscountModal() {
        document.getElementById('discountModal').classList.remove('show');
    }

    // ── PATIENT TYPE MODAL ──
    function posOpenPatientModal() {
        document.getElementById('patientTypeSelect').value = patientType || '';
        document.getElementById('patientTypeError').style.display = 'none';
        document.getElementById('patientTypeModal').classList.add('show');
    }

    function posOnPatientTypeDropdownChange() {
        document.getElementById('patientTypeError').style.display = 'none';
    }

    function posFilterPatientRegistry() {
        const q = (document.getElementById('patientSearchInput').value || '').toLowerCase().trim();
        document.querySelectorAll('.patient-picker-row').forEach(row => {
            const no = row.dataset.no || '';
            const name = row.dataset.name || '';
            const type = row.dataset.type || '';
            const ward = row.dataset.ward || '';
            const bed = row.dataset.bed || '';
            const match = q === '' || `${no} ${name} ${type} ${ward} ${bed}`.includes(q);
            row.style.display = match ? '' : 'none';
        });
    }

    function posPickPatientRecord(id, no, name, type, ward, bed) {
        selectedPatientRecord = { id, patient_no: no, first_name: name, last_name: '', patient_type: type, ward, bed_number: bed };
        patientType = (type || 'outpatient').toLowerCase();

        document.getElementById('patientTypeSelect').value = patientType;
        document.getElementById('posFormPatientId').value = id;
        document.getElementById('posFormCustomerName').value = name;
        document.getElementById('customerName').value = name;

        posClosePatientTypeModal();
        renderCart();
    }

    function posApplyPatientType() {
        const val = document.getElementById('patientTypeSelect').value;
        if (!['outpatient', 'inpatient'].includes(val)) {
            document.getElementById('patientTypeError').style.display = 'block';
            return;
        }

        patientType = val;
        posClosePatientTypeModal();
        renderCart();
    }

    function posClosePatientTypeModal() {
        document.getElementById('patientTypeModal').classList.remove('show');
    }

    // ── CLEAR ORDER ──
    function posClearOrder() {
        if (cart.length === 0) {
            alert('There are no items to clear.');
            return;
        }

        if (!confirm('Are you sure you want to clear the current order?')) {
            return;
        }

        cart = [];
        discountPercent = 0;
        appliedDiscount = null;
        patientType = null;
        selectedPatientRecord = null;

        document.getElementById('posFormPatientId').value = '';
        document.getElementById('posFormCustomerName').value = '';

        renderCart();
    }

    // ── PROCEED TO PAYMENT ──
    function posHandleProceedToPayment() {
        const messageEl = document.getElementById('paymentMessage');
        const confirmBtn = document.getElementById('confirmPayment');
        const optionsWrap = document.getElementById('paymentOptionsWrap');

        confirmBtn.style.display = 'inline-block';
        if (optionsWrap) optionsWrap.style.display = 'block';

        if (cart.length === 0) {
            messageEl.textContent = 'Your order is empty. Add products before proceeding.';
            confirmBtn.style.display = 'none';
            if (optionsWrap) optionsWrap.style.display = 'none';
        } else if (!patientType) {
            messageEl.textContent = 'Please select the patient type (Outpatient or Inpatient) before proceeding to payment.';
            confirmBtn.style.display = 'none';
            if (optionsWrap) optionsWrap.style.display = 'none';
        } else {
            const totalText = document.getElementById('total').textContent;
            messageEl.textContent = `Patient type: ${patientType.toUpperCase()}. Order total: ${totalText}. Verify tender amount and record payment settlement.`;
            document.getElementById('paymentTenderedInput').placeholder = totalText.replace('₱', '').trim();
            posUpdateTenderChange();
        }

        document.getElementById('paymentModal').classList.add('show');
    }

    function posOnPaymentMethodChange() {
        const method = document.getElementById('paymentMethodSelect').value;
        const cashRow = document.getElementById('cashTenderRow');
        if (cashRow) {
            cashRow.style.display = (method === 'Cash') ? 'flex' : 'none';
        }
        document.getElementById('posFormPaymentMethod').value = method;
    }

    function posUpdateTenderChange() {
        const total = parseFloat((document.getElementById('total').textContent || '0').replace(/[^0-9.]/g, '')) || 0;
        const tendered = parseFloat(document.getElementById('paymentTenderedInput').value) || total;
        const change = Math.max(0, tendered - total);
        document.getElementById('paymentChangeDue').textContent = money(change);
        document.getElementById('posFormAmountTendered').value = tendered.toFixed(2);
    }

    function posClosePaymentModal() {
        document.getElementById('paymentModal').classList.remove('show');
    }

    function posSubmitPayment() {
        if (!patientType) {
            alert('Please select patient type first.');
            return;
        }

        const method = document.getElementById('paymentMethodSelect').value;
        const ref = document.getElementById('paymentRefInput').value.trim();
        document.getElementById('posFormPaymentMethod').value = method;
        document.getElementById('posFormPaymentRef').value = ref;

        posUpdateTenderChange();
        document.getElementById('posCheckoutForm').submit();
    }

    // ── SEARCH & CATEGORY FILTERING ──
    function posFilterCategory(cat) {
        activeCategory = cat.toUpperCase();
        document.querySelectorAll('.pos-category-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.category === activeCategory);
        });
        posFilterCatalogClientSide();
    }

    function posFilterCatalogClientSide() {
        const term = (document.getElementById('search').value || '').toLowerCase().trim();
        const cards = document.querySelectorAll('.pos-product-card');

        cards.forEach(card => {
            const cat = (card.dataset.category || '').toUpperCase();
            const catMatch = (activeCategory === 'ALL ITEMS' || activeCategory === 'ALL ITEM' || cat === activeCategory);

            const haystack = `${card.dataset.code} ${card.dataset.barcode} ${card.dataset.name} ${card.dataset.generic} ${card.dataset.brand}`;
            const searchMatch = term === '' || haystack.includes(term);

            card.style.display = (catMatch && searchMatch) ? '' : 'none';
        });
    }

    function posTriggerBarcodeScan() {
        document.getElementById('search').focus();
        alert('Barcode scanning is active. Scan a product barcode or enter the barcode number into the search field.');
    }

    function posOnSearchKeyDown(event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            const term = (document.getElementById('search').value || '').trim().toLowerCase();
            if (!term) return;

            // 1. Direct barcode match
            let matched = POS_CATALOG.find(c => (c.barcode || '').toLowerCase() === term);

            // 2. Direct code match
            if (!matched) {
                matched = POS_CATALOG.find(c => c.code.toLowerCase() === term);
            }

            // 3. Exact medication name match
            if (!matched) {
                matched = POS_CATALOG.find(c => c.medication_name.toLowerCase() === term);
            }

            // 4. If only 1 visible card in current catalog grid, pick that item
            if (!matched) {
                const visibleCards = Array.from(document.querySelectorAll('.pos-product-card'))
                    .filter(c => c.style.display !== 'none' && !c.disabled);
                if (visibleCards.length === 1) {
                    const cardCode = visibleCards[0].dataset.code;
                    matched = getCatalogItemByCode(cardCode);
                }
            }

            if (matched) {
                const added = posAddProductToCart(matched.code, 1);
                if (added) {
                    const searchInput = document.getElementById('search');
                    searchInput.value = '';
                    posFilterCatalogClientSide();
                }
            } else {
                alert(`No product matching barcode or code "${term}" found.`);
            }
        }
    }

    // ── ADD PRODUCT MODAL LOGIC ──
    function posOpenAddProductModal() {
        const filterInput = document.getElementById('addProductFilter');
        const selectEl = document.getElementById('addProductSelect');
        const qtyInput = document.getElementById('addProductQty');
        const errorEl = document.getElementById('addProductError');

        if (filterInput) filterInput.value = '';
        if (qtyInput) qtyInput.value = '1';
        if (errorEl) errorEl.style.display = 'none';

        // Reset option display
        if (selectEl) {
            Array.from(selectEl.options).forEach(opt => {
                opt.style.display = '';
            });
            // Select first available sellable option
            const firstAvailable = Array.from(selectEl.options).find(opt => opt.value && !opt.disabled);
            if (firstAvailable) {
                selectEl.value = firstAvailable.value;
            } else {
                selectEl.value = '';
            }
        }

        posOnAddProductSelectChange();
        document.getElementById('addProductModal').classList.add('show');
        if (filterInput) filterInput.focus();
    }

    function posCloseAddProductModal() {
        document.getElementById('addProductModal').classList.remove('show');
    }

    function posFilterAddProductOptions() {
        const q = (document.getElementById('addProductFilter').value || '').toLowerCase().trim();
        const selectEl = document.getElementById('addProductSelect');
        if (!selectEl) return;

        let firstMatch = null;
        Array.from(selectEl.options).forEach(opt => {
            if (!opt.value) {
                opt.style.display = '';
                return;
            }
            const name = opt.dataset.name || '';
            const gen = opt.dataset.generic || '';
            const brand = opt.dataset.brand || '';
            const code = opt.dataset.code || '';
            const match = q === '' || `${name} ${gen} ${brand} ${code}`.includes(q);
            opt.style.display = match ? '' : 'none';
            if (match && !opt.disabled && !firstMatch) {
                firstMatch = opt;
            }
        });

        if (firstMatch && (selectEl.selectedOptions.length === 0 || selectEl.selectedOptions[0].style.display === 'none')) {
            selectEl.value = firstMatch.value;
            posOnAddProductSelectChange();
        }
    }

    function posOnAddProductSelectChange() {
        const selectEl = document.getElementById('addProductSelect');
        const code = selectEl ? selectEl.value : '';
        const infoEl = document.getElementById('addProductStockInfo');
        const qtyInput = document.getElementById('addProductQty');
        const maxHint = document.getElementById('addProductMaxHint');
        const errEl = document.getElementById('addProductError');
        if (errEl) errEl.style.display = 'none';

        if (!code) {
            if (infoEl) infoEl.textContent = 'Select a medication to view stock details.';
            if (maxHint) maxHint.textContent = '';
            if (qtyInput) qtyInput.max = '0';
            return;
        }

        const item = getCatalogItemByCode(code);
        if (!item) return;

        const currentlyInCart = getCartItemQuantity(code);
        const available = Math.max(0, parseInt(item.available_quantity, 10) - currentlyInCart);

        if (available <= 0) {
            if (infoEl) infoEl.innerHTML = `<span class="text-danger fw-bold"><i class="bi bi-x-circle me-1"></i> No available stock remaining</span> (${currentlyInCart} already in order, total stock: ${item.available_quantity}).`;
            if (qtyInput) qtyInput.max = '0';
            if (maxHint) maxHint.textContent = '(0 available)';
        } else {
            if (infoEl) infoEl.innerHTML = `<strong>${available} unit(s) available</strong> to add. Unit price: <strong class="text-primary">${money(item.unit_price)}</strong>. Category: <em>${item.category || 'General'}</em>.`;
            if (qtyInput) {
                qtyInput.max = String(available);
                if (parseInt(qtyInput.value, 10) > available) qtyInput.value = String(available);
                if (parseInt(qtyInput.value, 10) < 1) qtyInput.value = '1';
            }
            if (maxHint) maxHint.textContent = `(max ${available})`;
        }
    }

    function posStepAddProductModalQty(delta) {
        const qtyInput = document.getElementById('addProductQty');
        if (!qtyInput) return;
        const max = parseInt(qtyInput.max || '9999', 10);
        let val = (parseInt(qtyInput.value, 10) || 1) + delta;
        if (val < 1) val = 1;
        if (max > 0 && val > max) val = max;
        qtyInput.value = String(val);
        posOnAddProductQtyChange();
    }

    function posOnAddProductQtyChange() {
        const qtyInput = document.getElementById('addProductQty');
        if (!qtyInput) return;
        const max = parseInt(qtyInput.max || '0', 10);
        let val = parseInt(qtyInput.value, 10);
        const errEl = document.getElementById('addProductError');

        if (isNaN(val) || val < 1) {
            val = 1;
            qtyInput.value = '1';
        }

        if (max > 0 && val > max) {
            qtyInput.value = String(max);
            if (errEl) {
                errEl.textContent = `Quantity adjusted to maximum available remaining stock (${max}).`;
                errEl.style.display = 'block';
            }
        } else if (errEl) {
            errEl.style.display = 'none';
        }
    }

    function posSubmitAddProductFromModal() {
        const selectEl = document.getElementById('addProductSelect');
        const code = selectEl ? selectEl.value : '';
        const qtyInput = document.getElementById('addProductQty');
        const qty = parseInt(qtyInput ? qtyInput.value : '1', 10);
        const errEl = document.getElementById('addProductError');

        if (!code) {
            if (errEl) {
                errEl.textContent = 'Please choose a medication from the list.';
                errEl.style.display = 'block';
            }
            return;
        }

        const item = getCatalogItemByCode(code);
        if (!item) {
            if (errEl) {
                errEl.textContent = 'Selected medicine could not be found.';
                errEl.style.display = 'block';
            }
            return;
        }

        const currentlyInCart = getCartItemQuantity(code);
        const available = Math.max(0, parseInt(item.available_quantity, 10) - currentlyInCart);

        if (available <= 0) {
            if (errEl) {
                errEl.textContent = `"${item.medication_name}" has no remaining stock available (${currentlyInCart} already in order).`;
                errEl.style.display = 'block';
            }
            return;
        }

        if (isNaN(qty) || qty < 1 || qty > available) {
            if (errEl) {
                errEl.textContent = `Please enter a valid quantity between 1 and ${available}.`;
                errEl.style.display = 'block';
            }
            return;
        }

        const added = posAddProductToCart(code, qty);
        if (added) {
            posCloseAddProductModal();
        }
    }

    // Modal click-outside dismissal
    ['editItemModal', 'discountModal', 'patientTypeModal', 'paymentModal', 'addProductModal'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('click', e => {
                if (e.target === el) el.classList.remove('show');
            });
        }
    });

    document.addEventListener('DOMContentLoaded', () => {
        renderCart();
    });
</script>
@endsection
