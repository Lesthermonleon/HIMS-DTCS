# DNMS Requirements Audit — Role-Based Workflow

**Project:** DTCS HIMS · **Module:** Diet and Nutrition Management System (DNMS)
**Scope of audit:** routes/middleware, models, controllers, form requests, policies/gates, RBAC tables, lifecycles, dashboard, sidebar, reports, activity logs, schema, tests.

---

## 1. Role Inventory

| Role | Slug | Exists? | Decision |
|---|---|---|---|
| Doctor | `doctor` | Yes (`roles.id = 2`, `doctor.dashboard`) | Reused |
| Dietitian / Nutritionist | `dietitian` | Yes (`roles.id = 7`, `diet.dashboard`) | Reused — **no duplicate created** |
| Dietary Staff | `dietary-staff` | No | **Not created — outside current project scope** (see §6) |

The custom RBAC (`roles`, `permissions`, `permission_role`, `role_user`) is retained; Spatie is not used.

## 2. Existing Functionality

* **Models:** `DietRequest` (statuses `Pending / Active / Completed / Cancelled`), `DietPlan` (`Active / Revised / Completed`, one plan per request via unique index), `MealSchedule` (read-only relation on a plan).
* **Controllers:** `Diet\DietRequestController`, `Diet\DietPlanController`.
* **Routes:** `/diet/dashboard`, `/diet/requests` (resource), `/diet/plans` (resource + `print` + `complete`), `/reports/diet/{activity,active,completed}`.
* **UI:** DNMS dashboard, request/plan list/detail/create/edit views, printable diet plan, sidebar entries, nutrition reports through `ReportService`.
* **Activity logs:** present for request create/update/cancel only.

## 3. Findings — Security and Authorization Gaps

| # | Gap | Severity |
|---|---|---|
| 1 | No `DietRequestPolicy` / `DietPlanPolicy`; zero `DNMS` rows in `permissions`. | High |
| 2 | `DietRequestController::create()` listed **all** patients and `store()` did not call `Patient::isAuthorizedForDoctor()` → a doctor could order a diet for another doctor's restricted patient. | High |
| 3 | `show/edit/update/destroy` on requests had **no ownership or role check**. Any dietitian, admin, super admin or other doctor could read, edit or cancel a physician's diagnosis, allergies, restrictions and clinical notes by direct URL. | High |
| 4 | `DietPlanController::index/show/print` were unscoped → Doctor A could read and print Doctor B's patient plans. | High |
| 5 | `admin` was in the mutation middleware of plans/requests (clinical authority from an administrative role). | High |
| 6 | Dashboard (`DashboardController::diet`) and reports (`ReportService::diet*`) were unscoped → cross-doctor data leakage. | Medium |
| 7 | `DietPlanController::store` did not verify the request was `Pending` or lacked a plan → duplicate plan produced an unhandled SQL 500; plans could be attached to Cancelled requests. | Medium |
| 8 | `update()` did not block `Completed` plans (only `edit()` did); `complete()` accepted already-completed plans. | Medium |
| 9 | Plan create/update/complete/delete wrote **no** activity log; no actor/timestamp attribution for update or completion. | Medium |
| 10 | Request-number generation (`count()+1`) is not collision safe. | Low |

## 4. Findings — Functional Defects

* Views referenced `DietPlan` attributes that did not exist: `plan_no`, `patient`, `diet_type`, `calories`, `protein`, `carbohydrates`, `fats`, `instructions` (dashboard, plan index/show/edit/print). Now provided as accessors.
* Request detail view used non-existent `Patient::age` / `Patient::sex` (rendered blank). Fixed to use `date_of_birth` / `gender`.
* Plan detail actions were hidden for `Revised` plans (condition was `status === 'Active'`), so a revised plan could never be completed from the UI.
* Sidebar header for `dietitian` rendered the generic `CLINICAL`; no Nutrition Reports entry for doctors/admin groups.
* No structured nutrition assessment field, though Dietitian responsibilities require recording assessments and recommendations.

## 5. Missing Functionality (Addressed)

* Granular DNMS permissions and least-privilege role bindings.
* Policies enforced in controllers, form requests and views.
* Nutrition assessment notes with creator/updater/completer attribution.
* Complete activity logging for the lifecycle.
* Scoped dashboard / reports.

## 6. `dietary-staff` — Scope Decision

Inspection shows DNMS is a **clinical dietetics** workflow: `diet_requests` → `diet_plans`. `meal_schedules` exists only as a read-only relationship displayed on a plan; there are no meal preparation, tray assembly, delivery, or serving controllers, routes, or views, and existing dashboard/sidebar tests assume the two-controller workflow. Per the brief ("Only implement this role if meal preparation and delivery are within the approved DNMS scope"), `dietary-staff` is **documented as outside current scope** rather than adding unrequested complexity. It can be added later as a hospital-client expansion by reusing the permission pattern introduced here.

## 7. Implemented Changes (Summary)

| Area | Change |
|---|---|
| Schema | Additive: `diet_plans.assessment_notes`, `updated_by`, `completed_by`, `completed_at` (migration `2026_11_06_000002_dnms_role_workflow_additive.php`, SQL `database/schema/dnms_role_workflow_additive.sql`, and base schema updated). Applied to `newdb` and `ditc_hms`. |
| RBAC | 10 permissions in module `DNMS`; bindings below. |
| Policies | `DietRequestPolicy`, `DietPlanPolicy` registered in `AppServiceProvider`. |
| Controllers | Policy `authorize()` on every action; patient authorization on create/update; transactional plan creation with row lock; transition validation; activity logging. |
| Routes | Mutations restricted to `doctor` (requests) and `dietitian` (plans); `admin`/`super_admin` read-only. |
| Scoping | `DietRequest::visibleTo()`, `DietPlan::visibleTo()` used by index, dashboard, and reports. |
| UI | Assessment field/section on plan form/show/print; `@can` gating; sidebar label + Nutrition Reports links. |

### Permission matrix

| Permission | doctor | dietitian | admin | super_admin |
|---|:-:|:-:|:-:|:-:|
| `dnms.requests.view` | ✔ | ✔ | ✔ | – |
| `dnms.requests.create` | ✔ | | | |
| `dnms.requests.manage` (own pending) | ✔ | | | |
| `dnms.assessments.create` | | ✔ | | |
| `dnms.assessments.update` | | ✔ | | |
| `dnms.plans.view` | ✔ | ✔ | ✔ | – |
| `dnms.plans.create` | | ✔ | | |
| `dnms.plans.update` | | ✔ | | |
| `dnms.plans.complete` | | ✔ | | |
| `dnms.reports.view` | ✔ | ✔ | ✔ | – |

`super_admin` receives route-level read access only (consistent with the existing DNMS dashboard) and no clinical permissions.

### Lifecycles

* **DietRequest:** `Pending → Active` (plan created) `→ Completed` (plan completed); `Pending → Cancelled` (originating doctor only, no plan). Plan deletion reverts `Active → Pending`.
* **DietPlan:** `Active → Revised` (dietitian edit) `→ Completed` (terminal and immutable).

## 8. Residual Notes

* Existing `diet_requests.request_no` values in seeded data use `DIET-YYYY-NNNN`; new requests use `DR-YYYY-NNNN` (unchanged convention from the controller).
* Patient ownership relies on the existing `Patient::isAuthorizedForDoctor()` rule; no patient master-record CRUD was introduced.

