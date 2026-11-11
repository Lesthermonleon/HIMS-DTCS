# DTCS HIMS — Doctor Dashboard Clinical Audit & Architectural Assessment

**Repository:** `Lesthermonleon/DTCS`  
**System:** DTCS Hospital Information Management System (Diagnostics, Treatment & Clinical Services)  
**Target:** Doctor Clinical Dashboard, Backend Controller, Authorization Boundaries & Workflows  
**Date:** November 2026  
**Document Status:** Complete Pre-Implementation Clinical Audit

---

## 1. Executive Summary & Scope

The DTCS (Diagnostics, Treatment & Clinical Services) subsystem serves five operational clinical modules:
1. **LIS** — Laboratory Information System
2. **RIS** — Radiology & Imaging System
3. **PMS** — Pharmacy Management System
4. **SORS** — Surgery & Operating Room Scheduler
5. **DNMS** — Diet & Nutrition Management System
6. **MediSense AI** — Doctor-only Clinical Decision Support System (CDSS)

The **Doctor / Physician** is a licensed clinical practitioner responsible for direct patient assessment, clinical decision-making, authorizing diagnostic investigations, prescribing medications, requesting surgical procedures, ordering dietary plans, and consulting MediSense AI for evidence-based decision support.

This audit evaluates the existing Doctor Dashboard (`resources/views/dashboard/doctor.blade.php`, `App\Http\Controllers\DashboardController::doctor()`, and `getDoctorDashboardData()`), inspecting data sources, Eloquent queries, route protections, role-based access control (RBAC), patient confidentiality boundaries, and clinical status lifecycles.

---

## 2. Existing Dashboard Architecture & Components

### 2.1 Route & Middleware Specification
- **Route:** `GET /doctor/dashboard`
- **Route Name:** `doctor.dashboard`
- **Controller Action:** `App\Http\Controllers\DashboardController@doctor`
- **Middleware:** `['auth', 'otp.verified', 'single.session', 'role:super_admin,admin,doctor']`
- **Blade View:** `resources/views/dashboard/doctor.blade.php` extending `layouts.app`

### 2.2 Controller Architecture
The doctor dashboard controller logic resides in `DashboardController::doctor()` and its private helper `DashboardController::getDoctorDashboardData(int $doctorId)`. It accepts the authenticated user's ID (`$doctorId = Auth::id()`), executes queries across clinical models, and returns an associative array to the Blade view.

---

## 3. Component-by-Component Data Source & Integrity Analysis

