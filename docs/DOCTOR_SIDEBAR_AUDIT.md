# DTCS HIMS — Doctor Sidebar Audit & Route Alignment

**Repository:** `Lesthermonleon/DTCS`  
**System:** DTCS Hospital Information Management System (Diagnostics, Treatment & Clinical Services)  
**Target:** Doctor Sidebar Navigation (`resources/views/partials/_sidebar.blade.php`) & Route Authorization  
**Date:** November 2026  
**Document Status:** Complete Pre-Implementation Clinical Audit

---

## 1. Executive Summary & Audit Scope

The Doctor in DTCS is a licensed clinician responsible for patient consultation, diagnosing conditions, ordering laboratory and imaging examinations, prescribing medications, requesting operating room procedures, ordering dietary regimens, and utilizing MediSense AI for clinical decision support.

The sidebar in `resources/views/partials/_sidebar.blade.php` serves as the primary navigation interface. This audit evaluates:
1. **Navigation Structure:** Consistency with clinical responsibilities and absence of redundant/duplicate entries.
2. **Appointment Route Integrity:** Deep investigation of `/admin/appointments` (`admin.appointments.index`) to determine whether it is appropriate for Doctor access or breaches administrative boundaries.
3. **MediSense AI Navigation:** Audit of AI navigation entries, ensuring exactly one legitimate link in MAIN.
4. **Clinical Module Authorizations:** Reviewing all 5 modules (LIS, RIS, PMS, SORS, DNMS) to guarantee that doctors have clinical ordering/viewing access without granting departmental administration or technician privileges.
5. **Patient Directory Read-Only Scope:** Ensuring patient master-record editing/creation is absent.
6. **Clinical Reports Authorization:** Verifying that Doctor reports are strictly limited to clinical care analytics.

---

## 2. Current Doctor Sidebar Inventory & Findings

In `resources/views/partials/_sidebar.blade.php` (lines 251–385), the current doctor navigation renders:

```blade
@if($role === 'doctor')
<div class="sb-nav-label" style="margin-top:.5rem;">MAIN</div>
<a href="{{ route('doctor.dashboard') }}" class="nav-link ...">Dashboard</a>
<a href="{{ route('admin.appointments.index') }}" class="nav-link ...">Patient Queue & Appointments</a>
<a href="{{ route('medisense.index') }}" class="nav-link ...">MediSense AI <span class="sb-badge">AI</span></a>

<div class="sb-nav-label" style="margin-top:.5rem;">CLINICAL</div>
... [Laboratory, Radiology, Pharmacy, Surgery, Nutrition groups] ...

<div class="sb-nav-label" style="margin-top:.5rem;">Patients</div>
<a href="{{ route('patients.index') }}" class="nav-link ...">Patient Directory</a>

<div class="sb-nav-label" style="margin-top:.5rem;">REPORTS</div>
<a href="{{ route('reports.index') }}" class="nav-link ...">Clinical Reports</a>
```

### 2.1 Item-by-Item Route & Controller Audit

