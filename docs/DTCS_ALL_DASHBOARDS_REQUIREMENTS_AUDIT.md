# DTCS HIMS — Complete Role-Based Dashboard Audit & Analytics Requirements Report

**Repository:** `Lesthermonleon/DTCS`  
**Phase:** Phase 1 — Complete Dashboard Audit (Read-Only)  
**Target Scope:** All 10 Active DTCS Account Roles + DNMS Clinical Overview (`/diet/dashboard`)

---

## 1. Executive Summary & Active Role Inventory

An end-to-end audit of `routes/web.php`, `app/Models/Role.php`, `app/Models/User.php`, `app/Http/Controllers/DashboardController.php`, `app/Http/Controllers/SuperAdmin/DashboardController.php`, `app/Http/Controllers/PatientPortal/PatientPortalController.php`, `resources/views/partials/_sidebar.blade.php`, and all dashboard Blade templates identified **10 active user roles** (plus historical `dietitian` records retained for audit/data integrity):

| # | Role Name | Role Slug | Primary Dashboard Route | Controller Action | Blade View |
| :--- | :--- | :--- | :--- | :--- | :--- |
| 1 | Super Administrator | `super_admin` | `super-admin.dashboard` (`/super-admin/dashboard`) | `App\Http\Controllers\SuperAdmin\DashboardController@index` | `resources/views/super_admin/dashboard.blade.php` |
| 2 | System Administrator | `admin` | `admin.dashboard` (`/admin/dashboard`) | `App\Http\Controllers\DashboardController@admin` | `resources/views/dashboard/admin.blade.php` |
| 3 | Doctor | `doctor` | `doctor.dashboard` (`/doctor/dashboard`) | `App\Http\Controllers\DashboardController@doctor` | `resources/views/dashboard/doctor.blade.php` |
| 4 | Medical Technologist | `med-tech` | `lab.dashboard` (`/lab/dashboard`) | `App\Http\Controllers\DashboardController@lab` | `resources/views/dashboard/lab.blade.php` |
| 5 | Radiologic Technologist | `rad-tech` | `radiology.dashboard` (`/radiology/dashboard`) | `App\Http\Controllers\DashboardController@radiology` | `resources/views/dashboard/radiology.blade.php` |
| 6 | Radiologist | `radiologist` | `radiology.dashboard` (`/radiology/dashboard`) | `App\Http\Controllers\DashboardController@radiology` | `resources/views/dashboard/radiology.blade.php` |
| 7 | Pharmacist | `pharmacist` | `pharmacy.dashboard` (`/pharmacy/dashboard`) | `App\Http\Controllers\DashboardController@pharmacy` | `resources/views/dashboard/pharmacy.blade.php` |
| 8 | OR Coordinator | `or-coordinator` | `surgery.dashboard` (`/surgery/dashboard`) | `App\Http\Controllers\DashboardController@surgery` | `resources/views/dashboard/surgery.blade.php` |
| 9 | OR Staff | `or-staff` | `surgery.dashboard` (`/surgery/dashboard`) | `App\Http\Controllers\DashboardController@surgery` | `resources/views/dashboard/surgery.blade.php` |
| 10 | Patient | `patient` | `patient.dashboard` (`/patient/dashboard`) | `App\Http\Controllers\PatientPortal\PatientPortalController@dashboard` | `resources/views/patient/dashboard.blade.php` |

> **Note on DNMS (`diet.dashboard` — `/diet/dashboard`):** Doctors manage the DNMS workflow (`Doctor → DNMS → Patient Diet Plan`). A separate active Dietitian account is not required for daily DNMS operations, while historical `dietitian` accounts and records are preserved.

---

## 2. Prioritized Findings Summary (Critical / High / Medium / Low)

