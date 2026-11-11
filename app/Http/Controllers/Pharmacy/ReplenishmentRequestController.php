<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\PharmacyReplenishmentRequest;
use App\Models\User;
use App\Services\Pharmacy\Contracts\MedicationStockProviderInterface;
use App\Services\Pharmacy\DTOs\MedicationStockData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * ReplenishmentRequestController — Static Demonstration Feature for Low-Stock Medicine Replenishment.
 *
 * IMPORTANT ARCHITECTURAL INVARIANT:
 * - This controller is a STATIC DEMO / SIMULATED REQUEST workflow only.
 * - It NEVER connects to an external API or third-party procurement system.
 * - It NEVER creates real Purchase Orders or modifies actual inventory stock quantities.
 */
class ReplenishmentRequestController extends Controller
{
    public const DEPARTMENTS = [
        'Main Hospital Pharmacy (DTCS-PHARM-MAIN)',
        'Inpatient Clinical Pharmacy',
        'Outpatient & POS Pharmacy',
        'Emergency Satellite Pharmacy',
    ];

    public function __construct(
        protected MedicationStockProviderInterface $stockProvider
    ) {}

    public function index(Request $request): View
    {
        /** @var User|null $user */
        $user = Auth::user();
        abort_if(
            ! $user || (! $user->hasAnyRole(['pharmacist', 'admin', 'super_admin']) && ! $user->hasPermission('pms.replenishment.demo')),
            403,
            'Unauthorized access to Low-Stock Replenishment Demo.'
        );

        $rawStock = $this->stockProvider->getAllMedicationStock();
        $todayTs = strtotime(date('Y-m-d'));

        $catalog = collect($rawStock)->map(function (MedicationStockData $item, int $idx) use ($todayTs) {
            $qty = $item->availableQuantity;
            $reorderLevel = $item->reorderLevel ?: 20;
            $expTs = ! empty($item->expiryDate) ? strtotime($item->expiryDate) : null;
            $isExpired = ! $expTs || $expTs <= $todayTs;

            $isLowOrOut = $qty <= $reorderLevel;

            if ($qty <= 0) {
                $statusLabel = 'Out of Stock';
                $statusKey   = 'out';
            } elseif ($qty <= $reorderLevel) {
                $statusLabel = 'Low Stock';
                $statusKey   = 'low';
            } else {
                $statusLabel = 'In Stock';
                $statusKey   = 'in_stock';
            }

            $suggestedOrderQty = max(50, ($reorderLevel * 3) - max(0, $qty));

            return [
                'code'                => $item->code ?: ('MED-' . str_pad((string) ($idx + 1), 3, '0', STR_PAD_LEFT)),
                'medication_name'     => $item->medicationName,
                'generic_name'        => $item->genericName ?: $item->medicationName,
                'brand_name'          => $item->brandName ?: 'Generic',
                'strength'            => $item->strength ?: 'Standard',
                'dosage_form'         => $item->dosageForm ?: 'Tablet',
                'unit'                => $item->unit ?: 'unit',
                'available_quantity'  => $qty,
                'reorder_level'       => $reorderLevel,
                'lot_number'          => $item->lotNumber ?? 'N/A',
                'expiry_date'         => $item->expiryDate,
                'is_expired'          => $isExpired,
                'is_low_or_out'       => $isLowOrOut,
                'status_key'          => $statusKey,
                'status_label'        => $statusLabel,
                'suggested_order_qty' => $suggestedOrderQty,
            ];
        })->values();

        $lowStockMedicines = $catalog->where('is_low_or_out', true)->values();

        $requests = PharmacyReplenishmentRequest::with('requestedBy')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $departments = self::DEPARTMENTS;
        $demoStatuses = PharmacyReplenishmentRequest::DEMO_STATUSES;

        return view('pharmacy.inventory.replenishment', compact(
            'catalog',
            'lowStockMedicines',
            'requests',
            'departments',
            'demoStatuses'
        ));
    }

    /**
     * Store a simulated (static demo) replenishment request.
     * NEVER transmits externally, NEVER creates real POs, NEVER alters stock quantities.
     */
    public function store(Request $request): RedirectResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        abort_if(
            ! $user || ! $user->hasRole('pharmacist') || ! $user->hasPermission('pms.replenishment.demo'),
            403,
            'Only authorized pharmacists can create Demo Replenishment Requests.'
        );

