<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Prescription;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Requirements-alignment tests: appointment integrity, link-only portal accounts,
 * route-level permission enforcement for LIS / PMS.
 */
class RequirementsAlignmentTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([
            ['Doctor', 'doctor', 'doctor.dashboard'],
            ['Admin', 'admin', 'admin.dashboard'],
            ['Patient', 'patient', 'patient.dashboard'],
            ['Pharmacist', 'pharmacist', 'pharmacy.dashboard'],
            ['Medical Technologist', 'med-tech', 'lab.dashboard'],
        ] as [$n, $s, $d]) {
            Role::firstOrCreate(['slug' => $s], ['name' => $n, 'dashboard_route' => $d]);
        }
    }

    private function makeUser(string $slug, string $label = 'u'): User
    {
        $u = User::create([
            'name' => ucfirst($label) . ' ' . uniqid(),
            'email' => $label . '_' . uniqid() . '@test.dtcs',
            'password' => bcrypt('Secr3t!P@ssword2026'),
            'department' => 'Test',
            'is_active' => true,
        ]);
        $u->roles()->attach(Role::where('slug', $slug)->first()->id);
        return $u;
    }

    private function makePatient(): Patient
    {
        $uniq = uniqid();
        return Patient::create([
            'patient_no' => "PAT-T-{$uniq}",
            'first_name' => 'Test',
            'last_name' => 'Subject',
            'date_of_birth' => '1990-01-01',
            'gender' => 'Male',
            'phone' => '09170001111',
            'email' => "p_{$uniq}@patient.dtcs",
            'address' => 'Test St',
            'status' => 'Active',
        ]);
    }

    private function portalUser(Patient $p): User
    {
        $u = $this->makeUser('patient', 'pt');
        $u->update(['patient_id' => $p->id]);
        $p->update(['user_id' => $u->id]);
        return $u;
    }

    private function apt(Patient $p, User $doctor, string $when, string $status = 'Confirmed'): Appointment
    {
        return Appointment::create([
            'appointment_no' => 'APT-' . strtoupper(uniqid()),
            'patient_id' => $p->id,
            'doctor_id' => $doctor->id,
            'department' => 'General',
            'scheduled_at' => $when,
            'duration_minutes' => 30,
            'type' => 'In-Person Consultation',
            'reason' => 'test',
            'status' => $status,
        ]);
    }

    // ── Appointments ────────────────────────────────────────────────

    public function test_doctor_double_booking_is_rejected(): void
    {
        $doctor = $this->makeUser('doctor', 'dr');
        $p1 = $this->makePatient();
        $p2 = $this->makePatient();
        $u2 = $this->portalUser($p2);
        $slot = now()->addDays(3)->setTime(10, 0);
        $this->apt($p1, $doctor, $slot->format('Y-m-d H:i:s'));

        $this->actingAs($u2)->post(route('patient.appointments.book'), [
            'doctor_id' => $doctor->id,
            'scheduled_at' => $slot->copy()->addMinutes(10)->format('Y-m-d H:i:s'),
            'type' => 'In-Person Consultation',
            'reason' => 'overlap',
        ]);

        $this->assertSame(0, Appointment::where('patient_id', $p2->id)->count());
    }

    public function test_non_doctor_cannot_be_booked_as_provider(): void
    {
        $notDoctor = $this->makeUser('admin', 'ad');
        $p = $this->makePatient();
        $u = $this->portalUser($p);

        $this->actingAs($u)->post(route('patient.appointments.book'), [
            'doctor_id' => $notDoctor->id,
            'scheduled_at' => now()->addDays(4)->format('Y-m-d H:i:s'),
            'type' => 'In-Person Consultation',
            'reason' => 'x',
        ]);

        $this->assertSame(0, Appointment::where('patient_id', $p->id)->count());
    }

    public function test_patient_can_cancel_own_but_not_others_appointment(): void
    {
        $doctor = $this->makeUser('doctor', 'dr');
        $pa = $this->makePatient();
        $pb = $this->makePatient();
        $ua = $this->portalUser($pa);
        $aptA = $this->apt($pa, $doctor, now()->addDays(2)->format('Y-m-d H:i:s'));
        $aptB = $this->apt($pb, $doctor, now()->addDays(3)->format('Y-m-d H:i:s'));

        $this->actingAs($ua)->post(route('patient.appointments.cancel', $aptB))->assertStatus(404);
        $this->assertSame('Confirmed', $aptB->fresh()->status);

        $this->actingAs($ua)->post(route('patient.appointments.cancel', $aptA));
        $this->assertSame('Cancelled', $aptA->fresh()->status);
    }

    public function test_doctor_cannot_update_another_doctors_appointment(): void
    {
        $d1 = $this->makeUser('doctor', 'dr');
        $d2 = $this->makeUser('doctor', 'dr');
        $apt = $this->apt($this->makePatient(), $d1, now()->addDays(2)->format('Y-m-d H:i:s'), 'Requested');

        $this->actingAs($d2)
            ->patch(route('admin.appointments.status', $apt), ['status' => 'Confirmed'])
            ->assertStatus(403);
        $this->assertSame('Requested', $apt->fresh()->status);
    }

    // ── Link-only portal accounts ───────────────────────────────────

    public function test_intake_create_route_no_longer_exists(): void
    {
        $this->assertFalse(Route::has('admin.patients.intake.store'));
    }

    public function test_portal_link_requires_identity_attestation(): void
    {
        $admin = $this->makeUser('admin', 'ad');
        $p = $this->makePatient();

        $this->actingAs($admin)->post(route('admin.patients.link-portal', $p), ['email' => $p->email])
            ->assertSessionHasErrors('identity_verified');
        $this->assertNull($p->fresh()->user_id);
    }

    public function test_portal_link_email_must_match_record_and_no_default_password(): void
    {
        $admin = $this->makeUser('admin', 'ad');
        $p = $this->makePatient();

        $this->actingAs($admin)->post(route('admin.patients.link-portal', $p), [
            'email' => 'other_' . uniqid() . '@x.test', 'identity_verified' => '1',
        ])->assertSessionHasErrors('email');
        $this->assertNull($p->fresh()->user_id);

        $this->actingAs($admin)->post(route('admin.patients.link-portal', $p), [
            'email' => $p->email, 'identity_verified' => '1',
        ]);
        $user = User::where('email', $p->email)->first();
        $this->assertNotNull($user);
        $this->assertSame($p->id, $user->patient_id);
        $this->assertFalse(\Illuminate\Support\Facades\Hash::check('Patient@' . date('Y'), $user->password));

        // second link is refused
        $this->actingAs($admin)->post(route('admin.patients.link-portal', $p), [
            'email' => $p->email, 'identity_verified' => '1',
        ])->assertSessionHasErrors('email');
    }

    // ── Route-level permission enforcement ──────────────────────────

    public function test_lis_and_pms_routes_carry_permission_middleware(): void
    {
        $expect = [
            'lab.results.validate' => 'permission:lis.results.verify',
            'lab.results.release' => 'permission:lis.results.release',
            'pharmacy.prescriptions.verify' => 'permission:pms.prescriptions.verify',
        ];
        foreach ($expect as $name => $mw) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, "Missing route {$name}");
            $this->assertContains($mw, $route->gatherMiddleware(), "{$name} lacks {$mw}");
        }
    }

    public function test_pharmacist_without_verify_permission_is_forbidden(): void
    {
        $route = Route::getRoutes()->getByName('pharmacy.prescriptions.verify');
        $this->assertNotNull($route);

        $role = Role::where('slug', 'pharmacist')->first();
        $perm = Permission::where('slug', 'pms.prescriptions.verify')->first();
        $this->assertNotNull($perm, 'permission missing from DB');
        $role->permissions()->detach($perm->id); // rolled back by transaction

        $pharm = $this->makeUser('pharmacist', 'ph');
        $patient = $this->makePatient();
        $doctor = $this->makeUser('doctor', 'dr');
        $prescription = Prescription::create([
            'prescription_no' => 'RX-' . uniqid(),
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'status' => 'pending',
            'prescribed_at' => now(),
        ]);

        $this->actingAs($pharm)->patch(route('pharmacy.prescriptions.verify', $prescription))->assertStatus(403);
    }
}
