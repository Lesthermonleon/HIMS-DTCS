# DTCS HIMS — Admin Dashboard Audit & Architectural Alignment

**Repository:** `Lesthermonleon/DTCS`  
**System:** DTCS Hospital Information Management System (Diagnostics, Treatment & Clinical Services)  
**Target Role:** System Administrator (`admin`)  
**Audit Date:** 2026-11-03  
**Status:** Complete — Baseline Audit before Implementation  

---

## 1. Executive Summary & Architectural Scope

The DTCS Hospital Information Management System is architecturally centered around five core clinical service modules:
1. **LIS** — Laboratory Information System
2. **RIS** — Radiology and Imaging System
3. **PMS** — Pharmacy Management System
4. **SORS** — Surgery and Operating Room Scheduler
5. **DNMS** — Diet and Nutrition Management System

### Architectural Role Boundaries: Admin vs. Super Admin

| Dimension | Super Admin (`super_admin`) | Admin (`admin`) |
|---|---|---|
| **Primary Mandate** | Master system authority, governance, security configuration, database backup/recovery | Routine operational monitoring, day-to-day oversight of DTCS modules and personnel |
| **User Account Management** | Full lifecycle authority across all 10 roles (Super Admin, Admin, Doctor, Staff roles, Patient) | Operational user oversight, restricted non-privileged user edits, password resets, account unlocks |
| **Privilege Escalation** | Master authority | **STRICTLY BLOCKED** — cannot create, edit, or assign `super_admin` or `admin` roles |
| **Roles & Permissions** | Full authority to manage permissions | Read-only inspection of role assignments |
| **System Settings** | Full authority over system configuration, session security, OTP policy | No access to global system security settings |
| **Patient Information** | Upstream master record authority (external subsystem) | Read-only directory view for clinical-administrative correlation; **NO patient CRUD** |
| **Clinical Decision-Making** | No clinical override | No clinical override — operational administrative monitoring only |
| **Audit Logs** | Complete Audit Vault access (all security, governance, clinical logs) | Operational and system activity log viewer |

The purpose of this audit is to rigorously examine every component of the existing Admin Dashboard (`resources/views/dashboard/admin.blade.php`), its backing controller (`App\Http\Controllers\DashboardController::admin`), database queries, status definitions, security boundaries, and sidebar navigation to ensure complete alignment with the DTCS subsystem responsibilities.

---

## 2. Existing Controller, Routes, and View Inventory

### Primary Endpoints and Controllers
- **Dashboard Route:** `GET /admin/dashboard`
- **Route Name:** `admin.dashboard`
- **Controller Action:** `App\Http\Controllers\DashboardController::admin`
- **Middleware Pipeline:** `['auth', 'otp.verified', 'role:admin']`
- **Blade Template:** `resources/views/dashboard/admin.blade.php`

### Supporting Admin Endpoints Referenced from Dashboard
- User Accounts: `GET /admin/users` (`admin.users.index`) via `App\Http\Controllers\Admin\UserController::index`
- Patient Directory: `GET /patients` (`patients.index`) via `App\Http\Controllers\PatientController::index`
- Audit Logs: `GET /admin/audit-logs` (`admin.audit-logs.index`) via `App\Http\Controllers\Admin\AuditLogController::index`
- Operations Monitor: `GET /admin/operations` (`admin.operations.index`) via `App\Http\Controllers\Admin\OperationsMonitorController::index`
- Clinical Module Dashboards:
  - LIS: `GET /lab/dashboard` (`lab.dashboard`)
  - RIS: `GET /radiology/dashboard` (`radiology.dashboard`)
  - PMS: `GET /pharmacy/dashboard` (`pharmacy.dashboard`)
  - SORS: `GET /surgery/dashboard` (`surgery.dashboard`)
  - DNMS: `GET /diet/dashboard` (`diet.dashboard`)

---

## 3. Component-by-Component Data Source & Integrity Audit

Each component of the Admin Dashboard was audited for real database connectivity, query efficiency, data integrity, potential mock/hardcoded values, and role alignment.

