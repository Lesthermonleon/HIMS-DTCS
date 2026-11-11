<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Login') — DTCS HIMS</title>
    <link rel="icon" href="{{ asset('assets/images/brand/favicon.svg') }}" type="image/svg+xml" />

    {{-- Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">

    {{-- Icons --}}
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css" />
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/fill/style.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    {{-- Bootstrap CSS --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        /* ── Design tokens ── */
        :root {
            --ink:         oklch(0.31 0.05 165);
            --ink-deep:    oklch(0.25 0.04 165);
            --ink-bright:  oklch(0.98 0.005 155);
            --paper:       oklch(0.98 0.012 155);
            --line:        oklch(0.93 0.02 160);
            --soft:        oklch(0.55 0.035 160);
            --teal:        oklch(0.74 0.14 170);
            --signal:      oklch(0.51 0.13 152);
            --signal-dark: oklch(0.44 0.11 152);
            --font-display: "Space Grotesk", ui-sans-serif, system-ui, sans-serif;
            --font-body:    "Inter", ui-sans-serif, system-ui, sans-serif;
            --font-mono:    "IBM Plex Mono", ui-monospace, SFMono-Regular, monospace;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: var(--font-body);
            font-size: 16px;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        /* ─────────────────────────────────────────
           Full-page: dark green + ECG background
           ───────────────────────────────────────── */
        .page-bg {
            position: relative;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: radial-gradient(ellipse 130% 100% at 50% 0%, #0d4a2e 0%, #072918 55%, #041a0f 100%);
            overflow: hidden;
            padding: 2rem 1rem;
        }
        @media (min-width: 640px) { .page-bg { padding: 2.5rem 2rem; } }

        /* ECG heartbeat traces across full page */
        .ecg-bg-top,
        .ecg-bg-mid,
        .ecg-bg-bot {
            position: absolute;
            left: 0; right: 0;
            pointer-events: none;
            z-index: 0;
            overflow: visible;
        }
        .ecg-bg-top { top: 18%; opacity: 0.07; }
        .ecg-bg-mid { top: 50%; transform: translateY(-50%); opacity: 0.1; }
        .ecg-bg-bot { bottom: 18%; opacity: 0.07; }

        .ecg-bg-line {
            fill: none;
            stroke: #14C79A;
            stroke-width: 2.5;
            stroke-linecap: round;
            stroke-linejoin: round;
            stroke-dasharray: 1200;
            stroke-dashoffset: 1200;
            animation: ecg-sweep 2.6s linear infinite;
        }
        .ecg-bg-line.d1 { animation-delay: 0s; }
        .ecg-bg-line.d2 { animation-delay: 0.87s; opacity: 0.7; }
        .ecg-bg-line.d3 { animation-delay: 1.74s; opacity: 0.5; }
        @keyframes ecg-sweep {
            0%   { stroke-dashoffset: 1200; opacity: 0; }
            4%   { opacity: 1; }
            84%  { opacity: 1; }
            100% { stroke-dashoffset: 0; opacity: 0; }
        }

        /* ─────────────────────────────────────────
           Single Auth Card (Centered)
           ───────────────────────────────────────── */
        .single-auth-card {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 28.75rem;
            margin: 0 auto;
            border-radius: 1.25rem;
            overflow: hidden;
            background: #ffffff;
            box-shadow: 0 24px 48px rgba(0,0,0,0.5), 0 0 0 1px rgba(20,199,154,0.12);
            animation: rise-in 0.5s ease both;
        }
        .single-auth-card .card-form {
            padding: 2rem;
        }
        .single-auth-brand-header {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            padding-bottom: 0.875rem;
            margin-bottom: 1.25rem;
            border-bottom: 1px solid var(--line);
        }
        @media (max-width: 520px) {
            .single-auth-card {
                width: calc(100% - 2rem);
                max-width: 390px;
            }
            .single-auth-card .card-form {
                padding: 1.5rem 1.25rem;
            }
        }
        @media (max-width: 360px) {
            .single-auth-card {
                width: calc(100% - 1.25rem);
                max-width: 330px;
            }
            .single-auth-card .card-form {
                padding: 1.25rem 1rem;
            }
        }

        /* ─────────────────────────────────────────
           Split card
           ───────────────────────────────────────── */
        .split-card {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 21.5rem;
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 24px 48px rgba(0,0,0,0.5), 0 0 0 1px rgba(20,199,154,0.12);
            animation: rise-in 0.5s ease both;
        }
        @media (min-width: 440px) {
            .split-card { max-width: 23.5rem; }
        }
        @media (min-width: 640px) {
            .split-card { max-width: 26rem; border-radius: 1.25rem; }
        }
        @media (min-width: 1024px) {
            .split-card {
                max-width: 50rem;
                display: grid;
                grid-template-columns: 1fr 1fr;
            }
        }

        /* ── Left brand side (inside card) ── */
        .card-brand {
            display: none;
            position: relative;
            overflow: hidden;
            flex-direction: column;
            justify-content: space-between;
            padding: 2.5rem;
            min-height: 100%;
        }
        @media (min-width: 1024px) {
            .card-brand { display: flex; }
        }

        /* Hospital photo + overlay */
        .card-brand-photo {
            position: absolute;
            inset: 0;
            background-image: url('/assets/images/brand/hospital_bg.png');
            background-size: cover;
            background-position: center;
            animation: kb-zoom 22s ease-in-out infinite alternate;
            will-change: transform;
        }
        @keyframes kb-zoom {
            0%   { transform: scale(1.0) translate(0, 0); }
            100% { transform: scale(1.08) translate(-1%, 1.5%); }
        }
        .card-brand-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(
                160deg,
                rgba(5, 22, 15, 0.84) 0%,
                rgba(10, 35, 25, 0.72) 50%,
                rgba(5, 22, 15, 0.90) 100%
            );
        }
        .card-brand-glow {
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 45%;
            pointer-events: none;
            background: radial-gradient(ellipse 80% 60% at 50% 100%, rgba(20,199,154,0.14) 0%, transparent 70%);
            animation: glow-pulse 4s ease-in-out infinite alternate;
        }
        @keyframes glow-pulse { from { opacity: 0.7; } to { opacity: 1; } }

        /* Brand content inside card */
        .card-brand-top {
            position: relative;
            z-index: 10;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .cb-pulse { width: 2.25rem; height: 2.25rem; flex-shrink: 0; filter: drop-shadow(0 0 8px rgba(20,199,154,0.4)); }

        .cb-name {
            font-family: var(--font-display);
            font-size: 1.1rem;
            font-weight: 700;
            letter-spacing: -0.025em;
            color: var(--ink-bright);
            white-space: nowrap;
        }
        .cb-sub {
            font-family: var(--font-mono);
            font-size: 0.55rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.18em;
            color: oklch(0.98 0.005 155 / 0.4);
        }

        .card-brand-mid { position: relative; z-index: 10; }
        .card-brand-mid h2 {
            font-family: var(--font-display);
            font-size: 1.75rem;
            font-weight: 700;
            line-height: 1.15;
            letter-spacing: -0.025em;
            color: var(--ink-bright);
        }
        .card-brand-mid h2 .accent { color: var(--teal); }
        .card-brand-mid p {
            margin-top: 0.875rem;
            font-size: 0.8rem;
            line-height: 1.7;
            color: oklch(0.98 0.005 155 / 0.6);
        }

        .cb-features { margin-top: 1.25rem; list-style: none; display: grid; gap: 0.625rem; }
        .cb-features li { display: flex; align-items: center; gap: 0.625rem; }
        .cb-feature-icon {
            display: flex;
            height: 1.875rem; width: 1.875rem;
            flex-shrink: 0;
            align-items: center; justify-content: center;
            border-radius: 0.4rem;
            border: 1px solid rgba(20,199,154,0.2);
            background: rgba(20,199,154,0.1);
            color: var(--teal);
        }
        .cb-feature-icon svg { width: 0.875rem; height: 0.875rem; }
        .cb-features span.label { font-size: 0.8rem; color: oklch(0.98 0.005 155 / 0.8); }

        .cb-status {
            position: relative;
            z-index: 10;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border-radius: 9999px;
            border: 1px solid rgba(20,199,154,0.22);
            background: rgba(20,199,154,0.08);
            backdrop-filter: blur(6px);
            padding: 0.35rem 0.8rem;
        }
        .cb-status-dot {
            width: 5px; height: 5px;
            border-radius: 9999px;
            background: #14C79A;
            flex-shrink: 0;
            animation: blink-dot 1.8s ease-in-out infinite;
        }
        .cb-status-text {
            font-family: var(--font-mono);
            font-size: 0.58rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: oklch(0.98 0.005 155 / 0.7);
        }
        @keyframes blink-dot { 0%, 100% { opacity: 1; } 50% { opacity: 0.2; } }

        /* ── Right form side (inside card) ── */
        .card-form {
            background: #fff;
            padding: 1.25rem 1rem;
        }
        @media (min-width: 440px) { .card-form { padding: 1.5rem 1.25rem; } }
        @media (min-width: 640px) { .card-form { padding: 2rem; } }

        .card-head {
            margin-bottom: 1rem;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.5rem;
        }
        .card-head h2 {
            font-family: var(--font-display);
            font-size: 1.15rem;
            font-weight: 600;
            letter-spacing: -0.025em;
            color: var(--ink);
            margin: 0;
        }
        .card-head .hint { margin-top: 0.15rem; font-size: 0.8rem; color: var(--soft); }

        .badge-secured {
            display: block;
            flex-shrink: 0;
            border-radius: 9999px;
            border: 1px solid rgba(20,199,154,0.3);
            background: rgba(20,199,154,0.1);
            padding: 0.2rem 0.65rem;
            font-family: var(--font-mono);
            font-size: 0.58rem;
            font-weight: 600;
            letter-spacing: 0.15em;
            color: var(--signal-dark);
            white-space: nowrap;
        }

        /* ── Compact mobile brand ── */
        .mobile-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding-bottom: 0.875rem;
            margin-bottom: 1.25rem;
            border-bottom: 1px solid var(--line);
        }
        @media (min-width: 1024px) {
            .mobile-brand { display: none !important; }
        }

        /* ── Form controls ── */
        .field-label {
            display: block;
            margin-bottom: 0.375rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--ink);
        }
        .label-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.375rem;
        }
        .label-header .field-label {
            margin-bottom: 0;
        }
        .input-wrap { position: relative; margin-bottom: 0; }
        .input-wrap > svg.leading {
            position: absolute;
            left: 0.75rem; top: 50%;
            transform: translateY(-50%);
            width: 1rem; height: 1rem;
            color: var(--soft);
            pointer-events: none;
        }
        .auth-input {
            width: 100%;
            border-radius: 0.5rem;
            border: 1px solid var(--line);
            background: #fcfdfd;
            padding: 0.65rem 0.75rem 0.65rem 2.5rem;
            font-family: var(--font-body);
            font-size: 0.875rem;
            color: var(--ink);
            outline: none;
            transition: border-color 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
        }
        .input-wrap.has-toggle .auth-input { padding-right: 2.5rem; }
        .auth-input::placeholder { color: oklch(0.55 0.035 160 / 0.7); }
        .auth-input:focus {
            background: #ffffff;
            border-color: #14C79A;
            box-shadow: 0 0 0 3.5px rgba(20,199,154,0.18);
        }
        .auth-input.is-invalid { border-color: #dc3545; }
        .auth-input.is-invalid:focus { box-shadow: 0 0 0 3px rgba(220,53,69,0.18); }
        .invalid-feedback { display: block; font-size: 0.78rem; color: #dc3545; margin-top: 0.25rem; }

        .toggle-pw {
            position: absolute;
            right: 0.75rem; top: 50%;
            transform: translateY(-50%);
            border: none; background: none; padding: 0.25rem;
            color: var(--soft); cursor: pointer;
            border-radius: 0.25rem;
            transition: color 0.15s ease, background-color 0.15s ease;
        }
        .toggle-pw:hover { color: var(--signal); background-color: rgba(20,199,154,0.08); }
        .toggle-pw svg { width: 1rem; height: 1rem; display: block; }

        a.forgot {
            flex-shrink: 0;
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--signal-dark);
            text-decoration: none;
            transition: color 0.15s ease, text-decoration 0.15s ease;
        }
        a.forgot:hover { color: #0d9673; text-decoration: underline; text-underline-offset: 3px; }

        .btn-auth {
            display: flex;
            width: 100%;
            align-items: center;
            justify-content: center;
            gap: 0.625rem;
            border: none;
            border-radius: 0.5rem;
            background: linear-gradient(135deg, #14C79A 0%, #0d9673 100%);
            padding: 0.75rem 1rem;
            font-family: var(--font-display);
            font-size: 0.9rem;
            font-weight: 600;
            color: #ffffff;
            box-shadow: 0 8px 20px -4px rgba(20,199,154,0.32);
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .btn-auth:hover {
            background: linear-gradient(135deg, #16d8a7 0%, #0ea37e 100%);
            box-shadow: 0 12px 24px -4px rgba(20,199,154,0.42);
            transform: translateY(-1px);
            color: #ffffff;
        }
        .btn-auth:active {
            transform: translateY(0) scale(0.985);
            box-shadow: 0 4px 12px -2px rgba(20,199,154,0.25);
        }
        .btn-auth:disabled { opacity: 0.6; transform: none; cursor: not-allowed; box-shadow: none; }
        .btn-auth .btn-icon {
            width: 1.125rem;
            height: 1.125rem;
            transition: transform 0.2s ease;
        }
        .btn-auth:hover .btn-icon {
            transform: translateX(3px);
        }

        .legal {
            margin-top: 1.5rem;
            text-align: center;
            font-family: var(--font-mono);
            font-size: 0.65rem;
            line-height: 1.7;
            color: var(--soft);
        }

        /* ── Alerts ── */
        .alert {
            position: relative;
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
            border-radius: 0.5rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            font-size: 0.83rem;
            font-family: var(--font-body);
            border: 1px solid var(--line);
        }
        .alert-success  { background: rgba(20,199,154,0.12) !important; color: var(--ink) !important; }
        .alert-danger   { background: rgba(220,38,38,0.08) !important; border-left: 4px solid #dc2626 !important; color: var(--ink) !important; }
        .alert-warning  { background: rgba(234,179,8,0.1) !important; border-left: 4px solid #ca8a04 !important; color: var(--ink) !important; }
        .alert .alert-icon { align-self: flex-start; }
        .invalid-feedback { font-size: .78rem; }

        /* ── Loader overlay ── */
        .overlay {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: oklch(0.31 0.05 165 / 0.45);
            backdrop-filter: blur(4px);
        }
        .overlay.show { display: flex; }
        .spinner-big {
            width: 4rem;
            height: 4rem;
            border-radius: 9999px;
            border: 4px solid rgba(20, 199, 154, 0.2);
            border-top-color: var(--teal);
            animation: spin 0.8s linear infinite;
        }
        .overlay p {
            margin-top: 1rem;
            font-family: var(--font-display);
            font-weight: 600;
            letter-spacing: 0.025em;
            color: var(--ink-bright);
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        @media (prefers-reduced-motion: reduce) {
            .card-brand-photo { animation: none; }
            .card-brand-glow  { animation: none; opacity: 0.8; }
            .ecg-bg-line      { animation: none; opacity: 0; }
            .split-card       { animation: none; }
        }
    </style>
</head>
<body>

{{-- ═══════════ Full-page green + ECG ═══════════ --}}
<div class="page-bg">

    {{-- ECG heartbeat trace — single middle row --}}
    <svg class="ecg-bg-mid" viewBox="0 0 900 50" preserveAspectRatio="none" aria-hidden="true">
        <polyline class="ecg-bg-line d2" points="0,25 60,25 80,25 95,8 103,42 111,2 119,48 127,25 160,25 220,25 240,25 255,8 263,42 271,2 279,48 287,25 320,25 380,25 400,25 415,8 423,42 431,2 439,48 447,25 480,25 540,25 560,25 575,8 583,42 591,2 599,48 607,25 640,25 700,25 720,25 735,8 743,42 751,2 759,48 767,25 800,25 860,25 880,25 900,25"/>
        <polyline class="ecg-bg-line d3" points="0,25 60,25 80,25 95,8 103,42 111,2 119,48 127,25 160,25 220,25 240,25 255,8 263,42 271,2 279,48 287,25 320,25 380,25 400,25 415,8 423,42 431,2 439,48 447,25 480,25 540,25 560,25 575,8 583,42 591,2 599,48 607,25 640,25 700,25 720,25 735,8 743,42 751,2 759,48 767,25 800,25 860,25 880,25 900,25"/>
    </svg>

    @hasSection('single_card')
        {{-- ═══ Single centered authentication card ═══ --}}
        <div class="single-auth-card">
            <div class="card-form">
                {{-- Compact DTCS HIMS Header --}}
                <div class="single-auth-brand-header">
                    <svg class="cb-pulse" viewBox="0 0 38 38" aria-hidden="true" style="width: 2.25rem; height: 2.25rem;">
                        <rect width="38" height="38" rx="9" fill="#0d4a2e"/>
                        <polyline points="3,19 9,19 12,12 14,26 17,8 19,30 21,14 23,22 26,19 35,19"
                            fill="none" stroke="#14C79A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <div>
                        <span style="font-family: var(--font-display); font-weight: 700; font-size: 1.1rem; color: var(--ink); display: block; line-height: 1.2;">DTCS HIMS</span>
                        <span style="font-family: var(--font-mono); font-size: 0.58rem; color: var(--soft); text-transform: uppercase; letter-spacing: 0.12em;">Diagnostic, Treatment &amp; Clinical Services</span>
                    </div>
                </div>

                <div class="card-head">
                    <div>
                        <h2>@yield('card-title', 'Forgot Password')</h2>
                        <p class="hint">@yield('card-hint', 'Enter your registered email address and we\'ll send you a password reset link.')</p>
                    </div>
                    <span class="badge-secured">SECURED ACCESS</span>
                </div>

                @yield('content')

                <p class="legal">
                    Protected by session-based authentication &amp; rate limiting.<br>
                    Unauthorized access is strictly prohibited.
                </p>
            </div>
        </div>
    @else
        {{-- ═══ Split card ═══ --}}
        <div class="split-card">

            {{-- ── Left: hospital photo brand panel ── --}}
            <div class="card-brand">
                <div class="card-brand-photo" aria-hidden="true"></div>
                <div class="card-brand-overlay" aria-hidden="true"></div>
                <div class="card-brand-glow" aria-hidden="true"></div>

                {{-- Top: logo --}}
                <div class="card-brand-top">
                    <svg class="cb-pulse" viewBox="0 0 38 38" aria-hidden="true">
                        <rect width="38" height="38" rx="9" fill="rgba(5,22,15,0.7)"/>
                        <polyline points="3,19 9,19 12,12 14,26 17,8 19,30 21,14 23,22 26,19 35,19"
                            fill="none" stroke="#14C79A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <div>
                        <p class="cb-name">DTCS HIMS</p>
                        <p class="cb-sub">Diagnostic, Treatment &amp; Clinical Services</p>
                    </div>
                </div>

                {{-- Mid: pitch --}}
                <div class="card-brand-mid">
                    <h2>One record.<br>Every department.<br><span class="accent">Real time.</span></h2>
                    <p>The hospital information system for Diagnostic, Treatment &amp; Clinical Services.</p>
                </div>
            </div>

            {{-- ── Right: login form ── --}}
            <div class="card-form">
                {{-- Mobile-only Brand Header --}}
                <div class="mobile-brand">
                    <svg class="cb-pulse" viewBox="0 0 38 38" aria-hidden="true" style="width: 2rem; height: 2rem;">
                        <rect width="38" height="38" rx="9" fill="#0d4a2e"/>
                        <polyline points="3,19 9,19 12,12 14,26 17,8 19,30 21,14 23,22 26,19 35,19"
                            fill="none" stroke="#14C79A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <div>
                        <span style="font-family: var(--font-display); font-weight: 700; font-size: 1rem; color: var(--ink); display: block; line-height: 1.2;">DTCS HIMS</span>
                        <span style="font-family: var(--font-mono); font-size: 0.58rem; color: var(--soft); text-transform: uppercase; letter-spacing: 0.12em;">Diagnostic, Treatment &amp; Clinical Services</span>
                    </div>
                </div>

                <div class="card-head">
                    <div>
                        <h2>@yield('card-title', 'Staff Sign In')</h2>
                        <p class="hint">@yield('card-hint', 'Use your hospital email to continue.')</p>
                    </div>
                    <span class="badge-secured">SECURED ACCESS</span>
                </div>

                @yield('content')

                <p class="legal">
                    Protected by session-based authentication &amp; rate limiting.<br>
                    Unauthorized access is strictly prohibited.
                </p>
            </div>

        </div>{{-- /split-card --}}
    @endif
</div>{{-- /page-bg --}}

{{-- Full-screen loader overlay --}}
<div class="overlay" id="overlay" aria-hidden="true">
    <div class="spinner-big"></div>
    <p>Signing in…</p>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
(function () {
    'use strict';

    function getOverlay() {
        return document.getElementById('overlay');
    }

    function showOverlay() {
        var overlay = getOverlay();
        if (overlay) {
            overlay.classList.add('show');
            overlay.setAttribute('aria-hidden', 'false');
        }
    }

    function hideOverlay() {
        var overlay = getOverlay();
        if (overlay) {
            overlay.classList.remove('show');
            overlay.setAttribute('aria-hidden', 'true');
        }
    }

    /* ── Hide overlay on initial load & bfcache navigation ── */
    window.addEventListener('pageshow', function () {
        hideOverlay();
    });

    document.addEventListener('DOMContentLoaded', function () {
        hideOverlay();

        /* ── Global Alert Icons Injector ── */
        document.querySelectorAll('.alert').forEach(function (alert) {
            if (!alert.querySelector('.ph-fill, .bi, .alert-icon')) {
                var iconClass = 'ph-fill ph-info';
                if (alert.classList.contains('alert-success'))      { iconClass = 'ph-fill ph-check-circle'; }
                else if (alert.classList.contains('alert-danger'))  { iconClass = 'ph-fill ph-warning-circle'; }
                else if (alert.classList.contains('alert-warning')) { iconClass = 'ph-fill ph-warning'; }
                var icon = document.createElement('i');
                icon.className = iconClass + ' alert-icon';
                alert.insertBefore(icon, alert.firstChild);
            }
        });
    });

    /* ── Show overlay on valid form submission (no artificial delays/timers) ── */
    document.addEventListener('submit', function (e) {
        if (e.defaultPrevented) return;
        showOverlay();
    });

    /* ── Hide overlay if form validation fails in browser ── */
    document.addEventListener('invalid', function () {
        hideOverlay();
    }, true);

    /* ── Frontend Browser Interaction Restrictions (Authentication Pages Only) ── */
    // Disable Right-Click / Context Menu
    document.addEventListener('contextmenu', function (e) {
        e.preventDefault();
    });

    // Disable DevTools, View Source, and Save Page Shortcuts
    document.addEventListener('keydown', function (e) {
        var key = e.key ? e.key.toUpperCase() : '';
        var keyCode = e.keyCode || e.which;
        var isCtrlOrCmd = e.ctrlKey || e.metaKey;
        var isAltOrOpt = e.altKey;

        // F12 Key
        if (key === 'F12' || keyCode === 123) {
            e.preventDefault();
            return;
        }

        // Ctrl+Shift+I, J, C, K OR Cmd+Option+I, J, C, K (DevTools & Inspector)
        if ((isCtrlOrCmd && e.shiftKey) || (e.metaKey && isAltOrOpt)) {
            if (key === 'I' || key === 'J' || key === 'C' || key === 'K') {
                e.preventDefault();
                return;
            }
        }

        // Ctrl+U / Cmd+U (View Source) & Ctrl+S / Cmd+S (Save Page)
        if (isCtrlOrCmd && !e.shiftKey && !isAltOrOpt) {
            if (key === 'U' || key === 'S') {
                e.preventDefault();
                return;
            }
        }
    });
})();
</script>

@yield('scripts')

</body>
</html>
