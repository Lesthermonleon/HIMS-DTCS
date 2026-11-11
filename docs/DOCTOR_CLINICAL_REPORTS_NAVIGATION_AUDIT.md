# Doctor Sidebar & Clinical Reports Navigation Audit and Implementation Report

**Repository:** `Lesthermonleon/DTCS`  
**Target:** Doctor account sidebar and Clinical Reports navigation  
**Date:** 2026-11-08

---

## 1. Initial Audit Findings

Before modifying any files, an audit was performed across `resources/views/partials/_sidebar.blade.php`, `resources/views/reports/index.blade.php`, `routes/web.php`, `app/Http/Controllers/ReportController.php`, `app/Services/ReportService.php`, and the existing feature tests:

1. **Redundant `Surgery Reports` Entry in the Doctor Sidebar:**
   - Under `@if($role === 'doctor')` in `resources/views/partials/_sidebar.blade.php`, the `REPORTS` section rendered two links:
     - `Clinical Reports` (`route('reports.index')`)
     - `Surgery Reports` (`route('reports.surgery.activity')`)
   - Because `resources/views/reports/index.blade.php` (`Clinical Reports`) already includes the **Surgery Reports (SORS)** card linking directly to `route('reports.surgery.activity')` (`Surgical Activity & Procedure Records`), the separate `Surgery Reports` sidebar link was redundant for the Doctor role.
   - Additionally, `$reportsActive` excluded `reports.surgery.*`, so when a Doctor visited `/reports/surgery/activity`, `Clinical Reports` was not highlighted as `active`.

2. **Clinical Reports Hub Structure (`resources/views/reports/index.blade.php`):**
   - For `doctor`, `ReportService::accessibleCategories('doctor')` returns all six clinical categories (`laboratory`, `radiology`, `pharmacy`, `surgery`, `diet`, `clinical`), and `resources/views/reports/index.blade.php` renders streamlined Doctor-focused report cards for each category.

3. **Doctor Report Authorization & Record-Scoping Gaps Identified During Audit:**
   - While **Surgery (`SORS`)** and **Nutrition (`DNMS`)** report queries in `App\Services\ReportService` already scoped records via `visibleTo($user)`, the following report queries did not yet enforce Doctor-level scoping when accessed by a user with `primaryRole === 'doctor'`:
     - **Laboratory (`LIS`), Radiology (`RIS`), and Pharmacy (`PMS`) in `ReportService`:** `labActivitySummary`, `labActivityDetails`, `labTestVolume`, `radiologyActivitySummary`, `radiologyActivityDetails`, `radiologyVolumeByModality`, `radiologyPendingInterpretations`, `pharmacyActivitySummary`, `pharmacyActivityDetails`, and `dispensingDetails` returned hospital-wide records and allowed passing `?doctor_id=<other_doctor_id>` to view another Doctor's orders and restricted patients.
     - **`ReportController::clinicalPatientActivity()` (`reports.clinical.patient-activity`):** Queried all patients in the hospital who had activity from any doctor rather than scoping via `Patient::authorizedForDoctor($user)` and counting only the logged-in Doctor's clinical orders.
     - **`ReportController::clinicalDoctorActivity()` (`reports.clinical.doctor-activity`):** Listed all Doctors in the hospital and their order counts instead of scoping to the logged-in Doctor (`whereKey($user->id)`).
     - **Filter Dropdown (`$doctors`) in `ReportController`:** Passed all hospital doctors to the report filter bar even when logged in as a `doctor`.

---

## 2. Files Inspected and Modified

### Files Inspected
- `resources/views/partials/_sidebar.blade.php`
- `resources/views/reports/index.blade.php`
- `resources/views/reports/_filters.blade.php`
- `resources/views/reports/surgery/activity.blade.php`
- `resources/views/reports/clinical/{patient-activity,doctor-activity,services-summary}.blade.php`
- `routes/web.php`
- `app/Http/Controllers/ReportController.php`
- `app/Services/ReportService.php`
- `app/Models/RadiologyRequest.php`
- `tests/Feature/DoctorSidebarTest.php`
- `tests/Feature/SorsWorkflowAuthorizationTest.php`

