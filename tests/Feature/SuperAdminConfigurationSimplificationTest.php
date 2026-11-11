<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\CatalogChangeHistory;
use App\Models\CatalogItemState;
use App\Models\LabRequest;
use App\Models\LabRequestItem;
use App\Models\LabTest;
use App\Models\LabTestCategory;
use App\Models\Patient;
use App\Models\RadiologyRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SuperAdminConfigurationSimplificationTest extends TestCase
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
        ];

        foreach ($roles as $r) {
            Role::firstOrCreate(['slug' => $r['slug']], $r);
        }
    }

    private function createUser(string $roleSlug, ?string $department = null): User
    {
        $uniq = uniqid();
        $user = User::create([
            'name'        => 'Test ' . ucfirst($roleSlug),
            'email'       => "{$roleSlug}_{$uniq}@dtcs.hospital",
            'password'    => bcrypt('Secr3t!P@ssword2026'),
            'employee_id' => 'EMP-' . rand(10000, 99999),
            'department'  => $department ?? 'Laboratory',
            'is_active'   => true,
        ]);

        $role = Role::where('slug', $roleSlug)->firstOrFail();
        $user->roles()->attach($role->id);

        return $user;
    }

    /**
     * 1. Test Super Admin sees remaining relevant configuration menus and not removed ones.
     */
    public function test_super_admin_sidebar_displays_only_approved_dtcs_configuration_menus(): void
    {
        $superAdmin = $this->createUser('super_admin');

        $response = $this->actingAs($superAdmin)->get(route('super-admin.dashboard'));
        $response->assertStatus(200);

        // Retained approved configuration menus
        $response->assertSeeText('DEPARTMENTS & SERVICES');
        $response->assertSeeText('Department Management');
        $response->assertSeeText('Diagnostic Services Configuration');

        // Removed / decoupled menus
        $response->assertDontSeeText('Service Catalog & Pricing');
        $response->assertDontSeeText('Clinical Services Configuration');
        $response->assertDontSeeText('Treatment Services Configuration');
        $response->assertDontSeeText('External Healthcare Integrations');
    }

    /**
     * 2. Test unauthorized roles cannot access protected Super Admin configuration routes.
     */
    public function test_unauthorized_users_cannot_access_super_admin_configuration_routes(): void
    {
        // Unauthenticated access redirects to login
        $this->get(route('super-admin.departments.index'))->assertRedirect(route('login'));
        $this->get(route('super-admin.services.diagnostic'))->assertRedirect(route('login'));

        // Admin cannot access super admin configuration
        $admin = $this->createUser('admin');
        $this->actingAs($admin)->get(route('super-admin.departments.index'))->assertStatus(403);
        $this->actingAs($admin)->get(route('super-admin.services.diagnostic'))->assertStatus(403);

        // Doctor cannot access super admin configuration
        $doctor = $this->createUser('doctor');
        $this->actingAs($doctor)->get(route('super-admin.departments.index'))->assertStatus(403);
        $this->actingAs($doctor)->get(route('super-admin.services.diagnostic'))->assertStatus(403);
    }

    /**
     * 3. Test Department Monitor displays accurate DTCS counts and prevents department creation.
     */
    public function test_department_monitor_displays_accurate_counts_and_prevents_creation(): void
    {
        $superAdmin = $this->createUser('super_admin');

        $response = $this->actingAs($superAdmin)->get(route('super-admin.departments.index'));
        $response->assertStatus(200);

        // Page identification as a monitor
        $response->assertSeeText('DTCS Subsystem Departments & Resource Monitor');
        $response->assertSeeText('5 Predefined DTCS Subsystems');

        // All 5 DTCS subsystems present
        $response->assertSeeText('Laboratory');
        $response->assertSeeText('LIS-LAB');
        $response->assertSeeText('Radiology');
        $response->assertSeeText('RIS-RAD');
        $response->assertSeeText('Pharmacy');
        $response->assertSeeText('PMS-PHR');
        $response->assertSeeText('Operating Room');
        $response->assertSeeText('SORS-OR');
        $response->assertSeeText('Nutrition & Dietetics');
        $response->assertSeeText('DNMS-NUT');

        // Absence of misleading creation form & modal
        $response->assertDontSeeText('Add Department');
        $response->assertDontSee('id="newDepartmentModal"', false);

        // Rejection of POST creation attempts
        $postResponse = $this->actingAs($superAdmin)->post('/super-admin/departments', [
            'name' => 'Cardiology & Vascular',
            'code' => 'CARD-01',
            'type' => 'Clinical',
        ]);
        $this->assertEquals(405, $postResponse->status());
    }

    /**
     * 4. Test LIS lifecycle and price updates affect future ordering while preserving historical records.
     */
    public function test_lis_lifecycle_and_pricing_updates_affect_future_ordering_and_preserve_history(): void
    {
        $superAdmin = $this->createUser('super_admin');
        $doctor = $this->createUser('doctor');

        $category = LabTestCategory::firstOrCreate(
            ['code' => 'TEST-CAT'],
            ['name' => 'Test Category', 'description' => 'Test Category Description']
        );

        $testCode = 'TST-' . rand(1000, 9999);
        $labTest = LabTest::create([
            'code'                 => $testCode,
            'name'                 => 'Specific Automated Test ' . $testCode,
            'lab_test_category_id' => $category->id,
            'specimen_type'        => 'Blood',
            'price'                => 350.00,
            'is_active'            => true,
            'turnaround_hours'     => 4,
        ]);

        $patient = Patient::firstOrCreate(
            ['patient_no' => 'P-TEST-001'],
            [
                'first_name'    => 'John',
                'last_name'     => 'Doe',
                'gender'        => 'Male',
                'date_of_birth' => '1990-01-01',
            ]
        );

        // Create historical lab request and item using this test
        $labRequest = LabRequest::create([
            'request_no'     => 'LR-HIST-' . rand(1000, 9999),
            'patient_id'     => $patient->id,
            'doctor_id'      => $doctor->id,
            'priority'       => 'Routine',
            'specimen_type'  => 'Blood',
            'clinical_notes' => 'Pre-operative screening',
            'status'         => 'Pending',
            'requested_at'   => now(),
        ]);

        $labRequestItem = LabRequestItem::create([
            'lab_request_id' => $labRequest->id,
            'lab_test_id'    => $labTest->id,
            'status'         => 'Pending',
        ]);

        // Prior to deactivation, test is available for doctor orders
        $activeTestIdsBefore = LabTest::where('is_active', true)->pluck('id')->all();
        $this->assertContains($labTest->id, $activeTestIdsBefore);

        // Super Admin updates price and deactivates the test
        $response = $this->actingAs($superAdmin)->patch(
            route('super-admin.catalog.lab-tests.lifecycle', $labTest),
            [
                'base_price' => 495.50,
                'status'     => 'inactive',
                'reason'     => 'Reagent shortage transition to alternate analyzer',
            ]
        );
        $response->assertSessionHas('success');

        // Test attributes updated on real lab_tests table
        $labTest->refresh();
        $this->assertEquals(495.50, (float) $labTest->price);
        $this->assertFalse((bool) $labTest->is_active);

        // Side state tracked
        $sideState = CatalogItemState::where('entity_type', 'lab_test')
            ->where('entity_id', $labTest->id)
            ->first();
        $this->assertNotNull($sideState);
        $this->assertEquals('inactive', $sideState->status);

        // Audit history created
        $history = CatalogChangeHistory::where('entity_type', 'lab_test')
            ->where('entity_id', $labTest->id)
            ->latest('id')
            ->first();
        $this->assertNotNull($history);
        $this->assertEquals('inactive', $history->action);
        $this->assertEquals('Reagent shortage transition to alternate analyzer', $history->reason);

        // Future orders: test is excluded from active ordering query
        $activeTestIdsAfter = LabTest::where('is_active', true)->pluck('id')->all();
        $this->assertNotContains($labTest->id, $activeTestIdsAfter);

        // Historical order integrity: historical lab_request_item is NOT deleted or modified
        $this->assertDatabaseHas('lab_request_items', [
            'id'             => $labRequestItem->id,
            'lab_request_id' => $labRequest->id,
            'lab_test_id'    => $labTest->id,
        ]);
        $this->assertEquals($labTest->name, $labRequestItem->fresh()->labTest->name);
    }

    /**
     * 5. Test Diagnostic Services Configuration accurately reports RIS modalities separately from LIS.
     */
    public function test_diagnostic_services_configuration_reports_ris_modalities_and_lis_tests(): void
    {
        $superAdmin = $this->createUser('super_admin');

        $category = LabTestCategory::firstOrCreate(
            ['code' => 'TEST-CAT'],
            ['name' => 'Test Category', 'description' => 'Test Category Description']
        );
        LabTest::firstOrCreate(
            ['code' => 'CBC-TEST'],
            [
                'name'                 => 'Complete Blood Count',
                'lab_test_category_id' => $category->id,
                'specimen_type'        => 'Blood',
                'price'                => 250.00,
                'is_active'            => true,
            ]
        );

        // LIS Tab view
        $labResponse = $this->actingAs($superAdmin)->get(route('super-admin.services.diagnostic', ['tab' => 'laboratory']));
        $labResponse->assertStatus(200);
        $labResponse->assertSeeText('Laboratory Diagnostic Tests (LIS)');
        $labResponse->assertSeeText('Live LIS & RIS Operational Data');
        $labResponse->assertSeeText('Price & Lifecycle');

        // RIS Tab view
        $risResponse = $this->actingAs($superAdmin)->get(route('super-admin.services.diagnostic', ['tab' => 'radiology']));
        $risResponse->assertStatus(200);
        $risResponse->assertSeeText('Standardized Radiology & Imaging Modalities (RIS)');
        $risResponse->assertSeeText('General Radiography (X-Ray)');
        $risResponse->assertSeeText('Computed Tomography (CT Scan)');
        $risResponse->assertSeeText('Magnetic Resonance Imaging (MRI)');
        $risResponse->assertSeeText('Diagnostic Ultrasonography');
        $risResponse->assertSeeText('Diagnostic & Screening Mammography');
        $risResponse->assertSeeText('Fluoroscopic Real-Time Imaging');

        // RIS operational notice explaining workflow
        $risResponse->assertSeeText('RIS Operational Architecture');
        $risResponse->assertSeeText('Radiology imaging requests operate through physician order entry');
        $risResponse->assertDontSeeText('Add Imaging Procedure in Master Catalog');
    }
}
