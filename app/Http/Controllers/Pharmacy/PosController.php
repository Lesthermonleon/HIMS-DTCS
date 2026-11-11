<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\FinancialRule;
use App\Models\Patient;
use App\Models\PharmacyPosTransaction;
use App\Models\PharmacyPosTransactionItem;
use App\Models\Prescription;
use App\Models\User;
use App\Services\Finance\MoneyMath;
use App\Services\Pharmacy\Contracts\MedicationStockProviderInterface;
use App\Services\Pharmacy\DTOs\MedicationStockData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * PosController — Pharmacist-integrated Point of Sale (POS) controller.
 * Enforces server-side pricing, VAT/discount calculation, idempotency,
 * and strict separation between payment transactions and clinical dispensing.
 */
class PosController extends Controller
{
    public const CATEGORIES = [
        'All Items',
        'Default',
        'Best Seller',
        'Others / Special',
        'Vitamins & Supplements',
        'Gastro / Stomach',
        'Allergy',
        'Cough & Cold',
    ];

    public const DISCOUNT_RATES = [
        'none'             => 0.0000,
        'senior'           => 0.2000,
        'senior_citizen'   => 0.2000,
        'senior_5'         => 0.0500,
        'pwd'              => 0.2000,
        'pwd_5'            => 0.0500,
        'athlete'          => 0.2000,
        'national_athlete' => 0.2000,
        'solo_parent'      => 0.1000,
        'employee'         => 0.1000,
        'promotional'      => 0.0500,
        'custom_percentage'=> 0.0500,
        'custom_amount'    => 0.0000,
    ];

    public function __construct(
        protected MedicationStockProviderInterface $stockProvider
    ) {}

    public function index(Request $request): View
    {
        /** @var User|null $user */
        $user = Auth::user();
        abort_if(! $user || ! $user->hasRole('pharmacist'), 403, 'Only authorized pharmacists can access the Pharmacy POS.');

        $rawStock = $this->stockProvider->getAllMedicationStock();
        $todayTs  = strtotime(date('Y-m-d'));
        $expiringThresholdTs = strtotime(date('Y-m-d', strtotime('+90 days')));

        $catalog = collect($rawStock)->map(function (MedicationStockData $item, int $idx) use ($todayTs, $expiringThresholdTs) {
            $qty = $item->availableQuantity;
            $expTs = ! empty($item->expiryDate) ? strtotime($item->expiryDate) : null;
            $isExpired = ! $expTs || $expTs <= $todayTs;
            $isExpiringSoon = ! $isExpired && $expTs <= $expiringThresholdTs;

            if ($isExpired) {
                $stockKey   = 'expired';
                $stockLabel = 'Expired';
                $canSell    = false;
            } elseif ($qty <= 0) {
                $stockKey   = 'out';
                $stockLabel = 'Out of Stock';
                $canSell    = false;
            } elseif ($qty <= $item->reorderLevel) {
                $stockKey   = 'low';
                $stockLabel = 'Low Stock';
                $canSell    = true;
            } else {
                $stockKey   = 'in_stock';
                $stockLabel = 'In Stock';
                $canSell    = true;
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
                'reorder_level'      => $item->reorderLevel ?: 20,
                'lot_number'         => $item->lotNumber,
                'expiry_date'        => $item->expiryDate,
                'stock_status_key'   => $stockKey,
                'stock_status_label' => $stockLabel,
                'is_expiring_soon'   => $isExpiringSoon,
                'is_expired'         => $isExpired,
                'can_sell'           => $canSell,
            ];
        })->values();

        // Filter catalog when server-side query params are passed
        $selectedCategory = $request->input('category', 'All Items');
        $searchQuery      = trim((string) $request->input('search', ''));

        $filteredCatalog = $catalog->filter(function (array $med) use ($selectedCategory, $searchQuery) {
            if ($selectedCategory && $selectedCategory !== 'All Items' && strcasecmp($med['category'], $selectedCategory) !== 0) {
                return false;
            }
            if ($searchQuery !== '') {
                $haystack = strtolower(implode(' ', [
                    $med['code'],
                    $med['barcode'],
                    $med['medication_name'],
                    $med['generic_name'],
                    $med['brand_name'],
                    $med['category'],
                ]));
                if (! str_contains($haystack, strtolower($searchQuery))) {
                    return false;
                }
            }
            return true;
        })->values();

        // Active prescriptions eligible for prescription-linked POS billing
        $prescriptions = Prescription::with(['patient', 'doctor', 'items'])
            ->whereIn('status', ['Verified', 'Partially Dispensed', 'Dispensed', 'Pending'])
            ->latest()
            ->take(50)
            ->get();

        $linkedPrescription = null;
        if ($rxId = $request->input('prescription_id')) {
            $linkedPrescription = Prescription::with(['patient', 'doctor', 'items'])->find($rxId);
        }

        // Financial rule (VAT rate & currency)
        $activeRule = FinancialRule::where('effective_from', '<=', now()->toDateString())
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();

        $vatRate = $activeRule && (float) $activeRule->tax_rate > 0
            ? (float) $activeRule->tax_rate
            : 0.1200;

        // Recent POS transactions for the Orders & Shift modals
        $recentTransactions = PharmacyPosTransaction::with(['patient', 'prescription', 'pharmacist', 'items'])
            ->latest('transacted_at')
            ->take(20)
            ->get();

        $todayStr = now()->toDateString();
        $shiftQuery = PharmacyPosTransaction::where('pharmacist_id', $user->id)
            ->whereDate('transacted_at', $todayStr);

        $shiftStats = [
            'completed_count' => (clone $shiftQuery)->where('status', 'Completed')->count(),
            'voided_count'    => (clone $shiftQuery)->whereIn('status', ['Voided', 'Refunded'])->count(),
            'net_sales'       => (float) (clone $shiftQuery)->where('status', 'Completed')->sum('grand_total'),
            'discounts_given' => (float) (clone $shiftQuery)->where('status', 'Completed')->sum('discount_amount'),
            'vat_collected'   => (float) (clone $shiftQuery)->where('status', 'Completed')->sum('vat_amount'),
        ];

        $patients = Patient::orderBy('last_name')
            ->select('id', 'patient_no', 'first_name', 'last_name', 'patient_type', 'ward', 'bed_number')
            ->get();

        $categories     = self::CATEGORIES;
        $idempotencyKey = (string) Str::uuid();

        return view('pharmacy.pos.index', compact(
            'catalog',
            'filteredCatalog',
            'categories',
            'selectedCategory',
            'searchQuery',
            'prescriptions',
            'linkedPrescription',
            'patients',
            'vatRate',
            'activeRule',
            'recentTransactions',
            'shiftStats',
            'idempotencyKey'
        ));
    }