| Component | UI Section | Route / Action | Underlying Model(s) | Current Implementation | Data Source Integrity |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **My Patients Card** | Overview Metrics | `patients.index` | `App\Models\Patient` | Counts patients with doctor's clinical requests; **Falls back to `Patient::count()` if 0** | **CRITICAL DEFECT** (Data Leakage) |
| **Pending Tasks Card** | Overview Metrics | `lab.requests.index` (Pending) | `LabRequest`, `RadiologyRequest`, `Prescription`, `SurgeryRequest`, `DietRequest` | Aggregates pending requests where `doctor_id = $doctorId`; **Falls back to top 3 global requests if 0** | **CRITICAL DEFECT** (Data Leakage) |
| **Critical Alerts Card** | Overview Metrics | `lab.results.index` | `LabResult`, `RadiologyReport` | `labResultsReviewCount + radReportsReviewCount`; **Every released result is treated as Critical** | **CLINICAL LOGIC DEFECT** |
| **Lab Results Card** | Overview Metrics | `lab.results.index` | `LabResult` | Counts released lab results for doctor; **Falls back to 5 global hospital results if 0** | **CRITICAL DEFECT** (Data Leakage) |
| **Imaging Reports Card** | Overview Metrics | `radiology.reports.index` | `RadiologyReport` | Counts approved radiology reports for doctor; **Falls back to 5 global reports if 0** | **CRITICAL DEFECT** (Data Leakage) |
| **Surgeries Card** | Overview Metrics | `surgery.calendar` | `SurgeryRequest`, `SurgerySchedule` | Counts doctor surgery requests in `['Scheduled', 'Approved', 'Pending']`; **Falls back to all `SurgerySchedule` if 0** | **CRITICAL DEFECT** (Data Leakage) |
| **Critical Clinical Alerts** | Detail Panel (Left) | `lab.results.show`, `radiology.reports.show` | `LabResult`, `RadiologyReport` | Displays top 3 released lab results & top 3 radiology reports. No severity distinction (Critical vs Urgent vs Routine). | **MODIFY** (Requires clinical triage) |
| **Pending Clinical Tasks** | Detail Panel (Right) | Shows pending orders across LIS, RIS, PMS | `LabRequest`, `RadiologyRequest`, `Prescription` | Shows pending requests; uses global records if doctor has 0 pending tasks | **MODIFY** (Enforce doctor isolation) |
| **Clinical Request Trend** | Analytics Chart | Client-side Chart.js (6m & 12m) | All 5 clinical request models | Aggregates doctor's requests by month; **Falls back to system-wide counts if month total is 0** | **CRITICAL DEFECT** (Fabricated Trend) |
| **Requests by Service** | Analytics Bar Chart | Client-side Chart.js | All 5 clinical request models | `LabRequest::where('doctor_id', $doctorId)->count() ?: LabRequest::count()` | **CRITICAL DEFECT** (Fabricated Counts) |
| **Quick Actions** | Clinical Tools Grid | 5 legitimate creation routes | `LabRequest`, `RadiologyRequest`, `Prescription`, `SurgeryRequest`, `DietRequest` | Pre-existing creation workflows (`*.create`); doctor identity recorded on submit | **KEEP & REINFORCE** |
| **Clinical Orders Breakdown** | Doughnut Chart | Client-side Chart.js | All 5 clinical request models | Renders pending order counts across 5 modules | **MODIFY** (Zero fallback) |
| **Clinical Module Overview** | Departmental Grid | Department dashboards & `medisense.index` | Departmental dashboards | Displays doctor's recorded orders per module + MediSense AI tile | **MODIFY** (Zero fallback, clear RBAC boundaries) |
| **MediSense AI Tile** | Decision Support Card | `medisense.index` | `Patient`, `EvidenceMDProvider`, `MockAIProvider` | Fully integrated CDSS (Symptom Assessment, Diagnostic Assistance, Treatment Recommendation, Clinical Service Assistance) | **PRESERVE** |
| **Recent Patient Access** | Patient Table | `patients.show` | `App\Models\Patient` | `Patient::latest('updated_at')->take(5)->get()` retrieves arbitrary hospital patients | **CRITICAL PRIVACY DEFECT** |

---

## 4. Detailed Clinical & Architectural Vulnerabilities Identified

### 4.1 Vulnerability 1: Global Fallbacks Cause Severe Patient Privacy Violations
In `DashboardController::getDoctorDashboardData()`, several ternary and conditional fallback queries were introduced:
```php
// Existing line 472-475:
if ($myPatientsCount === 0) {
    $myPatientsCount = \App\Models\Patient::count(); // LEAKS TOTAL HOSPITAL PATIENT COUNT
}

// Existing line 484-491:
if ($pendingLabRequests->isEmpty() && ...) {
    $pendingLabRequests = LabRequest::with('patient')->where('status', 'Pending')->latest()->take(3)->get(); // LEAKS OTHER DOCTORS' PATIENTS
}

// Existing line 501-506:
if ($releasedLabResults->isEmpty()) {
    $releasedLabResults = \App\Models\LabResult::latest()->take(5)->get(); // LEAKS OTHER DOCTORS' LAB RESULTS
}

// Existing line 541-545:
'my_lab_requests' => LabRequest::where('doctor_id', $doctorId)->count() ?: LabRequest::count() // LEAKS HOSPITAL TOTALS
```
**Impact:** A newly registered physician or a physician with zero active tasks is presented with unauthorized patient names, clinical findings, pending orders, and laboratory results belonging to other doctors' patients. This violates medical data privacy standards (HIPAA / Philippine Data Privacy Act of 2012 / DOH clinical privacy mandates).  
**Remediation:** Remove all global fallbacks unconditionally. If a doctor has 0 patients or 0 pending tasks, the query must return 0, and the UI must display a clean, reassuring empty state ("No pending tasks requiring your attention").

