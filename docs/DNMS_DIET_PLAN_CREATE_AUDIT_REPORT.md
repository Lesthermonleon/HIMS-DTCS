# DTCS HIMS — DNMS Diet Plan Creation Workflow Audit & Verification Report

**Repository:** `Lesthermonleon/DTCS`  
**Module:** Diet and Nutrition Management System (DNMS)  
**Target Endpoints:**
1. `GET /diet/plans/create?diet_request_id={id}` — **Create Plan** button from a specific pending request (`resources/views/diet/plans/index.blade.php`, `resources/views/diet/requests/show.blade.php`)
2. `GET /diet/plans/create` — **New Diet Plan** button from the workspace header (`resources/views/diet/plans/index.blade.php`)
3. `POST /diet/plans` — Unified form submission endpoint (`DietPlanController::store`, `StoreDietPlanRequest`)

---

## 1. Phase 1 Audit: Comparison of the Two Entry Points (Before Fixes)

Before modifying any code, `DietPlanController::create()`, `DietPlanController::store()`, `StoreDietPlanRequest`, `DietPlanPolicy`, and `resources/views/diet/plans/create.blade.php` were audited side-by-side:

| Aspect | Entry Point 1: `/diet/plans/create?diet_request_id={id}` (Before) | Entry Point 2: `/diet/plans/create` (Before) | Audit Finding / Gap |
| :--- | :--- | :--- | :--- |
| **Existence & Ownership Check (`GET`)** | Loaded `DietRequest::with('patient')->findOrFail(...)` and called `$this->authorize('create', [DietPlan::class, $preselected])`. | Authorized `$this->authorize('create', DietPlan::class)` and loaded `DietRequest::visibleTo($user)->where('status', 'Pending')->whereDoesntHave('dietPlan')`. | `DietPlanController::create()` did **not** pass `$preselected` (`$selectedRequest`) to the Blade view, nor did it eager-load `doctor`. |
| **Eligibility & Duplicate Plan Check on `GET`** | Did **not** check whether `$preselected->status === 'Pending'` or `$preselected->dietPlan()->exists()`. | Filtered `$pendingRequests` to `status = 'Pending'` and `whereDoesntHave('dietPlan')`. | Visiting `/diet/plans/create?diet_request_id={id}` for a `Cancelled`, `Active`, or `Completed` request (or one with an existing plan) returned `200 OK` with the `<select>` silently reset to `— Select Request —` instead of rejecting the ineligible request. |
| **Display of Request Clinical Context** | Only selected the `<option>` label (`request_no — patient_name (Diet: diet_type)`). | Only rendered `<option>` labels inside the `<select>`. | Neither entry point displayed the selected request's **Requesting Doctor**, **Food Allergies**, **Food Restrictions**, or **Clinical Notes** on the creation page. |
| **Locking Preselected Request** | Left `<select name="diet_request_id">` unlocked and editable even when opened for a specific request via `?diet_request_id={id}`. | Open `<select name="diet_request_id">` dropdown. | When entering via `?diet_request_id={id}`, the form did not lock the preselected request or validate `preselected_request_id` against tampering. |
| **Unified Backend Validation (`POST /diet/plans`)** | `StoreDietPlanRequest` validated plan fields first, while `diet_request_id` was validated separately inside `DietPlanController::store()`. | Same. | Moving `diet_request_id` (and `preselected_request_id`) into `StoreDietPlanRequest` on `POST` unifies authorization and field validation in a single FormRequest pass. |
| **Concurrent Duplicate Submission Protection** | Used `DB::transaction()` with `lockForUpdate()` on `diet_requests`, plus `UNIQUE KEY diet_plans_diet_request_id_unique`. | Same. | Added `lockForUpdate()` on the `dietPlan()` check and explicit handling of `UniqueConstraintViolationException` / SQLSTATE `23000` so concurrent race conditions always return a clean validation error on `diet_request_id` rather than a `500` database exception. |

---

## 2. Implemented Improvements

### 2.1 Controller Entry Point Validation & Context Loading (`app/Http/Controllers/Diet/DietPlanController.php`)
- **When `diet_request_id` is supplied (`GET /diet/plans/create?diet_request_id={id}`):**
  1. Rejects non-numeric or non-existent IDs with `404 Not Found`.
  2. Eager-loads `['patient', 'doctor']` on `$selectedRequest` and enforces `$this->authorize('create', [DietPlan::class, $selectedRequest])` (`403 Forbidden` if the request belongs to another Doctor or an unauthorized patient).
  3. Checks `$selectedRequest->dietPlan()->exists()` and `$selectedRequest->status !== 'Pending'`; if ineligible, redirects to `route('diet.plans.index')` with both a flash error toast and a `diet_request_id` validation error.
  4. Passes `$selectedRequest` and `$isRequestLocked = true` to `resources/views/diet/plans/create.blade.php`.
- **When no `diet_request_id` is supplied (`GET /diet/plans/create`):**
  1. Queries eligible `Pending` requests (`whereDoesntHave('dietPlan')`) visible to the logged-in Doctor and explicitly filters to patients passing `$req->patient->isAuthorizedForDoctor($user)`.
  2. Passes `$pendingRequests`, `$selectedRequest` (resolved from `old('diet_request_id')` if returning from a validation error), and `$isRequestLocked = false`.