        $validated = $request->validate([
            'requesting_department'        => 'required|string|max:128',
            'priority'                     => 'required|in:Normal,High,Urgent',
            'status'                       => ['nullable', Rule::in(PharmacyReplenishmentRequest::DEMO_STATUSES)],
            'notes'                        => 'nullable|string|max:1000',
            'items'                        => 'required|array|min:1',
            'items.*.medication_name'      => 'required|string|max:255',
            'items.*.requested_quantity'   => 'required|integer|min:1|max:50000',
            'items.*.medicine_code'        => 'nullable|string|max:64',
            'items.*.current_stock'        => 'nullable|integer|min:0',
            'items.*.reorder_level'        => 'nullable|integer|min:0',
            'items.*.unit'                 => 'nullable|string|max:32',
        ], [
            'items.required' => 'Please select at least one low-stock medicine to include in the demo replenishment request.',
            'items.min'      => 'Please select at least one low-stock medicine to include in the demo replenishment request.',
        ]);

        // Enrich items from the stock catalog for display accuracy without mutating stock
        $stockCatalog = collect($this->stockProvider->getAllMedicationStock());

        $normalizedItems = collect($validated['items'])->map(function (array $row) use ($stockCatalog) {
            $name = trim($row['medication_name']);
            /** @var MedicationStockData|null $matched */
            $matched = $stockCatalog->first(fn (MedicationStockData $s) => strcasecmp($s->medicationName, $name) === 0);

            return [
                'medicine_code'      => $row['medicine_code'] ?? ($matched?->code ?: 'MED-DEMO'),
                'medication_name'    => $matched?->medicationName ?? $name,
                'current_stock'      => isset($row['current_stock']) ? (int) $row['current_stock'] : ($matched?->availableQuantity ?? 0),
                'reorder_level'      => isset($row['reorder_level']) ? (int) $row['reorder_level'] : ($matched?->reorderLevel ?? 20),
                'requested_quantity' => (int) $row['requested_quantity'],
                'unit'               => $row['unit'] ?? ($matched?->unit ?: 'unit'),
            ];
        })->values()->all();

        $seq = PharmacyReplenishmentRequest::count() + 1;
        $reqNo = 'REP-DEMO-' . date('Y') . '-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
        $targetStatus = $validated['status'] ?? PharmacyReplenishmentRequest::STATUS_SUBMITTED_DEMO;
        $totalUnits = collect($normalizedItems)->sum('requested_quantity');

        $demoRequest = PharmacyReplenishmentRequest::create([
            'request_no'            => $reqNo,
            'pharmacist_id'         => $user->id,
            'requesting_department' => $validated['requesting_department'],
            'target_subsystem'      => 'Inventory / Supply Chain Subsystem (Demo Boundary)',
            'items_payload'         => $normalizedItems,
            'total_items'           => count($normalizedItems),
            'total_units_requested' => $totalUnits,
            'remarks'               => $validated['notes'] ?? null,
            'demo_status'           => $targetStatus,
            'is_demo'               => true,
            'submitted_at'          => $targetStatus === PharmacyReplenishmentRequest::STATUS_DRAFT ? null : now(),
        ]);

        ActivityLog::create([
            'user_id'       => $user->id,
            'action'        => 'Demo Replenishment Request Created',
            'module'        => 'Pharmacy',
            'description'   => "Simulated Low-Stock Replenishment Request [{$demoRequest->request_no}] ({$demoRequest->status}) saved for static demonstration only (Not Transmitted; stock unchanged).",
            'loggable_type' => PharmacyReplenishmentRequest::class,
            'loggable_id'   => $demoRequest->id,
            'ip_address'    => request()->ip(),
            'logged_at'     => now(),
        ]);

        return redirect()->route('pharmacy.inventory.replenishment.index')
            ->with('success', "Static Demo Replenishment Request [{$demoRequest->request_no}] saved with status '{$demoRequest->status}' (Demo / Not Transmitted — no external PO created and stock quantities unchanged).");
    }

    /**
     * Update the simulated status of a demo replenishment request.
     */
    public function updateDemoStatus(Request $request, PharmacyReplenishmentRequest $replenishmentRequest): RedirectResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        abort_if(
            ! $user || ! $user->hasRole('pharmacist') || ! $user->hasPermission('pms.replenishment.demo'),
            403,
            'Only authorized pharmacists can update Demo Replenishment Request statuses.'
        );

        $validated = $request->validate([
            'status' => ['required', Rule::in(PharmacyReplenishmentRequest::DEMO_STATUSES)],
        ]);

        $replenishmentRequest->update([
            'demo_status'  => $validated['status'],
            'submitted_at' => $replenishmentRequest->submitted_at ?? now(),
        ]);

        return back()->with('success', "Demo Request [{$replenishmentRequest->request_no}] status updated to '{$validated['status']}' (Static Demonstration Only).");
    }
}
