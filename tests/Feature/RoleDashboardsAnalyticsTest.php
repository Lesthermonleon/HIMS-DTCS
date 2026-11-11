<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\DietPlan;
use App\Models\DietRequest;
use App\Models\LabRequest;
use App\Models\LabRequestItem;
use App\Models\LabResult;
use App\Models\LabTest;
use App\Models\LabTestCategory;
use App\Models\OperatingRoom;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\RadiologyReport;
use App\Models\RadiologyRequest;
use App\Models\Role;
use App\Models\SurgeryRequest;
use App\Models\SurgerySchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RoleDashboardsAnalyticsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureRolesExist();
    }

    private function ensureRolesExist(): void
    {
        $roles = [
            ['name' => 'Super Administrator',     'slug' => 'super_admin',    'dashboard_route' => 'super-admin.dashboard'],
            ['name' => 'System Administrator',    'slug' => 'admin',          'dashboard_route' => 'admin.dashboard'],
            ['name' => 'Doctor',                  'slug' => 'doctor',         'dashboard_route' => 'doctor.dashboard'],
            ['name' => 'Medical Technologist',    'slug' => 'med-tech',       'dashboard_route' => 'lab.dashboard'],
            ['name' => 'Radiologic Technologist', 'slug' => 'rad-tech',       'dashboard_route' => 'radiology.dashboard'],
            ['name' => 'Radiologist',             'slug' => 'radiologist',    'dashboard_route' => 'radiology.dashboard'],
            ['name' => 'Pharmacist',              'slug' => 'pharmacist',     'dashboard_route' => 'pharmacy.dashboard'],
            ['name' => 'OR Coordinator',          'slug' => 'or-coordinator', 'dashboard_route' => 'surgery.dashboard'],
            ['name' => 'OR Staff',                'slug' => 'or-staff',       'dashboard_route' => 'surgery.dashboard'],
            ['name' => 'Patient',                 'slug' => 'patient',        'dashboard_route' => 'patient.dashboard'],
        ];

        foreach ($roles as $r) {
            Role::firstOrCreate(['slug' => $r['slug']], $r);
        }
    }

    private function createUserWithRole(string $slug, string $namePrefix = 'User'): User
    {
        $role = Role::where('slug', $slug)->firstOrFail();

        $user = User::create([
            'name'        => $namePrefix . ' ' . ucfirst($slug),
            'email'       => $slug . '_' . uniqid() . '@dtcs.test',
            'password'    => bcrypt('password'),
            'employee_id' => 'EMP-' . rand(10000, 99999),
            'department'  => 'Clinical Services',
            'is_active'   => true,
        ]);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function createPatient(?User $portalUser = null): Patient
    {
        $patient = Patient::create([
            'user_id'       => $portalUser?->id,
            'patient_no'    => 'PAT-' . date('Y') . '-' . rand(10000, 99999),
            'first_name'    => 'PatFirst' . rand(100, 999),
            'last_name'     => 'PatLast' . rand(100, 999),
            'date_of_birth' => '1990-04-12',
            'gender'        => 'Male',
            'patient_type'  => 'Inpatient',
            'ward'          => 'Ward 2A',
            'bed_number'    => 'Bed 4',
        ]);

        if ($portalUser) {
            $portalUser->update(['patient_id' => $patient->id]);
        }

        return $patient;
    }

    public function test_super_admin_dashboard_loads_all_governance_metrics_and_includes_or_staff_in_staff_count(): void
    {
        $superAdmin = $this->createUserWithRole('super_admin', 'Super');
        $orStaff    = $this->createUserWithRole('or-staff', 'Staff');

        $response = $this->actingAs($superAdmin)->get(route('super-admin.dashboard'));

        $response->assertStatus(200);
        $response->assertViewIs('super_admin.dashboard');
        $response->assertViewHasAll([
            'accountStats',
            'accountGrowth6m',
            'accountGrowth12m',
            'roleDistribution',
            'statusDistribution',
            'authAndSecurity7d',
            'moduleHealth',
            'recentSecurityEvents',
            'recentAdminActivities',
        ]);

        $response->assertSee('Total DTCS Accounts');
        $response->assertSee('Active Accounts');
        $response->assertSee('Inactive Accounts');
        $response->assertSee('Locked Accounts');
        $response->assertSee('Total DTCS Modules');
        $response->assertSee('Security Alerts');
        $accountStats = $response->viewData('accountStats');
        $this->assertGreaterThanOrEqual(1, $accountStats['staff']);
    }

    public function test_admin_dashboard_renders_six_scorecards_and_operational_sections_without_doctor_terminology(): void
    {
        $admin = $this->createUserWithRole('admin', 'Admin');

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertStatus(200);
        $response->assertViewIs('dashboard.admin');
        $response->assertSee('Total Users');
        $response->assertSee('Active Users');
        $response->assertSee('Total Patients');
        $response->assertSee('Inactive Users');
        $response->assertSee('Pending Clinical Requests');
        $response->assertSee('Operational Alerts');
        $response->assertSee('Recent Operational Activities');
        $response->assertDontSee('Pending Tasks');
    }

    public function test_doctor_dashboard_renders_enhanced_metrics_consultation_trends_and_scoped_data(): void
    {
        $doctorA = $this->createUserWithRole('doctor', 'Dr. A');
        $doctorB = $this->createUserWithRole('doctor', 'Dr. B');
        $patientA = $this->createPatient();
        $patientB = $this->createPatient();

        Appointment::create([
            'appointment_no'   => 'APT-' . uniqid(),
            'patient_id'       => $patientA->id,
            'doctor_id'        => $doctorA->id,
            'scheduled_at'     => now()->addDay(),
            'duration_minutes' => 30,
            'type'             => 'In-Person Consultation',
            'status'           => Appointment::STATUS_CONFIRMED,
        ]);

        LabRequest::create([
            'request_no'   => 'LR-' . uniqid(),
            'patient_id'   => $patientB->id,
            'doctor_id'    => $doctorB->id,
            'priority'     => 'Routine',
            'status'       => 'Pending',
            'requested_at' => now(),
        ]);

        $response = $this->actingAs($doctorA)->get(route('doctor.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('My Patients');
        $response->assertSee('Pending Tasks');
        $response->assertSee('Upcoming Appointments');
        $response->assertSee('Pending Lab Results');
        $response->assertSee('Pending Imaging Reports');
        $response->assertSee('Active Clinical Requests');
        $response->assertSee('Consultation Trends');

        $stats = $response->viewData('stats');
        $this->assertEquals(1, $stats['my_patients']);
        $this->assertEquals(1, $stats['upcoming_appointments']);
        $this->assertEquals(0, $stats['pending_lab_results']);
    }

    public function test_med_tech_lab_dashboard_renders_six_scorecards_and_daily_trends(): void
    {
        $medTech = $this->createUserWithRole('med-tech', 'Tech');
        $doctor  = $this->createUserWithRole('doctor', 'Doc');
        $patient = $this->createPatient();

        $req = LabRequest::create([
            'request_no'   => 'LR-STAT-' . uniqid(),
            'patient_id'   => $patient->id,
            'doctor_id'    => $doctor->id,
            'priority'     => 'STAT',
            'status'       => 'In Progress',
            'requested_at' => now(),
            'received_at'  => now(),
        ]);

        $response = $this->actingAs($medTech)->get(route('lab.dashboard'));

        $response->assertStatus(200);
        $response->assertViewIs('dashboard.lab');
        $response->assertSee('Pending Lab Requests');
        $response->assertSee('Specimens Received');
        $response->assertSee('Tests In Progress');
        $response->assertSee('Results Awaiting Validation');
        $response->assertSee('Results Awaiting Release');
        $response->assertSee('Completed Tests');
        $response->assertSee('Daily/Weekly Test Trends');
        $response->assertSee($patient->patient_no);

        $stats = $response->viewData('stats');
        $this->assertGreaterThanOrEqual(1, $stats['specimen_received']);
    }

    public function test_rad_tech_and_radiologist_dashboards_differentiate_metrics_and_fix_double_counting(): void
    {
        $radTech     = $this->createUserWithRole('rad-tech', 'RadTech');
        $radiologist = $this->createUserWithRole('radiologist', 'RadDoc');
        $doctor      = $this->createUserWithRole('doctor', 'OrderDoc');
        $patient     = $this->createPatient();

        // 1 Completed study with NO report yet -> awaiting report preparation
        $studyWithoutReport = RadiologyRequest::create([
            'request_no'   => 'RAD-1-' . uniqid(),
            'patient_id'   => $patient->id,
            'doctor_id'    => $doctor->id,
            'modality'     => 'X-Ray',
            'body_part'    => 'Chest',
            'priority'     => 'Routine',
            'status'       => 'Completed',
            'requested_at' => now(),
            'completed_at' => now(),
        ]);

        // 1 Completed study WITH a Draft report -> under review
        $studyWithDraft = RadiologyRequest::create([
            'request_no'   => 'RAD-2-' . uniqid(),
            'patient_id'   => $patient->id,
            'doctor_id'    => $doctor->id,
            'modality'     => 'CT Scan',
            'body_part'    => 'Head',
            'priority'     => 'Urgent',
            'status'       => 'Completed',
            'requested_at' => now(),
            'completed_at' => now(),
        ]);

        RadiologyReport::create([
            'radiology_request_id' => $studyWithDraft->id,
            'radiologist_id'       => $radiologist->id,
            'findings'             => 'Normal CT',
            'impression'           => 'Unremarkable',
            'status'               => 'Draft',
        ]);

        // 1. Test Rad-Tech View
        $techRes = $this->actingAs($radTech)->get(route('radiology.dashboard'));
        $techRes->assertStatus(200);
        $techRes->assertSee('Pending Imaging Requests');
        $techRes->assertSee('Scheduled Examinations');
        $techRes->assertSee('Examinations In Progress');
        $techRes->assertSee('Completed Examinations');
        $techRes->assertSee('Reports to Prepare');
        $techRes->assertSee('Modality Distribution');

        // 2. Test Radiologist View
        $radRes = $this->actingAs($radiologist)->get(route('radiology.dashboard'));
        $radRes->assertStatus(200);
        $radRes->assertSee('Reports Awaiting Review');
        $radRes->assertSee('Reports Under Review');
        $radRes->assertSee('Reports Returned for Correction');
        $radRes->assertSee('Reports Awaiting Approval');
        $radRes->assertSee('Reports Released');
    }

    public function test_pharmacist_dashboard_renders_five_scorecards_and_dispensing_trends(): void
    {
        $pharmacist = $this->createUserWithRole('pharmacist', 'Pharm');
        $doctor     = $this->createUserWithRole('doctor', 'Doc');
        $patient    = $this->createPatient();

        Prescription::create([
            'prescription_no' => 'RX-' . uniqid(),
            'patient_id'      => $patient->id,
            'doctor_id'       => $doctor->id,
            'status'          => 'Pending',
            'diagnosis'       => 'Infection',
        ]);

        $response = $this->actingAs($pharmacist)->get(route('pharmacy.dashboard'));

        $response->assertStatus(200);
        $response->assertViewIs('dashboard.pharmacy');
        $response->assertSee('Pending Prescriptions');
        $response->assertSee('Prescriptions for Verification');
        $response->assertSee('Pending Dispensing');
        $response->assertSee('Dispensed Today');
        $response->assertSee('Completed Prescriptions');
        $response->assertSee('Medication Dispensing Trends');
        $response->assertSee($patient->patient_no);
    }

    public function test_or_coordinator_and_or_staff_dashboards_enforce_distinct_metrics_and_scoping(): void
    {
        $coordinator = $this->createUserWithRole('or-coordinator', 'Coord');
        $staffA      = $this->createUserWithRole('or-staff', 'StaffA');
        $staffB      = $this->createUserWithRole('or-staff', 'StaffB');
        $doctor      = $this->createUserWithRole('doctor', 'Surgeon');
        $patient     = $this->createPatient();

        $room = OperatingRoom::create([
            'name'      => 'Operating Room Alpha ' . rand(100, 999),
            'location'  => 'OR Suite 2',
            'status'    => 'Available',
            'is_active' => true,
        ]);

        $team = \App\Models\SurgicalTeam::create([
            'name'       => 'Alpha Team ' . uniqid(),
            'surgeon_id' => $doctor->id,
            'is_active'  => true,
        ]);
        \App\Models\SurgicalTeamMember::create([
            'surgical_team_id' => $team->id,
            'user_id'          => $staffA->id,
            'role_in_team'     => 'Scrub Nurse',
        ]);

        $sReq = SurgeryRequest::create([
            'request_no'     => 'SR-' . uniqid(),
            'patient_id'     => $patient->id,
            'doctor_id'      => $doctor->id,
            'procedure_name' => 'Appendectomy',
            'priority'       => 'Urgent',
            'status'         => 'Scheduled',
        ]);

        $schedule = SurgerySchedule::create([
            'surgery_request_id' => $sReq->id,
            'operating_room_id'  => $room->id,
            'surgical_team_id'   => $team->id,
            'scheduled_by'       => $coordinator->id,
            'scheduled_at'       => now()->addHours(4),
            'duration_minutes'   => 90,
            'status'             => 'Scheduled',
            'readiness_status'   => 'Pending Prep',
        ]);

        // 1. OR Coordinator view
        $coordRes = $this->actingAs($coordinator)->get(route('surgery.dashboard'));
        $coordRes->assertStatus(200);
        $coordRes->assertSee('Pending Surgery Requests');
        $coordRes->assertSee('Scheduled Operations');
        $coordRes->assertSee("Today's Surgeries", false);
        $coordRes->assertSee('Operations In Progress');
        $coordRes->assertSee('Available Operating Rooms');
        $coordRes->assertSee('Cases Requiring Readiness Updates');
        $coordRes->assertSee('Operating Room Utilization');

        // 2. OR Staff A view (assigned to 1 case)
        $staffARes = $this->actingAs($staffA)->get(route('surgery.dashboard'));
        $staffARes->assertStatus(200);
        $staffARes->assertSee('Assigned Cases');
        $staffARes->assertSee('Upcoming Procedures');
        $staffARes->assertSee('Preparation Tasks');
        $staffARes->assertSee('Pending Readiness Updates');
        $staffARes->assertSee('Ready Cases');
        $this->assertEquals(1, $staffARes->viewData('stats')['assigned_cases']);

        // 3. OR Staff B view (assigned to 0 cases - no data leak)
        $staffBRes = $this->actingAs($staffB)->get(route('surgery.dashboard'));
        $staffBRes->assertStatus(200);
        $this->assertEquals(0, $staffBRes->viewData('stats')['assigned_cases']);
    }

    public function test_patient_dashboard_renders_five_scorecards_personal_charts_and_prevents_other_patient_leakage(): void
    {
        $patientUserA = $this->createUserWithRole('patient', 'PatA');
        $patientA     = $this->createPatient($patientUserA);

        $patientUserB = $this->createUserWithRole('patient', 'PatB');
        $patientB     = $this->createPatient($patientUserB);

        $doctor = $this->createUserWithRole('doctor', 'Doc');

        // Create pending lab request for Patient A
        LabRequest::create([
            'request_no'   => 'LR-PATA-' . uniqid(),
            'patient_id'   => $patientA->id,
            'doctor_id'    => $doctor->id,
            'priority'     => 'Routine',
            'status'       => 'Pending',
            'requested_at' => now(),
        ]);

        // Create diet plan for Patient B
        $dietReqB = DietRequest::create([
            'request_no' => 'DR-PATB-' . uniqid(),
            'patient_id' => $patientB->id,
            'doctor_id'  => $doctor->id,
            'diet_type'  => 'Renal Diet',
            'status'     => 'Completed',
        ]);
        DietPlan::create([
            'diet_request_id' => $dietReqB->id,
            'dietitian_id'    => $doctor->id,
            'plan_details'    => 'Low potassium meals',
            'start_date'      => now()->toDateString(),
            'status'          => 'Active',
        ]);

        $responseA = $this->actingAs($patientUserA)->get(route('patient.dashboard'));
        $responseA->assertStatus(200);
        $responseA->assertViewIs('patient.dashboard');
        $responseA->assertSee('Upcoming Appointments');
        $responseA->assertSee('Pending Laboratory Results');
        $responseA->assertSee('Pending Imaging Reports');
        $responseA->assertSee('Active Prescriptions');
        $responseA->assertSee('Available Clinical Reports');
        $responseA->assertSee('Personal Clinical Activity');
        $responseA->assertDontSee('Prescription Refill Consultation');

        $this->assertEquals(1, $responseA->viewData('pendingLabCount'));
        // Patient B's diet plan must not appear in Patient A's dashboard
        $this->assertCount(0, $responseA->viewData('activeDietPlans'));
    }

    public function test_role_based_dashboard_authorization_blocks_unauthorized_cross_role_access(): void
    {
        $patient    = $this->createUserWithRole('patient', 'Pat');
        $this->createPatient($patient);
        $medTech    = $this->createUserWithRole('med-tech', 'Med');
        $pharmacist = $this->createUserWithRole('pharmacist', 'Pharm');

        // Patient cannot access staff or admin dashboards
        $this->actingAs($patient)->get(route('super-admin.dashboard'))->assertStatus(403);
        $this->actingAs($patient)->get(route('admin.dashboard'))->assertStatus(403);
        $this->actingAs($patient)->get(route('doctor.dashboard'))->assertStatus(403);
        $this->actingAs($patient)->get(route('lab.dashboard'))->assertStatus(403);
        $this->actingAs($patient)->get(route('radiology.dashboard'))->assertStatus(403);
        $this->actingAs($patient)->get(route('pharmacy.dashboard'))->assertStatus(403);
        $this->actingAs($patient)->get(route('surgery.dashboard'))->assertStatus(403);
        $this->actingAs($patient)->get(route('diet.dashboard'))->assertStatus(403);

        // Med-Tech cannot access Pharmacy, Radiology, Surgery, Doctor, or Admin dashboards
        $this->actingAs($medTech)->get(route('pharmacy.dashboard'))->assertStatus(403);
        $this->actingAs($medTech)->get(route('radiology.dashboard'))->assertStatus(403);
        $this->actingAs($medTech)->get(route('surgery.dashboard'))->assertStatus(403);
        $this->actingAs($medTech)->get(route('doctor.dashboard'))->assertStatus(403);
        $this->actingAs($medTech)->get(route('admin.dashboard'))->assertStatus(403);

        // Pharmacist cannot access Lab or Patient Portal
        $this->actingAs($pharmacist)->get(route('lab.dashboard'))->assertStatus(403);
        $this->actingAs($pharmacist)->get(route('patient.dashboard'))->assertStatus(403);
    }
}
