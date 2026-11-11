# DNMS Sidebar and Workflow Simplification Report

**Project:** DTCS HIMS (`Lesthermonleon/DTCS`)  
**Module:** Diet and Nutrition Management System (DNMS)  
**Architecture:** `Doctor → DNMS → Patient Diet Plan`  
**Prior References:** [`docs/DNMS_DOCTOR_MANAGED_WORKFLOW_AUDIT.md`](./DNMS_DOCTOR_MANAGED_WORKFLOW_AUDIT.md), [`docs/DNMS_DOCTOR_MANAGED_WORKFLOW_IMPLEMENTATION_REPORT.md`](./DNMS_DOCTOR_MANAGED_WORKFLOW_IMPLEMENTATION_REPORT.md)  
**Implementation Date:** 2026-11-06

---

## 1. Initial Audit Findings

Before modifying the application, an inspection of the DNMS routes, controllers, policies, views, sidebar, models, and test suite identified the following:

1. **Redundant Sidebar Navigation Item:**
   - Following the Doctor-managed workflow redesign, the Doctor is responsible for both clinical diet requests (`DietRequest`) and therapeutic diet plans (`DietPlan`).
   - However, `resources/views/partials/_sidebar.blade.php` still displayed four items under **Nutrition (DNMS)** for both Doctor and Admin:
     1. `Overview` (`diet.dashboard`)
     2. `Diet Requests` (`diet.requests.index`)
     3. `Diet Plans` (`diet.plans.index`)
     4. `Nutrition Reports` (`reports.diet.activity`)
   - Requiring the Doctor to switch between a separate **Diet Requests** page and **Diet Plans** page fragmented a single physician-owned clinical workflow.
2. **Opportunity for Workspace Consolidation in `Diet Plans`:**
   - `DietPlanController::index()` previously queried only `DietPlan` records (`Active` and `Completed` in the UI filter dropdown, omitting `Revised` and omitting `Pending` `DietRequest` records awaiting a plan).
   - Integrating `Pending` `DietRequest` records and workflow tabs (`All Plans & Requests`, `Pending Requests`, `Active Plans`, `Revised Plans`, `Completed Plans`) directly into `diet.plans.index` allows the Doctor to view pending diet orders, create new diet requests, record nutrition assessments, and create/revise/complete diet plans from one central workspace without duplicating backend controller or model logic.
3. **Status Badge Distinction:**
   - `App\Traits\HasStatusBadge` mapped `Revised` to the fallback `secondary` badge class instead of a distinct `info text-dark` indicator.

---

## 2. Files Inspected and Modified

### Files Inspected
- `routes/web.php`
- `app/Http/Controllers/Diet/DietRequestController.php`
- `app/Http/Controllers/Diet/DietPlanController.php`
- `app/Policies/DietRequestPolicy.php`
- `app/Policies/DietPlanPolicy.php`
- `app/Models/DietRequest.php`
- `app/Models/DietPlan.php`
- `app/Traits/HasStatusBadge.php`
- `resources/views/partials/_sidebar.blade.php`
- `resources/views/dashboard/diet.blade.php`
- `resources/views/diet/plans/{index,create,edit,show,print}.blade.php`
- `resources/views/diet/requests/{index,create,edit,show}.blade.php`
- `tests/Feature/DnmsWorkflowAuthorizationTest.php`
- `tests/Feature/DoctorSidebarTest.php`

