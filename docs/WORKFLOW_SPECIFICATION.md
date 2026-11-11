# DTCS HIMS — Complete Workflow & Status Transition Specification

**Document Version:** 2.0.0  
**Date:** October 18, 2026  
**Scope:** Core Hospital Information Management System Workflows & State Machines  
**Status:** DRAFT / PENDING ARCHITECTURAL REVIEW (Implementation Paused)

---

## 1. Architectural Principles & Global Workflow Rules

Every clinical, operational, and administrative transaction in DTCS HIMS follows deterministic, audited state machines. The system strictly prohibits illegal state jumps, silent overwrites of signed clinical records, and unverified data releases.

### 1.1 Global State Transition & Security Invariants
1. **Immutable Audit Trail:** Any status transition on a clinical, operational, or administrative record generates an `activity_logs` entry with actor ID, IP address, severity level, and before/after context.
2. **Deterministic Role Attribution:** Every change explicitly tracks `user_id` or professional credentials (e.g., `doctor_id`, `med_tech_id`, `radiologist_id`, `pharmacist_id`).
3. **No Unreleased Patient Exposure:** Diagnostic and clinical records in `Draft`, `Pending`, `Encoded`, or `Under Review` states are never returned or rendered in Patient Portal endpoints.
4. **Separation of Verification & Release:** In diagnostic and dispensing workflows, the individual entering the data cannot bypass required verification steps.
5. **Zero-Trust Ownership Check:** All patient-specific routes enforce `$record->patient_id === Auth::user()->patient_id` on the server.
6. **Anti-Escalation:** Administrative users cannot elevate accounts to Super Admin or modify higher-privileged accounts.

---

## 2. Master Workflows (1 through 12)

---

### Workflow 1: Authentication, OTP, Session Replacement, Account Lockout & Screen Lock

#### 1. Actor & Required Permissions
- **Actor:** Any user (`super_admin`, `admin`, `doctor`, `staff`, `patient`).
- **Required Permission:** Public authentication routes; `auth` middleware once authenticated.

#### 2. Preconditions
- User account exists in `users` table and `is_active = 1`.
- Account is not locked (`locked_until IS NULL` or in past).

#### 3. Starting Page & Route
- Route: `GET /login` (`login`)
- Page: `resources/views/auth/login.blade.php`

#### 4. User Actions & Form Validation
- User enters `email` (required, email) and `password` (required).
- System checks credentials against `users.password` using `Hash::check()`.

#### 5. Controller / Service Processing
- Handled by `App\Http\Controllers\Auth\AuthenticatedSessionController`.
- If credentials invalid: increments `failed_login_attempts`. If `>= 5`, sets `locked_until = now()->addMinutes(15)` and fires lockout audit log.
- If credentials valid: resets `failed_login_attempts = 0`.
- If `LOGIN_OTP_ENABLED=true`: generates 6-digit numeric OTP, stores hash and expiration in session/cache, sends email notification, and redirects to `GET /verify-otp`.
- If OTP disabled or verified: regenerates session ID.
- Single-session check: updates `users.current_session_id`. If prior session exists, broadcasts `SessionReplaced` via Reverb.
- Role-based redirect: redirects to `dashboard_route` based on user's highest role.
- Idle screen lock: `EnsureSessionInactivity` middleware monitors inactivity (>3 minutes). If exceeded, flags session as locked; `EnsureSessionUnlocked` intercepts requests and redirects to `GET /screen-lock` until current user password is re-verified.

#### 6. Database Reads & Writes
- Reads: `users`, `roles`, `role_user`.
- Writes: `users.failed_login_attempts`, `users.locked_until`, `users.last_login_at`, `users.current_session_id`, `activity_logs`.

#### 7. Status Transitions
- Account: `Active` -> `Locked` (after 5 failed attempts) -> `Active` (after 15 minutes or Admin unlock).
- Session: `Anonymous` -> `Pending OTP` -> `Authenticated` -> `Screen Locked` -> `Authenticated` -> `Terminated`.

#### 8. Notifications & Audit Events
- Events: `User Login Success`, `User Login Failed`, `Account Locked`, `OTP Dispatched`, `Session Replaced`.
- Severity: `INFO` for login/logout; `WARNING` for failed login; `CRITICAL` for lockout.

#### 9. Success & Failure Outcomes
- Success: Redirect to role-specific dashboard.
- Failure: Redirect to login with error "These credentials do not match our records" or lockout banner.

#### 10. Unauthorized-Access Behavior
- Unauthenticated access to protected routes: 302 redirect to `login`.
- Locked account: 423 or redirect with lockout message.

#### 11. Data Privacy & Integrity
- Passwords never logged. OTPs stored as salted hashes or transient cache tokens with short TTL (5 minutes).

#### 12. Test Cases
- `test_user_can_login_with_valid_credentials`
- `test_account_locks_after_repeated_failures`
- `test_single_session_replaces_previous_session`
- `test_idle_screen_lock_requires_password`

---

### Workflow 2: User and Role Management (Super Admin & Admin Governance)