### 4.2 Vulnerability 2: Conflation of "Released Diagnostic Result" with "Critical Alert"
In `DashboardController.php` line 522:
```php
$criticalAlertsCount = $labResultsReviewCount + $radReportsReviewCount;
```
Every single released lab result and approved radiology report was counted and presented under a red banner labeled "Critical Clinical Alerts".  
**Clinical Safety Violation:** In clinical practice, an abnormal or released result is **not** automatically a critical result. Treating a routine normal CBC or standard chest X-ray as a "Critical Alert" causes severe alert fatigue and endangers patients when genuinely critical findings (e.g., severe thrombocytopenia < 50k, STAT troponin elevation, tension pneumothorax) are lost among routine releases.  
**Remediation:** Distinguish clinical severity based on verified database fields:
1. **Critical:** Orders with `priority = 'STAT'` or results where remarks/impression explicitly flag critical values (e.g. "Critical", "critically low", "severe").
2. **Urgent:** Orders with `priority = 'Urgent'`.
3. **Informational:** Routine released results awaiting review.
Preserve the existing medical data without fabricating artificial clinical thresholds.

### 4.3 Vulnerability 3: Recent Patient Access Displays Unassigned Hospital Patients
In `DashboardController.php` line 555:
```php
$recentPatients = \App\Models\Patient::latest('updated_at')->take(5)->get();
```
This queries the general patient directory for the 5 most recently updated patients across the entire hospital, regardless of whether the logged-in doctor has any clinical relationship with them.  
**Remediation:** Scope `$recentPatients` strictly to patients with whom the logged-in doctor has an active clinical association:
- Patient has a `LabRequest` where `doctor_id = $doctorId`
- Patient has a `RadiologyRequest` where `doctor_id = $doctorId`
- Patient has a `Prescription` where `doctor_id = $doctorId`
- Patient has a `SurgeryRequest` where `doctor_id = $doctorId`
- Patient has a `DietRequest` where `doctor_id = $doctorId`
- Patient has an `Appointment` where `doctor_id = $doctorId`

### 4.4 Vulnerability 4: Monthly Request Trend and Service Breakdown Contamination
In `getDoctorRequestTrend()` (lines 264-271) and line 563:
When a doctor has no requests in a given month or overall service, the queries fall back to system-wide counts:
```php
if ($total === 0) {
    $total = LabRequest::whereBetween(...)->count() + ...;
}
```
**Impact:** Doctors are misled into believing hospital-wide clinical activity is their personal activity, producing incorrect clinical audit records.  
**Remediation:** Remove the fallback. If a doctor made 0 requests in a month, the trend correctly records 0.

---

## 5. Patient Data Access & Authorization Logic

### 5.1 Patient Master Record Read-Only Boundary
- The Patient Directory (`PatientController`) originates from the hospital's central patient registration subsystem.
- In DTCS, `PatientController` provides `index`, `show`, and `destroy` (soft delete archive). `create`, `store`, `edit`, and `update` are intentionally absent.
- The Doctor Dashboard must **not** introduce patient CRUD.
- Links to patient profiles must route to `route('patients.show', $patient)`.

### 5.2 Patient Relationship Logic for Doctor Scoping
A patient is authorized for the logged-in doctor if:
$$\text{Patient} \in \bigcup_{M \in \{\text{LabRequest, RadiologyRequest, Prescription, SurgeryRequest, DietRequest, Appointment}\}} \{ p \mid M.\text{patient\_id} = p.\text{id} \land M.\text{doctor\_id} = \$doctorId \}$$

This query is performed via Eloquent `where(function($q) { $q->whereHas(...)... })` ensuring exact database-level isolation.

---

## 6. Clinical Request and Result Status Definitions

### 6.1 Laboratory Information System (LIS)
- **Request Priorities:** `STAT`, `Urgent`, `Routine`
- **Request Statuses:**
  - `Pending`: Request created by Doctor, awaiting specimen intake in lab.
  - `In Progress`: Specimen received by Med-Tech, analysis underway.
  - `Completed`: All test items analyzed and results validated/released.
  - `Cancelled`: Request terminated prior to analysis.
- **Result Statuses:**
  - `Pending`: Specimen received, awaiting analyzer run.
  - `Encoded`: Med-Tech entered test values.
  - `Validated`: Verified by senior medical technologist.
  - `Released`: Formally released to attending physician and medical record.

