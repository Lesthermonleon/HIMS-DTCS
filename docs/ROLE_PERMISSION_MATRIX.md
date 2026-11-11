# DTCS HIMS — Master Role-Permission Access Control Matrix

**Document Version:** 2.0.0  
**Date:** October 18, 2026  
**System Architecture:** Custom Multi-Role Relational RBAC (`roles`, `permissions`, `role_user`, `permission_role`)  
**Scope:** Panelist-Mandated 5 Main User Types (Super Admin, Admin, Doctor, Staff, Patient)  
**Status:** DRAFT / PENDING ARCHITECTURAL REVIEW (Implementation Paused)

---

## 1. Architectural Principles of Access Control

The DTCS Hospital Information Management System implements strict, defense-in-depth authorization governed by ten non-negotiable architectural principles:

1. **Authentication Identifies the User:** Credentials and Multi-Factor Email OTP confirm digital identity.
2. **Roles Define Broad Responsibilities:** Five primary user classifications (`Super Admin`, `Admin`, `Doctor`, `Staff`, `Patient`).
3. **Permissions Define Granular Actions:** Specific operations (e.g. `clinical.prescribe`, `diagnostics.release_results`) govern high-risk workflows.
4. **Department Assignments Constrain Staff Operations:** Staff members access only the operational workflows authorized for their department.
5. **Patient Record Ownership Constrains Portal Access:** Zero-trust IDOR prevention. Every patient portal action validates `$record->patient_id === Auth::user()->patient_id` on the server.
6. **Professional Qualifications Constrain Clinical Actions:** System administrators cannot diagnose, prescribe, or release laboratory/radiology findings merely by virtue of administrative rank.
7. **Clinical Separation of Super Admin:** Super Admin status grants zero automatic access to patient medical records or clinical authoring.
8. **Server-Side Enforcement is Mandatory:** Blade `@can` and `@if` directives control visual menus only. Server-side middleware (`RoleMiddleware`, `EnsurePermission`) and Controller Policies enforce actual security boundaries.
9. **Sensitive Operations Generate Cryptographic Audit Logs:** All role changes, privileged updates, account unlocks, and diagnostic releases are logged in the immutable audit vault.
10. **Anti-Escalation Controls Prevent Privilege Takeover:** Admins cannot assign `super_admin` or `admin` roles, cannot unlock or archive protected accounts, and cannot modify security parameters outside their operational tier.

---

## 2. The Five Main User Types

| User Type | Primary Role Slug | Key Responsibilities | Absolute Restrictions |
|---|---|---|---|
| **1. Super Admin** | `super_admin` | Master governance, system configuration, database backup oversight, master catalog management, clinical code staging, audit vault oversight, privileged Admin account management, Doctor provisioning authorization. | **No clinical authority:** Cannot diagnose, prescribe medication, enter/release diagnostic results, or view private medical records without explicit separate clinical credentials. |
| **2. Admin** | `admin` | Hospital operations, staff account administration, doctor onboarding requests, operating hours, operational queues, financial rules, department monitoring, operational patient portal account linking. | **No security policy takeover:** Cannot assign `super_admin`, cannot modify peer `admin` accounts, cannot alter signed clinical records, cannot release diagnostic results. |
| **3. Doctor** | `doctor` | Clinical consultations, symptom assessment, diagnostic request orders (LIS/RIS), medical diagnosis, treatment planning, medication prescriptions, Virtual MediSense AI CDSS. | **No global system settings:** Cannot modify security policies, cannot manage system-wide user roles, cannot dispense pharmacy medications directly. |
| **4. Staff** | Department Roles: `med-tech`, `rad-tech`, `radiologist`, `pharmacist`, `dietitian`, `or-coordinator` *(Note: Receptionist, Nurse, Cashier pending approval)* | Department-specific operational execution: specimen processing & result release (LIS), imaging acquisition & reports (RIS), prescription verification & dispensing (PMS), OR scheduling (SORS), diet plans (DNMS). | **Department isolation:** Staff cannot execute workflows outside their assigned department. Med-Techs cannot dispense drugs; Pharmacists cannot release radiology reports; Rad-Techs cannot sign diagnosis. |
| **5. Patient** | `patient` | Personal self-service healthcare portal: view own released laboratory/radiology reports, active prescriptions, treatment summaries, upcoming appointments, bills & receipts, notifications, help. | **Strict IDOR boundary:** Can NEVER access another patient's records or unreleased drafts. No access to administrative or clinical staff interfaces. |

---

## 3. Master Role-Permission Matrix

