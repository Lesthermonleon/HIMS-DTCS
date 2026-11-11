{{-- ─── Sidebar Navigation (role-aware) ─── --}}
@php
    $role = auth()->user()?->primaryRole;

    // Is any child route in this module currently active?
    $patientsActive = request()->routeIs('patients.*');
    $labActive      = request()->routeIs('lab.*');
    $radActive      = request()->routeIs('radiology.*');
    $pmsActive      = request()->routeIs('pharmacy.*');
    $sorActive      = request()->routeIs('surgery.*');
    $dnmActive      = request()->routeIs('diet.*') || request()->routeIs('reports.diet.*');
    $reportsActive  = request()->routeIs('reports.*') && !request()->routeIs('reports.diet.*') && !request()->routeIs('reports.surgery.*');
    $adminActive    = request()->routeIs('admin.*');
@endphp


@if($role === 'super_admin')
{{-- ────────────────────────────────────────────────────────────
     SUPER ADMIN: Master System Authority & Governance
──────────────────────────────────────────────────────────── --}}
<div class="sb-nav-label" style="margin-top:.5rem;">MAIN</div>
<a href="{{ route('super-admin.dashboard') }}" class="nav-link {{ request()->routeIs('super-admin.dashboard') ? 'active' : '' }}">
    <i class="bi bi-speedometer2 text-primary"></i> Dashboard
</a>

<div class="sb-nav-label" style="margin-top:.5rem;">ACCESS & GOVERNANCE</div>
<a href="{{ route('super-admin.users.index') }}" class="nav-link {{ request()->routeIs('super-admin.users.*') || request()->routeIs('admin.users.*') || request()->routeIs('super-admin.admins.*') ? 'active' : '' }}">
    <i class="bi bi-people"></i> User Management
</a>
<a href="{{ route('admin.roles.index') }}" class="nav-link {{ request()->routeIs('admin.roles.*') ? 'active' : '' }}">
    <i class="bi bi-shield-lock"></i> Roles & Permissions
</a>

<div class="sb-nav-label" style="margin-top:.5rem;">DEPARTMENTS & SERVICES</div>
<a href="{{ route('super-admin.departments.index') }}" class="nav-link {{ request()->routeIs('super-admin.departments.*') ? 'active' : '' }}">
    <i class="bi bi-diagram-3"></i> Department Management
</a>
<a href="{{ route('super-admin.services.diagnostic') }}" class="nav-link {{ request()->routeIs('super-admin.services.diagnostic*') ? 'active' : '' }}">
    <i class="bi bi-clipboard2-pulse"></i> Diagnostic Services Configuration
</a>

<div class="sb-nav-label" style="margin-top:.5rem;">MONITORING & REPORTS</div>
<a href="{{ route('reports.index') }}" class="nav-link {{ $reportsActive ? 'active' : '' }}">
    <i class="bi bi-bar-chart-line"></i> Reports & Analytics
</a>
<a href="{{ route('reports.surgery.activity') }}" class="nav-link {{ request()->routeIs('reports.surgery.*') ? 'active' : '' }}">
    <i class="bi bi-file-earmark-bar-graph"></i> Surgery Reports
</a>
<a href="{{ route('super-admin.audit-vault.index') }}" class="nav-link {{ request()->routeIs('super-admin.audit-vault.*') ? 'active' : '' }}">
    <i class="bi bi-journal-check"></i> Audit Logs
</a>

<div class="sb-nav-label" style="margin-top:.5rem;">SYSTEM & SECURITY</div>
<a href="{{ route('super-admin.settings.index') }}" class="nav-link {{ request()->routeIs('super-admin.settings.*') ? 'active' : '' }}">
    <i class="bi bi-shield-check"></i> Security Settings
</a>
<a href="{{ route('super-admin.backup.index') }}" class="nav-link {{ request()->routeIs('super-admin.backup.*') ? 'active' : '' }}">
    <i class="bi bi-database-down"></i> Backup & Recovery
</a>
<a href="{{ route('admin.hospital-settings.index') }}" class="nav-link {{ request()->routeIs('admin.hospital-settings.*') ? 'active' : '' }}">
    <i class="bi bi-gear"></i> System Settings
</a>

@elseif($role === 'admin')
{{-- ──────────────────────────────
     ADMIN: collapsible dropdowns
────────────────────────────── --}}

<div class="sb-nav-label" style="margin-top:.5rem;">MAIN</div>
<a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
    <i class="bi bi-speedometer2"></i> Dashboard
</a>

<div class="sb-nav-label" style="margin-top:.5rem;">DTCS CLINICAL MODULES</div>

