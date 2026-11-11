# DNMS Doctor-Managed Workflow Audit

**Project:** DTCS HIMS (`Lesthermonleon/DTCS`)  
**Module:** Diet and Nutrition Management System (DNMS)  
**Target Architecture:** `Doctor → DNMS → Patient Diet Plan`  
**Audit Date:** 2026-11-06  
**Prior References:** `docs/DNMS_REQUIREMENTS_AUDIT.md`, `docs/DNMS_IMPLEMENTATION_REPORT.md`

---

## 1. Executive Summary & Architectural Shift

The Diet and Nutrition Management System (DNMS) remains one of the five core DTCS HIMS clinical modules alongside LIS, RIS, PMS, and SORS. Previously, DNMS split clinical responsibility across two roles:
- **Doctor (`doctor`):** created and managed pending `DietRequest` records (`diet_requests`) and viewed plans/reports for their own patients.
- **Dietitian (`dietitian`):** reviewed all `DietRequest` records, recorded nutrition assessments, and created, revised, completed, or removed `DietPlan` records (`diet_plans`).

Under the **Doctor-Managed Workflow Redesign**, a separate `dietitian` user account is no longer required for DNMS operations:
1. **Doctor (`doctor`)** assumes full end-to-end clinical responsibility in DNMS for their authorized patients:
   - Creating, viewing, editing, and cancelling eligible `Pending` diet requests (`DietRequest`).
   - Performing nutrition assessments and creating, revising (`Active → Revised`), completing (`Active/Revised → Completed`), or reverting uncompleted individualized diet plans (`DietPlan`) for their own authorized patients.
   - Viewing patient nutrition history, the DNMS dashboard, and scoped nutrition reports.
   - Strict cross-doctor isolation remains enforced: a Doctor cannot view or modify another Doctor's patients, diet requests, or diet plans.
2. **Admin (`admin`) & Super Admin (`super_admin`)** retain read-only monitoring access to the DNMS dashboard, diet requests, diet plans, and nutrition reports, with zero clinical mutation authority (`403 Forbidden` on all create/edit/update/complete/delete endpoints).
3. **Dietitian (`dietitian`)** is removed from active DNMS route middleware, policies, form requests, model visibility scopes, report access matrices, and sidebar navigation, while preserving existing database rows (`roles`, `users`, historical `diet_plans`, `activity_logs`, `patients`) so historical attribution and foreign-key integrity remain 100% intact.
4. **Dietary Staff (`dietary-staff`)** and meal preparation/delivery workflows remain outside scope and are not created.

---

## 2. Audit of Existing `dietitian` Dependencies

### 2.1 Live Database State (`newdb` & `ditc_hms`)

| Artifact | Current State in `newdb` / `ditc_hms` | Impact & Preservation Requirement |
|---|---|---|
| `roles` (`slug = 'dietitian'`) | `id = 7`, `name = 'Dietitian / Nutritionist'`, `dashboard_route = 'diet.dashboard'` | **Preserve row** in `roles` table (referenced by existing `role_user` rows and non-DNMS account-provisioning tests `SuperAdminUserManagementTest` & `PatientAuthorizationTest`). |
| `users` (`id = 7`, `dietitian@dtcs.com`) | `Rosa Mendoza`, linked to `role_id = 7` | **Preserve user account**; referenced by foreign key `diet_plans_dietitian_id_foreign` (`ON DELETE RESTRICT`) on historical `diet_plans` (`id = 1, 2, 3` in `newdb`). |
| `diet_plans` (`id = 1, 2, 3` in `newdb`) | Linked to `diet_request_id = 2, 3, 5` (`doctor_id = 2`) with `dietitian_id = 7` (`Active`, `Completed`, `Revised`) | **Preserve all historical rows**. Ownership in `DietPlanPolicy` and `DietPlan::scopeVisibleTo` is resolved through `$dietPlan->dietRequest->doctor_id === $user->id` so the ordering physician retains seamless access to historical plans on their own requests. |
| `permission_role` (`DNMS` module) | `doctor` holds 5 permissions (`requests.*`, `plans.view`, `reports.view`); `dietitian` holds 8 permissions (`requests.view`, `assessments.*`, `plans.*`, `reports.view`); `admin` holds 3 read-only permissions | **Update bindings**: grant all 10 `dnms.*` permissions to `doctor`; revoke `dnms.*` permissions from `dietitian`; keep `admin` on read-only `dnms.*.view` permissions. |

