# DTCS HIMS — SORS Role-Based Workflow Implementation Report

**Project:** DTCS Hospital Information Management System (HIMS)  
**Module:** Surgery and Operating Room Scheduler (SORS)  
**Repository:** `Lesthermonleon/DTCS`  
**Audit Baseline:** `docs/SORS_REQUIREMENTS_AUDIT.md`  
**Date:** November 6, 2026

---

## 1. Implemented Features

### 1.1 Role-Based Access Control & Custom RBAC Integration
- Reused existing `doctor` and `or-coordinator` roles without duplication.
- Registered the new `or-staff` role (`OR Staff`, dashboard route `surgery.dashboard`) across `App\Models\Role`, `App\Http\Controllers\Admin\StaffAccountController`, `App\Http\Controllers\SuperAdmin\UserManagementController`, and database schemas/seeders.
- Seeded eight granular SORS permissions under module `SORS` and mapped them to roles following least-privilege principles without introducing Spatie or altering the existing custom `roles` / `role_user` / `permissions` / `permission_role` architecture.
- Enforced strict clinical boundary separation so that `admin` and `super_admin` administrative privileges do not automatically grant clinical or OR-scheduling mutation authority.

### 1.2 Policy-Driven Authorization (`SurgeryRequestPolicy` & `SurgerySchedulePolicy`)
- Created and registered `App\Policies\SurgeryRequestPolicy` and `App\Policies\SurgerySchedulePolicy` in `App\Providers\AppServiceProvider`.
- Updated `App\Policies\PatientPolicy::view` and `App\Models\Patient` (`scopeAuthorizedForDoctor`, `isAuthorizedForDoctor`) so doctors cannot view or order procedures for another doctor's restricted patient records.
- Scoped `SurgeryRequest::scopeVisibleTo`, `SurgerySchedule::scopeVisibleTo`, FullCalendar JSON feed (`calendarEvents`), and `App\Services\ReportService` surgery reports so each role only sees authorized records.

### 1.3 Request Review & Scheduling Accountability
- Preserved the existing `surgery_requests.status` lifecycle (`Pending`, `Scheduled`, `In Progress`, `Completed`, `Cancelled`) where incoming `Pending` requests are never automatically scheduled; `or-coordinator` reviews pending requests before scheduling (`Pending` -> `Scheduled`) or cancelling (`Pending` -> `Cancelled`).
- Restricted clinical creation, editing, and unscheduled deletion of `Pending` surgery requests exclusively to the originating `doctor`.
- Added the missing `resources/views/surgery/requests/edit.blade.php` template and restored the `surgery_type` compatibility accessor on `SurgeryRequest`.

### 1.4 Conflict Prevention & Transactional Concurrency Safety
- Upgraded `App\Http\Requests\StoreSurgeryScheduleRequest` and `App\Models\SurgerySchedule` (`findRoomConflict`, `findTeamConflict`) to enforce:
  - Operating Room active (`is_active = true`) and non-maintenance (`status != 'Under Maintenance'`) checks.
  - Overlapping time-window detection for the same Operating Room across active schedules (`Scheduled`, `In Progress`).
  - Overlapping time-window detection for the same Surgical Team across active schedules (`Scheduled`, `In Progress`).
  - Duplicate active schedule prevention for the same `SurgeryRequest`.
  - Self-exclusion during rescheduling (`update`) so non-conflicting updates succeed cleanly.
- Wrapped `store`, `update`, `destroy`, `cancel`, `updateReadiness`, `start`, and `complete` in `SurgeryScheduleController` inside `DB::transaction(...)` with `lockForUpdate()` row-level locking to prevent concurrent double-booking or race-condition state transitions.

### 1.5 Operating Room Preparation & Readiness Tracking
- Added OR Readiness tracking on `surgery_schedules` supporting `Pending Prep`, `Preparing`, and `Ready`:
  - `previous_readiness_status`
  - `readiness_status`
  - `readiness_notes`
  - `readiness_updated_by`
  - `readiness_updated_at`