{{-- ── Laboratory (LIS) ── --}}
<button class="nav-link sb-group-toggle {{ $labActive ? 'active' : '' }}"
        data-bs-toggle="collapse" data-bs-target="#grp-lab"
        aria-expanded="{{ $labActive ? 'true' : 'false' }}" aria-controls="grp-lab">
    <i class="bi bi-clipboard2-pulse"></i>
    <span class="sb-group-label">Laboratory <span class="sb-badge">LIS</span></span>
    <i class="bi bi-chevron-down sb-chevron"></i>
</button>
<div class="collapse {{ $labActive ? 'show' : '' }}" id="grp-lab">
    <div class="sb-group-body">
        <a href="{{ route('lab.dashboard') }}" class="nav-link sb-sub {{ request()->routeIs('lab.dashboard') ? 'active' : '' }}">
            <i class="bi bi-grid-1x2"></i> Overview
        </a>
        <a href="{{ route('lab.requests.index') }}" class="nav-link sb-sub {{ request()->routeIs('lab.requests.*') ? 'active' : '' }}">
            <i class="bi bi-list-task"></i> Lab Requests
        </a>
        <a href="{{ route('lab.results.index') }}" class="nav-link sb-sub {{ request()->routeIs('lab.results.*') ? 'active' : '' }}">
            <i class="bi bi-journal-medical"></i> Lab Results
        </a>
    </div>
</div>

{{-- ── Radiology (RIS) ── --}}
<button class="nav-link sb-group-toggle {{ $radActive ? 'active' : '' }}"
        data-bs-toggle="collapse" data-bs-target="#grp-rad"
        aria-expanded="{{ $radActive ? 'true' : 'false' }}" aria-controls="grp-rad">
    <i class="bi bi-activity"></i>
    <span class="sb-group-label">Radiology <span class="sb-badge">RIS</span></span>
    <i class="bi bi-chevron-down sb-chevron"></i>
</button>
<div class="collapse {{ $radActive ? 'show' : '' }}" id="grp-rad">
    <div class="sb-group-body">
        <a href="{{ route('radiology.dashboard') }}" class="nav-link sb-sub {{ request()->routeIs('radiology.dashboard') ? 'active' : '' }}">
            <i class="bi bi-grid-1x2"></i> Overview
        </a>
        <a href="{{ route('radiology.requests.index') }}" class="nav-link sb-sub {{ request()->routeIs('radiology.requests.*') ? 'active' : '' }}">
            <i class="bi bi-image"></i> Imaging Requests
        </a>
        <a href="{{ route('radiology.reports.index') }}" class="nav-link sb-sub {{ request()->routeIs('radiology.reports.*') ? 'active' : '' }}">
            <i class="bi bi-file-text"></i> Reports
        </a>
    </div>
</div>

{{-- ── Pharmacy (PMS) ── --}}
<button class="nav-link sb-group-toggle {{ $pmsActive ? 'active' : '' }}"
        data-bs-toggle="collapse" data-bs-target="#grp-pms"
        aria-expanded="{{ $pmsActive ? 'true' : 'false' }}" aria-controls="grp-pms">
    <i class="bi bi-capsule"></i>
    <span class="sb-group-label">Pharmacy <span class="sb-badge">PMS</span></span>
    <i class="bi bi-chevron-down sb-chevron"></i>
</button>
<div class="collapse {{ $pmsActive ? 'show' : '' }}" id="grp-pms">
    <div class="sb-group-body">
        <a href="{{ route('pharmacy.dashboard') }}" class="nav-link sb-sub {{ request()->routeIs('pharmacy.dashboard') ? 'active' : '' }}">
            <i class="bi bi-grid-1x2"></i> Overview
        </a>
        <a href="{{ route('pharmacy.prescriptions.index') }}" class="nav-link sb-sub {{ request()->routeIs('pharmacy.prescriptions.*') ? 'active' : '' }}">
            <i class="bi bi-prescription2"></i> Prescriptions
        </a>
        <a href="{{ route('pharmacy.dispensing.index') }}" class="nav-link sb-sub {{ request()->routeIs('pharmacy.dispensing.*') ? 'active' : '' }}">
            <i class="bi bi-bag-plus"></i> Dispensing
        </a>
        <a href="{{ route('pharmacy.medicines.index') }}" class="nav-link sb-sub {{ request()->routeIs('pharmacy.medicines.*') ? 'active' : '' }}">
            <i class="bi bi-box-seam"></i> Medicine Availability
        </a>
    </div>
</div>

{{-- ── Surgery (SORS) ── --}}
<button class="nav-link sb-group-toggle {{ $sorActive ? 'active' : '' }}"
        data-bs-toggle="collapse" data-bs-target="#grp-sor"
        aria-expanded="{{ $sorActive ? 'true' : 'false' }}" aria-controls="grp-sor">
    <i class="bi bi-heart-pulse"></i>
    <span class="sb-group-label">Surgery <span class="sb-badge">SORS</span></span>
    <i class="bi bi-chevron-down sb-chevron"></i>