#### 1. Actor & Required Permissions
- **Super Admin:** `role:super_admin`, permissions `accounts.admin.manage`, `access.roles.review`.
- **Admin:** `role:admin`, permissions `accounts.staff.manage`, `accounts.doctor.manage`.

#### 2. Preconditions
- Managing user is authenticated with active session.
- Target roles exist in `roles` table.

#### 3. Starting Page & Route
- Super Admin: `GET /super-admin/admins` (`super-admin.admins.index`)
- Admin: `GET /admin/users` (`admin.users.index`), `GET /admin/staff` (`admin.staff.index`), `GET /admin/doctors` (`admin.doctors.index`).

#### 4. User Actions & Form Validation
- Super Admin creates/updates `admin` accounts: `name`, `email` (unique), `password` (min 8, confirmed), `department`.
- Admin creates/updates `staff` accounts: `name`, `email` (unique), `password`, `department`, `role_id` (restricted to staff roles: `med-tech`, `rad-tech`, `radiologist`, `pharmacist`, `dietitian`, `or-coordinator`).
- Doctor Onboarding: Admin initiates request; Super Admin authorizes two-tier provisioning.

#### 5. Controller / Service Processing
- `SuperAdmin\AdminAccountController` manages Admin accounts with anti-escalation rules.
- `Admin\StaffController` and `Admin\DoctorController` manage staff and doctor accounts.
- Anti-escalation check: Admin is blocked via policy from assigning `super_admin` or `admin` role (returns 403 Forbidden).

#### 6. Database Reads & Writes
- Reads: `users`, `roles`, `permissions`, `departments`.
- Writes: `users`, `role_user`, `doctor_profiles` (if doctor), `activity_logs`.

#### 7. Status Transitions
- User: `Draft/Requested` -> `Active` <-> `Suspended/Inactive` -> `Archived (Soft Deleted)`.

#### 8. Notifications & Audit Events
- Audit: `Admin Account Created`, `Staff Account Provisioned`, `Doctor Access Authorized`, `Account Unlocked`.

#### 9. Success & Failure Outcomes
- Success: User record created; redirected with success flash message.
- Failure: Validation errors on duplicate email; 403 Forbidden on unauthorized role assignment.

#### 10. Unauthorized-Access Behavior
- Admin attempting to access `/super-admin/*`: 403 Forbidden.
- Doctor/Staff attempting to access `/admin/users`: 403 Forbidden.

#### 11. Data Privacy & Integrity
- Passwords hashed via `bcrypt` (cost 12). Soft deletes prevent accidental data destruction.

#### 12. Test Cases
- `test_super_admin_can_manage_admin_accounts`
- `test_admin_cannot_create_or_escalate_to_super_admin`
- `test_staff_cannot_access_user_management`

---

### Workflow 3: Patient Identity Integration and Patient Portal Access

#### 1. Actor & Required Permissions
- **Admin / Intake Officer:** `role:admin`, permission `patients.create` (adapter linking only).
- **Patient:** `role:patient` (self-service portal).

#### 2. Preconditions
- Patient record already exists in `patients` table (master created by external registration authority).
- Patient does not already have an active portal user account (`patients.user_id IS NULL`).

#### 3. Starting Page & Route
- Admin Linking: `GET /admin/patients/intake` (`admin.patients.intake`)
- Patient Portal: `GET /patient/dashboard` (`patient.dashboard`)

#### 4. User Actions & Form Validation
- Admin searches for patient by name or `patient_no`.
- In the patient row, Admin clicks "Link Portal Access".
- Form requires:
  - `email`: required, email, unique:users,email. If patient master has email, must match.
  - `identity_verified`: required, accepted checkbox confirming in-person identity verification.
- No default password input: system generates a cryptographically random 40-character secret.

#### 5. Controller / Service Processing
- `Admin\PatientIntakeController::linkAccount()` executes within a DB transaction:
  1. Verifies patient has no existing user account.
  2. Creates user record in `users` with `patient_id = $patient->id`, `department = 'Patient Portal'`.
  3. Attaches `patient` role in `role_user`.
  4. Updates `patients.user_id = $user->id`.
  5. Generates password-reset token via `Password::broker()->createToken($user)` and dispatches setup email.
  6. Creates `activity_logs` entry with `SEVERITY_WARNING`.

#### 6. Database Reads & Writes
- Reads: `patients`, `users`, `roles`.
- Writes: `users`, `role_user`, `patients.user_id`, `activity_logs`.

#### 7. Status Transitions
- Patient Master: `Unlinked Patient` -> `Portal Linked Patient`.
- Portal User: `Provisioned (Pending Password Setup)` -> `Active`.

#### 8. Notifications & Audit Events
- Notification: Password reset / setup email sent to patient.
- Audit Event: `Patient Portal Account Linked` (`SEVERITY_WARNING`).

#### 9. Success & Failure Outcomes
- Success: Redirect to intake list with notice "Portal account created; password setup link sent".
- Failure: Validation failure if email differs from patient master or checkbox unselected; error flash if already linked.