### Files Modified
| File | Summary of Changes |
|---|---|
| `resources/views/partials/_sidebar.blade.php` | Removed the redundant `Surgery Reports` link from the Doctor's `REPORTS` section so `Clinical Reports` (`route('reports.index')`) is the single entry point; updated the Doctor's `Clinical Reports` active state to `request()->routeIs('reports.*') && !request()->routeIs('reports.diet.*')`; preserved all other role sidebars unchanged. |
| `app/Services/ReportService.php` | Added `currentDoctorId()` helper and scoped LIS (`lab*`), RIS (`radiology*`), and PMS (`pharmacy*`, `dispensingDetails`) report queries to the logged-in Doctor (`doctor_id = $doctorId`) when `primaryRole === 'doctor'`, preventing cross-doctor data exposure or `?doctor_id=` parameter overrides. |
| `app/Http/Controllers/ReportController.php` | Scoped `clinicalPatientActivity()` to `Patient::authorizedForDoctor($user)` and the logged-in Doctor's own orders; scoped `clinicalDoctorActivity()` to `whereKey($user->id)` when accessed by a Doctor; added `doctorsForFilter()` so Doctors are not presented with an "All Doctors" filter dropdown. |
| `tests/Feature/DoctorSidebarTest.php` | Verified that the Doctor sidebar renders `Clinical Reports` once under `REPORTS` with no separate `Surgery Reports` link, that all 6 Clinical Reports categories and routes work, that `Clinical Reports` highlights active on `reports.surgery.activity`, and that patient/doctor isolation is enforced across all clinical reports. |
| `tests/Feature/SorsWorkflowAuthorizationTest.php` | Updated Doctor sidebar assertions in `test_sidebar_visibility_for_doctor_or_coordinator_and_or_staff` to reflect `Clinical Reports` as the Doctor's single `REPORTS` sidebar link while preserving `Surgery Reports` under `REPORTS` / `MONITORING & REPORTS` for `or-coordinator`, `admin`, and `super_admin`. |
| `docs/SORS_IMPLEMENTATION_REPORT.md` | Updated sidebar documentation and test counts to stay synchronized. |

---

## 3. Sidebar Changes

In `resources/views/partials/_sidebar.blade.php` (`@if($role === 'doctor')`), the Doctor sidebar now uses the exact required structure:

- **MAIN**
  - `Dashboard` (`route('doctor.dashboard')`)
  - `Patient Queue & Appointments` (`route('admin.appointments.index')`)
  - `MediSense AI` (`route('medisense.index')`)
- **DIAGNOSTIC SERVICES**
  - `Laboratory (LIS)` (`Overview`, `Lab Requests`, `Lab Results`)
  - `Radiology (RIS)` (`Overview`, `Imaging Requests`, `Reports`)
- **CLINICAL MODULES**
  - `Pharmacy (PMS)` (`Overview`, `Prescriptions`, `Medicine Availability`)
  - `Surgery (SORS)` (`Overview`, `Surgery Requests`, `OR Schedules`, `Surgery Calendar`)
  - `Nutrition (DNMS)` (`Overview`, `Diet Plans`, `Nutrition Reports`)
- **PATIENTS**
  - `Patient Directory` (`route('patients.index')`)
- **REPORTS**
  - `Clinical Reports` (`route('reports.index')`)

Other role sidebars (`super_admin`, `admin`, `or-coordinator`, `or-staff`, `med-tech`, `rad-tech`, `radiologist`, `pharmacist`, `patient`) remain unchanged.

---

## 4. Clinical Reports Categories Preserved

All six Clinical Reports categories on `GET /reports` (`route('reports.index')`) are preserved for the Doctor account:

1. **Laboratory Reports (LIS)**
   - `Laboratory Activity & Results` — `route('reports.laboratory.activity')`
2. **Radiology Reports (RIS)**
   - `Radiology Activity & Imaging Records` — `route('reports.radiology.activity')`
3. **Prescription Reports (PMS)**
   - `Prescription & Medication Activity` — `route('reports.pharmacy.activity')`
4. **Surgery Reports (SORS)**
   - `Surgical Activity & Procedure Records` — `route('reports.surgery.activity')`
5. **Nutrition Reports (DNMS)**
   - `Patient Diet & Nutrition Activity` — `route('reports.diet.activity')`
6. **Clinical Summary Reports (ALL)**
   - `Patient Clinical Activity Report` — `route('reports.clinical.patient-activity')`
   - `Doctor Activity Summary` — `route('reports.clinical.doctor-activity')`
   - `Clinical Services Summary` — `route('reports.clinical.services-summary')`

---

## 5. Authorization Findings & Protections Verified

