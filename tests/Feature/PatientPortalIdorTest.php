<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\LabRequest;
use App\Models\LabRequestItem;
use App\Models\LabResult;
use App\Models\LabTest;
use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PatientPortalIdorTest extends TestCase
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
            ['name' => 'Doctor',   'slug' => 'doctor',   'dashboard_route' => 'doctor.dashboard'],
            ['name' => 'Admin',    'slug' => 'admin',    'dashboard_route' => 'admin.dashboard'],
            ['name' => 'Patient',  'slug' => 'patient',  'dashboard_route' => 'patient.dashboard'],
        ];

        foreach ($roles as $r) {
            Role::firstOrCreate(['slug' => $r['slug']], $r);
        }
    }

    private function createPatientUser(string $name, string $prefix): array
    {
        $uniq = uniqid();
        $patient = Patient::create([
            'patient_no'    => "PAT-{$prefix}-{$uniq}",
            'first_name'    => $name,
            'last_name'     => 'TestSubject',
            'date_of_birth' => '1992-03-20',
            'gender'        => 'Male',
            'phone'         => '0917000' . rand(1000, 9999),
            'email'         => "{$prefix}_{$uniq}@patient.dtcs",
            'address'       => "{$prefix} Street, Suite 100",
            'status'        => 'Active',
        ]);

        $user = User::create([
            'name'        => $name,
            'email'       => "{$prefix}_{$uniq}@patient.dtcs",
            'password'    => bcrypt('Secr3t!P@ssword2026'),
            'patient_id'  => $patient->id,
            'department'  => 'Patient Portal',
            'is_active'   => true,
        ]);

        $patientRole = Role::where('slug', 'patient')->firstOrFail();
        $user->roles()->attach($patientRole->id);

        $patient->update(['user_id' => $user->id]);

        return [$user, $patient];
    }

    private function createDoctorUser(): User
    {
        $uniq = uniqid();
        $doctor = User::create([
            'name'        => 'Dr. Gregory House',
            'email'       => "dr_{$uniq}@dtcs.hospital",
            'password'    => bcrypt('Secr3t!P@ssword2026'),
            'employee_id' => 'DOC-' . rand(1000, 9999),
            'department'  => 'Diagnostic Medicine',
            'is_active'   => true,
        ]);

        $doctorRole = Role::where('slug', 'doctor')->firstOrFail();
        $doctor->roles()->attach($doctorRole->id);

        return $doctor;
    }

    // ─────────────────────────────────────────────────────────────────
    // 1. Zero-Trust Access & IDOR Prevention
    // ─────────────────────────────────────────────────────────────────

    public function test_patient_sees_only_own_appointments(): void
    {
        [$patientUserA, $patientA] = $this->createPatientUser('Alice', 'alice');
        [$patientUserB, $patientB] = $this->createPatientUser('Bob', 'bob');
        $doctor = $this->createDoctorUser();

        // Appointment for Alice
        $aptA = Appointment::create([
            'appointment_no'   => 'APT-ALICE-01',
            'patient_id'       => $patientA->id,
            'doctor_id'        => $doctor->id,
            'department'       => 'Cardiology',
            'scheduled_at'     => now()->addDays(2),
            'duration_minutes' => 30,
            'type'             => 'Follow-up',
            'reason'           => 'Alice checkup',
            'status'           => Appointment::STATUS_CONFIRMED,
        ]);

        // Appointment for Bob
        $aptB = Appointment::create([
            'appointment_no'   => 'APT-BOB-01',
            'patient_id'       => $patientB->id,
            'doctor_id'        => $doctor->id,
            'department'       => 'Orthopedics',
            'scheduled_at'     => now()->addDays(3),
            'duration_minutes' => 30,
            'type'             => 'Consultation',
            'reason'           => 'Bob knee pain',
            'status'           => Appointment::STATUS_CONFIRMED,
        ]);

        // Alice visits appointments
        $responseA = $this->actingAs($patientUserA)->get(route('patient.appointments'));
        $responseA->assertStatus(200);
        $responseA->assertSee('APT-ALICE-01');
        $responseA->assertDontSee('APT-BOB-01');

        // Bob visits appointments
        $responseB = $this->actingAs($patientUserB)->get(route('patient.appointments'));
        $responseB->assertStatus(200);
        $responseB->assertSee('APT-BOB-01');
        $responseB->assertDontSee('APT-ALICE-01');
    }

    public function test_patient_sees_only_own_billing_invoices(): void
    {
        [$patientUserA, $patientA] = $this->createPatientUser('Alice', 'alice');
        [$patientUserB, $patientB] = $this->createPatientUser('Bob', 'bob');

        $invA = Invoice::create([
            'invoice_no'     => 'INV-ALICE-999',
            'patient_id'     => $patientA->id,
            'description'    => 'Consultation and Lab Services',
            'total_amount'   => 1500.00,
            'paid_amount'    => 1500.00,
            'balance'        => 0.00,
            'payment_method' => 'Cash',
            'status'         => Invoice::STATUS_PAID,
            'paid_at'        => now(),
        ]);

        $invB = Invoice::create([
            'invoice_no'     => 'INV-BOB-888',
            'patient_id'     => $patientB->id,
            'description'    => 'Orthopedic Surgery Assessment',
            'total_amount'   => 4500.00,
            'paid_amount'    => 0.00,
            'balance'        => 4500.00,
            'status'         => Invoice::STATUS_UNPAID,
        ]);

        // Alice views billing
        $responseA = $this->actingAs($patientUserA)->get(route('patient.billing'));
        $responseA->assertStatus(200);
        $responseA->assertSee('INV-ALICE-999');
        $responseA->assertDontSee('INV-BOB-888');

        // Bob views billing
        $responseB = $this->actingAs($patientUserB)->get(route('patient.billing'));
        $responseB->assertStatus(200);
        $responseB->assertSee('INV-BOB-888');
        $responseB->assertDontSee('INV-ALICE-999');
    }

    public function test_patient_booking_action_binds_strictly_to_authenticated_patient(): void
    {
        [$patientUserA, $patientA] = $this->createPatientUser('Alice', 'alice');
        [$patientUserB, $patientB] = $this->createPatientUser('Bob', 'bob');
        $doctor = $this->createDoctorUser();

        // Alice books appointment, even if malicious input attempts to pass patient_id = Bob's id
        $response = $this->actingAs($patientUserA)->post(route('patient.appointments.book'), [
            'patient_id'   => $patientB->id, // Malicious spoof attempt
            'doctor_id'    => $doctor->id,
            'scheduled_at' => now()->addDays(5)->format('Y-m-d H:i:s'),
            'type'         => 'In-Person Consultation',
            'reason'       => 'Alice routine consultation',
        ]);

        $response->assertRedirect(route('patient.appointments'));

        // Verify the appointment was created for Alice, NOT Bob
        $created = Appointment::where('patient_id', $patientA->id)->first();
        $this->assertNotNull($created);
        $this->assertEquals('Alice routine consultation', $created->reason);

        // Bob should have 0 appointments
        $bobApts = Appointment::where('patient_id', $patientB->id)->count();
        $this->assertEquals(0, $bobApts);
    }

    public function test_patient_profile_update_strictly_updates_authenticated_patient(): void
    {
        [$patientUserA, $patientA] = $this->createPatientUser('Alice', 'alice');
        [$patientUserB, $patientB] = $this->createPatientUser('Bob', 'bob');

        $originalBobPhone = $patientB->phone;

        // Alice submits contact update
        $response = $this->actingAs($patientUserA)->post(route('patient.profile.update'), [
            'phone'   => '09991112222',
            'address' => 'Updated Alice Street',
        ]);

        $response->assertRedirect(route('patient.profile'));

        // Alice is updated
        $patientA->refresh();
        $this->assertEquals('09991112222', $patientA->phone);
        $this->assertEquals('Updated Alice Street', $patientA->address);

        // Bob remains completely untouched
        $patientB->refresh();
        $this->assertEquals($originalBobPhone, $patientB->phone);
    }

    // ─────────────────────────────────────────────────────────────────
    // 2. Unreleased Results Concealment & Privacy Boundary
    // ─────────────────────────────────────────────────────────────────

    public function test_unreleased_diagnostic_results_are_hidden_from_patient(): void
    {
        [$patientUser, $patient] = $this->createPatientUser('Alice', 'alice');
        $doctor = $this->createDoctorUser();

        // Find or create a lab test category and test
        $category = \App\Models\LabTestCategory::firstOrCreate(
            ['code' => 'HEM'],
            ['name' => 'Hematology', 'is_active' => true]
        );

        $labTest = LabTest::firstOrCreate(
            ['code' => 'CBC-01'],
            ['name' => 'Complete Blood Count', 'lab_test_category_id' => $category->id, 'price' => 250.00, 'is_active' => true]
        );

        // Create Lab Request
        $requestNo = 'REQ-' . uniqid();
        $labRequest = LabRequest::create([
            'request_no' => $requestNo,
            'patient_id' => $patient->id,
            'doctor_id'  => $doctor->id,
            'priority'   => 'Routine',
            'status'     => 'Completed',
        ]);

        $item = LabRequestItem::create([
            'lab_request_id' => $labRequest->id,
            'lab_test_id'    => $labTest->id,
            'status'         => 'Completed',
        ]);

        // 1. Encoded Result (NOT released)
        $unreleasedResult = LabResult::create([
            'lab_request_item_id' => $item->id,
            'result_value'        => 'WBC: 15.2 (Elevated - Critical Note)',
            'remarks'             => 'INTERNAL: Differential diagnosis suggests severe infection',
            'status'              => 'Encoded',
        ]);

        // Patient visits results page
        $response = $this->actingAs($patientUser)->get(route('patient.results'));
        $response->assertStatus(200);
        // The unreleased result value and remarks must NEVER appear
        $response->assertDontSee('Elevated - Critical Note');
        $response->assertDontSee('INTERNAL: Differential diagnosis');

        // 2. Now Release the result
        $unreleasedResult->update([
            'status'       => 'Released',
            'released_at'  => now(),
            'result_value' => 'WBC: 15.2 - Official Report',
        ]);

        // Now patient visits results page again
        $responseReleased = $this->actingAs($patientUser)->get(route('patient.results'));
        $responseReleased->assertStatus(200);
        $responseReleased->assertSee('WBC: 15.2 - Official Report');
    }

    // ─────────────────────────────────────────────────────────────────
    // 3. Unauthenticated & Malformed Session Protection
    // ─────────────────────────────────────────────────────────────────

    public function test_unauthenticated_request_to_patient_portal_redirects_to_login(): void
    {
        $this->get(route('patient.dashboard'))->assertRedirect(route('login'));
        $this->get(route('patient.profile'))->assertRedirect(route('login'));
        $this->get(route('patient.appointments'))->assertRedirect(route('login'));
        $this->get(route('patient.results'))->assertRedirect(route('login'));
        $this->get(route('patient.billing'))->assertRedirect(route('login'));
    }

    public function test_patient_user_without_linked_patient_record_is_forbidden(): void
    {
        $orphanUser = User::create([
            'name'        => 'Orphan Patient',
            'email'       => 'orphan@patient.dtcs',
            'password'    => bcrypt('Secr3t!P@ssword2026'),
            'patient_id'  => null, // Deliberately unlinked
            'department'  => 'Patient Care',
            'is_active'   => true,
        ]);

        $patientRole = Role::where('slug', 'patient')->firstOrFail();
        $orphanUser->roles()->attach($patientRole->id);

        $this->actingAs($orphanUser)->get(route('patient.dashboard'))->assertStatus(403);
    }
}