#### 10. Unauthorized-Access Behavior
- Doctor or Staff attempting to link portal account: 403 Forbidden.
- Patient trying to access intake: 403 Forbidden.

#### 11. Data Privacy & Integrity
- Zero guessable passwords (no `Patient@YYYY`). Upstream patient master records are never mutated or duplicated.

#### 12. Test Cases
- `test_portal_link_requires_identity_attestation`
- `test_portal_link_email_must_match_record_and_no_default_password`
- `test_duplicate_portal_link_is_rejected`

---

### Workflow 4: Appointments, Cancellation, and Scheduling

#### 1. Actor & Required Permissions
- **Patient:** `role:patient` (book own request, cancel own request).
- **Admin:** `role:admin`, permission `appointments.manage` (full scheduling and queue management).
- **Doctor:** `role:doctor`, permission `appointments.view` (view own appointments, update consultation status).

#### 2. Preconditions
- Patient is registered; Doctor is an active user with role `doctor`.
- Desired slot is within clinic hours and in the future.

#### 3. Starting Page & Route
- Patient: `GET /patient/appointments` (`patient.appointments`)
- Admin / Doctor: `GET /admin/appointments` (`admin.appointments.index`)

#### 4. User Actions & Form Validation
- **Booking (Patient or Admin):**
  - Form inputs: `doctor_id` (required, exists:users,id where doctor), `scheduled_at` (required, future datetime), `type` (required, in catalog: `In-Person Consultation`, `Follow-up Visit`, `Diagnostic Review`), `reason` (required, string).
  - Patient cannot specify `patient_id` (strictly bound to `Auth::user()->patient_id`).
- **Cancellation (Patient):**
  - Patient clicks "Cancel" on an upcoming appointment.
- **Status Update (Admin or Doctor):**
  - Select new status from allowed transition dropdown.

#### 5. Controller / Service Processing
- Managed by `AppointmentScheduler` service:
  1. Acquires database row lock on doctor's schedule slot (`lockForUpdate()`).
  2. Validates `doctor_id` belongs to an active user holding role `doctor`.
  3. Checks for overlapping active appointments for both doctor and patient:
     `scheduled_at < existing_end AND existing_start < proposed_end`.
  4. Generates unique collision-safe `appointment_no` (`APT-YYYYMMDD-XXXXX`).
  5. Inserts appointment with status `Requested` (or `Confirmed` if Admin).
  6. Transition validation: enforces state machine:
     - `Requested` -> `Confirmed`, `Cancelled`
     - `Confirmed` -> `CheckedIn`, `Cancelled`, `No Show`
     - `CheckedIn` -> `In Consultation`, `Cancelled`
     - `In Consultation` -> `Completed`
     - Terminal states (`Completed`, `Cancelled`, `No Show`) cannot transition.
  7. Doctors are restricted to updating only their own appointments to `Confirmed`, `In Consultation`, or `Completed`.

#### 6. Database Reads & Writes
- Reads: `appointments`, `users`, `patients`.
- Writes: `appointments` (create or status update), `activity_logs`.

#### 7. Status Transitions
- State Machine: `Requested` -> `Confirmed` -> `CheckedIn` -> `In Consultation` -> `Completed`.
- Alternate paths: `Requested` -> `Cancelled`; `Confirmed` -> `Cancelled` or `No Show`.

#### 8. Notifications & Audit Events
- Audit: `Appointment Booked`, `Appointment Cancelled`, `Appointment Status Updated`.

#### 9. Success & Failure Outcomes
- Success: Appointment booked or transitioned; redirected with success flash.
- Failure: Validation error if slot overlaps (422) or invalid status transition attempted.

#### 10. Unauthorized-Access Behavior
- Patient attempting to cancel another patient's appointment: 404 Not Found (scoped query).
- Doctor attempting to update another doctor's appointment: 403 Forbidden.
- Non-doctor booked as physician: 422 Unprocessable Entity.

#### 11. Data Privacy & Integrity
- Doctor's personal email is never exposed in the patient portal appointment view. Concurrency locks prevent double-booking.

#### 12. Test Cases
- `test_doctor_double_booking_is_rejected`
- `test_non_doctor_cannot_be_booked_as_provider`
- `test_patient_can_cancel_own_but_not_others_appointment`
- `test_doctor_cannot_update_another_doctors_appointment`

---

### Workflow 5: Laboratory Diagnostic Workflow (LIS)

#### 1. Actor & Required Permissions
- **Doctor:** `role:doctor`, permission `diagnostics.order` (create request).
- **Medical Technologist (Staff):** `role:med-tech`, permissions `lis.results.enter`, `lis.results.verify`, `lis.results.release`.

#### 2. Preconditions
- Patient is registered; Doctor has active clinical encounter.
- Laboratory test exists in active master catalog (`lab_tests`).