### 2.2 Codebase Touchpoints Requiring Modification

| File | Current `dietitian` Coupling | Required Change |
|---|---|---|
| `routes/web.php` (lines 476–515, 554) | `diet.dashboard`, `diet.requests.index/show`, `diet.plans.index/show/print`, `reports.diet.*` include `dietitian` in `role:` middleware; `diet.plans.create/store/edit/update/destroy/complete` require `role:dietitian`. | Remove `dietitian` from all DNMS and diet report routes. Change `diet.plans` mutation routes (`create`, `store`, `edit`, `update`, `destroy`, `complete`) from `role:dietitian` to `role:doctor`. Keep `admin` and `super_admin` on read-only routes only. |
| `app/Policies/DietRequestPolicy.php` | `viewAny()` and `view()` allow `dietitian` to view all requests across doctors. | Remove `dietitian` from `viewAny()` and `view()`. Enforce patient authorization (`$dietRequest->patient->isAuthorizedForDoctor($user)`) alongside `doctor_id === $user->id` for doctors. |
| `app/Policies/DietPlanPolicy.php` | `viewAny()` and `view()` allow `dietitian`; `create()`, `update()`, `complete()`, `delete()` require `hasRole('dietitian')`. | Remove `dietitian`. Allow `doctor` in `create(?DietRequest)`, `update()`, `complete()`, `delete()` strictly when the doctor owns the parent `DietRequest` (`doctor_id === $user->id`) and is authorized for the patient (`isAuthorizedForDoctor($user)`), and the plan is not `Completed`. |
| `app/Http/Requests/StoreDietPlanRequest.php` | `authorize()` checks `$this->user()?->hasRole('dietitian')`. | Change `authorize()` to check `(bool) $this->user()?->hasRole('doctor')`. |
| `app/Http/Controllers/Diet/DietPlanController.php` | `create()` lists all `Pending` requests across the hospital; `store()` does not check `DietPlanPolicy::create` against the specific `DietRequest`. | Scope `create()` `$pendingRequests` to `DietRequest::visibleTo($user)` and authorize any preselected `?diet_request_id`. In `store()`, authorize `$this->authorize('create', [DietPlan::class, $dietRequest])` before creating the plan so a doctor cannot create a plan for another doctor's request or unauthorized patient. Set `dietitian_id => Auth::id()` (acting Doctor) to satisfy the non-null FK while recording the authoring clinician. |
| `app/Models/DietRequest.php` & `app/Models/DietPlan.php` | `scopeVisibleTo()` grants full visibility to `dietitian` alongside `admin` and `super_admin`. | Remove `dietitian` from `scopeVisibleTo()`. Only `admin` and `super_admin` have hospital-wide read scope; `doctor` is scoped to `doctor_id = $user->id`; all other roles (including `dietitian`) receive an empty scope (`1 = 0`). |
| `app/Services/ReportService.php` & `app/Http/Controllers/ReportController.php` | `ROLE_ACCESS['diet']` includes `'dietitian'`; `ReportController::index()` redirects `dietitian` to `reports.diet.activity`; `dietActive`/`dietCompleted` filter dropdown only lists `dietitian` role users. | Remove `'dietitian'` from `ROLE_ACCESS['diet']` and `ReportController::index()`. Update clinician filter query in `dietActive`/`dietCompleted` to include doctors and any historical plan authors. |
| `resources/views/partials/_sidebar.blade.php` | Renders a `@elseif($role === 'dietitian')` section header and `@if($role === 'dietitian')` navigation links (`Diet Requests`, `Diet Plans`, `Nutrition Reports`). | Remove the `dietitian` sidebar navigation block while keeping DNMS (`Overview`, `Diet Requests`, `Diet Plans`, `Nutrition Reports`) in the Doctor and Admin sidebars. |
| `resources/views/dashboard/diet.blade.php` & `resources/views/diet/**` | `dashboard/diet.blade.php` badge says `Dietitian Operational Workspace`; `diet/requests/index.blade.php`, `diet/requests/show.blade.php`, and `diet/plans/index.blade.php` check `hasRole('dietitian')` to show "Create Diet Plan" / "Edit Diet Plan" buttons. | Update workspace badge to clinical DNMS wording; replace `hasRole('dietitian')` checks in Blade templates with `@can('create', ...)` and `@can('update', ...)` policy directives so owning Doctors see plan creation/management actions and Admin/Super Admin remain read-only. |
| `database/migrations/` & `database/schema/` | `2026_11_06_000002_dnms_role_workflow_additive.php` and `dnms_role_workflow_additive.sql` bind plan creation/update/complete permissions to `dietitian`. | Add additive idempotent migration `2026_11_06_000003_dnms_doctor_managed_workflow.php` and update `database/schema/dnms_role_workflow_additive.sql` so `doctor` receives all 10 `dnms.*` permissions and `dietitian` has all `dnms.*` permissions revoked. |