</button>
<div class="collapse {{ $sorActive ? 'show' : '' }}" id="grp-sor">
    <div class="sb-group-body">
        <a href="{{ route('surgery.dashboard') }}" class="nav-link sb-sub {{ request()->routeIs('surgery.dashboard') ? 'active' : '' }}">
            <i class="bi bi-grid-1x2"></i> Overview
        </a>
        <a href="{{ route('surgery.requests.index') }}" class="nav-link sb-sub {{ request()->routeIs('surgery.requests.*') ? 'active' : '' }}">
            <i class="bi bi-scissors"></i> Surgery Requests
        </a>
        <a href="{{ route('surgery.schedules.index') }}" class="nav-link sb-sub {{ request()->routeIs('surgery.schedules.*') ? 'active' : '' }}">
            <i class="bi bi-calendar3"></i> OR Schedules
        </a>
        <a href="{{ route('surgery.calendar') }}" class="nav-link sb-sub {{ request()->routeIs('surgery.calendar') ? 'active' : '' }}">
            <i class="bi bi-calendar-week"></i> Surgery Calendar
        </a>
    </div>
</div>

{{-- ── Nutrition / Diet (DNMS) ── --}}
<button class="nav-link sb-group-toggle {{ $dnmActive ? 'active' : '' }}"
        data-bs-toggle="collapse" data-bs-target="#grp-dnm"
        aria-expanded="{{ $dnmActive ? 'true' : 'false' }}" aria-controls="grp-dnm">
    <i class="bi bi-apple"></i>
    <span class="sb-group-label">Nutrition <span class="sb-badge">DNMS</span></span>
    <i class="bi bi-chevron-down sb-chevron"></i>
</button>
<div class="collapse {{ $dnmActive ? 'show' : '' }}" id="grp-dnm">
    <div class="sb-group-body">
        <a href="{{ route('diet.dashboard') }}" class="nav-link sb-sub {{ request()->routeIs('diet.dashboard') ? 'active' : '' }}">
            <i class="bi bi-grid-1x2"></i> Overview
        </a>
        <a href="{{ route('diet.plans.index') }}" class="nav-link sb-sub {{ request()->routeIs('diet.plans.*') || request()->routeIs('diet.requests.*') ? 'active' : '' }}">
            <i class="bi bi-clipboard2-heart"></i> Diet Plans
        </a>
        <a href="{{ route('reports.diet.activity') }}" class="nav-link sb-sub {{ request()->routeIs('reports.diet.*') ? 'active' : '' }}">
            <i class="bi bi-file-earmark-bar-graph"></i> Nutrition Reports
        </a>
    </div>
</div>

{{-- ── Patients ── --}}
<div class="sb-nav-label" style="margin-top:.5rem;">PATIENTS</div>
<a href="{{ route('patients.index') }}" class="nav-link {{ $patientsActive ? 'active' : '' }}">
    <i class="bi bi-people"></i> Patient Directory
</a>

{{-- ── Monitoring & Reports ── --}}
<div class="sb-nav-label" style="margin-top:.5rem;">MONITORING & REPORTS</div>
<a href="{{ route('admin.operations.index') }}" class="nav-link {{ request()->routeIs('admin.operations.*') ? 'active' : '' }}">
    <i class="bi bi-activity"></i> Operations Monitor
</a>
<a href="{{ route('reports.index') }}" class="nav-link {{ $reportsActive ? 'active' : '' }}">
    <i class="bi bi-bar-chart-line"></i> Reports & Analytics
</a>
<a href="{{ route('reports.surgery.activity') }}" class="nav-link {{ request()->routeIs('reports.surgery.*') ? 'active' : '' }}">
    <i class="bi bi-file-earmark-bar-graph"></i> Surgery Reports
</a>
<a href="{{ route('admin.users.index') }}" class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
    <i class="bi bi-people"></i> User Accounts
</a>
<a href="{{ route('admin.audit-logs.index') }}" class="nav-link {{ request()->routeIs('admin.audit-logs.*') ? 'active' : '' }}">
    <i class="bi bi-journal-text"></i> System Audit Logs
</a>




@elseif($role === 'patient')
{{-- ──────────────────────────────
     PATIENT: Personal Healthcare Portal
────────────────────────────── --}}
<div class="sb-nav-label" style="margin-top:.5rem;">MY HEALTH PORTAL</div>
<a href="{{ route('patient.dashboard') }}" class="nav-link {{ request()->routeIs('patient.dashboard') ? 'active' : '' }}">
    <i class="bi bi-speedometer2 text-success"></i> My Dashboard
