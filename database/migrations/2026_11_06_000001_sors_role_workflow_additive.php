<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('surgery_schedules')) {
            Schema::table('surgery_schedules', function (Blueprint $table) {
                $table->unsignedBigInteger('surgical_team_id')->nullable()->change();

                if (! Schema::hasColumn('surgery_schedules', 'actual_start')) {
                    $table->timestamp('actual_start')->nullable()->after('status');
                }
                if (! Schema::hasColumn('surgery_schedules', 'actual_end')) {
                    $table->timestamp('actual_end')->nullable()->after('actual_start');
                }
                if (! Schema::hasColumn('surgery_schedules', 'previous_readiness_status')) {
                    $table->string('previous_readiness_status', 32)->nullable()->after('actual_end');
                }
                if (! Schema::hasColumn('surgery_schedules', 'readiness_status')) {
                    $table->enum('readiness_status', ['Pending Prep', 'Preparing', 'Ready'])
                        ->default('Pending Prep')
                        ->after('previous_readiness_status');
                }
                if (! Schema::hasColumn('surgery_schedules', 'readiness_notes')) {
                    $table->text('readiness_notes')->nullable()->after('readiness_status');
                }
                if (! Schema::hasColumn('surgery_schedules', 'readiness_updated_by')) {
                    $table->unsignedBigInteger('readiness_updated_by')->nullable()->after('readiness_notes');
                }
                if (! Schema::hasColumn('surgery_schedules', 'readiness_updated_at')) {
                    $table->timestamp('readiness_updated_at')->nullable()->after('readiness_updated_by');
                }
            });
        }

        if (Schema::hasTable('roles')) {
            $now = now();
            DB::table('roles')->updateOrInsert(
                ['slug' => 'or-coordinator'],
                [
                    'name'            => 'OR Coordinator',
                    'dashboard_route' => 'surgery.dashboard',
                    'description'     => 'Manage surgery requests, schedule operations, assign operating rooms, and coordinate OR readiness',
                    'updated_at'      => $now,
                    'created_at'      => $now,
                ]
            );
            DB::table('roles')->updateOrInsert(
                ['slug' => 'or-staff'],
                [
                    'name'            => 'OR Staff',
                    'dashboard_route' => 'surgery.dashboard',
                    'description'     => 'View assigned surgery schedules, update operating-room preparation/readiness, and record authorized operational updates',
                    'updated_at'      => $now,
                    'created_at'      => $now,
                ]
            );
        }

        if (Schema::hasTable('permissions') && Schema::hasTable('permission_role') && Schema::hasTable('roles')) {
            $now = now();
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
                DB::table('permissions')->updateOrInsert(
                    ['slug' => $perm['slug']],
                    [
                        'name'       => $perm['name'],
                        'module'     => $perm['module'],
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );
            }

            // Least-privilege bindings:
            // - OR Staff receives view + readiness update by default; sors.procedures.execute is reserved for OR Coordinator
            //   unless explicitly granted to an OR Staff role per hospital policy.
            // - Admin receives operational read access only; Super Admin receives no automatic clinical authority.
            $roleMap = [
                'doctor'         => ['sors.requests.view', 'sors.requests.create', 'sors.schedules.view', 'sors.reports.view'],
                'or-coordinator' => ['sors.requests.view', 'sors.requests.manage', 'sors.schedules.view', 'sors.schedules.manage', 'sors.readiness.update', 'sors.procedures.execute', 'sors.reports.view'],
                'or-staff'       => ['sors.schedules.view', 'sors.readiness.update'],
                'admin'          => ['sors.requests.view', 'sors.schedules.view', 'sors.reports.view'],
            ];

            $roleIds = DB::table('roles')->whereIn('slug', array_keys($roleMap))->pluck('id', 'slug');
            $permIds = DB::table('permissions')->where('module', 'SORS')->pluck('id', 'slug');

            // Ensure default bindings match least-privilege matrix
            if (isset($roleIds['or-staff'], $permIds['sors.procedures.execute'])) {
                DB::table('permission_role')
                    ->where('role_id', $roleIds['or-staff'])
                    ->where('permission_id', $permIds['sors.procedures.execute'])
                    ->delete();
            }

            foreach ($roleMap as $roleSlug => $permSlugs) {
                $roleId = $roleIds[$roleSlug] ?? null;
                if (! $roleId) {
                    continue;
                }
                foreach ($permSlugs as $permSlug) {
                    $permId = $permIds[$permSlug] ?? null;
                    if (! $permId) {
                        continue;
                    }
                    DB::table('permission_role')->updateOrInsert(
                        ['permission_id' => $permId, 'role_id' => $roleId],
                        []
                    );
                }
            }
        }
    }

    public function down(): void
    {
        // Additive-only migration per project constraints; no destructive operations.
    }
};