- Authorized `or-coordinator` and assigned `or-staff` (verified via `surgical_team_members.user_id`) to update readiness and operational notes via `PATCH /surgery/schedules/{surgerySchedule}/readiness`.
- Blocked `doctor`, unassigned `or-staff`, `admin`, and `super_admin` from updating OR preparation/readiness.

### 1.6 Server-Side Status Transitions & Historical Record Preservation
- Enforced strict state-machine rules separating **Surgery Request Status**, **OR Schedule Status**, and **OR Readiness Status**:
  - `start` (`Scheduled` -> `In Progress`) records `actual_start`, marks the `SurgeryRequest` as `In Progress`, and sets the `OperatingRoom` status to `Occupied`.
  - `complete` (`In Progress` -> `Completed`) records `actual_end`, marks the `SurgeryRequest` as `Completed`, and releases the `OperatingRoom` back to `Available` if no other active case is in progress.
  - `cancel` (`Scheduled` / `Postponed` -> `Cancelled`) cancels the schedule and linked request and releases the room if unoccupied.
  - Terminal schedules (`Completed`, `Cancelled`) and active `In Progress` schedules are protected from permanent deletion (`destroy` returns HTTP `422`).
- Reserved `start` and `complete` execution (`sors.procedures.execute`) for `or-coordinator` by default while allowing assigned `or-staff` only if explicitly granted the `sors.procedures.execute` permission.

### 1.7 Dashboard, Sidebar & View Fixes
- Updated `resources/views/partials/_sidebar.blade.php` with role-aware `MAIN` -> `Dashboard` and `SURGERY & OPERATING ROOM (SORS)` navigation (`Overview`, `Surgery Requests`, `OR Schedules`, `Surgery Calendar`). For `doctor`, `Clinical Reports` (`route('reports.index')`) serves as the single central entry point under `REPORTS` (which links to `Surgery Reports`), while `or-coordinator`, `admin`, and `super_admin` have `Surgery Reports` (`route('reports.surgery.activity')`) under `REPORTS` / `MONITORING & REPORTS` and `or-staff` has it hidden.
- Enhanced `DashboardController::surgery()` and `resources/views/dashboard/surgery.blade.php` to provide a dedicated **OR Staff Assigned Cases & Readiness Center** view (`Assigned Cases`, `OR Pending Prep`, `OR Preparing`, `OR Ready`) alongside the **OR Coordinator Operational Center**.
- Fixed `resources/views/surgery/schedules/print.blade.php`, `resources/views/surgery/schedules/show.blade.php`, and `resources/views/surgery/schedules/index.blade.php` (`schedule_no` accessor, correct `duration_minutes` / `procedure_name` / `urgency` bindings, readiness badges, and `@can` action guards).

---

## 2. Final Role and Permission Matrix

### 2.1 Granular SORS Permissions (`permissions` table, `module = 'SORS'`)

| Permission Slug | Permission Name | `doctor` | `or-coordinator` | `or-staff` | `admin` | `super_admin` |
| :--- | :--- | :---: | :---: | :---: | :---: | :---: |
| `sors.requests.view` | View Surgery Requests | Yes (Own / Assigned) | Yes (All) | No | Yes (Read-Only Monitor) | No Clinical Mutation |
| `sors.requests.create` | Create Surgery Requests | Yes (Authorized Patients) | No | No | No | No |
| `sors.requests.manage` | Manage Surgery Requests | Own `Pending` Only | Cancel `Pending`/`Scheduled` | No | No | No |
| `sors.schedules.view` | View OR Schedules | Yes (Own / Assigned) | Yes (All) | Yes (Assigned Only) | Yes (Read-Only Monitor) | No Clinical Mutation |
| `sors.schedules.manage` | Manage OR Schedules | No | Yes | No | No | No |
| `sors.readiness.update` | Update OR Readiness | No | Yes | Yes (Assigned Only) | No | No |
| `sors.procedures.execute` | Execute OR Procedures | No | Yes | Configurable (Off by default) | No | No |
| `sors.reports.view` | View Surgery Reports | Yes (Own Scope) | Yes (All) | No | Yes (Read-Only Monitor) | Read-Only Monitor |