### Critical Findings (Security, Authorization, or Incorrect Data)
1. **[CRITICAL — Data & Authorization Leak on Module Dashboards When Accessed by Doctors]:**
   - `DashboardController::lab()`, `DashboardController::radiology()`, and `DashboardController::pharmacy()` do **not** scope queries when accessed by a `doctor` user, exposing other Doctors' patients and clinical requests on `/lab/dashboard`, `/radiology/dashboard`, and `/pharmacy/dashboard`.
2. **[CRITICAL — Unscoped Surgery Trend & Broken Zero Metrics for `or-staff` on `/surgery/dashboard`]:**
   - `DashboardController::surgery()` calls `$this->getSurgeryTrend(6)` and `$this->getSurgeryTrend(12)` without scoping to `visibleTo($user)`, leaking hospital-wide surgery request totals to `or-staff` and `doctor`.
   - Furthermore, for `or-staff`, `SurgeryRequest::visibleTo($user)` only matches requests with assigned schedules, while `surgery.blade.php` binds the status doughnut chart (`#surgStatusDonut`) and completion rate to `$reqQuery` instead of `$schedQuery`, causing inaccurate/zeroed analytics for `or-staff`.
3. **[CRITICAL — Double-Counting in Radiology `reports_pending` Metric]:**
   - In `DashboardController::radiology()` (lines 749–751), `reports_pending` adds `RadiologyRequest::where('status', 'Completed')->whereDoesntHave('report', fn($q) => $q->where('status', 'Released'))->count()` AND `RadiologyReport::whereIn('status', ['Draft', 'Approved'])->count()`. Because a `Completed` request with a `Draft` or `Approved` report satisfies both conditions, every `Draft` and `Approved` report is counted **twice**.
4. **[CRITICAL — `or-staff` Omitted from Super Admin Staff Count]:**
   - In `App\Http\Controllers\SuperAdmin\DashboardController::index()` (lines 25–27), `$staffCount` filters `['med-tech', 'rad-tech', 'radiologist', 'pharmacist', 'dietitian', 'or-coordinator']`, omitting `'or-staff'`.
5. **[CRITICAL — Broken Column References (`patient_mrn`, `surgery_type`) & Invalid Patient Appointment Type]:**
   - `resources/views/dashboard/lab.blade.php` (line 134) and `resources/views/dashboard/pharmacy.blade.php` (line 146) reference `$req->patient->patient_mrn`, which does not exist on `Patient` (`patient_no` is the actual column), causing every row to display `MRN: N/A`.
   - `resources/views/patient/dashboard.blade.php` (line 313) includes `<option value="Prescription Refill Consultation">` in the appointment modal, which fails backend validation in `PatientPortalController::bookAppointment()` (`in:In-Person Consultation,Follow-up Visit,Diagnostic Review`).
   - `resources/views/patient/dashboard.blade.php` (line 25) calls `$patient->date_of_birth->format(...)` without null-safe `?->`, risking a 500 error if `date_of_birth` is null.
   - `app/Models/Role.php` `getDashboardRouteAttribute()` fallback `match ($this->slug)` omits `'patient' => 'patient.dashboard'`.

### High Findings (Missing Required Dashboard Functionality)
1. **[HIGH — Super Admin Dashboard Lacks Charts, Analytics & Required Scorecards]:**
   - `resources/views/super_admin/dashboard.blade.php` has zero charts and lacks scorecards for `Total DTCS Accounts`, `Active Accounts`, `Total DTCS Modules`, and `Security Alerts`, as well as analytics for `User Account Growth`, `Account Distribution by Role`, `Account Status Distribution`, `Login and Authentication Activity`, `Security Events and Lockout Trends`, `DTCS Module Activity Overview`, `Recent Security Events`, `Account Management Shortcuts`, and `Module Health and Operational Status`.
2. **[HIGH — Admin Dashboard Missing Pending Clinical Requests Scorecard & Per-Module Pending vs Completed Analytics]:**
   - `resources/views/dashboard/admin.blade.php` has 4 scorecards (`Total Users`, `Active Users`, `Total Patients`, `Inactive Users`) instead of the 6 required scorecards (`Total DTCS Users`, `Active Users`, `Total Patients Available Through Authorized Integration`, `Inactive Users`, `Pending Clinical Requests`, `Operational Alerts`), and lacks `Recent Operational Activities` and `Pending versus Completed Requests` by module.
