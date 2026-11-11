@extends('layouts.guest')
@section('title', 'Verify Your Identity')
@section('card-title', 'Verify Your Identity')
@section('card-hint', 'Enter the 6-digit code sent to your registered email.')

@section('content')

<style>
    /* ── Balanced Single OTP Card (Desktop: 460px width + compact vertical spacing) ── */
    .split-card {
        display: block !important;
        max-width: 460px !important;
        margin: 0 auto;
    }
    .card-brand, .mobile-brand {
        display: none !important;
    }
    .card-form {
        padding: 1.5rem 1.75rem !important;
    }
    .card-head {
        margin-bottom: 0.85rem !important;
    }
    .otp-form-group {
        margin-bottom: 0.75rem !important;
    }
    .otp-input {
        height: 54px !important;
        font-size: 1.35rem !important;
    }
    #otp-countdown-wrap {
        margin: 0.45rem 0 0.75rem !important;
        font-size: 0.78rem !important;
    }
    .btn-auth-verify {
        height: 42px !important;
        padding: 0.6rem 1rem !important;
    }
    .otp-divider {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        margin: 0.85rem 0 0.65rem !important;
    }
    .resend-btn {
        height: 38px !important;
        padding: 0.45rem 1rem !important;
    }
    .otp-back-wrap {
        margin-top: 0.65rem !important;
        text-align: center;
    }
    .card-form .legal {
        margin-top: 1rem !important;
    }

    /* ── Floating OTP Toast Notification Styling ── */
    .otp-sent-toast {
        position: fixed;
        top: 1.5rem;
        right: 1.5rem;
        z-index: 9999;
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        background: #ffffff;
        color: #0f172a;
        border: 1px solid rgba(20, 199, 154, 0.35);
        border-radius: 0.65rem;
        padding: 0.85rem 1rem;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.2), 0 4px 10px -2px rgba(0, 0, 0, 0.1);
        max-width: 420px;
        width: calc(100% - 3rem);
        transition: opacity 0.4s ease, transform 0.4s ease;
        opacity: 1;
        transform: translateY(0);
    }
    .otp-sent-toast.hide {
        opacity: 0;
        transform: translateY(-12px);
        pointer-events: none;
    }
    .toast-icon-wrap {
        font-size: 1.25rem;
        color: var(--signal-dark, #14C79A);
        line-height: 1;
        margin-top: 0.1rem;
    }
    .toast-body-wrap {
        flex: 1;
    }
    .toast-header-title {
        font-family: var(--font-display, inherit);
        font-weight: 700;
        font-size: 0.82rem;
        color: #0f172a;
        letter-spacing: 0.02em;
        margin-bottom: 0.2rem;
    }
    .toast-message-text {
        font-size: 0.78rem;
        line-height: 1.45;
        color: #475569;
    }
    .toast-message-text strong {
        font-family: var(--font-mono, monospace);
        color: #0f172a;
    }
    .toast-close-btn {
        background: transparent;
        border: none;
        color: #94a3b8;
        font-size: 1.25rem;
        line-height: 1;
        cursor: pointer;
        padding: 0 0.2rem;
        margin-left: 0.25rem;
        transition: color 0.2s ease;
    }
    .toast-close-btn:hover {
        color: #0f172a;
    }

    /* ── Responsive Mobile Refinement (< 520px) ── */
    @media (max-width: 520px) {
        .split-card {
            width: calc(100% - 2rem) !important;
            max-width: 390px !important;
            margin: 0 auto;
        }
        .card-form {
            padding: 1.25rem 1.15rem !important;
        }
        .card-head {
            display: block !important;
            margin-bottom: 0.75rem !important;
        }
        .card-head h2 {
            font-size: 1.15rem !important;
        }
        .badge-secured {
            display: inline-block !important;
            margin-top: 0.45rem !important;
        }
        .otp-form-group {
            margin-bottom: 0.65rem !important;
        }
        .otp-input {
            max-width: 240px !important;
            height: 48px !important;
            font-size: 1.25rem !important;
            padding-left: 2rem !important;
        }
        #otp-countdown-wrap {
            margin: 0.4rem 0 0.65rem !important;
            font-size: 0.7rem !important;
        }
        .btn-auth-verify {
            height: 40px !important;
            font-size: 0.85rem !important;
        }
        .otp-divider {
            margin: 0.75rem 0 0.6rem !important;
        }
        .resend-btn {
            height: 36px !important;
            font-size: 0.78rem !important;
        }
        .otp-back-wrap {
            margin-top: 0.6rem !important;
        }
        .card-form .legal {
            margin-top: 0.85rem !important;
            font-size: 0.62rem !important;
        }
        .otp-sent-toast {
            top: 1rem;
            left: 1rem;
            right: 1rem;
            width: calc(100% - 2rem);
            max-width: none;
            padding: 0.75rem 0.85rem;
        }
    }

    /* ── Extra Small Screens (< 360px) ── */
    @media (max-width: 360px) {
        .split-card {
            width: calc(100% - 1.25rem) !important;
            max-width: 330px !important;
        }
        .card-form {
            padding: 1rem 0.85rem !important;
        }
        .otp-input {
            max-width: 210px !important;
            height: 46px !important;
            font-size: 1.15rem !important;
        }
    }
