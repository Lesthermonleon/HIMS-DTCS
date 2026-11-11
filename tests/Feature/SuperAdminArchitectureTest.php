<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ClinicalCodeSystem;
use App\Models\ClinicalCodeVersion;
use App\Models\FinancialRule;
use App\Models\PrivilegedAccessRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\Settings\SettingsRegistry;
use App\Services\Settings\SettingsService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SuperAdminArchitectureTest extends TestCase
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
        ];

        foreach ($roles as $r) {
            Role::firstOrCreate(['slug' => $r['slug']], $r);
        }
    }

    private function createUser(string $roleSlug): User
    {
        $uniq = uniqid();
        $user = User::create([
            'name'        => 'Test ' . ucfirst($roleSlug),
            'email'       => "{$roleSlug}_{$uniq}@dtcs.hospital",
            'password'    => bcrypt('Secr3t!P@ssword2026'),
            'employee_id' => 'EMP-' . rand(10000, 99999),
            'department'  => 'Administration',
            'is_active'   => true,
        ]);

        $role = Role::where('slug', $roleSlug)->firstOrFail();
        $user->roles()->attach($role->id);

        return $user;
    }

    // ═══════════════════════════════════════════════════════════════════
    // 1. Super Admin Authorization & Boundary Enforcement
    // ═══════════════════════════════════════════════════════════════════

    public function test_super_admin_can_access_super_admin_dashboard_and_modules(): void
    {
        $superAdmin = $this->createUser('super_admin');

        $this->actingAs($superAdmin)->get(route('super-admin.dashboard'))->assertStatus(200);
        $this->actingAs($superAdmin)->get(route('super-admin.departments.index'))->assertStatus(200);
        $this->actingAs($superAdmin)->get(route('super-admin.services.diagnostic'))->assertStatus(200);
        $this->actingAs($superAdmin)->get(route('super-admin.catalog.index'))->assertStatus(200);
        $this->actingAs($superAdmin)->get(route('admin.users.index'))->assertStatus(200);
        $this->actingAs($superAdmin)->get(route('admin.roles.index'))->assertStatus(200);
        $this->actingAs($superAdmin)->get(route('reports.index'))->assertStatus(200);
        $this->actingAs($superAdmin)->get(route('super-admin.clinical-codes.index'))->assertStatus(200);
        $this->actingAs($superAdmin)->get(route('super-admin.audit-vault.index'))->assertStatus(200);
        $this->actingAs($superAdmin)->get(route('super-admin.admins.index'))->assertStatus(200);
        $this->actingAs($superAdmin)->get(route('super-admin.doctor-provisioning.index'))->assertStatus(200);
        $this->actingAs($superAdmin)->get(route('super-admin.settings.index'))->assertStatus(200);
        $this->actingAs($superAdmin)->get(route('super-admin.backup.index'))->assertStatus(200);
        $this->actingAs($superAdmin)->get(route('admin.hospital-settings.index'))->assertStatus(200);

        // Deprecated configuration endpoints safely redirect
        $this->actingAs($superAdmin)->get(route('super-admin.services.clinical'))->assertRedirect(route('super-admin.services.diagnostic'));
        $this->actingAs($superAdmin)->get(route('super-admin.services.treatment'))->assertRedirect(route('super-admin.catalog.index'));
    }

    public function test_admin_cannot_access_super_admin_routes(): void
    {
        $admin = $this->createUser('admin');

        $this->actingAs($admin)->get(route('super-admin.dashboard'))->assertStatus(403);
        $this->actingAs($admin)->get(route('super-admin.departments.index'))->assertStatus(403);
        $this->actingAs($admin)->get(route('super-admin.services.clinical'))->assertStatus(403);
        $this->actingAs($admin)->get(route('super-admin.services.diagnostic'))->assertStatus(403);
        $this->actingAs($admin)->get(route('super-admin.services.treatment'))->assertStatus(403);
        $this->actingAs($admin)->get(route('super-admin.catalog.index'))->assertStatus(403);
        $this->actingAs($admin)->get(route('super-admin.clinical-codes.index'))->assertStatus(403);
        $this->actingAs($admin)->get(route('super-admin.audit-vault.index'))->assertStatus(403);
        $this->actingAs($admin)->get(route('super-admin.admins.index'))->assertStatus(403);
        $this->actingAs($admin)->get(route('super-admin.doctor-provisioning.index'))->assertStatus(403);
        $this->actingAs($admin)->get(route('super-admin.settings.index'))->assertStatus(403);
        $this->actingAs($admin)->get(route('super-admin.backup.index'))->assertStatus(403);
    }

    public function test_super_admin_sidebar_navigation_structure_and_labels(): void
    {
        $superAdmin = $this->createUser('super_admin');

        $response = $this->actingAs($superAdmin)->get(route('super-admin.dashboard'));
        $response->assertStatus(200);

        // Group headings
        $response->assertSeeText('MAIN');
        $response->assertSeeText('ACCESS & GOVERNANCE');
        $response->assertSeeText('DEPARTMENTS & SERVICES');
        $response->assertSeeText('MONITORING & REPORTS');
        $response->assertSeeText('SYSTEM & SECURITY');

        // Verified active items
        $response->assertSeeText('Dashboard');
        $response->assertSeeText('User Management');
        $response->assertSeeText('Roles & Permissions');
        $response->assertSeeText('Department Management');
        $response->assertSeeText('Diagnostic Services Configuration');
        $response->assertSeeText('Reports & Analytics');
        $response->assertSeeText('Audit Logs');
        $response->assertSeeText('Security Settings');
        $response->assertSeeText('Backup & Recovery');
        $response->assertSeeText('System Settings');

        // Confirmed removed / unnecessary configuration menus
        $response->assertDontSeeText('Service Catalog & Pricing');
        $response->assertDontSeeText('Clinical Services Configuration');
        $response->assertDontSeeText('Treatment Services Configuration');
        $response->assertDontSeeText('External Healthcare Integrations');
        $response->assertDontSee('Integrations</span>', false);
    }

    public function test_department_management_focuses_strictly_on_dtcs_modules(): void
    {
        $superAdmin = $this->createUser('super_admin');

        $response = $this->actingAs($superAdmin)->get(route('super-admin.departments.index'));
        $response->assertStatus(200);

        // Displays all 5 DTCS subsystem departments and codes
        $response->assertSeeText('Laboratory');
        $response->assertSeeText('LIS-LAB');
        $response->assertSeeText('LIS');

        $response->assertSeeText('Radiology');
        $response->assertSeeText('RIS-RAD');
        $response->assertSeeText('RIS');

        $response->assertSeeText('Pharmacy');
        $response->assertSeeText('PMS-PHR');
        $response->assertSeeText('PMS');

        $response->assertSeeText('Operating Room');
        $response->assertSeeText('SORS-OR');
        $response->assertSeeText('SORS');

        $response->assertSeeText('Nutrition & Dietetics');
        $response->assertSeeText('DNMS-NUT');
        $response->assertSeeText('DNMS');

        // Page title, descriptions, and no fake Add Department button/modal
        $response->assertSeeText('DTCS Subsystem Departments & Resource Monitor');
        $response->assertDontSeeText('Add Department');
        $response->assertDontSee('id="newDepartmentModal"', false);

        // Unrelated non-DTCS hospital departments are excluded
        $response->assertDontSeeText('Emergency Department');
        $response->assertDontSeeText('Hospital Administration');

        // Cannot create non-existent departments (POST route disabled/removed)
        $postResponse = $this->actingAs($superAdmin)->post('/super-admin/departments', [
            'name' => 'Cardiology',
            'code' => 'CARD-01',
            'type' => 'Clinical',
        ]);
        $this->assertEquals(405, $postResponse->status());
    }

    public function test_super_admin_can_manage_user_accounts_via_admin_module(): void
    {
        $superAdmin = $this->createUser('super_admin');
        $doctor = $this->createUser('doctor');

        $this->actingAs($superAdmin)->get(route('admin.users.edit', $doctor))->assertStatus(200);
    }

    public function test_clinical_boundary_super_admin_cannot_access_medisense_or_patient_records(): void
    {
        $superAdmin = $this->createUser('super_admin');

        // MediSense AI is strictly restricted to Doctors
        $this->actingAs($superAdmin)->get(route('medisense.index'))->assertStatus(403);

        // Patient Records are restricted to Admin and Doctor
        $this->actingAs($superAdmin)->get(route('patients.index'))->assertStatus(403);

        // Operational dashboards strictly restricted from Super Admin
        $this->actingAs($superAdmin)->get(route('admin.dashboard'))->assertStatus(403);
        $this->actingAs($superAdmin)->get(route('lab.dashboard'))->assertStatus(403);

        // Clinical action execution endpoints exclude super_admin from authorized roles
        $labReleaseRoute = \Illuminate\Support\Facades\Route::getRoutes()->getByName('lab.results.release');
        $this->assertNotNull($labReleaseRoute);
        $this->assertFalse(str_contains(implode(',', $labReleaseRoute->gatherMiddleware()), 'super_admin'));

        $rxVerifyRoute = \Illuminate\Support\Facades\Route::getRoutes()->getByName('pharmacy.prescriptions.verify');
        $this->assertNotNull($rxVerifyRoute);
        $this->assertFalse(str_contains(implode(',', $rxVerifyRoute->gatherMiddleware()), 'super_admin'));

        $radReleaseRoute = \Illuminate\Support\Facades\Route::getRoutes()->getByName('radiology.reports.release');
        $this->assertNotNull($radReleaseRoute);
        $this->assertFalse(str_contains(implode(',', $radReleaseRoute->gatherMiddleware()), 'super_admin'));
    }

    // ═══════════════════════════════════════════════════════════════════
    // 2. Privileged Account Protection & Anti-Escalation
    // ═══════════════════════════════════════════════════════════════════

    public function test_admin_cannot_assign_super_admin_or_admin_role(): void
    {
        $admin = $this->createUser('admin');
        $targetUser = $this->createUser('pharmacist');
        $superAdminRole = Role::where('slug', 'super_admin')->firstOrFail();
        $adminRole = Role::where('slug', 'admin')->firstOrFail();

        // Attempt to assign super_admin
        $response = $this->actingAs($admin)->post(route('admin.users.assign-role', $targetUser), [
            'role_id' => $superAdminRole->id,
        ]);
        $response->assertStatus(403);

        // Attempt to assign admin
        $response2 = $this->actingAs($admin)->post(route('admin.users.assign-role', $targetUser), [
            'role_id' => $adminRole->id,
        ]);
        $response2->assertStatus(403);

        // Attempt privilege escalation via manipulated PUT update request
        $response3 = $this->actingAs($admin)->put(route('admin.users.update', $targetUser), [
            'name'      => $targetUser->name,
            'email'     => $targetUser->email,
            'role_id'   => $superAdminRole->id,
            'is_active' => true,
        ]);
        $response3->assertStatus(403);

        // Confirm security audit log recorded
        $this->assertDatabaseHas('activity_logs', [
            'action'   => 'Privilege Escalation Blocked',
            'module'   => ActivityLog::MODULE_SECURITY,
            'severity' => ActivityLog::SEVERITY_CRITICAL,
            'result'   => ActivityLog::RESULT_BLOCKED,
        ]);

        // Super Admin can update role without anti-escalation false positive
        $superAdmin = $this->createUser('super_admin');
        $targetUser2 = $this->createUser('pharmacist');
        $responseSA = $this->actingAs($superAdmin)->put(route('admin.users.update', $targetUser2), [
            'name'      => 'Promoted By SuperAdmin',
            'email'     => $targetUser2->email,
            'role_id'   => $adminRole->id,
            'is_active' => true,
        ]);
        $responseSA->assertRedirect(route('admin.users.index'));
        $this->assertTrue($targetUser2->fresh()->hasRole('admin'));
    }

    public function test_admin_cannot_edit_update_or_delete_privileged_accounts(): void
    {
        $admin = $this->createUser('admin');
        $superAdmin = $this->createUser('super_admin');
        $anotherAdmin = $this->createUser('admin');

        // Admin attempting to edit super_admin
        $this->actingAs($admin)->get(route('admin.users.edit', $superAdmin))->assertStatus(403);

        // Admin attempting to update super_admin
        $this->actingAs($admin)->put(route('admin.users.update', $superAdmin), [
            'name'    => 'Hacked Name',
            'email'   => $superAdmin->email,
            'role_id' => $superAdmin->roles->first()->id,
        ])->assertStatus(403);

        // Admin attempting to delete super_admin
        $this->actingAs($admin)->delete(route('admin.users.destroy', $superAdmin), [
            'comment' => 'Unauthorized deletion attempt',
        ])->assertStatus(403);

        // Admin attempting to archive another admin
        $this->actingAs($admin)->delete(route('admin.users.destroy', $anotherAdmin), [
            'comment' => 'Unauthorized peer deletion attempt',
        ])->assertStatus(403);
    }

    public function test_super_admin_cannot_deactivate_or_archive_own_account(): void
    {
        $superAdmin = $this->createUser('super_admin');

        // Attempt to toggle active on self
        $response = $this->actingAs($superAdmin)->patch(route('super-admin.admins.toggle-active', $superAdmin), [
            'reason' => 'Testing self deactivation prevention',
        ]);
        $response->assertSessionHas('error');

        // Attempt to archive self
        $response = $this->actingAs($superAdmin)->delete(route('super-admin.admins.archive', $superAdmin), [
            'reason' => 'Testing self deactivation prevention',
        ]);
        $response->assertSessionHas('error');

        // Attempt to delete self via admin.users.destroy
        $this->actingAs($superAdmin)->delete(route('admin.users.destroy', $superAdmin), [
            'comment' => 'Self deletion attempt',
        ])->assertStatus(403);
    }

    public function test_audit_vault_export_and_access_controls(): void
    {
        $superAdmin = $this->createUser('super_admin');
        $admin = $this->createUser('admin');

        // Super Admin can export audit logs
        $response = $this->actingAs($superAdmin)->get(route('super-admin.audit-vault.export'));
        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));

        // Admin is forbidden from audit vault index and export
        $this->actingAs($admin)->get(route('super-admin.audit-vault.index'))->assertStatus(403);
        $this->actingAs($admin)->get(route('super-admin.audit-vault.export'))->assertStatus(403);
    }

    public function test_super_admin_can_create_and_manage_admin_account(): void
    {
        $superAdmin = $this->createUser('super_admin');
        $uniq = uniqid();

        $response = $this->from(route('super-admin.admins.index'))
            ->actingAs($superAdmin)
            ->post(route('super-admin.admins.store'), [
                'name'        => 'Hospital Administrator ' . $uniq,
                'email'       => "admin_{$uniq}@dtcs.hospital",
                'department'  => 'Hospital Administration',
                'phone'       => '+63 (2) 8123-4567',
                'reason'      => 'Hospital expansion administrator onboarding',
            ]);

        $response->assertRedirect(route('super-admin.admins.index'));
        $this->assertDatabaseHas('users', ['email' => "admin_{$uniq}@dtcs.hospital"]);

        $createdAdmin = User::where('email', "admin_{$uniq}@dtcs.hospital")->firstOrFail();
        $this->assertTrue($createdAdmin->hasRole('admin'));

        // Toggle active
        $this->from(route('super-admin.admins.index'))
            ->actingAs($superAdmin)
            ->patch(route('super-admin.admins.toggle-active', $createdAdmin), [
                'reason' => 'Temporary administrative security audit toggle',
            ])
            ->assertRedirect(route('super-admin.admins.index'));

        $this->assertFalse((bool) $createdAdmin->fresh()->is_active);

        // Unlock
        $this->from(route('super-admin.admins.index'))
            ->actingAs($superAdmin)
            ->post(route('super-admin.admins.unlock', $createdAdmin), [
                'reason' => 'Unlocking admin account after security verification',
            ])
            ->assertRedirect(route('super-admin.admins.index'));
    }

    // ═══════════════════════════════════════════════════════════════════
    // 3. Two-Tier Doctor Provisioning Workflow
    // ═══════════════════════════════════════════════════════════════════

    public function test_doctor_provisioning_request_approval_workflow(): void
    {
        $admin = $this->createUser('admin');
        $superAdmin = $this->createUser('super_admin');
        $uniq = uniqid();
        $email = "dr_jane_{$uniq}@dtcs.hospital";

        // Step 1: Admin requests doctor provisioning
        $response = $this->from(route('admin.doctors.index'))
            ->actingAs($admin)
            ->post(route('admin.doctors.request-provisioning'), [
                'name'             => 'Dr. Jane Smith ' . $uniq,
                'email'            => $email,
                'employee_id'      => 'DOC-' . rand(1000, 9999),
                'department'       => 'Cardiology',
                'phone'            => '+63 (2) 8999-1234',
                'requested_reason' => 'New cardiology consultant appointment',
            ]);

        $response->assertRedirect(route('admin.doctors.index'));

        $accessRequest = PrivilegedAccessRequest::where('request_type', 'doctor_provisioning')
            ->where('status', 'pending')
            ->where('payload->email', $email)
            ->firstOrFail();

        // Step 2: Super Admin approves doctor provisioning
        $approveResponse = $this->from(route('super-admin.doctor-provisioning.index'))
            ->actingAs($superAdmin)
            ->post(route('super-admin.doctor-provisioning.approve', $accessRequest), [
                'reason' => 'Credentials verified with PRC medical board database.',
            ]);

        $approveResponse->assertRedirect(route('super-admin.doctor-provisioning.index'));
        $this->assertEquals('approved', $accessRequest->fresh()->status);

        // Step 3: Verify created user has doctor role
        $newDoctor = User::where('email', $email)->first();
        $this->assertNotNull($newDoctor);
        $this->assertTrue($newDoctor->hasRole('doctor'));
        $this->assertEquals('Cardiology', $newDoctor->department);
    }

    public function test_doctor_provisioning_can_be_rejected_with_reason(): void
    {
        $admin = $this->createUser('admin');
        $superAdmin = $this->createUser('super_admin');
        $uniq = uniqid();
        $email = "dr_rej_{$uniq}@dtcs.hospital";

        $this->from(route('admin.doctors.index'))
            ->actingAs($admin)
            ->post(route('admin.doctors.request-provisioning'), [
                'name'             => 'Dr. Rejected ' . $uniq,
                'email'            => $email,
                'employee_id'      => 'DOC-' . rand(1000, 9999),
                'department'       => 'Pediatrics',
                'phone'            => '+63 (2) 8555-4321',
                'requested_reason' => 'Pediatric wing expansion',
            ]);

        $accessRequest = PrivilegedAccessRequest::where('request_type', 'doctor_provisioning')
            ->where('status', 'pending')
            ->where('payload->email', $email)
            ->firstOrFail();

        $response = $this->from(route('super-admin.doctor-provisioning.index'))
            ->actingAs($superAdmin)
            ->post(route('super-admin.doctor-provisioning.reject', $accessRequest), [
                'reason' => 'PRC license cannot be verified in official registry.',
            ]);

        $response->assertRedirect(route('super-admin.doctor-provisioning.index'));
        $this->assertEquals('rejected', $accessRequest->fresh()->status);
        $this->assertNull(User::where('email', $email)->first());
    }

    // ═══════════════════════════════════════════════════════════════════
    // 4. Staff Account Management by Admin
    // ═══════════════════════════════════════════════════════════════════

    public function test_admin_can_create_staff_account_with_allowed_role(): void
    {
        $admin = $this->createUser('admin');
        $uniq = uniqid();

        $response = $this->from(route('admin.staff.index'))
            ->actingAs($admin)
            ->post(route('admin.staff.store'), [
                'name'        => 'Test Pharmacist ' . $uniq,
                'email'       => "pharm_{$uniq}@dtcs.hospital",
                'employee_id' => 'STF-' . rand(1000, 9999),
                'department'  => 'Central Pharmacy',
                'role_slug'   => 'pharmacist',
                'phone'       => '+63 (2) 8333-5678',
                'reason'      => 'Hospital pharmacy expansion staffing',
            ]);

        $response->assertRedirect(route('admin.staff.index'));
        $createdStaff = User::where('email', "pharm_{$uniq}@dtcs.hospital")->first();
        $this->assertNotNull($createdStaff);
        $this->assertTrue($createdStaff->hasRole('pharmacist'));
    }

    public function test_admin_cannot_create_doctor_or_admin_via_staff_endpoint(): void
    {
        $admin = $this->createUser('admin');
        $uniq = uniqid();

        // Attempting to create doctor via staff endpoint
        $response = $this->actingAs($admin)->post(route('admin.staff.store'), [
            'name'        => 'Illegal Doctor ' . $uniq,
            'email'       => "illegal_doc_{$uniq}@dtcs.hospital",
            'employee_id' => 'STF-' . rand(1000, 9999),
            'department'  => 'Clinical',
            'role_slug'   => 'doctor',
            'reason'      => 'Testing doctor block on staff form',
        ]);
        $response->assertSessionHasErrors(['role_slug']);

        // Attempting to create super_admin via staff endpoint
        $response2 = $this->actingAs($admin)->post(route('admin.staff.store'), [
            'name'        => 'Illegal Super ' . $uniq,
            'email'       => "illegal_super_{$uniq}@dtcs.hospital",
            'employee_id' => 'STF-' . rand(1000, 9999),
            'department'  => 'Administration',
            'role_slug'   => 'super_admin',
            'reason'      => 'Testing super_admin block on staff form',
        ]);
        $response2->assertSessionHasErrors(['role_slug']);
    }

    // ═══════════════════════════════════════════════════════════════════
    // 5. Settings Governance & Scoping
    // ═══════════════════════════════════════════════════════════════════

    public function test_super_admin_can_update_system_settings_with_audit_trail(): void
    {
        $superAdmin = $this->createUser('super_admin');

        $response = $this->from(route('super-admin.settings.index'))
            ->actingAs($superAdmin)
            ->post(route('super-admin.settings.update'), [
                'security_password_min_length'          => 12,
                'security_password_require_mixed_case'  => 1,
                'security_password_require_numbers'     => 1,
                'security_password_require_symbols'     => 1,
                'security_session_inactivity_minutes'   => 25,
                'security_otp_expires_minutes'          => 10,
                'maintenance_enabled'                   => 0,
                'maintenance_message'                   => 'System maintenance scheduled.',
                'backup_enabled'                        => 1,
                'backup_time'                           => '03:30',
                'backup_retention_days'                 => 45,
                'reason'                                => 'Governance compliance audit 2026 update',
            ]);

        $response->assertRedirect(route('super-admin.settings.index'));

        /** @var SettingsService $settings */
        $settings = app(SettingsService::class);
        $this->assertEquals(12, $settings->get('security.password_min_length'));
        $this->assertEquals(25, $settings->get('security.session_inactivity_minutes'));
        $this->assertEquals('03:30', $settings->get('backup.time'));

        $this->assertDatabaseHas('setting_histories', [
            'setting_key' => 'security.password_min_length',
            'reason'      => 'Governance compliance audit 2026 update',
        ]);
    }

    public function test_admin_can_update_hospital_branding_and_queue_settings(): void
    {
        $admin = $this->createUser('admin');

        // 1. Branding
        $response = $this->from(route('admin.hospital-settings.index'))
            ->actingAs($admin)
            ->post(route('admin.hospital-settings.branding'), [
                'hospital_name'            => 'DTCS Provincial Medical Center',
                'hospital_tagline'         => 'Leading Healthcare Excellence',
                'hospital_address'         => '100 Medical Plaza, Metro Manila',
                'hospital_phone'           => '+63 (2) 8888-1234',
                'hospital_email'           => 'contact@dtcs-medical.gov',
                'hospital_registration_no' => 'DOH-HOSP-2026-001',
                'reason'                   => 'Annual hospital rebranding and accreditation updates',
            ]);
        $response->assertRedirect(route('admin.hospital-settings.index'));

        // 2. Queue Settings
        $response2 = $this->from(route('admin.hospital-settings.index'))
            ->actingAs($admin)
            ->post(route('admin.hospital-settings.queue'), [
                'queue_prefix_diagnostic' => 'DIAG-',
                'queue_prefix_treatment'  => 'TRT-',
                'queue_delay_threshold'   => 45,
                'reason'                  => 'Operational queue TAT threshold adjustment',
            ]);
        $response2->assertRedirect(route('admin.hospital-settings.index'));

        /** @var SettingsService $settings */
        $settings = app(SettingsService::class);
        $this->assertEquals('DTCS Provincial Medical Center', $settings->get('hospital.name'));
        $this->assertEquals('DIAG-', $settings->get('queue.prefix_diagnostic'));
        $this->assertEquals(45, $settings->get('ops.queue_delay_threshold_minutes'));
    }

    public function test_settings_service_rejects_scope_violations(): void
    {
        /** @var SettingsService $settings */
        $settings = app(SettingsService::class);
        $admin = $this->createUser('admin');

        $this->expectException(AuthorizationException::class);
        $settings->set(
            'security.password_min_length',
            16,
            SettingsRegistry::SCOPE_ADMIN, // Admin attempting to write a super_admin key
            $admin->id,
            'Scope violation attempt',
            '127.0.0.1'
        );
    }

    // ═══════════════════════════════════════════════════════════════════
    // 6. Append-Only Immutability Guards
    // ═══════════════════════════════════════════════════════════════════

    public function test_activity_log_is_strictly_append_only(): void
    {
        $admin = $this->createUser('admin');

        $log = ActivityLog::create([
            'user_id'     => $admin->id,
            'user_role'   => 'admin',
            'action'      => 'test_action',
            'module'      => ActivityLog::MODULE_SECURITY,
            'description' => 'Test immutable audit entry',
            'ip_address'  => '127.0.0.1',
        ]);

        $this->expectException(\RuntimeException::class);
        $log->update(['description' => 'Tampered description']);
    }

    public function test_financial_rule_is_strictly_immutable(): void
    {
        $admin = $this->createUser('admin');

        $rule = FinancialRule::create([
            'currency_code'       => 'PHP',
            'currency_symbol'     => '₱',
            'tax_name'            => 'VAT',
            'tax_rate'            => 0.1200,
            'service_charge_rate' => 0.0000,
            'effective_from'      => now()->toDateString(),
            'created_by'          => $admin->id,
        ]);

        $this->expectException(\RuntimeException::class);
        $rule->update(['tax_rate' => 0.1500]);
    }

    public function test_admin_can_enact_new_financial_rule(): void
    {
        $admin = $this->createUser('admin');

        $response = $this->from(route('admin.hospital-settings.index'))
            ->actingAs($admin)
            ->post(route('admin.hospital-settings.financial-rules'), [
                'currency_code'       => 'USD',
                'currency_symbol'     => '$',
                'tax_name'            => 'Hospital Surcharge',
                'tax_rate'            => '0.0800',
                'service_charge_rate' => '0.0200',
                'effective_from'      => '2026-12-01',
                'notes'               => 'Q4 Financial Adjustment',
                'reason'              => 'Board of Directors Resolution 2026-B',
            ]);

        $response->assertRedirect(route('admin.hospital-settings.index'));
        $this->assertDatabaseHas('financial_rules', [
            'currency_code'  => 'USD',
            'tax_rate'       => 0.0800,
            'effective_from' => '2026-12-01',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // 7. Clinical Codes Staging, License Verification, and Publish
    // ═══════════════════════════════════════════════════════════════════

    public function test_clinical_code_staging_and_license_gate(): void
    {
        $superAdmin = $this->createUser('super_admin');

        $system = ClinicalCodeSystem::firstOrCreate(
            ['system_key' => 'icd-10-cm-test'],
            [
                'name'                => 'ICD-10-CM Test',
                'kind'                => 'diagnosis',
                'license_status'      => 'unverified',
                'license_verified_at' => null,
            ]
        );

        $csvContent = "code,display,category,is_active\n"
                    . "T01.0,Test Fracture of Skull,Trauma,1\n"
                    . "T01.1,Test Open Wound,Trauma,1\n";

        $file = UploadedFile::fake()->createWithContent('icd10_sample.csv', $csvContent);

        // Stage version
        $response = $this->from(route('super-admin.clinical-codes.index'))
            ->actingAs($superAdmin)
            ->post(route('super-admin.clinical-codes.stage', $system), [
                'version_label'  => '2026.1-test',
                'effective_date' => '2026-01-01',
                'dataset_file'   => $file,
            ]);

        $response->assertRedirect(route('super-admin.clinical-codes.index'));

        $version = ClinicalCodeVersion::where('system_id', $system->id)
            ->where('version_label', '2026.1-test')
            ->firstOrFail();

        $this->assertEquals('staged', $version->status);
        $this->assertEquals(2, $version->valid_rows);

        // Attempt publish WITHOUT license verification -> should fail
        $pubResponse = $this->from(route('super-admin.clinical-codes.index'))
            ->actingAs($superAdmin)
            ->post(route('super-admin.clinical-codes.publish', $version));

        $pubResponse->assertRedirect(route('super-admin.clinical-codes.index'));
        $pubResponse->assertSessionHas('error');
        $this->assertEquals('staged', $version->fresh()->status);

        // Now verify license
        $this->actingAs($superAdmin)->patch(route('super-admin.clinical-codes.verify-license', $system), [
            'license_status'    => 'verified',
            'license_reference' => 'GOV-LICENSE-VERIFIED-2026',
            'jurisdiction'      => 'Philippines',
        ])->assertRedirect();

        $this->assertEquals('verified', $system->fresh()->license_status);

        // Now publish should succeed
        $pubResponse2 = $this->from(route('super-admin.clinical-codes.index'))
            ->actingAs($superAdmin)
            ->post(route('super-admin.clinical-codes.publish', $version));

        $pubResponse2->assertRedirect(route('super-admin.clinical-codes.index'));
        $this->assertEquals('published', $version->fresh()->status);
        $this->assertEquals($version->id, $system->fresh()->active_version_id);
    }
}