```
+---------------------------------------------------------------------------------------------------------+
|                                    EXISTING ADMIN DASHBOARD COMPONENTS                                  |
+------------------------------------+------------------------------------+-------------------------------+
| Component Name                     | Current Data Source / Controller   | Finding / Data Status         |
+------------------------------------+------------------------------------+-------------------------------+
| 1. System Overview KPI Cards       | User & Patient Eloquent counts     | Real DB data; needs alignment |
| 2. Critical System/Security Alerts | User (lockouts) & ActivityLog      | Real DB data; functional      |
| 3. Administrative Tools            | Static tiles linking to routes     | Includes out-of-scope setting |
| 4. Clinical Module Overview        | Count of pending clinical requests | Real DB data; status check ok |
| 5. Service Request Trend (6m/12m)  | Monthly aggregates across 5 tables | Real DB data; responsive      |
| 6. Requests by Module (Bar)        | All-time table counts              | Real DB data; responsive      |
| 7. Global Request Status (Donut)   | Status sums across 5 tables        | Real DB data; mapped properly |
| 8. System Activity Log (Line)      | Daily ActivityLog aggregates (7d)  | Real DB data; responsive      |
| 9. User Distribution by Role       | Role::withCount('users')           | Real DB data; responsive      |
| 10. User Presence & Activity       | active_session_id & ActivityLog    | Real DB data; privacy-aware   |
| 11. Patient Directory Overview     | Patient::latest()->take(5)         | Real DB data; strictly read   |
+------------------------------------+------------------------------------+-------------------------------+
```

---

### Component 1: Account and Patient Overview (KPI Summary Cards)

- **View Location:** `resources/views/dashboard/admin.blade.php` (Lines 34–106)
- **Controller Method:** `DashboardController::getAdminDashboardData()` (Lines 43–61)
- **Current Data Sources:**
  - `total_users`: `User::count()`
  - `active_users`: `User::where('is_active', true)->count()`
  - `total_patients`: `\App\Models\Patient::count()`
  - `inactive_users`: `User::where('is_active', false)->whereNull('locked_at')->count()`
- **Audit Findings:**
  1. All counts connect directly to the MySQL database tables (`users` and `patients`). There is zero mock or hardcoded data.
  2. **Inconsistency Found:** `active_users` counts all users with `is_active = true`, without checking if they are currently locked (`whereNull('locked_at')`). Conversely, `inactive_users` excludes locked accounts (`whereNull('locked_at')`). This means locked accounts with `is_active = true` are counted as active users even though they cannot log in.
  3. **Alignment Fix:** 
     - Active Users must be defined as `User::where('is_active', true)->whereNull('locked_at')->count()`.
     - Inactive Users: `User::where('is_active', false)->whereNull('locked_at')->count()`.
     - Locked Users: `User::whereNotNull('locked_at')->count()`.
     - This guarantees the invariant: `Active Users + Inactive Users + Locked Users = Total Users`.
  4. Patient count connects to the real `patients` table. It does not expose sensitive medical data on the dashboard card.
- **Decision:** **KEEP & IMPROVE** (Standardize status query filters to ensure mathematical consistency with account lockout logic).

---

### Component 2: Critical System and Security Alerts

- **View Location:** `resources/views/dashboard/admin.blade.php` (Lines 110–153)
- **Controller Method:** `DashboardController::buildAdminAlerts()` (Lines 330–420)
- **Current Data Sources:**
  - **Account Lockouts (Critical):** `User::with('roles')->whereNotNull('locked_at')->orderBy('locked_at', 'desc')->get()`
  - **Excessive Failed Logins (Warning/Critical):** `User::with('roles')->whereNotIn('id', $lockedUserIds)->where(fn($q) => $q->where('failed_attempts', '>', 0)->orWhere(fn($q2) => $q2->whereNotNull('lockout_until')->where('lockout_until', '>', now())))`
  - **Security Events (Warning):** `ActivityLog::with('user')->whereIn('action', ['Role Assignment Changed', 'Account Unlocked', 'Password Reset'])->where('created_at', '>=', now()->subHours(24))->orderBy('created_at', 'desc')->take(5)->get()`
- **Audit Findings:**
  1. The alerts connect strictly to real system events in the `users` and `activity_logs` tables.
  2. Alerts are properly classified into `danger` (critical lockouts and repeated login breaches) and `warning` (administrative security modifications).
  3. The action routes point to `route('admin.users.index')` and `route('admin.audit-logs.index')`, both of which are accessible to the `admin` role.
  4. Empty state is supported: when no alerts exist, a green shield with "No system alerts. Everything is operating normally. Security status is healthy." is rendered.
- **Decision:** **KEEP & ENHANCE** (Ensure alerts only expose authorized information, avoid disclosing sensitive session tokens or OTP secrets, and maintain clear visual indicators).

---

### Component 3: Administrative Tools / Quick Actions

- **View Location:** `resources/views/dashboard/admin.blade.php` (Lines 158–226)
- **Current Tiles:**
  1. User Management (`route('admin.users.index')`)
  2. Patient Directory (`route('patients.index')`)
  3. Audit Logs (`route('admin.audit-logs.index')`)
  4. System Settings (`route('settings.index')`)
