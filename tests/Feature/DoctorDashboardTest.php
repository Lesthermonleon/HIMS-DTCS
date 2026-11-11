<?php

namespace Tests\Feature;

use App\AI\EvidenceMDProvider;
use App\Models\DietRequest;
use App\Models\LabRequest;
use App\Models\LabRequestItem;
use App\Models\LabResult;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\RadiologyReport;
use App\Models\RadiologyRequest;
use App\Models\Role;
use App\Models\SurgeryRequest;
use App\Models\User;
use App\Services\MediSense\ClinicalContextBuilder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DoctorDashboardTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureCoreRolesExist();
    }

    private function ensureCoreRolesExist(): void
    {
        $roles = [
            ['name' => 'Super Administrator',     'slug' => 'super_admin',    'dashboard_route' => 'super-admin.dashboard'],
            ['name' => 'System Administrator',    'slug' => 'admin',          'dashboard_route' => 'admin.dashboard'],
            ['name' => 'Doctor',                  'slug' => 'doctor',         'dashboard_route' => 'doctor.dashboard'],
            ['name' => 'Medical Technologist',    'slug' => 'med-tech',       'dashboard_route' => 'lab.dashboard'],
            ['name' => 'Radiologic Technologist', 'slug' => 'rad-tech',       'dashboard_route' => 'radiology.dashboard'],
            ['name' => 'Radiologist',             'slug' => 'radiologist',    'dashboard_route' => 'radiology.dashboard'],
            ['name' => 'Pharmacist',              'slug' => 'pharmacist',     'dashboard_route' => 'pharmacy.dashboard'],
            ['name' => 'Dietitian / Nutritionist','slug' => 'dietitian',      'dashboard_route' => 'diet.dashboard'],
            ['name' => 'OR Coordinator',          'slug' => 'or-coordinator', 'dashboard_route' => 'surgery.dashboard'],
            ['name' => 'Patient',                 'slug' => 'patient',         'dashboard_route' => 'patient.dashboard'],
        ];

        foreach ($roles as $r) {
            Role::firstOrCreate(['slug' => $r['slug']], $r);
        }
    }

    private function createUserWithRole(string $slug): User
    {
        $role = Role::firstOrCreate(
            ['slug' => $slug],
            ['name' => ucfirst($slug), 'slug' => $slug, 'dashboard_route' => $slug . '.dashboard']
        );

        $user = User::create([
            'name'        => 'Dr. Test ' . ucfirst($slug),
            'email'       => $slug . '_' . uniqid() . '@test.hospital',
            'password'    => bcrypt('password'),
            'employee_id' => 'EMP-' . rand(10000, 99999),
            'department'  => 'Internal Medicine',
            'is_active'   => true,
        ]);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function createPatient(): Patient
    {
        return Patient::create([
            'patient_no'    => 'PAT-' . date('Y') . '-' . rand(10000, 99999),
            'first_name'    => 'TestFirst' . rand(100, 999),
            'last_name'     => 'TestLast' . rand(100, 999),
            'date_of_birth' => '1985-05-15',
            'gender'        => 'Female',
            'patient_type'  => 'Inpatient',
            'ward'          => 'Ward 3B',
            'bed_number'    => 'Bed 12',
        ]);
    }

    public function test_doctor_can_access_doctor_dashboard(): void
    {
        $doctor = $this->createUserWithRole('doctor');

        $response = $this->actingAs($doctor)->get(route('doctor.dashboard'));

        $response->assertStatus(200);
        $response->assertViewIs('dashboard.doctor');
        $response->assertViewHasAll([
            'stats',
            'pendingLabRequests',
            'pendingRadRequests',
            'pendingRx',
            'pendingSurgery',
            'pendingDiet',
            'sortedPendingTasks',
            'releasedLabResults',
            'releasedRadReports',
            'sortedAlerts',
            'recentPatients',
            'doctorTrend6m',
            'doctorTrend12m',
            'doctorServiceBreakdown',
        ]);
    }

    public function test_unauthorized_roles_cannot_access_doctor_dashboard(): void
    {
        $patient = $this->createUserWithRole('patient');
        $this->actingAs($patient)->get(route('doctor.dashboard'))->assertStatus(403);

        $medTech = $this->createUserWithRole('med-tech');
        $this->actingAs($medTech)->get(route('doctor.dashboard'))->assertStatus(403);
    }

    public function test_my_patients_displays_only_authorized_records_and_no_fallback(): void
    {
        // Ensure hospital has existing patients
        $this->createPatient();
        $this->createPatient();

        // Fresh doctor with zero clinical records
        $newDoctor = $this->createUserWithRole('doctor');

        $response = $this->actingAs($newDoctor)->get(route('doctor.dashboard'));
        $response->assertStatus(200);

        $stats = $response->viewData('stats');
        // Must be exactly 0, not falling back to total hospital patients
        $this->assertEquals(0, $stats['my_patients']);

        // Now associate one patient with this doctor via a lab request
        $patient = $this->createPatient();
        LabRequest::create([
            'request_no'   => 'LR-' . uniqid(),
            'patient_id'   => $patient->id,
            'doctor_id'    => $newDoctor->id,
            'priority'     => 'Routine',
            'status'       => 'Pending',
            'requested_at' => now(),
        ]);

        $response2 = $this->actingAs($newDoctor)->get(route('doctor.dashboard'));
        $stats2 = $response2->viewData('stats');
        $this->assertEquals(1, $stats2['my_patients']);
    }

    public function test_pending_tasks_are_scoped_strictly_to_doctor_and_not_leaked(): void
    {
        $doctorA = $this->createUserWithRole('doctor');
        $doctorB = $this->createUserWithRole('doctor');
        $patient = $this->createPatient();

        // Doctor A creates a pending prescription
        Prescription::create([
            'prescription_no' => 'RX-' . uniqid(),
            'patient_id'      => $patient->id,
            'doctor_id'       => $doctorA->id,
            'status'          => 'Pending',
            'diagnosis'       => 'Hypertension',
        ]);

        // Doctor B has zero requests
        $responseB = $this->actingAs($doctorB)->get(route('doctor.dashboard'));
        $statsB = $responseB->viewData('stats');
        $sortedPendingB = $responseB->viewData('sortedPendingTasks');

        // Doctor B must see 0 pending tasks, Doctor A's prescription must not leak
        $this->assertEquals(0, $statsB['pending_tasks']);
        $this->assertCount(0, $sortedPendingB);

        // Doctor A sees 1 pending task
        $responseA = $this->actingAs($doctorA)->get(route('doctor.dashboard'));
        $statsA = $responseA->viewData('stats');
        $this->assertEquals(1, $statsA['pending_tasks']);
    }

    public function test_clinical_alerts_distinguish_severity_and_exclude_unauthorized_records(): void
    {
        $doctor = $this->createUserWithRole('doctor');
        $patient1 = $this->createPatient();
        $patient2 = $this->createPatient();

        // 1. STAT priority Lab Request -> Critical Alert
        $statReq = LabRequest::create([
            'request_no'   => 'LR-STAT-' . uniqid(),
            'patient_id'   => $patient1->id,
            'doctor_id'    => $doctor->id,
            'priority'     => 'STAT',
            'status'       => 'Completed',
            'requested_at' => now(),
        ]);
        $category = \App\Models\LabTestCategory::firstOrCreate(
            ['code' => 'HEM'],
            ['name' => 'Hematology', 'is_active' => true]
        );
        $labTest = \App\Models\LabTest::firstOrCreate(
            ['code' => 'PLT'],
            [
                'lab_test_category_id' => $category->id,
                'name'                 => 'Platelet Count',
                'price'                => 150.00,
                'is_active'            => true,
            ]
        );
        $item1 = LabRequestItem::create([
            'lab_request_id' => $statReq->id,
            'lab_test_id'    => $labTest->id,
            'test_name'      => 'Platelet Count',
        ]);
        LabResult::create([
            'lab_request_item_id' => $item1->id,
            'result_value'        => '45 x10³/µL',
            'remarks'             => 'Critical platelet value communicated.',
            'status'              => 'Released',
            'released_at'         => now(),
        ]);

        // 2. Urgent priority Radiology Request -> Urgent Alert
        $radiologist = $this->createUserWithRole('radiologist');
        $urgentRad = RadiologyRequest::create([
            'request_no'   => 'RAD-URG-' . uniqid(),
            'patient_id'   => $patient2->id,
            'doctor_id'    => $doctor->id,
            'modality'     => 'Ultrasound',
            'body_part'    => 'Gallbladder',
            'priority'     => 'Urgent',
            'status'       => 'Completed',
            'requested_at' => now(),
        ]);
        RadiologyReport::create([
            'radiology_request_id' => $urgentRad->id,
            'radiologist_id'       => $radiologist->id,
            'impression'           => 'Acute Cholecystitis',
            'findings'             => 'Gallbladder wall thickening',
            'status'               => 'Approved',
            'approved_at'          => now(),
        ]);

        $response = $this->actingAs($doctor)->get(route('doctor.dashboard'));
        $stats = $response->viewData('stats');
        $alerts = $response->viewData('sortedAlerts');

        $this->assertEquals(1, $stats['critical_alerts']);
        $this->assertEquals(1, $stats['urgent_alerts']);
        $this->assertCount(2, $alerts);

        // First alert must be Critical
        $this->assertEquals('Critical', $alerts->first()['severity']);
        // Second alert must be Urgent
        $this->assertEquals('Urgent', $alerts->last()['severity']);
    }

    public function test_quick_action_routes_are_accessible_for_authorized_doctor(): void
    {
        $doctor = $this->createUserWithRole('doctor');

        $this->actingAs($doctor)->get(route('lab.requests.create'))->assertStatus(200);
        $this->actingAs($doctor)->get(route('radiology.requests.create'))->assertStatus(200);
        $this->actingAs($doctor)->get(route('pharmacy.prescriptions.create'))->assertStatus(200);
        $this->actingAs($doctor)->get(route('surgery.requests.create'))->assertStatus(200);
        $this->actingAs($doctor)->get(route('diet.requests.create'))->assertStatus(200);
    }

    public function test_medisense_ai_remains_doctor_only(): void
    {
        $doctor = $this->createUserWithRole('doctor');
        $admin  = $this->createUserWithRole('admin');
        $patientUser = $this->createUserWithRole('patient');

        // Doctor is authorized
        $this->actingAs($doctor)->get(route('medisense.index'))->assertStatus(200);

        // Admin cannot access Doctor-only MediSense
        $this->actingAs($admin)->get(route('medisense.index'))->assertStatus(403);

        // Patient cannot access MediSense
        $this->actingAs($patientUser)->get(route('medisense.index'))->assertStatus(403);
    }

    public function test_medisense_ai_context_builder_and_privacy_sanitizer(): void
    {
        $patient = $this->createPatient();
        $patient->update(['clinical_findings' => 'Patient has severe chest pain radiating to left arm.']);

        $contextBuilder = app(ClinicalContextBuilder::class);
        $context = $contextBuilder->build($patient);

        $this->assertEquals($patient->id, $context['patient']['id']);
        $this->assertEquals('Patient has severe chest pain radiating to left arm.', $context['symptoms_and_findings']);

        // Verify EvidenceMDProvider sanitization does not leak direct identifiers
        $provider = app(EvidenceMDProvider::class);
        $sanitizedText = $provider->sanitizeFreeText("Patient {$patient->full_name} in Ward 3B Bed 12 has pneumonia", [
            'full_name'  => $patient->full_name,
            'ward'       => 'Ward 3B',
            'bed_number' => '12',
        ]);

        $this->assertStringNotContainsString($patient->first_name, $sanitizedText);
        $this->assertStringNotContainsString('Ward 3B', $sanitizedText);
    }

    public function test_recent_patients_scoped_strictly_to_doctor_records(): void
    {
        $doctor = $this->createUserWithRole('doctor');

        // Unrelated patient in hospital
        $unrelatedPatient = $this->createPatient();

        // Doctor's assigned patient
        $myPatient = $this->createPatient();
        DietRequest::create([
            'request_no' => 'DIET-' . uniqid(),
            'patient_id' => $myPatient->id,
            'doctor_id'  => $doctor->id,
            'diet_type'  => 'Low Sodium',
            'status'     => 'Pending',
        ]);

        $response = $this->actingAs($doctor)->get(route('doctor.dashboard'));
        $recentPatients = $response->viewData('recentPatients');

        $this->assertCount(1, $recentPatients);
        $this->assertEquals($myPatient->id, $recentPatients->first()->id);
        $this->assertFalse($recentPatients->contains('id', $unrelatedPatient->id));
    }

    public function test_empty_datasets_do_not_break_doctor_dashboard(): void
    {
        $freshDoctor = $this->createUserWithRole('doctor');

        $response = $this->actingAs($freshDoctor)->get(route('doctor.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('No Pending Tasks');
        $response->assertSee('No Critical Alerts');
        $response->assertSee('No Authorized Patient Records Yet');
    }
}