1. **Role-Level Route & Category Protection:**
   - All report routes in `routes/web.php` remain protected by `auth` and role middleware (`role:super_admin,admin,doctor,...`) plus `ReportController::authorizeCategory()` (`ReportService::canAccess()`).
   - Unauthorized roles (such as `or-staff` or `patient`) receive `403 Forbidden` when attempting direct URL access to `/reports/surgery/*` or `/reports/clinical/*`.
   - Doctors remain strictly blocked (`403 Forbidden`) from administrative audit logs (`admin.audit-logs.*`, `super-admin.audit-vault.*`), user management (`admin.users.*`, `super-admin.users.*`), security/system settings (`super-admin.settings.*`, `admin.hospital-settings.*`), and pharmacy dispensing (`pharmacy.dispensing.*`).
2. **Doctor Patient Scope & Cross-Doctor Isolation:**
   - **LIS, RIS, PMS, SORS, and DNMS Reports:** When accessed by a `doctor`, `ReportService` scopes all activity summaries, detail tables, and utilization/volume queries to the authenticated Doctor's own orders/assigned cases and ignores any external `?doctor_id=` query parameter.
   - **Patient Clinical Activity Report (`reports.clinical.patient-activity`):** Enforces `Patient::authorizedForDoctor($user)` and counts only the authenticated Doctor's orders across LIS, RIS, PMS, SORS, and DNMS so another Doctor's restricted patient records are never exposed.
   - **Doctor Activity Summary (`reports.clinical.doctor-activity`):** Scopes the query to `whereKey($user->id)` for `doctor` accounts so a Doctor only sees their own order breakdown, while `admin` and `super_admin` continue to see all Doctors.
   - **Clinical Services Summary (`reports.clinical.services-summary`):** Aggregates the Doctor-scoped summaries across all five clinical modules.

---

## 6. Automated Tests Executed and Exact Results

| Test Suite | Command | Tests | Assertions | Result |
|---|---|---:|---:|---|
| **Doctor Sidebar & SORS Authorization Suites** | `php artisan test tests/Feature/DoctorSidebarTest.php tests/Feature/SorsWorkflowAuthorizationTest.php` | 17 | 304 | **PASSED** (`17 passed, 0 failed`) |
| **Full Application Test Suite** | `php artisan test` | 239 | 1,623 | **PASSED** (`239 passed, 0 failed`) |

---

## 7. Vite Production Build Result

Command:
```bash
npm run build
```
Output:
```text
> build
> vite build

vite v8.1.5 building client environment for production...
transforming...✓ 7 modules transformed.
rendering chunks...
computing gzip size...
public/build/manifest.json              0.33 kB │ gzip:  0.16 kB
public/build/assets/app-DRfsTYCH.css   40.28 kB │ gzip:  7.64 kB
public/build/assets/app-B50xrYjX.js   118.87 kB │ gzip: 36.65 kB
✓ built in 1.79s
```

---

## 8. Manual Verification Steps (Completed vs. Pending)

- **Completed via Automated Feature & View Rendering Tests (`DoctorSidebarTest` & `SorsWorkflowAuthorizationTest`):**
  - Rendered `partials._sidebar` and full dashboard/report responses for `doctor`, `or-coordinator`, `or-staff`, `admin`, and `super_admin`.
  - Verified HTTP `200` access across all 21 Doctor sidebar destinations and all 8 Clinical Reports links (`laboratory.activity`, `radiology.activity`, `pharmacy.activity`, `surgery.activity`, `diet.activity`, `clinical.patient-activity`, `clinical.doctor-activity`, `clinical.services-summary`).
  - Verified active link highlighting (`class="nav-link active"`) on `Clinical Reports` when visiting `/reports/surgery/activity`.
  - Verified cross-doctor patient isolation and HTTP `403` enforcement on unauthorized routes.
- **Pending Manual Browser Verification (Not Performed in Interactive Browser):**
  1. Log in as a `doctor` user in a browser and visually confirm the `REPORTS` section at the bottom of the left sidebar displays only `Clinical Reports`.
  2. Click `Clinical Reports` (`/reports`) and visually inspect the six report cards (`LIS`, `RIS`, `PMS`, `SORS`, `DNMS`, `ALL`).
  3. Click `Surgical Activity & Procedure Records` (`/reports/surgery/activity`), confirm the report renders and prints cleanly, and confirm `Clinical Reports` remains highlighted in the sidebar.
  4. Click `Patient Clinical Activity Report` and `Doctor Activity Summary` under **Clinical Summary Reports** and confirm only the logged-in Doctor's authorized patients and own activity row are displayed.

---

## 9. Unresolved Issues

- None. No database schema or record modifications were required, and all 239 automated tests pass.

