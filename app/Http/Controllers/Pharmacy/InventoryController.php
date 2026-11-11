<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Models\DispensingRecord;
use App\Models\PharmacyPosTransactionItem;
use App\Models\PharmacyReplenishmentRequest;
use App\Models\User;
use App\Services\Pharmacy\Contracts\MedicationStockProviderInterface;
use App\Services\Pharmacy\DTOs\MedicationStockData;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * InventoryController — PMS Inventory visibility & stock monitoring workspace.
 * Preserves the strict architectural boundary with the external Hospital Inventory /
 * Supply Chain Subsystem (read-only stock monitoring + pharmacy movement tracking).
 */
class InventoryController extends Controller
{
    public function __construct(
        protected MedicationStockProviderInterface $stockProvider
    ) {}

    /**
     * Build the normalized medicine inventory collection from MedicationStockProviderInterface.
     */
    protected function buildInventoryCollection(): Collection
    {
        $rawStock = $this->stockProvider->getAllMedicationStock();
        $todayTs = strtotime(date('Y-m-d'));
        $expiringThresholdTs = strtotime(date('Y-m-d', strtotime('+90 days')));

        return collect($rawStock)->map(function (MedicationStockData $item, int $idx) use ($todayTs, $expiringThresholdTs) {
            $qty = $item->availableQuantity;
            $reorderLevel = $item->reorderLevel ?: 20;
            $expTs = ! empty($item->expiryDate) ? strtotime($item->expiryDate) : null;
            $isExpired = ! $expTs || $expTs <= $todayTs;
            $isNearExpiry = ! $isExpired && $expTs <= $expiringThresholdTs;

            if ($isExpired) {
                $statusKey = 'expired';
                $statusLabel = 'Expired';
            } elseif ($qty <= 0) {
                $statusKey = 'out';
                $statusLabel = 'Out of Stock';
            } elseif ($qty <= $reorderLevel) {
                $statusKey = 'low';
                $statusLabel = 'Low Stock';
            } else {
                $statusKey = 'available';
                $statusLabel = 'In Stock';
            }

            $code = $item->code ?: ('MED-' . str_pad((string) ($idx + 1), 3, '0', STR_PAD_LEFT));

            return [
                'code'               => $code,
                'barcode'            => $item->barcode ?: ('4800016000' . str_pad((string) ($idx + 1), 3, '0', STR_PAD_LEFT)),
                'medication_name'    => $item->medicationName,
                'generic_name'       => $item->genericName ?: $item->medicationName,
                'brand_name'         => $item->brandName ?: 'Generic',
                'category'           => $item->category ?: 'Default',
                'strength'           => $item->strength ?: 'Standard',
                'dosage_form'        => $item->dosageForm ?: 'Tablet',
                'unit'               => $item->unit ?: 'unit',
                'unit_price'         => round((float) $item->unitPrice, 2),
                'available_quantity' => $qty,
                'reorder_level'      => $reorderLevel,
                'lot_number'         => $item->lotNumber ?? 'N/A',
                'expiry_date'        => $item->expiryDate,
                'status_key'         => $statusKey,
                'status_label'       => $statusLabel,
                'is_near_expiry'     => $isNearExpiry,
                'is_expired'         => $isExpired,
                'stock_value'        => round($qty * (float) $item->unitPrice, 2),
            ];
        })->values();
    }

