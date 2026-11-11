@extends('layouts.app')
@section('title', 'Help & Support — Patient Portal')
@section('page-title', 'Help & Support')

@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-transparent py-3">
        <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-question-circle me-2 text-primary"></i>Help &amp; Support</h5>
        <small class="text-muted">Guidance for using your patient portal</small>
    </div>
    <div class="card-body">
        <h6 class="fw-bold">Using the portal</h6>
        <ul class="small">
            <li><strong>My Appointments</strong> — request a consultation, or cancel a Requested/Confirmed appointment.</li>
            <li><strong>Diagnostic Results</strong> — only results that have been verified and released to you are shown.</li>
            <li><strong>Prescriptions &amp; Treatment Plans</strong> — read-only. Contact your doctor to change them.</li>
            <li><strong>Bills &amp; Receipts</strong> — read-only. Payments are settled at the hospital cashier.</li>
        </ul>
        <h6 class="fw-bold mt-3">Need changes to your profile or records?</h6>
        <p class="small mb-2">Your core patient record is maintained by the hospital's patient registration system. Please visit the registration desk to correct personal details.</p>
        <h6 class="fw-bold mt-3">Account problems</h6>
        <p class="small mb-0">For locked accounts or password issues, use the "Forgot password" link on the login page, or contact the hospital administration office.</p>
    </div>
</div>
@endsection