### 2.2 Action-Level Authorization Summary

| Action / Endpoint | `doctor` | `or-coordinator` | `or-staff` (Assigned) | `or-staff` (Unassigned) | `admin` / `super_admin` | Other Staff (`med-tech`, etc.) |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: |
| Create Surgery Request (`POST /surgery/requests`) | Allowed (Authorized Patient) | `403` | `403` | `403` | `403` | `403` |
| Edit/Update Own `Pending` Request (`PUT /surgery/requests/{id}`) | Allowed (Owner) | `403` | `403` | `403` | `403` | `403` |
| Cancel `Pending` Request (`PATCH /surgery/requests/{id}/cancel`) | Allowed (Owner) | Allowed | `403` | `403` | `403` | `403` |
| Cancel `Scheduled` Request (`PATCH /surgery/requests/{id}/cancel`) | `403` | Allowed | `403` | `403` | `403` | `403` |
| Schedule / Reschedule OR (`POST/PUT /surgery/schedules`) | `403` | Allowed | `403` | `403` | `403` | `403` |
| Cancel OR Schedule (`PATCH /surgery/schedules/{id}/cancel`) | `403` | Allowed | `403` | `403` | `403` | `403` |
| Update OR Readiness (`PATCH /surgery/schedules/{id}/readiness`) | `403` | Allowed | Allowed | `403` | `403` | `403` |
| Start / Complete Surgery (`PATCH /surgery/schedules/{id}/start\|complete`) | `403` | Allowed | Requires `sors.procedures.execute` | `403` | `403` | `403` |
| View Schedule / Print (`GET /surgery/schedules/{id}`) | Own / Team | Allowed | Allowed | `403` | Read-Only | `403` |
| View Surgery Reports (`GET /reports/surgery/*`) | Own Cases Only | Allowed (All) | `403` | `403` | Read-Only | `403` |

---

## 3. Workflow and Status Transitions

### 3.1 Three Separated State Dimensions

1. **Surgery Request Status (`surgery_requests.status`):**
   - `Pending` -> Created by `doctor`. Editable/cancellable by originating `doctor`; reviewable/schedulable/cancellable by `or-coordinator`. Never auto-scheduled.
   - `Scheduled` -> Set when `or-coordinator` assigns an Operating Room, Surgical Team, and time slot.
   - `In Progress` -> Synchronized when the scheduled operation starts.
   - `Completed` -> Synchronized when the operation completes (terminal).
   - `Cancelled` -> Set when cancelled from `Pending` or `Scheduled` (terminal).

2. **OR Schedule Status (`surgery_schedules.status`):**
   - `Scheduled` -> Active booking created by `or-coordinator`.
   - `Postponed` -> Temporarily deferred schedule managed by `or-coordinator`.
   - `In Progress` -> Operation underway (`actual_start` recorded; OR marked `Occupied`). Cannot be started again, cancelled, or deleted.
   - `Completed` -> Operation finished (`actual_end` recorded; OR released to `Available`). Terminal and immutable.
   - `Cancelled` -> Booking cancelled before execution; OR released to `Available`. Terminal and immutable.

3. **OR Preparation / Readiness Status (`surgery_schedules.readiness_status`):**
   - `Pending Prep` (default upon scheduling) -> `Preparing` -> `Ready`.
   - Tracked independently of clinical request status and schedule execution status.
   - Records `previous_readiness_status`, `readiness_status`, `readiness_notes`, `readiness_updated_by`, and `readiness_updated_at` on every update.

---

## 4. Modified Files

