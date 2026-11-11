<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\LabRequest;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Prescription;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SuperAdminUserManagementTest extends TestCase
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
            ['name' => 'Patient Portal User',     'slug' => 'patient',        'dashboard_route' => 'patient.dashboard'],
        ];

        foreach ($roles as $r) {
            Role::firstOrCreate(['slug' => $r['slug']], $r);
        }
    }

    private function createUser(string $roleSlug, array $attributes = []): User
    {
        $uniq = uniqid();
        $user = User::create(array_merge([
            'name'              => 'Test ' . ucfirst($roleSlug),
            'email'             => "{$roleSlug}_{$uniq}@dtcs.hospital",
            'password'          => bcrypt('P@ssword123456!'),
            'employee_id'       => 'EMP-' . rand(10000, 99999),
            'department'        => 'Administration',
            'is_active'         => true,
            'email_verified_at' => now(),
        ], $attributes));

        $role = Role::where('slug', $roleSlug)->firstOrFail();
        $user->roles()->sync([$role->id]);

        return $user;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 1. ACCESS & VISIBILITY TESTS
    // ─────────────────────────────────────────────────────────────────────────

    public function test_super_admin_can_view_all_user_accounts_index(): void
    {
        $superAdmin = $this->createUser('super_admin');
        $doctor     = $this->createUser('doctor');
        $patientUser= $this->createUser('patient');

        $response = $this->actingAs($superAdmin)->get(route('super-admin.users.index'));

        $response->assertStatus(200);
        $response->assertSee('User Account Governance');

        $searchResponse = $this->actingAs($superAdmin)->get(route('super-admin.users.index', ['search' => $doctor->email]));
        $searchResponse->assertStatus(200);
        $searchResponse->assertSee($doctor->email);
    }

    public function test_admin_cannot_access_super_admin_user_management(): void
    {
        $admin = $this->createUser('admin');

        $response = $this->actingAs($admin)->get(route('super-admin.users.index'));
        $response->assertStatus(403);

        $postResponse = $this->actingAs($admin)->post(route('super-admin.users.store'), [
            'name' => 'Hacker Admin',
            'email' => 'hacker@dtcs.hospital',
            'role_slug' => 'super_admin',
            'password_mode' => 'manual',
            'password' => 'H@cked123456',
            'reason' => 'Unauthorized escalation attempt',
        ]);
        $postResponse->assertStatus(403);
    }

    public function test_super_admin_can_filter_users_by_role_and_status(): void
    {
        $superAdmin = $this->createUser('super_admin');
        $doctor     = $this->createUser('doctor', ['name' => 'Dr. Specialist']);
        $medTech    = $this->createUser('med-tech', ['name' => 'Lab Tech John']);

        $roleResponse = $this->actingAs($superAdmin)->get(route('super-admin.users.index', ['role' => 'doctor']));
        $roleResponse->assertStatus(200);
        $roleResponse->assertSee('Dr. Specialist');
        $roleResponse->assertDontSee('Lab Tech John');

        // Status filter
        $inactive = $this->createUser('admin', ['name' => 'Inactive User', 'is_active' => false]);
        $statusResponse = $this->actingAs($superAdmin)->get(route('super-admin.users.index', ['status' => 'inactive']));
        $statusResponse->assertStatus(200);
        $statusResponse->assertSee('Inactive User');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. ACCOUNT CREATION TESTS (EVERY SUPPORTED ROLE)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_super_admin_can_create_user_with_any_supported_role(): void
    {
        $superAdmin = $this->createUser('super_admin');

        $rolesToTest = [
            'super_admin'    => 'New Super Admin',
            'admin'          => 'New System Admin',
            'doctor'         => 'Dr. New Clinician',
            'med-tech'       => 'New Lab Technologist',
            'radiologist'    => 'Dr. New Radiologist',
            'pharmacist'     => 'New Pharmacist',
            'dietitian'      => 'New Dietitian',
            'or-coordinator' => 'New OR Coordinator',
        ];

        foreach ($rolesToTest as $roleSlug => $name) {
            $email = "test_{$roleSlug}_" . uniqid() . "@dtcs.hospital";

            $response = $this->actingAs($superAdmin)->post(route('super-admin.users.store'), [
                'role_slug'     => $roleSlug,
                'name'          => $name,
                'email'         => $email,
                'employee_id'   => 'EMP-' . rand(10000, 99999),
                'department'    => 'Clinical Unit',
                'phone'         => '555-1234',
                'password_mode' => 'manual',
                'password'      => 'SecureP@ssword2026',
                'reason'        => "Provisioning {$name} for hospital duty",
            ]);

            $response->assertRedirect(route('super-admin.users.index'));
            $response->assertSessionHas('success');

            $created = User::where('email', $email)->first();
            $this->assertNotNull($created, "User for role {$roleSlug} was not created");
            $this->assertTrue($created->hasRole($roleSlug), "User does not have role {$roleSlug}");
            $this->assertTrue($created->is_active);

            // Audit record verification
            $log = ActivityLog::where('action', 'User Account Created')
                ->where('description', 'LIKE', "%[{$email}]%")
                ->first();
            $this->assertNotNull($log, "Audit record for {$email} was not found");
        }
    }

    public function test_super_admin_can_create_patient_portal_user_with_patient_chart(): void
    {
        $superAdmin = $this->createUser('super_admin');
        $email = 'patient_' . uniqid() . '@dtcs.hospital';

        $response = $this->actingAs($superAdmin)->post(route('super-admin.users.store'), [
            'role_slug'         => 'patient',
            'name'              => 'Alice Patient',
            'email'             => $email,
            'phone'             => '555-9876',
            'password_mode'     => 'invite',
            'patient_link_mode' => 'new',
            'patient_dob'       => '1992-05-15',
            'patient_gender'    => 'female',
            'reason'            => 'Registering outpatient portal access',
        ]);

        $response->assertRedirect(route('super-admin.users.index'));

        $user = User::where('email', $email)->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('patient'));
        $this->assertNotNull($user->patient_id);

        $patient = Patient::find($user->patient_id);
        $this->assertNotNull($patient);
        $this->assertEquals($user->id, $patient->user_id);
        $this->assertEquals('Alice', $patient->first_name);
    }

    public function test_super_admin_cannot_create_user_with_duplicate_email(): void
    {
        $superAdmin = $this->createUser('super_admin');
        $existing = $this->createUser('doctor');

        $response = $this->actingAs($superAdmin)->post(route('super-admin.users.store'), [
            'role_slug'     => 'doctor',
            'name'          => 'Duplicate Doctor',
            'email'         => $existing->email,
            'password_mode' => 'invite',
            'reason'        => 'Should fail duplicate validation',
        ]);

        $response->assertSessionHasErrors('email');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. EDIT & ROLE SWITCHING TESTS
    // ─────────────────────────────────────────────────────────────────────────

    public function test_super_admin_can_edit_user_and_change_role(): void
    {
        $superAdmin = $this->createUser('super_admin');
        $staffUser  = $this->createUser('med-tech', ['department' => 'Laboratory']);

        $response = $this->actingAs($superAdmin)->put(route('super-admin.users.update', $staffUser), [
            'name'        => 'Promoted Clinician',
            'email'       => $staffUser->email,
            'role_slug'   => 'doctor',
            'department'  => 'Internal Medicine',
            'employee_id' => $staffUser->employee_id,
            'phone'       => '555-7777',
            'is_active'   => 1,
            'reason'      => 'Promotion to Doctor after residency completion',
        ]);

        $response->assertRedirect(route('super-admin.users.index'));

        $staffUser->refresh();
        $this->assertEquals('Promoted Clinician', $staffUser->name);
        $this->assertTrue($staffUser->hasRole('doctor'));
        $this->assertFalse($staffUser->hasRole('med-tech'));
        $this->assertEquals('Internal Medicine', $staffUser->department);
    }

    public function test_super_admin_can_assign_super_admin_role_to_another_account(): void
    {
        $superAdmin = $this->createUser('super_admin');
        $adminUser  = $this->createUser('admin');

        $response = $this->actingAs($superAdmin)->put(route('super-admin.users.update', $adminUser), [
            'name'        => $adminUser->name,
            'email'       => $adminUser->email,
            'role_slug'   => 'super_admin',
            'department'  => 'Master System Governance',
            'employee_id' => $adminUser->employee_id,
            'is_active'   => 1,
            'reason'      => 'Promoted to Super Administrator for redundancy',
        ]);

        $response->assertRedirect(route('super-admin.users.index'));

        $adminUser->refresh();
        $this->assertTrue($adminUser->hasRole('super_admin'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 4. ACCOUNT LIFECYCLE, STATUS, UNLOCK & SESSION TESTS
    // ─────────────────────────────────────────────────────────────────────────

    public function test_super_admin_can_suspend_and_activate_user_account(): void
    {
        $superAdmin = $this->createUser('super_admin');
        $doctor     = $this->createUser('doctor');

        // Suspend
        $response = $this->actingAs($superAdmin)->patch(route('super-admin.users.toggle-status', $doctor), [
            'reason' => 'Temporary suspension pending clinical license verification',
        ]);
        $response->assertSessionHas('success');
        $doctor->refresh();
        $this->assertFalse($doctor->is_active);

        // Reactivate
        $reactivateResponse = $this->actingAs($superAdmin)->patch(route('super-admin.users.toggle-status', $doctor), [
            'reason' => 'License verified successfully; reactivating account',
        ]);
        $reactivateResponse->assertSessionHas('success');
        $doctor->refresh();
        $this->assertTrue($doctor->is_active);
    }

    public function test_deactivating_account_terminates_active_session(): void
    {
        $superAdmin = $this->createUser('super_admin');
        $doctor     = $this->createUser('doctor');

        // Simulate active session
        $doctor->setActiveSession('test_session_token_123');
        $this->assertTrue($doctor->hasActiveSession('other_browser_session'));

        // Suspend account
        $this->actingAs($superAdmin)->patch(route('super-admin.users.toggle-status', $doctor), [
            'reason' => 'Suspending account and revoking active sessions',
        ]);

        $doctor->refresh();
        $this->assertFalse($doctor->is_active);
        $this->assertNull($doctor->active_session_id);
    }

    public function test_super_admin_can_terminate_active_session_directly(): void
    {
        $superAdmin = $this->createUser('super_admin');
        $user       = $this->createUser('admin');

        $user->setActiveSession('sess_active_admin_token');
        $this->assertNotNull($user->active_session_id);

        $response = $this->actingAs($superAdmin)->post(route('super-admin.users.terminate-session', $user), [
            'reason' => 'Administrative session revocation',
        ]);

        $response->assertSessionHas('success');
        $user->refresh();
        $this->assertNull($user->active_session_id);
    }

    public function test_super_admin_can_unlock_locked_account(): void
    {
        $superAdmin = $this->createUser('super_admin');
        $lockedUser = $this->createUser('doctor', [
            'failed_attempts' => 5,
            'locked_at'       => now()->subMinutes(10),
            'is_active'       => false,
        ]);

        $this->assertTrue($lockedUser->isLockedOut());

        $response = $this->actingAs($superAdmin)->post(route('super-admin.users.unlock', $lockedUser), [
            'reason' => 'Identity verified with clinician; resetting lockout',
        ]);

        $response->assertSessionHas('success');
        $lockedUser->refresh();
        $this->assertFalse($lockedUser->isLockedOut());
        $this->assertEquals(0, $lockedUser->failed_attempts);
        $this->assertTrue($lockedUser->is_active);
    }

    public function test_super_admin_can_dispatch_password_reset(): void
    {
        $superAdmin = $this->createUser('super_admin');
        $user       = $this->createUser('doctor');

        $response = $this->actingAs($superAdmin)->post(route('super-admin.users.reset-password', $user), [
            'reason' => 'Password reset requested via IT service desk',
        ]);

        $response->assertSessionHas('success');

        $log = ActivityLog::where('action', 'Password Reset Dispatched')
            ->where('description', 'LIKE', "%[{$user->email}]%")
            ->first();
        $this->assertNotNull($log);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 5. SAFETY & CLINICAL RECORD DELETION GUARDS
    // ─────────────────────────────────────────────────────────────────────────

    public function test_permanent_deletion_blocked_when_user_has_clinical_records(): void
    {
        $superAdmin = $this->createUser('super_admin');
        $doctor     = $this->createUser('doctor');

        // Create clinical patient and prescription tied to this doctor
        $patient = Patient::create([
            'patient_no'    => 'PAT-TEST-' . rand(1000, 9999),
            'first_name'    => 'Clinical',
            'last_name'     => 'Subject',
            'date_of_birth' => '1985-01-01',
            'gender'        => 'male',
        ]);

        Prescription::create([
            'prescription_no' => 'RX-' . rand(10000, 99999),
            'patient_id'      => $patient->id,
            'doctor_id'       => $doctor->id,
            'diagnosis'       => 'Hypertension Stage 1',
            'status'          => 'Pending',
        ]);

        $this->assertTrue($doctor->hasLinkedClinicalRecords());

        // Archive first
        $doctor->delete();

        // Attempt permanent forceDelete
        $response = $this->actingAs($superAdmin)->delete(route('super-admin.users.force-delete', $doctor->id), [
            'reason' => 'Attempting permanent delete on doctor with clinical charts',
        ]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('tied to clinical records', session('error'));

        // Verify account was NOT deleted from DB
        $this->assertDatabaseHas('users', ['id' => $doctor->id]);
    }

    public function test_safe_account_without_clinical_records_can_be_permanently_deleted(): void
    {
        $superAdmin = $this->createUser('super_admin');
        $tempUser   = $this->createUser('med-tech');

        $this->assertFalse($tempUser->hasLinkedClinicalRecords());

        // Archive first
        $tempUser->delete();

        // Attempt permanent forceDelete
        $response = $this->actingAs($superAdmin)->delete(route('super-admin.users.force-delete', $tempUser->id), [
            'reason' => 'Purging temporary test account without clinical records',
        ]);

        $response->assertRedirect(route('super-admin.users.index'));
        $response->assertSessionHas('success');

        // Verify account was permanently erased
        $this->assertDatabaseMissing('users', ['id' => $tempUser->id]);
    }

    public function test_last_active_super_admin_cannot_be_deactivated_demoted_or_deleted(): void
    {
        // Ensure there is only 1 active super admin
        $allSuperAdmins = User::whereHas('roles', fn($q) => $q->where('slug', 'super_admin'))->get();
        foreach ($allSuperAdmins as $sa) {
            $sa->roles()->detach();
        }

        $soleSuperAdmin = $this->createUser('super_admin');
        $this->assertEquals(1, User::countActiveSuperAdmins());

        // 1. Attempt to deactivate
        $deactivateResponse = $this->actingAs($soleSuperAdmin)->patch(route('super-admin.users.toggle-status', $soleSuperAdmin), [
            'reason' => 'Accidental self deactivation attempt',
        ]);
        $deactivateResponse->assertSessionHas('error');
        $this->assertTrue($soleSuperAdmin->fresh()->is_active);

        // 2. Attempt to demote role
        $demoteResponse = $this->actingAs($soleSuperAdmin)->put(route('super-admin.users.update', $soleSuperAdmin), [
            'name'       => $soleSuperAdmin->name,
            'email'      => $soleSuperAdmin->email,
            'role_slug'  => 'admin',
            'reason'     => 'Demoting sole super admin',
        ]);
        $demoteResponse->assertSessionHasErrors();
        $this->assertTrue($soleSuperAdmin->fresh()->isSuperAdmin());

        // 3. Attempt to archive
        $archiveResponse = $this->actingAs($soleSuperAdmin)->delete(route('super-admin.users.destroy', $soleSuperAdmin), [
            'reason' => 'Archiving sole super admin',
        ]);
        $archiveResponse->assertSessionHas('error');
        $this->assertNull($soleSuperAdmin->fresh()->deleted_at);
    }

    public function test_super_admin_cannot_deactivate_or_archive_self(): void
    {
        $superAdmin1 = $this->createUser('super_admin');
        $superAdmin2 = $this->createUser('super_admin'); // ensures > 1 active

        $response = $this->actingAs($superAdmin1)->delete(route('super-admin.users.destroy', $superAdmin1), [
            'reason' => 'Self deletion attempt',
        ]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('cannot archive your own account', session('error'));
        $this->assertNull($superAdmin1->fresh()->deleted_at);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 6. ROLE PERMISSION GOVERNANCE
    // ─────────────────────────────────────────────────────────────────────────

    public function test_super_admin_can_update_role_permissions_but_admin_cannot(): void
    {
        $superAdmin = $this->createUser('super_admin');
        $admin      = $this->createUser('admin');
        $doctorRole = Role::where('slug', 'doctor')->firstOrFail();

        $perm = Permission::firstOrCreate([
            'slug'   => 'test.permission.' . uniqid(),
        ], [
            'name'   => 'Test Permission Capability',
            'module' => 'Clinical Care',
        ]);

        // Super Admin update: success
        $saResponse = $this->actingAs($superAdmin)->put(route('admin.roles.update', $doctorRole), [
            'permissions' => [$perm->id],
            'reason'      => 'Super Admin assigned new clinical capability',
        ]);
        $saResponse->assertSessionHas('success');
        $this->assertTrue($doctorRole->fresh()->permissions()->where('permissions.id', $perm->id)->exists());

        // Ordinary Admin update: 403 Forbidden
        $adminResponse = $this->actingAs($admin)->put(route('admin.roles.update', $doctorRole), [
            'permissions' => [],
            'reason'      => 'Unauthorized role permission alteration',
        ]);
        $adminResponse->assertStatus(403);
    }
}
