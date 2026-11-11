# SORS Role-Based Workflow Audit (`docs/SORS_REQUIREMENTS_AUDIT.md`)

**Project:** DTCS HIMS  
**Module:** Surgery and Operating Room Scheduler (SORS)  
**Repository:** `Lesthermonleon/DTCS`  
**Audit Date:** 2026-11-06  

---

## 1. Executive Summary

An exhaustive code and schema audit of the Surgery and Operating Room Scheduler (SORS) module in DTCS HIMS was conducted prior to implementation.

The existing SORS implementation provides foundational database tables (`operating_rooms`, `surgical_teams`, `surgical_team_members`, `surgery_requests`, `surgery_schedules`), basic request and schedule CRUD controllers, a FullCalendar view, and basic surgery reports. However, several **critical gaps** exist across role separation, record ownership (IDOR vulnerabilities), operating-room conflict detection, server-side status transition validation, OR preparation/readiness tracking, broken view templates, and automated test coverage:

1. **Role Availability (`or-coordinator` vs. `or-staff`):**
   - `or-coordinator` (**OR Coordinator**) **already exists** in the `roles` table and RBAC configuration (`Role.php`, `StaffAccountController`, `UserManagementController`). It will be **reused** without duplication.
   - `or-staff` (**OR Staff**) **does not exist** as a role slug yet, although the schema already includes `surgical_team_members` (`surgical_team_id`, `user_id`, `role_in_team`) to assign individual staff members to surgical teams. An additive, idempotent role `or-staff` ("OR Staff") and granular SORS permissions will be introduced to support OR Staff responsibilities cleanly while reusing `surgical_team_members` for case assignment.
2. **Cross-Doctor & Unassigned Staff Record Exposure (IDOR):**
   - `SurgeryRequestController::show()` lacks an ownership check, allowing Doctor A to view Doctor B's surgery requests via direct URL (`GET /surgery/requests/{id}`).
   - `SurgeryScheduleController::index()`, `show()`, `print()`, `calendar()`, and `calendarEvents()` return **all** hospital surgery schedules without scoping to the requesting/assigned doctor (`doctor`) or assigned surgical team member (`or-staff`).
   - `DashboardController::surgery()` and `ReportService` (`surgeryActivitySummary`, `surgeryActivityDetails`) expose hospital-wide surgery metrics and records to any doctor without scoping to that doctor's own requests/cases.
3. **Missing Operating-Room Double-Booking & Conflict Detection:**
   - `SurgeryScheduleController::store()` and `update()` do not check whether the selected `OperatingRoom` is already booked during an overlapping time window (`[scheduled_at, scheduled_at + duration_minutes]`), nor whether the room is `Under Maintenance` or inactive, nor whether the `SurgeryRequest` already has an active schedule.
4. **Unvalidated Status Transitions & Missing Readiness Workflow:**
   - `SurgeryScheduleController::start()` and `complete()` do not validate the current status before transitioning (e.g., a `Completed` or `Cancelled` schedule can be started again; a `Scheduled` case can be marked `Completed` without being `In Progress`).
   - `SurgeryRequestController::cancel()` does not block cancelling an already `Completed` or `In Progress` surgery, does not cancel/sync linked schedules, and does not record an `ActivityLog` entry.
   - There is no dedicated mechanism for `or-staff` or `or-coordinator` to update **OR Preparation / Readiness status** (`Pending Prep`, `Preparing`, `Ready`) and operational prep notes without full schedule-management authority.
5. **View & UI Discrepancies:**
   - `resources/views/surgery/requests/edit.blade.php` is referenced by `SurgeryRequestController::edit()` but **does not exist**, causing a 500 error when a doctor attempts to edit their pending surgery request.
   - `resources/views/surgery/schedules/print.blade.php` and `resources/views/dashboard/surgery.blade.php` reference non-existent model attributes (`$surgerySchedule->schedule_no`, `$surgerySchedule->operating_room`, `$surgerySchedule->scheduled_date`, `$surgerySchedule->coordinator`, `$req->surgery_type`).
   - `resources/views/surgery/schedules/index.blade.php` and `show.blade.php` render `Start` and `Complete` action buttons to `doctor` users even though the backend routes restrict them and doctors must not manage overall OR schedules or readiness.
   - `resources/views/partials/_sidebar.blade.php` places OR Coordinator links under a generic `CLINICAL` section header instead of organizing them under **MAIN** (`Dashboard`) and **SURGERY & OPERATING ROOM (SORS)** (`Surgery Requests`, `OR Schedules`, `Surgery Calendar`, `Surgery Reports`).