#### 3. Starting Page & Route
- Doctor Order: `POST /lab/requests` (`lab.requests.store`)
- LIS Dashboard: `GET /lab/dashboard` (`lab.dashboard`)
- LIS Result Entry: `GET /lab/requests/{request}/results/create` (`lab.results.create`)

#### 4. User Actions & Form Validation
- Doctor submits lab request: `patient_id`, `test_ids` (array), `priority` (`Routine`, `Urgent`, `STAT`), `clinical_notes`.
- Med-Tech receives specimen: marks request `In Progress`.
- Med-Tech enters results: `result_value` (numeric or text), `reference_range`, `abnormal_flag` (boolean), `remarks`.
- Med-Tech validates results: reviews against QC controls and normal ranges.
- Med-Tech releases results: formal sign-off making result visible to doctor and patient.

#### 5. Controller / Service Processing
- Handled by `Lab\LabRequestController` and `Lab\LabResultController`.
- Protected by route-level middleware:
  - Result Entry: `middleware('permission:lis.results.enter')`.
  - Validation: `middleware('permission:lis.results.verify')`.
  - Release: `middleware('permission:lis.results.release')`.
- Release logic sets `released_at = now()`, `status = 'Released'`, and logs audit event.

#### 6. Database Reads & Writes
- Reads: `lab_requests`, `lab_request_items`, `lab_tests`, `patients`.
- Writes: `lab_requests.status`, `lab_results` (inserts raw values, updates `status`), `activity_logs`.

#### 7. Status Transitions
- `LabRequest.status`: `Pending` -> `In Progress` -> `Completed` (or `Cancelled`).
- `LabResult.status`: `Draft/Encoded` -> `Validated` -> `Released`.

#### 8. Notifications & Audit Events
- Audit: `Lab Request Created`, `Specimen Received`, `Lab Results Encoded`, `Lab Results Validated`, `Lab Results Released`.

#### 9. Success & Failure Outcomes
- Success: Results saved and released; reports available for print and patient view.
- Failure: 403 if Med-Tech lacks specific permission; validation error on invalid numeric ranges.

#### 10. Unauthorized-Access Behavior
- Doctor or Admin attempting to enter lab results: 403 Forbidden.
- Patient attempting to view unreleased results: hidden from query (`where('status', 'Released')`).

#### 11. Data Privacy & Integrity
- Results cannot be modified once `Released` (amendment workflow requires formal addendum).

#### 12. Test Cases
- `test_unreleased_diagnostic_results_are_hidden_from_patient`
- `test_med_tech_can_enter_and_release_lab_results`
- `test_user_without_lis_release_permission_is_forbidden`

---

### Workflow 6: Radiology Diagnostic & Imaging Workflow (RIS)

#### 1. Actor & Required Permissions
- **Doctor:** `role:doctor`, permission `diagnostics.order` (order imaging).
- **Radiologic Technologist (Staff):** `role:rad-tech`, permission `ris.images.upload` (schedule & upload images).
- **Radiologist (Staff/Doctor):** `role:radiologist`, permission `ris.reports.release` (interpret & sign report).

#### 2. Preconditions
- Active radiology request submitted by Doctor.
- Modality catalog item active (e.g., Chest X-Ray, Brain MRI, Abdominal Ultrasound).

#### 3. Starting Page & Route
- RIS Worklist: `GET /radiology/requests` (`radiology.requests.index`)
- Upload Images: `POST /radiology/requests/{request}/images` (`radiology.images.store`)
- Interpret Report: `GET /radiology/requests/{request}/reports/create` (`radiology.reports.create`)

#### 4. User Actions & Form Validation
- Rad-Tech schedules procedure and positions patient.
- Rad-Tech uploads DICOM or high-resolution images (`image` files, mime validation, max 20MB).
- Radiologist opens study viewer, examines images, authors:
  - `findings`: detailed anatomical observations.
  - `impression`: diagnostic conclusion.
  - `status`: `Draft` -> `Approved` -> `Released`.

#### 5. Controller / Service Processing
- Handled by `Radiology\RadiologyRequestController` and `Radiology\RadiologyReportController`.
- Separation of duties:
  - Rad-Tech can update request status to `Completed` and upload images, but cannot author or release reports (`RadiologyReportPolicy`).
  - Radiologist authors report and calls release action (`permission:ris.reports.release`).
- File storage: images stored securely in private storage disk, never publicly accessible.

#### 6. Database Reads & Writes
- Reads: `radiology_requests`, `radiology_images`, `patients`.
- Writes: `radiology_requests.status`, `radiology_images`, `radiology_reports`, `activity_logs`.

#### 7. Status Transitions
- `RadiologyRequest.status`: `Pending` -> `Scheduled` -> `In Progress` -> `Completed`.
- `RadiologyReport.status`: `Draft` -> `Approved` -> `Released`.

#### 8. Notifications & Audit Events
- Audit: `Radiology Study Completed`, `Radiology Images Uploaded`, `Radiology Report Approved`, `Radiology Report Released`.

#### 9. Success & Failure Outcomes
- Success: Study finalized; report visible to ordering physician and released to Patient Portal.
- Failure: Rad-Tech attempt to sign report fails with 403 Forbidden.