### Files Modified / Added
| File | Change Summary |
|---|---|
| `resources/views/partials/_sidebar.blade.php` | Removed the separate **Diet Requests** (`route('diet.requests.index')`) link from the Doctor and Admin **Nutrition (DNMS)** sidebar groups; kept **Overview**, **Diet Plans**, and **Nutrition Reports**; kept **Diet Plans** highlighted when viewing/creating linked request records. |
| `app/Http/Controllers/Diet/DietPlanController.php` | Enhanced `index()` to load scoped `Pending` `DietRequest` records awaiting a diet plan (`$pendingRequests`), compute dynamic workflow tab counts (`all` = eligible pending requests + all visible plans, `pending`, `Active`, `Revised`, `Completed`) within the user's `visibleTo($user)` scope, eager-load `createdBy`, and support filtering across `Active`, `Revised`, and `Completed` plan statuses. |
| `app/Models/DietPlan.php` | Added `createdBy(): BelongsTo` relationship (`belongsTo(User::class, 'dietitian_id')`) alongside `dietitian()` so both historical Dietitian-created plans and new Doctor-created plans resolve their actual author cleanly without modifying historical rows. |
| `resources/views/diet/plans/index.blade.php` | Redesigned as the central DNMS workspace with workflow status tabs (`All Plans & Requests`, `Pending Requests`, `Active Plans`, `Revised Plans`, `Completed Plans`), action buttons (`New Diet Request`, `New Diet Plan`), a **Pending Requests Awaiting Diet Plan** table with direct `Create Plan` actions, and the **Therapeutic Diet Plans** table with a `Created By` column header (replacing `Clinician`), linked request numbers, and print/edit/view controls. |
| `resources/views/diet/plans/{show,print}.blade.php` & `resources/views/diet/requests/show.blade.php` | Updated plan author labels from `Assigned Dietitian` / `Clinical Dietitian` / `Plan Author` to `Created By`, preserving historical Dietitian attribution (`Rosa Mendoza`) on historical records and displaying the creating Doctor's name on Doctor-managed plans. |
| `resources/views/dashboard/diet.blade.php` | Updated the quick action header bar so the primary button navigates directly to the unified **Diet Plans Workspace** (`route('diet.plans.index')`) alongside **New Diet Request** (`@can('create', DietRequest::class)`). |
| `resources/views/diet/requests/{create,show}.blade.php` | Updated breadcrumbs and back/cancel links to return smoothly to the unified **Diet Plans** workspace (`route('diet.plans.index')`). |
| `app/Traits/HasStatusBadge.php` | Added `'Revised' => 'info text-dark'` so revised diet plans display a clear status badge distinct from `Pending` (`secondary`), `Active` (`success`), and `Completed` (`success`). |
| `tests/Feature/DoctorSidebarTest.php` | Updated DNMS sidebar assertions to verify `Overview`, `Diet Plans`, and `Nutrition Reports` are present and the separate `Diet Requests` link is absent. |
| `tests/Feature/DnmsWorkflowAuthorizationTest.php` | Expanded tests to verify dynamic `tabCounts` (`all = pending + Active + Revised + Completed`), `Created By` attribution for both historical Dietitian plans and new Doctor-created plans, pending requests visibility, status filtering (`Active`, `Revised`, `Completed`), and the simplified Doctor and Admin sidebars. |
| `docs/DNMS_WORKFLOW_SIMPLIFICATION_REPORT.md` | Created this implementation and verification report. |

---

## 3. Sidebar Changes

In `resources/views/partials/_sidebar.blade.php`, the separate **Diet Requests** (`route('diet.requests.index')`) navigation item was removed from both the Doctor and Admin **Nutrition (DNMS)** collapsible sidebar groups.

The **Nutrition (DNMS)** sidebar navigation now displays:
- **Overview** (`route('diet.dashboard')`)
- **Diet Plans** (`route('diet.plans.index')`)
- **Nutrition Reports** (`route('reports.diet.activity')`)

---

## 4. How Request Functionality Was Integrated into Diet Plans

`DietPlanController::index()` and `resources/views/diet/plans/index.blade.php` now serve as the unified DNMS workspace:

1. **Workflow Status Tabs with Dynamic Scoped Counts:**
   - All counts in `DietPlanController::index()` are calculated dynamically using `DietRequest::visibleTo($user)` and `DietPlan::visibleTo($user)` so Doctors only see counts for their own authorized patients and Admin / Super Admin see counts across all records:
     - **Pending Requests (`pending`):** `DietRequest::visibleTo($user)->where('status', 'Pending')->whereDoesntHave('dietPlan')->count()` — counts only eligible `Pending` requests without an existing `DietPlan` (never double-counting requests that already have a plan or cancelled requests).
     - **Active Plans (`Active`):** `(clone $basePlansQuery)->where('status', 'Active')->count()`.
     - **Revised Plans (`Revised`):** `(clone $basePlansQuery)->where('status', 'Revised')->count()`.
     - **Completed Plans (`Completed`):** `(clone $basePlansQuery)->where('status', 'Completed')->count()`.
     - **All Plans & Requests (`all`):** `$pendingCount + $totalPlansCount` — equals the exact total of unique workflow records displayed across pending requests and plans (e.g., `1` pending request `DIET-2026-0001` + `3` plans `DP-00001`, `DP-00002`, `DP-00003` = `4`).
2. **Clinician / Author Attribution (`Created By`):**
   - Historical seeded diet plans (`DP-00001`, `DP-00002`, `DP-00003`) store `dietitian_id = 7` (`Rosa Mendoza`, the historical Dietitian account), whereas newly created Doctor-managed plans store `dietitian_id = Auth::id()` (the acting Doctor's `users.id`).
   - Added `DietPlan::createdBy()` (`belongsTo(User::class, 'dietitian_id')`) and renamed the column header in `resources/views/diet/plans/index.blade.php` from `Clinician` to `Created By` (and updated `diet/plans/show.blade.php`, `diet/plans/print.blade.php`, and `diet/requests/show.blade.php`).
   - Historical records continue to display `Rosa Mendoza` accurately without rewriting or deleting historical database rows, while new plans created by a Doctor display the creating Doctor's name.
3. **Direct Request & Plan Actions for Authorized Doctors:**
   - **New Diet Request (`route('diet.requests.create')`):** available in the workspace header (guarded by `@can('create', DietRequest::class)`).
   - **New Diet Plan (`route('diet.plans.create')`):** available in the workspace header, and each row in **Pending Requests Awaiting Diet Plan** provides a direct **Create Plan** button (`route('diet.plans.create', ['diet_request_id' => $req->id])`) guarded by `@can('create', [DietPlan::class, $req])`, plus **View Request** and **Edit Pending Request** actions.
   - Each row in the **Therapeutic Diet Plans** table links to both the `DietPlan` (`DP-XXXXX`) and its originating `DietRequest` (`DR-YYYY-XXXX`), with direct **View**, **Edit / Revise** (`@can('update', $plan)`), and **Print** (`route('diet.plans.print', $plan)`) actions.

---

## 5. Routes and Backend Functionality Retained

- **All existing models and tables retained:** `DietRequest` (`diet_requests`), `DietPlan` (`diet_plans`), `MealSchedule` (`meal_schedules`), and `ActivityLog` (`activity_logs`).
- **All internal routes retained in `routes/web.php`:** `diet.dashboard`, `diet.requests.{index,create,store,show,edit,update,destroy}`, `diet.plans.{index,create,store,show,edit,update,destroy,complete,print}`, and `reports.diet.{activity,active,completed}`.
- **No duplicate creation logic:** request creation continues to use `DietRequestController::store()` and `StoreDietRequestRequest`; plan creation continues to use `DietPlanController::store()` and `StoreDietPlanRequest` inside a row-locked `DB::transaction()` that prevents duplicate plans per request.
- **Completed-plan immutability preserved:** `Completed` plans cannot be edited, re-completed, or deleted (`403 Forbidden`).

---

## 6. Authorization Changes

No authorization rules were weakened; all existing server-side policies and middleware remain strictly enforced:
- **Doctor (`doctor`):** full DNMS access (`DietRequestPolicy` & `DietPlanPolicy`) strictly scoped to their own requests (`doctor_id === $user->id`) and authorized patients (`Patient::isAuthorizedForDoctor($user)`). Cannot view or modify another Doctor's patients, requests, or plans.
- **Admin (`admin`) & Super Admin (`super_admin`):** read-only monitoring access to the DNMS dashboard, Diet Plans workspace (including viewing pending requests and plans), and Nutrition Reports. All clinical mutation routes (`create`, `store`, `edit`, `update`, `complete`, `destroy`) return `403 Forbidden`.
- **Dietitian (`dietitian`):** remains blocked (`403 Forbidden`) from all active DNMS routes while existing role, user account, and historical `diet_plans` records remain preserved.

---

## 7. Database Changes

- **None required for this simplification step.** Existing `diet_requests`, `diet_plans`, `meal_schedules`, `roles`, `permissions`, `permission_role`, and `users` tables and records in both `newdb` and `ditc_hms` remain untouched.
- Zero destructive operations (`migrate:fresh`, `db:wipe`, `DROP`, `TRUNCATE`) were executed.

---

## 8. Automated Test Results and Vite Build Result

### Automated PHPUnit Verification
| Suite | Command | Tests | Assertions | Status |
|---|---|---:|---:|---|
| **DNMS & Doctor Sidebar Targeted Suites** | `php artisan test tests/Feature/DnmsWorkflowAuthorizationTest.php tests/Feature/DoctorSidebarTest.php` | 20 | 323 | **PASSED** (20/20) |
| **Full Application Test Suite** | `php artisan test` | 238 | 1,559 | **PASSED** (238/238) |

### Production Vite Build Verification
```text
> npm run build
> vite build

vite v8.1.5 building client environment for production...
✓ 7 modules transformed.
public/build/manifest.json              0.33 kB │ gzip:  0.16 kB
public/build/assets/app-C3_B8kLt.css   40.43 kB │ gzip:  7.67 kB
public/build/assets/app-B50xrYjX.js   118.87 kB │ gzip: 36.65 kB
✓ built in 1.79s
```

---

## 9. Manual Verification Steps (Distinct from Automated Tests)

To verify the simplified UI and workflow manually in a browser:
1. Sign in as a **Doctor** and expand **Nutrition (DNMS)** in the left sidebar. Confirm only **Overview**, **Diet Plans**, and **Nutrition Reports** appear (no separate **Diet Requests** item).
2. Click **Diet Plans** (`/diet/plans`) and confirm the workspace displays:
   - Workflow tabs (`All Plans & Requests`, `Pending Requests`, `Active Plans`, `Revised Plans`, `Completed Plans`).
   - Action buttons (`New Diet Request` and `New Diet Plan`).
   - The **Pending Requests Awaiting Diet Plan** card and the **Therapeutic Diet Plans** card.
3. Click **New Diet Request**, submit a request for an authorized patient, and navigate to **Diet Plans** (`/diet/plans`). Verify the request appears under **Pending Requests Awaiting Diet Plan**.
4. Click **Create Plan** on the pending request row, enter Nutrition Assessment notes, caloric/macronutrient targets, and meal guidelines, and save. Confirm the request transitions to `Active` and the new plan appears in **Active Plans**.
5. Edit the plan and save; confirm its status badge updates to `Revised` (`bg-info text-dark`) and it appears under the **Revised Plans** tab.
6. Complete the plan and confirm it moves to the **Completed Plans** tab and its edit/delete controls are hidden.
7. Sign in as **Admin** or **Super Admin**, open **Diet Plans** (`/diet/plans`), and confirm all pending requests and plans are visible in read-only mode with no `New Diet Request`, `New Diet Plan`, `Create Plan`, or `Edit` buttons.

---

## 10. Remaining Limitations

1. **Internal `DietRequestController::index` Route Retained for Compatibility:** `GET /diet/requests` remains registered and protected by `DietRequestPolicy` so any existing bookmarks or direct links continue to work, even though the Doctor manages the entire workflow from `/diet/plans`.
2. **Out-of-Scope Kitchen / Meal Delivery Operations:** Meal tray preparation and `dietary-staff` workflows remain outside project scope.

