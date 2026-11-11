<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PanelistFiveUserTypesTest extends TestCase
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

    private function createUser(string $roleSlug, ?string $department = null): User
    {
        $uniq = uniqid();
        $user = User::create([
            'name'        => 'Test ' . ucfirst($roleSlug),
            'email'       => "{$roleSlug}_{$uniq}@dtcs.hospital",
            'password'    => bcrypt('Secr3t!P@ssword2026'),
            'employee_id' => 'EMP-' . rand(10000, 99999),
            'department'  => $department ?? 'Hospital Staff',
            'is_active'   => true,
        ]);

        $role = Role::where('slug', $roleSlug)->firstOrFail();
        $user->roles()->attach($role->id);

        return $user;
    }

    private function createPatientWithUser(): array
    {
        $uniq = uniqid();
        $patient = Patient::create([
            'patient_no' => 'PAT-' . rand(10000, 99999),
            'first_name' => 'Jane',
            'last_name'  => 'Doe',
            'date_of_birth' => '1990-05-15',
            'gender'     => 'Female',
            'phone'      => '09123456789',
            'email'      => "patient_{$uniq}@patient.dtcs",
            'address'    => '123 Medical Center Way',
            'status'     => 'Active',
        ]);

        $user = User::create([
            'name'        => 'Jane Doe',
            'email'       => "patient_{$uniq}@patient.dtcs",
            'password'    => bcrypt('Secr3t!P@ssword2026'),
            'patient_id'  => $patient->id,
            'department'  => 'Patient Care',
            'is_active'   => true,
        ]);

        $patientRole = Role::where('slug', 'patient')->firstOrFail();
        $user->roles()->attach($patientRole->id);

        $patient->update(['user_id' => $user->id]);

        return [$user, $patient];
    }

    // ─────────────────────────────────────────────────────────────────
    // 1. Super Admin Tests
    // ─────────────────────────────────────────────────────────────────

    public function test_super_admin_can_access_super_admin_modules(): void
    {
        $superAdmin = $this->createUser('super_admin');

        $this->actingAs($superAdmin)->get(route('super-admin.dashboard'))->assertStatus(200);
        $this->actingAs($superAdmin)->get(route('super-admin.departments.index'))->assertStatus(200);
        $this->actingAs($superAdmin)->get(route('super-admin.catalog.index'))->assertStatus(200);
        $this->actingAs($superAdmin)->get(route('super-admin.audit-vault.index'))->assertStatus(200);
        $this->actingAs($superAdmin)->get(route('super-admin.settings.index'))->assertStatus(200);
    }

    public function test_super_admin_cannot_access_clinical_records_or_medisense(): void
    {
        $superAdmin = $this->createUser('super_admin');

        // Clinical separation: Super Admin cannot diagnose or access patient directory directly
        $this->actingAs($superAdmin)->get(route('medisense.index'))->assertStatus(403);
        $this->actingAs($superAdmin)->get(route('patients.index'))->assertStatus(403);
    }

    // ─────────────────────────────────────────────────────────────────
    // 2. Admin Tests
    // ─────────────────────────────────────────────────────────────────

    public function test_admin_can_access_operations_and_intake(): void
    {
        $admin = $this->createUser('admin');

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertStatus(200);
        $this->actingAs($admin)->get(route('admin.appointments.index'))->assertStatus(200);
        $this->actingAs($admin)->get(route('admin.patients.intake'))->assertStatus(200);
        $this->actingAs($admin)->get(route('admin.doctors.index'))->assertStatus(200);
        $this->actingAs($admin)->get(route('admin.staff.index'))->assertStatus(200);
    }

    public function test_admin_cannot_access_super_admin_vault_or_departments(): void
    {
        $admin = $this->createUser('admin');

        $this->actingAs($admin)->get(route('super-admin.dashboard'))->assertStatus(403);
        $this->actingAs($admin)->get(route('super-admin.departments.index'))->assertStatus(403);
        $this->actingAs($admin)->get(route('super-admin.audit-vault.index'))->assertStatus(403);
    }

    // ─────────────────────────────────────────────────────────────────
    // 3. Doctor Tests
    // ─────────────────────────────────────────────────────────────────

    public function test_doctor_can_access_clinical_dashboard_and_medisense(): void
    {
        $doctor = $this->createUser('doctor', 'Internal Medicine');

        $this->actingAs($doctor)->get(route('doctor.dashboard'))->assertStatus(200);
        $this->actingAs($doctor)->get(route('medisense.index'))->assertStatus(200);
        $this->actingAs($doctor)->get(route('patients.index'))->assertStatus(200);
        $this->actingAs($doctor)->get(route('admin.appointments.index'))->assertStatus(200);
    }

    public function test_doctor_cannot_access_super_admin_or_admin_settings(): void
    {
        $doctor = $this->createUser('doctor', 'Internal Medicine');

        $this->actingAs($doctor)->get(route('super-admin.dashboard'))->assertStatus(403);
        $this->actingAs($doctor)->get(route('admin.audit-logs.index'))->assertStatus(403);
        $this->actingAs($doctor)->get(route('admin.hospital-settings.index'))->assertStatus(403);
    }

    // ─────────────────────────────────────────────────────────────────
    // 4. Staff Department Isolation Tests
    // ─────────────────────────────────────────────────────────────────

    public function test_staff_department_isolation_between_lab_radiology_and_pharmacy(): void
    {
        $medTech = $this->createUser('med-tech', 'Laboratory');
        $radTech = $this->createUser('rad-tech', 'Radiology');
        $pharmacist = $this->createUser('pharmacist', 'Pharmacy');

        // MedTech accesses Lab, but blocked from Pharmacy & Surgery
        $this->actingAs($medTech)->get(route('lab.dashboard'))->assertStatus(200);
        $this->actingAs($medTech)->get(route('pharmacy.dashboard'))->assertStatus(403);
        $this->actingAs($medTech)->get(route('surgery.dashboard'))->assertStatus(403);

        // RadTech accesses Radiology, but blocked from Lab & Pharmacy
        $this->actingAs($radTech)->get(route('radiology.dashboard'))->assertStatus(200);
        $this->actingAs($radTech)->get(route('lab.dashboard'))->assertStatus(403);
        $this->actingAs($radTech)->get(route('pharmacy.dashboard'))->assertStatus(403);

        // Pharmacist accesses Pharmacy, but blocked from Radiology & Surgery
        $this->actingAs($pharmacist)->get(route('pharmacy.dashboard'))->assertStatus(200);
        $this->actingAs($pharmacist)->get(route('radiology.dashboard'))->assertStatus(403);
        $this->actingAs($pharmacist)->get(route('surgery.dashboard'))->assertStatus(403);
    }

    // ─────────────────────────────────────────────────────────────────
    // 5. Patient Portal Tests
    // ─────────────────────────────────────────────────────────────────

    public function test_patient_can_access_patient_portal_modules(): void
    {
        [$patientUser, $patient] = $this->createPatientWithUser();

        $this->actingAs($patientUser)->get(route('patient.dashboard'))->assertStatus(200);
        $this->actingAs($patientUser)->get(route('patient.profile'))->assertStatus(200);
        $this->actingAs($patientUser)->get(route('patient.appointments'))->assertStatus(200);
        $this->actingAs($patientUser)->get(route('patient.results'))->assertStatus(200);
        $this->actingAs($patientUser)->get(route('patient.prescriptions'))->assertStatus(200);
        $this->actingAs($patientUser)->get(route('patient.treatments'))->assertStatus(200);
        $this->actingAs($patientUser)->get(route('patient.billing'))->assertStatus(200);
    }

    public function test_patient_cannot_access_staff_or_admin_dashboards(): void
    {
        [$patientUser, $patient] = $this->createPatientWithUser();

        $this->actingAs($patientUser)->get(route('admin.dashboard'))->assertStatus(403);
        $this->actingAs($patientUser)->get(route('doctor.dashboard'))->assertStatus(403);
        $this->actingAs($patientUser)->get(route('lab.dashboard'))->assertStatus(403);
        $this->actingAs($patientUser)->get(route('super-admin.dashboard'))->assertStatus(403);
    }
}