| File Path | Summary of Changes |
| :--- | :--- |
| `docs/SORS_REQUIREMENTS_AUDIT.md` | Phase 1 SORS architecture and RBAC audit document. |
| `docs/SORS_IMPLEMENTATION_REPORT.md` | Final implementation report and verification documentation. |
| `app/Models/Role.php` | Added `ROLE_OR_STAFF = 'or-staff'` constant and registered `or-staff` in `STAFF_PROVISIONABLE_ROLES`. |
| `app/Models/Patient.php` | Added `scopeAuthorizedForDoctor()` and `isAuthorizedForDoctor()` for cross-doctor patient protection. |
| `app/Models/SurgeryRequest.php` | Added `scopeVisibleTo()` and `getSurgeryTypeAttribute()` compatibility accessor. |
| `app/Models/SurgerySchedule.php` | Added readiness fields (`previous_readiness_status`, `readiness_status`, `readiness_notes`, `readiness_updated_by`, `readiness_updated_at`), `schedule_no` accessor, `scopeVisibleTo()`, `isAssignedToUser()`, `findRoomConflict()`, and `findTeamConflict()`. |
| `app/Policies/PatientPolicy.php` | Restricted `view()` for doctors to authorized patients via `isAuthorizedForDoctor()`. |
| `app/Policies/SurgeryRequestPolicy.php` | Enforced doctor ownership, patient authorization, coordinator review/cancellation, and non-clinical admin boundary. |
| `app/Policies/SurgerySchedulePolicy.php` | Enforced coordinator scheduling authority, assigned OR staff readiness updates, permission-gated procedure execution, and terminal immutability. |
| `app/Providers/AppServiceProvider.php` | Registered `SurgeryRequestPolicy` and `SurgerySchedulePolicy`. |
| `app/Http/Requests/StoreSurgeryScheduleRequest.php` | Enforced coordinator authorization, OR active/maintenance validation, room & team overlap detection, and duplicate-schedule prevention. |
| `app/Http/Controllers/Surgery/SurgeryRequestController.php` | Enforced policy checks, doctor-scoped patient dropdowns, pending-only edits, cancellation workflow, and audit logging. |
| `app/Http/Controllers/Surgery/SurgeryScheduleController.php` | Enforced policy checks, transactional `lockForUpdate()` concurrency protection, conflict re-validation, readiness updates (`previous_readiness_status` -> `readiness_status`), status transitions (`start`, `complete`, `cancel`), and `ActivityLog` entries. |
| `app/Http/Controllers/DashboardController.php` | Scoped `surgery()` dashboard queries and added OR Readiness & Assigned Case metrics for `or-staff`. |
| `app/Http/Controllers/Admin/StaffAccountController.php` | Added `or-staff` to allowed staff provisioning roles. |
| `app/Http/Controllers/SuperAdmin/UserManagementController.php` | Added `or-staff` to role filter ordering. |
| `app/Services/ReportService.php` | Scoped SORS report queries (`getSurgeryActivity`, `getSurgeryCompleted`, `getSurgeryCancelled`) to the authenticated doctor when accessed by `doctor`. |
| `routes/web.php` | Updated `surgery.*` route middleware to include `or-staff` on authorized endpoints and exclude `admin`/`super_admin` from clinical/scheduling mutation routes. |
| `resources/views/partials/_sidebar.blade.php` | Organized `MAIN` and `Surgery (SORS)` (`Overview`, `Surgery Requests`, `OR Schedules`, `Surgery Calendar`) navigation and moved `Surgery Reports` (`route('reports.surgery.activity')`) into the general `REPORTS` / `MONITORING & REPORTS` navigation group for `doctor`, `or-coordinator`, `admin`, and `super_admin` (hidden for `or-staff` and non-SORS staff). |
| `tests/Feature/DoctorSidebarTest.php` | Verified `Surgery Reports` appears once under `REPORTS` (outside `Surgery (SORS)`) and returns HTTP `200` for `doctor`. |
| `resources/views/dashboard/surgery.blade.php` | Added OR Staff Assigned Cases & Readiness Center cards/table and guarded request links with `@can`. |
| `resources/views/surgery/requests/edit.blade.php` | Created edit form for doctors to update their own `Pending` surgery requests. |
| `resources/views/surgery/requests/show.blade.php` | Guarded scheduling and mutation buttons with `@can` policy checks. |
| `resources/views/surgery/schedules/index.blade.php` | Added OR Readiness badges and guarded management buttons with `@can`. |
| `resources/views/surgery/schedules/show.blade.php` | Added OR Preparation & Readiness card/form, fixed `duration_minutes` display, and guarded actions with `@can`. |
| `resources/views/surgery/schedules/print.blade.php` | Fixed broken model attribute references (`schedule_no`, `procedure_name`, `urgency`, `duration_minutes`, `readiness_status`). |
| `database/migrations/2026_11_06_000001_sors_role_workflow_additive.php` | Additive, idempotent migration reconciling `surgery_schedules` columns, `or-staff` role, and 8 SORS permissions. |
| `database/schema/sors_role_workflow_additive.sql` | Standalone idempotent SQL script for non-destructive schema and RBAC updates. |
| `database/schema/ditc_hms_schema.sql` | Updated canonical schema with `actual_start`, `actual_end`, readiness columns, and nullable `surgical_team_id`. |
| `database/schema/users_seeder.sql` | Added `or-staff` role, seeded OR Staff account (`orstaff@hospital.com`), and 8 SORS permissions. |
| `tests/Feature/SorsWorkflowAuthorizationTest.php` | Comprehensive 10-part feature test suite verifying all SORS RBAC, conflict, readiness, transition, sidebar, and audit requirements. |

