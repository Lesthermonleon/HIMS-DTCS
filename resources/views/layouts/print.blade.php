<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Clinical Document') — HIMS Print System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --hims-blue: #0284c7;
            --hims-dark: #0f172a;
            --hims-border: #cbd5e1;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            font-size: 13px;
            color: #1e293b;
            background-color: #f8fafc;
            line-height: 1.5;
            margin: 0;
            padding: 0;
        }

        .print-container {
            max-width: 800px;
            margin: 20px auto;
            background: #ffffff;
            padding: 35px 40px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
        }

        /* Hospital Letterhead Header */
        .hospital-header {
            border-bottom: 2px solid var(--hims-blue);
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .hospital-title {
            font-size: 20px;
            font-weight: 800;
            color: var(--hims-dark);
            letter-spacing: -0.02em;
            text-transform: uppercase;
        }

        .hospital-subtitle {
            font-size: 12px;
            color: #64748b;
            font-weight: 600;
        }

        .doc-badge {
            font-size: 14px;
            font-weight: 700;
            color: var(--hims-blue);
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            padding: 4px 12px;
            border-radius: 20px;
            display: inline-block;
        }

        /* Patient Metadata Box */
        .patient-info-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 12px 16px;
            margin-bottom: 20px;
        }

        .info-label {
            font-size: 10px;
            text-transform: uppercase;
            font-weight: 700;
            color: #64748b;
            letter-spacing: 0.04em;
        }

        .info-value {
            font-size: 13px;
            font-weight: 600;
            color: #0f172a;
        }

        /* Clinical Data Table */
        .table-clinical {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }

        .table-clinical th {
            background-color: #f1f5f9;
            color: #334155;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            padding: 8px 10px;
            border: 1px solid #cbd5e1;
        }

        .table-clinical td {
            padding: 8px 10px;
            border: 1px solid #e2e8f0;
            font-size: 12.5px;
        }

        /* Signatures Section */
        .signature-section {
            margin-top: 45px;
            page-break-inside: avoid;
        }

        .signature-line {
            border-top: 1px solid #94a3b8;
            margin-top: 35px;
            padding-top: 4px;
            font-weight: 600;
            font-size: 12px;
            color: #334155;
        }

        /* On-Screen Floating Control Toolbar */
        .no-print-toolbar {
            position: fixed;
            top: 15px;
            right: 20px;
            z-index: 9999;
            background: #ffffff;
            padding: 8px 16px;
            border-radius: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
            border: 1px solid #e2e8f0;
            display: flex;
            gap: 10px;
            align-items: center;
        }

        /* Print Media Styles */
        @media print {
            body {
                background: #ffffff !important;
                color: #000000 !important;
                font-size: 11pt !important;
            }

            .no-print-toolbar, .no-print {
                display: none !important;
            }

            .print-container {
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                border: none !important;
                box-shadow: none !important;
                border-radius: 0 !important;
            }

            .table-clinical th {
                background-color: #f1f5f9 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .doc-badge {
                border: 1px solid #000 !important;
                color: #000 !important;
                background: transparent !important;
            }

            @page {
                size: A4 portrait;
                margin: 1.5cm;
            }
        }
    </style>
    @stack('styles')
</head>
<body>
    <!-- Screen View Navigation Bar -->
    <div class="no-print-toolbar">
        <button onclick="window.print()" class="btn btn-sm btn-primary fw-semibold px-3 rounded-pill">
            <i class="bi bi-printer me-1"></i> Print Document
        </button>
        <button onclick="window.close()" class="btn btn-sm btn-outline-secondary px-3 rounded-pill">
            <i class="bi bi-x-circle me-1"></i> Close
        </button>
    </div>

    <!-- Main Printable Document Sheet -->
    <div class="print-container">
        @php
            $hospitalName = app(\App\Services\Settings\SettingsService::class)->get('hospital.name', 'Diagnostic, Treatment & Clinical Services');
            $hospitalTagline = app(\App\Services\Settings\SettingsService::class)->get('hospital.tagline', 'Hospital Information Management System (HIMS)');
            $hospitalAddress = app(\App\Services\Settings\SettingsService::class)->get('hospital.address');
            $hospitalPhone = app(\App\Services\Settings\SettingsService::class)->get('hospital.phone');
        @endphp
        <!-- Hospital Header -->
        <div class="hospital-header d-flex justify-content-between align-items-start">
            <div class="d-flex align-items-center gap-3">
                <img src="{{ route('branding.logo') }}" alt="Logo" style="height: 48px; max-width: 120px; object-fit: contain;" onerror="this.style.display='none'">
                <div>
                    <div class="hospital-title">{{ $hospitalName }}</div>
                    <div class="hospital-subtitle">{{ $hospitalTagline }}</div>
                    @if($hospitalAddress || $hospitalPhone)
                        <div class="text-muted small" style="font-size: 10px;">
                            {{ implode(' · ', array_filter([$hospitalAddress, $hospitalPhone])) }}
                        </div>
                    @endif
                    <div class="text-muted small mt-1">@yield('department', 'Clinical Department')</div>
                </div>
            </div>
            <div class="text-end">
                <div class="doc-badge">@yield('document-title', 'CLINICAL RECORD')</div>
                <div class="text-muted small mt-2">Ref No: <strong>@yield('document-no', '—')</strong></div>
                <div class="text-muted small">Date: {{ now()->format('M d, Y') }}</div>
            </div>
        </div>

        <!-- Main Content View -->
        @yield('content')

        <!-- Document Footer & Verification -->
        <div class="signature-section border-top pt-3">
            <div class="d-flex justify-content-between align-items-end text-muted small" style="font-size: 10px;">
                <div>
                    <div>System ID: HIMS-DOC-{{ date('Ymd') }}-@yield('document-no', '001')</div>
                    <div>Printed by: {{ auth()->user()?->name ?? 'System Staff' }} ({{ auth()->user()?->roles()->first()?->name ?? 'Staff' }})</div>
                    <div>Printed on: {{ now()->format('F d, Y · h:i A') }}</div>
                </div>
                <div class="text-end">
                    <div>Confidential Medical Record</div>
                    <div>Page 1 of 1</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto open print dialog if requested via query string ?auto_print=1
        if (new URLSearchParams(window.location.search).has('auto_print')) {
            window.addEventListener('load', () => window.print());
        }
    </script>
    @stack('scripts')
</body>
</html>
