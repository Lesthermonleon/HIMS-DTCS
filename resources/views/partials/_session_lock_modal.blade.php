{{-- ── 3-Minute Session Inactivity Lock & 5-Second Centered Warning Partial ── --}}

<style>
    /* ── Warning Modal Overlay (Centered Screen, 5 Secs Before Lock) ── */
    .warning-backdrop {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        z-index: 99999;
        background: rgba(15, 23, 42, 0.45);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1.25rem;
    }
    .warning-card {
        background: #ffffff;
        color: #0f172a;
        width: 100%;
        max-width: 380px;
        border-radius: 1rem;
        padding: 1.75rem 1.5rem;
        box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.25), 0 0 0 1px rgba(20, 199, 154, 0.3);
        border-top: 4px solid #14C79A;
        text-align: center;
    }
    .warning-icon-wrap {
        width: 52px;
        height: 52px;
        border-radius: 50%;
        background: rgba(234, 179, 8, 0.15);
        color: #d97706;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        margin: 0 auto 0.85rem;
    }
    .warning-title {
        font-family: var(--font-display, inherit);
        font-weight: 700;
        font-size: 1.25rem;
        color: #0f172a;
        margin-bottom: 0.35rem;
    }
    .warning-subtitle {
        font-size: 0.85rem;
        color: #475569;
        margin-bottom: 0.85rem;
    }
    .warning-countdown-badge {
        font-family: var(--font-mono, monospace);
        font-weight: 700;
        font-size: 1.15rem;
        color: #b45309;
        background: rgba(254, 243, 199, 0.8);
        border: 1px solid rgba(251, 191, 36, 0.5);
        padding: 0.45rem 1rem;
        border-radius: 0.5rem;
        display: inline-block;
        margin-bottom: 1.25rem;
    }
    .warning-continue-btn {
        background: #15803d;
        color: #ffffff;
        border: none;
        width: 100%;
        padding: 0.65rem 1rem;
        font-size: 0.88rem;
        font-weight: 600;
        border-radius: 0.5rem;
        cursor: pointer;
        transition: background 0.15s ease;
    }
    .warning-continue-btn:hover {
        background: #166534;
    }

    /* ── Full-Screen Session Lock Overlay (3 Min Inactivity) ── */
    .hims-lock-backdrop {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        z-index: 100000;
        background: rgba(15, 23, 42, 0.68);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1.25rem;
    }
    .hims-lock-card {
        background: #ffffff;
        color: #0f172a;
        width: 100%;
        max-width: 420px;
        border-radius: 1rem;
        padding: 2rem 1.75rem;
        box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.3), 0 0 0 1px rgba(255, 255, 255, 0.1);
        text-align: center;
    }
    .lock-icon-circle {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        background: rgba(20, 199, 154, 0.12);
        color: #15803d;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6rem;
        margin: 0 auto 1rem;
    }
    .lock-card-title {
        font-family: var(--font-display, inherit);
        font-weight: 700;
        font-size: 1.35rem;
        color: #0f172a;
        margin-bottom: 0.3rem;
    }
    .lock-card-subtitle {
        font-size: 0.85rem;
        color: #64748b;
        margin-bottom: 1.25rem;
    }
    .lock-user-box {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 0.65rem;
        padding: 0.65rem 0.85rem;
        text-align: left;
        margin-bottom: 1.25rem;
    }
    .lock-user-avatar {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        background: #15803d;
        color: #ffffff;
        font-weight: 700;
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .lock-user-name {
        font-weight: 600;
        font-size: 0.88rem;
        color: #0f172a;
        line-height: 1.2;
    }
    .lock-user-email {
        font-size: 0.75rem;
        color: #64748b;
    }

    /* Dark Mode Theme Support for Warning & Lock Screens */
    html[data-theme="dark"] .warning-card {
        background: #111111;
        color: #ffffff;
        border-color: rgba(20, 199, 154, 0.4);
    }
    html[data-theme="dark"] .warning-title {
        color: #ffffff;
    }
    html[data-theme="dark"] .warning-subtitle {
        color: #d4d4d4;
    }
    html[data-theme="dark"] .warning-countdown-badge {
        background: rgba(234, 179, 8, 0.15);
        color: #f59e0b;
        border-color: rgba(245, 158, 11, 0.3);
    }
    html[data-theme="dark"] .hims-lock-card {
        background: #111111;
        color: #ffffff;
        border: 1px solid #262626;
    }
    html[data-theme="dark"] .lock-card-title {
        color: #ffffff;
    }
    html[data-theme="dark"] .lock-card-subtitle {
        color: #a3a3a3;
    }
    html[data-theme="dark"] .lock-user-box {
        background: #171717;
        border-color: #262626;
    }
    html[data-theme="dark"] .lock-user-name {
        color: #ffffff;
    }
    html[data-theme="dark"] .lock-user-email {
        color: #a3a3a3;
    }
</style>

{{-- ── Hidden Configuration Container for Clean JS Data Binding ── --}}
<div id="hims-session-lock-config"
    data-locked="{{ session('hims_session_locked') ? '1' : '0' }}"
    data-lock-url="{{ route('session.lock') }}"
    data-unlock-url="{{ route('session.unlock') }}"
    data-login-url="{{ route('login') }}"
    data-csrf-token="{{ csrf_token() }}"
    style="display: none;">
</div>

{{-- ── Centered 5-Second Session Expiring Warning Modal ── --}}
<div id="sessionWarningModal" class="warning-backdrop d-none" role="dialog" aria-modal="true">
    <div class="warning-card">
        <div class="warning-icon-wrap">
            <i class="bi bi-clock-history"></i>
        </div>
        <h3 class="warning-title">Session Expiring</h3>
        <p class="warning-subtitle">Your session will be locked due to inactivity.</p>
        <div class="warning-countdown-badge">
            <span id="warningSecondsCount">5 seconds</span>
        </div>
        <div>
            <button type="button" class="warning-continue-btn" id="warningContinueBtn">Continue Session</button>
        </div>
    </div>
</div>

{{-- ── 3-Minute Inactivity Session Lock Overlay ── --}}
<div id="himsSessionLockModal" class="hims-lock-backdrop d-none" role="dialog" aria-modal="true">
    <div class="hims-lock-card">
        {{-- Brand / Icon --}}
        <div class="lock-icon-circle">
            <i class="bi bi-lock-fill"></i>
        </div>

        <h3 class="lock-card-title">Session Locked</h3>
        <p class="lock-card-subtitle">Your session was locked due to inactivity.</p>


        {{-- Error Container --}}
        <div id="lockModalError" class="mb-3 d-none text-start text-danger" style="font-size: 0.8rem;">
            <i class="bi bi-shield-exclamation me-1"></i>
            <span id="lockModalErrorText"></span>
        </div>

        {{-- Unlock Form --}}
        <form id="lockModalForm">
            @csrf
            <div class="form-group mb-3 text-start">
                <label for="lockModalPassword" class="form-label fw-semibold" style="font-size: 0.82rem;">Password</label>
                <input
                    type="password"
                    id="lockModalPassword"
                    name="password"
                    class="form-control"
                    placeholder="Enter account password"
                    required
                    autocomplete="current-password">
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2" id="lockModalSubmitBtn">
                <span>Unlock</span>
            </button>
        </form>

        {{-- Direct Sign Out Action --}}
        <div class="mt-3">
            <form method="POST" action="{{ route('logout') }}" id="lockModalSignOutForm">
                @csrf
                <button type="submit" class="btn btn-link text-decoration-none text-secondary p-0" id="lockSignOutBtn" style="font-size: 0.82rem;">
                    Sign out
                </button>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var configEl = document.getElementById('hims-session-lock-config');
    var lockUrl   = configEl ? configEl.getAttribute('data-lock-url') : '';
    var unlockUrl = configEl ? configEl.getAttribute('data-unlock-url') : '';
    var loginUrl  = configEl ? configEl.getAttribute('data-login-url') : '';
    var csrfToken = configEl ? configEl.getAttribute('data-csrf-token') : '';

    var INACTIVITY_LIMIT_SEC = 180; // 3 minutes total
    var WARNING_LIMIT_SEC    = 175; // 2 minutes 55 seconds (triggers 5 seconds before lock)

    var lastActivityTime = Date.now();
    var isLocked         = configEl ? configEl.getAttribute('data-locked') === '1' : false;
    var isWarningActive  = false;
    var timerInterval    = 0;

    var warningModal = document.getElementById('sessionWarningModal');
    var warningSecs  = document.getElementById('warningSecondsCount');
    var continueBtn  = document.getElementById('warningContinueBtn');

    var lockModal    = document.getElementById('himsSessionLockModal');
    var lockForm     = document.getElementById('lockModalForm');
    var lockPassInput= document.getElementById('lockModalPassword');
    var lockErrorBox = document.getElementById('lockModalError');
    var lockErrText  = document.getElementById('lockModalErrorText');
    var lockSubmitBtn= document.getElementById('lockModalSubmitBtn');

    // ── 1. Reset activity timer on user interaction ─────────────────────────
    function resetActivityTimer() {
        if (isLocked) return;
        lastActivityTime = Date.now();

        if (isWarningActive) {
            isWarningActive = false;
            if (warningModal) warningModal.classList.add('d-none');
        }
    }

    var lastEventTrigger = 0;
    function handleUserActivity() {
        var now = Date.now();
        if (now - lastEventTrigger > 1000) {
            lastEventTrigger = now;
            resetActivityTimer();
        }
    }

    ['mousemove', 'keydown', 'click', 'touchstart', 'scroll'].forEach(function (evt) {
        window.addEventListener(evt, handleUserActivity, { passive: true });
    });

    if (continueBtn) {
        continueBtn.addEventListener('click', function () {
            resetActivityTimer();
        });
    }

    // ── 2. Trigger Lock on Server & Client ──────────────────────────────────
    function triggerLock() {
        if (isLocked) return;
        isLocked = true;

        if (warningModal) warningModal.classList.add('d-none');

        if (lockModal) {
            lockModal.classList.remove('d-none');
            document.body.style.overflow = 'hidden';
            if (lockPassInput) lockPassInput.focus();
        }

        fetch(lockUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            }
        }).catch(function (err) {
            console.error('Session lock sync failed:', err);
        });
    }

    function showLockUI() {
        isLocked = true;
        if (warningModal) warningModal.classList.add('d-none');
        if (lockModal) {
            lockModal.classList.remove('d-none');
            document.body.style.overflow = 'hidden';
            if (lockPassInput) lockPassInput.focus();
        }
    }

    if (isLocked) {
        showLockUI();
    }

    // ── 3. Interval Timer Check (Every 1 Second) ────────────────────────────
    timerInterval = setInterval(function () {
        if (isLocked) return;

        var elapsedSec = Math.floor((Date.now() - lastActivityTime) / 1000);

        if (elapsedSec >= INACTIVITY_LIMIT_SEC) {
            triggerLock();
        } else if (elapsedSec >= WARNING_LIMIT_SEC) {
            isWarningActive = true;
            var remainingSec = INACTIVITY_LIMIT_SEC - elapsedSec;
            if (remainingSec < 0) remainingSec = 0;
            if (warningSecs) {
                warningSecs.textContent = remainingSec + ' second' + (remainingSec === 1 ? '' : 's');
            }
            if (warningModal) warningModal.classList.remove('d-none');
        } else {
            if (isWarningActive) {
                isWarningActive = false;
                if (warningModal) warningModal.classList.add('d-none');
            }
        }
    }, 1000);

    // ── 4. Form Submit: Password Unlock Verification ────────────────────────
    if (lockForm) {
        lockForm.addEventListener('submit', function (e) {
            e.preventDefault();
            if (lockErrorBox) lockErrorBox.classList.add('d-none');
            if (lockSubmitBtn) lockSubmitBtn.disabled = true;

            fetch(unlockUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ password: lockPassInput ? lockPassInput.value : '' })
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (lockSubmitBtn) lockSubmitBtn.disabled = false;

                if (data.success) {
                    isLocked = false;
                    resetActivityTimer();

                    if (lockModal) lockModal.classList.add('d-none');
                    document.body.style.overflow = '';
                    if (lockPassInput) lockPassInput.value = '';
                } else if (data.max_attempts_exceeded) {
                    window.location.href = data.redirect || loginUrl;
                } else {
                    if (lockErrText) lockErrText.textContent = data.message || 'Incorrect password.';
                    if (lockErrorBox) lockErrorBox.classList.remove('d-none');
                    if (lockPassInput) {
                        lockPassInput.value = '';
                        lockPassInput.focus();
                    }
                }
            })
            .catch(function () {
                if (lockSubmitBtn) lockSubmitBtn.disabled = false;
                if (lockErrText) lockErrText.textContent = 'An error occurred while verifying your password.';
                if (lockErrorBox) lockErrorBox.classList.remove('d-none');
            });
        });
    }

    // ── 5. Server Lock Global Interceptor (for AJAX requests) ───────────────
    var originalFetch = window.fetch;
    window.fetch = function () {
        return originalFetch.apply(this, arguments).then(function (response) {
            if (response.status === 423) {
                showLockUI();
            }
            return response;
        });
    };
});
</script>
