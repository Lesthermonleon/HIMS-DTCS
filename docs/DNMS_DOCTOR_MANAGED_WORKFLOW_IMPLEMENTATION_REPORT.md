# DNMS Doctor-Managed Workflow Implementation Report

**Project:** DTCS HIMS (`Lesthermonleon/DTCS`)  
**Module:** Diet and Nutrition Management System (DNMS)  
**Architecture:** `Doctor → DNMS → Patient Diet Plan`  
**Audit Reference:** [`docs/DNMS_DOCTOR_MANAGED_WORKFLOW_AUDIT.md`](./DNMS_DOCTOR_MANAGED_WORKFLOW_AUDIT.md)  
**Implementation Date:** 2026-11-06

---

## 1. Initial Audit Findings

Before modifying the codebase, an audit of the DNMS module and live databases (`newdb` and `ditc_hms`) identified the following:

1. **Split Clinical Workflow Across Two Roles:**
   - `DietRequestController` and `DietRequestPolicy` allowed `doctor` to create, edit, and cancel `Pending` diet requests (`diet_requests`), while `dietitian` had hospital-wide read access.
   - `DietPlanController`, `DietPlanPolicy`, `StoreDietPlanRequest`, and `routes/web.php` restricted all `DietPlan` creation, revision, completion, and deletion to `role:dietitian`.
2. **Database & Historical Record Dependencies:**
   - Both `newdb` and `ditc_hms` contain `roles.id = 7` (`slug = 'dietitian'`) and `users.id = 7` (`Rosa Mendoza`, `dietitian@dtcs.com`).
   - `newdb` contains 3 historical `diet_plans` rows (`id = 1, 2, 3` linked to `diet_request_id = 2, 3, 5`, all ordered by `doctor_id = 2`) where `dietitian_id = 7` with a `NOT NULL` foreign key (`diet_plans_dietitian_id_foreign` referencing `users.id ON DELETE RESTRICT`).
   - Unrelated test suites (`SuperAdminUserManagementTest`, `PatientAuthorizationTest`) assert the presence of the `dietitian` role slug in `roles` and `UserManagementController::SUPPORTED_ROLES`.
3. **Navigation, Scoping & Report Couplings:**
   - `resources/views/partials/_sidebar.blade.php` rendered a dedicated `DIET & NUTRITION (DNMS)` block for `dietitian`.
   - `DietRequest::scopeVisibleTo`, `DietPlan::scopeVisibleTo`, and `ReportService::ROLE_ACCESS['diet']` granted hospital-wide visibility to `dietitian`.
   - `DietPlanController::create()` loaded all `Pending` requests across the hospital rather than scoping to the authenticated Doctor's requests.

---

## 2. Files Modified and Added

