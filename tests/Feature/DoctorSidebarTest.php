<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DoctorSidebarTest extends TestCase
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

    private function createUserWithRole(string $slug, string $department = 'Internal Medicine'): User
    {
        $role = Role::where('slug', $slug)->firstOrFail();

        $user = User::create([
            'name'        => 'Dr. ' . ucfirst($slug) . ' ' . rand(100, 999),
            'email'       => $slug . '_' . uniqid() . '@dtcs.hospital',
            'password'    => bcrypt('Secr3t!P@ssword2026'),
            'employee_id' => 'EMP-' . rand(10000, 99999),
            'department'  => $department,
            'is_active'   => true,
        ]);
        $user->roles()->attach($role->id);

        return $user;
    }

    /**
     * Test Doctor sidebar renders correct section hierarchy and links without duplicates.
     */
    public function test_doctor_sidebar_renders_expected_navigation_structure(): void
    {
        $doctor = $this->createUserWithRole('doctor');

        $response = $this->actingAs($doctor)->get(route('doctor.dashboard'));
        $response->assertStatus(200);

        $content = $response->getContent();

        // 1. Verify Section Headers in layout
        $this->assertStringContainsString('MAIN', $content);
        $this->assertStringContainsString('CLINICAL MODULES', $content);
        $this->assertStringContainsString('PATIENTS', $content);
        $this->assertStringContainsString('REPORTS', $content);

        // 2. Verify MAIN links
        $this->assertStringContainsString(route('doctor.dashboard'), $content);
        $this->assertStringContainsString(route('admin.appointments.index'), $content);
        $this->assertStringContainsString('Appointments', $content);
        $this->assertStringNotContainsString('Patient Queue & Appointments', $content);
        $this->assertStringContainsString(route('medisense.index'), $content);

        // 3. Verify exactly ONE MediSense AI navigation entry in the rendered sidebar partial
        $sidebarContent = view('partials._sidebar')->render();
        $medisenseHrefCount = substr_count($sidebarContent, 'href="' . route('medisense.index') . '"');
        $this->assertEquals(1, $medisenseHrefCount, 'MediSense AI must appear exactly once in the sidebar navigation.');

        // 4. Verify CLINICAL MODULES group toggles and links in sidebar
        // Laboratory (LIS)
        $this->assertStringContainsString('Laboratory <span class="sb-badge">LIS</span>', $sidebarContent);
        $this->assertStringContainsString(route('lab.dashboard'), $sidebarContent);
        $this->assertStringContainsString(route('lab.requests.index'), $sidebarContent);
        $this->assertStringContainsString(route('lab.results.index'), $sidebarContent);

        // Radiology (RIS)
        $this->assertStringContainsString('Radiology <span class="sb-badge">RIS</span>', $sidebarContent);
        $this->assertStringContainsString(route('radiology.dashboard'), $sidebarContent);
        $this->assertStringContainsString(route('radiology.requests.index'), $sidebarContent);
        $this->assertStringContainsString(route('radiology.reports.index'), $sidebarContent);

        // Pharmacy (PMS)
        $this->assertStringContainsString('Pharmacy <span class="sb-badge">PMS</span>', $sidebarContent);
        $this->assertStringContainsString(route('pharmacy.dashboard'), $sidebarContent);
        $this->assertStringContainsString(route('pharmacy.prescriptions.index'), $sidebarContent);
        $this->assertStringContainsString(route('pharmacy.medicines.index'), $sidebarContent);
        // Ensure doctor does NOT have dispensing link
        $this->assertStringNotContainsString(route('pharmacy.dispensing.index'), $sidebarContent);

        // Surgery (SORS)
        $this->assertStringContainsString('Surgery <span class="sb-badge">SORS</span>', $sidebarContent);
        $this->assertStringContainsString(route('surgery.dashboard'), $sidebarContent);
        $this->assertStringContainsString(route('surgery.requests.index'), $sidebarContent);
        $this->assertStringContainsString(route('surgery.schedules.index'), $sidebarContent);
        $this->assertStringContainsString(route('surgery.calendar'), $sidebarContent);

        // Nutrition (DNMS) — simplified sidebar: Overview, Diet Plans, Nutrition Reports (no separate Diet Requests link)
        $this->assertStringContainsString('Nutrition <span class="sb-badge">DNMS</span>', $sidebarContent);
        $this->assertStringContainsString(route('diet.dashboard'), $sidebarContent);
        $this->assertStringContainsString(route('diet.plans.index'), $sidebarContent);
        $this->assertStringContainsString(route('reports.diet.activity'), $sidebarContent);
        $this->assertStringNotContainsString(route('diet.requests.index'), $sidebarContent);

        // 5. Verify PATIENTS
        $this->assertStringContainsString(route('patients.index'), $sidebarContent);
        $this->assertStringContainsString('Patient Directory', $sidebarContent);

        // 6. Verify REPORTS: Clinical Reports is the single central entry point (no separate Surgery Reports sidebar link)
        $this->assertStringContainsString(route('reports.index'), $sidebarContent);
        $this->assertStringContainsString('Clinical Reports', $sidebarContent);
        $this->assertEquals(
            1,
            substr_count($sidebarContent, 'href="' . route('reports.index') . '"'),
            'Clinical Reports must appear exactly once under REPORTS in the Doctor sidebar.'
        );
        $this->assertStringNotContainsString(
            route('reports.surgery.activity'),
            $sidebarContent,
            'Surgery Reports must not appear as a separate link in the Doctor sidebar.'
        );

        // 7. Verify no admin/technician administrative links are present in doctor sidebar
        $this->assertStringNotContainsString(route('admin.users.index'), $sidebarContent);
        $this->assertStringNotContainsString(route('admin.audit-logs.index'), $sidebarContent);
        $this->assertStringNotContainsString(route('admin.hospital-settings.index'), $sidebarContent);
        $this->assertStringNotContainsString(route('super-admin.dashboard'), $sidebarContent);
        $this->assertStringNotContainsString(route('super-admin.users.index'), $sidebarContent);
    }

    /**
     * Test Doctor can access all 21 authorized navigation destinations with HTTP 200.
     */
    public function test_doctor_can_access_all_authorized_sidebar_destinations(): void
    {
        $doctor = $this->createUserWithRole('doctor');

        $routesToTest = [
            // MAIN
            'doctor.dashboard',
            'admin.appointments.index',
            'medisense.index',
            // LIS
            'lab.dashboard',
            'lab.requests.index',
            'lab.results.index',
            // RIS
            'radiology.dashboard',
            'radiology.requests.index',
            'radiology.reports.index',
            // PMS
            'pharmacy.dashboard',
            'pharmacy.prescriptions.index',
            'pharmacy.medicines.index',
            // SORS
            'surgery.dashboard',
            'surgery.requests.index',
            'surgery.schedules.index',
            'surgery.calendar',
            // DNMS
            'diet.dashboard',
            'diet.requests.index',
            'diet.plans.index',
            // PATIENTS
            'patients.index',
            // REPORTS
            'reports.index',
            'reports.surgery.activity',
        ];

        foreach ($routesToTest as $routeName) {
            $response = $this->actingAs($doctor)->get(route($routeName));
            $this->assertEquals(
                200,
                $response->status(),
                "Doctor must have HTTP 200 access to [{$routeName}] at URL: " . route($routeName)
            );
        }
    }

    /**
     * Test Doctor is strictly forbidden (HTTP 403) from administrative and technician-only routes.
     */
    public function test_doctor_cannot_access_unauthorized_administrative_or_staff_routes(): void
    {
        $doctor = $this->createUserWithRole('doctor');

        $forbiddenRoutes = [
            'pharmacy.dispensing.index',
            'admin.users.index',
            'admin.roles.index',
            'admin.audit-logs.index',
            'admin.hospital-settings.index',
            'super-admin.dashboard',
            'super-admin.users.index',
            'super-admin.audit-vault.index',
            'super-admin.departments.index',
            'super-admin.settings.index',
        ];

        foreach ($forbiddenRoutes as $routeName) {
            $response = $this->actingAs($doctor)->get(route($routeName));
            $this->assertEquals(
                403,
                $response->status(),
                "Doctor must be forbidden (HTTP 403) from accessing [{$routeName}]."
            );
        }
    }

    /**
     * Test Patient Directory is strictly read-only for master patient records (no CRUD/create routes).
     */
    public function test_patient_directory_is_read_only_with_no_create_routes(): void
    {
        $doctor = $this->createUserWithRole('doctor');

        // Directory index is accessible
        $response = $this->actingAs($doctor)->get(route('patients.index'));
        $response->assertStatus(200);

        // Verification that there is no 'patients.create' route in the application
        $this->assertFalse(
            \Illuminate\Support\Facades\Route::has('patients.create'),
            'patients.create route should not exist to maintain read-only master patient directory'
        );

        // Direct GET to /patients/create should return 404
        $createResponse = $this->actingAs($doctor)->get('/patients/create');
        $this->assertEquals(404, $createResponse->status());
    }

    /**
     * Test Doctor Reports index strictly exposes clinical categories and filters out administrative/financial reports.
     */
    public function test_clinical_reports_limits_doctor_to_clinical_categories(): void
    {
        $doctor = $this->createUserWithRole('doctor');

        $response = $this->actingAs($doctor)->get(route('reports.index'));
        $response->assertStatus(200);

        // View data verification: categories passed to view should be clinical only
        $categories = $response->viewData('categories');
        $this->assertIsArray($categories);

        $allowedCategories = [
            'overview',
            'laboratory',
            'radiology',
            'pharmacy',
            'surgery',
            'dietary',
            'diet',
            'nutrition',
            'clinical',
        ];

        foreach ($categories as $cat) {
            $this->assertTrue(
                in_array(strtolower($cat), $allowedCategories),
                "Category [{$cat}] must be clinical for Doctor access."
            );
        }

        // Verify all 6 Clinical Reports categories and their routes are rendered on the hub and return HTTP 200
        $hubHtml = $response->getContent();
        $expectedReportRoutes = [
            'reports.laboratory.activity',
            'reports.radiology.activity',
            'reports.pharmacy.activity',
            'reports.surgery.activity',
            'reports.diet.activity',
            'reports.clinical.patient-activity',
            'reports.clinical.doctor-activity',
            'reports.clinical.services-summary',
        ];

        foreach ($expectedReportRoutes as $routeName) {
            $url = route($routeName);
            $this->assertStringContainsString($url, $hubHtml, "Clinical Reports hub must link to [{$routeName}].");
            $subResp = $this->actingAs($doctor)->get($url);
            $subResp->assertStatus(200);
        }

        // Visiting Surgery Reports highlights Clinical Reports as active in the Doctor sidebar
        $surgReportHtml = $this->actingAs($doctor)->get(route('reports.surgery.activity'))->getContent();
        $this->assertMatchesRegularExpression(
            '/<a href="' . preg_quote(route('reports.index'), '/') . '" class="nav-link active">/',
            $surgReportHtml
        );
    }

    /**
     * Test Doctor Clinical Reports strictly enforce patient authorization scope and Doctor activity isolation.
     */
    public function test_doctor_clinical_reports_enforce_patient_and_doctor_isolation(): void
    {
        $doctor1 = $this->createUserWithRole('doctor');
        $doctor2 = $this->createUserWithRole('doctor');

        $patient1 = Patient::create([
            'patient_no'    => 'PAT-DR1-' . rand(10000, 99999),
            'first_name'    => 'Alice',
            'last_name'     => 'AuthorizedPt',
            'date_of_birth' => '1985-03-10',
            'gender'        => 'Female',
            'patient_type'  => 'Inpatient',
        ]);

        $patient2 = Patient::create([
            'patient_no'    => 'PAT-DR2-' . rand(10000, 99999),
            'first_name'    => 'Bob',
            'last_name'     => 'RestrictedPt',
            'date_of_birth' => '1978-11-22',
            'gender'        => 'Male',
            'patient_type'  => 'Inpatient',
        ]);

        // Exclusive clinical orders for Doctor 1 -> Patient 1 and Doctor 2 -> Patient 2
        $lab1 = \App\Models\LabRequest::create([
            'request_no'   => 'LAB-D1-' . uniqid(),
            'patient_id'   => $patient1->id,
            'doctor_id'    => $doctor1->id,
            'priority'     => 'Routine',
            'status'       => 'Pending',
            'requested_at' => now(),
        ]);
        $lab2 = \App\Models\LabRequest::create([
            'request_no'   => 'LAB-D2-' . uniqid(),
            'patient_id'   => $patient2->id,
            'doctor_id'    => $doctor2->id,
            'priority'     => 'Routine',
            'status'       => 'Pending',
            'requested_at' => now(),
        ]);

        $rad1 = \App\Models\RadiologyRequest::create([
            'request_no'           => 'RAD-D1-' . uniqid(),
            'patient_id'           => $patient1->id,
            'doctor_id'            => $doctor1->id,
            'modality'             => 'X-Ray',
            'body_part'            => 'Chest PA',
            'clinical_information' => 'Cough',
            'priority'             => 'Routine',
            'status'               => 'Pending',
            'requested_at'         => now(),
        ]);
        $rad2 = \App\Models\RadiologyRequest::create([
            'request_no'           => 'RAD-D2-' . uniqid(),
            'patient_id'           => $patient2->id,
            'doctor_id'            => $doctor2->id,
            'modality'             => 'CT',
            'body_part'            => 'Head CT',
            'clinical_information' => 'Trauma',
            'priority'             => 'Urgent',
            'status'               => 'Pending',
            'requested_at'         => now(),
        ]);

        $rx1 = \App\Models\Prescription::create([
            'prescription_no' => 'RX-D1-' . uniqid(),
            'patient_id'      => $patient1->id,
            'doctor_id'       => $doctor1->id,
            'status'          => 'Pending',
            'prescribed_at'   => now(),
        ]);
        $rx2 = \App\Models\Prescription::create([
            'prescription_no' => 'RX-D2-' . uniqid(),
            'patient_id'      => $patient2->id,
            'doctor_id'       => $doctor2->id,
            'status'          => 'Pending',
            'prescribed_at'   => now(),
        ]);

        $surg1 = \App\Models\SurgeryRequest::create([
            'request_no'     => 'SRG-D1-' . uniqid(),
            'patient_id'     => $patient1->id,
            'doctor_id'      => $doctor1->id,
            'procedure_name' => 'Laparoscopic Cholecystectomy',
            'urgency'        => 'Elective',
            'status'         => 'Pending',
            'requested_at'   => now(),
        ]);
        $surg2 = \App\Models\SurgeryRequest::create([
            'request_no'     => 'SRG-D2-' . uniqid(),
            'patient_id'     => $patient2->id,
            'doctor_id'      => $doctor2->id,
            'procedure_name' => 'Open Appendectomy',
            'urgency'        => 'Emergency',
            'status'         => 'Pending',
            'requested_at'   => now(),
        ]);

        // 1. Laboratory, Radiology, Pharmacy, and Surgery reports isolate Doctor 1 from Doctor 2's patient records (even if ?doctor_id=doctor2 is passed)
        $this->actingAs($doctor1)->get(route('reports.laboratory.activity', ['doctor_id' => $doctor2->id]))
            ->assertOk()
            ->assertSee($lab1->request_no)
            ->assertDontSee($lab2->request_no)
            ->assertDontSee('RestrictedPt');

        $this->actingAs($doctor1)->get(route('reports.radiology.activity', ['doctor_id' => $doctor2->id]))
            ->assertOk()
            ->assertSee($rad1->request_no)
            ->assertDontSee($rad2->request_no)
            ->assertDontSee('RestrictedPt');

        $this->actingAs($doctor1)->get(route('reports.pharmacy.activity', ['doctor_id' => $doctor2->id]))
            ->assertOk()
            ->assertSee($rx1->prescription_no)
            ->assertDontSee($rx2->prescription_no)
            ->assertDontSee('RestrictedPt');

        $this->actingAs($doctor1)->get(route('reports.surgery.activity', ['doctor_id' => $doctor2->id]))
            ->assertOk()
            ->assertSee($surg1->request_no)
            ->assertDontSee($surg2->request_no)
            ->assertDontSee('RestrictedPt');

        // 2. Patient Clinical Activity Report respects Doctor 1's patient authorization scope
        $this->actingAs($doctor1)->get(route('reports.clinical.patient-activity'))
            ->assertOk()
            ->assertSee('AuthorizedPt')
            ->assertDontSee('RestrictedPt');

        // 3. Doctor Activity Summary only shows Doctor 1's own activity and never exposes Doctor 2
        $this->actingAs($doctor1)->get(route('reports.clinical.doctor-activity'))
            ->assertOk()
            ->assertSee($doctor1->name)
            ->assertDontSee($doctor2->name);

        // 4. Clinical Services Summary reflects only Doctor 1's scoped totals (1 each, not 2)
        $summaryResp = $this->actingAs($doctor1)->get(route('reports.clinical.services-summary'))->assertOk();
        $summaryData = $summaryResp->viewData('summary');
        $this->assertSame(1, $summaryData['lab']['total']);
        $this->assertSame(1, $summaryData['radiology']['total']);
        $this->assertSame(1, $summaryData['pharmacy']['total']);
        $this->assertSame(1, $summaryData['surgery']['total']);
    }

    /**
     * Test Doctor appointment queue isolates records to the authenticated doctor.
     */
    public function test_doctor_appointment_queue_is_scoped_to_doctor(): void
    {
        $doctor1 = $this->createUserWithRole('doctor');
        $doctor2 = $this->createUserWithRole('doctor');

        $patient = Patient::create([
            'patient_no'    => 'PAT-' . date('Y') . '-' . rand(10000, 99999),
            'first_name'    => 'QueueFirst',
            'last_name'     => 'QueueLast',
            'date_of_birth' => '1990-01-01',
            'gender'        => 'Male',
            'patient_type'  => 'Outpatient',
        ]);

        $aptDoctor1 = Appointment::create([
            'appointment_no'   => 'APT-' . uniqid(),
            'patient_id'       => $patient->id,
            'doctor_id'        => $doctor1->id,
            'scheduled_at'     => now()->addHour(),
            'duration_minutes' => 30,
            'type'             => 'In-Person Consultation',
            'status'           => 'Confirmed',
            'reason'           => 'Doctor 1 Specific Case',
        ]);

        $aptDoctor2 = Appointment::create([
            'appointment_no'   => 'APT-' . uniqid(),
            'patient_id'       => $patient->id,
            'doctor_id'        => $doctor2->id,
            'scheduled_at'     => now()->addHours(2),
            'duration_minutes' => 30,
            'type'             => 'In-Person Consultation',
            'status'           => 'Confirmed',
            'reason'           => 'Doctor 2 Specific Case',
        ]);

        // Doctor 1 views the appointment queue
        $response1 = $this->actingAs($doctor1)->get(route('admin.appointments.index'));
        $response1->assertStatus(200);
        $this->assertTrue($response1->viewData('doctorScope'), 'doctorScope must be true for Doctor.');
        $response1->assertSee($aptDoctor1->appointment_no);
        $response1->assertDontSee($aptDoctor2->appointment_no);

        // Doctor 2 views the appointment queue
        $response2 = $this->actingAs($doctor2)->get(route('admin.appointments.index'));
        $response2->assertStatus(200);
        $this->assertTrue($response2->viewData('doctorScope'), 'doctorScope must be true for Doctor.');
        $response2->assertSee($aptDoctor2->appointment_no);
        $response2->assertDontSee($aptDoctor1->appointment_no);
    }
}
