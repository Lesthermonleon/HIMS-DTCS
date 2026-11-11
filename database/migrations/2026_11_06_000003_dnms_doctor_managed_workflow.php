<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DNMS Doctor-Managed Workflow — additive & idempotent RBAC update.
 *
 * - Grants full DNMS clinical request, assessment, and diet-plan permissions to `doctor`.
 * - Preserves read-only monitoring permissions (`dnms.*.view`) for `admin`.
 * - Revokes `dnms.*` permissions from `dietitian` while preserving existing `dietitian` role,
 *   user accounts, and historical `diet_plans` records.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('permission_role') || ! Schema::hasTable('roles')) {
            return;
        }

        $now = now();
        $permissions = [
            ['slug' => 'dnms.requests.view',      'name' => 'View Diet Requests',          'module' => 'DNMS'],
            ['slug' => 'dnms.requests.create',    'name' => 'Create Diet Requests',        'module' => 'DNMS'],
            ['slug' => 'dnms.requests.manage',    'name' => 'Manage Own Diet Requests',    'module' => 'DNMS'],
            ['slug' => 'dnms.assessments.create', 'name' => 'Create Nutrition Assessments', 'module' => 'DNMS'],
            ['slug' => 'dnms.assessments.update', 'name' => 'Update Nutrition Assessments', 'module' => 'DNMS'],
            ['slug' => 'dnms.plans.view',         'name' => 'View Diet Plans',             'module' => 'DNMS'],
            ['slug' => 'dnms.plans.create',       'name' => 'Create Diet Plans',           'module' => 'DNMS'],
            ['slug' => 'dnms.plans.update',       'name' => 'Update Diet Plans',           'module' => 'DNMS'],
            ['slug' => 'dnms.plans.complete',     'name' => 'Complete Diet Plans',         'module' => 'DNMS'],
            ['slug' => 'dnms.reports.view',       'name' => 'View Nutrition Reports',      'module' => 'DNMS'],
        ];

        foreach ($permissions as $perm) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $perm['slug']],
                ['name' => $perm['name'], 'module' => $perm['module'], 'updated_at' => $now, 'created_at' => $now]
            );
        }

        $permIds = DB::table('permissions')->where('module', 'DNMS')->pluck('id', 'slug');
        $roleIds = DB::table('roles')->whereIn('slug', ['doctor', 'admin', 'dietitian', 'super_admin'])->pluck('id', 'slug');

        // Revoke DNMS operational permissions from the separate `dietitian` role (preserving the role & accounts)
        if (isset($roleIds['dietitian']) && $permIds->isNotEmpty()) {
            DB::table('permission_role')
                ->where('role_id', $roleIds['dietitian'])
                ->whereIn('permission_id', $permIds->values()->all())
                ->delete();
        }

        // Ensure super_admin has no automatic clinical DNMS permissions
        if (isset($roleIds['super_admin']) && $permIds->isNotEmpty()) {
            DB::table('permission_role')
                ->where('role_id', $roleIds['super_admin'])
                ->whereIn('permission_id', $permIds->values()->all())
                ->delete();
        }

        // Doctor owns the full clinical DNMS lifecycle; Admin has read-only monitoring.
        $roleMap = [
            'doctor' => [
                'dnms.requests.view',
                'dnms.requests.create',
                'dnms.requests.manage',
                'dnms.assessments.create',
                'dnms.assessments.update',
                'dnms.plans.view',
                'dnms.plans.create',
                'dnms.plans.update',
                'dnms.plans.complete',
                'dnms.reports.view',
            ],
            'admin' => [
                'dnms.requests.view',
                'dnms.plans.view',
                'dnms.reports.view',
            ],
        ];

        foreach ($roleMap as $roleSlug => $permSlugs) {
            $roleId = $roleIds[$roleSlug] ?? null;
            if (! $roleId) {
                continue;
            }
            foreach ($permSlugs as $permSlug) {
                $permId = $permIds[$permSlug] ?? null;
                if ($permId) {
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

