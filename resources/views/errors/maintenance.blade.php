<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scheduled Maintenance — DTCS HIMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #f8fafc;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }
        .maintenance-card {
            background: rgba(30, 41, 59, 0.85);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 1rem;
            max-width: 580px;
            width: 90%;
            padding: 2.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            text-align: center;
        }
        .maintenance-icon {
            font-size: 3.5rem;
            color: #38bdf8;
            margin-bottom: 1.25rem;
        }
        .badge-expected {
            background-color: rgba(56, 189, 248, 0.15);
            color: #38bdf8;
            border: 1px solid rgba(56, 189, 248, 0.3);
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="maintenance-card">
        <div class="maintenance-icon">
            <i class="bi bi-shield-lock-fill"></i>
        </div>
        <h2 class="fw-bold mb-3">System Under Maintenance</h2>
        <p class="text-slate-300 fs-5 mb-4" style="color: #cbd5e1;">
            {{ $message ?? 'The hospital information system is undergoing scheduled maintenance and upgrades.' }}
        </p>

        @if(!empty($expectedBack))
            <div class="mb-4">
                <span class="badge-expected">
                    <i class="bi bi-clock-history"></i> Expected Completion: {{ $expectedBack }}
                </span>
            </div>
        @endif

        <div class="border-top border-secondary pt-3 mt-4 text-muted small" style="color: #94a3b8 !important;">
            <p class="mb-1">DTCS Hospital Information Management System</p>
            <p class="mb-0">For urgent medical queries, contact the hospital telephone operator.</p>
        </div>
    </div>
</body>
</html>