---

## 5. Database Changes

All database changes were applied **additively and idempotently** to both `newdb` (development) and `ditc_hms` (test) without running `migrate:fresh`, `db:wipe`, `DROP`, or `TRUNCATE`:

1. **`surgery_schedules` Table Reconciliation:**
   - Modified `surgical_team_id` (` BIGINT UNSIGNED NULL`) so unassigned/flexible team scheduling does not fail at the SQL layer.
   - Verified/added `actual_start` (`TIMESTAMP NULL`) and `actual_end` (`TIMESTAMP NULL`).
   - Added `previous_readiness_status` (`VARCHAR(32) NULL`).
   - Added `readiness_status` (`ENUM('Pending Prep', 'Preparing', 'Ready') NOT NULL DEFAULT 'Pending Prep'`).
   - Added `readiness_notes` (`TEXT NULL`).
   - Added `readiness_updated_by` (`BIGINT UNSIGNED NULL`, FK -> `users.id` `ON DELETE SET NULL`).
   - Added `readiness_updated_at` (`TIMESTAMP NULL`).
2. **`roles` Table:**
   - Upserted `or-coordinator` (`OR Coordinator`, `dashboard_route = 'surgery.dashboard'`).
   - Upserted `or-staff` (`OR Staff`, `dashboard_route = 'surgery.dashboard'`).
3. **`permissions` & `permission_role` Tables:**
   - Upserted the 8 `SORS` permissions (`sors.requests.view`, `sors.requests.create`, `sors.requests.manage`, `sors.schedules.view`, `sors.schedules.manage`, `sors.readiness.update`, `sors.procedures.execute`, `sors.reports.view`) and mapped them to `doctor`, `or-coordinator`, `or-staff`, and `admin` (read-only).

---

## 6. Security Improvements

- **Eliminated Horizontal Privilege Escalation (IDOR) Across Doctors:** Doctors can no longer view, edit, cancel, or print another doctor's surgery requests or schedules via direct URL manipulation, nor can they submit a surgery request for a patient exclusively under another doctor's care.
- **Eliminated Vertical Privilege Escalation Between Clinical & Operational Roles:**
  - Doctors are blocked (`403`) from creating/editing OR schedules, assigning operating rooms, or updating OR preparation/readiness.
  - OR Coordinators are blocked (`403`) from creating surgery requests or modifying clinical fields (`diagnosis`, `procedure_name`, `anesthesia_type`, `urgency`).
  - OR Staff are blocked (`403`) from viewing unassigned schedules, browsing the surgery requests queue, accessing surgery reports, or modifying OR room/time allocations.
  - Administrative roles (`admin`, `super_admin`) are blocked (`403`) from executing clinical orders, scheduling operations, or updating OR readiness.
