<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PatientAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    public function setUp(): void
    {
        parent::setUp();
        $this->ensureRolesExist();
    }

    public function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * Helper to create a user attached to a specific role by slug.
     */
    protected function createUserWithRole(string $roleSlug): User
    {
        $uniq = uniqid();
        $user = User::create([
            'name'        => 'Test User ' . ucfirst($roleSlug),
            'email'       => "{$roleSlug}_{$uniq}@hospital.test",
            'password'    => 'password',
            'employee_id' => 'EMP-' . rand(1000, 99999),
            'department'  => 'Clinical',
        ]);
        $role = Role::where('slug', $roleSlug)->firstOrFail();
        $user->roles()->attach($role->id);

        return $user;
    }

    /**
     * Helper to create a dummy patient.
     */
    protected function createPatient(): Patient
    {
        $uniq = rand(10000, 99999);
        return Patient::create([
            'patient_no'    => 'P-2026-' . $uniq,
            'first_name'    => 'John',
            'last_name'     => 'Doe',
            'date_of_birth' => '1990-01-01',
            'gender'        => 'Male',
            'patient_type'  => 'Outpatient',
        ]);
    }

    /**
     * Non-authorized roles list.
     */
    protected function unauthorizedRoles(): array
    {
        return [
            'med-tech',
            'rad-tech',
            'radiologist',
            'pharmacist',
            'dietitian',
            'or-coordinator',
        ];
    }

    // ──────────────────────────── Admin Tests ────────────────────────────

    public function test_admin_can_access_patient_information(): void
    {
        $admin = $this->createUserWithRole('admin');
        $patient = $this->createPatient();

        $this->actingAs($admin)->get(route('patients.index'))->assertStatus(200);
        $this->actingAs($admin)->get(route('patients.show', $patient))->assertStatus(200);
        $this->actingAs($admin)->delete(route('patients.destroy', $patient))->assertRedirect(route('patients.index'));
    }

    // ──────────────────────────── Doctor Tests ────────────────────────────

    public function test_doctor_can_access_patient_information(): void
    {
        $doctor = $this->createUserWithRole('doctor');
        $patient = $this->createPatient();

        $this->actingAs($doctor)->get(route('patients.index'))->assertStatus(200);
        $this->actingAs($doctor)->get(route('patients.show', $patient))->assertStatus(200);
        $this->actingAs($doctor)->delete(route('patients.destroy', $patient))->assertRedirect(route('patients.index'));
    }

    public function test_patient_profile_renders_redesigned_header_info_grid_and_all_five_clinical_module_tabs(): void
    {
        $doctorA = $this->createUserWithRole('doctor');
        $doctorB = $this->createUserWithRole('doctor');

        $patient = Patient::create([
            'patient_no'    => 'PAT-REDESIGN-' . rand(10000, 99999),
            'first_name'    => 'Eleanor',
            'middle_name'   => 'Marie',
            'last_name'     => 'Vance',
            'date_of_birth' => '1982-06-15',
            'gender'        => 'Female',
            'blood_type'    => 'O+',
            'phone'         => '09171234567',
            'email'         => 'eleanor.vance@example.test',
            'patient_type'  => 'Inpatient',
            'ward'          => 'Ward 3B',
            'bed_number'    => 'Bed 12',
            'address'       => '145 Emerald Ave, Pasig City',
        ]);

        $restrictedPatient = Patient::create([
            'patient_no'    => 'PAT-RESTRICT-' . rand(10000, 99999),
            'first_name'    => 'Marcus',
            'last_name'     => 'Sterling',
            'date_of_birth' => '1975-02-19',
            'gender'        => 'Male',
            'patient_type'  => 'Outpatient',
        ]);

        // Seed 1 record in each of the 5 clinical modules for $patient under $doctorA
        $lab = \App\Models\LabRequest::create([
            'request_no'   => 'LAB-PP-' . uniqid(),
            'patient_id'   => $patient->id,
            'doctor_id'    => $doctorA->id,
            'priority'     => 'Urgent',
            'status'       => 'Pending',
            'requested_at' => now(),
        ]);

        $rad = \App\Models\RadiologyRequest::create([
            'request_no'           => 'RAD-PP-' . uniqid(),
            'patient_id'           => $patient->id,
            'doctor_id'            => $doctorA->id,
            'modality'             => 'MRI',
            'body_part'            => 'Brain',
            'clinical_information' => 'Rule out ischemia',
            'priority'             => 'STAT',
            'status'               => 'Completed',
            'requested_at'         => now(),
        ]);

        $rx = \App\Models\Prescription::create([
            'prescription_no' => 'RX-PP-' . uniqid(),
            'patient_id'      => $patient->id,
            'doctor_id'       => $doctorA->id,
            'diagnosis'       => 'Essential Hypertension',
            'status'          => 'Dispensed',
            'prescribed_at'   => now(),
        ]);

        $surg = \App\Models\SurgeryRequest::create([
            'request_no'     => 'SRG-PP-' . uniqid(),
            'patient_id'     => $patient->id,
            'doctor_id'      => $doctorA->id,
            'procedure_name' => 'Laparoscopic Appendectomy',
            'urgency'        => 'Elective',
            'status'         => 'Scheduled',
            'requested_at'   => now(),
        ]);

        $diet = \App\Models\DietRequest::create([
            'request_no'   => 'DR-PP-' . uniqid(),
            'patient_id'   => $patient->id,
            'doctor_id'    => $doctorA->id,
            'diet_type'    => 'Low-Sodium Cardiac',
            'status'       => 'Active',
            'requested_at' => now(),
        ]);

        // Bind $restrictedPatient exclusively to $doctorB
        \App\Models\LabRequest::create([
            'request_no'   => 'LAB-B-' . uniqid(),
            'patient_id'   => $restrictedPatient->id,
            'doctor_id'    => $doctorB->id,
            'priority'     => 'Routine',
            'status'       => 'Pending',
            'requested_at' => now(),
        ]);

        $response = $this->actingAs($doctorA)->get(route('patients.show', $patient))->assertStatus(200);

        // Verify redesigned workspace, header, structured sections, and all 5 clinical module tabs & records
        $response
            ->assertSee('patient-profile-workspace', false)
            ->assertSee('Vance, Eleanor Marie')
            ->assertSee($patient->patient_no)
            ->assertSee('Inpatient')
            ->assertSee('Ward 3B / Bed 12')
            ->assertSee('O+')
            ->assertSee('09171234567')
            ->assertSee('eleanor.vance@example.test')
            ->assertSee('145 Emerald Ave, Pasig City')
            ->assertSee('Personal Information')
            ->assertSee('Admission Information')
            ->assertSee('Address')
            ->assertSee('Read-Only')
            ->assertSee('Laboratory (LIS)')
            ->assertSee('Radiology (RIS)')
            ->assertSee('Prescriptions (PMS)')
            ->assertSee('Surgery (SORS)')
            ->assertSee('Diet (DNMS)')
            ->assertSee($lab->request_no)
            ->assertSee($rad->request_no)
            ->assertSee($rx->prescription_no)
            ->assertSee($surg->request_no)
            ->assertSee($diet->request_no)
            ->assertSee('Low-Sodium Cardiac');

        // Doctor A is forbidden from viewing Doctor B's restricted patient profile
        $this->actingAs($doctorA)->get(route('patients.show', $restrictedPatient))->assertStatus(403);
    }

    // ──────────────────────── Unauthorized Roles Tests ────────────────────────

    public function test_unauthorized_roles_cannot_access_patient_index(): void
    {
        foreach ($this->unauthorizedRoles() as $roleSlug) {
            $user = $this->createUserWithRole($roleSlug);
            $this->actingAs($user)
                 ->get(route('patients.index'))
                 ->assertStatus(403);
        }
    }

    public function test_unauthorized_roles_cannot_view_patient_profile(): void
    {
        $patient = $this->createPatient();

        foreach ($this->unauthorizedRoles() as $roleSlug) {
            $user = $this->createUserWithRole($roleSlug);
            $this->actingAs($user)
                 ->get(route('patients.show', $patient))
                 ->assertStatus(403);
        }
    }


    public function test_unauthorized_roles_cannot_delete_patient(): void
    {
        $patient = $this->createPatient();

        foreach ($this->unauthorizedRoles() as $roleSlug) {
            $user = $this->createUserWithRole($roleSlug);

            $this->actingAs($user)
                 ->delete(route('patients.destroy', $patient))
                 ->assertStatus(403);
        }
    }

    // ──────────────────────── Global Search Protection ────────────────────────

    public function test_admin_and_doctor_can_search_patients_in_global_search(): void
    {
        $admin = $this->createUserWithRole('admin');
        $doctor = $this->createUserWithRole('doctor');
        $this->createPatient();

        $responseAdmin = $this->actingAs($admin)->getJson(route('global-search', ['q' => 'John']));
        $responseAdmin->assertStatus(200);
        $responseAdmin->assertJsonStructure(['results' => ['patients']]);

        $responseDoctor = $this->actingAs($doctor)->getJson(route('global-search', ['q' => 'John']));
        $responseDoctor->assertStatus(200);
        $responseDoctor->assertJsonStructure(['results' => ['patients']]);
    }

    public function test_unauthorized_roles_cannot_search_patients_in_global_search(): void
    {
        $this->createPatient();

        foreach ($this->unauthorizedRoles() as $roleSlug) {
            $user = $this->createUserWithRole($roleSlug);
            $response = $this->actingAs($user)->getJson(route('global-search', ['q' => 'John']));
            $response->assertStatus(200);
            $this->assertArrayNotHasKey('patients', $response->json('results'));
        }
    }

    protected function ensureRolesExist(): void
    {
        $roles = [
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
}
