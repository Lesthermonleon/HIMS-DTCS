@extends('layouts.guest')
@section('title', 'Forgot Password')
@section('card-title', 'Forgot Password')
@section('card-hint', 'Enter your registered email address and we\'ll send you a password reset link.')

@section('content')

<style>
    /* ── Floating Toast Notification Styling ── */
    .reset-status-toast {
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
    .reset-status-toast.hide {
        opacity: 0;
        transform: translateY(-12px);
        pointer-events: none;
    }
    .reset-status-toast .toast-icon-wrap {
        font-size: 1.25rem;
        color: var(--signal-dark, #14C79A);
        line-height: 1;
        margin-top: 0.1rem;
    }
    .reset-status-toast .toast-body-wrap {
        flex: 1;
    }
    .reset-status-toast .toast-header-title {
        font-family: var(--font-display, inherit);
        font-weight: 700;
        font-size: 0.82rem;
        color: #0f172a;
        letter-spacing: 0.02em;
        margin-bottom: 0.2rem;
    }
    .reset-status-toast .toast-message-text {
        font-size: 0.78rem;
        line-height: 1.45;
        color: #475569;
    }
    .reset-status-toast .toast-close-btn {
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
    .reset-status-toast .toast-close-btn:hover {
        color: #0f172a;
    }
    @media (max-width: 520px) {
        .reset-status-toast {
            top: 1rem;
            left: 1rem;
            right: 1rem;
            width: calc(100% - 2rem);
            max-width: none;
            padding: 0.75rem 0.85rem;
        }
    }
</style>

    {{-- Floating Top-Right Toast Notification for Password Reset Status --}}
    @if (session('status'))
        <div id="reset-status-toast" class="reset-status-toast" role="alert" aria-live="polite">
            <div class="toast-icon-wrap">
                <i class="bi bi-envelope-check"></i>
            </div>
            <div class="toast-body-wrap">
                <div class="toast-header-title">Password Reset</div>
                <div class="toast-message-text">
                    {{ session('status') }}
                </div>
            </div>
            <button type="button" class="toast-close-btn" onclick="dismissResetToast()" aria-label="Close notification">&times;</button>
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" id="forgot-password-form" class="auth-form-grid">
        @csrf

        {{-- Email Address --}}
        <div class="form-group mb-3">
            <label class="field-label" for="email">Email Address</label>
            <div class="input-wrap">
                <svg class="leading" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="4.5" width="19" height="15" rx="2.5"/><path d="m3 7 8.2 5.9a1.4 1.4 0 0 0 1.6 0L21 7"/></svg>
                <input type="email" id="email" name="email"
                       class="auth-input @error('email') is-invalid @enderror"
                       value="{{ old('email') }}"
                       required autofocus autocomplete="email"
                       placeholder="you@hospital.com">
            </div>
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        {{-- Submit Button --}}
        <button type="submit" class="btn-auth" id="forgot-submit-btn">
            <span>Send Reset Link</span>
            <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
        </button>
    </form>

    {{-- Back to Sign In Link --}}
    <div class="text-center mt-3">
        <a href="{{ route('login') }}" class="forgot text-decoration-none" style="font-size: 0.85rem;">
            &larr; Back to Sign In
        </a>
    </div>

@endsection

@section('scripts')
<script>
function dismissResetToast() {
    var toastEl = document.getElementById('reset-status-toast');
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
    var toastEl = document.getElementById('reset-status-toast');
    if (toastEl) {
        setTimeout(function () {
            dismissResetToast();
        }, 6000);
    }
});
</script>
@endsection