- **Audit Findings:**
  1. **User Management:** Legitimate Admin tool. Points to `admin.users.index`. The `UserController` already enforces strict anti-privilege escalation (ordinary Admin cannot assign `super_admin` or `admin`, and cannot edit protected privileged accounts).
  2. **Patient Directory:** Points to `patients.index`. Strictly read-only for Admin. No create, edit, or master-record mutation exists.
  3. **Audit Logs:** Points to `admin.audit-logs.index`. Authorized read-only log inspection.
  4. **System Settings:** **OUT OF SCOPE / MISCONFIGURED.** It currently points to `route('settings.index')`, which is either an empty stub or routes toward Super Admin system configuration (`super-admin.settings.index`). System configuration belongs exclusively to Super Admin.
- **Decision:** **MODIFY & RESTRICT**
  - **REMOVE** the "System Settings" shortcut tile.
  - **ADD** legitimate DTCS operational shortcuts: "Operations Monitor" (`route('admin.operations.index')`) and "Reports Hub" (`route('reports.index')`).
  - Keep User Management, Patient Directory, and System Audit Logs tiles.

---

### Component 4: Clinical Module Operational Overview

- **View Location:** `resources/views/dashboard/admin.blade.php` (Lines 228–290)
- **Controller Method:** `DashboardController::getAdminDashboardData()` (Lines 71–77)
- **Current Data Sources & Module Status Definitions in Database:**
  - **LIS (Laboratory):** `LabRequest::where('status', 'Pending')->count()`  
    *DB ENUM definition:* `enum('Pending','In Progress','Completed','Cancelled')`  
    *Pending state:* `Pending` is the actual operational status for new requests requiring specimen collection or analysis.
  - **RIS (Radiology):** `RadiologyRequest::where('status', 'Pending')->count()`  
    *DB ENUM definition:* `enum('Pending','Scheduled','In Progress','Completed','Cancelled')`  
    *Pending state:* `Pending` represents unscheduled, pending exam requests.
  - **PMS (Pharmacy):** `Prescription::where('status', 'Pending')->count()`  
    *DB ENUM definition:* `enum('Pending','Verified','Partially Dispensed','Dispensed','Cancelled')`  
    *Pending state:* `Pending` represents doctor orders awaiting pharmacist verification and dispensing.
  - **SORS (Surgery):** `SurgeryRequest::where('status', 'Pending')->count()`  
    *DB ENUM definition:* `enum('Pending','Scheduled','In Progress','Completed','Cancelled')`  
    *Pending state:* `Pending` represents surgery booking requests awaiting OR scheduling.
  - **DNMS (Nutrition/Diet):** `DietRequest::where('status', 'Pending')->count()`  
    *DB ENUM definition:* `enum('Pending','Active','Completed','Cancelled')`  
    *Pending state:* `Pending` represents diet consult requests awaiting clinical nutritionist plan creation.
- **Audit Findings:**
  1. All 5 modules are represented.
  2. Status definitions in `DashboardController` match the database schema exactly. Completed (`Completed`, `Dispensed`), cancelled (`Cancelled`), or scheduled/active records are not miscounted as pending.
  3. Module links point to authorized overview dashboards (`lab.dashboard`, `radiology.dashboard`, `pharmacy.dashboard`, `surgery.dashboard`, `diet.dashboard`).
- **Decision:** **KEEP & POLISH** (Maintain verified pending counts; ensure links route to module overviews).

---

### Component 5: Operational Reports and Charts

- **View Location:** `resources/views/dashboard/admin.blade.php` (Lines 293–378, 542–656)
- **Controller Method:** `DashboardController::getAdminDashboardData()` (Lines 88–127)
- **Charts Evaluated:**
  1. **Service Request Trend (Line Chart):**
     - Monthly aggregated volume across all five clinical modules (`LabRequest`, `RadiologyRequest`, `Prescription`, `SurgeryRequest`, `DietRequest`).
     - Supports 6-Month and 12-Month period switching via client-side Alpine/JS dropdown.
     - Fully backed by database `created_at` timestamp range queries.
  2. **Requests by Module (Bar Chart):**
     - Total request count per module: LIS, RIS, PMS, SORS, DNMS.
     - Real database counts: `LabRequest::count()`, `RadiologyRequest::count()`, `Prescription::count()`, `SurgeryRequest::count()`, `DietRequest::count()`.
  3. **Global Request Status (Donut Chart):**
     - Aggregated status breakdown across all five modules:
       - Pending: LIS `Pending` + RIS `Pending` + PMS `Pending` + SORS `Pending` + DNMS `Pending`
       - Completed: LIS `Completed` + RIS `Completed` + PMS `Dispensed` + SORS `Completed` + DNMS `Completed`
       - Cancelled: LIS `Cancelled` + RIS `Cancelled` + PMS `Cancelled` + SORS `Cancelled` + DNMS `Cancelled`
     - Correctly maps PMS `Dispensed` to Completed.
  4. **System Activity Log (Line Chart):**
     - 7-day daily activity counts from `ActivityLog::whereDate('created_at', ...)->count()`.
  5. **User Distribution by Role (Donut Chart):**
     - Account distribution across all active roles via `Role::withCount('users')->get()`.
