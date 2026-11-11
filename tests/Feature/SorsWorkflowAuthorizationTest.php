<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\OperatingRoom;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SurgeryRequest;
use App\Models\SurgerySchedule;
use App\Models\SurgicalTeam;
use App\Models\SurgicalTeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SorsWorkflowAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    protected User $doctorA;
    protected User $doctorB;
    protected User $orCoordinator;
    protected User $orStaffAssigned;
    protected User $orStaffUnassigned;
    protected User $medTech;

    protected Patient $patientA;
    protected Patient $patientB;

    protected OperatingRoom $room1;
    protected OperatingRoom $room2;
    protected OperatingRoom $roomMaintenance;

    protected SurgicalTeam $teamA;
    protected SurgicalTeam $teamB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureSorsRolesAndPermissions();

        $uniq = uniqid();

        $this->doctorA           = $this->createUserWithRole('doctor', "dr_a_{$uniq}@hospital.test", 'Dr. Alice Surgeon');
        $this->doctorB           = $this->createUserWithRole('doctor', "dr_b_{$uniq}@hospital.test", 'Dr. Bob Surgeon');
        $this->orCoordinator     = $this->createUserWithRole('or-coordinator', "coord_{$uniq}@hospital.test", 'Coord Clara');
        $this->orStaffAssigned   = $this->createUserWithRole('or-staff', "orstaff1_{$uniq}@hospital.test", 'Nurse Sam Scrub');
        $this->orStaffUnassigned = $this->createUserWithRole('or-staff', "orstaff2_{$uniq}@hospital.test", 'Nurse Uma Unassigned');
        $this->medTech           = $this->createUserWithRole('med-tech', "medtech_{$uniq}@hospital.test", 'Tech Mike');

        $this->patientA = Patient::create([
            'patient_no'    => 'PAT-A-' . rand(10000, 99999),
            'first_name'    => 'Arthur',
            'last_name'     => 'Pendleton',
            'date_of_birth' => '1980-04-12',
            'gender'        => 'Male',
            'patient_type'  => 'Inpatient',
        ]);

        $this->patientB = Patient::create([
            'patient_no'    => 'PAT-B-' . rand(10000, 99999),
            'first_name'    => 'Beatrice',
            'last_name'     => 'Vance',
            'date_of_birth' => '1975-09-23',
            'gender'        => 'Female',
            'patient_type'  => 'Inpatient',
        ]);

        // Establish exclusive care relationship between Doctor B and Patient B
        Appointment::create([
            'appointment_no'   => 'APT-B-' . strtoupper($uniq),
            'patient_id'       => $this->patientB->id,
            'doctor_id'        => $this->doctorB->id,
            'department'       => 'Surgery',
            'scheduled_at'     => now()->addDays(1),
            'duration_minutes' => 30,
            'type'             => 'In-Person Consultation',
            'status'           => 'Confirmed',
            'reason'           => 'Pre-op surgical consult with Dr. Bob',
        ]);

        $this->room1 = OperatingRoom::create([
            'name'      => 'OR-Alpha-' . $uniq,
            'location'  => '3rd Floor Surgical Suite',
            'status'    => 'Available',
            'is_active' => true,
        ]);

        $this->room2 = OperatingRoom::create([
            'name'      => 'OR-Beta-' . $uniq,
            'location'  => '3rd Floor Surgical Suite',
            'status'    => 'Available',
            'is_active' => true,
        ]);

        $this->roomMaintenance = OperatingRoom::create([
            'name'      => 'OR-Maint-' . $uniq,
            'location'  => '3rd Floor Surgical Suite',
            'status'    => 'Under Maintenance',
            'is_active' => true,
        ]);

        $this->teamA = SurgicalTeam::create([
            'name'       => 'Surgical Team Alpha ' . $uniq,
            'surgeon_id' => $this->doctorA->id,
            'notes'      => 'Dr. Alice Primary Team',
            'is_active'  => true,
        ]);

        SurgicalTeamMember::create([
            'surgical_team_id' => $this->teamA->id,
            'user_id'          => $this->orStaffAssigned->id,
            'role_in_team'     => 'Scrub Nurse',
        ]);

        $this->teamB = SurgicalTeam::create([
            'name'       => 'Surgical Team Beta ' . $uniq,
            'surgeon_id' => $this->doctorB->id,
            'notes'      => 'Dr. Bob Primary Team',
            'is_active'  => true,
        ]);
    }

    private function ensureSorsRolesAndPermissions(): void
    {
        $roles = [
            ['name' => 'Super Administrator',  'slug' => 'super_admin',    'dashboard_route' => 'super-admin.dashboard'],
            ['name' => 'System Administrator', 'slug' => 'admin',          'dashboard_route' => 'admin.dashboard'],
            ['name' => 'Doctor',               'slug' => 'doctor',         'dashboard_route' => 'doctor.dashboard'],
            ['name' => 'OR Coordinator',       'slug' => 'or-coordinator', 'dashboard_route' => 'surgery.dashboard'],
            ['name' => 'OR Staff',             'slug' => 'or-staff',       'dashboard_route' => 'surgery.dashboard'],
            ['name' => 'Medical Technologist', 'slug' => 'med-tech',       'dashboard_route' => 'lab.dashboard'],
        ];

        foreach ($roles as $r) {
            Role::firstOrCreate(['slug' => $r['slug']], $r);
        }

        $permissions = [
            ['slug' => 'sors.requests.view',      'name' => 'View Surgery Requests',   'module' => 'SORS'],
            ['slug' => 'sors.requests.create',    'name' => 'Create Surgery Requests', 'module' => 'SORS'],
            ['slug' => 'sors.requests.manage',    'name' => 'Manage Surgery Requests', 'module' => 'SORS'],
            ['slug' => 'sors.schedules.view',     'name' => 'View OR Schedules',       'module' => 'SORS'],
            ['slug' => 'sors.schedules.manage',   'name' => 'Manage OR Schedules',     'module' => 'SORS'],
            ['slug' => 'sors.readiness.update',   'name' => 'Update OR Readiness',     'module' => 'SORS'],
            ['slug' => 'sors.procedures.execute', 'name' => 'Execute OR Procedures',   'module' => 'SORS'],
            ['slug' => 'sors.reports.view',       'name' => 'View Surgery Reports',    'module' => 'SORS'],
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['slug' => $perm['slug']], $perm);
        }
    }

    private function createUserWithRole(string $roleSlug, string $email, string $name): User
    {
        $user = User::create([
            'name'        => $name,
            'email'       => $email,
            'password'    => bcrypt('Secr3t!P@ssword2026'),
            'employee_id' => 'EMP-' . rand(10000, 99999),
            'department'  => 'Surgery',
            'is_active'   => true,
        ]);

        $role = Role::where('slug', $roleSlug)->firstOrFail();
        $user->roles()->attach($role->id);

        return $user;
    }

    private function createSurgeryRequest(User $doctor, Patient $patient, string $status = 'Pending', string $procedure = 'Laparoscopic Cholecystectomy'): SurgeryRequest
    {
        return SurgeryRequest::create([
            'request_no'         => 'SR-TEST-' . strtoupper(uniqid()),
            'patient_id'         => $patient->id,
            'doctor_id'          => $doctor->id,
            'procedure_name'     => $procedure,
            'diagnosis'          => 'Acute Calculus Cholecystitis',
            'anesthesia_type'    => 'General Anesthesia',
            'urgency'            => 'Elective',
            'estimated_duration' => 90,
            'status'             => $status,
            'notes'              => 'Standard laparoscopic tray required',
            'requested_at'       => now(),
        ]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // 1. DOCTOR SURGERY REQUEST CREATION & EDITING
    // ════════════════════════════════════════════════════════════════════════

    public function test_doctor_can_create_and_edit_own_pending_surgery_request(): void
    {
        $this->actingAs($this->doctorA)
            ->get(route('surgery.requests.create'))
            ->assertStatus(200);

        $response = $this->actingAs($this->doctorA)->post(route('surgery.requests.store'), [
            'patient_id'         => $this->patientA->id,
            'procedure_name'     => 'Laparoscopic Appendectomy',
            'diagnosis'          => 'Acute Appendicitis',
            'urgency'            => 'Urgent',
            'anesthesia_type'    => 'General Anesthesia',
            'estimated_duration' => 60,
            'notes'              => 'Stat pre-op antibiotics',
        ]);

        $response->assertRedirect(route('surgery.requests.index'));

        $surgeryRequest = SurgeryRequest::where('doctor_id', $this->doctorA->id)
            ->where('patient_id', $this->patientA->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('Pending', $surgeryRequest->status);

        // Doctor A can load the edit view and update their Pending request
        $this->actingAs($this->doctorA)
            ->get(route('surgery.requests.edit', $surgeryRequest))
            ->assertStatus(200)
            ->assertSee('Laparoscopic Appendectomy');

        $this->actingAs($this->doctorA)
            ->put(route('surgery.requests.update', $surgeryRequest), [
                'patient_id'         => $this->patientA->id,
                'procedure_name'     => 'Laparoscopic Appendectomy with Peritoneal Lavage',
                'diagnosis'          => 'Perforated Appendicitis',
                'urgency'            => 'Emergency',
                'anesthesia_type'    => 'General Anesthesia',
                'estimated_duration' => 90,
                'notes'              => 'Updated clinical requirements',
            ])
            ->assertRedirect(route('surgery.requests.show', $surgeryRequest));

        $this->assertSame('Emergency', $surgeryRequest->fresh()->urgency);
    }

    // ════════════════════════════════════════════════════════════════════════
    // 2. DOCTOR PATIENT & REQUEST OWNERSHIP (CROSS-DOCTOR ISOLATION)
    // ════════════════════════════════════════════════════════════════════════

    public function test_doctor_cannot_access_or_order_for_another_doctors_restricted_patient_or_records(): void
    {
        $requestB = $this->createSurgeryRequest($this->doctorB, $this->patientB, 'Pending', 'Coronary Artery Bypass');

        $scheduleB = SurgerySchedule::create([
            'surgery_request_id' => $requestB->id,
            'operating_room_id'  => $this->room2->id,
            'surgical_team_id'   => $this->teamB->id,
            'scheduled_by'       => $this->orCoordinator->id,
            'scheduled_at'       => now()->addDays(2)->setTime(9, 0),
            'duration_minutes'   => 180,
            'status'             => 'Scheduled',
        ]);
        $requestB->update(['status' => 'Scheduled']);

        // 1. Doctor A cannot create a surgery request for Doctor B's restricted patient (Patient B)
        $this->actingAs($this->doctorA)->post(route('surgery.requests.store'), [
            'patient_id'     => $this->patientB->id,
            'procedure_name' => 'Unauthorized Procedure',
            'urgency'        => 'Elective',
        ])->assertStatus(403);

        // 2. Doctor A cannot view, edit, or cancel Doctor B's surgery request via direct URL
        $this->actingAs($this->doctorA)->get(route('surgery.requests.show', $requestB))->assertStatus(403);
        $this->actingAs($this->doctorA)->get(route('surgery.requests.edit', $requestB))->assertStatus(422); // Scheduled -> not Pending

        $pendingB = $this->createSurgeryRequest($this->doctorB, $this->patientB, 'Pending', 'Thyroidectomy');
        $this->actingAs($this->doctorA)->get(route('surgery.requests.show', $pendingB))->assertStatus(403);
        $this->actingAs($this->doctorA)->get(route('surgery.requests.edit', $pendingB))->assertStatus(403);
        $this->actingAs($this->doctorA)->put(route('surgery.requests.update', $pendingB), [
            'patient_id'     => $this->patientB->id,
            'procedure_name' => 'Tampered Procedure',
            'urgency'        => 'Urgent',
        ])->assertStatus(403);
        $this->actingAs($this->doctorA)->patch(route('surgery.requests.cancel', $pendingB))->assertStatus(403);

        // 3. Doctor A cannot view or print Doctor B's surgery schedule
        $this->actingAs($this->doctorA)->get(route('surgery.schedules.show', $scheduleB))->assertStatus(403);
        $this->actingAs($this->doctorA)->get(route('surgery.schedules.print', $scheduleB))->assertStatus(403);

        // 4. Doctor A's schedule index and calendar events do not leak Doctor B's cases
        $this->actingAs($this->doctorA)
            ->get(route('surgery.schedules.index'))
            ->assertStatus(200)
            ->assertDontSee('Coronary Artery Bypass');

        $calEvents = $this->actingAs($this->doctorA)
            ->getJson(route('surgery.calendar.events'))
            ->assertStatus(200)
            ->json();

        $eventIds = array_column($calEvents, 'id');
        $this->assertNotContains($scheduleB->id, $eventIds);

        // 5. Doctor A's surgery report only shows Doctor A's cases, never Doctor B's
        $this->actingAs($this->doctorA)
            ->get(route('reports.surgery.activity'))
            ->assertStatus(200)
            ->assertDontSee($requestB->request_no);
    }

    // ════════════════════════════════════════════════════════════════════════
    // 3. OR COORDINATOR SCHEDULING PERMISSIONS & CLINICAL RESTRICTIONS
    // ════════════════════════════════════════════════════════════════════════

    public function test_or_coordinator_can_schedule_and_manage_operations_but_cannot_make_clinical_decisions(): void
    {
        $requestA = $this->createSurgeryRequest($this->doctorA, $this->patientA, 'Pending');

        // OR Coordinator cannot create a surgery request or edit clinical details on a doctor's request
        $this->actingAs($this->orCoordinator)->get(route('surgery.requests.create'))->assertStatus(403);
        $this->actingAs($this->orCoordinator)->post(route('surgery.requests.store'), [
            'patient_id'     => $this->patientA->id,
            'procedure_name' => 'Coordinator Invented Surgery',
            'urgency'        => 'Elective',
        ])->assertStatus(403);
        $this->actingAs($this->orCoordinator)->get(route('surgery.requests.edit', $requestA))->assertStatus(403);
        $this->actingAs($this->orCoordinator)->put(route('surgery.requests.update', $requestA), [
            'patient_id'     => $this->patientA->id,
            'procedure_name' => 'Altered Procedure',
            'urgency'        => 'Emergency',
        ])->assertStatus(403);

        // OR Coordinator can view incoming requests and schedule an approved/pending operation
        $this->actingAs($this->orCoordinator)
            ->get(route('surgery.requests.show', $requestA))
            ->assertStatus(200)
            ->assertSee($requestA->request_no);

        $scheduledAt = now()->addDays(3)->setTime(10, 0)->format('Y-m-d H:i:s');

        $this->actingAs($this->orCoordinator)->post(route('surgery.schedules.store'), [
            'surgery_request_id' => $requestA->id,
            'operating_room_id'  => $this->room1->id,
            'surgical_team_id'   => $this->teamA->id,
            'scheduled_at'       => $scheduledAt,
            'duration_minutes'   => 90,
            'notes'              => 'Laparoscopic tower reserved',
        ])->assertRedirect(route('surgery.schedules.index'));

        $this->assertSame('Scheduled', $requestA->fresh()->status);

        $schedule = SurgerySchedule::where('surgery_request_id', $requestA->id)->firstOrFail();

        // OR Coordinator can reschedule the operation
        $newScheduledAt = now()->addDays(4)->setTime(14, 0)->format('Y-m-d H:i:s');
        $this->actingAs($this->orCoordinator)->put(route('surgery.schedules.update', $schedule), [
            'surgery_request_id' => $requestA->id,
            'operating_room_id'  => $this->room2->id,
            'surgical_team_id'   => $this->teamA->id,
            'scheduled_at'       => $newScheduledAt,
            'duration_minutes'   => 90,
            'status'             => 'Scheduled',
            'notes'              => 'Moved to OR-Beta',
        ])->assertRedirect(route('surgery.schedules.show', $schedule));

        $this->assertSame($this->room2->id, (int) $schedule->fresh()->operating_room_id);

        // OR Coordinator can access operational surgery reports
        $this->actingAs($this->orCoordinator)
            ->get(route('reports.surgery.activity'))
            ->assertStatus(200)
            ->assertSee($requestA->request_no);

        $this->actingAs($this->orCoordinator)
            ->get(route('reports.surgery.or-utilization'))
            ->assertStatus(200);
    }

    // ════════════════════════════════════════════════════════════════════════
    // 4. OR STAFF ASSIGNED-CASE ACCESS & RESTRICTIONS
    // ════════════════════════════════════════════════════════════════════════

    public function test_or_staff_can_access_and_update_readiness_on_assigned_cases_only(): void
    {
        $requestA = $this->createSurgeryRequest($this->doctorA, $this->patientA, 'Scheduled', 'Total Knee Arthroplasty');
        $scheduleAssigned = SurgerySchedule::create([
            'surgery_request_id' => $requestA->id,
            'operating_room_id'  => $this->room1->id,
            'surgical_team_id'   => $this->teamA->id, // Contains $this->orStaffAssigned
            'scheduled_by'       => $this->orCoordinator->id,
            'scheduled_at'       => now()->addDays(2)->setTime(8, 0),
            'duration_minutes'   => 120,
            'status'             => 'Scheduled',
            'readiness_status'   => 'Pending Prep',
        ]);

        $requestB = $this->createSurgeryRequest($this->doctorB, $this->patientB, 'Scheduled', 'Craniotomy');
        $scheduleUnassigned = SurgerySchedule::create([
            'surgery_request_id' => $requestB->id,
            'operating_room_id'  => $this->room2->id,
            'surgical_team_id'   => $this->teamB->id, // Does NOT contain $this->orStaffAssigned
            'scheduled_by'       => $this->orCoordinator->id,
            'scheduled_at'       => now()->addDays(2)->setTime(11, 0),
            'duration_minutes'   => 180,
            'status'             => 'Scheduled',
        ]);

        // 1. Assigned OR Staff sees only their assigned schedule in index & calendar
        $this->actingAs($this->orStaffAssigned)
            ->get(route('surgery.schedules.index'))
            ->assertStatus(200)
            ->assertSee('Total Knee Arthroplasty')
            ->assertDontSee('Craniotomy');

        $this->actingAs($this->orStaffAssigned)
            ->get(route('surgery.schedules.show', $scheduleAssigned))
            ->assertStatus(200)
            ->assertSee('Total Knee Arthroplasty');

        // 2. Assigned OR Staff is blocked (403) from viewing unassigned schedule
        $this->actingAs($this->orStaffAssigned)
            ->get(route('surgery.schedules.show', $scheduleUnassigned))
            ->assertStatus(403);

        // 3. Assigned OR Staff can update OR preparation & readiness on their assigned case
        $this->actingAs($this->orStaffAssigned)
            ->patch(route('surgery.schedules.readiness', $scheduleAssigned), [
                'readiness_status' => 'Ready',
                'readiness_notes'  => 'Orthopedic instruments sterilized and anesthesia machine checked.',
            ])
            ->assertRedirect();

        $this->assertSame('Pending Prep', $scheduleAssigned->fresh()->previous_readiness_status);
        $this->assertSame('Ready', $scheduleAssigned->fresh()->readiness_status);
        $this->assertSame($this->orStaffAssigned->id, (int) $scheduleAssigned->fresh()->readiness_updated_by);

        // 4. Unassigned OR Staff cannot update readiness on a case they are not assigned to
        $this->actingAs($this->orStaffUnassigned)
            ->patch(route('surgery.schedules.readiness', $scheduleAssigned), [
                'readiness_status' => 'Preparing',
            ])
            ->assertStatus(403);

        // 5. OR Staff cannot independently start or complete procedures by default without explicit sors.procedures.execute permission
        $this->actingAs($this->orStaffAssigned)
            ->patch(route('surgery.schedules.start', $scheduleAssigned))
            ->assertStatus(403);

        // 6. OR Staff cannot view general surgery requests, create requests, assign ORs, or view surgery reports
        $this->actingAs($this->orStaffAssigned)->get(route('surgery.requests.index'))->assertStatus(403);
        $this->actingAs($this->orStaffAssigned)->get(route('surgery.requests.show', $requestA))->assertStatus(403);
        $this->actingAs($this->orStaffAssigned)->get(route('surgery.schedules.create'))->assertStatus(403);
        $this->actingAs($this->orStaffAssigned)->get(route('surgery.schedules.edit', $scheduleAssigned))->assertStatus(403);
        $this->actingAs($this->orStaffAssigned)->get(route('reports.surgery.activity'))->assertStatus(403);
    }

    // ════════════════════════════════════════════════════════════════════════
    // 5. UNAUTHORIZED SCHEDULE & READINESS MODIFICATION
    // ════════════════════════════════════════════════════════════════════════

    public function test_doctors_and_or_staff_cannot_modify_master_or_schedules_and_doctors_cannot_update_readiness(): void
    {
        $requestA = $this->createSurgeryRequest($this->doctorA, $this->patientA, 'Pending');

        // Doctor cannot create an OR schedule or assign an operating room
        $this->actingAs($this->doctorA)->get(route('surgery.schedules.create'))->assertStatus(403);
        $this->actingAs($this->doctorA)->post(route('surgery.schedules.store'), [
            'surgery_request_id' => $requestA->id,
            'operating_room_id'  => $this->room1->id,
            'surgical_team_id'   => $this->teamA->id,
            'scheduled_at'       => now()->addDays(2)->format('Y-m-d H:i:s'),
            'duration_minutes'   => 60,
        ])->assertStatus(403);

        // OR Coordinator schedules it
        $schedule = SurgerySchedule::create([
            'surgery_request_id' => $requestA->id,
            'operating_room_id'  => $this->room1->id,
            'surgical_team_id'   => $this->teamA->id,
            'scheduled_by'       => $this->orCoordinator->id,
            'scheduled_at'       => now()->addDays(2)->setTime(10, 0),
            'duration_minutes'   => 60,
            'status'             => 'Scheduled',
        ]);
        $requestA->update(['status' => 'Scheduled']);

        // Doctor cannot edit, delete, cancel, start, complete, or update OR readiness on the schedule
        $this->actingAs($this->doctorA)->get(route('surgery.schedules.edit', $schedule))->assertStatus(403);
        $this->actingAs($this->doctorA)->put(route('surgery.schedules.update', $schedule), [
            'operating_room_id' => $this->room2->id,
            'scheduled_at'      => now()->addDays(3)->format('Y-m-d H:i:s'),
            'duration_minutes'  => 60,
        ])->assertStatus(403);
        $this->actingAs($this->doctorA)->delete(route('surgery.schedules.destroy', $schedule))->assertStatus(403);
        $this->actingAs($this->doctorA)->patch(route('surgery.schedules.cancel', $schedule))->assertStatus(403);
        $this->actingAs($this->doctorA)->patch(route('surgery.schedules.readiness', $schedule), [
            'readiness_status' => 'Ready',
        ])->assertStatus(403);
        $this->actingAs($this->doctorA)->patch(route('surgery.schedules.start', $schedule))->assertStatus(403);
        $this->actingAs($this->doctorA)->patch(route('surgery.schedules.complete', $schedule))->assertStatus(403);

        // Doctor also cannot cancel a SurgeryRequest once it has been Scheduled by OR Coordinator
        $this->actingAs($this->doctorA)->patch(route('surgery.requests.cancel', $requestA))->assertStatus(403);

        // OR Staff cannot create, edit, cancel, or delete schedules
        $this->actingAs($this->orStaffAssigned)->put(route('surgery.schedules.update', $schedule), [
            'operating_room_id' => $this->room2->id,
            'scheduled_at'      => now()->addDays(3)->format('Y-m-d H:i:s'),
            'duration_minutes'  => 60,
        ])->assertStatus(403);
        $this->actingAs($this->orStaffAssigned)->delete(route('surgery.schedules.destroy', $schedule))->assertStatus(403);
        $this->actingAs($this->orStaffAssigned)->patch(route('surgery.schedules.cancel', $schedule))->assertStatus(403);
    }

    // ════════════════════════════════════════════════════════════════════════
    // 6. OPERATING-ROOM SCHEDULING CONFLICTS & DOUBLE-BOOKING PREVENTION
    // ════════════════════════════════════════════════════════════════════════

    public function test_operating_room_and_surgical_team_scheduling_conflicts_are_prevented(): void
    {
        $req1 = $this->createSurgeryRequest($this->doctorA, $this->patientA, 'Pending', 'Case 1');
        $req2 = $this->createSurgeryRequest($this->doctorB, $this->patientB, 'Pending', 'Case 2');

        $baseStart = now()->addDays(5)->setTime(10, 0, 0);

        // Schedule Case 1 in Room 1 from 10:00 to 12:00 (120 mins) with Team A
        $this->actingAs($this->orCoordinator)->post(route('surgery.schedules.store'), [
            'surgery_request_id' => $req1->id,
            'operating_room_id'  => $this->room1->id,
            'surgical_team_id'   => $this->teamA->id,
            'scheduled_at'       => $baseStart->format('Y-m-d H:i:s'),
            'duration_minutes'   => 120,
        ])->assertRedirect(route('surgery.schedules.index'));

        // Attempt 1: Overlapping time window (11:00 to 12:30) in the SAME Operating Room (Room 1) -> Rejected!
        $this->actingAs($this->orCoordinator)->post(route('surgery.schedules.store'), [
            'surgery_request_id' => $req2->id,
            'operating_room_id'  => $this->room1->id,
            'surgical_team_id'   => $this->teamB->id,
            'scheduled_at'       => $baseStart->copy()->addMinutes(60)->format('Y-m-d H:i:s'),
            'duration_minutes'   => 90,
        ])->assertSessionHasErrors('operating_room_id');

        // Attempt 2: Overlapping time window (11:00 to 12:30) in Room 2, but with the SAME Surgical Team (Team A) -> Rejected!
        $this->actingAs($this->orCoordinator)->post(route('surgery.schedules.store'), [
            'surgery_request_id' => $req2->id,
            'operating_room_id'  => $this->room2->id,
            'surgical_team_id'   => $this->teamA->id,
            'scheduled_at'       => $baseStart->copy()->addMinutes(60)->format('Y-m-d H:i:s'),
            'duration_minutes'   => 90,
        ])->assertSessionHasErrors('surgical_team_id');

        // Attempt 3: Room under maintenance -> Rejected!
        $this->actingAs($this->orCoordinator)->post(route('surgery.schedules.store'), [
            'surgery_request_id' => $req2->id,
            'operating_room_id'  => $this->roomMaintenance->id,
            'surgical_team_id'   => $this->teamB->id,
            'scheduled_at'       => $baseStart->copy()->addHours(4)->format('Y-m-d H:i:s'),
            'duration_minutes'   => 60,
        ])->assertSessionHasErrors('operating_room_id');

        // Attempt 4: Valid booking in Room 2 with Team B at 11:00 -> Succeeds!
        $this->actingAs($this->orCoordinator)->post(route('surgery.schedules.store'), [
            'surgery_request_id' => $req2->id,
            'operating_room_id'  => $this->room2->id,
            'surgical_team_id'   => $this->teamB->id,
            'scheduled_at'       => $baseStart->copy()->addMinutes(60)->format('Y-m-d H:i:s'),
            'duration_minutes'   => 90,
        ])->assertRedirect(route('surgery.schedules.index'));

        $this->assertSame('Scheduled', $req2->fresh()->status);
    }

    // ════════════════════════════════════════════════════════════════════════
    // 7. INVALID STATUS TRANSITIONS
    // ════════════════════════════════════════════════════════════════════════

    public function test_invalid_status_transitions_are_rejected_server_side(): void
    {
        $req = $this->createSurgeryRequest($this->doctorA, $this->patientA, 'Scheduled');
        $schedule = SurgerySchedule::create([
            'surgery_request_id' => $req->id,
            'operating_room_id'  => $this->room1->id,
            'surgical_team_id'   => $this->teamA->id,
            'scheduled_by'       => $this->orCoordinator->id,
            'scheduled_at'       => now()->addDays(1)->setTime(9, 0),
            'duration_minutes'   => 60,
            'status'             => 'Scheduled',
        ]);

        // 1. Cannot mark a 'Scheduled' surgery as 'Completed' before starting it ('In Progress')
        $this->actingAs($this->orCoordinator)
            ->patch(route('surgery.schedules.complete', $schedule))
            ->assertStatus(422);
        $this->assertSame('Scheduled', $schedule->fresh()->status);

        // 2. Start the surgery ('Scheduled' -> 'In Progress')
        $this->actingAs($this->orCoordinator)
            ->patch(route('surgery.schedules.start', $schedule))
            ->assertRedirect();
        $this->assertSame('In Progress', $schedule->fresh()->status);
        $this->assertSame('In Progress', $req->fresh()->status);
        $this->assertSame('Occupied', $this->room1->fresh()->status);

        // 3. Cannot start an already 'In Progress' surgery again, nor cancel or delete it
        $this->actingAs($this->orCoordinator)
            ->patch(route('surgery.schedules.start', $schedule))
            ->assertStatus(422);
        $this->actingAs($this->orCoordinator)
            ->patch(route('surgery.schedules.cancel', $schedule))
            ->assertStatus(422);
        $this->actingAs($this->orCoordinator)
            ->delete(route('surgery.schedules.destroy', $schedule))
            ->assertStatus(422);

        // 4. Complete the surgery ('In Progress' -> 'Completed')
        $this->actingAs($this->orCoordinator)
            ->patch(route('surgery.schedules.complete', $schedule))
            ->assertRedirect();
        $this->assertSame('Completed', $schedule->fresh()->status);
        $this->assertSame('Completed', $req->fresh()->status);
        $this->assertSame('Available', $this->room1->fresh()->status);

        // 5. Terminal 'Completed' surgery cannot be started, completed again, edited, deleted, readiness-updated, or cancelled
        $this->actingAs($this->orCoordinator)
            ->patch(route('surgery.schedules.start', $schedule))
            ->assertStatus(403);
        $this->actingAs($this->orCoordinator)
            ->patch(route('surgery.schedules.complete', $schedule))
            ->assertStatus(403);
        $this->actingAs($this->orCoordinator)
            ->get(route('surgery.schedules.edit', $schedule))
            ->assertStatus(422);
        $this->actingAs($this->orCoordinator)
            ->delete(route('surgery.schedules.destroy', $schedule))
            ->assertStatus(422);
        $this->actingAs($this->orCoordinator)
            ->patch(route('surgery.schedules.readiness', $schedule), ['readiness_status' => 'Preparing'])
            ->assertStatus(422);
        $this->actingAs($this->orCoordinator)
            ->patch(route('surgery.requests.cancel', $req))
            ->assertStatus(422);

        // 6. Terminal 'Cancelled' schedule also cannot be permanently deleted (historical record preserved)
        $reqCancelled = $this->createSurgeryRequest($this->doctorA, $this->patientA, 'Cancelled');
        $scheduleCancelled = SurgerySchedule::create([
            'surgery_request_id' => $reqCancelled->id,
            'operating_room_id'  => $this->room2->id,
            'surgical_team_id'   => $this->teamA->id,
            'scheduled_by'       => $this->orCoordinator->id,
            'scheduled_at'       => now()->addDays(2)->setTime(14, 0),
            'duration_minutes'   => 60,
            'status'             => 'Cancelled',
        ]);
        $this->actingAs($this->orCoordinator)
            ->delete(route('surgery.schedules.destroy', $scheduleCancelled))
            ->assertStatus(422);
        $this->assertDatabaseHas('surgery_schedules', ['id' => $scheduleCancelled->id]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // 8. SIDEBAR VISIBILITY BY ROLE
    // ════════════════════════════════════════════════════════════════════════

    public function test_sidebar_visibility_for_doctor_or_coordinator_and_or_staff(): void
    {
        $surgeryReportsUrl = route('reports.surgery.activity');

        // A. OR Coordinator Sidebar
        $coordResponse = $this->actingAs($this->orCoordinator)->get(route('surgery.dashboard'));
        $coordResponse->assertStatus(200);
        $coordSidebar = view('partials._sidebar')->render();
        $this->assertStringContainsString('MAIN', $coordSidebar);
        $this->assertStringContainsString('SURGERY & OPERATING ROOM (SORS)', $coordSidebar);
        $this->assertStringContainsString(route('surgery.requests.index'), $coordSidebar);
        $this->assertStringContainsString(route('surgery.schedules.index'), $coordSidebar);
        $this->assertStringContainsString(route('surgery.calendar'), $coordSidebar);
        $this->assertStringContainsString('>REPORTS</div>', $coordSidebar);
        $this->assertSame(1, substr_count($coordSidebar, 'href="' . $surgeryReportsUrl . '"'));
        $this->assertGreaterThan(
            strpos($coordSidebar, '>REPORTS</div>'),
            strpos($coordSidebar, $surgeryReportsUrl),
            'OR Coordinator Surgery Reports link must appear under REPORTS, not under SURGERY & OPERATING ROOM (SORS).'
        );

        // B. OR Staff Sidebar & Dashboard
        $staffResponse = $this->actingAs($this->orStaffAssigned)->get(route('surgery.dashboard'));
        $staffResponse->assertStatus(200);
        $staffResponse->assertSeeText('OR Staff Assigned Cases & Readiness Center');
        $staffSidebar = view('partials._sidebar')->render();
        $this->assertStringContainsString('MAIN', $staffSidebar);
        $this->assertStringContainsString('SURGERY & OPERATING ROOM (SORS)', $staffSidebar);
        $this->assertStringContainsString(route('surgery.schedules.index'), $staffSidebar);
        $this->assertStringContainsString(route('surgery.calendar'), $staffSidebar);
        // OR Staff must NOT see Surgery Requests or Surgery Reports in the sidebar
        $this->assertStringNotContainsString(route('surgery.requests.index'), $staffSidebar);
        $this->assertStringNotContainsString($surgeryReportsUrl, $staffSidebar);

        // C. Doctor Sidebar: Clinical Reports is the single entry point under REPORTS; no separate Surgery Reports sidebar link
        $docResponse = $this->actingAs($this->doctorA)->get(route('doctor.dashboard'));
        $docResponse->assertStatus(200);
        $docSidebar = view('partials._sidebar')->render();
        $this->assertStringContainsString('Surgery <span class="sb-badge">SORS</span>', $docSidebar);
        $this->assertStringContainsString(route('surgery.dashboard'), $docSidebar);
        $this->assertStringContainsString(route('surgery.requests.index'), $docSidebar);
        $this->assertStringContainsString(route('surgery.schedules.index'), $docSidebar);
        $this->assertStringContainsString(route('surgery.calendar'), $docSidebar);
        $this->assertStringContainsString(route('reports.index'), $docSidebar);
        $this->assertStringContainsString('Clinical Reports', $docSidebar);
        $this->assertStringNotContainsString($surgeryReportsUrl, $docSidebar);

        // D. Admin & Super Admin Sidebars & Report Access
        $uniq = uniqid();
        $admin      = $this->createUserWithRole('admin', "adm_sb_{$uniq}@hospital.test", 'Admin User');
        $superAdmin = $this->createUserWithRole('super_admin', "sadm_sb_{$uniq}@hospital.test", 'SuperAdmin User');

        foreach ([$admin, $superAdmin] as $govUser) {
            $this->actingAs($govUser)->get($surgeryReportsUrl)->assertStatus(200);
            $govSidebar = view('partials._sidebar')->render();
            $this->assertSame(1, substr_count($govSidebar, 'href="' . $surgeryReportsUrl . '"'));
            $this->assertGreaterThan(
                strpos($govSidebar, '>MONITORING & REPORTS</div>'),
                strpos($govSidebar, $surgeryReportsUrl),
                'Admin/Super Admin Surgery Reports link must appear under MONITORING & REPORTS.'
            );
        }

        // E. Active state when visiting Surgery Reports page (Clinical Reports active for Doctor; Surgery Reports active for OR Coordinator)
        $reportResp = $this->actingAs($this->doctorA)->get($surgeryReportsUrl)->assertStatus(200);
        $reportHtml = $reportResp->getContent();
        $this->assertMatchesRegularExpression(
            '/<a href="' . preg_quote(route('reports.index'), '/') . '" class="nav-link active">/',
            $reportHtml
        );
        $this->assertStringNotContainsString('id="grp-sor" class="collapse show"', $reportHtml);
        $this->assertStringNotContainsString('<div class="collapse show" id="grp-sor">', $reportHtml);

        $coordReportHtml = $this->actingAs($this->orCoordinator)->get($surgeryReportsUrl)->assertStatus(200)->getContent();
        $this->assertMatchesRegularExpression(
            '/<a href="' . preg_quote($surgeryReportsUrl, '/') . '" class="nav-link active">/',
            $coordReportHtml
        );
    }

    // ════════════════════════════════════════════════════════════════════════
    // 9. DIRECT URL AUTHORIZATION FOR NON-SORS ROLES & ADMIN CLINICAL BOUNDARY
    // ════════════════════════════════════════════════════════════════════════

    public function test_unrelated_roles_are_blocked_from_direct_sors_urls(): void
    {
        $req = $this->createSurgeryRequest($this->doctorA, $this->patientA, 'Pending');

        $this->actingAs($this->medTech)->get(route('surgery.dashboard'))->assertStatus(403);
        $this->actingAs($this->medTech)->get(route('surgery.requests.index'))->assertStatus(403);
        $this->actingAs($this->medTech)->get(route('surgery.requests.show', $req))->assertStatus(403);
        $this->actingAs($this->medTech)->get(route('surgery.schedules.index'))->assertStatus(403);
        $this->actingAs($this->medTech)->get(route('surgery.calendar'))->assertStatus(403);
        $this->actingAs($this->medTech)->get(route('reports.surgery.activity'))->assertStatus(403);

        // Admin and Super Admin do not automatically receive clinical or OR scheduling mutation authority
        $uniq = uniqid();
        $admin      = $this->createUserWithRole('admin', "adm_{$uniq}@hospital.test", 'Admin User');
        $superAdmin = $this->createUserWithRole('super_admin', "sadm_{$uniq}@hospital.test", 'SuperAdmin User');

        foreach ([$admin, $superAdmin] as $privilegedUser) {
            $this->actingAs($privilegedUser)->post(route('surgery.requests.store'), [
                'patient_id'     => $this->patientA->id,
                'procedure_name' => 'Admin Surgery Attempt',
                'urgency'        => 'Elective',
            ])->assertStatus(403);

            $this->actingAs($privilegedUser)->patch(route('surgery.requests.cancel', $req))->assertStatus(403);

            $this->actingAs($privilegedUser)->post(route('surgery.schedules.store'), [
                'surgery_request_id' => $req->id,
                'operating_room_id'  => $this->room1->id,
                'surgical_team_id'   => $this->teamA->id,
                'scheduled_at'       => now()->addDays(3)->format('Y-m-d H:i:s'),
                'duration_minutes'   => 60,
            ])->assertStatus(403);
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    // 10. ACTIVITY LOGGING & AUDIT HISTORY RETENTION
    // ════════════════════════════════════════════════════════════════════════

    public function test_full_sors_lifecycle_records_audit_trail_in_activity_logs(): void
    {
        // 1. Doctor creates surgery request
        $this->actingAs($this->doctorA)->post(route('surgery.requests.store'), [
            'patient_id'         => $this->patientA->id,
            'procedure_name'     => 'Open Cholecystectomy',
            'diagnosis'          => 'Gallbladder Empyema',
            'urgency'            => 'Urgent',
            'anesthesia_type'    => 'General Anesthesia',
            'estimated_duration' => 120,
        ])->assertRedirect();

        $req = SurgeryRequest::where('doctor_id', $this->doctorA->id)->latest('id')->firstOrFail();

        $this->assertDatabaseHas('activity_logs', [
            'user_id'       => $this->doctorA->id,
            'action'        => 'Surgery Request Created',
            'module'        => 'Surgery',
            'loggable_type' => SurgeryRequest::class,
            'loggable_id'   => $req->id,
        ]);

        // 2. OR Coordinator schedules the operation
        $start = now()->addDays(2)->setTime(8, 30, 0);
        $this->actingAs($this->orCoordinator)->post(route('surgery.schedules.store'), [
            'surgery_request_id' => $req->id,
            'operating_room_id'  => $this->room1->id,
            'surgical_team_id'   => $this->teamA->id,
            'scheduled_at'       => $start->format('Y-m-d H:i:s'),
            'duration_minutes'   => 120,
        ])->assertRedirect();

        $schedule = SurgerySchedule::where('surgery_request_id', $req->id)->firstOrFail();

        $this->assertDatabaseHas('activity_logs', [
            'user_id'       => $this->orCoordinator->id,
            'action'        => 'Surgery Scheduled',
            'module'        => 'Surgery',
            'loggable_type' => SurgerySchedule::class,
            'loggable_id'   => $schedule->id,
        ]);

        // 3. OR Coordinator reschedules the operation
        $rescheduledStart = $start->copy()->addHours(3);
        $this->actingAs($this->orCoordinator)->put(route('surgery.schedules.update', $schedule), [
            'surgery_request_id' => $req->id,
            'operating_room_id'  => $this->room1->id,
            'surgical_team_id'   => $this->teamA->id,
            'scheduled_at'       => $rescheduledStart->format('Y-m-d H:i:s'),
            'duration_minutes'   => 120,
            'status'             => 'Scheduled',
        ])->assertRedirect();

        $this->assertDatabaseHas('activity_logs', [
            'user_id'       => $this->orCoordinator->id,
            'action'        => 'Surgery Rescheduled',
            'module'        => 'Surgery',
            'loggable_type' => SurgerySchedule::class,
            'loggable_id'   => $schedule->id,
        ]);

        // 4. Assigned OR Staff updates OR readiness (records previous_readiness_status -> readiness_status)
        $this->actingAs($this->orStaffAssigned)->patch(route('surgery.schedules.readiness', $schedule), [
            'readiness_status' => 'Ready',
            'readiness_notes'  => 'OR-Alpha prepped and verified.',
        ])->assertRedirect();

        $this->assertDatabaseHas('activity_logs', [
            'user_id'       => $this->orStaffAssigned->id,
            'action'        => 'OR Readiness Updated',
            'module'        => 'Surgery',
            'description'   => "OR readiness for schedule #{$schedule->id} updated from [Pending Prep] to [Ready].",
            'loggable_type' => SurgerySchedule::class,
            'loggable_id'   => $schedule->id,
        ]);

        // 5. OR Coordinator starts the operation
        $this->actingAs($this->orCoordinator)
            ->patch(route('surgery.schedules.start', $schedule))
            ->assertRedirect();

        $this->assertDatabaseHas('activity_logs', [
            'user_id'       => $this->orCoordinator->id,
            'action'        => 'Surgery Started',
            'module'        => 'Surgery',
            'loggable_type' => SurgerySchedule::class,
            'loggable_id'   => $schedule->id,
        ]);

        // 6. OR Coordinator completes the operation
        $this->actingAs($this->orCoordinator)
            ->patch(route('surgery.schedules.complete', $schedule))
            ->assertRedirect();

        $this->assertDatabaseHas('activity_logs', [
            'user_id'       => $this->orCoordinator->id,
            'action'        => 'Surgery Completed',
            'module'        => 'Surgery',
            'loggable_type' => SurgerySchedule::class,
            'loggable_id'   => $schedule->id,
        ]);

        // 7. Verify cancellation logging on a second request/schedule
        $reqCancel = $this->createSurgeryRequest($this->doctorA, $this->patientA, 'Pending', 'Hernia Repair');
        $this->actingAs($this->orCoordinator)
            ->patch(route('surgery.requests.cancel', $reqCancel))
            ->assertRedirect();

        $this->assertDatabaseHas('activity_logs', [
            'user_id'       => $this->orCoordinator->id,
            'action'        => 'Surgery Request Cancelled',
            'module'        => 'Surgery',
            'loggable_type' => SurgeryRequest::class,
            'loggable_id'   => $reqCancel->id,
        ]);
    }
}

