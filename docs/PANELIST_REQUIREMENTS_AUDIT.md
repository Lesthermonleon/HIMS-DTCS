# DTCS HIMS — Panelist Requirements Audit & Traceability Matrix

**Document Version:** 2.0.0  
**Date:** October 18, 2026  
**System:** Diagnostic, Treatment & Clinical Services HIMS (DTCS HIMS)  
**Status:** DRAFT / PENDING ARCHITECTURAL REVIEW (Implementation Paused)

---

## 1. Audit Classification Standards

Every feature, page, route, and requirement is audited and classified into one of seven standardized categories:
1. **Implemented and verified:** Code, route, authorization, database, and automated tests are fully functional.
2. **Implemented but needs correction:** Feature exists and runs, but contains behavioral gaps, invalid defaults, or missing constraints.
3. **Partially implemented:** Backend or UI exists in part, but end-to-end integration or specific sub-features are incomplete.
4. **Missing:** Required capability is not implemented in routes, controllers, views, or database.
5. **Duplicate or obsolete:** Legacy code, redundant screens, or obsolete branding requiring cleanup.
6. **Out of scope or dependent on an external system:** Feature belongs to external infrastructure or was explicitly excluded (e.g., patient master registry, medication inventory).
7. **Blocked pending clarification:** Implementation cannot safely proceed without client architectural approval or schema definition.

---

## 2. Requirements Traceability & Audit Matrix

### 2.1 Five Primary User Types & RBAC Architecture