#### 10. Unauthorized-Access Behavior
- Rad-Tech attempting report release: 403 Forbidden.
- Patient accessing direct image file URLs without signed session: 403 Forbidden.

#### 11. Data Privacy & Integrity
- DICOM and image files protected with authenticated streaming responses. Draft reports concealed from patients.

#### 12. Test Cases
- `test_rad_tech_can_upload_images_but_cannot_release_report`
- `test_radiologist_can_interpret_and_release_report`
- `test_patient_cannot_view_unapproved_radiology_report`

---

### Workflow 7: Pharmacy Prescription & Dispensing Workflow (PMS)

#### 1. Actor & Required Permissions
- **Doctor:** `role:doctor`, permission `clinical.prescribe` (author prescription).
- **Pharmacist (Staff):** `role:pharmacist`, permissions `pms.prescriptions.verify`, `pms.dispense` (verify & dispense).

#### 2. Preconditions
- Prescription ordered by physician with drug name, dosage, frequency, and quantity.
- Stock availability confirmed in read-only medication stock provider.

#### 3. Starting Page & Route
- Doctor Prescribe: `POST /doctor/prescriptions` (`doctor.prescriptions.store`)
- Pharmacy Dashboard: `GET /pharmacy/dashboard` (`pharmacy.dashboard`)
- Dispensing Form: `GET /pharmacy/dispensing/create` (`pharmacy.dispensing.create`)

#### 4. User Actions & Form Validation
- Doctor submits prescription: `patient_id`, `medication_name`, `dosage`, `frequency`, `duration_days`, `instructions`.
- Pharmacist reviews prescription for drug-drug interactions, contraindications, and dosage accuracy.
- Pharmacist clicks "Verify" (`PATCH /pharmacy/prescriptions/{prescription}/verify`).
- Pharmacist dispenses medication: enters `quantity_dispensed`, `batch_no`, `counseling_notes`.

#### 5. Controller / Service Processing
- `Pharmacy\PrescriptionController` handles prescription review and status updates.
- `Pharmacy\DispensingController` handles medication fulfillment.
- Route-level middleware enforces:
  - Verification: `permission:pms.prescriptions.verify`.
  - Dispensing: `permission:pms.dispense`.
- Transaction: creates immutable record in `dispensing_records` and updates `prescriptions.status` to `Dispensed`.

#### 6. Database Reads & Writes
- Reads: `prescriptions`, `medicines` (read-only stock directory), `patients`.
- Writes: `prescriptions.status`, `prescriptions.verified_by`, `dispensing_records`, `activity_logs`.

#### 7. Status Transitions
- `Prescription.status`: `Pending` -> `Verified` -> `Dispensed` (or `Partially Dispensed` / `Cancelled`).

#### 8. Notifications & Audit Events
- Audit: `Prescription Created`, `Prescription Verified`, `Medication Dispensed`.

#### 9. Success & Failure Outcomes
- Success: Dispensing logged; patient prescription marked fulfilled; available in Patient Portal.
- Failure: 403 Forbidden if user lacks `pms.dispense`; 422 if quantity exceeds prescribed amount.

#### 10. Unauthorized-Access Behavior
- Doctor attempting to dispense medication: 403 Forbidden (prescribers cannot dispense).
- Pharmacist attempting to alter prescribed molecule: prohibited; requires doctor re-order.

#### 11. Data Privacy & Integrity
- Dispensing records are write-once/immutable. No stock-purchasing or supply-chain operations permitted.

#### 12. Test Cases
- `test_doctor_can_prescribe_medication`
- `test_pharmacist_with_permission_can_verify_and_dispense`
- `test_pharmacist_without_permission_is_forbidden`

---

### Workflow 8: Surgical Operations & Scheduling Workflow (SORS)

#### 1. Actor & Required Permissions
- **Surgeon / Doctor (`doctor`):** `sors.requests.view`, `sors.requests.create`, `sors.schedules.view`, `sors.reports.view` (scoped to own patients/cases).
- **OR Coordinator (`or-coordinator`):** `sors.requests.view`, `sors.requests.manage`, `sors.schedules.view`, `sors.schedules.manage`, `sors.readiness.update`, `sors.procedures.execute`, `sors.reports.view`.
- **OR Staff (`or-staff`):** `sors.schedules.view`, `sors.readiness.update`, `sors.procedures.execute` (scoped strictly to assigned `surgical_team_members` schedules).

#### 2. Preconditions
- Patient clinical assessment indicates surgical necessity and the ordering Doctor is authorized for the patient.
- Target Operating Room is active (`is_active = 1`), not `Under Maintenance`, and has no overlapping booking (`Scheduled` or `In Progress`) during `[scheduled_at, scheduled_at + duration_minutes]`.