| # | Sidebar Item | Target Route | Target URL | Controller & Action | Route Middleware | Controller Authorization / Scoping | Audit Finding |
|---|---|---|---|---|---|---|---|
| 1 | **Dashboard** | `doctor.dashboard` | `/doctor/dashboard` | `DashboardController@doctor` | `role:super_admin,admin,doctor` | Scoped strictly to `$doctorId` | **VALID** (Preserve) |
| 2 | **Patient Queue & Appointments** | `admin.appointments.index` | `/admin/appointments` | `AppointmentController@index` | `role:super_admin,admin,doctor` | Enforces `isDoctorScope()`: doctors only see their own consultation queue | **APPROPRIATE WORKFLOW** (Requires documentation & alignment) |
| 3 | **MediSense AI** | `medisense.index` | `/medisense` | `MedisenseController@index` | `role:doctor` | Doctor-only CDSS; patient privacy sanitization | **VALID** (Single entry required in MAIN) |
| 4 | **Laboratory (LIS) Overview** | `lab.dashboard` | `/lab/dashboard` | `DashboardController@lab` | `role:admin,med-tech,doctor` | Departmental overview with doctor request summaries | **VALID** (Preserve) |
| 5 | **Lab Requests** | `lab.requests.index` | `/lab/requests` | `LabRequestController@index` | `role:super_admin,admin,doctor,med-tech` | Doctors view & author lab orders | **VALID** (Preserve) |
| 6 | **Lab Results** | `lab.results.index` | `/lab/results` | `LabResultController@index` | `role:admin,med-tech,doctor` | Doctors view released results. Encode/Validate/Release restricted to Med-Tech | **VALID** (Preserve) |
| 7 | **Radiology (RIS) Overview** | `radiology.dashboard` | `/radiology/dashboard` | `DashboardController@radiology` | `role:super_admin,admin,doctor,rad-tech,radiologist` | Departmental overview | **VALID** (Preserve) |
| 8 | **Imaging Requests** | `radiology.requests.index` | `/radiology/requests` | `RadiologyRequestController@index` | `role:super_admin,admin,doctor,rad-tech,radiologist` | Doctors author imaging orders | **VALID** (Preserve) |
| 9 | **Radiology Reports** | `radiology.reports.index` | `/radiology/reports` | `RadiologyReportController@index` | `role:admin,radiologist,doctor,rad-tech` | Doctors view approved reports; Approval restricted to Radiologist | **VALID** (Preserve) |
| 10 | **Pharmacy (PMS) Overview** | `pharmacy.dashboard` | `/pharmacy/dashboard` | `DashboardController@pharmacy` | `role:super_admin,admin,doctor,pharmacist` | Departmental overview | **VALID** (Preserve) |
| 11 | **Prescriptions** | `pharmacy.prescriptions.index` | `/pharmacy/prescriptions` | `PrescriptionController@index` | `role:super_admin,admin,doctor,pharmacist` | Doctors author prescriptions; Dispensing excluded | **VALID** (Preserve) |
| 12 | **Medicine Availability** | `pharmacy.medicines.index` | `/pharmacy/medicines` | `MedicineAvailabilityController@index` | `role:super_admin,admin,doctor,pharmacist` | Read-only formulary stock check for prescribing | **VALID** (Preserve) |
| 13 | **Surgery (SORS) Overview** | `surgery.dashboard` | `/surgery/dashboard` | `DashboardController@surgery` | `role:super_admin,admin,doctor,or-coordinator` | Departmental overview | **VALID** (Preserve) |
| 14 | **Surgery Requests** | `surgery.requests.index` | `/surgery/requests` | `SurgeryRequestController@index` | `role:super_admin,admin,doctor,or-coordinator` | Doctors author surgical requests | **VALID** (Preserve) |
| 15 | **OR Schedules** | `surgery.schedules.index` | `/surgery/schedules` | `SurgeryScheduleController@index` | `role:super_admin,admin,doctor,or-coordinator` | View OR schedule details | **VALID** (Preserve) |
| 16 | **Surgery Calendar** | `surgery.calendar` | `/surgery/calendar` | `SurgeryScheduleController@calendar` | `role:super_admin,admin,doctor,or-coordinator` | Full calendar view of surgical bookings | **VALID** (Preserve) |
| 17 | **Nutrition (DNMS) Overview** | `diet.dashboard` | `/diet/dashboard` | `DashboardController@diet` | `role:super_admin,admin,doctor,dietitian` | Departmental overview | **VALID** (Preserve) |
| 18 | **Diet Requests** | `diet.requests.index` | `/diet/requests` | `DietRequestController@index` | `role:super_admin,admin,doctor,dietitian` | Doctors submit clinical dietary requisitions | **VALID** (Preserve) |
| 19 | **Diet Plans** | `diet.plans.index` | `/diet/plans` | `DietPlanController@index` | `role:admin,dietitian,doctor` | Doctors view dietitian-formulated nutrition plans | **VALID** (Preserve) |
| 20 | **Patient Directory** | `patients.index` | `/patients` | `PatientController@index` | `canAccessPatients()` (Admin & Doctor) | Read-only search & view. No create/edit routes exist | **VALID** (Preserve) |
| 21 | **Clinical Reports** | `reports.index` | `/reports` | `ReportController@index` | `role:super_admin,admin,doctor` | Filtered to clinical categories (Lab, Rad, Rx, Surg, Diet) | **VALID** (Preserve) |

---

## 3. Deep Investigation: Patient Queue & Appointments Route (`/admin/appointments`)

### 3.1 Background & Concern
The route points to `/admin/appointments` under route name `admin.appointments.index`. The concern is whether this grants Doctors unauthorized administrative privileges or exposes admin-only appointment management.

### 3.2 Route & Middleware Architecture
In `routes/web.php` (lines 237–244):
```php
Route::prefix('admin')
     ->name('admin.')
     ->middleware('role:super_admin,admin,doctor')
     ->group(function () {
         Route::get('appointments', [AppointmentController::class, 'index'])->name('appointments.index');
         Route::post('appointments', [AppointmentController::class, 'store'])->name('appointments.store');
         Route::patch('appointments/{appointment}/status', [AppointmentController::class, 'updateStatus'])->name('appointments.status');
     });
```

### 3.3 Controller-Level Scoping Analysis (`AppointmentController.php`)
Inspection of `App\Http\Controllers\Admin\AppointmentController` reveals that doctor isolation is enforced on the backend:
1. **Doctor Scope Detection:**
   ```php
   private function isDoctorScope(User $user): bool
   {
       return $user->hasRole('doctor') && ! $user->hasRole('admin');
   }
   ```