| Category | File | Summary of Change |
|---|---|---|
| **Audit Documentation** | `docs/DNMS_DOCTOR_MANAGED_WORKFLOW_AUDIT.md` | Added pre-implementation audit of `dietitian` touchpoints, risks, and migration strategy. |
| **Implementation Report** | `docs/DNMS_DOCTOR_MANAGED_WORKFLOW_IMPLEMENTATION_REPORT.md` | Added final implementation report and verification checklist. |
| **Database Migration** | `database/migrations/2026_11_06_000003_dnms_doctor_managed_workflow.php` | Additive, idempotent migration assigning all 10 `dnms.*` permissions to `doctor` and revoking `dnms.*` permissions from `dietitian` and `super_admin`. |
| **Canonical SQL Patch** | `database/schema/dnms_role_workflow_additive.sql` | Updated idempotent SQL patch to reflect the Doctor-managed RBAC permission bindings. |
| **Models** | `app/Models/DietRequest.php` | Updated `scopeVisibleTo()` so only `doctor` (own requests) and `admin`/`super_admin` (read-only monitoring) have visibility; `dietitian` receives empty scope. |
| **Models** | `app/Models/DietPlan.php` | Updated `scopeVisibleTo()` so only `doctor` (plans on own requests) and `admin`/`super_admin` (read-only monitoring) have visibility. |
| **Policies** | `app/Policies/DietRequestPolicy.php` | Removed `dietitian` access; enforced `doctor` ownership and `Patient::isAuthorizedForDoctor` on `view`, `create`, `update`, and `delete`. |
| **Policies** | `app/Policies/DietPlanPolicy.php` | Replaced `dietitian` with `doctor` on `create(?DietRequest)`, `update`, `complete`, and `delete`, strictly scoped to the doctor's own requests and authorized patients; preserved `admin`/`super_admin` read-only `viewAny`/`view`. |
| **Form Request** | `app/Http/Requests/StoreDietPlanRequest.php` | Changed `authorize()` from `hasRole('dietitian')` to `hasRole('doctor')`. |
| **Controller** | `app/Http/Controllers/Diet/DietPlanController.php` | Scoped `create()` pending requests via `DietRequest::visibleTo($user)` and authorized preselected `diet_request_id`; enforced `$this->authorize('create', [DietPlan::class, $targetRequest])` in `store()`; recorded acting Doctor ID on creation, revision, and completion. |
| **Routes** | `routes/web.php` | Removed `dietitian` from all `diet.*` and `reports.diet.*` route middleware; changed `diet.plans` mutation routes (`create`, `store`, `edit`, `update`, `destroy`, `complete`) to `role:doctor`. |
| **Reports** | `app/Services/ReportService.php`, `app/Http/Controllers/ReportController.php` | Removed `dietitian` from `ROLE_ACCESS['diet']` and `ReportController::index()`; updated clinician filter in `dietActive`/`dietCompleted` to include doctors and historical plan authors. |
| **Views & Sidebar** | `resources/views/partials/_sidebar.blade.php`, `resources/views/dashboard/diet.blade.php`, `resources/views/diet/requests/{index,show}.blade.php`, `resources/views/diet/plans/index.blade.php` | Removed `dietitian`-specific sidebar links; updated DNMS workspace badge; replaced `hasRole('dietitian')` checks with `@can` policy gates so owning Doctors see plan creation/editing controls. |
| **Feature Tests** | `tests/Feature/DnmsWorkflowAuthorizationTest.php` | Rebuilt comprehensive test suite (14 tests, 199 assertions) covering all 15 required Doctor-managed workflow and security scenarios. |

---

## 3. Previous vs. Revised Role Responsibilities

| Role | Previous Responsibilities | Revised Responsibilities (Doctor-Managed Workflow) |
|---|---|---|
| **Doctor (`doctor`)** | Created, edited, and cancelled own `Pending` diet requests; read-only access to own patients' diet plans and nutrition reports. Could not create, edit, or complete diet plans. | **Full clinical DNMS ownership for authorized patients:** creates, edits, and cancels own `Pending` diet requests; records nutrition assessments; creates, revises (`Active → Revised`), completes (`Completed`), and reverts uncompleted diet plans for own authorized patients; views own DNMS dashboard and scoped nutrition reports. Cannot access another Doctor's patients, requests, or plans. |
| **Dietitian (`dietitian`)** | Viewed all hospital diet requests; recorded nutrition assessments; created, revised, completed, and deleted diet plans; accessed DNMS dashboard and nutrition reports. | **No longer required for DNMS operations.** All `dnms.*` permissions, route access, policy gates, and sidebar links are revoked (`403 Forbidden`). Existing role row, user account (`Rosa Mendoza`), and historical `diet_plans` records remain preserved in the database. |
| **Admin (`admin`)** | Read-only monitoring of DNMS dashboard, requests, plans, and reports (`dnms.*.view`). | **Unchanged:** read-only monitoring of DNMS dashboard, requests, plans, and reports; all clinical mutation routes return `403 Forbidden`. |
| **Super Admin (`super_admin`)** | Read-only route access to DNMS dashboard, requests, plans, and reports; no clinical mutation authority. | **Unchanged:** read-only route access to DNMS dashboard, requests, plans, and reports; no clinical permissions or mutation authority (`403 Forbidden`). |
| **Dietary Staff (`dietary-staff`)** | Not created (outside project scope). | **Not created** (meal preparation/delivery outside project scope). |