### 6.2 Radiology Information System (RIS)
- **Request Priorities:** `STAT`, `Urgent`, `Routine`
- **Request Statuses:**
  - `Pending`: Imaging ordered by Doctor.
  - `Scheduled`: Rad-Tech scheduled imaging examination.
  - `In Progress`: Patient undergoing imaging examination.
  - `Completed`: Imaging acquired and uploaded to PACS/RIS.
  - `Cancelled`: Examination aborted.
- **Report Statuses:**
  - `Draft`: Radiologist diagnostic draft.
  - `Approved`: Radiologist reviewed and signed findings & impression.
  - `Released`: Formally distributed to clinical chart and Doctor.

### 6.3 Pharmacy Management System (PMS)
- **Prescription Statuses:**
  - `Pending`: Prescribed by Doctor, awaiting pharmacy verification.
  - `Verified`: Pharmacist validated dosage, frequency, and contraindications.
  - `Partially Dispensed`: Subset of prescribed medication dispensed.
  - `Dispensed`: Fully dispensed to patient/ward.
  - `Cancelled`: Prescription revoked.
- *Clinical Safety Rule:* Prescriptions in `Dispensed` status are fulfilled orders and must **not** be counted as pending clinical tasks.

### 6.4 Surgery & Operating Room Scheduler (SORS)
- **Request Statuses:**
  - `Pending`: Surgical procedure requested by Doctor.
  - `Scheduled`: OR coordinator assigned operating room slot and surgical team.
  - `In Progress`: Patient in operating theater.
  - `Completed`: Surgery concluded, post-op recovery started.
  - `Cancelled`: Procedure cancelled.

### 6.5 Diet & Nutrition Management System (DNMS)
- **Request Statuses:**
  - `Pending`: Doctor submitted dietary requisition (e.g. NPO, Diabetic, Low Sodium).
  - `Active`: Dietitian formulated and approved clinical nutrition plan.
  - `Completed`: Dietary regimen concluded.
  - `Cancelled`: Dietary order superseded or cancelled.

---

## 7. MediSense AI Integration & Clinical Boundaries

### 7.1 Architecture Verification
- **Provider Abstraction:** `App\Contracts\AIProviderInterface`
- **Providers:**
  - `App\AI\MockAIProvider`: Safe offline testing provider returning standardized, evidence-grounded response structures.
  - `App\AI\EvidenceMDProvider`: Production API provider integrating with EvidenceMD.
- **Context Builder:** `App\Services\MediSense\ClinicalContextBuilder` aggregates patient history, LIS results, RIS reports, active prescriptions, surgical orders, and diet plans.
- **Service Orchestrator:** `App\Services\MediSense\MedisenseService` resolves the active provider and executes analysis tasks.
- **Controller:** `App\Http\Controllers\MedisenseController` guarded by `role:doctor`.

### 7.2 Clinical Safety & Regulatory Guardrails
1. **Doctor-Only Access:** MediSense AI is strictly restricted to authenticated physicians. Administrative roles (`admin`, `super_admin`) and technical staff (`med-tech`, `rad-tech`, `pharmacist`) cannot access MediSense endpoints (HTTP 403 Forbidden).
2. **Non-Autonomous Decision Support:** MediSense provides decision support only. It does not auto-prescribe, does not execute automatic orders, and does not alter clinical charts autonomously.
3. **Explicit Physician Responsibility:** Prominent disclaimers remind the attending physician that clinical decision-making remains solely their professional responsibility.
4. **Data Sanitization:** `ClinicalContextBuilder` and `EvidenceMDProvider::sanitizeFreeText()` scrub patient names, MRNs, bed numbers, and direct identifiers before transmitting data to external AI models.

---

## 8. Role and Permission Boundaries

| Capability | Doctor Authority | Admin Authority | Super Admin Authority | Staff (Tech/Pharm) Authority |
| :--- | :---: | :---: | :---: | :---: |
| Access Doctor Dashboard | **YES** | View Only | View Only | NO (403) |
| Access MediSense AI | **YES** | NO (403) | NO (403) | NO (403) |
| Create Clinical Requests (LIS, RIS, PMS, SORS, DNMS) | **YES** | NO (Clinical Only) | NO | NO |
| Release Laboratory Results | NO (Med-Tech only) | NO | NO | **YES** (Med-Tech) |
| Approve Radiology Reports | NO (Radiologist only)| NO | NO | **YES** (Radiologist) |
| Dispense Medications | NO (Pharmacist only) | NO | NO | **YES** (Pharmacist) |
| Schedule Operating Room | NO (OR Coord only) | NO | NO | **YES** (OR Coordinator) |
| Formulate Diet Plans | NO (Dietitian only) | NO | NO | **YES** (Dietitian) |
| Patient Master Record Edit/Create | NO (External) | NO (External) | NO (External) | NO (External) |
| User & Account Management | NO (403) | Audit / Limited Staff | Full DTCS User Management | NO (403) |

