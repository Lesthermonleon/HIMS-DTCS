<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Tests for the archived user profile page (admin.users.archived).
 *
 * Covers:
 *  - Admin can open an archived user's profile (200).
 *  - Profile displays the deletion reason from the audit log.
 *  - Profile displays the correct admin who performed the deletion.
 *  - Profile handles a missing audit record gracefully (no fabrication).
 *  - Delete → Restore → Delete shows the latest deletion event, not an old one.
 *  - Non-admin roles cannot access the archived profile (403).
 *  - Unauthenticated users are redirected to login.
 */
class ArchivedUserProfileTest extends TestCase
{
    use DatabaseTransactions;

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureRolesExist();
    }

    private function ensureRolesExist(): void
    {
        $defs = [
            ['name' => 'System Administrator', 'slug' => 'admin',      'dashboard_route' => 'admin.dashboard'],
            ['name' => 'Doctor',               'slug' => 'doctor',     'dashboard_route' => 'doctor.dashboard'],
            ['name' => 'Medical Technologist', 'slug' => 'med-tech',   'dashboard_route' => 'lab.dashboard'],
            ['name' => 'Pharmacist',           'slug' => 'pharmacist', 'dashboard_route' => 'pharmacy.dashboard'],
        ];
        foreach ($defs as $r) {
            Role::firstOrCreate(['slug' => $r['slug']], $r);
        }
    }

    private function makeUser(string $slug, bool $active = true): User
    {
        $user = User::create([
            'name'        => 'Test ' . ucfirst($slug) . ' ' . uniqid(),
            'email'       => $slug . '_' . uniqid() . '@test.hospital',
            'password'    => bcrypt('password'),
            'employee_id' => 'EMP-' . rand(10000, 99999),
            'department'  => 'Test Dept',
            'is_active'   => $active,
        ]);
        $role = Role::where('slug', $slug)->firstOrFail();
        $user->roles()->attach($role->id);
        return $user;
    }

    /** Archive a user and create the matching ActivityLog record (mirrors what the controller does). */
    private function archiveWithComment(User $admin, User $target, string $comment): void
    {
        $target->delete();
        ActivityLog::create([
            'user_id'     => $admin->id,
            'action'      => 'User Archived',
            'module'      => 'User Management',
            'severity'    => ActivityLog::SEVERITY_WARNING,
            'result'      => ActivityLog::RESULT_SUCCESS,
            'description' => "User account [{$target->email}] ({$target->name}) was archived by admin. Reason: {$comment}",
            'ip_address'  => '127.0.0.1',
            'logged_at'   => now(),
        ]);
    }

    /** Restore a user and create the matching ActivityLog record. */
    private function restoreWithComment(User $admin, User $target, string $comment): void
    {
        $target->restore();
        ActivityLog::create([
            'user_id'     => $admin->id,
            'action'      => 'User Restored',
            'module'      => 'User Management',
            'severity'    => ActivityLog::SEVERITY_WARNING,
            'result'      => ActivityLog::RESULT_SUCCESS,
            'description' => "User account [{$target->email}] ({$target->name}) was restored by admin. Reason: {$comment}",
            'ip_address'  => '127.0.0.1',
            'logged_at'   => now(),
        ]);
    }

    private function profileUrl(User $target): string
    {
        return route('admin.users.archived', $target->id);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Access
    // ──────────────────────────────────────────────────────────────────────────

    public function test_admin_can_open_archived_user_profile(): void
    {
        $admin  = $this->makeUser('admin');
        $target = $this->makeUser('doctor');
        $this->archiveWithComment($admin, $target, 'Testing access.');

        $this->actingAs($admin)
             ->get($this->profileUrl($target))
             ->assertStatus(200)
             ->assertSee($target->name)
             ->assertSee($target->email);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Deletion reason displayed correctly
    // ──────────────────────────────────────────────────────────────────────────

    public function test_profile_shows_deletion_reason_from_audit_log(): void
    {
        $admin   = $this->makeUser('admin');
        $target  = $this->makeUser('doctor');
        $comment = 'Account no longer required — employee left the organisation.';
        $this->archiveWithComment($admin, $target, $comment);

        $response = $this->actingAs($admin)->get($this->profileUrl($target));

        $response->assertStatus(200);
        $response->assertSee($comment);
    }

    public function test_profile_shows_correct_deleted_by_admin(): void
    {
        $admin  = $this->makeUser('admin');
        $target = $this->makeUser('doctor');
        $this->archiveWithComment($admin, $target, 'Deletion by specific admin.');

        $this->actingAs($admin)
             ->get($this->profileUrl($target))
             ->assertStatus(200)
             ->assertSee($admin->name);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Missing audit record — graceful handling
    // ──────────────────────────────────────────────────────────────────────────

    public function test_profile_handles_missing_audit_record_gracefully(): void
    {
        $admin  = $this->makeUser('admin');
        $target = $this->makeUser('doctor');
        $target->delete(); // Archive without creating a log record

        $response = $this->actingAs($admin)->get($this->profileUrl($target));

        $response->assertStatus(200);
        // Must show the unavailability message, not null/undefined in the rendered document
        $response->assertSee('Deletion reason unavailable');
        // Must NOT contain fabricated data in rendered content
        $htmlWithoutScripts = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $response->getContent());
        $this->assertStringNotContainsString('null', $htmlWithoutScripts);
        $this->assertStringNotContainsString('undefined', $htmlWithoutScripts);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Delete → Restore → Delete shows latest deletion event
    // ──────────────────────────────────────────────────────────────────────────

    public function test_profile_shows_latest_deletion_event_after_multiple_cycles(): void
    {
        $admin  = $this->makeUser('admin');
        $target = $this->makeUser('doctor');

        // First cycle
        $this->archiveWithComment($admin, $target, 'First deletion — temporary leave.');
        $this->restoreWithComment($admin, $target, 'Returned from leave.');

        // Second deletion — this must be the one displayed
        $latestComment = 'Second deletion — permanently reassigned to another facility.';
        $this->archiveWithComment($admin, $target, $latestComment);

        $response = $this->actingAs($admin)->get($this->profileUrl($target));

        $response->assertStatus(200);
        $response->assertSee($latestComment);
        // The old deletion comment should also appear in the Account History section
        $response->assertSee('First deletion');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Account history shows all events
    // ──────────────────────────────────────────────────────────────────────────

    public function test_account_history_shows_delete_and_restore_events(): void
    {
        $admin  = $this->makeUser('admin');
        $target = $this->makeUser('doctor');

        $this->archiveWithComment($admin, $target, 'Deletion for testing.');
        $this->restoreWithComment($admin, $target, 'Restore for testing.');
        $this->archiveWithComment($admin, $target, 'Final deletion.');

        $response = $this->actingAs($admin)->get($this->profileUrl($target));

        $response->assertStatus(200);
        $response->assertSee('User Archived');
        $response->assertSee('User Restored');
        $response->assertSee('Deletion for testing.');
        $response->assertSee('Restore for testing.');
        $response->assertSee('Final deletion.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Authorisation
    // ──────────────────────────────────────────────────────────────────────────

    public function test_doctor_cannot_access_archived_user_profile(): void
    {
        $admin  = $this->makeUser('admin');
        $doctor = $this->makeUser('doctor');
        $target = $this->makeUser('med-tech');
        $this->archiveWithComment($admin, $target, 'Archived for RBAC test.');

        $this->actingAs($doctor)
             ->get($this->profileUrl($target))
             ->assertStatus(403);
    }

    public function test_non_admin_roles_cannot_access_archived_user_profile(): void
    {
        $admin    = $this->makeUser('admin');
        $nonAdmin = $this->makeUser('pharmacist');
        $target   = $this->makeUser('med-tech');
        $this->archiveWithComment($admin, $target, 'Archived for RBAC test.');

        $this->actingAs($nonAdmin)
             ->get($this->profileUrl($target))
             ->assertStatus(403);
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $admin  = $this->makeUser('admin');
        $target = $this->makeUser('doctor');
        $this->archiveWithComment($admin, $target, 'Unauthenticated test.');

        $this->get($this->profileUrl($target))
             ->assertRedirect('/login');
    }
}