    /**
     * Process a POS sale with strict server-side validation, pricing, VAT, and discount calculation.
     * IMPORTANT: Completing a payment NEVER changes a prescription's clinical or dispensing status.
     */
    public function checkout(Request $request): RedirectResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        abort_if(! $user || ! $user->hasRole('pharmacist'), 403, 'Only authorized pharmacists can process POS transactions.');

        $validated = $request->validate([
            'sale_type'          => 'required|in:walk_in,prescription',
            'patient_type'       => 'nullable|in:Outpatient,Inpatient,outpatient,inpatient',
            'prescription_id'    => 'nullable|exists:prescriptions,id',
            'patient_id'         => 'nullable|exists:patients,id',
            'customer_name'      => 'nullable|string|max:255',
            'discount_type'      => 'nullable|in:none,senior,senior_citizen,senior_5,pwd,pwd_5,athlete,national_athlete,solo_parent,employee,promotional,custom_percentage,custom_amount',
            'custom_discount_rate'   => 'nullable|numeric|min:0|max:100',
            'custom_discount_amount' => 'nullable|numeric|min:0',
            'discount_reference' => 'nullable|string|max:128',
            'payment_method'     => 'required|in:Cash,GCash,Card,Hospital Billing',
            'amount_tendered'    => 'nullable|numeric|min:0',
            'payment_reference'  => 'nullable|string|max:128',
            'idempotency_key'    => 'nullable|string|max:128',
            'notes'              => 'nullable|string|max:500',
            'items'              => 'required|array|min:1',
            'items.*.medication_name'      => 'required|string|max:255',
            'items.*.medicine_code'        => 'nullable|string|max:64',
            'items.*.quantity'             => 'required|integer|min:1',
            'items.*.prescription_item_id' => 'nullable|exists:prescription_items,id',
        ], [
            'items.required' => 'Please add at least one medicine to the POS cart before completing payment.',
            'items.min'      => 'Please add at least one medicine to the POS cart before completing payment.',
        ]);

        $rawDiscount = $validated['discount_type'] ?? 'none';
        $discountType = ($rawDiscount === 'senior') ? 'senior_citizen' : $rawDiscount;

        if ($discountType !== 'none' && ! $user->hasPermission('pms.pos.discount')) {
            abort(403, 'You do not have permission to apply POS discounts.');
        }

