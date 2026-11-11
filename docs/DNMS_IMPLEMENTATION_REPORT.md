# DNMS Implementation Report

**Module:** Diet and Nutrition Management System · **Baseline:** [DNMS_REQUIREMENTS_AUDIT.md](./DNMS_REQUIREMENTS_AUDIT.md)

## Outcome

Doctors own the clinical diet order; dietitians own nutrition assessment and diet-plan authoring; Admin / Super Admin are read-only. Authorization is enforced by routes, form requests, policies, and controller actions — not just sidebar visibility.

## Roles

* Reused `doctor` and `dietitian` (no duplicate roles; verified by test).
* `dietary-staff` **not implemented** — meal preparation/delivery is outside the current DNMS scope (documented in the audit §6).

## Files Changed / Added

| Type | File |
|---|---|
| Migration | `database/migrations/2026_11_06_000002_dnms_role_workflow_additive.php` |
| SQL patch | `database/schema/dnms_role_workflow_additive.sql`, `database/schema/ditc_hms_schema.sql` (columns) |
| Policies | `app/Policies/DietRequestPolicy.php`, `app/Policies/DietPlanPolicy.php` (registered in `AppServiceProvider`) |
| Models | `DietRequest` (`STATUSES`, `scopeVisibleTo`), `DietPlan` (new fillables/casts/relations, view accessors, `scopeVisibleTo`) |
| Requests | `StoreDietRequestRequest` (doctor only), `StoreDietPlanRequest` (dietitian only, `assessment_notes`) |
| Controllers | `Diet\DietRequestController`, `Diet\DietPlanController`, `DashboardController::diet`, `ReportService` diet methods |
| Routes | `routes/web.php` DNMS block |
| Views | `partials/_sidebar`, `dashboard/diet`, `diet/requests/{index,show}`, `diet/plans/{create,edit,show,print}` |
| Tests | `tests/Feature/DnmsWorkflowAuthorizationTest.php` |

## Key Behaviour

* **Doctor:** creates requests only for authorized patients (`Patient::isAuthorizedForDoctor`); sees only own requests/plans; edits/cancels own `Pending` requests with no plan.
* **Dietitian:** views all requests; creates a plan (with nutrition assessment) only for a `Pending` request without an existing plan (row-locked transaction, validation error instead of SQL 500); revises plans (`Active → Revised`), completes them (`completed_by`/`completed_at`), or removes uncompleted ones (request reverts to `Pending`). Cannot modify or cancel the doctor's order.
* **Admin / Super Admin:** read-only list/detail/print/dashboard/reports; every mutation returns 403.
* **Completed plans** are immutable; they cannot be edited, re-completed or deleted.
* **Audit trail:** `Diet Request Created/Updated/Cancelled`, `Diet Plan Created/Updated/Completed/Removed`, `Nutrition Assessment Recorded/Updated`.
* **Scoping:** `visibleTo()` applied to list pages, dashboard stats/trends, and nutrition reports.

## Defects Fixed Along the Way

* Missing `DietPlan` accessors referenced by views (`plan_no`, `patient`, `diet_type`, macros, `instructions`).
* Blank Age/Sex on request detail.
* Revised plans could not be completed/edited from the UI.
* Dietitian sidebar header and missing Nutrition Reports links.

## Database

Additive and idempotent; applied to both `newdb` and `ditc_hms`. No `migrate:fresh`, `DROP` or `TRUNCATE` was used.

## Verification

| Check | Result |
|---|---|
| `DnmsWorkflowAuthorizationTest` | 20 passed, 184 assertions |
| Full `php artisan test` | 244 passed, 1,511 assertions |
| `npm run build` | Succeeded |

## Test Coverage Map

1. Role/permission matrix and no duplicate roles
2. Doctor request creation (+ activity log)
3. Patient authorization / cross-doctor IDOR
4. Doctor ownership (view/edit/update/cancel; index scope)
5. Dietitian request access, assessment, plan creation, revision, completion
6. Dietitian/Admin/Super Admin/other doctor clinical-modification blocks
7. Plan visibility and print authorization
8. Dashboard/report scoping
9. Sidebar visibility; direct URL blocks for non-DNMS roles
10. Status-transition validation; completed-plan immutability; activity logging

## Known Limitations / Follow-ups

* `dietary-staff` and meal preparation/delivery are a future scope item.
* `dnms.*` permission slugs are seeded and bound for administration/auditing; runtime enforcement uses role-based policies consistent with the existing SORS approach.