---

## 4. Authorization and Workflow Changes

1. **Route Middleware (`routes/web.php`):**
   - `GET /diet/dashboard`, `GET /diet/requests`, `GET /diet/requests/{dietRequest}`, `GET /diet/plans`, `GET /diet/plans/{dietPlan}`, `GET /diet/plans/{dietPlan}/print`, and `GET /reports/diet/{activity,active,completed}` require `role:super_admin,admin,doctor`.
   - `diet.requests.{create,store,edit,update,destroy}`, `diet.plans.{create,store,edit,update,destroy}`, and `PATCH /diet/plans/{dietPlan}/complete` strictly require `role:doctor`.
2. **Policy & Patient Ownership Enforcement (`DietRequestPolicy`, `DietPlanPolicy`):**
   - `DietRequestPolicy` verifies `(int) $dietRequest->doctor_id === (int) $user->id` and `$patient->isAuthorizedForDoctor($user)`.
   - `DietPlanPolicy` verifies that the authenticated user has `role:doctor`, that `$dietPlan->dietRequest->doctor_id === $user->id`, and that `$dietPlan->dietRequest->patient->isAuthorizedForDoctor($user)`.
   - `DietPlanController::create()` and `store()` authorize `[DietPlan::class, $targetRequest]` before displaying preselected requests or persisting a plan, preventing cross-doctor IDOR on `diet_request_id`.
3. **Lifecycle & Concurrency Protections Preserved:**
   - `DietPlanController::store()` executes inside `DB::transaction()` with `lockForUpdate()` on the parent `DietRequest`, verifying `status === 'Pending'` and `! $dietRequest->dietPlan()->exists()` (returning a clean validation error on `diet_request_id` rather than a duplicate-key SQL error).
   - Creating a plan transitions the `DietRequest` from `Pending → Active`.
   - Updating an active plan transitions `DietPlan` to `Revised` and records `updated_by = Auth::id()`.
   - Completing a plan transitions both `DietPlan` and `DietRequest` to `Completed` and records `completed_by = Auth::id()` and `completed_at = now()`.
   - `Completed` plans are immutable (`update`, `complete`, and `delete` all return `403 Forbidden`).
   - Reverting an uncompleted plan (`destroy`) returns the `DietRequest` to `Pending`.
4. **Activity Logging:**
   - Every clinical action (`Diet Request Created`, `Diet Request Updated`, `Diet Request Cancelled`, `Diet Plan Created`, `Diet Plan Updated`, `Diet Plan Completed`, `Diet Plan Removed`, `Nutrition Assessment Recorded`, `Nutrition Assessment Updated`) logs `user_id = Auth::id()`, identifying the acting Doctor.

---

## 5. Database Changes

Applied non-destructively and idempotently via `database/migrations/2026_11_06_000003_dnms_doctor_managed_workflow.php` to both `newdb` (development) and `ditc_hms` (test):
- Bound all 10 `DNMS` permissions (`dnms.requests.view`, `dnms.requests.create`, `dnms.requests.manage`, `dnms.assessments.create`, `dnms.assessments.update`, `dnms.plans.view`, `dnms.plans.create`, `dnms.plans.update`, `dnms.plans.complete`, `dnms.reports.view`) to `doctor`.
- Retained `dnms.requests.view`, `dnms.plans.view`, and `dnms.reports.view` on `admin`.
- Removed `DNMS` permission bindings from `dietitian` and `super_admin` in `permission_role`.
- **Zero destructive operations:** no `migrate:fresh`, `db:wipe`, `DROP TABLE`, or `TRUNCATE` was executed.

---