        // Prevent duplicate payment via idempotency_key
        $idempotencyKey = $validated['idempotency_key'] ?? null;
        if ($idempotencyKey && PharmacyPosTransaction::where('idempotency_key', $idempotencyKey)->exists()) {
            return back()->with('error', 'Duplicate payment submission detected. This transaction has already been processed.');
        }

        // Validate patient association & prescription-linked sale rules
        $prescription = null;
        $patientId    = $validated['patient_id'] ?? null;
        $patientType  = ! empty($validated['patient_type']) ? ucfirst(strtolower($validated['patient_type'])) : 'Outpatient';
        $customerName = $validated['customer_name'] ?? 'Walk-In Customer';

        if ($patientId) {
            $patient = Patient::find($patientId);
            if ($patient) {
                $patientType = $patient->patient_type ?: $patientType;
                if (empty($validated['customer_name']) || $validated['customer_name'] === 'Walk-In Customer') {
                    $customerName = $patient->full_name;
                }
            }
        }

        if (($validated['sale_type'] === 'prescription') || ! empty($validated['prescription_id'])) {
            if (empty($validated['prescription_id'])) {
                return back()->withErrors(['prescription_id' => 'Please select a valid prescription for a prescription-linked sale.'])->withInput();
            }

            $prescription = Prescription::with('patient')->findOrFail($validated['prescription_id']);
            if (in_array($prescription->status, ['Cancelled', 'Rejected'], true)) {
                return back()->with('error', "Prescription [{$prescription->prescription_no}] is {$prescription->status} and cannot be billed in POS.")->withInput();
            }

            // Prevent duplicate Completed POS charge for the same prescription
            $existingPaid = PharmacyPosTransaction::where('prescription_id', $prescription->id)
                ->where('status', PharmacyPosTransaction::STATUS_COMPLETED)
                ->exists();
            if ($existingPaid) {
                return back()->with('error', "Prescription [{$prescription->prescription_no}] already has an active completed POS transaction. Void or refund the previous transaction first to re-bill.")->withInput();
            }

            $patientId    = $prescription->patient_id;
            $customerName = $prescription->patient?->full_name ?? $customerName;
            $patientType  = $prescription->patient?->patient_type ?? 'Inpatient';
        }

        // Build canonical catalog lookup from MedicationStockProviderInterface
        $stockCatalog = collect($this->stockProvider->getAllMedicationStock());
        $todayTs      = strtotime(date('Y-m-d'));

        $resolvedLines = [];
        $subtotalFloat = 0.0;

        foreach ($validated['items'] as $cartItem) {
            $reqName = trim($cartItem['medication_name']);
            $reqCode = trim($cartItem['medicine_code'] ?? '');
            $reqQty  = (int) $cartItem['quantity'];

            /** @var MedicationStockData|null $matchedStock */
            $matchedStock = null;
            if ($reqCode !== '') {
                $matchedStock = $stockCatalog->first(fn (MedicationStockData $s) => strcasecmp($s->code, $reqCode) === 0);
            }
            if (! $matchedStock) {
                $matchedStock = $stockCatalog->first(function (MedicationStockData $s) use ($reqName) {
                    return strcasecmp($s->medicationName, $reqName) === 0
                        || (! empty($s->code) && strcasecmp($s->code, $reqName) === 0)
                        || stripos($s->medicationName, $reqName) !== false;
                });
            }

            if (! $matchedStock) {
                return back()->with('error', "Medicine [{$reqName}] is not found in the active pharmacy catalog.")->withInput();
            }

            if (! $matchedStock->isAvailable || $matchedStock->availableQuantity <= 0) {
                return back()->with('error', "Medicine [{$matchedStock->medicationName}] is currently Out of Stock.")->withInput();
            }

            $expTs = ! empty($matchedStock->expiryDate) ? strtotime($matchedStock->expiryDate) : null;
            if (! $expTs || $expTs <= $todayTs || ! $matchedStock->isUnexpired()) {
                return back()->with('error', "Medicine [{$matchedStock->medicationName}] batch ({$matchedStock->lotNumber}) is expired and cannot be sold.")->withInput();
            }

            if ($reqQty > $matchedStock->availableQuantity) {
                return back()->with('error', "Requested quantity ({$reqQty}) for [{$matchedStock->medicationName}] exceeds available stock ({$matchedStock->availableQuantity}).")->withInput();
            }

            $unitPrice    = round((float) $matchedStock->unitPrice, 2);
            $lineSubtotal = round($unitPrice * $reqQty, 2);
            $subtotalFloat = round($subtotalFloat + $lineSubtotal, 2);

            $resolvedLines[] = [
                'prescription_item_id' => $cartItem['prescription_item_id'] ?? null,
                'medicine_code'        => $matchedStock->code ?: 'MED',
                'medication_name'      => $matchedStock->medicationName,
                'generic_name'         => $matchedStock->genericName ?: $matchedStock->medicationName,
                'brand_name'           => $matchedStock->brandName ?: 'Generic',
                'category'             => $matchedStock->category ?: 'Default',
                'strength'             => $matchedStock->strength ?: '',
                'dosage_form'          => $matchedStock->dosageForm ?: 'Tablet',
                'unit'                 => $matchedStock->unit ?: 'unit',
                'lot_number'           => $matchedStock->lotNumber,
                'expiry_date'          => $matchedStock->expiryDate,
                'quantity'             => $reqQty,
                'unit_price'           => $unitPrice,
                'line_subtotal'        => $lineSubtotal,
            ];
        }

