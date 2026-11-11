<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDispensingRecordRequest;
use App\Models\ActivityLog;
use App\Models\DispensingRecord;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\User;
use App\Services\Pharmacy\Contracts\MedicationStockProviderInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * DispensingController — manages medication dispensing workflow by pharmacists.
 */
class DispensingController extends Controller
{
    public function __construct(
        protected MedicationStockProviderInterface $stockProvider
    ) {}

    public function index(Request $request): View
    {
        $query = DispensingRecord::with([
            'prescriptionItem.prescription.patient',
            'prescriptionItem.prescription.doctor',
            'pharmacist'
        ]);

        if ($search = trim($request->input('search') ?? '')) {
            $query->where(function ($q) use ($search) {
                // Search by Dispensing Record ID (e.g., #DSP-00001, DSP-00001, or numeric ID)
                if (preg_match('/^#?DSP-(\d+)$/i', $search, $matches)) {
                    $q->where('id', (int) $matches[1]);
                } elseif (is_numeric($search)) {
                    $q->where('id', (int) $search);
                }

                $q->orWhereHas('prescriptionItem.prescription', fn($p) => $p->where('prescription_no', 'like', "%{$search}%"))
                  ->orWhereHas('prescriptionItem.prescription.patient', function ($pt) use ($search) {
                      $pt->where('first_name', 'like', "%{$search}%")
                         ->orWhere('last_name', 'like', "%{$search}%")
                         ->orWhere('patient_no', 'like', "%{$search}%")
                         ->orWhere(DB::raw("CONCAT(first_name, ' ', last_name)"), 'like', "%{$search}%");
                  })
                  ->orWhereHas('prescriptionItem', fn($item) => $item->where('medication_name', 'like', "%{$search}%"))
                  ->orWhere('lot_number', 'like', "%{$search}%");
            });
        }

        $records = $query->latest('dispensed_at')->paginate(15)->withQueryString();

        $today = now()->toDateString();
        $stats = [
            'dispensed_today'   => DispensingRecord::whereDate('dispensed_at', $today)->count(),
            'dispensed_month'   => DispensingRecord::whereMonth('dispensed_at', now()->month)
                                                    ->whereYear('dispensed_at', now()->year)->count(),
            'ready_to_dispense' => Prescription::whereIn('status', ['Verified', 'Partially Dispensed'])
                                                  ->whereHas('items', fn($q) => $q->where('status', 'Pending'))
                                                  ->count(),
            'total_dispensings' => DispensingRecord::count(),
        ];

        return view('pharmacy.dispensing.index', compact('records', 'stats'));
    }

    public function create(Request $request): View
    {
        /** @var User|null $user */
        $user = Auth::user();
        abort_if(! $user?->hasRole('pharmacist'), 403, 'Only pharmacists can dispense medications.');

        // Verified and Partially Dispensed prescriptions that still have pending items
        $prescriptions = Prescription::whereIn('status', ['Verified', 'Partially Dispensed'])
            ->whereHas('items', fn($q) => $q->where('status', 'Pending'))
            ->with(['patient', 'doctor', 'items' => fn($q) => $q->where('status', 'Pending')])
            ->orderBy('created_at', 'desc')
            ->get();

        $selectedPrescription = null;
        if ($rxId = $request->input('rx')) {
            $selectedPrescription = Prescription::with(['patient', 'doctor', 'items.dispensingRecords'])->find($rxId);
        }

        $selectedItem = null;
        if ($itemId = $request->input('item')) {
            $selectedItem = PrescriptionItem::with('prescription.patient', 'dispensingRecords')->find($itemId);
            if ($selectedItem && ! $selectedPrescription) {
                $selectedPrescription = $selectedItem->prescription->load(['patient', 'doctor', 'items.dispensingRecords']);
            }
        }

        // Retrieve stock provider integration data for all prescription items
        $stockDataMap = [];
        if ($selectedPrescription) {
            foreach ($selectedPrescription->items as $item) {
                $stockDataMap[$item->id] = $this->stockProvider->getMedicationStock($item);
            }
        }

        return view('pharmacy.dispensing.create', compact('prescriptions', 'selectedPrescription', 'selectedItem', 'stockDataMap'));
    }

    public function store(StoreDispensingRecordRequest $request): RedirectResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        abort_if(! $user?->hasRole('pharmacist'), 403, 'Only pharmacists can dispense medications.');

        // 1. Gather selected item payloads
        $selectedPayloads = [];