---

## 2. Inspection of Existing Implementation

### 2.1 Existing SORS Routes & Middleware (`routes/web.php`)

| HTTP Method & URI | Route Name | Controller Action | Current Middleware | Audit Finding |
|---|---|---|---|---|
| `GET /surgery/dashboard` | `surgery.dashboard` | `DashboardController@surgery` | `auth`, `role:super_admin,admin,doctor,or-coordinator` | Missing `or-staff`; controller returns unscoped hospital-wide data to `doctor`. |
| `GET /surgery/requests` | `surgery.requests.index` | `SurgeryRequestController@index` | `auth`, `role:super_admin,admin,doctor,or-coordinator` | Scoped for `doctor` (`doctor_id = Auth::id()`), unscoped for `or-coordinator`/`admin`. |
| `GET /surgery/requests/{surgeryRequest}` | `surgery.requests.show` | `SurgeryRequestController@show` | `auth`, `role:super_admin,admin,doctor,or-coordinator` | **Vulnerability:** No ownership check for `doctor`. Doctor A can view Doctor B's request. |
| `GET /surgery/requests/create` | `surgery.requests.create` | `SurgeryRequestController@create` | `auth`, `role:doctor` | Properly restricted to `doctor`; needs patient authorization scoping (`visibleTo`). |
| `POST /surgery/requests` | `surgery.requests.store` | `SurgeryRequestController@store` | `auth`, `role:doctor` | Properly restricted to `doctor`; needs check that doctor is authorized for the patient. |
| `GET /surgery/requests/{surgeryRequest}/edit` | `surgery.requests.edit` | `SurgeryRequestController@edit` | `auth`, `role:doctor` | Checks `doctor_id` & `Pending` status, **but view `surgery.requests.edit` is missing!** |
| `PUT /surgery/requests/{surgeryRequest}` | `surgery.requests.update` | `SurgeryRequestController@update` | `auth`, `role:doctor` | Checks `doctor_id` & `Pending` status. |
| `DELETE /surgery/requests/{surgeryRequest}` | `surgery.requests.destroy` | `SurgeryRequestController@destroy` | `auth`, `role:doctor` | Checks `doctor_id` & `Pending` status. |
| `PATCH /surgery/requests/{surgeryRequest}/cancel` | `surgery.requests.cancel` | `SurgeryRequestController@cancel` | `auth`, `role:super_admin,admin,doctor,or-coordinator` | Does not block cancelling `Completed`/`In Progress` requests; does not log to `ActivityLog`. |
| `GET /surgery/schedules` | `surgery.schedules.index` | `SurgeryScheduleController@index` | `auth`, `role:super_admin,admin,doctor,or-coordinator` | Missing `or-staff`; **no scoping** for `doctor` (sees all doctors' schedules) or `or-staff`. |
| `GET /surgery/schedules/{surgerySchedule}` | `surgery.schedules.show` | `SurgeryScheduleController@show` | `auth`, `role:super_admin,admin,doctor,or-coordinator` | Missing `or-staff`; **no ownership/assignment check** for `doctor` or `or-staff`. |
| `GET /surgery/schedules/{surgerySchedule}/print` | `surgery.schedules.print` | `SurgeryScheduleController@print` | `auth`, `role:super_admin,admin,doctor,or-coordinator` | Missing `or-staff`; **no ownership/assignment check**; template uses broken properties. |
| `GET /surgery/calendar` | `surgery.calendar` | `SurgeryScheduleController@calendar` | `auth`, `role:super_admin,admin,doctor,or-coordinator` | Missing `or-staff`. |
| `GET /surgery/calendar/events` | `surgery.calendar.events` | `SurgeryScheduleController@calendarEvents` | `auth`, `role:super_admin,admin,doctor,or-coordinator` | Missing `or-staff`; returns all hospital schedules to `doctor` and `or-staff` without scoping. |
| `GET /surgery/schedules/create` | `surgery.schedules.create` | `SurgeryScheduleController@create` | `auth`, `role:or-coordinator` | Restricted to `or-coordinator`. |
| `POST /surgery/schedules` | `surgery.schedules.store` | `SurgeryScheduleController@store` | `auth`, `role:or-coordinator` | Missing OR time-window conflict detection, room status check, and duplicate schedule check. |
| `GET /surgery/schedules/{surgerySchedule}/edit` | `surgery.schedules.edit` | `SurgeryScheduleController@edit` | `auth`, `role:or-coordinator` | Allows editing terminal (`Completed`/`Cancelled`) schedules. |
| `PUT /surgery/schedules/{surgerySchedule}` | `surgery.schedules.update` | `SurgeryScheduleController@update` | `auth`, `role:or-coordinator` | Missing conflict detection; allows invalid status transitions; lacks reschedule audit details. |
| `DELETE /surgery/schedules/{surgerySchedule}` | `surgery.schedules.destroy` | `SurgeryScheduleController@destroy` | `auth`, `role:or-coordinator` | Allows deleting `In Progress`/`Completed` schedules without status guard. |
| `PATCH /surgery/schedules/{surgerySchedule}/start` | `surgery.schedules.start` | `SurgeryScheduleController@start` | `auth`, `role:or-coordinator` | Missing status guard (`status === 'Scheduled'`); does not allow assigned `or-staff` operational update. |
| `PATCH /surgery/schedules/{surgerySchedule}/complete` | `surgery.schedules.complete` | `SurgeryScheduleController@complete` | `auth`, `role:or-coordinator` | Missing status guard (`status === 'In Progress'`); does not allow assigned `or-staff` operational update. |
| `GET /reports/surgery/*` | `reports.surgery.*` | `ReportController@surgery*` | `auth`, `role:super_admin,admin,doctor,or-coordinator,billing,inventory,pharmacy,laboratory,radiology,cashier` | Accessible to `doctor` and `or-coordinator`, but `ReportService` does not scope `doctor` queries to their own surgery cases. |

### 2.2 Models & Database Schema

- **`SurgeryRequest` (`app/Models/SurgeryRequest.php`):**
  - Table: `surgery_requests`
  - Columns: `id`, `request_no`, `patient_id`, `doctor_id`, `procedure_name`, `diagnosis`, `anesthesia_type` (`General`, `Regional`, `Local`, `Sedation`, `None`), `urgency` (`Elective`, `Urgent`, `Emergency`), `preferred_date`, `estimated_duration` (minutes), `status` (`Pending`, `Scheduled`, `In Progress`, `Completed`, `Cancelled`), `notes`, `created_at`, `updated_at`.
  - Relations: `patient()`, `doctor()`, `schedule()` (`hasOne(SurgerySchedule::class, 'surgery_request_id')`).
- **`SurgerySchedule` (`app/Models/SurgerySchedule.php`):**
  - Table: `surgery_schedules`
  - Columns in `ditc_hms_schema.sql`: `id`, `surgery_request_id`, `operating_room_id`, `surgical_team_id`, `scheduled_at`, `duration_minutes`, `status` (`Scheduled`, `In Progress`, `Completed`, `Cancelled`, `Postponed`), `actual_start`, `actual_end`, `notes`, `scheduled_by`, `created_at`, `updated_at`.
  - **Live Schema Discrepancy Found:** In the live `newdb` and `ditc_hms` databases, `actual_start` and `actual_end` are missing from `surgery_schedules` (causing `SurgeryScheduleController::start()` and `complete()` to fail with `Unknown column 'actual_start'`) and `surgical_team_id` is `NOT NULL` despite `StoreSurgeryScheduleRequest` allowing `nullable`.
  - **Readiness Tracking Gap:** Currently lacks dedicated columns for OR preparation/readiness status (`readiness_status`, `readiness_notes`, `readiness_updated_by`, `readiness_updated_at`). Adding these additive columns (with safe defaults) alongside `actual_start` and `actual_end` enables structured OR Readiness coordination by OR Staff and OR Coordinators without overwriting scheduling notes.
- **`OperatingRoom` (`app/Models/OperatingRoom.php`):**
  - Table: `operating_rooms`
  - Columns: `id`, `room_code`, `name`, `type`, `floor`, `status` (`Available`, `Occupied`, `Under Maintenance`), `description`, `is_active`, `created_at`, `updated_at`.
- **`SurgicalTeam` & `SurgicalTeamMember` (`app/Models/SurgicalTeam.php`, `app/Models/SurgicalTeamMember.php`):**
  - Tables: `surgical_teams` (`id`, `name`, `surgeon_id`, `anesthesiologist_id`, `is_active`) and `surgical_team_members` (`id`, `surgical_team_id`, `user_id`, `role_in_team`).
  - Provides the exact relational mechanism to link `or-staff` users (scrub nurses, circulating nurses, OR technicians) and surgeons to scheduled operations.

### 2.3 Policies, Gates, Roles, and Permissions

- **Existing Policies:** `PatientPolicy` and `RadiologyRequestPolicy` are registered in `AppServiceProvider.php`. **No `SurgeryRequestPolicy` or `SurgerySchedulePolicy` exists yet.**
- **Existing Roles:**
  - `doctor` (Doctor / Surgeon) — Exists (`id = 3`).
  - `or-coordinator` (OR Coordinator) — Exists (`id = 8`).
  - `or-staff` (OR Staff) — Does not exist yet in `roles`.
- **Existing Permissions:**
  - `panelist_redesign_additive.sql` seeded granular permissions for LIS, RIS, PMS, Clinical Care, Admin, and Super Admin, but **0 permissions exist for SORS**.

### 2.4 Activity Logs & Audit History

- `ActivityLog::record($action, $module, $description, $subject)` is used in `SurgeryRequestController` (`store`, `update`, `destroy`) and `SurgeryScheduleController` (`store`, `update`, `destroy`, `start`, `complete`).
- **Missing audit trails:**
  - `SurgeryRequestController::cancel()` does not call `ActivityLog::record()`.
  - Rescheduling or cancelling a `SurgerySchedule` does not distinguish between general edit, reschedule, and cancellation in `ActivityLog`.
  - Updating OR preparation/readiness has no action or `ActivityLog` entry.

### 2.5 Existing Automated Tests

- `tests/Feature/DoctorSidebarTest.php` verifies that a `doctor` sees the `Surgery` (`SORS`) dropdown in the sidebar with `surgery.dashboard`, `surgery.requests.index`, `surgery.schedules.index`, and `surgery.calendar`.
- `tests/Feature/RequirementsAlignmentTest.php` and `tests/Feature/PanelistFiveUserTypesTest.php` test general RBAC and radiology/lab/patient modules.
- **No dedicated SORS feature test suite (`SorsWorkflowAuthorizationTest`) exists yet.**

---

## 3. Proposed Changes & Implementation Specification

### 3.1 Roles & Granular SORS Permissions (Phase 2)

1. **Reuse `or-coordinator` and `doctor` roles.**
2. **Add `or-staff` ("OR Staff") role idempotently** (`slug = 'or-staff'`, `name = 'OR Staff'`), mapped to `surgery.dashboard` in `Role.php`, `StaffAccountController::STAFF_ROLE_SLUGS`, and `UserManagementController::SUPPORTED_ROLES`.
3. **Seed granular SORS permissions idempotently:**
   - `sors.requests.view` — View authorized surgery requests (`doctor`, `or-coordinator`, `admin`, `super_admin`)
   - `sors.requests.create` — Create and edit own pending surgery requests (`doctor`)
   - `sors.requests.manage` — Review incoming surgery requests and coordinate cancellations (`or-coordinator`, `admin`, `super_admin`)
   - `sors.schedules.view` — View authorized OR schedules and surgery calendar (`doctor`, `or-coordinator`, `or-staff`, `admin`, `super_admin`)
   - `sors.schedules.manage` — Schedule approved requests, assign ORs & teams, reschedule, postpone, or cancel operations (`or-coordinator`, `admin`, `super_admin`)
   - `sors.readiness.update` — Update OR preparation and readiness status/notes (`or-coordinator`, `or-staff`)
   - `sors.procedures.execute` — Record operational status transitions (`start`, `complete`) on authorized schedules (`or-coordinator`, `or-staff`)
   - `sors.reports.view` — Access authorized surgery reports (`doctor`, `or-coordinator`, `admin`, `super_admin`)

### 3.2 Dedicated Authorization Policies (`SurgeryRequestPolicy` & `SurgerySchedulePolicy`)

- **`SurgeryRequestPolicy`:**
  - `viewAny`: `doctor` (own requests), `or-coordinator` (all incoming requests), `admin`/`super_admin`. Denied for `or-staff`.
  - `view`: `doctor` only if `$surgeryRequest->doctor_id === $user->id` (or assigned surgeon on its schedule); `or-coordinator`, `admin`, `super_admin`. Denied for Doctor B and `or-staff`.
  - `create`: `doctor` only (and must be authorized to access the target patient via `PatientPolicy`). Denied for `or-coordinator` and `or-staff`.
  - `update` / `delete`: `doctor` only when `$surgeryRequest->doctor_id === $user->id` and `$surgeryRequest->status === 'Pending'`. Denied for `or-coordinator` and `or-staff` (protecting clinical details from non-surgeon modification).
  - `cancel`: `doctor` (only own `Pending` request) or `or-coordinator`/`admin`/`super_admin` (when status is `Pending` or `Scheduled`). Never allowed when `In Progress` or `Completed`.
- **`SurgerySchedulePolicy`:**
  - `viewAny`: `doctor`, `or-coordinator`, `or-staff`, `admin`, `super_admin`.
  - `view`:
    - `or-coordinator`, `admin`, `super_admin`: allowed for all schedules.
    - `doctor`: allowed **only** if `$schedule->surgeryRequest->doctor_id === $user->id`, OR `$schedule->surgicalTeam?->surgeon_id === $user->id`, OR `$schedule->surgicalTeam?->members()->where('user_id', $user->id)->exists()`.
    - `or-staff`: allowed **only** if `$schedule->surgicalTeam?->members()->where('user_id', $user->id)->exists()`.
  - `create` / `update` / `delete` / `reschedule` / `cancel`: `or-coordinator` (and `admin`/`super_admin`). Denied for `doctor` and `or-staff`.
  - `updateReadiness`: `or-coordinator` (any active schedule) and `or-staff` (only assigned schedules where status is `Scheduled` or `Postponed`). Denied for `doctor`.
  - `updateStatus` (`start` / `complete`): `or-coordinator` (any active schedule) and `or-staff` (only assigned schedules). Denied for `doctor`.

### 3.3 Workflow, Conflict Prevention & Status Validation (Phase 3 & Phase 5)

1. **Operating Room Double-Booking & Conflict Prevention:**
   - When creating or updating/rescheduling a `SurgerySchedule`, validate:
     - The selected `OperatingRoom` is active (`is_active = true`) and not `'Under Maintenance'`.
     - For new schedules, the `SurgeryRequest` must be in `'Pending'` status and must not already have an active schedule (`Scheduled` or `In Progress`).
     - No other active `SurgerySchedule` (`status IN ('Scheduled', 'In Progress')`) in the same `operating_room_id` overlaps the proposed `[scheduled_at, scheduled_at + duration_minutes]` window:
       $$\text{existing\_start} < \text{proposed\_end} \quad \text{AND} \quad \text{proposed\_start} < \text{existing\_end}$$
     - If a `surgical_team_id` is assigned, also verify that the same surgical team is not double-booked in another OR during the overlapping window.
2. **Server-Side Status Transition Enforcement:**
   - `SurgerySchedule::start()`: Allowed **only** when `status === 'Scheduled'`. Transitions schedule & request to `'In Progress'`, marks OR `'Occupied'`, sets `actual_start`, and records `ActivityLog`.
   - `SurgerySchedule::complete()`: Allowed **only** when `status === 'In Progress'`. Transitions schedule & request to `'Completed'`, marks OR `'Available'` (if no other in-progress surgery is in that room), sets `actual_end`, and records `ActivityLog`.
   - `SurgerySchedule::cancel()` / status change to `'Cancelled'`: Allowed **only** when `status` is `'Scheduled'` or `'Postponed'`. Syncs `SurgeryRequest` to `'Cancelled'` (or `'Pending'` if unscheduled via destroy), ensures OR availability is updated, and logs cancellation in `ActivityLog`.
   - Terminal states (`Completed`, `Cancelled`) are immutable: cannot be edited, deleted, started, completed, cancelled again, or have readiness updated.
3. **OR Preparation & Readiness Tracking:**
   - Additive columns on `surgery_schedules`:
     - `readiness_status` (`ENUM('Pending Prep', 'Preparing', 'Ready') NOT NULL DEFAULT 'Pending Prep'`)
     - `readiness_notes` (`TEXT NULL`)
     - `readiness_updated_by` (`BIGINT UNSIGNED NULL`)
     - `readiness_updated_at` (`TIMESTAMP NULL`)
   - Dedicated endpoint `PATCH /surgery/schedules/{surgerySchedule}/readiness` (`surgery.schedules.readiness`) allowing `or-coordinator` and assigned `or-staff` to update OR readiness and operational preparation notes with full `ActivityLog` auditing.

### 3.4 Sidebar & Navigation Organization (Phase 4)

- Organize SORS navigation in `resources/views/partials/_sidebar.blade.php` under:
  - **MAIN** -> `Dashboard`
  - **SURGERY & OPERATING ROOM (SORS)** ->
    - `Surgery Requests` (`doctor`, `or-coordinator`)
    - `OR Schedules` (`doctor`, `or-coordinator`, `or-staff`)
    - `Surgery Calendar` (`doctor`, `or-coordinator`, `or-staff`)
    - `Surgery Reports` (`doctor`, `or-coordinator`)
- Preserve existing `DoctorSidebarTest` expectations for the `doctor` sidebar while ensuring `or-coordinator` and `or-staff` have the exact SORS sidebar structure required.