        // Server-side VAT & discount calculation
        $activeRule = FinancialRule::where('effective_from', '<=', now()->toDateString())
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();

        $vatRate = $activeRule && (float) $activeRule->tax_rate > 0
            ? (float) $activeRule->tax_rate
            : 0.1200;

        $discountRate = self::DISCOUNT_RATES[$discountType] ?? 0.0000;
        if ($discountType === 'custom_percentage' && ! empty($validated['custom_discount_rate'])) {
            $discountRate = round(((float) $validated['custom_discount_rate']) / 100, 4);
        }

        $isVatExemptDiscount = in_array($discountType, ['senior', 'senior_citizen', 'pwd', 'athlete', 'national_athlete'], true) && ($discountRate >= 0.2000);

        if ($isVatExemptDiscount) {
            // Senior Citizen & PWD statutory calculation: VAT-exempt base, then 20% discount
            $vatExemptSales = round($subtotalFloat / (1 + $vatRate), 2);
            $vatableSales   = 0.00;
            $vatAmount      = 0.00;
            $discountAmount = round($vatExemptSales * $discountRate, 2);
            $grandTotal     = round(max(0, $vatExemptSales - $discountAmount), 2);
        } else {
            // Standard VAT-inclusive calculation matching BIR and POS reference UI
            if ($discountType === 'custom_amount' && ! empty($validated['custom_discount_amount'])) {
                $discountAmount = min($subtotalFloat, round((float) $validated['custom_discount_amount'], 2));
                $discountRate   = $subtotalFloat > 0 ? round($discountAmount / $subtotalFloat, 4) : 0.0000;
            } else {
                $discountAmount = round($subtotalFloat * $discountRate, 2);
            }
            $netPayable     = round(max(0, $subtotalFloat - $discountAmount), 2);
            $vatableSales   = round($netPayable / (1 + $vatRate), 2);
            $vatExemptSales = 0.00;
            $vatAmount      = round($netPayable - $vatableSales, 2);
            $grandTotal     = $netPayable;
        }

        $amountTendered = isset($validated['amount_tendered']) && $validated['amount_tendered'] !== null
            ? round((float) $validated['amount_tendered'], 2)
            : $grandTotal;

        if ($validated['payment_method'] === 'Cash' && $amountTendered < $grandTotal) {
            return back()->with('error', sprintf(
                'Insufficient cash tendered (₱%s) for Grand Total of ₱%s.',
                number_format($amountTendered, 2),
                number_format($grandTotal, 2)
            ))->withInput();
        }

        $changeAmount = max(0.00, round($amountTendered - $grandTotal, 2));

