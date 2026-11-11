<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\DietPlan;
use App\Models\DietRequest;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * DNMS Doctor-Managed Workflow Authorization & Lifecycle Test Suite.
 *
 * Architecture: Doctor -> DNMS -> Patient Diet Plan.
 * No separate Dietitian account is required; Admin & Super Admin are read-only observers;
 * historical Dietitian records remain intact.
 */
class DnmsWorkflowAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    protected User $doctorA;
    protected User $doctorB;
    protected User $legacyDietitian;
    protected User $admin;
    protected User $superAdmin;
    protected User $medTech;

    protected Patient $patientA;
    protected Patient $patientB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureRolesAndDnmsPermissions();

        $uniq = uniqid();

        $this->doctorA         = $this->createUserWithRole('doctor', "dn_dr_a_{$uniq}@hospital.test", 'Dr. Alice Physician');
        $this->doctorB         = $this->createUserWithRole('doctor', "dn_dr_b_{$uniq}@hospital.test", 'Dr. Bob Physician');
        $this->legacyDietitian = $this->createUserWithRole('dietitian', "dn_diet_{$uniq}@hospital.test", 'Rosa Legacy Dietitian');
        $this->admin           = $this->createUserWithRole('admin', "dn_admin_{$uniq}@hospital.test", 'Adam Admin');
        $this->superAdmin      = $this->createUserWithRole('super_admin', "dn_super_{$uniq}@hospital.test", 'Sue Super');
        $this->medTech         = $this->createUserWithRole('med-tech', "dn_tech_{$uniq}@hospital.test", 'Tech Mike');

        $this->patientA = Patient::create([
            'patient_no'    => 'PAT-DA-' . rand(10000, 99999),
            'first_name'    => 'Arthur',
            'last_name'     => 'Pendleton',
            'date_of_birth' => '1980-04-12',
            'gender'        => 'Male',
            'patient_type'  => 'Inpatient',
        ]);

        $this->patientB = Patient::create([
            'patient_no'    => 'PAT-DB-' . rand(10000, 99999),
            'first_name'    => 'Beatrice',
            'last_name'     => 'Vance',
            'date_of_birth' => '1975-09-23',
            'gender'        => 'Female',
            'patient_type'  => 'Inpatient',
        ]);

        // Establish exclusive care relationship between Doctor B and Patient B
        Appointment::create([
            'appointment_no'   => 'APT-DNB-' . strtoupper($uniq),
            'patient_id'       => $this->patientB->id,
            'doctor_id'        => $this->doctorB->id,
            'department'       => 'Internal Medicine',
            'scheduled_at'     => now()->addDay(),
            'duration_minutes' => 30,
            'type'             => 'In-Person Consultation',
            'status'           => 'Confirmed',
            'reason'           => 'Nutrition consult with Dr. Bob',
        ]);
    }

    private function ensureRolesAndDnmsPermissions(): void
    {
        $roles = [
            ['name' => 'Super Administrator',  'slug' => 'super_admin', 'dashboard_route' => 'super-admin.dashboard'],
            ['name' => 'System Administrator', 'slug' => 'admin',       'dashboard_route' => 'admin.dashboard'],
            ['name' => 'Doctor',               'slug' => 'doctor',      'dashboard_route' => 'doctor.dashboard'],
            ['name' => 'Dietitian',            'slug' => 'dietitian',   'dashboard_route' => 'diet.dashboard'],
            ['name' => 'Medical Technologist', 'slug' => 'med-tech',    'dashboard_route' => 'lab.dashboard'],
        ];

        foreach ($roles as $r) {
            Role::firstOrCreate(['slug' => $r['slug']], $r);
        }

        $permissions = [
            ['slug' => 'dnms.requests.view',      'name' => 'View Diet Requests',          'module' => 'DNMS'],
            ['slug' => 'dnms.requests.create',    'name' => 'Create Diet Requests',        'module' => 'DNMS'],
            ['slug' => 'dnms.requests.manage',    'name' => 'Manage Own Diet Requests',    'module' => 'DNMS'],
            ['slug' => 'dnms.assessments.create', 'name' => 'Create Nutrition Assessments', 'module' => 'DNMS'],
            ['slug' => 'dnms.assessments.update', 'name' => 'Update Nutrition Assessments', 'module' => 'DNMS'],
            ['slug' => 'dnms.plans.view',         'name' => 'View Diet Plans',             'module' => 'DNMS'],
            ['slug' => 'dnms.plans.create',       'name' => 'Create Diet Plans',           'module' => 'DNMS'],
            ['slug' => 'dnms.plans.update',       'name' => 'Update Diet Plans',           'module' => 'DNMS'],
            ['slug' => 'dnms.plans.complete',     'name' => 'Complete Diet Plans',         'module' => 'DNMS'],
            ['slug' => 'dnms.reports.view',       'name' => 'View Nutrition Reports',      'module' => 'DNMS'],
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['slug' => $perm['slug']], $perm);
        }
    }

    private function createUserWithRole(string $roleSlug, string $email, string $name): User
    {
        $user = User::create([
            'name'        => $name,
            'email'       => $email,
            'password'    => bcrypt('Secr3t!P@ssword2026'),
            'employee_id' => 'EMP-' . rand(10000, 99999),
            'department'  => 'Clinical Medicine',
            'is_active'   => true,
        ]);
        $user->roles()->attach(Role::where('slug', $roleSlug)->firstOrFail()->id);

        return $user;
    }

    private function createDietRequest(User $doctor, Patient $patient, string $status = 'Pending'): DietRequest
    {
        return DietRequest::create([
            'request_no'        => 'DR-TEST-' . strtoupper(uniqid()),
            'patient_id'        => $patient->id,
            'doctor_id'         => $doctor->id,
            'diet_type'         => 'Diabetic',
            'allergies'         => 'Shellfish',
            'food_restrictions' => 'No added sugar',
            'clinical_notes'    => 'Type 2 DM, monitor HbA1c',
            'status'            => $status,
            'requested_at'      => now(),
        ]);
    }

    private function planPayload(DietRequest $req, array $override = []): array
    {
        return array_merge([
            'diet_request_id'  => $req->id,
            'assessment_notes' => 'BMI 31, elevated fasting glucose, high refined-carb intake.',
            'plan_details'     => 'Three balanced meals and two snacks, carbohydrate-controlled, no added sugar.',
            'total_calories'   => 1800,
            'protein_grams'    => 90,
            'carb_grams'       => 200,
            'fat_grams'        => 60,
            'start_date'       => now()->toDateString(),
        ], $override);
    }

    private function createPlan(DietRequest $req, ?User $author = null, string $status = 'Active'): DietPlan
    {
        $authorUser = $author ?? $req->doctor;

        $plan = DietPlan::create([
            'diet_request_id' => $req->id,
            'dietitian_id'    => $authorUser->id,
            'updated_by'      => $authorUser->id,
            'plan_details'    => 'Carbohydrate-controlled plan with measured portions.',
            'start_date'      => now()->toDateString(),
            'status'          => $status,
        ]);
        if ($req->status === 'Pending') {
            $req->update(['status' => 'Active']);
        }

        return $plan;
    }

    // ════════════════════════════════════════════════════════════════════════
    // 1. RBAC MATRIX & NO DIETITIAN REQUIREMENT
    // ════════════════════════════════════════════════════════════════════════

    public function test_doctor_has_full_dnms_permissions_and_dietitian_is_no_longer_required(): void
    {
        $this->assertSame(1, Role::where('slug', 'doctor')->count());
        $this->assertSame(0, Role::where('slug', 'dietary-staff')->count(), 'dietary-staff must not be created.');
        $this->assertSame(10, Permission::where('module', 'DNMS')->count());

        // Doctor owns the entire clinical DNMS workflow
        foreach ([
            'dnms.requests.view',
            'dnms.requests.create',
            'dnms.requests.manage',
            'dnms.assessments.create',
            'dnms.assessments.update',
            'dnms.plans.view',
            'dnms.plans.create',
            'dnms.plans.update',
            'dnms.plans.complete',
            'dnms.reports.view',
        ] as $perm) {
            $this->assertTrue($this->doctorA->hasPermission($perm), "Doctor should hold permission {$perm}");
        }

        // Separate Dietitian role holds no DNMS operational permissions
        foreach (Permission::where('module', 'DNMS')->pluck('slug') as $perm) {
            $this->assertFalse($this->legacyDietitian->hasPermission($perm), "Dietitian should not hold DNMS permission {$perm}");
        }

        // Admin is read-only; Super Admin has no automatic clinical permissions
        $this->assertTrue($this->admin->hasPermission('dnms.requests.view'));
        $this->assertTrue($this->admin->hasPermission('dnms.plans.view'));
        $this->assertTrue($this->admin->hasPermission('dnms.reports.view'));
        $this->assertFalse($this->admin->hasPermission('dnms.requests.create'));
        $this->assertFalse($this->admin->hasPermission('dnms.plans.create'));
        $this->assertFalse($this->superAdmin->hasPermission('dnms.plans.create'));
    }

    // ════════════════════════════════════════════════════════════════════════
    // 2. DOCTOR REQUEST CREATION & OWNERSHIP
    // ════════════════════════════════════════════════════════════════════════

    public function test_doctor_can_create_edit_and_cancel_diet_request_for_authorized_patient_with_activity_logs(): void
    {
        $this->actingAs($this->doctorA)->get(route('diet.requests.create'))->assertOk();

        $this->actingAs($this->doctorA)->post(route('diet.requests.store'), [
            'patient_id'        => $this->patientA->id,
            'diet_type'         => 'Renal',
            'allergies'         => 'Peanuts',
            'food_restrictions' => 'Low potassium',
            'clinical_notes'    => 'CKD stage 3',
        ])->assertRedirect(route('diet.requests.index'));

        $dr = DietRequest::where('doctor_id', $this->doctorA->id)->where('patient_id', $this->patientA->id)->firstOrFail();
        $this->assertSame('Pending', $dr->status);
        $this->assertSame('Renal', $dr->diet_type);

        $this->assertTrue(ActivityLog::where('action', 'Diet Request Created')
            ->where('loggable_id', $dr->id)
            ->where('user_id', $this->doctorA->id)
            ->exists());

        // Edit own pending request
        $this->actingAs($this->doctorA)->put(route('diet.requests.update', $dr), [
            'patient_id' => $this->patientA->id,
            'diet_type'  => 'Low-Sodium',
            'allergies'  => 'None',
        ])->assertRedirect(route('diet.requests.show', $dr));

        $this->assertSame('Low-Sodium', $dr->fresh()->diet_type);
        $this->assertTrue(ActivityLog::where('action', 'Diet Request Updated')
            ->where('loggable_id', $dr->id)
            ->where('user_id', $this->doctorA->id)
            ->exists());

        // Cancel own pending request
        $this->actingAs($this->doctorA)->delete(route('diet.requests.destroy', $dr))
            ->assertRedirect(route('diet.requests.index'));

        $this->assertSame('Cancelled', $dr->fresh()->status);
        $this->assertTrue(ActivityLog::where('action', 'Diet Request Cancelled')
            ->where('loggable_id', $dr->id)
            ->where('user_id', $this->doctorA->id)
            ->exists());
    }

    public function test_doctor_cannot_create_diet_request_for_another_doctors_restricted_patient(): void
    {
        $before = DietRequest::count();

        $this->actingAs($this->doctorA)->post(route('diet.requests.store'), [
            'patient_id' => $this->patientB->id,
            'diet_type'  => 'Cardiac',
        ])->assertForbidden();

        $this->assertSame($before, DietRequest::count());

        // Patient picker only lists authorized patients
        $this->actingAs($this->doctorA)->get(route('diet.requests.create'))
            ->assertOk()
            ->assertDontSee('Vance');
    }

    // ════════════════════════════════════════════════════════════════════════
    // 3. DOCTOR DIET PLAN CREATION, REVISION & COMPLETION (WITHOUT DIETITIAN)
    // ════════════════════════════════════════════════════════════════════════

    public function test_doctor_can_create_diet_plan_with_assessment_without_dietitian_account(): void
    {
        $reqA = $this->createDietRequest($this->doctorA, $this->patientA);

        $this->actingAs($this->doctorA)
            ->get(route('diet.plans.create', ['diet_request_id' => $reqA->id]))
            ->assertOk()
            ->assertSee($reqA->request_no);

        $this->actingAs($this->doctorA)
            ->post(route('diet.plans.store'), $this->planPayload($reqA))
            ->assertRedirect();

        $plan = DietPlan::where('diet_request_id', $reqA->id)->firstOrFail();
        $this->assertSame('Active', $plan->status);
        $this->assertSame($this->doctorA->id, (int) $plan->dietitian_id);
        $this->assertSame($this->doctorA->id, (int) $plan->updated_by);
        $this->assertStringContainsString('BMI 31', $plan->assessment_notes);
        $this->assertSame('Active', $reqA->fresh()->status);

        // Activity logs identify the acting Doctor
        $this->assertTrue(ActivityLog::where('action', 'Diet Plan Created')
            ->where('loggable_id', $plan->id)
            ->where('user_id', $this->doctorA->id)
            ->exists());
        $this->assertTrue(ActivityLog::where('action', 'Nutrition Assessment Recorded')
            ->where('loggable_id', $plan->id)
            ->where('user_id', $this->doctorA->id)
            ->exists());

        $this->actingAs($this->doctorA)->get(route('diet.plans.show', $plan))
            ->assertOk()
            ->assertSee('Nutrition Assessment')
            ->assertSee($plan->plan_no);

        $this->actingAs($this->doctorA)->get(route('diet.plans.print', $plan))
            ->assertOk()
            ->assertSee('BMI 31');
    }

    public function test_doctor_can_edit_and_complete_own_eligible_diet_plan_with_doctor_attribution(): void
    {
        $req  = $this->createDietRequest($this->doctorA, $this->patientA);
        $plan = $this->createPlan($req, $this->doctorA);

        $this->actingAs($this->doctorA)->get(route('diet.plans.edit', $plan))->assertOk();

        $this->actingAs($this->doctorA)->put(route('diet.plans.update', $plan), [
            'plan_details'     => 'Revised by attending physician: increase fibre, reduce simple sugars, six small meals.',
            'assessment_notes' => 'Follow-up assessment: fasting glucose improving, maintain caloric target.',
            'start_date'       => now()->toDateString(),
        ])->assertRedirect(route('diet.plans.show', $plan));

        $plan->refresh();
        $this->assertSame('Revised', $plan->status);
        $this->assertSame($this->doctorA->id, (int) $plan->updated_by);
        $this->assertTrue(ActivityLog::where('action', 'Diet Plan Updated')
            ->where('loggable_id', $plan->id)
            ->where('user_id', $this->doctorA->id)
            ->exists());
        $this->assertTrue(ActivityLog::where('action', 'Nutrition Assessment Updated')
            ->where('loggable_id', $plan->id)
            ->where('user_id', $this->doctorA->id)
            ->exists());

        $this->actingAs($this->doctorA)->patch(route('diet.plans.complete', $plan))->assertRedirect();

        $plan->refresh();
        $this->assertSame('Completed', $plan->status);
        $this->assertSame($this->doctorA->id, (int) $plan->completed_by);
        $this->assertNotNull($plan->completed_at);
        $this->assertSame('Completed', $req->fresh()->status);
        $this->assertTrue(ActivityLog::where('action', 'Diet Plan Completed')
            ->where('loggable_id', $plan->id)
            ->where('user_id', $this->doctorA->id)
            ->exists());
    }

    // ════════════════════════════════════════════════════════════════════════
    // 4. CROSS-DOCTOR & DIRECT URL ISOLATION
    // ════════════════════════════════════════════════════════════════════════

    public function test_doctor_cannot_access_or_modify_another_doctors_requests_or_plans_via_direct_urls(): void
    {
        $reqBPending = $this->createDietRequest($this->doctorB, $this->patientB);
        $reqBActive  = $this->createDietRequest($this->doctorB, $this->patientB);
        $planB       = $this->createPlan($reqBActive, $this->doctorB);

        // Request isolation
        $this->actingAs($this->doctorA)->get(route('diet.requests.show', $reqBPending))->assertForbidden();
        $this->actingAs($this->doctorA)->get(route('diet.requests.edit', $reqBPending))->assertForbidden();
        $this->actingAs($this->doctorA)->put(route('diet.requests.update', $reqBPending), [
            'patient_id' => $this->patientB->id,
            'diet_type'  => 'Liquid',
        ])->assertForbidden();
        $this->actingAs($this->doctorA)->delete(route('diet.requests.destroy', $reqBPending))->assertForbidden();

        // Cannot create a diet plan for Doctor B's pending request
        $this->actingAs($this->doctorA)->get(route('diet.plans.create', ['diet_request_id' => $reqBPending->id]))->assertForbidden();
        $this->actingAs($this->doctorA)->post(route('diet.plans.store'), $this->planPayload($reqBPending))->assertForbidden();
        $this->assertNull(DietPlan::where('diet_request_id', $reqBPending->id)->first());

        // Plan isolation (view, print, edit, update, complete, delete)
        $this->actingAs($this->doctorA)->get(route('diet.plans.show', $planB))->assertForbidden();
        $this->actingAs($this->doctorA)->get(route('diet.plans.print', $planB))->assertForbidden();
        $this->actingAs($this->doctorA)->get(route('diet.plans.edit', $planB))->assertForbidden();
        $this->actingAs($this->doctorA)->put(route('diet.plans.update', $planB), $this->planPayload($reqBActive))->assertForbidden();
        $this->actingAs($this->doctorA)->patch(route('diet.plans.complete', $planB))->assertForbidden();
        $this->actingAs($this->doctorA)->delete(route('diet.plans.destroy', $planB))->assertForbidden();

        // Patient record direct URL isolation
        $this->actingAs($this->doctorA)->get(route('patients.show', $this->patientB))->assertForbidden();

        $this->assertSame('Pending', $reqBPending->fresh()->status);
        $this->assertSame('Active', $planB->fresh()->status);
    }

    public function test_doctor_index_pages_only_list_own_requests_and_plans(): void
    {
        $pendingA = $this->createDietRequest($this->doctorA, $this->patientA);
        $reqA     = $this->createDietRequest($this->doctorA, $this->patientA);
        $planA    = $this->createPlan($reqA, $this->doctorA);

        $pendingB = $this->createDietRequest($this->doctorB, $this->patientB);
        $reqB     = $this->createDietRequest($this->doctorB, $this->patientB);
        $planB    = $this->createPlan($reqB, $this->doctorB);

        $this->actingAs($this->doctorA)->get(route('diet.requests.index'))
            ->assertOk()
            ->assertSee($pendingA->request_no)
            ->assertSee($reqA->request_no)
            ->assertDontSee($pendingB->request_no)
            ->assertDontSee($reqB->request_no);

        // Unified Diet Plans workspace shows Doctor A's pending requests and plans, never Doctor B's
        $this->actingAs($this->doctorA)->get(route('diet.plans.index'))
            ->assertOk()
            ->assertSee('Pending Requests Awaiting Diet Plan')
            ->assertSee($pendingA->request_no)
            ->assertSee($planA->plan_no)
            ->assertDontSee($pendingB->request_no)
            ->assertDontSee($planB->plan_no);

        // Status filtering on Diet Plans workspace (Active, Revised, Completed)
        $revisedReq  = $this->createDietRequest($this->doctorA, $this->patientA);
        $revisedPlan = $this->createPlan($revisedReq, $this->doctorA, 'Revised');

        $completedReq  = $this->createDietRequest($this->doctorA, $this->patientA);
        $completedPlan = $this->createPlan($completedReq, $this->doctorA, 'Completed');

        // Cancelled request without a plan must not be counted as a Pending request or in All Plans & Requests
        $this->createDietRequest($this->doctorA, $this->patientA, 'Cancelled');

        // Verify dynamic tabCounts for Doctor A (1 pending + 1 Active + 1 Revised + 1 Completed = 4 total)
        $docWorkspace = $this->actingAs($this->doctorA)->get(route('diet.plans.index'))->assertOk();
        $this->assertSame([
            'all'       => 4,
            'pending'   => 1,
            'Active'    => 1,
            'Revised'   => 1,
            'Completed' => 1,
        ], $docWorkspace->viewData('tabCounts'));

        // Verify refined UI workspace structure, semantic status badges, and accessible action buttons for Doctor
        $docWorkspace
            ->assertSee('dnms-workspace', false)
            ->assertSee('id="dnms-workflow-tabs"', false)
            ->assertSee('id="pending-requests-section"', false)
            ->assertSee('id="filter-form"', false)
            ->assertSee('id="filter-search"', false)
            ->assertSee('id="filter-status"', false)
            ->assertSee('id="diet-plans-table-body"', false)
            ->assertSee('dnms-badge-Pending', false)
            ->assertSee('dnms-badge-Active', false)
            ->assertSee('dnms-badge-Revised', false)
            ->assertSee('dnms-badge-Completed', false)
            ->assertSee('dnms-badge-diet', false)
            ->assertSee('New Diet Request')
            ->assertSee('New Diet Plan')
            ->assertSee('Create Plan');

        // Verify dynamic tabCounts for Admin across all doctors (2 pending + 2 Active + 1 Revised + 1 Completed = 6 total)
        $adminWorkspace = $this->actingAs($this->admin)->get(route('diet.plans.index'))->assertOk();
        $this->assertSame([
            'all'       => 6,
            'pending'   => 2,
            'Active'    => 2,
            'Revised'   => 1,
            'Completed' => 1,
        ], $adminWorkspace->viewData('tabCounts'));

        // Admin read-only workspace omits mutation buttons while keeping View and Print actions
        $adminWorkspace
            ->assertDontSee('New Diet Request')
            ->assertDontSee('New Diet Plan')
            ->assertDontSee('Create Plan')
            ->assertSee('View Diet Plan Details', false)
            ->assertSee('Print Diet Plan', false);

        $this->actingAs($this->doctorA)->get(route('diet.plans.index', ['status' => 'Active']))
            ->assertOk()
            ->assertSee($planA->plan_no)
            ->assertDontSee($revisedPlan->plan_no)
            ->assertDontSee($completedPlan->plan_no);

        $this->actingAs($this->doctorA)->get(route('diet.plans.index', ['status' => 'Revised']))
            ->assertOk()
            ->assertSee($revisedPlan->plan_no)
            ->assertDontSee($planA->plan_no)
            ->assertDontSee($completedPlan->plan_no);

        $this->actingAs($this->doctorA)->get(route('diet.plans.index', ['status' => 'Completed']))
            ->assertOk()
            ->assertSee($completedPlan->plan_no)
            ->assertDontSee($planA->plan_no)
            ->assertDontSee($revisedPlan->plan_no);
    }

    // ════════════════════════════════════════════════════════════════════════
    // 5. ADMIN & SUPER ADMIN READ-ONLY ENFORCEMENT
    // ════════════════════════════════════════════════════════════════════════

    public function test_admin_and_super_admin_can_view_but_cannot_modify_diet_requests_or_plans(): void
    {
        $req  = $this->createDietRequest($this->doctorA, $this->patientA);
        $req2 = $this->createDietRequest($this->doctorA, $this->patientA);
        $plan = $this->createPlan($req2, $this->doctorA);

        foreach ([$this->admin, $this->superAdmin] as $user) {
            // Read-only monitoring is allowed
            $this->actingAs($user)->get(route('diet.dashboard'))->assertOk();
            $this->actingAs($user)->get(route('diet.requests.index'))->assertOk();
            $this->actingAs($user)->get(route('diet.requests.show', $req))->assertOk();
            $this->actingAs($user)->get(route('diet.plans.index'))->assertOk();
            $this->actingAs($user)->get(route('diet.plans.show', $plan))->assertOk();
            $this->actingAs($user)->get(route('diet.plans.print', $plan))->assertOk();
            $this->actingAs($user)->get(route('reports.diet.activity'))->assertOk();

            // Clinical mutations are strictly forbidden
            $this->actingAs($user)->get(route('diet.requests.create'))->assertForbidden();
            $this->actingAs($user)->post(route('diet.requests.store'), ['patient_id' => $this->patientA->id, 'diet_type' => 'Regular'])->assertForbidden();
            $this->actingAs($user)->get(route('diet.requests.edit', $req))->assertForbidden();
            $this->actingAs($user)->put(route('diet.requests.update', $req), ['patient_id' => $this->patientA->id, 'diet_type' => 'Regular'])->assertForbidden();
            $this->actingAs($user)->delete(route('diet.requests.destroy', $req))->assertForbidden();

            $this->actingAs($user)->get(route('diet.plans.create'))->assertForbidden();
            $this->actingAs($user)->post(route('diet.plans.store'), $this->planPayload($req))->assertForbidden();
            $this->actingAs($user)->get(route('diet.plans.edit', $plan))->assertForbidden();
            $this->actingAs($user)->put(route('diet.plans.update', $plan), $this->planPayload($req2))->assertForbidden();
            $this->actingAs($user)->patch(route('diet.plans.complete', $plan))->assertForbidden();
            $this->actingAs($user)->delete(route('diet.plans.destroy', $plan))->assertForbidden();
        }

        $this->assertSame('Pending', $req->fresh()->status);
        $this->assertSame('Active', $plan->fresh()->status);
        $this->assertNull(DietPlan::where('diet_request_id', $req->id)->first());
    }

    // ════════════════════════════════════════════════════════════════════════
    // 6. LEGACY DIETITIAN ROLE ACCESS REVOCATION & HISTORICAL RECORD PRESERVATION
    // ════════════════════════════════════════════════════════════════════════

    public function test_dietitian_role_cannot_access_dnms_endpoints_while_historical_records_remain_intact(): void
    {
        // Simulate a historical diet plan created by a legacy Dietitian for Doctor A's request
        $historicalReq  = $this->createDietRequest($this->doctorA, $this->patientA);
        $historicalPlan = $this->createPlan($historicalReq, $this->legacyDietitian);

        // Separate Dietitian account no longer has DNMS route access
        $this->actingAs($this->legacyDietitian)->get(route('diet.dashboard'))->assertForbidden();
        $this->actingAs($this->legacyDietitian)->get(route('diet.requests.index'))->assertForbidden();
        $this->actingAs($this->legacyDietitian)->get(route('diet.requests.show', $historicalReq))->assertForbidden();
        $this->actingAs($this->legacyDietitian)->get(route('diet.plans.index'))->assertForbidden();
        $this->actingAs($this->legacyDietitian)->get(route('diet.plans.create'))->assertForbidden();
        $this->actingAs($this->legacyDietitian)->get(route('diet.plans.show', $historicalPlan))->assertForbidden();
        $this->actingAs($this->legacyDietitian)->get(route('diet.plans.edit', $historicalPlan))->assertForbidden();
        $this->actingAs($this->legacyDietitian)->patch(route('diet.plans.complete', $historicalPlan))->assertForbidden();
        $this->actingAs($this->legacyDietitian)->get(route('reports.diet.activity'))->assertForbidden();

        // Historical record and legacy Dietitian user remain intact, and ordering Doctor A can view and manage the plan
        $this->assertNotNull(User::find($this->legacyDietitian->id));
        $this->assertSame($this->legacyDietitian->id, (int) $historicalPlan->fresh()->dietitian_id);

        // Also create a new Doctor-managed plan to verify Created By attribution for both historical and new plans
        $doctorReq  = $this->createDietRequest($this->doctorA, $this->patientA);
        $doctorPlan = $this->createPlan($doctorReq, $this->doctorA);

        $this->actingAs($this->doctorA)->get(route('diet.plans.index'))
            ->assertOk()
            ->assertSee('Created By')
            ->assertSee($this->legacyDietitian->name)
            ->assertSee($this->doctorA->name);

        $this->actingAs($this->doctorA)->get(route('diet.plans.show', $historicalPlan))
            ->assertOk()
            ->assertSee('Created By')
            ->assertSee($this->legacyDietitian->name);

        $this->actingAs($this->doctorA)->get(route('diet.plans.show', $doctorPlan))
            ->assertOk()
            ->assertSee('Created By')
            ->assertSee($this->doctorA->name);

        $this->actingAs($this->doctorA)->patch(route('diet.plans.complete', $historicalPlan))->assertRedirect();
        $this->assertSame('Completed', $historicalPlan->fresh()->status);
        $this->assertSame($this->doctorA->id, (int) $historicalPlan->fresh()->completed_by);
    }

    // ════════════════════════════════════════════════════════════════════════
    // 7. COMPLETED PLAN IMMUTABILITY, DUPLICATE PREVENTION & STATUS TRANSITIONS
    // ════════════════════════════════════════════════════════════════════════

    public function test_completed_plan_is_immutable_and_cannot_be_edited_completed_or_deleted(): void
    {
        $req  = $this->createDietRequest($this->doctorA, $this->patientA);
        $plan = $this->createPlan($req, $this->doctorA);

        $this->actingAs($this->doctorA)->patch(route('diet.plans.complete', $plan))->assertRedirect();
        $this->assertSame('Completed', $plan->fresh()->status);
        $completedAt = $plan->fresh()->completed_at;

        $this->actingAs($this->doctorA)->get(route('diet.plans.edit', $plan))->assertForbidden();
        $this->actingAs($this->doctorA)->put(route('diet.plans.update', $plan), $this->planPayload($req, ['plan_details' => 'Tampered plan details after completion.']))->assertForbidden();
        $this->actingAs($this->doctorA)->patch(route('diet.plans.complete', $plan))->assertForbidden();
        $this->actingAs($this->doctorA)->delete(route('diet.plans.destroy', $plan))->assertForbidden();

        $plan->refresh();
        $this->assertSame('Completed', $plan->status);
        $this->assertStringNotContainsString('Tampered', $plan->plan_details);
        $this->assertEquals($completedAt, $plan->completed_at);
    }

    public function test_cannot_create_duplicate_plan_or_plan_for_non_pending_request(): void
    {
        $req = $this->createDietRequest($this->doctorA, $this->patientA);
        $this->createPlan($req, $this->doctorA);

        // Request already has a plan (Active) -> validation error, not SQL 500
        $this->actingAs($this->doctorA)->post(route('diet.plans.store'), $this->planPayload($req))
            ->assertSessionHasErrors('diet_request_id');
        $this->assertSame(1, DietPlan::where('diet_request_id', $req->id)->count());

        // Cancelled request cannot receive a plan
        $cancelled = $this->createDietRequest($this->doctorA, $this->patientA, 'Cancelled');
        $this->actingAs($this->doctorA)->post(route('diet.plans.store'), $this->planPayload($cancelled))
            ->assertSessionHasErrors('diet_request_id');
        $this->assertNull(DietPlan::where('diet_request_id', $cancelled->id)->first());
    }

    public function test_diet_plan_creation_entry_points_preselection_locking_and_duplicate_prevention(): void
    {
        $reqA1 = $this->createDietRequest($this->doctorA, $this->patientA);
        $reqA2 = DietRequest::create([
            'request_no'        => 'DR-TEST-A2-' . strtoupper(uniqid()),
            'patient_id'        => $this->patientA->id,
            'doctor_id'         => $this->doctorA->id,
            'diet_type'         => 'Renal',
            'allergies'         => 'Tree nuts,Soy',
            'food_restrictions' => 'Low potassium, fluid < 1.5L',
            'clinical_notes'    => 'Stage 4 CKD nutrition consult',
            'status'            => 'Pending',
            'requested_at'      => now(),
        ]);
        $reqB        = $this->createDietRequest($this->doctorB, $this->patientB);
        $cancelledA  = $this->createDietRequest($this->doctorA, $this->patientA, 'Cancelled');
        $alreadyPlan = $this->createDietRequest($this->doctorA, $this->patientA);
        $this->createPlan($alreadyPlan, $this->doctorA);

        // ── Entry Point 1: /diet/plans/create?diet_request_id={id} (Specific Pending Request) ──
        $preselectedResp = $this->actingAs($this->doctorA)
            ->get(route('diet.plans.create', ['diet_request_id' => $reqA2->id]))
            ->assertOk();

        $this->assertTrue($preselectedResp->viewData('isRequestLocked'));
        $this->assertSame($reqA2->id, $preselectedResp->viewData('selectedRequest')->id);

        // Displays patient, requesting Doctor, diet type, allergies, restrictions, and locked hidden inputs
        $preselectedResp
            ->assertSee($reqA2->request_no)
            ->assertSee('Pendleton, Arthur')
            ->assertSee($this->patientA->patient_no)
            ->assertSee($this->doctorA->name)
            ->assertSee('Renal')
            ->assertSee('Tree nuts,Soy')
            ->assertSee('Low potassium, fluid &lt; 1.5L', false)
            ->assertSee('Stage 4 CKD nutrition consult')
            ->assertSee('name="preselected_request_id" value="' . $reqA2->id . '"', false)
            ->assertSee('name="diet_request_id" value="' . $reqA2->id . '"', false);

        // Non-existent request ID returns 404
        $this->actingAs($this->doctorA)
            ->get(route('diet.plans.create', ['diet_request_id' => 999999]))
            ->assertNotFound();

        // Another doctor's request returns 403
        $this->actingAs($this->doctorA)
            ->get(route('diet.plans.create', ['diet_request_id' => $reqB->id]))
            ->assertForbidden();

        // Request that already has a plan redirects to index with diet_request_id error
        $this->actingAs($this->doctorA)
            ->get(route('diet.plans.create', ['diet_request_id' => $alreadyPlan->id]))
            ->assertRedirect(route('diet.plans.index'))
            ->assertSessionHasErrors('diet_request_id');

        // Non-pending (Cancelled) request redirects to index with diet_request_id error
        $this->actingAs($this->doctorA)
            ->get(route('diet.plans.create', ['diet_request_id' => $cancelledA->id]))
            ->assertRedirect(route('diet.plans.index'))
            ->assertSessionHasErrors('diet_request_id');

        // Tampering with a preselected request ID to point to another doctor's request -> 403 Forbidden
        $this->actingAs($this->doctorA)
            ->post(route('diet.plans.store'), $this->planPayload($reqA2, [
                'preselected_request_id' => $reqA2->id,
                'diet_request_id'        => $reqB->id,
            ]))
            ->assertForbidden();

        // Tampering with a preselected request ID to switch to a different request -> validation error
        $this->actingAs($this->doctorA)
            ->post(route('diet.plans.store'), $this->planPayload($reqA2, [
                'preselected_request_id' => $reqA2->id,
                'diet_request_id'        => $reqA1->id,
            ]))
            ->assertSessionHasErrors('diet_request_id');

        // Valid submission from Entry Point 1 creates the plan
        $this->actingAs($this->doctorA)
            ->post(route('diet.plans.store'), $this->planPayload($reqA2, [
                'preselected_request_id' => $reqA2->id,
                'diet_request_id'        => $reqA2->id,
            ]))
            ->assertRedirect();
        $this->assertSame(1, DietPlan::where('diet_request_id', $reqA2->id)->count());

        // Duplicate / concurrent second submission for the same request is rejected cleanly
        $this->actingAs($this->doctorA)
            ->post(route('diet.plans.store'), $this->planPayload($reqA2, [
                'preselected_request_id' => $reqA2->id,
                'diet_request_id'        => $reqA2->id,
            ]))
            ->assertSessionHasErrors('diet_request_id');
        $this->assertSame(1, DietPlan::where('diet_request_id', $reqA2->id)->count());

        // ── Entry Point 2: /diet/plans/create (No request ID supplied) ──
        $openResp = $this->actingAs($this->doctorA)
            ->get(route('diet.plans.create'))
            ->assertOk();

        $this->assertFalse($openResp->viewData('isRequestLocked'));
        $this->assertNull($openResp->viewData('selectedRequest'));

        // Lists only Doctor A's remaining eligible pending request ($reqA1); excludes $reqA2 (now Active), $alreadyPlan, $cancelledA, and $reqB
        $openResp
            ->assertSee($reqA1->request_no)
            ->assertSee('data-allergies="Shellfish"', false)
            ->assertSee('data-restrictions="No added sugar"', false)
            ->assertDontSee($reqA2->request_no)
            ->assertDontSee($alreadyPlan->request_no)
            ->assertDontSee($cancelledA->request_no)
            ->assertDontSee($reqB->request_no);

        // Submitting Entry Point 2 with a selected eligible request creates the plan
        $this->actingAs($this->doctorA)
            ->post(route('diet.plans.store'), $this->planPayload($reqA1))
            ->assertRedirect();
        $this->assertSame(1, DietPlan::where('diet_request_id', $reqA1->id)->count());
    }

    public function test_doctor_cannot_edit_or_cancel_request_once_plan_is_created_unless_plan_is_reverted(): void
    {
        $req  = $this->createDietRequest($this->doctorA, $this->patientA);
        $plan = $this->createPlan($req, $this->doctorA);
        $this->assertSame('Active', $req->fresh()->status);

        $this->actingAs($this->doctorA)->get(route('diet.requests.edit', $req))->assertForbidden();
        $this->actingAs($this->doctorA)->put(route('diet.requests.update', $req), [
            'patient_id' => $this->patientA->id,
            'diet_type'  => 'Regular',
        ])->assertForbidden();
        $this->actingAs($this->doctorA)->delete(route('diet.requests.destroy', $req))->assertForbidden();

        // Reverting uncompleted plan returns request to Pending and logs Doctor attribution
        $planId = $plan->id;
        $this->actingAs($this->doctorA)->delete(route('diet.plans.destroy', $plan))
            ->assertRedirect(route('diet.plans.index'));

        $this->assertNull(DietPlan::find($planId));
        $this->assertSame('Pending', $req->fresh()->status);
        $this->assertTrue(ActivityLog::where('action', 'Diet Plan Removed')
            ->where('loggable_id', $planId)
            ->where('user_id', $this->doctorA->id)
            ->exists());
    }

    // ════════════════════════════════════════════════════════════════════════
    // 8. DASHBOARD, REPORTS & SIDEBAR NAVIGATION
    // ════════════════════════════════════════════════════════════════════════

    public function test_dashboard_and_nutrition_reports_display_correctly_and_are_scoped_for_doctor(): void
    {
        $own   = $this->createDietRequest($this->doctorA, $this->patientA);
        $planA = $this->createPlan($own, $this->doctorA);

        $other = $this->createDietRequest($this->doctorB, $this->patientB);
        $planB = $this->createPlan($other, $this->doctorB);

        $resp = $this->actingAs($this->doctorA)->get(route('diet.dashboard'))->assertOk();
        $this->assertSame(1, $resp->viewData('stats')['total_requests']);
        $this->assertSame(1, $resp->viewData('stats')['active_plans']);

        $this->actingAs($this->doctorA)->get(route('reports.diet.activity'))
            ->assertOk()
            ->assertSee($own->request_no)
            ->assertDontSee($other->request_no);

        $this->actingAs($this->doctorA)->get(route('reports.diet.active'))
            ->assertOk()
            ->assertSee($planA->plan_no)
            ->assertDontSee($planB->plan_no);

        // Admin sees both doctors' records in reports
        $this->actingAs($this->admin)->get(route('reports.diet.activity'))
            ->assertOk()
            ->assertSee($own->request_no)
            ->assertSee($other->request_no);
    }

    public function test_sidebar_navigation_reflects_doctor_managed_dnms_and_excludes_dietitian_sidebar(): void
    {
        // Doctor sidebar contains DNMS Overview, Diet Plans, and Nutrition Reports, without separate Diet Requests link
        $this->actingAs($this->doctorA);
        $docSidebar = view('partials._sidebar')->render();
        $this->assertStringContainsString('Nutrition <span class="sb-badge">DNMS</span>', $docSidebar);
        $this->assertStringContainsString(route('diet.dashboard'), $docSidebar);
        $this->assertStringContainsString(route('diet.plans.index'), $docSidebar);
        $this->assertStringContainsString(route('reports.diet.activity'), $docSidebar);
        $this->assertStringNotContainsString(route('diet.requests.index'), $docSidebar);
        $this->assertStringNotContainsString('DIET &amp; NUTRITION (DNMS)', $docSidebar);

        // Admin sidebar also excludes the separate Diet Requests sidebar link while keeping Overview, Diet Plans & Nutrition Reports
        $this->actingAs($this->admin);
        $admSidebar = view('partials._sidebar')->render();
        $this->assertStringContainsString(route('diet.dashboard'), $admSidebar);
        $this->assertStringContainsString(route('diet.plans.index'), $admSidebar);
        $this->assertStringContainsString(route('reports.diet.activity'), $admSidebar);
        $this->assertStringNotContainsString(route('diet.requests.index'), $admSidebar);

        // Non-DNMS staff cannot access DNMS URLs directly
        $this->actingAs($this->medTech)->get(route('diet.dashboard'))->assertForbidden();
        $this->actingAs($this->medTech)->get(route('diet.requests.index'))->assertForbidden();
        $this->actingAs($this->medTech)->get(route('diet.plans.index'))->assertForbidden();
        $this->actingAs($this->medTech)->get(route('reports.diet.activity'))->assertForbidden();
    }
}
