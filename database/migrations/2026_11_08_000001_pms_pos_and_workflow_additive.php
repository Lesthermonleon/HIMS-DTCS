<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Additive columns on `prescriptions` for verification notes and rejection/return workflow
        if (Schema::hasTable('prescriptions')) {
            Schema::table('prescriptions', function (Blueprint $table) {
                if (! Schema::hasColumn('prescriptions', 'verification_notes')) {
                    $table->text('verification_notes')->nullable()->after('verified_at');
                }
                if (! Schema::hasColumn('prescriptions', 'rejection_reason')) {
                    $table->text('rejection_reason')->nullable()->after('verification_notes');
                }
                if (! Schema::hasColumn('prescriptions', 'rejected_by')) {
                    $table->unsignedBigInteger('rejected_by')->nullable()->after('rejection_reason');
                }
                if (! Schema::hasColumn('prescriptions', 'rejected_at')) {
                    $table->timestamp('rejected_at')->nullable()->after('rejected_by');
                }
            });

            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE `prescriptions` MODIFY COLUMN `status` ENUM('Pending','Verified','Partially Dispensed','Dispensed','Cancelled','Rejected','Returned') NOT NULL DEFAULT 'Pending'");
            }
        }

        // 2. Pharmacy Point of Sale (POS) Transactions table
        if (! Schema::hasTable('pharmacy_pos_transactions')) {
            Schema::create('pharmacy_pos_transactions', function (Blueprint $table) {
                $table->id();
                $table->string('transaction_no', 64)->unique();
                $table->string('sale_type', 32)->default('walk_in'); // 'prescription' | 'walk_in'
                $table->unsignedBigInteger('prescription_id')->nullable()->index();
                $table->unsignedBigInteger('patient_id')->nullable()->index();
                $table->string('customer_name', 255)->nullable();
                $table->unsignedBigInteger('pharmacist_id')->index();
                $table->string('terminal_id', 64)->default('POS-TERM-01');
                $table->string('store_code', 64)->default('DTCS-PHARM-MAIN');
                $table->decimal('subtotal', 12, 2)->default(0.00);
                $table->string('discount_type', 64)->default('none'); // none, senior_citizen, pwd, employee, promotional
                $table->decimal('discount_rate', 7, 4)->default(0.0000);
                $table->decimal('discount_amount', 12, 2)->default(0.00);
                $table->string('discount_reference', 128)->nullable();
                $table->unsignedBigInteger('discount_authorized_by')->nullable();
                $table->decimal('vatable_sales', 12, 2)->default(0.00);
                $table->decimal('vat_exempt_sales', 12, 2)->default(0.00);
                $table->decimal('vat_rate', 7, 4)->default(0.1200);
                $table->decimal('vat_amount', 12, 2)->default(0.00);
                $table->decimal('grand_total', 12, 2)->default(0.00);
                $table->string('payment_method', 64)->default('Cash'); // Cash, GCash, Card, Hospital Billing
                $table->decimal('amount_tendered', 12, 2)->default(0.00);
                $table->decimal('change_amount', 12, 2)->default(0.00);
                $table->string('payment_reference', 128)->nullable();
                $table->string('status', 32)->default('Completed')->index(); // Completed, Voided, Refunded
                $table->unsignedBigInteger('voided_by')->nullable();
                $table->timestamp('voided_at')->nullable();
                $table->text('void_reason')->nullable();
                $table->string('idempotency_key', 128)->nullable()->unique();
                $table->text('notes')->nullable();
                $table->timestamp('transacted_at')->useCurrent()->index();
                $table->timestamps();
            });
        }

        // 3. Pharmacy POS Transaction Line Items table
        if (! Schema::hasTable('pharmacy_pos_transaction_items')) {
            Schema::create('pharmacy_pos_transaction_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('pharmacy_pos_transaction_id')->index();
                $table->unsignedBigInteger('prescription_item_id')->nullable()->index();
                $table->string('medicine_code', 64)->nullable();
                $table->string('medication_name', 255);
                $table->string('generic_name', 255)->nullable();
                $table->string('brand_name', 255)->nullable();
                $table->string('category', 100)->nullable();
                $table->string('strength', 100)->nullable();
                $table->string('dosage_form', 100)->nullable();
                $table->string('unit', 50)->default('unit');
                $table->string('lot_number', 100)->nullable();
                $table->date('expiry_date')->nullable();
                $table->integer('quantity');
                $table->decimal('unit_price', 12, 2);
                $table->decimal('line_subtotal', 12, 2);
                $table->timestamps();
            });
        }

        // 4. Pharmacy Low-Stock Replenishment Requests (Static Demonstration Only)
        if (! Schema::hasTable('pharmacy_replenishment_requests')) {
            Schema::create('pharmacy_replenishment_requests', function (Blueprint $table) {
                $table->id();
                $table->string('request_no', 64)->unique();
                $table->unsignedBigInteger('pharmacist_id')->index();
                $table->string('requesting_department', 150)->default('Central Pharmacy (PMS)');
                $table->string('target_subsystem', 150)->default('Inventory / Supply Chain Subsystem (Demo Boundary)');
                $table->longText('items_payload');
                $table->unsignedInteger('total_items')->default(0);
                $table->unsignedInteger('total_units_requested')->default(0);
                $table->text('remarks')->nullable();
                $table->string('demo_status', 64)->default('Draft')->index();
                $table->boolean('is_demo')->default(true);
                $table->timestamp('submitted_at')->nullable();
                $table->timestamps();
            });
        }

        // 5. Granular PMS Permissions & Least-Privilege Role Bindings
        if (Schema::hasTable('permissions') && Schema::hasTable('permission_role') && Schema::hasTable('roles')) {
            $now = now();
            $permissions = [
                ['slug' => 'pms.prescriptions.view',   'name' => 'View Prescriptions',                   'module' => 'Pharmacy'],
                ['slug' => 'pms.prescriptions.verify', 'name' => 'Verify Prescription',                  'module' => 'Pharmacy'],
                ['slug' => 'pms.dispense',             'name' => 'Dispense Medication',                  'module' => 'Pharmacy'],
                ['slug' => 'pms.pos.access',           'name' => 'Access Pharmacy POS & Process Sales',  'module' => 'Pharmacy'],
                ['slug' => 'pms.pos.discount',         'name' => 'Apply POS Discounts',                  'module' => 'Pharmacy'],
                ['slug' => 'pms.pos.refund_void',      'name' => 'Process POS Refunds & Voids',          'module' => 'Pharmacy'],
                ['slug' => 'pms.inventory.view',       'name' => 'View Pharmacy Inventory',              'module' => 'Pharmacy'],
                ['slug' => 'pms.replenishment.demo',   'name' => 'Prepare Demo Replenishment Requests',  'module' => 'Pharmacy'],
                ['slug' => 'pms.reports.view',         'name' => 'View Pharmacy Reports',                'module' => 'Pharmacy'],
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

            // Role-permission mapping:
            // - Pharmacist: full pharmacy operations, POS, inventory viewing, demo replenishment, and reports.
            // - Doctor: view prescriptions, inventory availability, and clinical pharmacy reports (no dispensing or POS).
            // - Admin: operational read-only oversight (prescriptions, inventory, reports; no clinical dispensing or POS cashier actions).
            // - Super Admin: no automatic clinical dispensing authority.
            $roleMap = [
                'pharmacist' => [
                    'pms.prescriptions.view',
                    'pms.prescriptions.verify',
                    'pms.dispense',
                    'pms.pos.access',
                    'pms.pos.discount',
                    'pms.pos.refund_void',
                    'pms.inventory.view',
                    'pms.replenishment.demo',
                    'pms.reports.view',
                ],
                'doctor' => [
                    'pms.prescriptions.view',
                    'pms.inventory.view',
                    'pms.reports.view',
                ],
                'admin' => [
                    'pms.prescriptions.view',
                    'pms.inventory.view',
                    'pms.reports.view',
                ],
            ];

            $roleIds = DB::table('roles')->whereIn('slug', array_keys($roleMap))->pluck('id', 'slug');
            $permIds = DB::table('permissions')->whereIn('slug', array_column($permissions, 'slug'))->pluck('id', 'slug');

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