2. **Consultation Queue Query Isolation:**
   ```php
   $base = Appointment::query();
   if ($doctorScope) {
       $base->where('doctor_id', $user->id); // Doctors ONLY see appointments assigned to themselves
   }
   ```
3. **Doctor Filter Restriction:**
   When `$doctorScope` is true, the doctor dropdown is locked to the logged-in doctor only (`$doctors = User::...->when($doctorScope, fn ($q) => $q->whereKey($user->id))->get()`). Doctors cannot filter or spy on other doctors' appointment queues.
4. **Appointment Scheduling Boundary:**
   ```php
   // A Doctor may only schedule against their own calendar.
   if ($this->isDoctorScope($user)) {
       $validated['doctor_id'] = $user->id;
   }
   ```
5. **Status Transition Authority:**
   ```php
   // Doctors may act only on their own appointments.
   if ($this->isDoctorScope($user)) {
       abort_unless((int) $appointment->doctor_id === (int) $user->id, 403, 'You can only update your own appointments.');
   }
   ```
   Doctors are permitted to transition appointment status (e.g., from `CheckedIn` to `In Consultation` to `Completed`), which is a necessary clinical workflow when seeing outpatient consultation queues.

### 3.4 Integration & Subsystem Boundary
- **Does it belong to an external subsystem?** In a full multi-subsystem hospital, centralized patient registration and outpatient scheduling often interface with an upstream front-desk reception module. Within DTCS, `AppointmentController` serves as the clinic consultation queue where attending physicians receive scheduled patients.
- **Is Doctor access appropriate?** **YES.** The backend strictly isolates records to the attending physician (`where('doctor_id', $user->id)`). The physician is not granted access to hospital-wide admin settings, staff accounts, or other physicians' calendars.
- **Route Name Decision:** Existing tests (such as `PanelistFiveUserTypesTest::test_doctor_can_access_clinical_dashboard_and_medisense`) expect `route('admin.appointments.index')` to be accessible to doctors. Preserving this route name maintains backwards compatibility while upholding complete clinical isolation.

---

## 4. MediSense AI Link Duplication Analysis

### 4.1 Historical Root Cause
In prior Git revisions (e.g. commit `996fafc3`), a secondary navigation section titled `"Clinical AI Assistant"` existed at the bottom of the sidebar, creating two identical links pointing to `route('medisense.index')`.
In the current sidebar, `MediSense AI` is placed under `MAIN`.

### 4.2 Alignment Decision
- Keep exactly **ONE** MediSense AI entry under `MAIN`.
- Verify that no secondary/duplicate link exists under `CLINICAL MODULES`, `PATIENTS`, or at the bottom of the sidebar.
- Ensure the link displays a clean badge:
  ```blade
  <a href="{{ route('medisense.index') }}" class="nav-link {{ request()->routeIs('medisense.*') ? 'active' : '' }}">
      <i class="bi bi-cpu-fill text-success"></i> MediSense AI <span class="sb-badge">AI</span>
  </a>
  ```

---

## 5. Clinical Modules & Departmental Boundary Verification

### 5.1 Verification of Clinical Authority vs. Departmental Administration
A Doctor has clinical requisition and result-viewing authority, but must **not** possess departmental operational privileges:
- **LIS (Laboratory):** Doctor can view requests (`lab.requests.index`) and released results (`lab.results.index`). Doctor CANNOT validate or release lab results (`lis.results.verify` and `lis.results.release` are restricted to `med-tech`).
- **RIS (Radiology):** Doctor can view requests (`radiology.requests.index`) and reports (`radiology.reports.index`). Doctor CANNOT approve or release radiology reports (restricted to `radiologist`).
- **PMS (Pharmacy):** Doctor can view prescriptions (`pharmacy.prescriptions.index`) and check formulary availability (`pharmacy.medicines.index`). Doctor CANNOT verify prescriptions or dispense medications (`pms.dispense` is restricted to `pharmacist`). Pharmacy Dispensing is correctly omitted from the Doctor sidebar.
- **SORS (Surgery):** Doctor can submit surgery requests (`surgery.requests.index`) and view the surgery calendar (`surgery.calendar`). Operating room slot management and surgical start/completion timestamps remain under `or-coordinator` oversight.
- **DNMS (Nutrition):** Doctor can submit dietary requisitions (`diet.requests.index`) and view approved diet plans (`diet.plans.index`). Formulation of clinical dietary plans is reserved for `dietitian`.

All 5 clinical module dropdowns are properly scoped and free of administrative leakage.

---

## 6. Patient Directory & Clinical Reports Verification