| Requirement & Feature | Classification | File Paths & Routes | Authorization & DB Dependencies | Audit Findings & Current State |
|---|---|---|---|---|
| **Five Primary User Types** (Super Admin, Admin, Doctor, Staff, Patient) | **Implemented and verified** | `app/Models/User.php`, `app/Models/Role.php`, `resources/views/partials/_sidebar.blade.php` | `roles` table (`super_admin`, `admin`, `doctor`, staff roles, `patient`), `role_user` pivot | Fully modeled in database and navigation. Precedence: Super Admin > Admin > Doctor > Staff > Patient. |
| **Super Admin Governance Dashboard** | **Implemented and verified** | `app/Http/Controllers/SuperAdmin/DashboardController.php`, `resources/views/super_admin/dashboard.blade.php` | Route `super-admin.dashboard`, guarded by `role:super_admin` | Displays system metrics, catalog health, privileged counts. Text sanitized to remove legacy "integrations" references. |
| **Super Admin Master Service Catalog** | **Implemented and verified** | `app/Http/Controllers/SuperAdmin/ServiceCatalogController.php`, `resources/views/super_admin/catalog/index.blade.php` | Routes `super-admin.catalog.*`, permission `catalog.master.manage` | Full catalog management with lifecycle states (`Active`, `Draft`, `Deprecated`, `Archived`). |
| **Super Admin Clinical Codes (ICD/CPT/LOINC)** | **Implemented and verified** | `app/Http/Controllers/SuperAdmin/ClinicalCodeController.php`, `resources/views/super_admin/clinical_codes/index.blade.php` | Routes `super-admin.clinical-codes.*`, permission `clinical-codes.manage` | Code systems staging, activation, and rollback. |
| **Super Admin Protected Audit Vault** | **Implemented and verified** | `app/Http/Controllers/SuperAdmin/AuditVaultController.php`, `resources/views/super_admin/audit_vault/index.blade.php` | Routes `super-admin.audit-logs.*`, permission `audit.vault.view`, `audit.vault.export` | Immutable audit stream with SHA-256 tamper-evident integrity hashes and CSV export. |
| **Super Admin Privileged Admin Accounts** | **Implemented and verified** | `app/Http/Controllers/SuperAdmin/AdminAccountController.php`, `resources/views/super_admin/admins/index.blade.php` | Routes `super-admin.admins.*`, permission `accounts.admin.manage` | Full lifecycle for Admin accounts. Anti-escalation prevents self-elevation or elevation to Super Admin. |
| **Super Admin Database Backup** | **Implemented and verified** | `app/Http/Controllers/SuperAdmin/BackupController.php`, `resources/views/super_admin/backup/index.blade.php` | Routes `super-admin.backup.*`, permission `settings.backup.manage` | Backup snapshot tracking and manual pruning. UI accurately notes that restore is handled offline by authorized IT support. |
| **Super Admin Roles & Permissions Management** | **Partially implemented** | `app/Http/Controllers/Admin/RoleController.php`, `resources/views/admin/roles/index.blade.php` | Routes `admin.roles.*`, permission `access.roles.review` | Read-only matrix view available under Admin/Super Admin; direct UI for dynamically editing permissions or assigning custom roles is not implemented. |
| **Super Admin Department Management** | **Implemented and verified** | `app/Http/Controllers/SuperAdmin/DepartmentController.php`, `resources/views/super_admin/departments/index.blade.php` | Route `super-admin.departments`, `Department` model | Management and metrics for hospital clinical departments. |
| **Admin Operations Dashboard** | **Implemented and verified** | `app/Http/Controllers/Admin/DashboardController.php`, `resources/views/admin/dashboard.blade.php` | Route `admin.dashboard`, guarded by `role:admin` | Operational overview, queue counters, user presence, department activity. |
| **Admin Staff Account Management** | **Implemented and verified** | `app/Http/Controllers/Admin/StaffController.php`, `resources/views/admin/staff/index.blade.php` | Routes `admin.staff.*`, permission `accounts.staff.manage` | Provisions staff accounts for LIS, RIS, PMS, SORS, DNMS. Anti-escalation blocks assigning `super_admin` or `admin`. |
| **Admin Doctor Onboarding (Two-Tier)** | **Implemented and verified** | `app/Http/Controllers/Admin/DoctorController.php`, `resources/views/admin/doctors/index.blade.php` | Routes `admin.doctors.*`, permission `accounts.doctor.manage` | Admin initiates doctor request; Super Admin authorization required before activation. |
| **Admin Hospital Settings** | **Implemented and verified** | `app/Http/Controllers/Admin/HospitalSettingController.php`, `resources/views/admin/hospital_settings/index.blade.php` | Routes `admin.hospital-settings.*`, permission `settings.hospital.manage` | Facility metadata, operating hours, queue parameters. |
| **Doctor Outpatient Queue & Appointments** | **Implemented and verified** | `app/Http/Controllers/Admin/AppointmentController.php`, `app/Services/Appointments/AppointmentScheduler.php` | Routes `admin.appointments.*`, `appointments` table | Scoped so doctors view and manage only their own appointments. Double-booking prevented by scheduler. |
| **Doctor Clinical Consultations & Notes** | **Blocked pending clarification** | N/A (Missing dedicated controller / routes) | No `consultations` or `clinical_notes` table in schema | Consultation encounters and structured ICD-10 encounter notes require schema design and approval. |
| **Doctor Diagnostic Orders (LIS/RIS)** | **Implemented and verified** | `app/Http/Controllers/Lab/LabRequestController.php`, `app/Http/Controllers/Radiology/RadiologyRequestController.php` | Routes `lab.requests.*`, `radiology.requests.*`, `role:doctor` | Ordering interface for laboratory and imaging requests. |
| **Doctor Prescriptions (PMS)** | **Implemented and verified** | `app/Http/Controllers/Pharmacy/PrescriptionController.php` | Routes `pharmacy.prescriptions.*`, `role:doctor` | Medication prescription creation with dosage and duration. |
| **Doctor Virtual MediSense AI (CDSS)** | **Implemented and verified** | `app/Http/Controllers/MediSenseController.php`, `resources/views/medisense/index.blade.php` | Routes `medisense.*`, strictly guarded by `role:doctor` | Clinical decision support strictly restricted to Doctors. Does not independently diagnose, order, or sign records. |
| **Staff: Laboratory (LIS)** | **Implemented and verified** | `app/Http/Controllers/Lab/LabResultController.php` | Routes `lab.results.*`, permissions `lis.results.enter`, `lis.results.verify`, `lis.results.release` | Specimen accessioning, result entry, QC validation, and formal release. Route-level permissions enforced. |
| **Staff: Radiology (RIS)** | **Implemented and verified** | `app/Http/Controllers/Radiology/RadiologyRequestController.php`, `RadiologyReportController.php` | Routes `radiology.*`, permissions `ris.images.upload`, `ris.reports.release` | Rad-Tech uploads images; Radiologist interprets and releases report. Strict separation of duties. |
| **Staff: Pharmacy (PMS)** | **Implemented and verified** | `app/Http/Controllers/Pharmacy/PrescriptionController.php`, `DispensingController.php` | Routes `pharmacy.*`, permissions `pms.prescriptions.verify`, `pms.dispense` | Pharmacist verifies clinical suitability and dispenses medication. Route-level permissions enforced. |
| **Staff: Surgery Operations (SORS)** | **Implemented and verified** | `app/Http/Controllers/Surgery/SurgeryScheduleController.php` | Routes `surgery.*`, role `or-coordinator` | Operating room scheduling and surgical team tracking. |
| **Staff: Dietary Management (DNMS)** | **Implemented and verified** | `app/Http/Controllers/Diet/DietPlanController.php` | Routes `diet.*`, role `dietitian` | Clinical nutrition assessment and meal plan formulation. |
| **Staff: Receptionist, Nurse, Cashier** | **Blocked pending clarification** | N/A | Roles do not exist in `roles` table | Workflows are currently handled under `admin` or general staff permissions. Adding explicit roles requires approval. |
| **Patient Portal: My Dashboard** | **Implemented and verified** | `app/Http/Controllers/PatientPortal/PatientPortalController.php@dashboard`, `resources/views/patient/dashboard.blade.php` | Route `patient.dashboard`, guarded by `role:patient` | Shows personal upcoming appointments, released lab/radiology count, active prescriptions. |
| **Patient Portal: My Profile** | **Implemented and verified** | `PatientPortalController.php@profile`, `resources/views/patient/profile.blade.php` | Route `patient.profile`, `patient.profile.update` | View demographics; update contact phone and address. IDOR protected. |
| **Patient Portal: My Appointments** | **Implemented and verified** | `PatientPortalController.php@appointments`, `resources/views/patient/appointments.blade.php` | Routes `patient.appointments`, `patient.appointments.book`, `patient.appointments.cancel` | View upcoming/past consultations; request appointment (overlap-checked); cancel pending/confirmed appointments. |
| **Patient Portal: Diagnostic Results** | **Implemented and verified** | `PatientPortalController.php@results`, `resources/views/patient/results.blade.php` | Route `patient.results` | Unreleased diagnostic results strictly hidden (`where('status', 'Released')`). IDOR protected. |
| **Patient Portal: Prescriptions** | **Implemented and verified** | `PatientPortalController.php@prescriptions`, `resources/views/patient/prescriptions.blade.php` | Route `patient.prescriptions` | View verified and dispensed medication regimens. |
| **Patient Portal: Treatment Plans** | **Implemented and verified** | `PatientPortalController.php@treatments`, `resources/views/patient/treatments.blade.php` | Route `patient.treatments` | View clinical dietary regimens and care instructions. |
| **Patient Portal: Bills & Receipts** | **Implemented and verified** | `PatientPortalController.php@billing`, `resources/views/patient/billing.blade.php` | Route `patient.billing` | Read-only view of patient invoices and receipts. |
| **Patient Portal: Notifications** | **Implemented and verified** | `app/Http/Controllers/NotificationController.php`, `resources/views/notifications/index.blade.php` | Route `notifications.index` | View operational and clinical release notifications. |
| **Patient Portal: Help & Support** | **Implemented and verified** | `resources/views/patient/help.blade.php` | Route `patient.help` | Static guidance for portal navigation and account issues. |
| **Patient Portal: My Medical Records** | **Blocked pending clarification** | N/A | Missing clinical consultation entity | Blocked because outpatient consultation notes and diagnoses have no database entity. |