</a>
<a href="{{ route('patient.profile') }}" class="nav-link {{ request()->routeIs('patient.profile') ? 'active' : '' }}">
    <i class="bi bi-person-gear"></i> My Profile
</a>
<a href="{{ route('patient.appointments') }}" class="nav-link {{ request()->routeIs('patient.appointments*') ? 'active' : '' }}">
    <i class="bi bi-calendar-check"></i> My Appointments
</a>
<a href="{{ route('patient.results') }}" class="nav-link {{ request()->routeIs('patient.results') ? 'active' : '' }}">
    <i class="bi bi-file-earmark-medical text-success"></i> Diagnostic Results
</a>
<a href="{{ route('patient.prescriptions') }}" class="nav-link {{ request()->routeIs('patient.prescriptions') ? 'active' : '' }}">
    <i class="bi bi-capsule text-warning"></i> My Prescriptions
</a>
<a href="{{ route('patient.treatments') }}" class="nav-link {{ request()->routeIs('patient.treatments') ? 'active' : '' }}">
    <i class="bi bi-heart-pulse text-danger"></i> Treatment Plans
</a>
<a href="{{ route('patient.billing') }}" class="nav-link {{ request()->routeIs('patient.billing') ? 'active' : '' }}">
    <i class="bi bi-receipt text-primary"></i> Bills & Receipts
</a>
<a href="{{ route('notifications.index') }}" class="nav-link {{ request()->routeIs('notifications.*') ? 'active' : '' }}">
    <i class="bi bi-bell"></i> Notifications
</a>
<a href="{{ route('patient.help') }}" class="nav-link {{ request()->routeIs('patient.help') ? 'active' : '' }}">
    <i class="bi bi-question-circle"></i> Help & Support
</a>

@else
{{-- ──────────────────────────────
     NON-ADMIN: Clinical Services Navigation
────────────────────────────── --}}

@if($role === 'doctor')
<div class="sb-nav-label" style="margin-top:.5rem;">MAIN</div>
<a href="{{ route('doctor.dashboard') }}" class="nav-link {{ request()->routeIs('doctor.dashboard') ? 'active' : '' }}"><i class="bi bi-speedometer2"></i> Dashboard</a>
<a href="{{ route('admin.appointments.index') }}" class="nav-link {{ request()->routeIs('admin.appointments.*') ? 'active' : '' }}"><i class="bi bi-calendar3"></i> Appointments</a>
<a href="{{ route('medisense.index') }}" class="nav-link {{ request()->routeIs('medisense.*') ? 'active' : '' }}"><i class="bi bi-cpu-fill text-success"></i> MediSense AI <span class="sb-badge">AI</span></a>

<div class="sb-nav-label" style="margin-top:.5rem;">DIAGNOSTIC SERVICES</div>

{{-- ── Laboratory (LIS) ── --}}
<button class="nav-link sb-group-toggle {{ $labActive ? 'active' : '' }}"
        data-bs-toggle="collapse" data-bs-target="#grp-lab"
        aria-expanded="{{ $labActive ? 'true' : 'false' }}" aria-controls="grp-lab">
    <i class="bi bi-clipboard2-pulse"></i>
    <span class="sb-group-label">Laboratory <span class="sb-badge">LIS</span></span>
    <i class="bi bi-chevron-down sb-chevron"></i>
</button>
<div class="collapse {{ $labActive ? 'show' : '' }}" id="grp-lab">
    <div class="sb-group-body">
        <a href="{{ route('lab.dashboard') }}" class="nav-link sb-sub {{ request()->routeIs('lab.dashboard') ? 'active' : '' }}">
            <i class="bi bi-grid-1x2"></i> Overview
        </a>
        <a href="{{ route('lab.requests.index') }}" class="nav-link sb-sub {{ request()->routeIs('lab.requests.*') ? 'active' : '' }}">
            <i class="bi bi-list-task"></i> Lab Requests
        </a>
        <a href="{{ route('lab.results.index') }}" class="nav-link sb-sub {{ request()->routeIs('lab.results.*') ? 'active' : '' }}">
            <i class="bi bi-journal-medical"></i> Lab Results
        </a>
    </div>
</div>

{{-- ── Radiology (RIS) ── --}}
<button class="nav-link sb-group-toggle {{ $radActive ? 'active' : '' }}"
        data-bs-toggle="collapse" data-bs-target="#grp-rad"
        aria-expanded="{{ $radActive ? 'true' : 'false' }}" aria-controls="grp-rad">
    <i class="bi bi-activity"></i>
    <span class="sb-group-label">Radiology <span class="sb-badge">RIS</span></span>
    <i class="bi bi-chevron-down sb-chevron"></i>
