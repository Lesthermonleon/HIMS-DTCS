<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Diet\DietPlanController;
use App\Http\Controllers\Diet\DietRequestController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\Lab\LabRequestController;
use App\Http\Controllers\Lab\LabResultController;
use App\Http\Controllers\MedisenseController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\Pharmacy\DispensingController;
use App\Http\Controllers\Pharmacy\MedicineAvailabilityController;
use App\Http\Controllers\Pharmacy\PrescriptionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Radiology\RadiologyReportController;
use App\Http\Controllers\Radiology\RadiologyRequestController;
use App\Http\Controllers\Surgery\SurgeryRequestController;
use App\Http\Controllers\Surgery\SurgeryScheduleController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — DTCS Hospital Management System

|--------------------------------------------------------------------------
*/

// ── Auth Routes (Breeze) ─────────────────────────────────────────────────
require __DIR__.'/auth.php';

// ── Root redirect ────────────────────────────────────────────────────────
Route::get('/', fn() => redirect()->route('login'));

// ── Public Branding Assets ───────────────────────────────────────────────
Route::get('/branding/logo', [\App\Http\Controllers\BrandingController::class, 'logo'])->name('branding.logo');

// ═══════════════════════════════════════════════════════════════════════
// AUTHENTICATED ROUTES (all roles must be logged in)
// ═══════════════════════════════════════════════════════════════════════
Route::middleware(['auth', 'otp.verified'])->group(function () {

    // ── Skeleton System Demo (admin-only developer reference) ─────────
    Route::get('/skeleton-demo', fn() => view('skeleton-demo'))
         ->middleware('role:admin')
         ->name('skeleton.demo');

    // ── Fallback/Generic Dashboard (For role-less users & Breeze tests) ──
    Route::get('/dashboard', function () {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        $role = $user?->primaryRoleModel();
        $dashboardRoute = $role?->dashboard_route;

        if ($dashboardRoute && Route::has($dashboardRoute)) {
            return redirect()->route($dashboardRoute);
        }

        return view('dashboard.default');
    })->name('dashboard');


    // ── Profile ───────────────────────────────────────────────────────
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // ── Global Search ──────────────────────────────────────────────────
    Route::get('/global-search', [GlobalSearchController::class, 'search'])->name('global-search');

    // ── Notifications ─────────────────────────────────────────────────
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
    Route::get('/notifications/recent', [NotificationController::class, 'recent'])->name('notifications.recent');
    Route::get('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');

    // ── Internal Staff Messages ───────────────────────────────────────
    Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
    Route::get('/messages/create', [MessageController::class, 'create'])->name('messages.create');
    Route::get('/messages/recent', [MessageController::class, 'recent'])->name('messages.recent');
    Route::get('/messages/{conversation}', [MessageController::class, 'show'])->name('messages.show');
    Route::post('/messages', [MessageController::class, 'store'])->name('messages.store');

    // ── Settings ──────────────────────────────────────────────────────
    Route::get('/settings', fn() => view('settings.index'))->name('settings.index');

    // ── Patients ──────────────────────────────────────────────────────
    // Patient Information module — read-only. Search, view, and archive only.
    // Patient records are owned by the upstream patient data / registration system.
    Route::prefix('patients')
         ->name('patients.')
         ->middleware('role:super_admin,admin,doctor')
         ->group(function () {
             Route::get('/',         [PatientController::class, 'index'])->name('index');
             Route::get('/{patient}',[PatientController::class, 'show'])->name('show');
             Route::delete('/{patient}', [PatientController::class, 'destroy'])->name('destroy');
         });

    // ═══════════════════════════════════════════════════════════════════
    // SUPER ADMIN ROUTES (Master System Authority)
    // ═══════════════════════════════════════════════════════════════════
    Route::prefix('super-admin')
         ->middleware('role:super_admin')
         ->name('super-admin.')
         ->group(function () {
             Route::get('/dashboard', [\App\Http\Controllers\SuperAdmin\DashboardController::class, 'index'])->name('dashboard');

             // Master Catalog
             Route::get('/catalog', [\App\Http\Controllers\SuperAdmin\CatalogController::class, 'index'])->name('catalog.index');
             Route::post('/catalog/services', [\App\Http\Controllers\SuperAdmin\CatalogController::class, 'storeService'])->name('catalog.services.store');
             Route::put('/catalog/services/{service}', [\App\Http\Controllers\SuperAdmin\CatalogController::class, 'updateService'])->name('catalog.services.update');
             Route::patch('/catalog/services/{service}/status', [\App\Http\Controllers\SuperAdmin\CatalogController::class, 'updateServiceStatus'])->name('catalog.services.status');
             Route::patch('/catalog/lab-tests/{labTest}/lifecycle', [\App\Http\Controllers\SuperAdmin\CatalogController::class, 'updateLabTestLifecycle'])->name('catalog.lab-tests.lifecycle');

             // Diagnostic Services Configuration & Redirects
             Route::get('/services/diagnostic', [\App\Http\Controllers\SuperAdmin\CatalogController::class, 'diagnosticServices'])->name('services.diagnostic');
             Route::get('/services/clinical', fn() => redirect()->route('super-admin.services.diagnostic'))->name('services.clinical');
             Route::get('/services/treatment', fn() => redirect()->route('super-admin.catalog.index'))->name('services.treatment');

             // Clinical Codes (ICD / CPT / LOINC)
             Route::get('/clinical-codes', [\App\Http\Controllers\SuperAdmin\ClinicalCodeController::class, 'index'])->name('clinical-codes.index');
             Route::patch('/clinical-codes/systems/{system}/verify-license', [\App\Http\Controllers\SuperAdmin\ClinicalCodeController::class, 'verifyLicense'])->name('clinical-codes.verify-license');
             Route::post('/clinical-codes/systems/{system}/stage', [\App\Http\Controllers\SuperAdmin\ClinicalCodeController::class, 'stageVersion'])->name('clinical-codes.stage');
             Route::post('/clinical-codes/versions/{version}/publish', [\App\Http\Controllers\SuperAdmin\ClinicalCodeController::class, 'publish'])->name('clinical-codes.publish');
             Route::post('/clinical-codes/versions/{version}/rollback', [\App\Http\Controllers\SuperAdmin\ClinicalCodeController::class, 'rollback'])->name('clinical-codes.rollback');

             // Protected Audit Vault
             Route::get('/audit-vault', [\App\Http\Controllers\SuperAdmin\AuditVaultController::class, 'index'])->name('audit-vault.index');
             Route::get('/audit-vault/export', [\App\Http\Controllers\SuperAdmin\AuditVaultController::class, 'export'])->name('audit-vault.export');
             Route::get('/audit-vault/{auditLog}', [\App\Http\Controllers\SuperAdmin\AuditVaultController::class, 'show'])->name('audit-vault.show');

             // Full User Account Management (All Roles)
             Route::get('/users', [\App\Http\Controllers\SuperAdmin\UserManagementController::class, 'index'])->name('users.index');
             Route::get('/users/create', [\App\Http\Controllers\SuperAdmin\UserManagementController::class, 'create'])->name('users.create');
             Route::post('/users', [\App\Http\Controllers\SuperAdmin\UserManagementController::class, 'store'])->name('users.store');
             Route::get('/users/{user}', [\App\Http\Controllers\SuperAdmin\UserManagementController::class, 'show'])->name('users.show');
             Route::get('/users/{user}/edit', [\App\Http\Controllers\SuperAdmin\UserManagementController::class, 'edit'])->name('users.edit');
             Route::put('/users/{user}', [\App\Http\Controllers\SuperAdmin\UserManagementController::class, 'update'])->name('users.update');
             Route::delete('/users/{user}', [\App\Http\Controllers\SuperAdmin\UserManagementController::class, 'archive'])->name('users.destroy');
             Route::post('/users/{id}/restore', [\App\Http\Controllers\SuperAdmin\UserManagementController::class, 'restore'])->name('users.restore');
             Route::delete('/users/{id}/force-delete', [\App\Http\Controllers\SuperAdmin\UserManagementController::class, 'forceDelete'])->name('users.force-delete');
             Route::patch('/users/{user}/toggle-status', [\App\Http\Controllers\SuperAdmin\UserManagementController::class, 'toggleStatus'])->name('users.toggle-status');
             Route::post('/users/{user}/unlock', [\App\Http\Controllers\SuperAdmin\UserManagementController::class, 'unlock'])->name('users.unlock');
             Route::post('/users/{user}/reset-password', [\App\Http\Controllers\SuperAdmin\UserManagementController::class, 'resetPassword'])->name('users.reset-password');
             Route::post('/users/{user}/terminate-session', [\App\Http\Controllers\SuperAdmin\UserManagementController::class, 'terminateSession'])->name('users.terminate-session');

             // Privileged Admin Accounts
             Route::get('/admins', [\App\Http\Controllers\SuperAdmin\AdminAccountController::class, 'index'])->name('admins.index');
             Route::post('/admins', [\App\Http\Controllers\SuperAdmin\AdminAccountController::class, 'store'])->name('admins.store');
             Route::patch('/admins/{user}/toggle-active', [\App\Http\Controllers\SuperAdmin\AdminAccountController::class, 'toggleActive'])->name('admins.toggle-active');
             Route::post('/admins/{user}/unlock', [\App\Http\Controllers\SuperAdmin\AdminAccountController::class, 'unlock'])->name('admins.unlock');
             Route::post('/admins/{user}/reset-password', [\App\Http\Controllers\SuperAdmin\AdminAccountController::class, 'sendPasswordReset'])->name('admins.reset-password');
             Route::delete('/admins/{user}', [\App\Http\Controllers\SuperAdmin\AdminAccountController::class, 'archive'])->name('admins.archive');
             Route::post('/admins/{id}/restore', [\App\Http\Controllers\SuperAdmin\AdminAccountController::class, 'restore'])->name('admins.restore');

             // Doctor Provisioning Authorizations
             Route::get('/doctor-provisioning', [\App\Http\Controllers\SuperAdmin\DoctorProvisioningController::class, 'index'])->name('doctor-provisioning.index');
             Route::post('/doctor-provisioning/{accessRequest}/approve', [\App\Http\Controllers\SuperAdmin\DoctorProvisioningController::class, 'approve'])->name('doctor-provisioning.approve');
             Route::post('/doctor-provisioning/{accessRequest}/reject', [\App\Http\Controllers\SuperAdmin\DoctorProvisioningController::class, 'reject'])->name('doctor-provisioning.reject');

             // System & Security Settings
             Route::get('/settings', [\App\Http\Controllers\SuperAdmin\SystemSettingsController::class, 'index'])->name('settings.index');
             Route::post('/settings', [\App\Http\Controllers\SuperAdmin\SystemSettingsController::class, 'update'])->name('settings.update');
              // Department Configuration
              Route::get('/departments', [\App\Http\Controllers\SuperAdmin\DepartmentController::class, 'index'])->name('departments.index');

              // Database Backup & Maintenance
             Route::get('/backup', [\App\Http\Controllers\SuperAdmin\BackupController::class, 'index'])->name('backup.index');
             Route::post('/backup/trigger', [\App\Http\Controllers\SuperAdmin\BackupController::class, 'trigger'])->name('backup.trigger');
             Route::post('/backup/prune', [\App\Http\Controllers\SuperAdmin\BackupController::class, 'prune'])->name('backup.prune');
         });

    // ═══════════════════════════════════════════════════════════════════
    // ADMIN ROUTES (Daily Hospital Operations)
    // ═══════════════════════════════════════════════════════════════════
    Route::prefix('admin')
         ->name('admin.')
         ->group(function () {
             // System Governance Shared Routes (Super Admin & Admin)
             Route::middleware('role:super_admin,admin')->group(function () {
                 Route::resource('users', UserController::class)->except(['create', 'store']);
                 Route::resource('roles', RoleController::class);
                 Route::post('users/{user}/assign-role', [UserController::class, 'assignRole'])->name('users.assign-role');
                 Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
                 Route::post('users/{id}/restore', [UserController::class, 'restore'])->name('users.restore');
                 Route::get('users/{id}/archived', [UserController::class, 'showArchived'])->name('users.archived');
                 Route::post('users/{user}/unlock', [UserController::class, 'unlockAccount'])->name('users.unlock');
                  Route::post('users/{user}/terminate-session', [UserController::class, 'terminateSession'])->name('users.terminate-session');

                 // ── Hospital Settings & Financial Rules ────────────────────
                 Route::get('hospital-settings', [\App\Http\Controllers\Admin\HospitalSettingsController::class, 'index'])->name('hospital-settings.index');
                 Route::post('hospital-settings/branding', [\App\Http\Controllers\Admin\HospitalSettingsController::class, 'updateBranding'])->name('hospital-settings.branding');
                 Route::post('hospital-settings/hours', [\App\Http\Controllers\Admin\HospitalSettingsController::class, 'updateOperatingHours'])->name('hospital-settings.hours');
                 Route::post('hospital-settings/queue', [\App\Http\Controllers\Admin\HospitalSettingsController::class, 'updateQueueSettings'])->name('hospital-settings.queue');
                 Route::post('hospital-settings/financial-rules', [\App\Http\Controllers\Admin\HospitalSettingsController::class, 'storeFinancialRule'])->name('hospital-settings.financial-rules');
             });

             // Daily Operational Administration (Admin only)
             Route::middleware('role:admin')->group(function () {
                 Route::get('/dashboard', [DashboardController::class, 'admin'])->name('dashboard');

                 // ── Doctor Account Management ──────────────────────────────
                 Route::get('doctors', [\App\Http\Controllers\Admin\DoctorAccountController::class, 'index'])->name('doctors.index');
                 Route::post('doctors/request-provisioning', [\App\Http\Controllers\Admin\DoctorAccountController::class, 'requestProvisioning'])->name('doctors.request-provisioning');
                 Route::patch('doctors/{user}/toggle-active', [\App\Http\Controllers\Admin\DoctorAccountController::class, 'toggleActive'])->name('doctors.toggle-active');
                 Route::post('doctors/{user}/unlock', [\App\Http\Controllers\Admin\DoctorAccountController::class, 'unlock'])->name('doctors.unlock');
                 Route::post('doctors/{user}/reset-password', [\App\Http\Controllers\Admin\DoctorAccountController::class, 'sendPasswordReset'])->name('doctors.reset-password');

                 // ── Staff Account Management ───────────────────────────────
                 Route::get('staff', [\App\Http\Controllers\Admin\StaffAccountController::class, 'index'])->name('staff.index');
                 Route::post('staff', [\App\Http\Controllers\Admin\StaffAccountController::class, 'store'])->name('staff.store');
                 Route::patch('staff/{user}/toggle-active', [\App\Http\Controllers\Admin\StaffAccountController::class, 'toggleActive'])->name('staff.toggle-active');
                 Route::post('staff/{user}/unlock', [\App\Http\Controllers\Admin\StaffAccountController::class, 'unlock'])->name('staff.unlock');
                 Route::post('staff/{user}/reset-password', [\App\Http\Controllers\Admin\StaffAccountController::class, 'sendPasswordReset'])->name('staff.reset-password');

                 // ── Operations Monitor ─────────────────────────────────────
                 Route::get('operations', [\App\Http\Controllers\Admin\OperationsMonitorController::class, 'index'])->name('operations.index');

                 // ── Patient Intake & Portal Account Linking ────────────────
                 Route::get('patients/intake', [\App\Http\Controllers\Admin\PatientIntakeController::class, 'create'])->name('patients.intake');
                 Route::post('patients/{patient}/link-portal', [\App\Http\Controllers\Admin\PatientIntakeController::class, 'linkAccount'])->name('patients.link-portal');

                 // ── Audit Logs (read-only) ──────────────────────────────────────────────────────────────
                 Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
                 Route::get('audit-logs/{auditLog}', [AuditLogController::class, 'show'])->name('audit-logs.show');
             });
         });

    // ═══════════════════════════════════════════════════════════════════
    // ── Outpatient Appointments & Clinic Scheduling (Admin & Doctor) ──
    Route::prefix('admin')
         ->name('admin.')
         ->middleware('role:super_admin,admin,doctor')
         ->group(function () {
             Route::get('appointments', [\App\Http\Controllers\Admin\AppointmentController::class, 'index'])->name('appointments.index');
             Route::post('appointments', [\App\Http\Controllers\Admin\AppointmentController::class, 'store'])->name('appointments.store');
             Route::patch('appointments/{appointment}/status', [\App\Http\Controllers\Admin\AppointmentController::class, 'updateStatus'])->name('appointments.status');
         });

    // DOCTOR DASHBOARD
    // ═══════════════════════════════════════════════════════════════════
    Route::get('/doctor/dashboard', [DashboardController::class, 'doctor'])
         ->middleware('role:super_admin,admin,doctor')
         ->name('doctor.dashboard');

    // ═══════════════════════════════════════════════════════════════════
    // VIRTUAL MEDISENSE AI — Clinical Decision Support (Doctor-Only)
    // ═══════════════════════════════════════════════════════════════════
    Route::prefix('medisense')
         ->name('medisense.')
         ->middleware('role:doctor')
         ->group(function () {
             Route::get('/', [MedisenseController::class, 'index'])->name('index');
             Route::get('/{patient}', [MedisenseController::class, 'show'])->name('show');
             Route::post('/{patient}/analyze', [MedisenseController::class, 'analyze'])->name('analyze');
             Route::patch('/{patient}/findings', [MedisenseController::class, 'updateFindings'])->name('update-findings');
         });

    // ═══════════════════════════════════════════════════════════════════
    // LIS — Laboratory Information System
    // ═══════════════════════════════════════════════════════════════════
    Route::prefix('lab')
         ->name('lab.')
         ->group(function () {

             // Dashboard for lab staff
             Route::get('/dashboard', [DashboardController::class, 'lab'])
                  ->middleware('role:admin,med-tech,doctor')
                  ->name('dashboard');

             // Lab Requests (Doctor creates, Med-Tech manages)
             Route::resource('requests', LabRequestController::class)
                  ->parameters(['requests' => 'labRequest'])
                  ->middleware('role:super_admin,admin,doctor,med-tech');

             Route::patch('requests/{labRequest}/receive', [LabRequestController::class, 'receive'])
                  ->middleware('role:med-tech')
                  ->name('requests.receive');

             Route::patch('requests/{labRequest}/reject', [LabRequestController::class, 'reject'])
                  ->middleware('role:med-tech')
                  ->name('requests.reject');

             Route::get('requests/{labRequest}/print', [LabRequestController::class, 'print'])
                  ->middleware('role:super_admin,admin,doctor,med-tech')
                  ->name('requests.print');

             // Lab Results (Med-Tech encodes)
             Route::get('results/{labResult}/print', [LabResultController::class, 'print'])
                  ->middleware('role:admin,med-tech,doctor')
                  ->name('results.print');

             Route::resource('results', LabResultController::class)
                   ->parameters(['results' => 'labResult'])
                   ->middleware('role:admin,med-tech,doctor')
                   ->middlewareFor(['store', 'update', 'destroy'], 'permission:lis.results.enter');

             Route::patch('results/{labResult}/validate', [LabResultController::class, 'validate'])
                  ->middleware(['role:med-tech', 'permission:lis.results.verify'])
                  ->name('results.validate');

             Route::patch('results/{labResult}/release', [LabResultController::class, 'release'])
                  ->middleware(['role:med-tech', 'permission:lis.results.release'])
                  ->name('results.release');
         });

    // ═══════════════════════════════════════════════════════════════════
    // RIS — Radiology Information System
    // ═══════════════════════════════════════════════════════════════════
    Route::prefix('radiology')
         ->name('radiology.')
         ->group(function () {

             Route::get('/dashboard', [DashboardController::class, 'radiology'])
                  ->middleware('role:super_admin,admin,doctor,rad-tech,radiologist')
                  ->name('dashboard');

             Route::resource('requests', RadiologyRequestController::class)
                  ->parameters(['requests' => 'radiologyRequest'])
                  ->middleware('role:super_admin,admin,doctor,rad-tech,radiologist');

             Route::patch('requests/{radiologyRequest}/schedule', [RadiologyRequestController::class, 'schedule'])
                  ->middleware('role:rad-tech')
                  ->name('requests.schedule');

             Route::patch('requests/{radiologyRequest}/start', [RadiologyRequestController::class, 'start'])
                  ->middleware('role:rad-tech')
                  ->name('requests.start');

             Route::post('requests/{radiologyRequest}/upload', [RadiologyRequestController::class, 'uploadImage'])
                  ->middleware('role:rad-tech')
                  ->name('requests.upload');

             Route::get('images/{image}/view', [RadiologyRequestController::class, 'viewImage'])
                  ->middleware('role:super_admin,admin,doctor,rad-tech,radiologist')
                  ->name('images.view');

             Route::patch('requests/{radiologyRequest}/complete', [RadiologyRequestController::class, 'complete'])
                  ->middleware('role:rad-tech')
                  ->name('requests.complete');

             Route::resource('reports', RadiologyReportController::class)
                  ->parameters(['reports' => 'radiologyReport'])
                  ->middleware('role:admin,radiologist,doctor,rad-tech');

             Route::get('reports/{radiologyReport}/print', [RadiologyReportController::class, 'print'])
                  ->middleware('role:admin,radiologist,doctor,rad-tech')
                  ->name('reports.print');

             Route::patch('reports/{radiologyReport}/approve', [RadiologyReportController::class, 'approve'])
                  ->middleware('role:radiologist')
                  ->name('reports.approve');

             Route::patch('reports/{radiologyReport}/return-for-correction', [RadiologyReportController::class, 'returnForCorrection'])
                  ->middleware('role:radiologist')
                  ->name('reports.return');

             Route::patch('reports/{radiologyReport}/release', [RadiologyReportController::class, 'release'])
                  ->middleware('role:radiologist')
                  ->name('reports.release');
         });

    // ═══════════════════════════════════════════════════════════════════
    // PMS — Pharmacy Management System
    // ═══════════════════════════════════════════════════════════════════
    Route::prefix('pharmacy')
         ->name('pharmacy.')
         ->group(function () {

             Route::get('/dashboard', [DashboardController::class, 'pharmacy'])
                  ->middleware('role:super_admin,admin,doctor,pharmacist')
                  ->name('dashboard');

             Route::get('prescriptions/{prescription}/print', [PrescriptionController::class, 'print'])
                  ->middleware('role:super_admin,admin,doctor,pharmacist')
                  ->name('prescriptions.print');

             Route::resource('prescriptions', PrescriptionController::class)
                  ->middleware('role:super_admin,admin,doctor,pharmacist');

             Route::patch('prescriptions/{prescription}/verify', [PrescriptionController::class, 'verify'])
                  ->middleware(['role:pharmacist', 'permission:pms.prescriptions.verify'])
                  ->name('prescriptions.verify');

             Route::patch('prescriptions/{prescription}/reject', [PrescriptionController::class, 'reject'])
                  ->middleware(['role:pharmacist', 'permission:pms.prescriptions.verify'])
                  ->name('prescriptions.reject');

             Route::get('dispensing/{dispensing}/print', [DispensingController::class, 'print'])
                  ->middleware('role:admin,pharmacist')
                  ->name('dispensing.print');

             Route::resource('dispensing', DispensingController::class)
                   ->middleware('role:admin,pharmacist')
                   ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'permission:pms.dispense');

             Route::get('medicines', [MedicineAvailabilityController::class, 'index'])
                  ->middleware('role:super_admin,admin,doctor,pharmacist')
                  ->name('medicines.index');

             // ── Point of Sale (POS) — Integrated into Pharmacist Account ──
             Route::get('pos', [\App\Http\Controllers\Pharmacy\PosController::class, 'index'])
                  ->middleware('role:pharmacist')
                  ->name('pos.index');

             Route::post('pos/checkout', [\App\Http\Controllers\Pharmacy\PosController::class, 'checkout'])
                  ->middleware('role:pharmacist')
                  ->name('pos.checkout');

             Route::get('pos/receipt/{transaction}', [\App\Http\Controllers\Pharmacy\PosController::class, 'receipt'])
                  ->middleware('role:super_admin,admin,pharmacist')
                  ->name('pos.receipt');

             Route::patch('pos/transactions/{transaction}/void-refund', [\App\Http\Controllers\Pharmacy\PosController::class, 'voidOrRefund'])
                  ->middleware(['role:pharmacist', 'permission:pms.pos.refund_void'])
                  ->name('pos.void-refund');

             // ── Inventory & Static Demo Low-Stock Replenishment ──
             Route::get('inventory/items', [\App\Http\Controllers\Pharmacy\InventoryController::class, 'items'])
                  ->middleware('role:super_admin,admin,pharmacist')
                  ->name('inventory.items');

             Route::get('inventory/purchase-orders', [\App\Http\Controllers\Pharmacy\InventoryController::class, 'purchaseOrders'])
                  ->middleware('role:super_admin,admin,pharmacist')
                  ->name('inventory.purchase-orders');

             Route::get('inventory/counts', [\App\Http\Controllers\Pharmacy\InventoryController::class, 'counts'])
                  ->middleware('role:super_admin,admin,pharmacist')
                  ->name('inventory.counts');

             Route::get('inventory/adjustments', [\App\Http\Controllers\Pharmacy\InventoryController::class, 'adjustments'])
                  ->middleware('role:super_admin,admin,pharmacist')
                  ->name('inventory.adjustments');

             Route::get('inventory/replenishment', [\App\Http\Controllers\Pharmacy\ReplenishmentRequestController::class, 'index'])
                  ->middleware('role:super_admin,admin,pharmacist')
                  ->name('inventory.replenishment.index');

             Route::post('inventory/replenishment', [\App\Http\Controllers\Pharmacy\ReplenishmentRequestController::class, 'store'])
                  ->middleware(['role:pharmacist', 'permission:pms.replenishment.demo'])
                  ->name('inventory.replenishment.store');

             Route::patch('inventory/replenishment/{replenishmentRequest}/status', [\App\Http\Controllers\Pharmacy\ReplenishmentRequestController::class, 'updateDemoStatus'])
                  ->middleware(['role:pharmacist', 'permission:pms.replenishment.demo'])
                  ->name('inventory.replenishment.status');
         });


    // ═══════════════════════════════════════════════════════════════════
    // SORS — Surgery & Operating Room Scheduler
    // ═══════════════════════════════════════════════════════════════════
    Route::prefix('surgery')
         ->name('surgery.')
         ->group(function () {

             Route::get('/dashboard', [DashboardController::class, 'surgery'])
                  ->middleware('role:super_admin,admin,doctor,or-coordinator,or-staff')
                  ->name('dashboard');

             Route::get('/calendar', [SurgeryScheduleController::class, 'calendar'])
                  ->middleware('role:super_admin,admin,doctor,or-coordinator,or-staff')
                  ->name('calendar');

             Route::get('/calendar/events', [SurgeryScheduleController::class, 'calendarEvents'])
                  ->middleware('role:super_admin,admin,doctor,or-coordinator,or-staff')
                  ->name('calendar.events');

             Route::resource('requests', SurgeryRequestController::class)
                  ->only(['create', 'store', 'edit', 'update', 'destroy'])
                  ->parameters(['requests' => 'surgeryRequest'])
                  ->middleware('role:doctor');

             Route::resource('requests', SurgeryRequestController::class)
                  ->only(['index', 'show'])
                  ->parameters(['requests' => 'surgeryRequest'])
                  ->middleware('role:super_admin,admin,doctor,or-coordinator');

             Route::patch('requests/{surgeryRequest}/cancel', [SurgeryRequestController::class, 'cancel'])
                  ->middleware('role:doctor,or-coordinator')
                  ->name('requests.cancel');

             Route::get('schedules/{surgerySchedule}/print', [SurgeryScheduleController::class, 'print'])
                  ->middleware('role:super_admin,admin,doctor,or-coordinator,or-staff')
                  ->name('schedules.print');

             Route::resource('schedules', SurgeryScheduleController::class)
                  ->only(['create', 'store', 'edit', 'update', 'destroy'])
                  ->parameters(['schedules' => 'surgerySchedule'])
                  ->middleware('role:or-coordinator');

             Route::resource('schedules', SurgeryScheduleController::class)
                  ->only(['index', 'show'])
                  ->parameters(['schedules' => 'surgerySchedule'])
                  ->middleware('role:super_admin,admin,doctor,or-coordinator,or-staff');

             Route::patch('schedules/{surgerySchedule}/cancel', [SurgeryScheduleController::class, 'cancel'])
                  ->middleware('role:or-coordinator')
                  ->name('schedules.cancel');

             Route::patch('schedules/{surgerySchedule}/readiness', [SurgeryScheduleController::class, 'updateReadiness'])
                  ->middleware('role:or-coordinator,or-staff')
                  ->name('schedules.readiness');

             Route::patch('schedules/{surgerySchedule}/start', [SurgeryScheduleController::class, 'start'])
                  ->middleware('role:or-coordinator,or-staff')
                  ->name('schedules.start');

             Route::patch('schedules/{surgerySchedule}/complete', [SurgeryScheduleController::class, 'complete'])
                  ->middleware('role:or-coordinator,or-staff')
                  ->name('schedules.complete');
         });

    // ═══════════════════════════════════════════════════════════════════
    // DNMS — Diet & Nutrition Management System
    // ═══════════════════════════════════════════════════════════════════
    Route::prefix('diet')
         ->name('diet.')
         ->group(function () {

             Route::get('/dashboard', [DashboardController::class, 'diet'])
                  ->middleware('role:super_admin,admin,doctor')
                  ->name('dashboard');

             // Clinical mutation routes (registered first so /requests/create and /plans/create are not captured by show).
             // Only the owning clinical role (`doctor`) may mutate; admin / super_admin are read-only monitors.
             // DietRequestPolicy / DietPlanPolicy additionally enforce ownership, patient authorization & status.
             Route::resource('requests', DietRequestController::class)
                  ->parameters(['requests' => 'dietRequest'])
                  ->only(['create', 'store', 'edit', 'update', 'destroy'])
                  ->middleware('role:doctor');

             Route::resource('requests', DietRequestController::class)
                  ->parameters(['requests' => 'dietRequest'])
                  ->only(['index', 'show'])
                  ->middleware('role:super_admin,admin,doctor');

             Route::get('plans/{dietPlan}/print', [DietPlanController::class, 'print'])
                  ->middleware('role:super_admin,admin,doctor')
                  ->name('plans.print');

             Route::resource('plans', DietPlanController::class)
                  ->parameters(['plans' => 'dietPlan'])
                  ->only(['create', 'store', 'edit', 'update', 'destroy'])
                  ->middleware('role:doctor');

             Route::resource('plans', DietPlanController::class)
                  ->parameters(['plans' => 'dietPlan'])
                  ->only(['index', 'show'])
                  ->middleware('role:super_admin,admin,doctor');

             Route::patch('plans/{dietPlan}/complete', [DietPlanController::class, 'complete'])
                  ->middleware('role:doctor')
                  ->name('plans.complete');

         });

    // ═══════════════════════════════════════════════════════════════════
    // REPORTS MODULE
    // ═══════════════════════════════════════════════════════════════════
    Route::prefix('reports')
         ->name('reports.')
         ->group(function () {

             Route::get('/', [\App\Http\Controllers\ReportController::class, 'index'])->name('index');

             // ── Laboratory Reports ──
             Route::prefix('laboratory')->name('laboratory.')->middleware('role:super_admin,admin,doctor,med-tech')->group(function () {
                 Route::get('/activity',  [\App\Http\Controllers\ReportController::class, 'labActivity'])->name('activity');
                 Route::get('/volume',    [\App\Http\Controllers\ReportController::class, 'labVolume'])->name('volume');
                 Route::get('/completed', [\App\Http\Controllers\ReportController::class, 'labCompleted'])->name('completed');
                 Route::get('/pending',   [\App\Http\Controllers\ReportController::class, 'labPending'])->name('pending');
             });

             // ── Radiology Reports ──
             Route::prefix('radiology')->name('radiology.')->middleware('role:super_admin,admin,doctor,rad-tech,radiologist')->group(function () {
                 Route::get('/activity',  [\App\Http\Controllers\ReportController::class, 'radiologyActivity'])->name('activity');
                 Route::get('/volume',    [\App\Http\Controllers\ReportController::class, 'radiologyVolume'])->name('volume');
                 Route::get('/completed', [\App\Http\Controllers\ReportController::class, 'radiologyCompleted'])->name('completed');
                 Route::get('/pending',   [\App\Http\Controllers\ReportController::class, 'radiologyPending'])->name('pending');
             });

             // ── Pharmacy Reports ──
             Route::prefix('pharmacy')->name('pharmacy.')->middleware('role:super_admin,admin,doctor,pharmacist')->group(function () {
                 Route::get('/activity',   [\App\Http\Controllers\ReportController::class, 'pharmacyActivity'])->name('activity');
                 Route::get('/dispensing', [\App\Http\Controllers\ReportController::class, 'pharmacyDispensing'])->name('dispensing');
                 Route::get('/pending',    [\App\Http\Controllers\ReportController::class, 'pharmacyPending'])->name('pending');
             });

             // ── Surgery Reports ──
             Route::prefix('surgery')->name('surgery.')->middleware('role:super_admin,admin,doctor,or-coordinator')->group(function () {
                 Route::get('/activity',       [\App\Http\Controllers\ReportController::class, 'surgeryActivity'])->name('activity');
                 Route::get('/completed',      [\App\Http\Controllers\ReportController::class, 'surgeryCompleted'])->name('completed');
                 Route::get('/cancelled',      [\App\Http\Controllers\ReportController::class, 'surgeryCancelled'])->name('cancelled');
                 Route::get('/or-utilization', [\App\Http\Controllers\ReportController::class, 'surgeryOrUtilization'])->name('or-utilization');
             });

             // ── Diet & Nutrition Reports ──
             Route::prefix('diet')->name('diet.')->middleware('role:super_admin,admin,doctor')->group(function () {
                 Route::get('/activity',  [\App\Http\Controllers\ReportController::class, 'dietActivity'])->name('activity');
                 Route::get('/active',    [\App\Http\Controllers\ReportController::class, 'dietActive'])->name('active');
                 Route::get('/completed', [\App\Http\Controllers\ReportController::class, 'dietCompleted'])->name('completed');
             });

             // ── Clinical Summary Reports ──
             Route::prefix('clinical')->name('clinical.')->middleware('role:super_admin,admin,doctor')->group(function () {
                 Route::get('/patient-activity',  [\App\Http\Controllers\ReportController::class, 'clinicalPatientActivity'])->name('patient-activity');
                 Route::get('/doctor-activity',   [\App\Http\Controllers\ReportController::class, 'clinicalDoctorActivity'])->name('doctor-activity');
                 Route::get('/services-summary',  [\App\Http\Controllers\ReportController::class, 'clinicalServicesSummary'])->name('services-summary');
             });
         });


    // ═══════════════════════════════════════════════════════════════════
    // PATIENT HEALTHCARE PORTAL (Personal Self-Service)
    // ═══════════════════════════════════════════════════════════════════
    Route::prefix('patient')
         ->name('patient.')
         ->middleware('role:patient')
         ->group(function () {
             Route::get('/dashboard', [\App\Http\Controllers\PatientPortal\PatientPortalController::class, 'dashboard'])->name('dashboard');
             Route::get('/profile', [\App\Http\Controllers\PatientPortal\PatientPortalController::class, 'profile'])->name('profile');
             Route::post('/profile', [\App\Http\Controllers\PatientPortal\PatientPortalController::class, 'updateProfile'])->name('profile.update');
             Route::get('/appointments', [\App\Http\Controllers\PatientPortal\PatientPortalController::class, 'appointments'])->name('appointments');
             Route::post('/appointments/book', [\App\Http\Controllers\PatientPortal\PatientPortalController::class, 'bookAppointment'])->name('appointments.book');
              Route::post('/appointments/{appointment}/cancel', [\App\Http\Controllers\PatientPortal\PatientPortalController::class, 'cancelAppointment'])->name('appointments.cancel');
              Route::get('/help', fn() => view('patient.help'))->name('help');
             Route::get('/results', [\App\Http\Controllers\PatientPortal\PatientPortalController::class, 'results'])->name('results');
             Route::get('/prescriptions', [\App\Http\Controllers\PatientPortal\PatientPortalController::class, 'prescriptions'])->name('prescriptions');
             Route::get('/treatments', [\App\Http\Controllers\PatientPortal\PatientPortalController::class, 'treatments'])->name('treatments');
             Route::get('/billing', [\App\Http\Controllers\PatientPortal\PatientPortalController::class, 'billing'])->name('billing');
         });

});