        if ($itemsPayload = $request->input('items')) {
            foreach ($itemsPayload as $key => $itemData) {
                if (is_array($itemData)) {
                    $itemId = !empty($itemData['id'])
                        ? $itemData['id']
                        : (!empty($itemData['prescription_item_id']) ? $itemData['prescription_item_id'] : (is_numeric($key) && (int) $key > 0 ? $key : null));
                    if ($itemId) {
                        $selectedPayloads[$itemId] = [
                            'id'                 => $itemId,
                            'quantity_dispensed' => $itemData['quantity_dispensed'] ?? null,
                            'lot_number'         => $itemData['lot_number'] ?? null,
                            'expiry_date'        => $itemData['expiry_date'] ?? null,
                            'notes'              => $itemData['notes'] ?? null,
                        ];
                    }
                }
            }
        } elseif ($itemIds = $request->input('prescription_item_ids')) {
            foreach ((array)$itemIds as $id) {
                $selectedPayloads[$id] = [
                    'id'                 => $id,
                    'quantity_dispensed' => null,
                    'lot_number'         => null,
                    'expiry_date'        => null,
                    'notes'              => null,
                ];
            }
        } elseif ($singleId = $request->input('prescription_item_id')) {
            $selectedPayloads[$singleId] = [
                'id'                 => $singleId,
                'quantity_dispensed' => $request->input('quantity_dispensed'),
                'lot_number'         => $request->input('lot_number'),
                'expiry_date'        => $request->input('expiry_date'),
                'notes'              => $request->input('notes'),
            ];
        }

        if (empty($selectedPayloads) && $rxId = $request->input('prescription_id')) {
            $rxCheck = Prescription::find($rxId);
            if ($rxCheck && ! in_array($rxCheck->status, ['Verified', 'Partially Dispensed'], true)) {
                return back()->with('error', "Prescription [{$rxCheck->prescription_no}] is currently {$rxCheck->status}. Prescriptions must be Verified before dispensing.")
                             ->withInput();
            }

            $pendingItems = PrescriptionItem::where('prescription_id', $rxId)
                ->where('status', 'Pending')
                ->get();

            if ($pendingItems->isEmpty()) {
                return back()->with('error', 'All prescribed medications for this prescription have already been dispensed.')
                             ->withInput();
            }

            foreach ($pendingItems as $pItem) {
                $selectedPayloads[$pItem->id] = [
                    'id'                 => $pItem->id,
                    'quantity_dispensed' => null,
                    'lot_number'         => null,
                    'expiry_date'        => null,
                    'notes'              => null,
                ];
            }
        }

        if (empty($selectedPayloads)) {
            return back()->with('error', 'Please select at least one prescription item to dispense.')
                         ->withInput();
        }

        // 2. Fetch and validate items
        /** @var \Illuminate\Database\Eloquent\Collection<int, PrescriptionItem> $items */
        $items = PrescriptionItem::with(['prescription.patient', 'dispensingRecords'])
            ->whereIn('id', array_keys($selectedPayloads))
            ->get();

        if ($items->isEmpty()) {
            return back()->with('error', 'The selected prescription items could not be found.')
                         ->withInput();
        }

        // Verify all items belong to the same prescription
        $prescriptionIds = $items->pluck('prescription_id')->unique();
        if ($prescriptionIds->count() > 1) {
            return back()->with('error', 'All items in a batch dispensing request must belong to the same prescription.')
                         ->withInput();
        }

        // Verify prescription validation & items status
        /** @var PrescriptionItem $firstItem */
        $firstItem = $items->first();
        $prescription = $firstItem->prescription;

        // Verify prescription status is eligible (must be Verified or Partially Dispensed)
        if (! in_array($prescription->status, ['Verified', 'Partially Dispensed'], true)) {
            return back()->with('error', "Prescription [{$prescription->prescription_no}] is currently {$prescription->status}. Only Verified or Partially Dispensed prescriptions can be dispensed.")
                         ->withInput();
        }

        // Verify no items are already fully dispensed
        $alreadyDispensed = $items->filter(fn(PrescriptionItem $i) => $i->status === 'Dispensed' || $i->remaining_quantity <= 0);
        if ($alreadyDispensed->isNotEmpty()) {
            $names = $alreadyDispensed->pluck('medication_name')->implode(', ');
            return back()->with('error', "The following medication(s) have already been dispensed: {$names}.")
                         ->withInput();
        }

        // Verify quantities, lot numbers, and expiry dates per item via MedicationStockProviderInterface
        $todayStr = date('Y-m-d');
        /** @var PrescriptionItem $item */
        foreach ($items as $item) {
            $payload = $selectedPayloads[$item->id] ?? [];
            $remainingAuthorizedQty = $item->remaining_quantity;

            $requestedQty = !empty($payload['quantity_dispensed'])
                ? (int) $payload['quantity_dispensed']
                : (int) $request->input('quantity_dispensed', $remainingAuthorizedQty);

            if ($requestedQty > $remainingAuthorizedQty) {
                return back()->with('error', "Dispensing quantity for {$item->medication_name} ({$requestedQty}) exceeds remaining authorized quantity ({$remainingAuthorizedQty}).")
                             ->withInput();
            }

            // Query stock availability & batch data from inventory stock provider
            $stockData = $this->stockProvider->getMedicationStock($item);

            if (! $stockData->isAvailable) {
                $errDetail = $stockData->errorMessage ?: 'Inventory stock data unavailable.';
                return back()->with('error', "Stock information unavailable for medication [{$item->medication_name}]: {$errDetail}")
                             ->withInput();
            }

            if (! $stockData->hasSufficientStock($requestedQty)) {
                return back()->with('error', "Insufficient stock for medication [{$item->medication_name}]. Prescribed: {$requestedQty} units, Available in Stock: {$stockData->availableQuantity} units.")
                             ->withInput();
            }

            $lot = !empty($payload['lot_number']) ? $payload['lot_number'] : (!empty($request->input('lot_number')) ? $request->input('lot_number') : $stockData->lotNumber);
            $exp = !empty($payload['expiry_date']) ? $payload['expiry_date'] : (!empty($request->input('expiry_date')) ? $request->input('expiry_date') : $stockData->expiryDate);

            if (empty($lot)) {
                return back()->with('error', "Lot / Batch number is missing for medication [{$item->medication_name}].")
                             ->withInput();
            }

            if (empty($exp) || strtotime($exp) <= strtotime($todayStr) || ! $stockData->isUnexpired()) {
                return back()->with('error', "Medication [{$item->medication_name}] stock batch is expired or has an invalid expiry date ({$exp}).")
                             ->withInput();
            }

            $selectedPayloads[$item->id]['resolved_lot'] = $lot;
            $selectedPayloads[$item->id]['resolved_exp'] = $exp;
            $selectedPayloads[$item->id]['resolved_qty'] = $requestedQty;
        }