</button>
<div class="collapse {{ $radActive ? 'show' : '' }}" id="grp-rad">
    <div class="sb-group-body">
        <a href="{{ route('radiology.dashboard') }}" class="nav-link sb-sub {{ request()->routeIs('radiology.dashboard') ? 'active' : '' }}">
            <i class="bi bi-grid-1x2"></i> Overview
        </a>
        <a href="{{ route('radiology.requests.index') }}" class="nav-link sb-sub {{ request()->routeIs('radiology.requests.*') ? 'active' : '' }}">
            <i class="bi bi-image"></i> Imaging Requests
        </a>
        <a href="{{ route('radiology.reports.index') }}" class="nav-link sb-sub {{ request()->routeIs('radiology.reports.*') ? 'active' : '' }}">
            <i class="bi bi-file-text"></i> Reports
        </a>
    </div>
</div>

<div class="sb-nav-label" style="margin-top:.5rem;">CLINICAL MODULES</div>

{{-- ── Pharmacy (PMS) ── --}}
<button class="nav-link sb-group-toggle {{ $pmsActive ? 'active' : '' }}"
        data-bs-toggle="collapse" data-bs-target="#grp-pms"
        aria-expanded="{{ $pmsActive ? 'true' : 'false' }}" aria-controls="grp-pms">
    <i class="bi bi-capsule"></i>
    <span class="sb-group-label">Pharmacy <span class="sb-badge">PMS</span></span>
    <i class="bi bi-chevron-down sb-chevron"></i>
</button>
<div class="collapse {{ $pmsActive ? 'show' : '' }}" id="grp-pms">
    <div class="sb-group-body">
        <a href="{{ route('pharmacy.dashboard') }}" class="nav-link sb-sub {{ request()->routeIs('pharmacy.dashboard') ? 'active' : '' }}">
            <i class="bi bi-grid-1x2"></i> Overview
        </a>
        <a href="{{ route('pharmacy.prescriptions.index') }}" class="nav-link sb-sub {{ request()->routeIs('pharmacy.prescriptions.*') ? 'active' : '' }}">
            <i class="bi bi-prescription2"></i> Prescriptions
        </a>
        <a href="{{ route('pharmacy.medicines.index') }}" class="nav-link sb-sub {{ request()->routeIs('pharmacy.medicines.*') ? 'active' : '' }}">
            <i class="bi bi-box-seam"></i> Medicine Availability
        </a>
    </div>
</div>


{{-- ── Surgery (SORS) ── --}}
<button class="nav-link sb-group-toggle {{ $sorActive ? 'active' : '' }}"
        data-bs-toggle="collapse" data-bs-target="#grp-sor"
        aria-expanded="{{ $sorActive ? 'true' : 'false' }}" aria-controls="grp-sor">
    <i class="bi bi-heart-pulse"></i>
    <span class="sb-group-label">Surgery <span class="sb-badge">SORS</span></span>
    <i class="bi bi-chevron-down sb-chevron"></i>
</button>
<div class="collapse {{ $sorActive ? 'show' : '' }}" id="grp-sor">
    <div class="sb-group-body">
        <a href="{{ route('surgery.dashboard') }}" class="nav-link sb-sub {{ request()->routeIs('surgery.dashboard') ? 'active' : '' }}">
            <i class="bi bi-grid-1x2"></i> Overview
        </a>
        <a href="{{ route('surgery.requests.index') }}" class="nav-link sb-sub {{ request()->routeIs('surgery.requests.*') ? 'active' : '' }}">
            <i class="bi bi-scissors"></i> Surgery Requests
        </a>
        <a href="{{ route('surgery.schedules.index') }}" class="nav-link sb-sub {{ request()->routeIs('surgery.schedules.*') ? 'active' : '' }}">
            <i class="bi bi-calendar3"></i> OR Schedules
        </a>
        <a href="{{ route('surgery.calendar') }}" class="nav-link sb-sub {{ request()->routeIs('surgery.calendar') ? 'active' : '' }}">
            <i class="bi bi-calendar-week"></i> Surgery Calendar
        </a>
    </div>
</div>

{{-- ── Nutrition / Diet (DNMS) ── --}}
<button class="nav-link sb-group-toggle {{ $dnmActive ? 'active' : '' }}"
        data-bs-toggle="collapse" data-bs-target="#grp-dnm"
        aria-expanded="{{ $dnmActive ? 'true' : 'false' }}" aria-controls="grp-dnm">
    <i class="bi bi-apple"></i>
    <span class="sb-group-label">Nutrition <span class="sb-badge">DNMS</span></span>
    <i class="bi bi-chevron-down sb-chevron"></i>