#### 3. Starting Page & Route
- Doctor Request Creation: `GET /surgery/requests/create`, `POST /surgery/requests` (`surgery.requests.store`)
- OR Coordinator Request Review: `GET /surgery/requests`, `GET /surgery/requests/{surgeryRequest}`
- OR Coordinator Scheduling: `GET /surgery/schedules/create`, `POST /surgery/schedules` (`surgery.schedules.store`)
- OR Readiness Update: `PATCH /surgery/schedules/{surgerySchedule}/readiness` (`surgery.schedules.readiness`)
- Operation Status Transitions: `PATCH /surgery/schedules/{surgerySchedule}/start`, `PATCH /surgery/schedules/{surgerySchedule}/complete`, `PATCH /surgery/schedules/{surgerySchedule}/cancel`

#### 4. User Actions & Form Validation
- **Doctor:** Submits surgery request (`patient_id`, `procedure_name`, `diagnosis`, `anesthesia_type`, `urgency`, `estimated_duration`, `notes`). May edit or cancel only while status is `Pending`. Cannot assign operating rooms, manage the master schedule, or update OR readiness on behalf of OR staff.
- **OR Coordinator:** Reviews pending requests, assigns an available `OperatingRoom` and `SurgicalTeam`, sets `scheduled_at` and `duration_minutes`, checks for room/team conflicts, reschedules or cancels according to workflow, coordinates OR readiness, and monitors operational reports. Cannot create surgery requests or alter clinical fields (`procedure_name`, `diagnosis`, `anesthesia_type`).
- **OR Staff:** Views assigned surgery schedules and calendar entries, updates OR preparation/readiness (`Pending Prep` -> `Preparing` -> `Ready`) and operational prep notes, and records authorized operational status updates (`start` / `complete`) on assigned cases. Cannot approve requests, assign rooms, or view unassigned cases.

#### 5. Controller / Service Processing
- Handled by `Surgery\SurgeryRequestController`, `Surgery\SurgeryScheduleController`, `SurgeryRequestPolicy`, `SurgerySchedulePolicy`, and `StoreSurgeryScheduleRequest`.
- Server-side conflict detection (`SurgerySchedule::findRoomConflict` and `findTeamConflict`) blocks overlapping OR bookings and double-booked surgical teams (HTTP 422 validation error).
- Server-side state machine validation rejects invalid transitions (e.g., starting a non-`Scheduled` case, completing a non-`In Progress` case, or modifying/cancelling a `Completed` or `Cancelled` case).

#### 6. Database Reads & Writes
- Reads: `surgery_requests`, `surgery_schedules`, `operating_rooms`, `surgical_teams`, `surgical_team_members`, `users`.
- Writes: `surgery_requests.status`, `surgery_schedules` (including `actual_start`, `actual_end`, `readiness_status`, `readiness_notes`, `readiness_updated_by`, `readiness_updated_at`), `operating_rooms.status`, `activity_logs`.

#### 7. Status Transitions
- `SurgeryRequest.status`: `Pending` -> `Scheduled` -> `In Progress` -> `Completed` (or `Pending`/`Scheduled` -> `Cancelled`).
- `SurgerySchedule.status`: `Scheduled` -> `In Progress` -> `Completed` (or `Scheduled` <-> `Postponed` -> `Cancelled`).
- `SurgerySchedule.readiness_status`: `Pending Prep` -> `Preparing` -> `Ready`.

#### 8. Notifications & Audit Events
- Audit (`activity_logs`): `Surgery Request Created`, `Surgery Request Updated`, `Surgery Request Cancelled`, `Surgery Scheduled`, `Surgery Rescheduled`, `Surgery Schedule Updated`, `Surgery Schedule Removed`, `OR Readiness Updated`, `Surgery Started`, `Surgery Completed`, `Surgery Cancelled`.

#### 9. Success & Failure Outcomes
- Success: OR reserved without conflict; readiness tracked; procedure timestamps (`actual_start`, `actual_end`) and room occupancy updated; full history retained.
- Failure: Room/team conflict error (422), invalid status transition (422), or unauthorized access (403).

#### 10. Unauthorized-Access Behavior
- Direct URL access to another doctor's request or schedule, or an unassigned schedule by OR Staff: 403 Forbidden.
- Doctor or OR Staff attempting to create/edit/delete OR schedules: 403 Forbidden.
- OR Coordinator or OR Staff attempting to create/edit clinical surgery requests: 403 Forbidden.

#### 11. Data Privacy & Integrity
- Doctors are scoped strictly to their own patients, requests, schedules, and surgery reports.
- OR Staff are scoped strictly to schedules where they are members of the assigned surgical team.
- Completed and cancelled operations retain their full audit history and cannot be overwritten.

#### 12. Test Cases
- `test_doctor_can_create_and_edit_own_pending_surgery_request`
- `test_doctor_cannot_access_another_doctors_restricted_patient_or_surgery_records`
- `test_or_coordinator_can_schedule_and_reschedule_but_cannot_alter_clinical_details`
- `test_or_staff_can_view_and_update_readiness_on_assigned_schedule_only`
- `test_unauthorized_schedule_modifications_are_blocked`
- `test_operating_room_double_booking_conflict_is_rejected`
- `test_invalid_status_transitions_are_rejected_server_side`
- `test_sors_sidebar_visibility_and_direct_url_authorization_by_role`
- `test_sors_lifecycle_activity_logging_records_all_actors_and_transitions`