- **Audit Findings:**
  1. All charts utilize real database data. There are no static arrays or synthetic demo values.
  2. All 5 DTCS clinical modules are included in trends and distributions.
  3. Empty state handling: If any dataset is empty (e.g., zero requests), the chart renders cleanly without throwing JavaScript errors.
- **Decision:** **KEEP & IMPROVE** (Ensure full responsiveness, dark theme compatibility, and seamless empty state rendering).

---

### Component 6: System Activity & User Presence

- **View Location:** `resources/views/dashboard/admin.blade.php` (Lines 381–460)
- **Controller Method:** `DashboardController::getUserPresenceData()` (Lines 135–213)
- **Current Data Sources:**
  - Evaluates users with `is_active = true`.
  - Determines online status via `user.active_session_id != null` and `last_activity_at >= now() - 5 minutes`.
  - Determines recent activity via `last_activity_at >= now() - 3 hours`.
  - Displays list of up to 8 users with name, primary role, and relative activity time.
- **Audit Findings:**
  1. Leverages real database columns (`active_session_id`, `last_activity_at`) established by single-session and activity tracking middleware.
  2. Zero exposure of credentials, passwords, session tokens, or OTP codes.
  3. Provides genuine visibility into active operators across hospital shifts.
- **Decision:** **KEEP & REFINE** (Maintain single-session security, preserve privacy safeguards).

---

### Component 7: Patient Information and Directory Overview (Read-Only)

- **View Location:** `resources/views/dashboard/admin.blade.php` (Lines 462–529)
- **Controller Method:** `DashboardController::getAdminDashboardData()` (Line 86)
- **Current Data Sources:** `Patient::latest('created_at')->take(5)->get()`
- **Audit Findings:**
  1. Table displays 5 recent patients: Patient ID (`patient_no`), Full Name, Gender/DOB, Patient Type/Ward, Registration Date.
  2. Action column contains only a "View Profile" link pointing to `route('patients.show', $patient)`.
  3. **No Create, Edit, Delete, or Archive functionality is present.**
  4. Upstream boundary respected: Patient master records are managed externally; DTCS holds read-only records.
- **Decision:** **KEEP AS READ-ONLY SNAPSHOT** (Ensure no patient CRUD actions or master-record forms are introduced).

---

## 4. Super Admin vs. Admin Permission & Access Enforcement Matrix

The architectural boundary must be strictly enforced on the backend via middleware, policies, and gate checks, not just UI visibility.

```
+------------------------------------+------------------+-----------------------+------------------------------------------+
| Feature / Action                   | Admin Access     | Super Admin Access    | Backend Enforcement Mechanism            |
+------------------------------------+------------------+-----------------------+------------------------------------------+
| View Admin Dashboard               | YES (200)        | NO (403)              | Route middleware: role:admin             |
| View Super Admin Dashboard         | NO (403)         | YES (200)             | Route middleware: role:super_admin       |
| Monitor LIS, RIS, PMS, SORS, DNMS  | YES              | YES                   | Role-based route middleware              |
| Operations Monitor                 | YES              | YES                   | Route middleware: role:admin,super_admin |
| Clinical Reports Hub               | YES              | YES                   | Role-based reporting middleware          |
| Read-only Patient Directory        | YES              | YES                   | PatientPolicy (viewAny, view)            |
| Patient Master Record CRUD         | NO (External)    | NO (External)         | Policy & external subsystem boundary     |
| View Permitted Audit Logs          | YES (Admin logs) | YES (Audit Vault)     | Controller query filtering / middleware  |
| Full User Account Lifecycle        | RESTRICTED       | FULL (All 10 roles)   | UserManagementController (super_admin)   |
| Create User Account                | NO (Super Admin) | YES                   | Controller authorization / route guard   |
| Assign Roles / Permissions         | READ-ONLY        | FULL                  | RoleController / UserController checks   |
| Create / Promote Super Admin       | STRICTLY BLOCKED | Controlled process    | UserController anti-escalation guard     |
| Global System & Security Settings  | NO (403)         | YES                   | Route middleware: role:super_admin       |
| Database Backup & Recovery         | NO (403)         | YES                   | Route middleware: role:super_admin       |
+------------------------------------+------------------+-----------------------+------------------------------------------+
```