</button>
<div class="collapse {{ $dnmActive ? 'show' : '' }}" id="grp-dnm">
    <div class="sb-group-body">
        <a href="{{ route('diet.dashboard') }}" class="nav-link sb-sub {{ request()->routeIs('diet.dashboard') ? 'active' : '' }}">
            <i class="bi bi-grid-1x2"></i> Overview
        </a>
        <a href="{{ route('diet.plans.index') }}" class="nav-link sb-sub {{ request()->routeIs('diet.plans.*') || request()->routeIs('diet.requests.*') ? 'active' : '' }}">
            <i class="bi bi-clipboard2-heart"></i> Diet Plans
        </a>
        <a href="{{ route('reports.diet.activity') }}" class="nav-link sb-sub {{ request()->routeIs('reports.diet.*') ? 'active' : '' }}">
            <i class="bi bi-file-earmark-bar-graph"></i> Nutrition Reports
        </a>
    </div>
</div>

{{-- ── Patients ── --}}
<div class="sb-nav-label" style="margin-top:.5rem;">PATIENTS</div>
<a href="{{ route('patients.index') }}" class="nav-link {{ $patientsActive ? 'active' : '' }}">
    <i class="bi bi-people"></i> Patient Directory
</a>

{{-- ── Doctor Reports Entry Point ── --}}
<div class="sb-nav-label" style="margin-top:.5rem;">REPORTS</div>
<a href="{{ route('reports.index') }}" class="nav-link {{ request()->routeIs('reports.*') && !request()->routeIs('reports.diet.*') ? 'active' : '' }}">
    <i class="bi bi-file-earmark-bar-graph"></i> Clinical Reports
</a>


@elseif($role === 'pharmacist')
{{-- ── Pharmacist Role: Complete PMS & POS Navigation ── --}}
<div class="sb-nav-label" style="margin-top:.5rem;">MAIN</div>
<a href="{{ route('pharmacy.dashboard') }}" class="nav-link {{ request()->routeIs('pharmacy.dashboard') ? 'active' : '' }}">
    <i class="bi bi-speedometer2"></i> Pharmacy Dashboard
</a>

<div class="sb-nav-label" style="margin-top:.5rem;">PHARMACY OPERATIONS</div>
<a href="{{ route('pharmacy.prescriptions.index') }}" class="nav-link {{ request()->routeIs('pharmacy.prescriptions.*') || request()->routeIs('pharmacy.dispensing.*') ? 'active' : '' }}">
    <i class="bi bi-prescription2"></i> Prescription Management
</a>
<a href="{{ route('pharmacy.pos.index') }}" class="nav-link {{ request()->routeIs('pharmacy.pos.*') ? 'active' : '' }}">
    <i class="bi bi-calculator"></i> Point of Sale
</a>

<div class="sb-nav-label" style="margin-top:.5rem;">INVENTORY</div>
<a href="{{ route('pharmacy.inventory.items') }}" class="nav-link {{ request()->routeIs('pharmacy.inventory.items') || request()->routeIs('pharmacy.medicines.*') ? 'active' : '' }}">
    <i class="bi bi-boxes"></i> Inventory Items
</a>
<a href="{{ route('pharmacy.inventory.purchase-orders') }}" class="nav-link {{ request()->routeIs('pharmacy.inventory.purchase-orders') ? 'active' : '' }}">
    <i class="bi bi-truck"></i> Purchase Orders
</a>
<a href="{{ route('pharmacy.inventory.counts') }}" class="nav-link {{ request()->routeIs('pharmacy.inventory.counts') ? 'active' : '' }}">
    <i class="bi bi-clipboard2-check"></i> Inventory Counts
</a>
<a href="{{ route('pharmacy.inventory.adjustments') }}" class="nav-link {{ request()->routeIs('pharmacy.inventory.adjustments') ? 'active' : '' }}">
    <i class="bi bi-sliders"></i> Stock Adjustments
</a>

<div class="sb-nav-label" style="margin-top:.5rem;">REPORTS</div>
<a href="{{ route('reports.pharmacy.activity') }}" class="nav-link {{ request()->routeIs('reports.pharmacy.*') ? 'active' : '' }}">
    <i class="bi bi-file-earmark-bar-graph"></i> Pharmacy Reports
</a>

@else
{{-- ── Single-Department Clinical Roles: Flat Direct Navigation ── --}}
<div class="sb-nav-label" style="margin-top:.5rem;">MAIN</div>
<a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('*.dashboard') && !request()->routeIs('admin.dashboard') ? 'active' : '' }}">
    <i class="bi bi-speedometer2"></i> Dashboard
</a>