---

## 9. KEEP, MODIFY, REMOVE, or ADD Recommendations

| Component / Feature | Recommendation | Rationale & Clinical Specification |
| :--- | :---: | :--- |
| **Overview Cards (6 Cards)** | **MODIFY** | Retain all 6 cards (My Patients, Pending Tasks, Critical Alerts, Lab Results, Imaging Reports, Surgeries). Eliminate all `?: count()` and fallback queries. Scope counts strictly to the logged-in doctor. Display `0` and neutral badges when no records exist. |
| **Critical Clinical Alerts Section** | **MODIFY** | Classify alerts into **Critical** (`STAT` priority or critical text in remarks), **Urgent** (`Urgent` priority), and **Informational** (Routine releases). Provide direct links to `lab.results.show` and `radiology.reports.show`. Scope exclusively to doctor's patients. |
| **Pending Clinical Tasks Section** | **MODIFY** | Display pending orders created by the logged-in doctor across LIS, RIS, PMS, SORS, DNMS. Sort by priority (`STAT` first, then `Urgent`, then `Routine`). Remove fallback to other doctors' tasks. |
| **Clinical Request Trend Chart** | **MODIFY** | Maintain 6-month and 12-month periods. Remove fallback that injects hospital-wide request totals when the doctor has 0 requests. Handle empty series gracefully. |
| **Requests by Service Chart** | **MODIFY** | Maintain 5 service bars (Lab, Radiology, Pharmacy, Surgery, Diet). Remove system-wide fallback counts. Show actual doctor order volume. |
| **Quick Actions (5 Actions)** | **KEEP** | Preserve the 5 direct clinical creation routes: New Lab Request (`lab.requests.create`), New Imaging Order (`radiology.requests.create`), New Prescription (`pharmacy.prescriptions.create`), New Surgery Request (`surgery.requests.create`), New Diet Request (`diet.requests.create`). Ensure doctor ID is retained. |
| **Clinical Orders Breakdown** | **MODIFY** | Ensure doughnut chart displays the logged-in doctor's pending workload across the 5 modules without fallback distortion. |
| **Clinical Module Overview** | **MODIFY** | Retain quick links to clinical module pages and MediSense AI. Display doctor-scoped request counts. Preserve strict separation between clinical request authority and departmental operational authority. |
| **MediSense AI Card & Workflow** | **KEEP & REINFORCE** | Retain doctor-only CDSS integration with direct link to `medisense.index`. Emphasize its 4 diagnostic and decision-support modalities and physician responsibility guardrail. |
| **Recent Patient Access Section** | **MODIFY** | Replace hospital-wide query with doctor-scoped query (`whereHas('labRequests', ...)->orWhereHas(...)`). Display authorized patient records only. Preserve read-only patient profile links. |
| **All Global Fallback Code in Controller** | **REMOVE** | Completely delete all fallback branches (`$myPatientsCount === 0 ? count()`, `$pendingLabRequests->isEmpty() ? take(3)`, etc.) to prevent medical privacy breaches. |
| **Severity Badging & Triage Tags** | **ADD** | Add standardized clinical urgency badges (`STAT` / `Critical` in red, `Urgent` in amber/yellow, `Routine` / `Normal` in teal/slate). |

---

## 10. Conclusion & Approval to Proceed

The audit reveals that while the Doctor Dashboard architecture has strong clinical foundations and authentic database models, it suffered from critical data leaks where doctors with low or zero volume were presented with hospital-wide data via fallback logic. Furthermore, all released diagnostic results were conflated as "Critical Alerts".

By eliminating fallback queries, enforcing strict doctor-patient relationship scoping, implementing proper clinical alert triage, and refining the presentation with the DTCS teal/blue/slate palette, the Doctor Dashboard will achieve clinical safety, regulatory privacy compliance, and physician-oriented operational efficiency.

Implementation may proceed following these specifications.
