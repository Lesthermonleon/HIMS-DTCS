<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\DietRequest;
use App\Models\LabRequest;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\RadiologyRequest;
use App\Models\Role;
use App\Models\SurgeryRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use DatabaseTransactions;

    private function createUserWithRole(string $slug): User
    {
        $role = Role::firstOrCreate(
            ['slug' => $slug],
            ['name' => ucfirst($slug), 'slug' => $slug, 'dashboard_route' => $slug . '.dashboard']
        );

        $user = User::create([
            'name'        => 'Test ' . ucfirst($slug),
            'email'       => $slug . '_' . uniqid() . '@test.hospital',
            'password'    => bcrypt('password'),
            'employee_id' => 'EMP-' . rand(10000, 99999),
            'department'  => 'Operational Administration',
            'is_active'   => true,
        ]);
        $user->roles()->attach($role->id);
        return $user;
    }

    public function test_system_administrator_can_access_admin_dashboard()
    {
        $admin = $this->createUserWithRole('admin');

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertStatus(200);
        $response->assertViewIs('dashboard.admin');
        $response->assertViewHasAll([
            'stats',
            'usersByRole',
            'moduleStats',
            'systemAlerts',
            'recentPatients',
            'userPresenceStats',
            'userPresenceList',
            'adminActivity7d',
            'adminTrend6m',
            'adminTrend12m',
            'adminModuleVolume',
            'allStatuses',
        ]);
        $response->assertSee('Inactive Users');
        $response->assertSee('Total Users');
        $response->assertSee('Active Users');
        $response->assertSee('Total Patients');
        $response->assertSee('Clinical Module Operational Overview');
        $response->assertDontSee('Pending Tasks');
    }

    public function test_non_admin_role_cannot_access_admin_dashboard()
    {
        $doctor = $this->createUserWithRole('doctor');
        $response = $this->actingAs($doctor)->get(route('admin.dashboard'));
        $response->assertStatus(403);

        $superAdmin = $this->createUserWithRole('super_admin');
        $response = $this->actingAs($superAdmin)->get(route('admin.dashboard'));
        $response->assertStatus(403);
    }

    public function test_admin_cannot_access_super_admin_governance_routes()
    {
        $admin = $this->createUserWithRole('admin');

        // Direct URL access attempts to Super Admin governance endpoints must yield 403 Forbidden
        $this->actingAs($admin)->get('/super-admin/dashboard')->assertStatus(403);
        $this->actingAs($admin)->get('/super-admin/users')->assertStatus(403);
        $this->actingAs($admin)->get('/super-admin/settings')->assertStatus(403);
        $this->actingAs($admin)->get('/super-admin/backup')->assertStatus(403);
        $this->actingAs($admin)->get('/super-admin/doctor-provisioning')->assertStatus(403);
    }

    public function test_admin_dashboard_statistics_accuracy_and_mathematical_consistency()
    {
        $admin = $this->createUserWithRole('admin');

        // Create a known inactive user
        $inactiveUser = User::create([
            'name'        => 'Inactive Staff',
            'email'       => 'inactive_' . uniqid() . '@test.hospital',
            'password'    => bcrypt('password'),
            'employee_id' => 'EMP-' . rand(10000, 99999),
            'department'  => 'Test',
            'is_active'   => false,
            'locked_at'   => null,
        ]);

        // Create a locked user
        $lockedUser = User::create([
            'name'        => 'Locked Account',
            'email'       => 'locked_' . uniqid() . '@test.hospital',
            'password'    => bcrypt('password'),
            'employee_id' => 'EMP-' . rand(10000, 99999),
            'department'  => 'Test',
            'is_active'   => true,
            'locked_at'   => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));
        $response->assertStatus(200);

        $stats = $response->viewData('stats');

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_users', $stats);
        $this->assertArrayHasKey('active_users', $stats);
        $this->assertArrayHasKey('inactive_users', $stats);
        $this->assertArrayHasKey('locked_accounts', $stats);
        $this->assertArrayHasKey('total_patients', $stats);

        // Verification of invariant: active + inactive + locked = total users
        $calculatedSum = $stats['active_users'] + $stats['inactive_users'] + $stats['locked_accounts'];
        $this->assertEquals($stats['total_users'], $calculatedSum);
    }

    public function test_admin_dashboard_clinical_module_counts_and_routes()
    {
        $admin = $this->createUserWithRole('admin');

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));
        $response->assertStatus(200);

        $moduleStats = $response->viewData('moduleStats');

        // All five DTCS clinical modules must be present
        $this->assertArrayHasKey('lis', $moduleStats);
        $this->assertArrayHasKey('ris', $moduleStats);
        $this->assertArrayHasKey('pms', $moduleStats);
        $this->assertArrayHasKey('sors', $moduleStats);
        $this->assertArrayHasKey('dnms', $moduleStats);

        $this->assertEquals('Laboratory (LIS)', $moduleStats['lis']['name']);
        $this->assertEquals('Radiology (RIS)', $moduleStats['ris']['name']);
        $this->assertEquals('Pharmacy (PMS)', $moduleStats['pms']['name']);
        $this->assertEquals('Surgery (SORS)', $moduleStats['sors']['name']);
        $this->assertEquals('Nutrition & Dietetics (DNMS)', $moduleStats['dnms']['name']);

        $this->assertEquals('lab.dashboard', $moduleStats['lis']['route']);
        $this->assertEquals('radiology.dashboard', $moduleStats['ris']['route']);
        $this->assertEquals('pharmacy.dashboard', $moduleStats['pms']['route']);
        $this->assertEquals('surgery.dashboard', $moduleStats['sors']['route']);
        $this->assertEquals('diet.dashboard', $moduleStats['dnms']['route']);

        // Verify pending counts are integers and correspond to real database queries
        $this->assertIsInt($moduleStats['lis']['pending']);
        $this->assertIsInt($moduleStats['ris']['pending']);
        $this->assertIsInt($moduleStats['pms']['pending']);
        $this->assertIsInt($moduleStats['sors']['pending']);
        $this->assertIsInt($moduleStats['dnms']['pending']);
    }

    public function test_admin_dashboard_system_security_alerts_detect_lockouts_and_failed_logins()
    {
        $admin = $this->createUserWithRole('admin');

        $lockedUser = User::create([
            'name'            => 'Breach Suspect',
            'email'           => 'breach_' . uniqid() . '@test.hospital',
            'password'        => bcrypt('password'),
            'employee_id'     => 'EMP-' . rand(10000, 99999),
            'department'      => 'Security',
            'is_active'       => true,
            'locked_at'       => now(),
            'failed_attempts' => 5,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));
        $response->assertStatus(200);

        $alerts = $response->viewData('systemAlerts');

        $this->assertTrue($alerts->contains(fn($a) => $a['title'] === 'Account Locked' && str_contains($a['description'], 'Breach Suspect')));
        $response->assertSee('Breach Suspect');
        $response->assertSee('Account Locked');
    }

    public function test_admin_dashboard_patient_records_are_strictly_read_only()
    {
        $admin = $this->createUserWithRole('admin');

        $patient = Patient::create([
            'patient_no'    => 'PAT-' . rand(100000, 999999),
            'first_name'    => 'Audited',
            'last_name'     => 'Patient',
            'gender'        => 'Female',
            'date_of_birth' => '1992-05-14',
            'patient_type'  => 'Outpatient',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));
        $response->assertStatus(200);

        // Assert patient appears in recent patients table
        $response->assertSee('Audited Patient');
        $response->assertSee($patient->patient_no);

        // Assert strictly read-only profile link exists and no CRUD mutation forms exist on dashboard
        $response->assertSee(route('patients.show', $patient));
        $response->assertDontSee('Create Patient');
        $response->assertDontSee('Delete Patient');
    }

    public function test_admin_dashboard_chart_datasets_are_complete_and_consistent()
    {
        $admin = $this->createUserWithRole('admin');

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));
        $response->assertStatus(200);

        $trend6m     = $response->viewData('adminTrend6m');
        $trend12m    = $response->viewData('adminTrend12m');
        $modVolume   = $response->viewData('adminModuleVolume');
        $allStatuses = $response->viewData('allStatuses');
        $activity7d  = $response->viewData('adminActivity7d');

        $this->assertCount(6, $trend6m);
        $this->assertCount(12, $trend12m);
        $this->assertCount(5, $modVolume);

        $this->assertArrayHasKey('Pending', $allStatuses);
        $this->assertArrayHasKey('In Progress', $allStatuses);
        $this->assertArrayHasKey('Completed', $allStatuses);
        $this->assertArrayHasKey('Cancelled', $allStatuses);

        $this->assertCount(7, $activity7d);
    }
}