@if(in_array($role, ['med-tech', 'rad-tech', 'radiologist']))
<div class="sb-nav-label" style="margin-top:.5rem;">DIAGNOSTIC SERVICES</div>

@if($role === 'med-tech')
{{-- ── Laboratory (LIS) ── --}}
<button class="nav-link sb-group-toggle {{ $labActive ? 'active' : '' }}"
        data-bs-toggle="collapse" data-bs-target="#grp-lab-staff"
        aria-expanded="{{ $labActive ? 'true' : 'false' }}" aria-controls="grp-lab-staff">
    <i class="bi bi-clipboard2-pulse"></i>
    <span class="sb-group-label">Laboratory <span class="sb-badge">LIS</span></span>
    <i class="bi bi-chevron-down sb-chevron"></i>
</button>
<div class="collapse {{ $labActive ? 'show' : '' }}" id="grp-lab-staff">
    <div class="sb-group-body">
        <a href="{{ route('lab.requests.index') }}" class="nav-link sb-sub {{ request()->routeIs('lab.requests.*') ? 'active' : '' }}">
            <i class="bi bi-list-task"></i> Lab Requests
        </a>
        <a href="{{ route('lab.results.index') }}" class="nav-link sb-sub {{ request()->routeIs('lab.results.*') ? 'active' : '' }}">
            <i class="bi bi-journal-medical"></i> Lab Results
        </a>
        <a href="{{ route('reports.laboratory.activity') }}" class="nav-link sb-sub {{ request()->routeIs('reports.laboratory.*') ? 'active' : '' }}">
            <i class="bi bi-file-earmark-bar-graph"></i> Laboratory Reports
        </a>
    </div>
</div>
@endif

@if(in_array($role, ['rad-tech', 'radiologist']))
{{-- ── Radiology (RIS) ── --}}
<button class="nav-link sb-group-toggle {{ $radActive ? 'active' : '' }}"
        data-bs-toggle="collapse" data-bs-target="#grp-rad-staff"
        aria-expanded="{{ $radActive ? 'true' : 'false' }}" aria-controls="grp-rad-staff">
    <i class="bi bi-activity"></i>
    <span class="sb-group-label">Radiology <span class="sb-badge">RIS</span></span>
    <i class="bi bi-chevron-down sb-chevron"></i>
</button>
<div class="collapse {{ $radActive ? 'show' : '' }}" id="grp-rad-staff">
    <div class="sb-group-body">
        <a href="{{ route('radiology.requests.index') }}" class="nav-link sb-sub {{ request()->routeIs('radiology.requests.*') ? 'active' : '' }}">
            <i class="bi bi-image"></i> Imaging Requests
        </a>
        <a href="{{ route('radiology.reports.index') }}" class="nav-link sb-sub {{ request()->routeIs('radiology.reports.*') ? 'active' : '' }}">
            <i class="bi bi-file-text"></i> Reports
        </a>
        <a href="{{ route('reports.radiology.activity') }}" class="nav-link sb-sub {{ request()->routeIs('reports.radiology.*') ? 'active' : '' }}">
            <i class="bi bi-file-earmark-bar-graph"></i> Radiology Reports
        </a>
    </div>
</div>
@endif

@elseif(in_array($role, ['or-coordinator', 'or-staff']))
<div class="sb-nav-label" style="margin-top:.5rem;">SURGERY & OPERATING ROOM (SORS)</div>
@if($role === 'or-coordinator')
<a href="{{ route('surgery.requests.index') }}" class="nav-link {{ request()->routeIs('surgery.requests.*') ? 'active' : '' }}">
    <i class="bi bi-scissors"></i> Surgery Requests
</a>
@endif
<a href="{{ route('surgery.schedules.index') }}" class="nav-link {{ request()->routeIs('surgery.schedules.*') ? 'active' : '' }}">
    <i class="bi bi-calendar3"></i> OR Schedules
</a>
<a href="{{ route('surgery.calendar') }}" class="nav-link {{ request()->routeIs('surgery.calendar') ? 'active' : '' }}">
    <i class="bi bi-calendar-week"></i> Surgery Calendar
</a>
@if($role === 'or-coordinator')
<div class="sb-nav-label" style="margin-top:.5rem;">REPORTS</div>
<a href="{{ route('reports.surgery.activity') }}" class="nav-link {{ request()->routeIs('reports.surgery.*') ? 'active' : '' }}">
    <i class="bi bi-file-earmark-bar-graph"></i> Surgery Reports
</a>
@endif
@endif

@endif
@endif