- **Database Concurrency Protection:** All schedule creation, rescheduling, readiness, and lifecycle endpoints acquire row locks (`lockForUpdate()`) inside `DB::transaction()` to prevent race-condition double-booking.
- **Immutable Audit Trail:** Every SORS lifecycle action (`Surgery Request Created`, `Surgery Request Updated`, `Surgery Request Cancelled`, `Surgery Scheduled`, `Surgery Rescheduled`, `Surgery Cancelled`, `OR Readiness Updated` with `[previous] -> [new]` status, `Surgery Started`, `Surgery Completed`) writes an append-only `ActivityLog` record.

---

## 7. Automated Test Results

### 7.1 Targeted SORS & Sidebar Feature Suites (`SorsWorkflowAuthorizationTest` & `DoctorSidebarTest`)
Command:
```bash
php artisan test tests/Feature/DoctorSidebarTest.php tests/Feature/SorsWorkflowAuthorizationTest.php
```
Result:
- **Status:** `PASSED` (17 tests, 304 assertions, 0 failures)
- **Covered Scenarios:**
  1. `test_doctor_can_create_and_edit_own_pending_surgery_request`
  2. `test_doctor_cannot_access_or_order_for_another_doctors_restricted_patient_or_records`
  3. `test_or_coordinator_can_schedule_and_manage_operations_but_cannot_make_clinical_decisions`
  4. `test_or_staff_can_access_and_update_readiness_on_assigned_cases_only`
  5. `test_doctors_and_or_staff_cannot_modify_master_or_schedules_and_doctors_cannot_update_readiness`
  6. `test_operating_room_and_surgical_team_scheduling_conflicts_are_prevented`
  7. `test_invalid_status_transitions_are_rejected_server_side`
  8. `test_sidebar_visibility_for_doctor_or_coordinator_and_or_staff` (verifies `Clinical Reports` is the single entry point under `REPORTS` for `doctor`, `Surgery Reports` is under `REPORTS` / `MONITORING & REPORTS` for `or-coordinator`, `admin`, and `super_admin`, absent from `Surgery (SORS)`, and hidden from `or-staff`)
  9. `test_unrelated_roles_are_blocked_from_direct_sors_urls`
  10. `test_full_sors_lifecycle_records_audit_trail_in_activity_logs`

### 7.2 Full Application Test Suite
Command:
```bash
php artisan test
```
Result:
- **Status:** `PASSED` (**239 tests, 239 passed, 1,623 assertions, 0 failures**)

### 7.3 Frontend Production Asset Build
Command:
```bash
npm run build
```
Result:
- **Status:** `PASSED` (`public/build/manifest.json`, `app-DRfsTYCH.css`, `app-B50xrYjX.js` built cleanly with Vite v8.1.5).

---

## 8. Manual Verification Checklist

- [ ] **Doctor Flow:**
  1. Sign in as a `doctor` account.
  2. Expand **Surgery (SORS)** in the sidebar; confirm it displays **Overview**, **Surgery Requests**, **OR Schedules**, and **Surgery Calendar** (and no longer displays **Surgery Reports** inside the SORS group).
  3. Under the **REPORTS** sidebar section, click **Surgery Reports** (`/reports/surgery/activity`) and verify it highlights as `active` while **Surgery (SORS)** remains collapsed.
  4. Open **Surgery (SORS) -> Surgery Requests -> New Request**; confirm the patient dropdown lists only authorized/unassigned patients.
  5. Submit a `Pending` surgery request, then click **Edit** on the request detail page and verify clinical changes save properly.
  6. Open **OR Schedules** and **Surgery Calendar**; confirm only the doctor's own/assigned cases appear and no room-assignment or readiness controls are shown.