</style>

    {{-- Floating Top-Right Toast Notification for OTP Sent Message --}}
    <div id="otp-sent-toast" class="otp-sent-toast" role="alert" aria-live="polite">
        <div class="toast-icon-wrap">
            <i class="bi bi-envelope-check"></i>
        </div>
        <div class="toast-body-wrap">
            <div class="toast-header-title">Security Verification</div>
            <div class="toast-message-text">
                A 6-digit verification code has been sent to <strong>{{ $maskedEmail }}</strong>. The code expires in <strong>{{ $expiresMinutes }} minutes</strong>.
            </div>
        </div>
        <button type="button" class="toast-close-btn" onclick="dismissOtpToast()" aria-label="Close notification">&times;</button>
    </div>

    {{-- Success: new OTP sent session message --}}
    @if (session('resend_success'))
        <div class="alert alert-success mb-3">
            <i class="bi bi-envelope-check alert-icon"></i>
            <span>{{ session('resend_success') }}</span>
        </div>
    @endif

    {{-- Resend error (cooldown / rate limit) --}}
    @if (session('resend_error'))
        <div class="alert alert-warning mb-3">
            <i class="bi bi-clock alert-icon"></i>
            <span>{{ session('resend_error') }}</span>
        </div>
    @endif

    {{-- OTP verification error --}}
    @error('otp')
        <div class="alert alert-danger mb-3">
            <i class="bi bi-shield-exclamation alert-icon"></i>
            <span>{{ $message }}</span>
        </div>
    @enderror

    {{-- General session error (exhausted attempts redirect) --}}
    @if (session('error'))
        <div class="alert alert-danger mb-3">
            <i class="bi bi-shield-exclamation alert-icon"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    {{-- OTP submission form (exact auth-form-grid & auth-input styling as Staff Sign In) --}}
    <form method="POST" action="{{ route('otp.verify.submit') }}" id="otp-form" class="auth-form-grid">
        @csrf

        <div class="form-group otp-form-group">
            <label class="field-label" for="otp">Verification Code</label>
            <div class="input-wrap">
                <svg class="leading" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                </svg>
                <input
                    type="text"
                    id="otp"
                    name="otp"
                    inputmode="numeric"
                    pattern="\d{6}"
                    maxlength="6"
                    autocomplete="one-time-code"
                    class="auth-input otp-input @error('otp') is-invalid @enderror"
                    placeholder="000000"
                    style="font-family: var(--font-mono); font-size: 1.35rem; letter-spacing: 0.35em; text-align: center; padding-left: 2.5rem;"
                    autofocus
                    required>
            </div>
        </div>

        {{-- Expiry countdown --}}
        <div id="otp-countdown-wrap" class="text-center" style="color: var(--soft);"
             data-expires-minutes="{{ $expiresMinutes }}">
            Code expires in <strong id="otp-countdown" style="font-family: var(--font-mono); color: var(--ink);">{{ $expiresMinutes }}:00</strong>
        </div>

        {{-- Submit (exact primary btn-auth from Staff Sign In) --}}
        <button type="submit" class="btn-auth btn-auth-verify" id="otp-submit-btn">
            <span>Verify Code</span>
            <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                <polyline points="9 12 11 14 15 10"/>
            </svg>
        </button>
    </form>

    {{-- Separator --}}
    <div class="otp-divider">
        <div style="flex: 1; height: 1px; background: var(--line);"></div>
        <span style="font-size: 0.72rem; color: var(--soft); font-family: var(--font-mono); text-transform: uppercase; letter-spacing: 0.1em; white-space: nowrap;">Didn't receive the code?</span>
        <div style="flex: 1; height: 1px; background: var(--line);"></div>
    </div>

    {{-- Resend form --}}
    <form method="POST" action="{{ route('otp.resend') }}" id="resend-form">
        @csrf
        @if ($resendCooldown > 0)
            <button type="submit"
                    id="resend-btn"
                    class="btn-auth resend-btn"
                    disabled
                    data-cooldown="{{ $resendCooldown }}"
                    style="background: #f8fafc; color: var(--soft); border: 1px solid var(--line); box-shadow: none; font-size: 0.82rem;">
                Resend OTP <span id="resend-countdown">({{ $resendCooldown }}s)</span>
            </button>
        @else
            <button type="submit"
                    id="resend-btn"
                    class="btn-auth resend-btn"
                    style="background: transparent; color: var(--signal-dark); border: 1px solid rgba(20,199,154,0.35); box-shadow: none; font-size: 0.82rem;">
                Resend OTP
            </button>
        @endif
    </form>

    {{-- Back to login --}}
    <div class="otp-back-wrap">
        <a href="{{ route('login') }}" class="forgot" onclick="document.getElementById('overlay').classList.remove('show')">
            &larr; Back to Sign In
        </a>
    </div>