3. **[HIGH — Doctor Dashboard Missing Appointments, Consultation Waiting Queue, & Module-Specific Activity Summaries]:**
   - `DashboardController::doctor()` does not query `Appointment` records for `Upcoming Appointments` or `Patients Waiting for Consultation` (`Requested`, `Confirmed`, `CheckedIn`), nor does it display `Active Clinical Requests`, `Patient Consultation Trends`, or dedicated `Recent Laboratory Results` and `Recent Radiology Reports` tables.
4. **[HIGH — Medical Technologist Dashboard Missing Validation/Release Split & Specimen/Validation Queues]:**
   - `DashboardController::lab()` conflates `Draft` results with `pending_release` and does not separate `Results Awaiting Validation` (`Draft`) from `Results Awaiting Release` (`Validated`) or `Specimens Received` (`received_at` not null). It also lacks sections for `Pending Specimens`, `Urgent Laboratory Requests`, `Results Requiring Validation`, and `Recent Laboratory Activities`.
5. **[HIGH — Radiologic Technologist vs. Radiologist Dashboards Insufficiently Differentiated]:**
   - Both roles share `/radiology/dashboard` (`resources/views/dashboard/radiology.blade.php`) with nearly identical top scorecards and identical bottom charts.
   - `rad-tech` lacks scorecards for `Scheduled Examinations`, `Examinations In Progress`, `Reports to Prepare`, a `Modality Distribution` chart, `Today's Imaging Schedule`, `Reports Requiring Preparation`, and `Recent Imaging Activities`.
   - `radiologist` lacks scorecards for `Reports Awaiting Review` (`Completed` studies without a report), `Reports Under Review` (`Draft`), `Reports Returned for Correction`, `Reports Awaiting Approval`, `Reports Released`, a `Report Review Trends` chart, `Reports Requiring Correction`, `Recent Approved Reports`, and `Recent Radiology Activities`.
6. **[HIGH — Pharmacist Dashboard Missing Rendered Dispensing Activity & Verification/Dispensing Split]:**
   - `DashboardController::pharmacy()` queries `$recentDispensing` (`DispensingRecord`) but `resources/views/dashboard/pharmacy.blade.php` never renders it. It also lacks `Completed Prescriptions` scorecard, `Dispensing Activity` trend chart, and separate `Pending Prescription Verification` vs. `Prescriptions Awaiting Dispensing` tables.
7. **[HIGH — OR Coordinator & OR Staff Dashboards Missing Role-Specific Scorecards, Charts & Sections]:**
   - `or-coordinator` lacks `Today's Surgeries`, `Operations In Progress`, `Available Operating Rooms`, `Cases Requiring Readiness Updates` scorecards, `Operating Room Utilization` chart, `Room Availability` panel, and `Recent Scheduling Activities`.
   - `or-staff` lacks `Upcoming Procedures`, `Preparation Tasks`, `Pending Readiness Updates`, `Ready Cases` scorecards, `Readiness Status Distribution` chart, `Weekly Assigned Workload` chart, `Cases Requiring Preparation`, and `Recent Readiness Updates`.
8. **[HIGH — Patient Dashboard Lacks All Charts/Analytics, Pending Counts, Radiology Reports & Clinical Reports Section]:**
   - `PatientPortalController::dashboard()` and `resources/views/patient/dashboard.blade.php` have zero charts and do not show `Pending Laboratory Results`, `Pending Imaging Reports`, `Available Clinical Reports` scorecards, `Personal Appointment History` / `Personal Clinical Activity` charts, `Recent Radiology Reports`, or `Available Clinical Reports` (including Diet Plans).

---

## 3. Detailed Role-by-Role Audit