---

## 3. Risks & Mitigation Strategy

1. **Foreign-Key Integrity on `diet_plans.dietitian_id`:**
   - *Risk:* `diet_plans.dietitian_id` is `BIGINT UNSIGNED NOT NULL` with `FOREIGN KEY (dietitian_id) REFERENCES users(id) ON DELETE RESTRICT`. Dropping or renaming the column or deleting user `id = 7` (`Rosa Mendoza`) would break existing seeded data and violate non-destructive database rules.
   - *Mitigation:* Keep `diet_plans.dietitian_id` intact in the schema as the authoring user FK (`users.id`). When a Doctor creates a `DietPlan`, store `$doctor->id` in `dietitian_id` (and `updated_by`). Historical plans created by `user_id = 7` continue to resolve `$dietPlan->dietitian->name` (`Rosa Mendoza`), while new plans created by a Doctor resolve `$dietPlan->dietitian->name` to the authoring Doctor.
2. **Historical Diet Plans Access by Ordering Doctor:**
   - *Risk:* If `DietPlanPolicy` checked `$dietPlan->dietitian_id === $user->id`, then historical plans in `newdb` (where `dietitian_id = 7` and `dietRequest->doctor_id = 2`) would become uneditable by Doctor `id = 2`.
   - *Mitigation:* `DietPlanPolicy` authorizes the Doctor via `$dietPlan->dietRequest->doctor_id === $user->id` and `$dietPlan->dietRequest->patient->isAuthorizedForDoctor($user)`. Thus, a Doctor owns and manages both newly created plans and any historical plans linked to their own patients' diet requests, while remaining strictly blocked from another Doctor's requests and plans.
3. **Cross-Module Test Compatibility:**
   - *Risk:* `SuperAdminUserManagementTest` and `PatientAuthorizationTest` iterate over `roles` including `'dietitian'`.
   - *Mitigation:* Preserve the `dietitian` row in `roles` and `UserManagementController::SUPPORTED_ROLES`, while revoking its `dnms.*` permissions and removing `dietitian` from all DNMS routes, policies, and sidebar links.

---

## 4. Migration & Implementation Plan

1. **Database & RBAC:** Create and run additive migration `2026_11_06_000003_dnms_doctor_managed_workflow.php` on both `newdb` and `ditc_hms` (and update `database/schema/dnms_role_workflow_additive.sql`) to assign all 10 `dnms.*` permissions to `doctor` and revoke `dnms.*` permissions from `dietitian`.
2. **Policies & Models:** Update `DietRequestPolicy`, `DietPlanPolicy`, `DietRequest::scopeVisibleTo`, and `DietPlan::scopeVisibleTo` so only `doctor` has clinical mutation authority (scoped to own requests & authorized patients) and only `admin`/`super_admin` have hospital-wide read-only visibility.
3. **Controllers, FormRequests & Routes:** Update `StoreDietPlanRequest`, `DietPlanController`, `ReportService`, `ReportController`, and `routes/web.php`.
4. **Sidebar & Blade Views:** Remove `dietitian` navigation from `resources/views/partials/_sidebar.blade.php` and update `dashboard/diet.blade.php`, `diet/requests/{index,show}.blade.php`, `diet/plans/{index,show,print}.blade.php`, and `reports/diet/{activity,active,completed}.blade.php`.
5. **Automated Verification:** Update `tests/Feature/DnmsWorkflowAuthorizationTest.php` to cover all 15 required scenarios, run the full PHPUnit test suite and `npm run build`, and produce `docs/DNMS_DOCTOR_MANAGED_WORKFLOW_IMPLEMENTATION_REPORT.md`.

