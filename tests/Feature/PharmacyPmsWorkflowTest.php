<?php

namespace Tests\Feature;

use App\Models\DispensingRecord;
use App\Models\Patient;
use App\Models\PharmacyPosTransaction;
use App\Models\PharmacyReplenishmentRequest;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Role;
use App\Models\User;
use App\Services\Pharmacy\Contracts\MedicationStockProviderInterface;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class PharmacyPmsWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    protected User $pharmacist;
    protected User $doctorA;
    protected User $doctorB;
    protected User $admin;
    protected User $radTech;
    protected Patient $patientA;
    protected Patient $patientB;

    protected static bool $pmsSchemaMigrated = false;

    protected function setUpTraits()
    {
        if (! static::$pmsSchemaMigrated) {
            $migration = include base_path('database/migrations/2026_11_08_000001_pms_pos_and_workflow_additive.php');
            $migration->up();
            static::$pmsSchemaMigrated = true;
        }

        return parent::setUpTraits();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->pharmacist = $this->createUserWithRole('pharmacist');
        $this->doctorA    = $this->createUserWithRole('doctor');
        $this->doctorB    = $this->createUserWithRole('doctor');
        $this->admin      = $this->createUserWithRole('admin');
        $this->radTech    = $this->createUserWithRole('rad-tech');

        $this->patientA = Patient::create([
            'patient_no'         => 'PAT-PMS-' . rand(10000, 99999),
            'first_name'         => 'Elena',
            'last_name'          => 'Santos',
            'date_of_birth'      => '1980-05-15',
            'gender'             => 'Female',
            'patient_type'       => 'Inpatient',
            'ward'               => 'Ward 3B',
            'bed_number'         => 'Bed 12',
            'assigned_doctor_id' => $this->doctorA->id,
        ]);

        $this->patientB = Patient::create([
            'patient_no'         => 'PAT-PMS-' . rand(10000, 99999),
            'first_name'         => 'Marco',
            'last_name'          => 'Reyes',
            'date_of_birth'      => '1975-09-20',
            'gender'             => 'Male',
            'patient_type'       => 'Outpatient',
            'assigned_doctor_id' => $this->doctorB->id,
        ]);
    }

    protected function createUserWithRole(string $roleSlug): User
    {
        $user = User::create([
            'name'        => 'Test ' . ucfirst($roleSlug) . ' ' . Str::random(4),
            'email'       => "{$roleSlug}_" . uniqid() . '@hospital.test',
            'password'    => bcrypt('password'),
            'employee_id' => 'EMP-' . rand(10000, 99999),
            'department'  => 'Pharmacy & Clinical',
        ]);
        $role = Role::where('slug', $roleSlug)->firstOrFail();
        $user->roles()->attach($role->id);

        return $user;
    }

    protected function createSamplePrescription(User $doctor, Patient $patient, string $status = 'Pending'): Prescription
    {
        $rx = Prescription::create([
            'prescription_no' => 'RX-TEST-' . strtoupper(Str::random(6)),
            'patient_id'      => $patient->id,
            'doctor_id'       => $doctor->id,
            'diagnosis'       => 'Acute Bacterial Pharyngitis',
            'notes'           => 'Take after meals.',
            'status'          => $status,
            'prescribed_at'   => now(),
        ]);

        PrescriptionItem::create([
            'prescription_id' => $rx->id,
            'medication_name' => 'Paracetamol 500mg Tablet',
            'dosage'          => '500mg',
            'frequency'       => 'Every 6 hours',
            'route'           => 'Oral',
            'duration'        => '5 days',
            'quantity'        => 10,
            'instructions'    => 'For fever',
        ]);

        return $rx->fresh(['items', 'patient', 'doctor']);
    }

    // ═══════════════════════════════════════════════════════════════
    // 1. PHARMACY DASHBOARD & SIDEBAR NAVIGATION
    // ═══════════════════════════════════════════════════════════════

    public function test_pharmacy_dashboard_scorecards_filters_and_sidebar_navigation(): void
    {
        $rx = $this->createSamplePrescription($this->doctorA, $this->patientA, 'Pending');

        $response = $this->actingAs($this->pharmacist)
            ->get(route('pharmacy.dashboard', [
                'date_from' => now()->subDays(7)->toDateString(),
                'date_to'   => now()->toDateString(),
            ]));

        $response->assertStatus(200);
        // Core scorecards & stock directory headers
        $response->assertSee('Pending Prescriptions');
        $response->assertSee('Prescriptions for Verification');
        $response->assertSee('Pending Dispensing');
        $response->assertSee('Dispensed Today');
        $response->assertSee('Sales Today');
        $response->assertSee('Pharmacy Stock Directory');
        $response->assertSee('Out-of-Stock Medicines');
        $response->assertSee('Near-Expiry Medicines');
        $response->assertDontSee('Low Stock Alert');

        // Pharmacist Sidebar required links
        $response->assertSee('Pharmacy Dashboard');
        $response->assertSee('Prescription Management');
        $response->assertSee('Point of Sale');
        $response->assertSee('Inventory Items');
        $response->assertSee('Purchase Orders');
        $response->assertSee('Inventory Counts');
        $response->assertSee('Stock Adjustments');
        $response->assertSee('Pharmacy Reports');

        // Unauthorized role blocked
        $this->actingAs($this->radTech)
            ->get(route('pharmacy.dashboard'))
            ->assertStatus(403);
    }

    // ═══════════════════════════════════════════════════════════════
    // 2. PRESCRIPTION MANAGEMENT & DISPENSING WORKFLOW
    // ═══════════════════════════════════════════════════════════════

    public function test_prescription_verification_rejection_partial_and_complete_dispensing(): void
    {
        $rx = $this->createSamplePrescription($this->doctorA, $this->patientA, 'Pending');
        $rxItem = $rx->items->first();

        // 1. Cannot dispense an unverified (Pending) prescription
        $unverifiedDispense = $this->actingAs($this->pharmacist)
            ->post(route('pharmacy.dispensing.store'), [
                'prescription_id' => $rx->id,
                'items'           => [
                    [
                        'prescription_item_id' => $rxItem->id,
                        'quantity_dispensed'   => 4,
                    ],
                ],
            ]);
        $unverifiedDispense->assertSessionHas('error');
        $this->assertEquals('Pending', $rx->fresh()->status);

        // 2. Pharmacist verifies prescription with clinical verification notes
        $verifyRes = $this->actingAs($this->pharmacist)
            ->patch(route('pharmacy.prescriptions.verify', $rx), [
                'verification_notes' => 'Dosage and renal function checked; safe to dispense.',
            ]);
        $verifyRes->assertRedirect();
        $rx->refresh();
        $this->assertEquals('Verified', $rx->status);
        $this->assertEquals('Dosage and renal function checked; safe to dispense.', $rx->verification_notes);

        // 3. Cannot over-dispense beyond prescribed quantity (10)
        $overDispense = $this->actingAs($this->pharmacist)
            ->from(route('pharmacy.dispensing.create', ['prescription_id' => $rx->id]))
            ->post(route('pharmacy.dispensing.store'), [
                'prescription_id' => $rx->id,
                'items'           => [
                    [
                        'prescription_item_id' => $rxItem->id,
                        'quantity_dispensed'   => 15,
                    ],
                ],
            ]);
        $overDispense->assertSessionHas('error');
        $this->assertEquals(0, DispensingRecord::where('prescription_item_id', $rxItem->id)->count());

        // 4. Partial dispensing (dispense 4 of 10) -> status becomes 'Partially Dispensed'
        $partialRes = $this->actingAs($this->pharmacist)
            ->post(route('pharmacy.dispensing.store'), [
                'prescription_id' => $rx->id,
                'items'           => [
                    [
                        'prescription_item_id' => $rxItem->id,
                        'quantity_dispensed'   => 4,
                        'notes'                => 'Initial inpatient dose',
                    ],
                ],
            ]);
        $partialRes->assertRedirect();
        $rx->refresh();
        $this->assertEquals('Partially Dispensed', $rx->status);
        $this->assertEquals(4, $rxItem->fresh()->dispensed_quantity);
        $this->assertEquals(6, $rxItem->fresh()->remaining_quantity);

        // 5. Complete remaining dispensing (dispense remaining 6 of 10) -> status becomes 'Dispensed'
        $completeRes = $this->actingAs($this->pharmacist)
            ->post(route('pharmacy.dispensing.store'), [
                'prescription_id' => $rx->id,
                'items'           => [
                    [
                        'prescription_item_id' => $rxItem->id,
                        'quantity_dispensed'   => 6,
                        'notes'                => 'Remaining balance dispensed',
                    ],
                ],
            ]);
        $completeRes->assertRedirect();
        $rx->refresh();
        $this->assertEquals('Dispensed', $rx->status);
        $this->assertEquals(0, $rxItem->fresh()->remaining_quantity);

        // 6. Test prescription rejection / return with reason
        $rxToReject = $this->createSamplePrescription($this->doctorA, $this->patientA, 'Pending');
        $rejectRes = $this->actingAs($this->pharmacist)
            ->patch(route('pharmacy.prescriptions.reject', $rxToReject), [
                'action'           => 'Returned',
                'rejection_reason' => 'Clarify frequency for pediatric weight adjustment.',
            ]);
        $rejectRes->assertRedirect();
        $rxToReject->refresh();
        $this->assertEquals('Returned', $rxToReject->status);
        $this->assertEquals('Clarify frequency for pediatric weight adjustment.', $rxToReject->rejection_reason);
    }

    public function test_doctor_patient_authorization_on_prescriptions(): void
    {
        $rxForPatientA = $this->createSamplePrescription($this->doctorA, $this->patientA, 'Pending');

        // Doctor A (assigned & prescribing doctor) can view
        $this->actingAs($this->doctorA)
            ->get(route('pharmacy.prescriptions.show', $rxForPatientA))
            ->assertStatus(200);

        // Doctor B (unrelated to Patient A) cannot view Patient A's prescription
        $this->actingAs($this->doctorB)
            ->get(route('pharmacy.prescriptions.show', $rxForPatientA))
            ->assertStatus(403);
    }

    // ═══════════════════════════════════════════════════════════════
    // 3. POINT OF SALE (POS) WORKFLOW
    // ═══════════════════════════════════════════════════════════════

    public function test_pos_walk_in_prescription_linked_vat_discounts_idempotency_and_void_refund(): void
    {
        // 1. Pharmacist can view POS page and filter by category/search
        $posPage = $this->actingAs($this->pharmacist)
            ->get(route('pharmacy.pos.index', ['category' => 'Best Seller', 'search' => 'Paracetamol']));
        $posPage->assertStatus(200);
        $posPage->assertSee('Point of Sale');
        $posPage->assertSee('Paracetamol 500mg Tablet');

        // 2. Unauthorized roles cannot access POS
        $this->actingAs($this->doctorA)->get(route('pharmacy.pos.index'))->assertStatus(403);
        $this->actingAs($this->admin)->get(route('pharmacy.pos.index'))->assertStatus(403);

        // 3. Walk-in sale with Senior Citizen (20% VAT-exempt) discount & idempotency protection
        $idempotencyKey = (string) Str::uuid();
        $checkoutRes = $this->actingAs($this->pharmacist)
            ->post(route('pharmacy.pos.checkout'), [
                'sale_type'          => 'walk_in',
                'customer_name'      => 'Lola Maria Cruz',
                'discount_type'      => 'senior_citizen',
                'discount_reference' => 'OSCA-2026-8891',
                'payment_method'     => 'Cash',
                'amount_tendered'    => 500.00,
                'idempotency_key'    => $idempotencyKey,
                'items'              => [
                    [
                        'medication_name' => 'Paracetamol 500mg Tablet',
                        'quantity'        => 10,
                    ],
                ],
            ]);

        $txn = PharmacyPosTransaction::where('idempotency_key', $idempotencyKey)->first();
        $this->assertNotNull($txn);
        $checkoutRes->assertRedirect(route('pharmacy.pos.receipt', $txn));
        $this->assertEquals('Completed', $txn->status);
        $this->assertEquals('senior_citizen', $txn->discount_type);
        $this->assertEquals(0.00, (float) $txn->vat_amount); // Senior Citizen is VAT-exempt
        $this->assertGreaterThan(0, (float) $txn->discount_amount);

        // 4. Duplicate payment submission with same idempotency_key is blocked
        $dupRes = $this->actingAs($this->pharmacist)
            ->post(route('pharmacy.pos.checkout'), [
                'sale_type'       => 'walk_in',
                'customer_name'   => 'Lola Maria Cruz',
                'payment_method'  => 'Cash',
                'amount_tendered' => 500.00,
                'idempotency_key' => $idempotencyKey,
                'items'           => [
                    [
                        'medication_name' => 'Paracetamol 500mg Tablet',
                        'quantity'        => 10,
                    ],
                ],
            ]);
        $dupRes->assertSessionHas('error');
        $this->assertEquals(1, PharmacyPosTransaction::where('idempotency_key', $idempotencyKey)->count());

        // 5. Prescription-linked POS sale MUST NOT automatically mark the prescription as Dispensed
        $rx = $this->createSamplePrescription($this->doctorA, $this->patientA, 'Verified');
        $rxPosRes = $this->actingAs($this->pharmacist)
            ->post(route('pharmacy.pos.checkout'), [
                'sale_type'       => 'prescription',
                'prescription_id' => $rx->id,
                'payment_method'  => 'GCash',
                'idempotency_key' => (string) Str::uuid(),
                'items'           => [
                    [
                        'medication_name'      => 'Paracetamol 500mg Tablet',
                        'quantity'             => 5,
                        'prescription_item_id' => $rx->items->first()->id,
                    ],
                ],
            ]);

        $rxTxn = PharmacyPosTransaction::where('prescription_id', $rx->id)->first();
        $this->assertNotNull($rxTxn);
        $rxPosRes->assertRedirect(route('pharmacy.pos.receipt', $rxTxn));
        // CRITICAL INVARIANT: Prescription status remains 'Verified' until clinical dispensing is performed
        $this->assertEquals('Verified', $rx->fresh()->status);

        // 6. Void / Refund a completed POS transaction preserves record and logs reason
        $voidRes = $this->actingAs($this->pharmacist)
            ->patch(route('pharmacy.pos.void-refund', $rxTxn), [
                'action'      => 'Voided',
                'void_reason' => 'Customer requested quantity correction before pickup.',
            ]);
        $voidRes->assertRedirect();
        $this->assertEquals('Voided', $rxTxn->fresh()->status);
        $this->assertEquals('Customer requested quantity correction before pickup.', $rxTxn->fresh()->void_reason);
    }

    public function test_pos_patient_type_selection_and_patient_association_without_master_mutation(): void
    {
        // 1. Inpatient transaction associated with authorized patient record
        $originalPatientA = $this->patientA->fresh()->toArray();

        $idempotencyKey = (string) Str::uuid();
        $checkoutRes = $this->actingAs($this->pharmacist)
            ->post(route('pharmacy.pos.checkout'), [
                'sale_type'       => 'walk_in',
                'patient_type'    => 'Inpatient',
                'patient_id'      => $this->patientA->id,
                'customer_name'   => $this->patientA->full_name,
                'payment_method'  => 'Hospital Billing',
                'idempotency_key' => $idempotencyKey,
                'items'           => [
                    [
                        'medication_name' => 'Paracetamol 500mg Tablet',
                        'medicine_code'   => 'MED-001',
                        'quantity'        => 5,
                    ],
                ],
            ]);

        $txn = PharmacyPosTransaction::where('idempotency_key', $idempotencyKey)->first();
        $this->assertNotNull($txn);
        $checkoutRes->assertRedirect(route('pharmacy.pos.receipt', $txn));
        $this->assertEquals($this->patientA->id, $txn->patient_id);
        $this->assertEquals('Inpatient', $txn->patient_type);
        $this->assertEquals('Hospital Billing', $txn->payment_method);

        // Verify patient master record is unchanged (read-only master boundary)
        $this->assertEquals($originalPatientA['first_name'], $this->patientA->fresh()->first_name);
        $this->assertEquals($originalPatientA['last_name'], $this->patientA->fresh()->last_name);
        $this->assertEquals($originalPatientA['ward'], $this->patientA->fresh()->ward);
        $this->assertEquals($originalPatientA['bed_number'], $this->patientA->fresh()->bed_number);

        // 2. Outpatient walk-in transaction without patient master link
        $idempotencyKeyOut = (string) Str::uuid();
        $outpatientRes = $this->actingAs($this->pharmacist)
            ->post(route('pharmacy.pos.checkout'), [
                'sale_type'       => 'walk_in',
                'patient_type'    => 'Outpatient',
                'customer_name'   => 'Walk-In Customer Juan',
                'payment_method'  => 'Cash',
                'amount_tendered' => 200.00,
                'idempotency_key' => $idempotencyKeyOut,
                'items'           => [
                    [
                        'medication_name' => 'Paracetamol 500mg Tablet',
                        'medicine_code'   => 'MED-001',
                        'quantity'        => 2,
                    ],
                ],
            ]);

        $outTxn = PharmacyPosTransaction::where('idempotency_key', $idempotencyKeyOut)->first();
        $this->assertNotNull($outTxn);
        $this->assertNull($outTxn->patient_id);
        $this->assertEquals('Outpatient', $outTxn->patient_type);
        $this->assertEquals('Walk-In Customer Juan', $outTxn->customer_name);
    }

    public function test_pos_statutory_discounts_senior_pwd_solo_parent_and_employee(): void
    {
        // 1. PWD statutory discount (RA 10754): 12% VAT exempt + 20% discount on net base
        $pwdKey = (string) Str::uuid();
        $this->actingAs($this->pharmacist)
            ->post(route('pharmacy.pos.checkout'), [
                'sale_type'          => 'walk_in',
                'customer_name'      => 'Maria Santos (PWD)',
                'discount_type'      => 'pwd',
                'discount_reference' => 'PWD-NCR-2026-0981',
                'payment_method'     => 'Cash',
                'amount_tendered'    => 1000.00,
                'idempotency_key'    => $pwdKey,
                'items'              => [
                    [
                        'medication_name' => 'Paracetamol 500mg Tablet',
                        'quantity'        => 10,
                    ],
                ],
            ]);

        $pwdTxn = PharmacyPosTransaction::where('idempotency_key', $pwdKey)->first();
        $this->assertNotNull($pwdTxn);
        $this->assertEquals('pwd', $pwdTxn->discount_type);
        $this->assertEquals('PWD-NCR-2026-0981', $pwdTxn->discount_reference);
        $this->assertEquals(0.00, (float) $pwdTxn->vat_amount); // VAT exempt
        $this->assertGreaterThan(0.00, (float) $pwdTxn->vat_exempt_sales);
        $this->assertGreaterThan(0.00, (float) $pwdTxn->discount_amount);

        // 2. Solo Parent statutory discount (RA 11861): 10% statutory discount
        $spKey = (string) Str::uuid();
        $this->actingAs($this->pharmacist)
            ->post(route('pharmacy.pos.checkout'), [
                'sale_type'          => 'walk_in',
                'customer_name'      => 'Ana Reyes (Solo Parent)',
                'discount_type'      => 'solo_parent',
                'discount_reference' => 'SP-11861-2026-44',
                'payment_method'     => 'Cash',
                'amount_tendered'    => 1000.00,
                'idempotency_key'    => $spKey,
                'items'              => [
                    [
                        'medication_name' => 'Paracetamol 500mg Tablet',
                        'quantity'        => 10,
                    ],
                ],
            ]);

        $spTxn = PharmacyPosTransaction::where('idempotency_key', $spKey)->first();
        $this->assertNotNull($spTxn);
        $this->assertEquals('solo_parent', $spTxn->discount_type);
        $this->assertEquals('SP-11861-2026-44', $spTxn->discount_reference);
        $this->assertEquals(0.1000, (float) $spTxn->discount_rate);
        $this->assertEquals(8.50, (float) $spTxn->discount_amount); // 10 x 8.50 = 85.00 * 10% = 8.50
        $this->assertEquals(76.50, (float) $spTxn->grand_total);
    }

    public function test_pos_edit_order_item_variant_and_overselling_prevention(): void
    {
        // 1. Sell dosage variant (MED-007B: Losec 40mg vs MED-007: Losec 20mg)
        $variantKey = (string) Str::uuid();
        $res = $this->actingAs($this->pharmacist)
            ->post(route('pharmacy.pos.checkout'), [
                'sale_type'       => 'walk_in',
                'customer_name'   => 'Variant Test Customer',
                'payment_method'  => 'Cash',
                'amount_tendered' => 500.00,
                'idempotency_key' => $variantKey,
                'items'           => [
                    [
                        'medication_name' => 'Losec (Omeprazole 40mg)',
                        'medicine_code'   => 'MED-007B',
                        'quantity'        => 2,
                    ],
                ],
            ]);

        $txn = PharmacyPosTransaction::where('idempotency_key', $variantKey)->first();
        $this->assertNotNull($txn);
        $item = $txn->items->first();
        $this->assertEquals('MED-007B', $item->medicine_code);
        $this->assertEquals(55.00, (float) $item->unit_price);
        $this->assertEquals(110.00, (float) $item->line_subtotal);

        // 2. Overselling beyond available stock is blocked
        // Losec 40mg has available_quantity = 25
        $oversellRes = $this->actingAs($this->pharmacist)
            ->post(route('pharmacy.pos.checkout'), [
                'sale_type'       => 'walk_in',
                'customer_name'   => 'Oversell Test',
                'payment_method'  => 'Cash',
                'idempotency_key' => (string) Str::uuid(),
                'items'           => [
                    [
                        'medication_name' => 'Losec (Omeprazole 40mg)',
                        'medicine_code'   => 'MED-007B',
                        'quantity'        => 100, // exceeds 25
                    ],
                ],
            ]);

        $oversellRes->assertSessionHas('error');

        // 3. Purchasing an out-of-stock item is blocked (Cefalexin MED-018 has 0pcs stock)
        $outOfStockRes = $this->actingAs($this->pharmacist)
            ->post(route('pharmacy.pos.checkout'), [
                'sale_type'       => 'walk_in',
                'customer_name'   => 'Out of Stock Test',
                'payment_method'  => 'Cash',
                'idempotency_key' => (string) Str::uuid(),
                'items'           => [
                    [
                        'medication_name' => 'Cefalexin 500mg Capsule',
                        'medicine_code'   => 'MED-018',
                        'quantity'        => 1,
                    ],
                ],
            ]);

        $outOfStockRes->assertSessionHas('error');
    }

    // ═══════════════════════════════════════════════════════════════
    // 4. INVENTORY & STATIC DEMO LOW-STOCK REPLENISHMENT
    // ═══════════════════════════════════════════════════════════════

    public function test_inventory_pages_and_low_stock_replenishment_static_demo_workflow(): void
    {
        // 1. Pharmacist can view all 4 Inventory sub-pages
        $this->actingAs($this->pharmacist)->get(route('pharmacy.inventory.items'))->assertStatus(200)->assertSee('Inventory Items');
        $this->actingAs($this->pharmacist)->get(route('pharmacy.inventory.purchase-orders'))->assertStatus(200)->assertSee('Purchase Orders');
        $this->actingAs($this->pharmacist)->get(route('pharmacy.inventory.counts'))->assertStatus(200)->assertSee('Inventory Counts');
        $this->actingAs($this->pharmacist)->get(route('pharmacy.inventory.adjustments'))->assertStatus(200)->assertSee('Stock Adjustments');

        // 2. Capture initial stock quantities from MedicationStockProviderInterface
        /** @var MedicationStockProviderInterface $stockProvider */
        $stockProvider = app(MedicationStockProviderInterface::class);
        $beforeStock = collect($stockProvider->getAllMedicationStock())->pluck('availableQuantity', 'medicationName')->all();

        // 3. View Replenishment Demo page — verify required demo banners
        $demoPage = $this->actingAs($this->pharmacist)->get(route('pharmacy.inventory.replenishment.index'));
        $demoPage->assertStatus(200);
        $demoPage->assertSee('Demo / Not Transmitted');
        $demoPage->assertSee('Static Demonstration Only');

        // 4. Submit a simulated low-stock replenishment request
        $storeRes = $this->actingAs($this->pharmacist)
            ->post(route('pharmacy.inventory.replenishment.store'), [
                'requesting_department' => 'Main Hospital Pharmacy (DTCS-PHARM-MAIN)',
                'priority'              => 'Urgent',
                'status'                => 'Submitted (Demo)',
                'notes'                 => 'Simulated demo restock for low-stock respiratory and analgesic items.',
                'items'                 => [
                    [
                        'medication_name'    => 'Solmoux 500mg Capsule',
                        'medicine_code'      => 'MED-002',
                        'current_stock'      => 15,
                        'reorder_level'      => 20,
                        'requested_quantity' => 100,
                        'unit'               => 'capsule',
                    ],
                ],
            ]);
        $storeRes->assertRedirect(route('pharmacy.inventory.replenishment.index'));

        $demoReq = PharmacyReplenishmentRequest::latest('id')->first();
        $this->assertNotNull($demoReq);
        $this->assertTrue($demoReq->is_demo);
        $this->assertEquals('Submitted (Demo)', $demoReq->status);

        // 5. Transition simulated status to 'Acknowledged (Demo)'
        $statusRes = $this->actingAs($this->pharmacist)
            ->patch(route('pharmacy.inventory.replenishment.status', $demoReq), [
                'status' => 'Acknowledged (Demo)',
            ]);
        $statusRes->assertRedirect();
        $this->assertEquals('Acknowledged (Demo)', $demoReq->fresh()->status);

        // 6. Verify actual inventory stock quantities were NOT modified by the demo request
        $afterStock = collect($stockProvider->getAllMedicationStock())->pluck('availableQuantity', 'medicationName')->all();
        $this->assertEquals($beforeStock, $afterStock);
    }

    // ═══════════════════════════════════════════════════════════════
    // 5. PHARMACY REPORTS
    // ═══════════════════════════════════════════════════════════════

    public function test_pharmacy_reports_financial_summary_discounts_voids_and_role_access(): void
    {
        // Create a POS sale so Financial Summary has live numbers
        $this->actingAs($this->pharmacist)->post(route('pharmacy.pos.checkout'), [
            'sale_type'       => 'walk_in',
            'customer_name'   => 'Report Test Customer',
            'discount_type'   => 'employee',
            'payment_method'  => 'Card',
            'idempotency_key' => (string) Str::uuid(),
            'items'           => [
                [
                    'medication_name' => 'Paracetamol 500mg Tablet',
                    'quantity'        => 4,
                ],
            ],
        ]);

        $reportRes = $this->actingAs($this->pharmacist)
            ->get(route('reports.pharmacy.activity', [
                'date_from' => now()->startOfMonth()->toDateString(),
                'date_to'   => now()->toDateString(),
            ]));

        $reportRes->assertStatus(200);
        $reportRes->assertSee('Financial Summary (Pharmacy POS)');
        $reportRes->assertSee('Gross Sales');
        $reportRes->assertSee('Net Sales (Completed Revenue)');
        $reportRes->assertSee('VAT Collected');
        $reportRes->assertSee('Payment Method Breakdown');
        $reportRes->assertSee('Sales by Medicine');
        $reportRes->assertSee('Sales by Pharmacist');
        $reportRes->assertSee('Discount Report');
        $reportRes->assertSee('Refund & Void Report');
        $reportRes->assertSee('Inventory & Medicine Expiry Reports');

        // Unauthorized role cannot access pharmacy reports
        $this->actingAs($this->radTech)
            ->get(route('reports.pharmacy.activity'))
            ->assertStatus(403);
    }
}