### 3.1 Super Admin (`super_admin`)
- **Route & Controller:** `GET /super-admin/dashboard` (`super-admin.dashboard`) → `App\Http\Controllers\SuperAdmin\DashboardController@index` → `resources/views/super_admin/dashboard.blade.php`.
- **Existing Summary Cards:** `Super Admin`, `Administrators`, `Doctors`, `Staff (6 Roles)`, `Patients`, `Locked / Inactive`.
- **Existing Charts & Analytics:** None.
- **Existing Sections:** Database Backup Status, Clinician Authorizations, Privileged Governance Audit Trail table.
- **Issues & Recommendations:**
  - Fix `$staffCount` to include `or-staff` (`7 Staff Roles`).
  - Add the 6 required summary scorecards (`Total DTCS Accounts`, `Active Accounts`, `Inactive Accounts`, `Locked Accounts`, `Total DTCS Modules`, `Security Alerts`) while preserving the 5-category User Account Hierarchy breakdown.
  - Add Chart.js analytics: `User Account Growth` (6m/12m), `Account Distribution by Role`, `Account Status Distribution` (`Active`, `Inactive`, `Locked`), `Login & Authentication Activity` (7d), `Security Events & Lockout Trends`, and `DTCS Module Activity Overview` (aggregate counts per module without exposing patient PII).
  - Add `Recent Security Events`, `Account Management Shortcuts`, and `Module Health & Operational Status`.

### 3.2 Admin (`admin`)
- **Route & Controller:** `GET /admin/dashboard` (`admin.dashboard`) → `App\Http\Controllers\DashboardController@admin` → `resources/views/dashboard/admin.blade.php`.
- **Existing Summary Cards:** `Total Users`, `Active Users`, `Total Patients`, `Inactive Users`.
- **Existing Charts & Analytics:** `Service Request Trend` (6m/12m), `Requests by Module`, `Global Request Status`, `User Distribution by Role`, `System Activity Log Trend` (7d).
- **Existing Sections:** Critical System & Security Alerts, Administrative Operational Tools, Clinical Module Operational Overview, User Presence & Activity, Recent Patients Overview (Read-Only).
- **Issues & Recommendations:**
  - Expand summary scorecards to 6 cards by adding `Pending Clinical Requests` and `Operational Alerts` (keeping `Total Users`, `Active Users`, `Total Patients`, `Inactive Users` and never using the label `"Pending Tasks"` per `AdminDashboardTest`).
  - Add `Pending versus Completed Requests` comparison dataset/chart across the 5 clinical modules (`LIS`, `RIS`, `PMS`, `SORS`, `DNMS`).
  - Add `Recent Operational Activities` section from `ActivityLog` (excluding Super-Admin-only vault internals as appropriate).

### 3.3 Doctor (`doctor`)
- **Route & Controller:** `GET /doctor/dashboard` (`doctor.dashboard`) → `App\Http\Controllers\DashboardController@doctor` → `resources/views/dashboard/doctor.blade.php`.
- **Existing Summary Cards:** `My Patients`, `Pending Tasks`, `Critical Alerts`, `Lab Results`, `Imaging Reports`, `Surgeries`.
- **Existing Charts & Analytics:** `Clinical Request Trend` (6m/12m), `Requests by Service` bar chart, `Clinical Orders Breakdown` doughnut chart.
- **Existing Sections:** Critical Clinical Alerts, Pending Clinical Tasks, Quick Clinical Actions, Clinical Module Overview, MediSense AI panel, Recent Patient Access.
- **Issues & Recommendations:**
  - Add Doctor-scoped `Appointment` queries (`Upcoming Appointments`, `Patients Waiting for Consultation`, and `Patient Consultation Trends`).
  - Ensure summary cards and metrics include `My Patients`, `Pending Tasks`, `Upcoming Appointments`, `Pending Lab Results`, `Pending Imaging Reports`, and `Active Clinical Requests` (while preserving `critical_alerts`, `urgent_alerts`, `lab_awaiting`, `rad_awaiting`, `upcoming_surgeries` in `$stats` for backward compatibility with `DoctorDashboardTest`).
  - Add module activity summary (`Prescription Activity`, `Surgery Request Activity`, `Nutrition Plan Activity` including `DietPlan` counts) and dedicated `Upcoming Appointments` / `Patients Waiting for Consultation`, `Recent Laboratory Results`, and `Recent Radiology Reports` sections.
  - Add `Appointments` quick action alongside LIS, RIS, PMS, SORS, DNMS, and MediSense AI.