---

### Workflow 9: Dietary & Nutrition Care Workflow (DNMS)

#### 1. Actor & Required Permissions
- **Doctor:** `role:doctor`, permission `diet.order`.
- **Dietitian (Staff):** `role:dietitian`, permission `dnms.plans.manage`.

#### 2. Preconditions
- Patient is admitted or in clinical consultation requiring dietary intervention.

#### 3. Starting Page & Route
- Doctor Order: `POST /diet/requests` (`diet.requests.store`)
- Dietitian Dashboard: `GET /diet/dashboard` (`diet.dashboard`)
- Create Diet Plan: `POST /diet/plans` (`diet.plans.store`)

#### 4. User Actions & Form Validation
- Doctor specifies clinical need: `diet_type` (`Low Sodium`, `Diabetic`, `Renal`, `NPO`, `High Protein`), `clinical_indications`.
- Dietitian conducts nutritional assessment: calculates BMI, caloric target, macro breakdown.
- Dietitian authors meal regimen: `breakfast`, `lunch`, `dinner`, `snacks`, `allergies_noted`.

#### 5. Controller / Service Processing
- Handled by `Diet\DietRequestController` and `Diet\DietPlanController`.
- Formulated diet plans link to active diet request.
- Released diet plans are surfaced to the Patient Portal (`patient.treatments`).

#### 6. Database Reads & Writes
- Reads: `diet_requests`, `diet_plans`, `patients`.
- Writes: `diet_requests.status`, `diet_plans`, `activity_logs`.

#### 7. Status Transitions
- `DietRequest.status`: `Pending` -> `Active` -> `Completed` (or `Cancelled`).

#### 8. Notifications & Audit Events
- Audit: `Diet Order Created`, `Diet Plan Formulated`, `Diet Plan Completed`.

#### 9. Success & Failure Outcomes
- Success: Meal regimen scheduled and visible to clinical care team and patient.
- Failure: 403 Forbidden if non-dietitian attempts to formulate nutritional plans.

#### 10. Unauthorized-Access Behavior
- Non-dietitian staff access to diet plan formulation: 403 Forbidden.

#### 11. Data Privacy & Integrity
- Dietary allergies flagged with high-visibility alerts across clinical views.

#### 12. Test Cases
- `test_doctor_can_order_dietary_plan`
- `test_dietitian_can_create_and_manage_meal_plans`
- `test_diet_plan_visible_in_patient_treatments`

---

### Workflow 10: Virtual MediSense AI Decision Support Workflow

#### 1. Actor & Required Permissions
- **Doctor:** `role:doctor` exclusively, permission `medisense.access`.

#### 2. Preconditions
- Physician is authenticated with active clinical consultation session.

#### 3. Starting Page & Route
- Route: `GET /medisense` (`medisense.index`), `POST /medisense/consult` (`medisense.consult`)
- Page: `resources/views/medisense/index.blade.php`

#### 4. User Actions & Form Validation
- Doctor inputs patient clinical scenario: `age`, `gender`, `chief_complaint`, `symptoms` (text), `vital_signs` (optional), `lab_findings` (optional).
- Form validation: `symptoms` (required, string, min 10 chars).

#### 5. Controller / Service Processing
- Handled by `App\Http\Controllers\MediSenseController`.
- Strict gate: `RoleMiddleware:doctor`. Any other role (including Super Admin and Admin) receives 403 Forbidden.
- AI engine evaluates inputs and generates clinical decision support (CDSS) suggestions:
  - Differential diagnoses (with probability ranking).
  - Recommended laboratory and diagnostic imaging investigations.
  - Evidence-based medication and management considerations.
- **Strict Clinical Invariant:** MediSense AI suggestions are strictly advisory. The AI **never** commits diagnoses to database, orders tests, writes prescriptions, or signs clinical notes. The attending physician must independently evaluate, verify, and execute any clinical action.

#### 6. Database Reads & Writes
- Reads: `patients` (optional context).
- Writes: `activity_logs` (logs interaction metadata for compliance; clinical prompt contents are not permanently stored to protect patient privacy).

#### 7. Status Transitions
- No state transitions on clinical records.

#### 8. Notifications & Audit Events
- Audit: `MediSense AI Clinical Consultation Executed` (`SEVERITY_INFO`).

#### 9. Success & Failure Outcomes
- Success: Interactive CDSS recommendations rendered in physician's secure workspace.
- Failure: 403 Forbidden for any non-physician account.

#### 10. Unauthorized-Access Behavior
- Super Admin, Admin, Staff, or Patient attempting to access `/medisense`: immediate 403 Forbidden.

#### 11. Data Privacy & Integrity
- No Protected Health Information (PHI) transmitted to unverified external services. Prompts are anonymized.