        $transaction = DB::transaction(function () use (
            $validated,
            $prescription,
            $patientId,
            $patientType,
            $customerName,
            $user,
            $subtotalFloat,
            $discountType,
            $discountRate,
            $discountAmount,
            $vatableSales,
            $vatExemptSales,
            $vatRate,
            $vatAmount,
            $grandTotal,
            $amountTendered,
            $changeAmount,
            $idempotencyKey,
            $resolvedLines
        ) {
            $seq = PharmacyPosTransaction::count() + 1;
            $txnNo = 'POS-' . date('Ymd') . '-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);

            $txn = PharmacyPosTransaction::create([
                'transaction_no'         => $txnNo,
                'sale_type'              => $prescription ? 'prescription' : 'walk_in',
                'patient_type'           => $patientType,
                'prescription_id'        => $prescription?->id,
                'patient_id'             => $patientId,
                'customer_name'          => $customerName,
                'pharmacist_id'          => $user->id,
                'terminal_id'            => 'POS-TERM-01',
                'store_code'             => 'DTCS-PHARM-MAIN',
                'subtotal'               => $subtotalFloat,
                'discount_type'          => $discountType,
                'discount_rate'          => $discountRate,
                'discount_amount'        => $discountAmount,
                'discount_reference'     => $validated['discount_reference'] ?? null,
                'discount_authorized_by' => $discountType !== 'none' ? $user->id : null,
                'vatable_sales'          => $vatableSales,
                'vat_exempt_sales'       => $vatExemptSales,
                'vat_rate'               => $vatRate,
                'vat_amount'             => $vatAmount,
                'grand_total'            => $grandTotal,
                'payment_method'         => $validated['payment_method'],
                'amount_tendered'        => $amountTendered,
                'change_amount'          => $changeAmount,
                'payment_reference'      => $validated['payment_reference'] ?? null,
                'status'                 => PharmacyPosTransaction::STATUS_COMPLETED,
                'idempotency_key'        => $idempotencyKey,
                'notes'                  => $validated['notes'] ?? null,
                'transacted_at'          => now(),
            ]);

            foreach ($resolvedLines as $line) {
                PharmacyPosTransactionItem::create(array_merge($line, [
                    'pharmacy_pos_transaction_id' => $txn->id,
                ]));
            }

            ActivityLog::create([
                'user_id'       => $user->id,
                'action'        => 'POS Sale Completed',
                'module'        => 'Pharmacy',
                'description'   => sprintf(
                    'POS Transaction [%s] (%s) completed for ₱%s via %s.%s',
                    $txn->transaction_no,
                    $txn->sale_type,
                    number_format((float) $txn->grand_total, 2),
                    $txn->payment_method,
                    $prescription ? " Linked to Rx [{$prescription->prescription_no}] (dispensing status unchanged)." : ''
                ),
                'loggable_type' => PharmacyPosTransaction::class,
                'loggable_id'   => $txn->id,
                'ip_address'    => request()->ip(),
                'logged_at'     => now(),
            ]);

            return $txn;
        });

        return redirect()->route('pharmacy.pos.receipt', $transaction)
                         ->with('success', "POS Transaction {$transaction->transaction_no} completed successfully.");
    }

    /**
     * Display printable POS receipt.
     */
    public function receipt(PharmacyPosTransaction $transaction): View
    {
        /** @var User|null $user */
        $user = Auth::user();
        abort_if(! $user || ! $user->hasAnyRole(['pharmacist', 'admin', 'super_admin']), 403);

        $transaction->load(['items', 'patient', 'prescription.doctor', 'pharmacist', 'discountAuthorizedBy', 'voidedByUser']);

        return view('pharmacy.pos.receipt', compact('transaction'));
    }

    /**
     * Void or Refund a completed POS transaction with authorization and audit logging.
     * Completed financial transactions are never deleted.
     */
    public function voidOrRefund(Request $request, PharmacyPosTransaction $transaction): RedirectResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        abort_if(! $user || ! $user->hasRole('pharmacist') || ! $user->hasPermission('pms.pos.refund_void'), 403, 'You do not have permission to void or refund POS transactions.');

        if ($transaction->status !== PharmacyPosTransaction::STATUS_COMPLETED) {
            return back()->with('error', "Transaction [{$transaction->transaction_no}] is already {$transaction->status} and cannot be modified again.");
        }

        $validated = $request->validate([
            'action'      => 'required|in:Voided,Refunded',
            'void_reason' => 'required|string|min:3|max:500',
        ], [
            'void_reason.required' => 'Please provide a reason for voiding or refunding this POS transaction.',
        ]);

        $targetStatus = $validated['action'];

        $transaction->update([
            'status'      => $targetStatus,
            'voided_by'   => $user->id,
            'voided_at'   => now(),
            'void_reason' => $validated['void_reason'],
        ]);

        ActivityLog::create([
            'user_id'       => $user->id,
            'action'        => "POS Transaction {$targetStatus}",
            'module'        => 'Pharmacy',
            'description'   => "POS Transaction [{$transaction->transaction_no}] (₱" . number_format((float) $transaction->grand_total, 2) . ") marked as {$targetStatus}. Reason: {$validated['void_reason']}",
            'loggable_type' => PharmacyPosTransaction::class,
            'loggable_id'   => $transaction->id,
            'ip_address'    => request()->ip(),
            'logged_at'     => now(),
        ]);

        return back()->with('success', "Transaction {$transaction->transaction_no} has been marked as {$targetStatus}.");
    }
}