### 3.4 Medical Technologist (`med-tech`)
- **Route & Controller:** `GET /lab/dashboard` (`lab.dashboard`) → `App\Http\Controllers\DashboardController@lab` → `resources/views/dashboard/lab.blade.php`.
- **Existing Summary Cards:** `Total Requests`, `Pending Queue`, `Tests In Progress`, `STAT Priority`.
- **Existing Charts & Analytics:** `Workload Distribution` doughnut, `Lab Request Trend` (6m/12m), `Request Priority` doughnut.
- **Issues & Recommendations:**
  - Scope queries when accessed by a `doctor` so only that Doctor's lab requests/results are shown.
  - Provide the 6 required scorecards: `Pending Lab Requests`, `Specimens Received`, `Tests In Progress`, `Results Awaiting Validation` (`Draft`), `Results Awaiting Release` (`Validated`), and `Completed Tests`.
  - Fix `$req->patient->patient_mrn` → `$req->patient->patient_no`.
  - Add `Daily/Weekly Test Trends` (7-day received vs. completed trend), `Pending Specimens`, `Urgent Laboratory Requests`, `Results Requiring Validation`, and `Recent Laboratory Activities` (`ActivityLog` where `module = 'Laboratory'`).

### 3.5 Radiologic Technologist (`rad-tech`)
- **Route & Controller:** `GET /radiology/dashboard` (`radiology.dashboard`) → `App\Http\Controllers\DashboardController@radiology` → `resources/views/dashboard/radiology.blade.php`.
- **Issues & Recommendations:**
  - When accessed by `rad-tech`, display the 5 required scorecards: `Pending Imaging Requests`, `Scheduled Examinations`, `Examinations In Progress`, `Completed Examinations`, and `Reports to Prepare` (`Completed` requests without a report).
  - Add `Modality Distribution` chart (`X-Ray`, `CT`, `MRI`, `Ultrasound`, etc.), `Examination Processing Trends` (7-day scheduled vs. completed), `Today's Imaging Schedule`, `Pending Examinations`, `Reports Requiring Preparation`, and `Recent Imaging Activities`.
  - Ensure `rad-tech` never sees report approval or release buttons.

### 3.6 Radiologist (`radiologist`)
- **Route & Controller:** `GET /radiology/dashboard` (`radiology.dashboard`) → `App\Http\Controllers\DashboardController@radiology` → `resources/views/dashboard/radiology.blade.php`.
- **Issues & Recommendations:**
  - Fix the double-counted `reports_pending` calculation.
  - When accessed by `radiologist`, display the 5 required scorecards: `Reports Awaiting Review` (completed studies without a report + non-returned Draft reports), `Reports Under Review` (`Draft` reports), `Reports Returned for Correction` (`Draft` reports with `[Returned for Correction` in `recommendations`), `Reports Awaiting Approval` (`Draft` reports ready for approval), and `Reports Released` (`Released` reports, plus `Approved` count).
  - Add `Report Review Trends`, `Report Status Distribution` (`Draft`, `Returned`, `Approved`, `Released`), `Pending Report Reviews`, `Reports Requiring Correction`, `Recent Approved Reports`, and `Recent Radiology Activities`.