---

### 2.2 Scope Restrictions & Conflict Audit

| Scope Area | Audit Classification | Architectural Resolution | Impact & Safeguards |
|---|---|---|---|
| **Patient Master Data** | **Out of scope (External System)** | Retracted standalone patient registration form. Implemented link-only adapter in `Admin\PatientIntakeController`. | Prevents creating duplicate or conflicting patient master records. Requires identity verification attestation. Does not set guessable default passwords. |
| **Medication Inventory & Purchasing** | **Out of scope** | Inventory management, purchasing orders, and warehouse adjustments are omitted. | The read-only medication stock provider integration is preserved for prescription dispensing. |
| **External Healthcare Integrations** | **Duplicate / Obsolete (Removed)** | Purged routes, controllers, views, and UI links per user instructions. | Removed all mock connectivity tests and unused API credential forms. |
| **Doctor-Patient Assignment Scope** | **Blocked pending clarification** | Doctors currently search all patients in the master directory placeholder. | Scoping doctors to an "assigned care team" requires establishing care team relationships in schema. |

---

## 3. Branding & Legacy Feature Audit

1. **Brand Transformation (`DITC` -> `DTCS`):**
   - User-facing text, page titles, navigation bars, and documentation updated to **DTCS HIMS** (Diagnostic, Treatment & Clinical Services).
   - Operational identifiers preserved: test database name `ditc_hms` in `phpunit.xml` and historical schema file `ditc_hms_schema.sql` are retained as operational configuration to prevent breaking build scripts.
2. **Obsolete Schema Cleanups:**
   - Deleted obsolete un-migrated schema files `database/schema/mysql-schema.sql` and `super_admin_tables.sql` in previous approved cleanup.
3. **Stray Root Files Identified (Not Deleted):**
   - `smtp-test.php`, `tmp_inspect_db.php`, `tmp_inspect_db2.php`, and root `seader.sql` exist in project root. Reported for user decision.
