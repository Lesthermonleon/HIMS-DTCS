<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DNMS role-based workflow — additive & idempotent.
 *
 *  - Adds nullable assessment / audit-attribution columns on `diet_plans`.
 *  - Ensures the existing `doctor` and `dietitian` roles are reused (no duplicates; `dietary-staff` is out of scope).
 *  - Seeds granular DNMS permissions and least-privilege role bindings.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('diet_plans')) {
            Schema::table('diet_plans', function (Blueprint $table) {
                if (! Schema::hasColumn('diet_plans', 'assessment_notes')) {
                    $table->text('assessment_notes')->nullable()->after('plan_details');
                }
                if (! Schema::hasColumn('diet_plans', 'updated_by')) {
                    $table->unsignedBigInteger('updated_by')->nullable();
                }
                if (! Schema::hasColumn('diet_plans', 'completed_by')) {
                    $table->unsignedBigInteger('completed_by')->nullable();
                }
                if (! Schema::hasColumn('diet_plans', 'completed_at')) {
                    $table->timestamp('completed_at')->nullable();
                }
            });
        }

        if (Schema::hasTable('roles')) {
            $now = now();
            // Reuse existing roles; only ensure they exist (never duplicate).
            foreach ([
                'doctor'    => ['Doctor', 'doctor.dashboard', 'Order therapeutic diets for authorized patients'],
                'dietitian' => ['Dietitian', 'diet.dashboard', 'Perform nutrition assessments and prepare, update and complete individualized diet plans'],
            ] as $slug => [$name, $route, $description]) {
                if (! DB::table('roles')->where('slug', $slug)->exists()) {
                    DB::table('roles')->insert([
                        'slug' => $slug, 'name' => $name, 'dashboard_route' => $route,
                        'description' => $description, 'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }
        }

        if (Schema::hasTable('permissions') && Schema::hasTable('permission_role') && Schema::hasTable('roles')) {
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

            // Least privilege: admin gets read-only monitoring; super_admin gets no automatic clinical authority.
            $roleMap = [
                'doctor'    => ['dnms.requests.view', 'dnms.requests.create', 'dnms.requests.manage', 'dnms.plans.view', 'dnms.reports.view'],
                'dietitian' => ['dnms.requests.view', 'dnms.assessments.create', 'dnms.assessments.update', 'dnms.plans.view', 'dnms.plans.create', 'dnms.plans.update', 'dnms.plans.complete', 'dnms.reports.view'],
                'admin'     => ['dnms.requests.view', 'dnms.plans.view', 'dnms.reports.view'],
            ];

            $roleIds = DB::table('roles')->whereIn('slug', array_keys($roleMap))->pluck('id', 'slug');
            $permIds = DB::table('permissions')->where('module', 'DNMS')->pluck('id', 'slug');

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
    }

    public function down(): void
    {
        // Additive-only migration per project constraints; no destructive operations.
    }
};