### 3.7 Pharmacist (`pharmacist`)
- **Route & Controller:** `GET /pharmacy/dashboard` (`pharmacy.dashboard`) → `App\Http\Controllers\DashboardController@pharmacy` → `resources/views/dashboard/pharmacy.blade.php`.
- **Issues & Recommendations:**
  - Scope queries when accessed by a `doctor`.
  - Fix `$rx->patient->patient_mrn` → `$rx->patient->patient_no`.
  - Display the 5 required scorecards: `Pending Prescriptions`, `Prescriptions for Verification`, `Pending Dispensing` (`Verified` + `Partially Dispensed`), `Dispensed Today`, and `Completed Prescriptions` (`Dispensed`), while preserving read-only `MedicationStockProviderInterface` stock cards.
  - Add `Medication Dispensing Trends` (7-day dispensing activity chart) alongside `Prescription Volume Trends` (6m/12m) and `Prescription Status Distribution` (`Pending`, `Verified`, `Partially Dispensed`, `Dispensed`).
  - Render separate `Pending Prescription Verification`, `Prescriptions Awaiting Dispensing`, and `Recent Dispensing Activities` sections.

### 3.8 OR Coordinator (`or-coordinator`)
- **Route & Controller:** `GET /surgery/dashboard` (`surgery.dashboard`) → `App\Http\Controllers\DashboardController@surgery` → `resources/views/dashboard/surgery.blade.php`.
- **Issues & Recommendations:**
  - Display the 6 required scorecards: `Pending Surgery Requests`, `Scheduled Operations`, `Today's Surgeries`, `Operations In Progress`, `Available Operating Rooms`, and `Cases Requiring Readiness Updates`.
  - Add `Operating Room Utilization` chart (cases per Operating Room), `Surgery Volume Trends` (scoped), `Case Status Distribution`, `OR Readiness Overview`, `Room Availability` table/cards (`OperatingRoom`), and `Recent Scheduling Activities`.

### 3.9 OR Staff (`or-staff`)
- **Route & Controller:** `GET /surgery/dashboard` (`surgery.dashboard`) → `App\Http\Controllers\DashboardController@surgery` → `resources/views/dashboard/surgery.blade.php`.
- **Issues & Recommendations:**
  - Scope all surgery statistics, charts, and trends for `or-staff` strictly to `SurgerySchedule::visibleTo($user)` (assigned surgical team cases only).
  - Display the 5 required scorecards: `Assigned Cases`, `Upcoming Procedures`, `Preparation Tasks` (`Pending Prep` + `Preparing`), `Pending Readiness Updates` (`Pending Prep`), and `Ready Cases` (`Ready`).
  - Add `Assigned Case Distribution` (by status), `Readiness Status Distribution` (`Pending Prep`, `Preparing`, `Ready`), `Weekly Assigned Workload` chart, `My Assigned Cases`, `Upcoming Procedures`, `Cases Requiring Preparation`, and `Recent Readiness Updates`.
  - Do not expose unrestricted scheduling controls or unassigned cases.

### 3.10 Patient (`patient`)
- **Route & Controller:** `GET /patient/dashboard` (`patient.dashboard`) → `App\Http\Controllers\PatientPortal\PatientPortalController@dashboard` → `resources/views/patient/dashboard.blade.php`.
- **Issues & Recommendations:**
  - Fix null-safe `$patient->date_of_birth?->format('M d, Y')`, fix N+1 on `requestItem.labTest`, and remove the invalid `"Prescription Refill Consultation"` option from the booking modal.
  - Display the 5 required scorecards: `Upcoming Appointments`, `Pending Laboratory Results`, `Pending Imaging Reports`, `Active Prescriptions`, and `Available Clinical Reports` (Released Lab Results + Released Radiology Reports + Active/Completed Diet Plans).
  - Add Chart.js analytics: `Personal Appointment History` / `Personal Clinical Activity` (6-month personal activity trend) and `Available Result History` (breakdown of Released Lab Results, Released Radiology Reports, Active Prescriptions, and Diet Plans).
  - Render dedicated sections for `Upcoming Appointments`, `Recent Laboratory Results`, `Recent Radiology Reports`, `Active Prescriptions`, and `Available Clinical Reports` (including Diet Plans).