{{-- ── Dropdown styles (scoped to sidebar, admin only) ── --}}
@once
<style>
    #sidebar .nav-link {
        border-left: none !important;
        margin: 2px 12px;
        border-radius: 6px;
        padding: 0.55rem 0.85rem;
        color: var(--sidebar-text-soft);
        font-family: var(--font-body);
        font-size: .84rem;
        font-weight: 400;
        display: flex;
        align-items: center;
        gap: .65rem;
        text-decoration: none;
        transition: background-color 0.15s, color 0.15s, transform 0.1s;
    }

    #sidebar .nav-link i:first-child,
    #sidebar .sb-group-toggle i:first-child,
    #sidebar .nav-link.sb-sub i:first-child {
        width: 20px !important;
        text-align: center !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        font-size: 1rem;
        opacity: .75;
        flex-shrink: 0 !important;
        transition: transform 0.15s ease;
    }

    #sidebar .nav-link:hover {
        background: var(--sidebar-hover-bg) !important;
        color: var(--sidebar-hover-text) !important;
    }
    #sidebar .nav-link:hover i:first-child {
        opacity: 1;
        transform: scale(1.05);
    }

    #sidebar .nav-link.active {
        background: var(--sidebar-active-bg) !important;
        color: var(--sidebar-active-text) !important;
        font-weight: 600;
    }
    #sidebar .nav-link.active i:first-child {
        opacity: 1;
        color: var(--sidebar-active-text);
    }
    #sidebar .nav-link:active {
        transform: scale(0.98);
    }

    /* ── Group toggle button ── */
    .sb-group-toggle {
        background: none;
        border: none !important;
        display: flex;
        align-items: center;
        gap: .65rem;
        width: calc(100% - 24px) !important;
        margin: 2px 12px;
        border-radius: 6px;
        text-align: left;
        font-family: var(--font-body);
        font-size: .84rem;
        font-weight: 400;
        color: var(--sidebar-text-soft);
        padding: 0.55rem 0.85rem;
        cursor: pointer;
        text-transform: none;
        transition: background-color 0.15s, color 0.15s, transform 0.1s;
    }
    
    .sb-group-toggle:hover {
        background: var(--sidebar-hover-bg) !important;
        color: var(--sidebar-hover-text) !important;
    }
    .sb-group-toggle:hover i:first-child {
        opacity: 1;
        transform: scale(1.05);
    }

    .sb-group-toggle.active {
        background: var(--sidebar-active-bg) !important;
        color: var(--sidebar-active-text) !important;
        font-weight: 600;
    }
    .sb-group-toggle.active i:first-child {
        opacity: 1;
        color: var(--signal);
    }
    .sb-group-toggle:active {
        transform: scale(0.98);
    }

    /* Label text + module badge */
    .sb-group-label {
        flex: 1;
        display: flex;
        align-items: center;
        gap: .4rem;
    }
    .sb-badge {
        font-family: var(--font-mono);
        font-size: .52rem;
        font-weight: 650;
        letter-spacing: .06em;
        background: rgba(20,199,154,.12);
        color: var(--signal);
        border: 1px solid rgba(20,199,154,0.25);
        border-radius: .25rem;
        padding: .06rem .28rem;
        line-height: 1.4;
        transition: background-color 0.15s;
    }
    .sb-group-toggle.active .sb-badge,
    .sb-group-toggle:hover .sb-badge {
        background: rgba(20,199,154,.2);
    }

    /* Rotating chevron */
    .sb-chevron {
        font-size: .65rem;
        color: var(--sidebar-text-soft);
        flex-shrink: 0;
        transition: transform .2s ease, color .15s;
    }
    .sb-group-toggle[aria-expanded="true"] .sb-chevron {
        transform: rotate(-180deg);
        color: var(--signal);
    }
    @media (prefers-reduced-motion: reduce) {
        .sb-chevron { transition: none; }
    }

    /* Sub-items list body */
    .sb-group-body {
        border-left: 1px solid var(--sidebar-border);
        margin-left: 1.85rem;
        padding-left: 0.1rem;
        margin-top: 1px;
        margin-bottom: 3px;
    }
    .nav-link.sb-sub {
        font-size: .81rem !important;
        padding: .4rem 0.85rem !important;
        color: var(--sidebar-text-soft) !important;
        margin: 1px 12px 1px 4px !important;
        border-radius: 5px !important;
    }
    .nav-link.sb-sub i {
        font-size: .82rem !important;
        opacity: .55;
    }
    .nav-link.sb-sub:hover {
        background: var(--sidebar-hover-bg) !important;
        color: var(--sidebar-hover-text) !important;
    }
    .nav-link.sb-sub:hover i { opacity: .85; }
    .nav-link.sb-sub.active {
        background: var(--sidebar-active-bg) !important;
        color: var(--sidebar-active-text) !important;
        font-weight: 500 !important;
    }
    .nav-link.sb-sub.active i { opacity: 1; }
</style>
@endonce