### 2.2 Unified Form & Automatic Clinical Context Display (`resources/views/diet/plans/create.blade.php`)
- **Preselected / Locked Mode (`?diet_request_id={id}`):**
  - Renders `<input type="hidden" name="diet_request_id" value="{{ $activeRequest->id }}">` and `<input type="hidden" name="preselected_request_id" value="{{ $activeRequest->id }}">`.
  - Renders `#diet-request-select` as `disabled` (`aria-disabled="true"`) with a `Preselected Request: {request_no}` badge so the Doctor cannot switch to another request.
  - Immediately displays `#selected-request-summary` containing:
    - **Request No:** `{{ $activeRequest->request_no }}`
    - **Patient:** `{{ $activeRequest->patient->last_name }}, {{ $activeRequest->patient->first_name }}` (`{{ $activeRequest->patient->patient_no }}`)
    - **Requesting Doctor:** `{{ $activeRequest->doctor->name }}`
    - **Prescribed Diet Type:** `{{ $activeRequest->diet_type }}`
    - **Food Allergies:** `{{ $activeRequest->allergies ?: 'None recorded' }}`
    - **Food Restrictions:** `{{ $activeRequest->food_restrictions ?: 'None recorded' }}`
    - **Clinical Notes:** `{{ $activeRequest->clinical_notes ?: 'None recorded' }}`
- **Open Selection Mode (`/diet/plans/create`):**
  - Renders the enabled `#diet-request-select` dropdown with `data-*` attributes (`data-request-no`, `data-patient-name`, `data-patient-no`, `data-doctor-name`, `data-diet-type`, `data-allergies`, `data-restrictions`, `data-clinical-notes`) for each eligible pending request.
  - Automatically populates and reveals `#selected-request-summary` as soon as the Doctor selects a pending request from the dropdown (and preserves it via `old('diet_request_id')` on validation redirects).

### 2.3 Unified Backend Validation & Concurrency Protection (`app/Http/Requests/StoreDietPlanRequest.php`, `app/Policies/DietPlanPolicy.php`, `app/Http/Controllers/Diet/DietPlanController.php`)
- **`DietPlanPolicy::create()`:** Requires `$user->hasRole('doctor')`, and when a `$dietRequest` is supplied, requires `(int) $dietRequest->doctor_id === (int) $user->id && $dietRequest->patient !== null && $dietRequest->patient->isAuthorizedForDoctor($user)`.
- **`StoreDietPlanRequest`:**
  - `authorize()` verifies Doctor role and checks `DietPlanPolicy::create` against both `diet_request_id` and `preselected_request_id` on `POST`, returning `403 Forbidden` if a Doctor attempts to submit or tamper with an unauthorized request ID.
  - `rules()` and `after()` validate `diet_request_id` (`required|integer|exists:diet_requests,id`) and `preselected_request_id` (`nullable|integer|exists:diet_requests,id`), rejecting any mismatch between `diet_request_id` and `preselected_request_id`.
- **`DietPlanController::store()`:**
  - Executes inside `DB::transaction()` with `DietRequest::whereKey(...)->lockForUpdate()->firstOrFail()`, re-authorizes under the row lock, verifies `$dietRequest->status === 'Pending'` and `!$dietRequest->dietPlan()->lockForUpdate()->exists()`, and catches `UniqueConstraintViolationException` / `QueryException` (`23000`) to convert any concurrent duplicate insert into a clean `ValidationException` on `diet_request_id`.

---

## 3. Automated Test Verification

- **DNMS Feature & Authorization Suite:**
  - `php artisan test --filter=DnmsWorkflowAuthorizationTest` — **15 passed (294 assertions)**
  - Includes `test_diet_plan_creation_entry_points_preselection_locking_and_duplicate_prevention`, which tests:
    1. `GET /diet/plans/create?diet_request_id={id}` with a valid pending request (verifies locked state, hidden inputs, and rendered patient, doctor, diet type, allergies, restrictions, and clinical notes).
    2. `GET /diet/plans/create?diet_request_id=999999` (`404 Not Found`).
    3. `GET /diet/plans/create?diet_request_id={otherDoctorRequest}` (`403 Forbidden`).
    4. `GET /diet/plans/create?diet_request_id={requestWithExistingPlan}` (`302` redirect to `/diet/plans` with `diet_request_id` error).
    5. `GET /diet/plans/create?diet_request_id={cancelledRequest}` (`302` redirect to `/diet/plans` with `diet_request_id` error).
    6. Tampering with `diet_request_id` on `POST /diet/plans` to another Doctor's request (`403 Forbidden`) or another own request when `preselected_request_id` is locked (validation error on `diet_request_id`).
    7. Valid submission from Entry Point 1 followed by a duplicate/concurrent second submission (rejected with validation error on `diet_request_id`; count remains `1`).
    8. `GET /diet/plans/create` without `diet_request_id` (verifies unlocked dropdown listing only eligible pending requests for authorized patients, with `data-allergies` and `data-restrictions` attributes, followed by successful `POST /diet/plans`).