---

## 5. Duplicate Functionality & Misaligned Sidebar Items

### Sidebar Alignment Audit
The Admin sidebar (`resources/views/partials/_sidebar.blade.php`) previously contained links that duplicated Super Admin authority or introduced out-of-scope hospital administration:

1. **`Hospital Settings` (`admin.hospital-settings.index`):** 
   - *Audit finding:* Duplicates Super Admin system configuration.
   - *Recommendation:* Restrict/remove from standard operational Admin menu.
2. **`Doctor Directory` & `Staff Directory`:**
   - *Audit finding:* Subsets of user management that duplicate the primary user accounts interface.
3. **`Appointments & Queue`:**
   - *Audit finding:* Outpatient clinic appointment scheduling outside core DTCS diagnostics & treatment scope.
4. **`Patient Portal Accounts` (`admin.patients.intake`):**
   - *Audit finding:* Patient account creation belongs to external portal registration.

### Aligned Admin Sidebar Structure
```
MAIN
└── Dashboard (admin.dashboard)

DTCS CLINICAL MODULES
├── Laboratory (LIS) (Overview, Requests, Results)
├── Radiology (RIS) (Overview, Requests, Reports)
├── Pharmacy (PMS) (Overview, Prescriptions, Dispensing, Medicine Availability)
├── Surgery (SORS) (Overview, Requests, Schedules, Calendar)
└── Nutrition (DNMS) (Overview, Requests, Plans)

PATIENTS
└── Patient Directory (Read-only: patients.index)

MONITORING & REPORTS
├── Operations Monitor (admin.operations.index)
├── Reports & Analytics (reports.index)
└── System Audit Logs (admin.audit-logs.index)
```

---

## 6. Recommendations Summary Table

```
+------------------------------------+----------------+-------------------------------------------------------------+
| Component / Functionality          | Recommendation | Rationale & Implementation Details                          |
+------------------------------------+----------------+-------------------------------------------------------------+
| Account & Patient Overview KPIs    | KEEP & MODIFY  | Standardize active/inactive/locked account filter logic     |
| System & Security Alerts           | KEEP & ENHANCE | Real lockouts & failed logins; clear severity visual states |
| Administrative Tools               | MODIFY         | Remove System Settings tile; add Operations & Reports links |
| Clinical Module Overview           | KEEP           | Verified real pending counts for LIS, RIS, PMS, SORS, DNMS  |
| Operational Charts & Reports       | KEEP & POLISH  | Ensure responsive layout, empty state safety, dark theme    |
| Activity & User Presence           | KEEP           | Privacy-safe online/recent activity from actual sessions    |
| Patient Directory Overview Table   | KEEP (READ)    | Recent patients list with view-only links; no CRUD          |
| Direct URL Protection              | RESTRICT       | Ensure Admin cannot access /super-admin/* routes            |
+------------------------------------+----------------+-------------------------------------------------------------+
```

---

## 7. Implementation Roadmap

1. **Backend Adjustments (`DashboardController.php`):**
   - Update `stats` array in `getAdminDashboardData()` so `active_users` checks `whereNull('locked_at')`.
   - Ensure all module counts remain strictly backed by real database records.
   - Verify all chart query aggregates handle zero-record situations gracefully.
2. **Frontend Redesign (`resources/views/dashboard/admin.blade.php`):**
   - Re-organize layout into the 6 designated logical sections:
     1. Account & Patient Overview
     2. System & Security Monitoring
     3. Clinical Module Operational Overview
     4. Operational Reports & Charts
     5. Activity Monitoring
     6. Patient Information (Read-only Overview)
   - Replace the out-of-scope "System Settings" tile with "Operations Monitor" and "Reports Hub".
   - Maintain the DTCS hospital visual identity (teal, deep blue, slate colors; Bootstrap 5 / Tailwind harmonious tokens; zero green left-borders).
3. **Sidebar Alignment (`resources/views/partials/_sidebar.blade.php`):**
   - Ensure Admin sidebar strictly features MAIN, DTCS CLINICAL MODULES, PATIENTS, and MONITORING & REPORTS.
4. **Automated Testing & Verification:**
   - Execute existing feature tests (`AdminDashboardTest`, `SuperAdminArchitectureTest`, `PanelistFiveUserTypesTest`).
   - Add new tests in `AdminDashboardTest` verifying statistical consistency, module pending counts, alert queries, and role boundary protection.
   - Execute `npm run build` and verify assets compile cleanly.