### Legend:
- `FULL`: Complete Create, Read, Update, Delete / Manage authority.
- `READ`: View and search access only.
- `OWN`: Access restricted exclusively to own records or assigned patients.
- `DEP`: Access restricted to assigned department.
- `NONE`: Access completely prohibited (Server-side 403 Forbidden).

---

### Detailed Matrix Table

| Module & Action | Permission Slug | Super Admin | Admin | Doctor | Diagnostic Staff (LIS/RIS) | Pharmacy Staff (PMS) | Surgical / Diet Staff | Patient | Enforcement Mechanism |
|---|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|---|
| **SUPER ADMIN GOVERNANCE** | | | | | | | | | |
| View Super Admin Dashboard | `superadmin.dashboard.view` | FULL | NONE | NONE | NONE | NONE | NONE | NONE | `RoleMiddleware:super_admin` |
| Manage Security & Maintenance Settings | `settings.security.manage` | FULL | NONE | NONE | NONE | NONE | NONE | NONE | `EnsurePermission` |
| Database Backup Management | `settings.backup.manage` | FULL | NONE | NONE | NONE | NONE | NONE | NONE | `EnsurePermission` |
| Master Diagnostic & Service Catalog | `catalog.master.manage` | FULL | READ | READ | READ | READ | READ | NONE | `EnsurePermission` |
| Clinical Code Systems (ICD/CPT/LOINC) | `clinical-codes.manage` | FULL | READ | READ | READ | READ | NONE | NONE | `EnsurePermission` |
| Protected Audit Vault (Tamper-evident) | `audit.vault.view` | FULL | NONE | NONE | NONE | NONE | NONE | NONE | `EnsurePermission` |
| Audit Vault CSV Export | `audit.vault.export` | FULL | NONE | NONE | NONE | NONE | NONE | NONE | `EnsurePermission` |
| Manage Privileged Admin Accounts | `accounts.admin.manage` | FULL | NONE | NONE | NONE | NONE | NONE | NONE | `EnsurePermission` + Anti-escalation |
| Authorize Doctor Provisioning Requests | `accounts.doctor-provisioning.authorize`| FULL | NONE | NONE | NONE | NONE | NONE | NONE | `EnsurePermission` |
| Review Roles and Permission Structure | `access.roles.review` | FULL | READ | NONE | NONE | NONE | NONE | NONE | `EnsurePermission` |
| **HOSPITAL OPERATIONS (ADMIN)** | | | | | | | | | |
| View Admin Dashboard | `admin.dashboard.view` | READ | FULL | NONE | NONE | NONE | NONE | NONE | `RoleMiddleware:admin` |
| Manage Hospital Branding & Settings | `settings.hospital.manage` | FULL | FULL | NONE | NONE | NONE | NONE | NONE | `EnsurePermission` |
| Manage Operational Staff Accounts | `accounts.staff.manage` | FULL | FULL | NONE | NONE | NONE | NONE | NONE | `EnsurePermission` + Policy |
| Request Doctor Account Provisioning | `accounts.doctor.manage` | NONE | FULL | NONE | NONE | NONE | NONE | NONE | `EnsurePermission` |
| View Real-time Operations Monitor | `operations.monitor.view` | READ | FULL | NONE | NONE | NONE | NONE | NONE | `EnsurePermission` |
| View System Audit Logs (Operational) | `audit.logs.view` | FULL | READ | NONE | NONE | NONE | NONE | NONE | `EnsurePermission` |
| Manage Departments & Operating Hours | `departments.manage` | FULL | FULL | NONE | NONE | NONE | NONE | NONE | `EnsurePermission` |
| **PATIENT MANAGEMENT & PORTAL LINKING** | | | | | | | | | |
| Patient Directory (Master Search/View) | `patients.view` | NONE | FULL | FULL | DEP | DEP | DEP | NONE | `PatientPolicy` + `User::canAccessPatients()` |
| Patient Portal Account Linking (Adapter)| `patients.create` | NONE | FULL | NONE | NONE | NONE | NONE | NONE | `Admin\PatientIntakeController` |
| Patient Profile Edit (Contact/Address) | `patients.update` | NONE | FULL | NONE | NONE | NONE | NONE | NONE | `PatientPolicy` |
| Archive Patient Record (Soft Delete) | `patients.archive` | NONE | FULL | NONE | NONE | NONE | NONE | NONE | `PatientPolicy` |
| **APPOINTMENTS & SCHEDULING** | | | | | | | | | |
| View Master Appointment Schedule | `appointments.view` | NONE | FULL | OWN | NONE | NONE | NONE | NONE | `AppointmentController` (Doctor scoped) |
| Book Appointment (In-Clinic / Intake) | `appointments.create` | NONE | FULL | OWN | NONE | NONE | NONE | NONE | `AppointmentScheduler` Service |
| Manage / Update Appointment Status | `appointments.manage` | NONE | FULL | OWN | NONE | NONE | NONE | NONE | `AppointmentScheduler` (Doctor restricted) |
| Book Own Appointment (Patient Portal) | `appointments.patient_book`| NONE | NONE | NONE | NONE | NONE | NONE | OWN | `PatientPortalController` (Zero-trust) |
| Cancel Own Appointment (Patient Portal)| `appointments.patient_cancel`| NONE | NONE | NONE | NONE | NONE | NONE | OWN | `PatientPortalController` (Scoped) |
| **CLINICAL CARE & MEDICAL RECORDS** | | | | | | | | | |
| Access Medical Records (EHR History) | `clinical.records.view` | NONE | READ | FULL | DEP | DEP | DEP | NONE | `PatientPolicy` |
| Order Laboratory / Diagnostic Tests | `diagnostics.order` | NONE | NONE | FULL | NONE | NONE | NONE | NONE | `RoleMiddleware:doctor` |
| Prescribe Medications | `clinical.prescribe` | NONE | NONE | FULL | NONE | NONE | NONE | NONE | `RoleMiddleware:doctor` |
| Order Surgical Procedures | `surgery.order` | NONE | NONE | FULL | NONE | NONE | NONE | NONE | `RoleMiddleware:doctor` |
| Order Clinical Dietary Plans | `diet.order` | NONE | NONE | FULL | NONE | NONE | NONE | NONE | `RoleMiddleware:doctor` |
| Access Virtual MediSense AI (CDSS) | `medisense.access` | NONE | NONE | FULL | NONE | NONE | NONE | NONE | `RoleMiddleware:doctor` (Strict) |
| **DIAGNOSTIC SUBSYSTEMS (LIS & RIS)** | | | | | | | | | |
| LIS: Access Laboratory Dashboard | `lis.dashboard.view` | NONE | READ | READ | FULL | NONE | NONE | NONE | `RoleMiddleware:med-tech,doctor` |
| LIS: Enter Laboratory Test Results | `lis.results.enter` | NONE | NONE | NONE | FULL (Med-Tech) | NONE | NONE | NONE | `permission:lis.results.enter` |
| LIS: Validate Laboratory Test Results | `lis.results.verify` | NONE | NONE | NONE | FULL (Med-Tech) | NONE | NONE | NONE | `permission:lis.results.verify` |
| LIS: Formally Release Test Results | `lis.results.release` | NONE | NONE | NONE | FULL (Med-Tech) | NONE | NONE | NONE | `permission:lis.results.release` |
| RIS: Access Radiology Dashboard | `ris.dashboard.view` | NONE | READ | READ | FULL | NONE | NONE | NONE | `RoleMiddleware:rad-tech,radiologist` |
| RIS: Procedure Scheduling & Imaging | `ris.images.upload` | NONE | NONE | NONE | FULL (Rad-Tech) | NONE | NONE | NONE | `RoleMiddleware:rad-tech` |
| RIS: Approve & Release Radiologist Report| `ris.reports.release` | NONE | NONE | NONE | FULL (Radiologist)| NONE | NONE | NONE | `permission:ris.reports.release` |
| **PHARMACY SUBSYSTEM (PMS)** | | | | | | | | | |
| PMS: Access Pharmacy Dashboard | `pms.dashboard.view` | NONE | READ | READ | NONE | FULL | NONE | NONE | `RoleMiddleware:pharmacist` |
| PMS: Clinical Verification of Orders | `pms.prescriptions.verify`| NONE | NONE | NONE | NONE | FULL | NONE | NONE | `permission:pms.prescriptions.verify` |
| PMS: Medication Dispensing Execution | `pms.dispense` | NONE | NONE | NONE | NONE | FULL | NONE | NONE | `permission:pms.dispense` |
| **SURGERY & OPERATING ROOM (SORS)** | | | | | | | | | |
| SORS: View Surgery Requests | `sors.requests.view` | READ | FULL | OWN | NONE | NONE | FULL (OR Coord) / NONE (OR Staff) | NONE | `SurgeryRequestPolicy` |
| SORS: Create / Edit Pending Request | `sors.requests.create` | NONE | NONE | OWN | NONE | NONE | NONE | NONE | `RoleMiddleware:doctor` + `SurgeryRequestPolicy` |
| SORS: Manage Incoming Requests | `sors.requests.manage` | FULL | FULL | NONE | NONE | NONE | FULL (OR Coord) | NONE | `SurgeryRequestPolicy` |
| SORS: View OR Schedules & Calendar | `sors.schedules.view` | READ | FULL | OWN | NONE | NONE | FULL (OR Coord) / OWN (OR Staff) | NONE | `SurgerySchedulePolicy` |
| SORS: Schedule / Reschedule / Assign OR | `sors.schedules.manage` | FULL | FULL | NONE | NONE | NONE | FULL (OR Coord) | NONE | `SurgerySchedulePolicy` + Conflict Check |
| SORS: Update OR Readiness & Prep | `sors.readiness.update` | FULL | FULL | NONE | NONE | NONE | FULL (OR Coord) / OWN (OR Staff) | NONE | `SurgerySchedulePolicy` |
| SORS: Update Operation Status | `sors.procedures.execute` | FULL | FULL | NONE | NONE | NONE | FULL (OR Coord) / OWN (OR Staff) | NONE | `SurgerySchedulePolicy` |
| SORS: Access Surgery Reports | `sors.reports.view` | FULL | FULL | OWN | NONE | NONE | FULL (OR Coord) / NONE (OR Staff) | NONE | `ReportController` + `ReportService` |
| **PATIENT HEALTHCARE PORTAL** | | | | | | | | | |
| View Personal Dashboard | `patient.portal.access` | NONE | NONE | NONE | NONE | NONE | NONE | OWN | `RoleMiddleware:patient` + Ownership |
| View & Update Personal Contact Profile | `patient.portal.access` | NONE | NONE | NONE | NONE | NONE | NONE | OWN | `PatientPortalController@profile` |
| View Personal Released Lab & RIS Reports| `patient.portal.access` | NONE | NONE | NONE | NONE | NONE | NONE | OWN | `where('status', 'Released')` strictly |
| View Active Prescriptions & Regimens | `patient.portal.access` | NONE | NONE | NONE | NONE | NONE | NONE | OWN | Filtered by `patient_id` |
| View Dietary & Clinical Treatment Plans | `patient.portal.access` | NONE | NONE | NONE | NONE | NONE | NONE | OWN | Filtered by `patient_id` |
| View Personal Invoices & Payment History| `patient.portal.access` | NONE | NONE | NONE | NONE | NONE | NONE | OWN | Filtered by `patient_id` |
| View Patient Help & Support Guide | `patient.portal.access` | NONE | NONE | NONE | NONE | NONE | NONE | FULL | Open to authenticated patients |