- [ ] **OR Coordinator Flow:**
  1. Sign in as `or-coordinator` (`or@hospital.com`).
  2. Verify sidebar displays `MAIN -> Dashboard`, `SURGERY & OPERATING ROOM (SORS) -> Surgery Requests, OR Schedules, Surgery Calendar`, and `REPORTS -> Surgery Reports`.
  3. Open a `Pending` surgery request and click **Schedule Surgery**.
  4. Attempt to book an overlapping time slot in the same Operating Room or with the same Surgical Team and verify validation blocks the conflict.
  5. Schedule the case into an available Operating Room with an assigned Surgical Team containing an `or-staff` member.
- [ ] **OR Staff Flow:**
  1. Sign in as an assigned `or-staff` account (`orstaff@hospital.com`).
  2. Verify the sidebar shows only `MAIN -> Dashboard` and `SURGERY & OPERATING ROOM (SORS) -> OR Schedules, Surgery Calendar` (no `Surgery Requests` or `Surgery Reports`).
  3. Verify the **OR Staff Assigned Cases & Readiness Center** dashboard displays assigned cases and readiness counts (`Pending Prep`, `Preparing`, `Ready`).
  4. Open the assigned schedule and update **OR Preparation & Readiness** from `Pending Prep` -> `Preparing` -> `Ready` with operational notes; verify the updated badge, timestamp, and user attribution.
- [ ] **Execution & Print Flow:**
  1. As `or-coordinator`, start the scheduled surgery (`Scheduled` -> `In Progress`) and complete it (`In Progress` -> `Completed`).
  2. Click **Print Schedule** (`/surgery/schedules/{id}/print`) and verify `SCH-xxxxx`, procedure name, duration, surgical team, and OR readiness render cleanly.

---

## 9. Unresolved Hospital-Client Decisions

1. **Intermediate Clinical/Department-Head Approval Status Before OR Scheduling:**
   - *Current State:* `surgery_requests.status` uses `ENUM('Pending', 'Scheduled', 'In Progress', 'Completed', 'Cancelled')`. Incoming requests remain `Pending` until reviewed and scheduled (`Pending` -> `Scheduled`) or cancelled (`Pending` -> `Cancelled`) by `or-coordinator`.
   - *Client Decision Needed:* Confirm whether the hospital requires a formal intermediate status (e.g., `Approved` / `Cleared for Scheduling` signed off by a Chief of Surgery or Anesthesiology clearance) prior to OR Coordinator scheduling.
2. **Default Authority for Recording Actual Surgery Start (`actual_start`) and Completion (`actual_end`):**
   - *Current State:* Controlled by the `sors.procedures.execute` permission, which is granted to `or-coordinator` by default and checked in `SurgerySchedulePolicy::updateStatus`. Assigned `or-staff` can record readiness updates (`Pending Prep` / `Preparing` / `Ready`) by default, and can execute `start`/`complete` if hospital governance attaches `sors.procedures.execute` to the `or-staff` role.
   - *Client Decision Needed:* Confirm whether circulating/scrub nurses (`or-staff`) should hold `sors.procedures.execute` by default in production or if intraoperative timestamps must remain exclusively under `or-coordinator` sign-off.
3. **Emergency Walk-In Patient Ordering Window:**
   - *Current State:* Doctors can order surgery for patients with an existing clinical relationship with that doctor or newly registered patients who do not yet have another doctor's exclusive assignment.
   - *Client Decision Needed:* Confirm whether emergency/on-call surgeons require a dedicated override reason prompt to order emergency procedures for patients admitted under another attending physician.

---

## 10. Known Limitations

- **Individual Staff Conflict Across Multiple Surgical Teams:** Conflict detection checks Operating Room overlap and `SurgicalTeam` overlap (`findTeamConflict`). If the same individual nurse or anesthesiologist is a member of two different `SurgicalTeam` records booked simultaneously in two different rooms, team-ID conflict detection will only flag a conflict if the same `surgical_team_id` is used. Adding cross-team member-level conflict checks is a recommended future enhancement if staff belong to multiple concurrent teams.
- **Postoperative Clinical Notes:** SORS tracks scheduling, OR preparation readiness, and intraoperative timestamps (`actual_start`, `actual_end`). Detailed postoperative surgical dictation/operative reports are not yet a separate entity in SORS.