#### 12. Test Cases
- `test_doctor_has_access_to_medisense_ai`
- `test_super_admin_cannot_access_medisense`
- `test_staff_and_patient_cannot_access_medisense`

---

### Workflow 11: Audit Logs and System Configuration

#### 1. Actor & Required Permissions
- **Super Admin:** `role:super_admin`, permissions `settings.security.manage`, `audit.vault.view`, `audit.vault.export`.
- **Admin:** `role:admin`, permissions `settings.hospital.manage`, `audit.logs.view`.

#### 2. Preconditions
- User is authenticated with privileged administrator session.

#### 3. Starting Page & Route
- Super Admin Audit Vault: `GET /super-admin/audit-logs` (`super-admin.audit-logs.index`)
- Super Admin Backup: `GET /super-admin/backup` (`super-admin.backup.index`)
- Admin Hospital Settings: `GET /admin/hospital-settings` (`admin.hospital-settings.index`)

#### 4. User Actions & Form Validation
- Super Admin filters audit vault: by `user_id`, `severity`, `module`, `date_from`, `date_to`.
- Super Admin exports audit records to CSV.
- Super Admin configures system security parameters: session timeout, OTP policy, lockout thresholds.
- Admin updates facility profile: hospital name, contact address, operating hours.

#### 5. Controller / Service Processing
- `SuperAdmin\AuditVaultController` streams immutable audit records with SHA-256 tamper-evident integrity hashes.
- `SuperAdmin\BackupController` monitors database backup snapshots and prunes obsolete archives per retention policy.
- System settings stored in `hospital_settings` or system config cache.

#### 6. Database Reads & Writes
- Reads: `activity_logs`, `hospital_settings`, `users`.
- Writes: `hospital_settings`, `activity_logs`.
- **Integrity Rule:** `activity_logs` records cannot be updated or deleted by any user or administrator through the application.

#### 7. Status Transitions
- Backup Archive: `Created` -> `Retained` -> `Pruned`.

#### 8. Notifications & Audit Events
- Audit: `System Security Configuration Modified`, `Audit Vault Exported`, `Backup Archive Created`.

#### 9. Success & Failure Outcomes
- Success: Configuration updated; tamper-evident audit log rendered or exported.
- Failure: Validation failure on invalid configuration parameters; 403 on unauthorized access.

#### 10. Unauthorized-Access Behavior
- Admin attempting to access Super Admin Audit Vault or Backup: 403 Forbidden.
- Non-administrators attempting access to settings: 403 Forbidden.

#### 11. Data Privacy & Integrity
- Audit logs contain zero plain-text passwords, OTPs, or API secrets.

#### 12. Test Cases
- `test_super_admin_can_view_and_export_audit_vault`
- `test_admin_cannot_access_super_admin_audit_vault`
- `test_audit_records_are_immutable`

---

### Workflow 12: Operational Reports and Clinical Analytics

#### 1. Actor & Required Permissions
- **Super Admin:** Master system metrics, cross-department catalog utilization.
- **Admin:** Operational metrics, appointment throughput, staff activity, bed/queue utilization.
- **Doctor:** Clinical metrics, personal patient caseload, diagnostic turnaround times.

#### 2. Preconditions
- User has active authenticated session.

#### 3. Starting Page & Route
- Super Admin: `GET /super-admin/dashboard` (`super-admin.dashboard`)
- Admin: `GET /admin/operations` (`admin.operations.index`), `GET /admin/dashboard` (`admin.dashboard`)
- Doctor: `GET /doctor/dashboard` (`doctor.dashboard`)

#### 4. User Actions & Form Validation
- User selects reporting timeframe (`Today`, `This Week`, `This Month`, `Year to Date`).
- Filters by department or provider.

#### 5. Controller / Service Processing
- Aggregation queries executed via Eloquent / DB queries with proper index utilization.
- Strict data scoping:
  - Doctor dashboard only aggregates appointments, prescriptions, and lab orders authored by the authenticated physician.
  - Admin dashboard aggregates facility-wide operational statistics.
  - Super Admin dashboard displays system governance indicators.

#### 6. Database Reads & Writes
- Reads: `appointments`, `prescriptions`, `lab_requests`, `radiology_requests`, `users`, `activity_logs`.
- Writes: None (read-only analytical queries).

#### 7. Status Transitions
- None.

#### 8. Notifications & Audit Events
- Audit: Logged on formal export of analytical reports.

#### 9. Success & Failure Outcomes
- Success: Charts and KPI metrics rendered.
- Failure: 403 Forbidden if unauthorized role attempts access.

#### 10. Unauthorized-Access Behavior
- Patients or Staff attempting to view executive operations dashboards: 403 Forbidden.

#### 11. Data Privacy & Integrity
- Aggregated reports do not leak individual patient identifiers to unauthorized roles.

#### 12. Test Cases
- `test_doctor_dashboard_scopes_metrics_to_authenticated_doctor`
- `test_admin_can_view_operations_monitor`
- `test_staff_cannot_view_executive_operations`