---

## 4. Distinct Permission Boundaries & Separation of Duties

### 4.1 Super Admin vs. Clinical Authority
- Super Admin accounts have **no clinical permissions**.
- Method `User::canAccessPatients()` explicitly returns `false` for Super Admin.
- Super Admin accessing `/medisense/*`, `/doctor/*`, or `/patient/*` triggers a 403 Forbidden response.

### 4.2 Admin vs. Super Admin Anti-Escalation
- Admins are blocked from accessing any `/super-admin/*` routes.
- When creating or editing staff in `/admin/staff` or `/admin/users`, policies reject any submission attempting to assign `super_admin` or `admin` role IDs.
- Admins cannot unlock or archive accounts belonging to `super_admin` or peer `admin` users.

### 4.3 Diagnostic Separation: Entry vs. Verification vs. Release
- In LIS, result processing requires separate permissions:
  - `lis.results.enter`: entering raw values.
  - `lis.results.verify`: quality control validation.
  - `lis.results.release`: official sign-off exposing report to doctor and patient.
- In RIS, Rad-Techs cannot sign or release reports; only Radiologists holding `ris.reports.release` can approve diagnostic impressions.

### 4.4 Clinical vs. Dispensing Separation
- Attending physicians create prescriptions (`clinical.prescribe`), but **cannot** dispense medications.
- Pharmacists verify suitability (`pms.prescriptions.verify`) and execute dispensing (`pms.dispense`), but **cannot** alter the prescribed active molecule without physician re-order.

### 4.5 Patient Portal Zero-Trust Boundaries
- Every query executes: `where('patient_id', Auth::user()->patient_id)`.
- Unreleased lab results (`status != 'Released'`) and draft radiology reports are stripped from query results.
- Patients cannot edit clinical notes, diagnoses, or prescriptions.