## 6. Existing Data Preservation Verification

- **Roles & Accounts:** `roles.id = 7` (`slug = 'dietitian'`) and `users.id = 7` (`Rosa Mendoza`, `dietitian@dtcs.com`) remain intact in `newdb` and `ditc_hms`.
- **Historical Diet Requests & Plans:** All 5 seeded `diet_requests` (`DIET-2026-0001` through `DIET-2026-0005`) and all 3 historical `diet_plans` (`id = 1, 2, 3` with `dietitian_id = 7`) in `newdb` remain intact.
- **Ordering Physician Access to Historical Plans:** Because `DietPlanPolicy` and `DietPlan::scopeVisibleTo` resolve ownership through `$dietPlan->dietRequest->doctor_id === $user->id`, the ordering Doctor can view, print, and manage historical plans on their own requests while preserving `Rosa Mendoza` as the historical `dietitian_id` author.

---

## 7. Test Results and Assertion Counts

| Test Suite | Command | Tests | Assertions | Result |
|---|---|---:|---:|---|
| **DNMS Workflow & Authorization Suite** | `php artisan test --filter=DnmsWorkflowAuthorizationTest` | 14 | 199 | **PASSED** (14/14) |
| **Full Application Test Suite** | `php artisan test` | 238 | 1,526 | **PASSED** (238/238) |

---

## 8. Vite Build Results

```text
> npm run build
> vite build

vite v8.1.5 building client environment for production...
✓ 7 modules transformed.
public/build/manifest.json              0.33 kB │ gzip:  0.16 kB
public/build/assets/app-C3_B8kLt.css   40.43 kB │ gzip:  7.67 kB
public/build/assets/app-B50xrYjX.js   118.87 kB │ gzip: 36.65 kB
✓ built in 2.00s
```

---

## 9. Remaining Limitations

1. **Legacy Column Name (`diet_plans.dietitian_id`):** To preserve non-destructive schema compatibility and foreign-key integrity with historical records, `diet_plans.dietitian_id` is retained as the column storing the authoring clinician's `users.id` (populated with `Auth::id()` when a Doctor creates a plan).
2. **Meal Preparation & Delivery Out of Scope:** Consistent with project requirements, `dietary-staff` and kitchen tray/meal delivery workflows are not implemented.

---

## 10. Manual Verification Checklist

- [ ] Sign in as a **Doctor** (`doctor` role) and verify the sidebar under **CLINICAL MODULES → Nutrition (DNMS)** displays **Overview**, **Diet Requests**, **Diet Plans**, and **Nutrition Reports**.
- [ ] As a **Doctor**, create a new Diet Request for an authorized patient at `/diet/requests/create` and verify its status is `Pending` and `Diet Request Created` is recorded in Activity Logs.
- [ ] From `/diet/requests` or `/diet/requests/{id}`, click **Create Diet Plan / Draw Diet Plan**, enter Nutrition Assessment notes, caloric/macronutrient targets, and meal guidelines, and submit.
- [ ] Verify the new Diet Plan is `Active`, the parent Diet Request is `Active`, and both `Diet Plan Created` and `Nutrition Assessment Recorded` are logged under the Doctor's account.
- [ ] Edit the active Diet Plan at `/diet/plans/{id}/edit`, save changes, and verify the status transitions to `Revised` with `Diet Plan Updated` logged.
- [ ] Click **Complete Diet Plan** on `/diet/plans/{id}` and verify both the Diet Plan and Diet Request transition to `Completed` and edit/delete buttons are no longer available.
- [ ] Sign in as a **second Doctor** and confirm another Doctor's patients, diet requests, and diet plans do not appear in lists/reports and return `403 Forbidden` via direct URL.
- [ ] Sign in as **Admin** or **Super Admin** and confirm `/diet/dashboard`, `/diet/requests`, `/diet/plans`, and `/reports/diet/activity` are viewable in read-only mode while all create/edit/complete/delete routes return `403 Forbidden`.