### 6.1 Patient Directory Read-Only Scope
- In `resources/views/partials/_sidebar.blade.php`:
  ```blade
  <div class="sb-nav-label" style="margin-top:.5rem;">PATIENTS</div>
  <a href="{{ route('patients.index') }}" class="nav-link {{ $patientsActive ? 'active' : '' }}">
      <i class="bi bi-people"></i> Patient Directory
  </a>
  ```
- **Controller Enforcement:** `PatientController` in DTCS intentionally contains NO `create`, `store`, `edit`, or `update` routes. Patient master records are managed by the upstream hospital intake system. The Doctor sidebar provides read-only search and viewing access only.

### 6.2 Clinical Reports Authorization Scope
- In `resources/views/partials/_sidebar.blade.php`:
  ```blade
  <div class="sb-nav-label" style="margin-top:.5rem;">REPORTS</div>
  <a href="{{ route('reports.index') }}" class="nav-link {{ $reportsActive ? 'active' : '' }}">
      <i class="bi bi-file-earmark-bar-graph"></i> Clinical Reports
  </a>
  ```
- **Controller & Service Enforcement:** In `ReportController@index` and `ReportService::accessibleCategories('doctor')`, doctors are restricted to clinical care categories: `laboratory`, `radiology`, `pharmacy`, `surgery`, `diet`, and `clinical`. System audit logs, administrative financial analytics, and user account reports are blocked.
- In `resources/views/reports/index.blade.php`, doctors see clinical care reports only, with a patient-centric disclaimer.

---

## 7. KEEP, MODIFY, or REMOVE Recommendations

| Navigation Element | Decision | Action & Architectural Rationale |
|---|:---:|---|
| **MAIN: Dashboard** | **KEEP** | Direct link to `doctor.dashboard`. Highlights clinical alerts, tasks, and doctor-scoped metrics. |
| **MAIN: Patient Queue & Appointments** | **KEEP** | Points to `admin.appointments.index`. Controller strictly enforces `isDoctorScope()` (doctors see only their consultation queue and schedule against their own calendar). Preserves existing route and feature tests. |
| **MAIN: MediSense AI** | **KEEP (SINGLE)** | Retain single Doctor-only CDSS entry in MAIN. Remove any duplicate or secondary AI entries across the file. |
| **Section Header: CLINICAL -> CLINICAL MODULES** | **MODIFY** | Update section label from `CLINICAL` to `CLINICAL MODULES` to match the exact DTCS architectural specification. |
| **CLINICAL MODULES: Laboratory (LIS)** | **KEEP** | Retain collapsible group: Overview (`lab.dashboard`), Lab Requests (`lab.requests.index`), Lab Results (`lab.results.index`). |
| **CLINICAL MODULES: Radiology (RIS)** | **KEEP** | Retain collapsible group: Overview (`radiology.dashboard`), Imaging Requests (`radiology.requests.index`), Reports (`radiology.reports.index`). |
| **CLINICAL MODULES: Pharmacy (PMS)** | **KEEP** | Retain collapsible group: Overview (`pharmacy.dashboard`), Prescriptions (`pharmacy.prescriptions.index`), Medicine Availability (`pharmacy.medicines.index`). Dispensing correctly excluded. |
| **CLINICAL MODULES: Surgery (SORS)** | **KEEP** | Retain collapsible group: Overview (`surgery.dashboard`), Surgery Requests (`surgery.requests.index`), OR Schedules (`surgery.schedules.index`), Surgery Calendar (`surgery.calendar`). |
| **CLINICAL MODULES: Nutrition (DNMS)** | **KEEP** | Retain collapsible group: Overview (`diet.dashboard`), Diet Requests (`diet.requests.index`), Diet Plans (`diet.plans.index`). |
| **Section Header: Patients -> PATIENTS** | **MODIFY** | Capitalize section label to `PATIENTS` for design consistency. |
| **PATIENTS: Patient Directory** | **KEEP** | Read-only directory access (`patients.index`). Zero patient CRUD. |
| **Section Header: REPORTS** | **KEEP** | Standardized uppercase label. |
| **REPORTS: Clinical Reports** | **KEEP** | Points to `reports.index`, filtered strictly to authorized clinical report categories for the doctor role. |

---

## 8. Conclusion

The audit demonstrates that the existing routes for the Doctor role possess legitimate clinical utility and backend authorization guards. The appointment route (`admin.appointments.index`) is already safeguarded by `isDoctorScope()` to prevent administrative leakage.

By aligning section labels (`CLINICAL MODULES`, `PATIENTS`), verifying single-entry MediSense AI placement, and maintaining the verified clinical module groups, the Doctor sidebar will perfectly reflect the clinical scope of the attending physician.

Implementation may proceed.