        // 3. Process dispensing batch inside a DB Transaction
        $firstRecord = DB::transaction(function () use ($items, $selectedPayloads, $request, $prescription) {
            $createdRecords = [];

            /** @var PrescriptionItem $item */
            foreach ($items as $item) {
                $payload = $selectedPayloads[$item->id] ?? [];
                $alreadyDispensedQty = $item->dispensed_quantity;

                $qty  = $payload['resolved_qty'] ?? (!empty($payload['quantity_dispensed']) ? (int)$payload['quantity_dispensed'] : (int)$request->input('quantity_dispensed', $item->remaining_quantity));
                $lot  = $payload['resolved_lot'] ?? (!empty($payload['lot_number']) ? $payload['lot_number'] : $request->input('lot_number'));
                $exp  = $payload['resolved_exp'] ?? (!empty($payload['expiry_date']) ? $payload['expiry_date'] : $request->input('expiry_date'));
                $note = !empty($payload['notes']) ? $payload['notes'] : $request->input('notes');

                // Create dispensing record
                $dispensing = DispensingRecord::create([
                    'prescription_item_id' => $item->id,
                    'pharmacist_id'        => Auth::id(),
                    'quantity_dispensed'   => $qty,
                    'lot_number'           => $lot,
                    'expiry_date'          => $exp,
                    'notes'                => $note,
                    'dispensed_at'         => now(),
                ]);

                // Mark item as Dispensed only when cumulative dispensed quantity meets or exceeds authorized quantity
                if (($alreadyDispensedQty + $qty) >= (int) $item->quantity) {
                    $item->update(['status' => 'Dispensed']);
                }

                // Audit log entry per item
                ActivityLog::create([
                    'user_id'       => Auth::id(),
                    'action'        => 'Medication Dispensed',
                    'module'        => 'Pharmacy',
                    'description'   => "Dispensed [{$item->medication_name}] (Qty: {$qty}, Lot: {$lot}) for Prescription [{$prescription->prescription_no}].",
                    'loggable_type' => DispensingRecord::class,
                    'loggable_id'   => $dispensing->id,
                    'ip_address'    => request()->ip(),
                    'logged_at'     => now(),
                ]);

                $createdRecords[] = $dispensing;
            }

            // 4. Recalculate parent prescription status
            $pendingItemsCount = $prescription->items()->where('status', 'Pending')->count();
            $newStatus = ($pendingItemsCount === 0) ? 'Dispensed' : 'Partially Dispensed';
            $prescription->update(['status' => $newStatus]);

            return reset($createdRecords);
        });

        $count = count($items);
        $message = $count === 1
            ? 'Medication dispensed successfully and inventory batch logged.'
            : "Successfully batch-dispensed {$count} medications for Prescription #{$prescription->prescription_no}.";

        if ($count === 1 && $firstRecord) {
            return redirect()->route('pharmacy.dispensing.show', $firstRecord)
                             ->with('success', $message);
        }

        return redirect()->route('pharmacy.prescriptions.show', $prescription)
                         ->with('success', $message);
    }

    public function show(DispensingRecord $dispensing): View
    {
        $dispensing->load([
            'prescriptionItem.prescription.patient',
            'prescriptionItem.prescription.doctor',
            'pharmacist'
        ]);

        return view('pharmacy.dispensing.show', compact('dispensing'));
    }

    // Immutable records
    public function edit(DispensingRecord $dispensing): View { abort(403, 'Dispensing records cannot be edited once recorded.'); }
    public function update(Request $request, DispensingRecord $dispensing): RedirectResponse { abort(403); }
    public function destroy(DispensingRecord $dispensing): RedirectResponse { abort(403); }

    /** Print-friendly view for dispensing record. */
    public function print(DispensingRecord $dispensing): View
    {
        $dispensing->load([
            'prescriptionItem.prescription.patient',
            'prescriptionItem.prescription.doctor',
            'pharmacist'
        ]);

        return view('pharmacy.dispensing.print', compact('dispensing'));
    }
}