@endsection

@section('scripts')
<script>
function dismissOtpToast() {
    var toastEl = document.getElementById('otp-sent-toast');
    if (toastEl) {
        toastEl.classList.add('hide');
        setTimeout(function () {
            if (toastEl.parentNode) {
                toastEl.parentNode.removeChild(toastEl);
            }
        }, 450);
    }
}

document.addEventListener('DOMContentLoaded', function () {

    // ── 0. Toast Notification Auto-Dismiss (6 seconds) ──────────────────────
    var toastEl = document.getElementById('otp-sent-toast');
    if (toastEl) {
        setTimeout(function () {
            dismissOtpToast();
        }, 6000);
    }

    // ── 1. OTP Expiry Countdown ───────────────────────────────────────────────
    var wrapEl         = document.getElementById('otp-countdown-wrap');
    var expiresMinutes = wrapEl ? parseInt(wrapEl.dataset.expiresMinutes, 10) : 3;
    var totalSeconds   = expiresMinutes * 60;
    var countdownEl    = document.getElementById('otp-countdown');
    var submitBtn      = document.getElementById('otp-submit-btn');
    var otpInput       = document.getElementById('otp');

    if (countdownEl && totalSeconds > 0) {
        var remaining = totalSeconds;

        var expiryTimer = setInterval(function () {
            remaining--;
            if (remaining <= 0) {
                clearInterval(expiryTimer);
                if (countdownEl) {
                    countdownEl.textContent = '00:00';
                    countdownEl.style.color = '#dc2626';
                }
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.style.opacity = '0.5';
                    submitBtn.style.cursor = 'not-allowed';
                }
                if (wrapEl) {
                    wrapEl.style.color = '#b91c1c';
                    wrapEl.innerHTML = '<strong style="font-family:var(--font-mono);color:#dc2626;">The verification code has expired.</strong> <a href="' + window.location.href + '" style="color:var(--signal-dark);font-size:0.78rem;">Request a new code.</a>';
                }
            } else {
                var mins = Math.floor(remaining / 60);
                var secs = remaining % 60;
                if (countdownEl) {
                    countdownEl.textContent = mins + ':' + (secs < 10 ? '0' : '') + secs;
                    // Turn red in final 30 seconds
                    if (remaining <= 30) {
                        countdownEl.style.color = '#dc2626';
                    }
                }
            }
        }, 1000);
    }

    // ── 2. Resend Cooldown Countdown ─────────────────────────────────────────
    var resendBtn = document.getElementById('resend-btn');
    var resendCountdownEl = document.getElementById('resend-countdown');

    if (resendBtn && resendBtn.dataset.cooldown) {
        var cooldown = parseInt(resendBtn.dataset.cooldown, 10);

        if (cooldown > 0) {
            var cooldownTimer = setInterval(function () {
                cooldown--;
                if (resendCountdownEl) {
                    resendCountdownEl.textContent = '(' + cooldown + 's)';
                }
                if (cooldown <= 0) {
                    clearInterval(cooldownTimer);
                    resendBtn.disabled = false;
                    resendBtn.style.background = 'transparent';
                    resendBtn.style.color = 'var(--signal-dark)';
                    resendBtn.style.border = '1px solid rgba(20,199,154,0.35)';
                    resendBtn.style.cursor = 'pointer';
                    if (resendCountdownEl) {
                        resendCountdownEl.textContent = '';
                    }
                }
            }, 1000);
        }
    }

    // ── 3. OTP Input: only allow digit input ─────────────────────────────────
    if (otpInput) {
        otpInput.addEventListener('input', function () {
            // Strip non-digits
            this.value = this.value.replace(/\D/g, '').slice(0, 6);
        });
    }

});
</script>
@endsection
