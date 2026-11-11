<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\LabTestCategory;
use App\Models\LabRequest;
use App\Models\LabRequestItem;
use App\Models\LabResult;
use App\Models\LabTest;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\RadiologyReport;
use App\Models\RadiologyRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DiagnosticServicesWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    protected User $doctorA;
    protected User $doctorB;
    protected User $medTech;
    protected User $medTechVerifier;
    protected User $radTech;
    protected User $radiologist;
    protected Patient $patientA;
    protected Patient $patientB;
    protected LabTest $cbcTest;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureDiagnosticRolesAndPermissions();

        $uniq = uniqid();

        // Doctors
        $this->doctorA = $this->createUserWithRole('doctor', "doctorA_{$uniq}@hospital.test", 'Dr. Alice Order');
        $this->doctorB = $this->createUserWithRole('doctor', "doctorB_{$uniq}@hospital.test", 'Dr. Bob Other');

        // Standard Lab Staff: med-tech without validation/release permissions
        $this->medTech = $this->createUserWithRole('med-tech', "medtech_{$uniq}@hospital.test", 'Tech Standard');

        // Senior Lab Staff: med-tech WITH validation and release permissions via senior-med-tech role
        $this->medTechVerifier = $this->createUserWithRole('med-tech', "medtech_senior_{$uniq}@hospital.test", 'Tech Senior Verifier');
        $seniorRole = Role::firstOrCreate(
            ['slug' => 'senior-med-tech'],
            ['name' => 'Senior Medical Technologist', 'dashboard_route' => 'lab.dashboard']
        );
        $this->attachPermissionToRole($seniorRole, 'lis.results.verify');
        $this->attachPermissionToRole($seniorRole, 'lis.results.release');
        $this->medTechVerifier->roles()->attach($seniorRole->id);

        // Radiology Staff: rad-tech
        $this->radTech = $this->createUserWithRole('rad-tech', "radtech_{$uniq}@hospital.test", 'Rad Tech Imaging');

        // Radiologist: radiologist
        $this->radiologist = $this->createUserWithRole('radiologist', "radiologist_{$uniq}@hospital.test", 'Dr. Carol Radiologist');

        // Patients
        $this->patientA = Patient::create([
            'patient_no'    => 'P-A-' . rand(10000, 99999),
            'first_name'    => 'John',
            'last_name'     => 'Doe',
            'date_of_birth' => '1985-05-15',
            'gender'        => 'Male',
            'patient_type'  => 'Inpatient',
        ]);

        $this->patientB = Patient::create([
            'patient_no'    => 'P-B-' . rand(10000, 99999),
            'first_name'    => 'Jane',
            'last_name'     => 'Smith',
            'date_of_birth' => '1992-08-20',
            'gender'        => 'Female',
            'patient_type'  => 'Outpatient',
        ]);

        // Lab Test
        $category = LabTestCategory::firstOrCreate(
            ['code' => 'HEMA'],
            ['name' => 'Hematology', 'description' => 'Hematology lab department', 'is_active' => true]
        );

        $this->cbcTest = LabTest::firstOrCreate(
            ['code' => 'CBC-01'],
            [
                'name'                 => 'Complete Blood Count',
                'lab_test_category_id' => $category->id,
                'unit'                 => 'cells/mcL',
                'normal_range'         => '4.5 - 11.0',
                'price'                => 350.00,
                'is_active'            => true,
            ]
        );
    }

    /* ════════════════════════════════════════════════════════════════════════
       1. DOCTOR DIAGNOSTIC WORKFLOW & RESTRICTIONS
       ════════════════════════════════════════════════════════════════════════ */

    public function test_doctor_can_create_lab_request_and_activity_is_logged(): void
    {
        $response = $this->actingAs($this->doctorA)->post(route('lab.requests.store'), [
            'patient_id'     => $this->patientA->id,
            'priority'       => 'Routine',
            'specimen_type'  => 'Whole Blood (EDTA)',
            'clinical_notes' => 'Routine annual health checkup',
            'tests'          => [$this->cbcTest->id],
        ]);

        $response->assertRedirect(route('lab.requests.index'));

        $this->assertDatabaseHas('lab_requests', [
            'patient_id' => $this->patientA->id,
            'doctor_id'  => $this->doctorA->id,
            'status'     => 'Pending',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $this->doctorA->id,
            'action'  => 'Lab Request Created',
            'module'  => 'Laboratory',
        ]);
    }

    public function test_doctor_cannot_view_or_tamper_another_doctors_lab_request(): void
    {
        $reqA = LabRequest::create([
            'request_no'     => 'LR-2026-' . rand(1000, 9999),
            'patient_id'     => $this->patientA->id,
            'doctor_id'      => $this->doctorA->id,
            'priority'       => 'Routine',
            'specimen_type'  => 'Blood',
            'clinical_notes' => 'Doctor A confidential consult',
            'status'         => 'Pending',
            'requested_at'   => now(),
        ]);

        // Doctor B accessing Doctor A's lab request -> 403 Forbidden
        $response = $this->actingAs($this->doctorB)->get(route('lab.requests.show', $reqA));
        $response->assertStatus(403);

        // Doctor A can access their own request -> 200 OK
        $responseOwn = $this->actingAs($this->doctorA)->get(route('lab.requests.show', $reqA));
        $responseOwn->assertStatus(200);
    }

    public function test_doctor_cannot_process_specimen_or_enter_results(): void
    {
        $req = LabRequest::create([
            'request_no'     => 'LR-2026-' . rand(1000, 9999),
            'patient_id'     => $this->patientA->id,
            'doctor_id'      => $this->doctorA->id,
            'priority'       => 'Routine',
            'specimen_type'  => 'Blood',
            'status'         => 'Pending',
            'requested_at'   => now(),
        ]);

        $item = LabRequestItem::create([
            'lab_request_id' => $req->id,
            'lab_test_id'    => $this->cbcTest->id,
            'status'         => 'Pending',
        ]);

        // Doctor cannot receive specimen -> 403
        $this->actingAs($this->doctorA)
             ->patch(route('lab.requests.receive', $req))
             ->assertStatus(403);

        // Doctor cannot reject specimen -> 403
        $this->actingAs($this->doctorA)
             ->patch(route('lab.requests.reject', $req), ['rejection_reason' => 'Specimen hemolyzed'])
             ->assertStatus(403);

        // Doctor cannot encode result -> 403
        $this->actingAs($this->doctorA)
             ->post(route('lab.results.store'), [
                 'lab_request_item_id' => $item->id,
                 'result_value'        => '7.5',
             ])
             ->assertStatus(403);
    }

    public function test_doctor_cannot_validate_or_release_results(): void
    {
        $req = LabRequest::create([
            'request_no'   => 'LR-2026-' . rand(1000, 9999),
            'patient_id'   => $this->patientA->id,
            'doctor_id'    => $this->doctorA->id,
            'priority'     => 'Routine',
            'status'       => 'In Progress',
            'requested_at' => now(),
        ]);

        $item = LabRequestItem::create([
            'lab_request_id' => $req->id,
            'lab_test_id'    => $this->cbcTest->id,
            'status'         => 'In Progress',
        ]);

        $result = LabResult::create([
            'lab_request_item_id' => $item->id,
            'technologist_id'     => $this->medTech->id,
            'result_value'        => '7.5',
            'status'              => 'Encoded',
        ]);

        // Doctor cannot validate result -> 403
        $this->actingAs($this->doctorA)
             ->patch(route('lab.results.validate', $result))
             ->assertStatus(403);

        $result->update(['status' => 'Validated']);

        // Doctor cannot release result -> 403
        $this->actingAs($this->doctorA)
             ->patch(route('lab.results.release', $result))
             ->assertStatus(403);
    }

    public function test_doctor_can_only_view_released_results_for_own_patients(): void
    {
        $reqA = LabRequest::create([
            'request_no'   => 'LR-2026-' . rand(1000, 9999),
            'patient_id'   => $this->patientA->id,
            'doctor_id'    => $this->doctorA->id,
            'priority'     => 'Routine',
            'status'       => 'Completed',
            'requested_at' => now(),
        ]);

        $itemA = LabRequestItem::create([
            'lab_request_id' => $reqA->id,
            'lab_test_id'    => $this->cbcTest->id,
            'status'         => 'Completed',
        ]);

        $resultDraft = LabResult::create([
            'lab_request_item_id' => $itemA->id,
            'technologist_id'     => $this->medTech->id,
            'result_value'        => '6.2',
            'status'              => 'Encoded', // not released yet
        ]);

        // Doctor A cannot view unreleased/draft result -> 403
        $this->actingAs($this->doctorA)
             ->get(route('lab.results.show', $resultDraft))
             ->assertStatus(403);

        $resultDraft->update(['status' => 'Released', 'released_at' => now()]);

        // Doctor A can view released result -> 200
        $this->actingAs($this->doctorA)
             ->get(route('lab.results.show', $resultDraft))
             ->assertStatus(200);

        // Doctor B cannot view Doctor A's released result -> 403
        $this->actingAs($this->doctorB)
             ->get(route('lab.results.show', $resultDraft))
             ->assertStatus(403);
    }

    /* ════════════════════════════════════════════════════════════════════════
       2. LABORATORY STAFF (MED-TECH) WORKFLOW & AUTHORIZATION
       ════════════════════════════════════════════════════════════════════════ */

    public function test_med_tech_can_receive_specimen_and_log_activity(): void
    {
        $req = LabRequest::create([
            'request_no'     => 'LR-2026-' . rand(1000, 9999),
            'patient_id'     => $this->patientA->id,
            'doctor_id'      => $this->doctorA->id,
            'priority'       => 'Routine',
            'specimen_type'  => 'Blood',
            'status'         => 'Pending',
            'requested_at'   => now(),
        ]);

        $item = LabRequestItem::create([
            'lab_request_id' => $req->id,
            'lab_test_id'    => $this->cbcTest->id,
            'status'         => 'Pending',
        ]);

        $response = $this->actingAs($this->medTech)
                         ->patch(route('lab.requests.receive', $req), [
                             'specimen_notes' => 'Received purple top tube 4mL intact',
                         ]);

        $response->assertRedirect();
        $this->assertEquals('In Progress', $req->fresh()->status);
        $this->assertNotNull($req->fresh()->received_at);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $this->medTech->id,
            'action'  => 'Lab Specimen Received',
            'module'  => 'Laboratory',
        ]);
    }

    public function test_med_tech_can_reject_specimen_with_reason(): void
    {
        $req = LabRequest::create([
            'request_no'     => 'LR-2026-' . rand(1000, 9999),
            'patient_id'     => $this->patientA->id,
            'doctor_id'      => $this->doctorA->id,
            'priority'       => 'Routine',
            'specimen_type'  => 'Blood',
            'status'         => 'Pending',
            'requested_at'   => now(),
        ]);

        $response = $this->actingAs($this->medTech)
                         ->patch(route('lab.requests.reject', $req), [
                             'rejection_reason' => 'Specimen severely hemolyzed; redraw requested.',
                         ]);

        $response->assertRedirect();
        $this->assertEquals('Cancelled', $req->fresh()->status);
        $this->assertStringContainsString('Specimen severely hemolyzed', $req->fresh()->clinical_notes);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $this->medTech->id,
            'action'  => 'Lab Specimen Rejected',
            'module'  => 'Laboratory',
        ]);
    }

    public function test_med_tech_can_encode_result_but_needs_permission_to_validate_and_release(): void
    {
        $req = LabRequest::create([
            'request_no'   => 'LR-2026-' . rand(1000, 9999),
            'patient_id'   => $this->patientA->id,
            'doctor_id'    => $this->doctorA->id,
            'priority'     => 'Routine',
            'status'       => 'In Progress',
            'requested_at' => now(),
        ]);

        $item = LabRequestItem::create([
            'lab_request_id' => $req->id,
            'lab_test_id'    => $this->cbcTest->id,
            'status'         => 'In Progress',
        ]);

        // Standard medTech encodes result -> Success
        $encodeRes = $this->actingAs($this->medTech)->post(route('lab.results.store'), [
            'lab_request_item_id' => $item->id,
            'result_value'        => '5.8',
            'remarks'             => 'WBC within normal limits',
        ]);

        $encodeRes->assertRedirect(route('lab.results.index'));

        $result = LabResult::where('lab_request_item_id', $item->id)->first();
        $this->assertNotNull($result);
        $this->assertEquals('Encoded', $result->status);
        $this->assertEquals($this->medTech->id, $result->technologist_id);

        // Standard medTech DOES NOT have lis.results.verify permission -> 403 Forbidden
        $this->actingAs($this->medTech)
             ->patch(route('lab.results.validate', $result))
             ->assertStatus(403);

        // Senior medTechVerifier HAS lis.results.verify -> Success
        $valRes = $this->actingAs($this->medTechVerifier)
                       ->patch(route('lab.results.validate', $result));
        $valRes->assertRedirect();
        $this->assertEquals('Validated', $result->fresh()->status);
        $this->assertEquals($this->medTechVerifier->id, $result->fresh()->validated_by);

        // Standard medTech DOES NOT have lis.results.release -> 403 Forbidden
        $this->actingAs($this->medTech)
             ->patch(route('lab.results.release', $result))
             ->assertStatus(403);

        // Senior medTechVerifier HAS lis.results.release -> Success
        $relRes = $this->actingAs($this->medTechVerifier)
                       ->patch(route('lab.results.release', $result));
        $relRes->assertRedirect();
        $this->assertEquals('Released', $result->fresh()->status);
        $this->assertEquals($this->medTechVerifier->id, $result->fresh()->released_by);
        $this->assertEquals('Completed', $req->fresh()->status);

        // Released result cannot be edited -> 403
        $this->actingAs($this->medTech)
             ->put(route('lab.results.update', $result), [
                 'lab_request_item_id' => $item->id,
                 'result_value'        => '9.9',
             ])
             ->assertStatus(403);
    }

    public function test_med_tech_cannot_approve_radiology_reports(): void
    {
        $radReq = RadiologyRequest::create([
            'request_no'   => 'RR-2026-' . rand(1000, 9999),
            'patient_id'   => $this->patientA->id,
            'doctor_id'    => $this->doctorA->id,
            'modality'     => 'X-Ray',
            'body_part'    => 'Chest PA',
            'status'       => 'Completed',
            'requested_at' => now(),
        ]);

        $report = RadiologyReport::create([
            'radiology_request_id' => $radReq->id,
            'radiologist_id'       => $this->radiologist->id,
            'findings'             => 'Clear lung fields',
            'impression'           => 'Normal exam',
            'status'               => 'Draft',
        ]);

        $this->actingAs($this->medTech)
             ->patch(route('radiology.reports.approve', $report))
             ->assertStatus(403);
    }

    /* ════════════════════════════════════════════════════════════════════════
       3. RADIOLOGY STAFF (RAD-TECH) WORKFLOW & RESTRICTIONS
       ════════════════════════════════════════════════════════════════════════ */

    public function test_rad_tech_can_prepare_draft_report_when_authorized(): void
    {
        $radReq = RadiologyRequest::create([
            'request_no'   => 'RR-2026-' . rand(1000, 9999),
            'patient_id'   => $this->patientA->id,
            'doctor_id'    => $this->doctorA->id,
            'modality'     => 'X-Ray',
            'body_part'    => 'Chest PA',
            'status'       => 'Completed',
            'requested_at' => now(),
            'completed_at' => now(),
        ]);

        // Rad tech prepares draft report
        $response = $this->actingAs($this->radTech)->post(route('radiology.reports.store'), [
            'radiology_request_id' => $radReq->id,
            'radiologist_id'       => $this->radiologist->id,
            'findings'             => 'Preliminary tech notes: Normal heart contour and clear lungs.',
            'impression'           => 'Pending Radiologist confirmation.',
            'recommendations'      => 'Awaiting formal reading.',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('radiology_reports', [
            'radiology_request_id' => $radReq->id,
            'radiologist_id'       => $this->radiologist->id,
            'status'               => 'Draft',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $this->radTech->id,
            'action'  => 'Radiology Report Prepared',
            'module'  => 'Radiology',
        ]);
    }

    public function test_rad_tech_cannot_approve_or_release_radiology_reports(): void
    {
        $radReq = RadiologyRequest::create([
            'request_no'   => 'RR-2026-' . rand(1000, 9999),
            'patient_id'   => $this->patientA->id,
            'doctor_id'    => $this->doctorA->id,
            'modality'     => 'X-Ray',
            'body_part'    => 'Chest PA',
            'status'       => 'Completed',
            'requested_at' => now(),
        ]);

        $report = RadiologyReport::create([
            'radiology_request_id' => $radReq->id,
            'radiologist_id'       => $this->radiologist->id,
            'findings'             => 'Test findings',
            'impression'           => 'Test impression',
            'status'               => 'Draft',
        ]);

        // Rad tech cannot approve report -> 403
        $this->actingAs($this->radTech)
             ->patch(route('radiology.reports.approve', $report))
             ->assertStatus(403);

        $report->update(['status' => 'Approved']);

        // Rad tech cannot release report -> 403
        $this->actingAs($this->radTech)
             ->patch(route('radiology.reports.release', $report))
             ->assertStatus(403);
    }

    /* ════════════════════════════════════════════════════════════════════════
       4. RADIOLOGIST WORKFLOW & RESTRICTIONS
       ════════════════════════════════════════════════════════════════════════ */

    public function test_radiologist_can_approve_release_and_return_reports_for_correction(): void
    {
        $radReq = RadiologyRequest::create([
            'request_no'   => 'RR-2026-' . rand(1000, 9999),
            'patient_id'   => $this->patientA->id,
            'doctor_id'    => $this->doctorA->id,
            'modality'     => 'CT Scan',
            'body_part'    => 'Abdomen',
            'status'       => 'Completed',
            'requested_at' => now(),
        ]);

        $report = RadiologyReport::create([
            'radiology_request_id' => $radReq->id,
            'radiologist_id'       => $this->radiologist->id,
            'findings'             => 'Draft findings requiring correction',
            'impression'           => 'Incomplete scan coverage',
            'status'               => 'Draft',
        ]);

        // 1. Radiologist returns report for correction
        $returnRes = $this->actingAs($this->radiologist)
                          ->patch(route('radiology.reports.return', $report), [
                              'correction_notes' => 'Please re-verify lower abdominal slice 44 artifacts.',
                          ]);

        $returnRes->assertRedirect();
        $this->assertEquals('Draft', $report->fresh()->status);
        $this->assertStringContainsString('lower abdominal slice 44', $report->fresh()->recommendations);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $this->radiologist->id,
            'action'  => 'Radiology Report Returned for Correction',
            'module'  => 'Radiology',
        ]);

        // 2. Radiologist approves report
        $approveRes = $this->actingAs($this->radiologist)
                           ->patch(route('radiology.reports.approve', $report));

        $approveRes->assertRedirect();
        $this->assertEquals('Approved', $report->fresh()->status);
        $this->assertEquals($this->radiologist->id, $report->fresh()->approved_by);

        // 3. Radiologist releases report
        $releaseRes = $this->actingAs($this->radiologist)
                           ->patch(route('radiology.reports.release', $report));

        $releaseRes->assertRedirect();
        $this->assertEquals('Released', $report->fresh()->status);
        $this->assertEquals($this->radiologist->id, $report->fresh()->released_by);
    }

    public function test_radiologist_cannot_create_imaging_request_as_physician(): void
    {
        $this->actingAs($this->radiologist)
             ->post(route('radiology.requests.store'), [
                 'patient_id' => $this->patientA->id,
                 'modality'   => 'MRI',
                 'body_part'  => 'Brain',
                 'priority'   => 'Routine',
             ])
             ->assertStatus(403);
    }

    /* ════════════════════════════════════════════════════════════════════════
       HELPER METHODS
       ════════════════════════════════════════════════════════════════════════ */

    protected function createUserWithRole(string $roleSlug, string $email, string $name): User
    {
        $role = Role::firstOrCreate(
            ['slug' => $roleSlug],
            ['name' => ucfirst($roleSlug), 'dashboard_route' => 'dashboard']
        );

        $user = User::create([
            'name'        => $name,
            'email'       => $email,
            'password'    => bcrypt('password123'),
            'employee_id' => 'EMP-' . strtoupper(substr(md5(uniqid()), 0, 8)),
            'department'  => 'Medical Services',
            'is_active'   => true,
        ]);

        $user->roles()->attach($role->id);

        return $user;
    }

    protected function attachPermissionToRole(Role $role, string $slug): void
    {
        $perm = Permission::firstOrCreate(
            ['slug' => $slug],
            ['name' => $slug, 'module' => 'Diagnostics']
        );

        if (! $role->permissions()->where('permissions.id', $perm->id)->exists()) {
            $role->permissions()->attach($perm->id);
        }
    }

    protected function ensureDiagnosticRolesAndPermissions(): void
    {
        $roleConfigs = [
            'doctor' => [
                'name' => 'Doctor',
                'permissions' => ['lis.requests.view', 'lis.requests.create', 'ris.requests.view', 'ris.requests.create', 'diagnostics.results.view'],
            ],
            'med-tech' => [
                'name' => 'Medical Technologist',
                'permissions' => ['lis.requests.view', 'lis.specimens.process', 'lis.results.enter'],
            ],
            'rad-tech' => [
                'name' => 'Radiologic Technologist',
                'permissions' => ['ris.requests.view', 'ris.procedures.manage', 'ris.reports.prepare'],
            ],
            'radiologist' => [
                'name' => 'Radiologist',
                'permissions' => ['ris.requests.view', 'ris.reports.review', 'ris.reports.approve'],
            ],
        ];

        foreach ($roleConfigs as $slug => $config) {
            $role = Role::firstOrCreate(
                ['slug' => $slug],
                ['name' => $config['name'], 'dashboard_route' => 'dashboard']
            );

            // Detach any extra permissions to start test from a clean baseline
            foreach ($config['permissions'] as $permSlug) {
                $this->attachPermissionToRole($role, $permSlug);
            }
        }

        // Ensure baseline med-tech role does not have verification/release by default in this test
        $medTechRole = Role::where('slug', 'med-tech')->first();
        if ($medTechRole) {
            $verifyPerm = Permission::where('slug', 'lis.results.verify')->first();
            $releasePerm = Permission::where('slug', 'lis.results.release')->first();
            if ($verifyPerm) {
                $medTechRole->permissions()->detach($verifyPerm->id);
            }
            if ($releasePerm) {
                $medTechRole->permissions()->detach($releasePerm->id);
            }
        }
    }
}