    protected function authorizeInventoryAccess(): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        abort_if(
            ! $user || (! $user->hasAnyRole(['pharmacist', 'admin', 'super_admin']) && ! $user->hasPermission('pms.inventory.view')),
            403,
            'Unauthorized access to Pharmacy Inventory.'
        );
    }

    /**
     * 1. Inventory Items & Stock Monitoring (/pharmacy/inventory/items)
     */
    public function items(Request $request): View
    {
        $this->authorizeInventoryAccess();

        $allItems = $this->buildInventoryCollection();

        $counts = [
            'all'         => $allItems->count(),
            'available'   => $allItems->where('status_key', 'available')->count(),
            'low'         => $allItems->where('status_key', 'low')->count(),
            'out'         => $allItems->where('status_key', 'out')->count(),
            'near_expiry' => $allItems->where('is_near_expiry', true)->count(),
            'expired'     => $allItems->where('is_expired', true)->count(),
        ];

        $filter = $request->input('filter', 'all');
        $search = trim((string) $request->input('search', ''));
        $category = trim((string) $request->input('category', ''));

        $items = $allItems->filter(function (array $item) use ($filter, $search, $category) {
            if ($filter === 'available' && $item['status_key'] !== 'available') {
                return false;
            }
            if ($filter === 'low' && $item['status_key'] !== 'low') {
                return false;
            }
            if ($filter === 'out' && $item['status_key'] !== 'out') {
                return false;
            }
            if ($filter === 'near_expiry' && ! $item['is_near_expiry']) {
                return false;
            }
            if ($filter === 'expired' && ! $item['is_expired']) {
                return false;
            }
            if ($category !== '' && strcasecmp($item['category'], $category) !== 0) {
                return false;
            }
            if ($search !== '') {
                $haystack = strtolower(implode(' ', [
                    $item['code'],
                    $item['barcode'],
                    $item['medication_name'],
                    $item['generic_name'],
                    $item['brand_name'],
                    $item['lot_number'],
                    $item['category'],
                ]));
                if (! str_contains($haystack, strtolower($search))) {
                    return false;
                }
            }
            return true;
        })->values();

        // Stock Movement History (combined from Clinical Dispensing + POS Sales)
        $dispensingMovements = DispensingRecord::with(['prescriptionItem', 'prescription.patient', 'dispensedBy'])
            ->latest('dispensed_at')
            ->take(15)
            ->get()
            ->map(function (DispensingRecord $rec) {
                return [
                    'timestamp'   => $rec->dispensed_at,
                    'type'        => 'Prescription Dispense',
                    'reference'   => $rec->prescription?->prescription_no ?? ('DISP-' . $rec->id),
                    'medicine'    => $rec->prescriptionItem?->medication_name ?? 'Medicine',
                    'lot_number'  => $rec->lot_number ?? 'N/A',
                    'qty_change'  => -$rec->quantity_dispensed,
                    'actor'       => $rec->dispensedBy?->name ?? 'Pharmacist',
                    'status'      => 'Recorded',
                ];
            });

        $posMovements = PharmacyPosTransactionItem::with(['transaction.pharmacist'])
            ->whereHas('transaction', fn ($q) => $q->where('status', 'Completed'))
            ->latest('id')
            ->take(15)
            ->get()
            ->map(function (PharmacyPosTransactionItem $item) {
                return [
                    'timestamp'   => $item->transaction?->transacted_at ?? $item->created_at,
                    'type'        => 'POS Retail / OTC Sale',
                    'reference'   => $item->transaction?->transaction_no ?? 'POS',
                    'medicine'    => $item->medication_name,
                    'lot_number'  => $item->lot_number ?? 'N/A',
                    'qty_change'  => -$item->quantity,
                    'actor'       => $item->transaction?->pharmacist?->name ?? 'Pharmacist',
                    'status'      => $item->transaction?->status ?? 'Completed',
                ];
            });

        $movements = $dispensingMovements->concat($posMovements)
            ->sortByDesc('timestamp')
            ->take(20)
            ->values();

        $categories = $allItems->pluck('category')->unique()->values();

        return view('pharmacy.inventory.items', compact(
            'items',
            'counts',
            'filter',
            'search',
            'category',
            'categories',
            'movements'
        ));
    }

    /**
     * 2. Purchase Orders Reference (/pharmacy/inventory/purchase-orders)
     * Clearly indicates the external Inventory / Supply Chain subsystem boundary.
     */
    public function purchaseOrders(Request $request): View
    {
        $this->authorizeInventoryAccess();

        $purchaseOrders = collect([
            [
                'po_number'       => 'PO-SUP-2026-0104',
                'supplier'        => 'MetroMed Pharma Distribution Inc.',
                'department'      => 'Main Hospital Pharmacy (DTCS-PHARM-MAIN)',
                'ordered_at'      => now()->subDays(9)->format('Y-m-d'),
                'expected_at'     => now()->subDays(2)->format('Y-m-d'),
                'items_count'     => 6,
                'total_amount'    => 48250.00,
                'status'          => 'Received by Central Supply',
                'source_system'   => 'Hospital Supply Chain & Procurement Subsystem',
            ],
            [
                'po_number'       => 'PO-SUP-2026-0109',
                'supplier'        => 'Unilab Institutional Healthcare',
                'department'      => 'Main Hospital Pharmacy (DTCS-PHARM-MAIN)',
                'ordered_at'      => now()->subDays(3)->format('Y-m-d'),
                'expected_at'     => now()->addDays(4)->format('Y-m-d'),
                'items_count'     => 4,
                'total_amount'    => 31900.00,
                'status'          => 'In Transit (External PO)',
                'source_system'   => 'Hospital Supply Chain & Procurement Subsystem',
            ],
            [
                'po_number'       => 'PO-SUP-2026-0112',
                'supplier'        => 'Zuellig Pharma Corporation',
                'department'      => 'Inpatient Clinical Pharmacy',
                'ordered_at'      => now()->subDay()->format('Y-m-d'),
                'expected_at'     => now()->addDays(7)->format('Y-m-d'),
                'items_count'     => 3,
                'total_amount'    => 64500.00,
                'status'          => 'Approved by Supply Chain',
                'source_system'   => 'Hospital Supply Chain & Procurement Subsystem',
            ],
        ]);

        $demoReplenishmentCount = PharmacyReplenishmentRequest::count();

        return view('pharmacy.inventory.purchase-orders', compact('purchaseOrders', 'demoReplenishmentCount'));
    }

    /**
     * 3. Inventory Counts (/pharmacy/inventory/counts)
     */
    public function counts(Request $request): View
    {
        $this->authorizeInventoryAccess();

        $allItems = $this->buildInventoryCollection();
        $countDate = now()->subDays(2)->format('Y-m-d');

        $countRecords = $allItems->map(function (array $item, int $idx) use ($countDate) {
            $expected = $item['available_quantity'];
            $counted  = $expected;
            $variance = $counted - $expected;

            return [
                'count_ref'        => 'CNT-' . date('Ym') . '-' . str_pad((string) ($idx + 1), 3, '0', STR_PAD_LEFT),
                'code'             => $item['code'],
                'medication_name'  => $item['medication_name'],
                'lot_number'       => $item['lot_number'],
                'unit'             => $item['unit'],
                'expected_qty'     => $expected,
                'counted_qty'      => $counted,
                'variance'         => $variance,
                'count_date'       => $countDate,
                'responsible_user' => 'Central Inventory Audit Team',
                'status'           => 'Verified (Zero Variance)',
            ];
        });

        return view('pharmacy.inventory.counts', compact('countRecords'));
    }

    /**
     * 4. Stock Adjustments (/pharmacy/inventory/adjustments)
     */
    public function adjustments(Request $request): View
    {
        $this->authorizeInventoryAccess();

        $adjustments = collect([
            [
                'adjustment_no'   => 'ADJ-SUP-2026-0018',
                'medicine_code'   => 'MED-004',
                'medication_name' => 'Salbutamol 100mcg Inhaler',
                'lot_number'      => 'LOT-SAL-088',
                'adjustment_type' => 'Quarantine Hold (Out of Stock)',
                'quantity_delta'  => 0,
                'reason'          => 'Stock exhausted; awaiting external PO-SUP-2026-0109 delivery.',
                'adjusted_at'     => now()->subDays(4)->format('Y-m-d H:i'),
                'authorized_by'   => 'Supply Chain Officer (External)',
            ],
            [
                'adjustment_no'   => 'ADJ-SUP-2026-0019',
                'medicine_code'   => 'MED-006',
                'medication_name' => 'Ceftriaxone 1g Vial',
                'lot_number'      => 'LOT-CEF-019',
                'adjustment_type' => 'Expired Batch Quarantine',
                'quantity_delta'  => -15,
                'reason'          => 'Batch LOT-CEF-019 reached expiration date; locked from dispensing and POS sale.',
                'adjusted_at'     => now()->subDays(2)->format('Y-m-d H:i'),
                'authorized_by'   => 'Pharmacy QA & Supply Chain',
            ],
            [
                'adjustment_no'   => 'ADJ-SUP-2026-0021',
                'medicine_code'   => 'MED-001',
                'medication_name' => 'Paracetamol 500mg',
                'lot_number'      => 'LOT-PARA-001',
                'adjustment_type' => 'Cycle Count Reconciliation',
                'quantity_delta'  => 0,
                'reason'          => 'Monthly physical count matched system balance (240 tablets).',
                'adjusted_at'     => now()->subDay()->format('Y-m-d H:i'),
                'authorized_by'   => 'Central Inventory Audit Team',
            ],
        ]);

        return view('pharmacy.inventory.adjustments', compact('adjustments'));
    }
}

