<?php

namespace App\Services;

use App\Models\DietPlan;
use App\Models\DietRequest;
use App\Models\DispensingRecord;
use App\Models\LabRequest;
use App\Models\LabRequestItem;
use App\Models\LabResult;
use App\Models\PharmacyPosTransaction;
use App\Models\PharmacyPosTransactionItem;
use App\Models\Prescription;
use App\Models\RadiologyReport;
use App\Models\RadiologyRequest;
use App\Models\SurgeryRequest;
use App\Models\SurgerySchedule;
use App\Services\Pharmacy\Contracts\MedicationStockProviderInterface;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportService
{
    /**
     * Role slugs allowed per report category.
     */
    public const ROLE_ACCESS = [
        'laboratory'  => ['super_admin', 'admin', 'doctor', 'med-tech'],
        'radiology'   => ['super_admin', 'admin', 'doctor', 'rad-tech', 'radiologist'],
        'pharmacy'    => ['super_admin', 'admin', 'doctor', 'pharmacist'],
        'surgery'     => ['super_admin', 'admin', 'doctor', 'or-coordinator'],
        'diet'        => ['super_admin', 'admin', 'doctor'],
        'clinical'    => ['super_admin', 'admin', 'doctor'],
    ];

    /**
     * Parse and validate date range from request.
     * Defaults to current month if not provided.
     */
    public function parseDateRange(Request $request): array
    {
        $from = $request->filled('date_from')
            ? Carbon::parse($request->date_from)->startOfDay()
            : Carbon::now()->startOfMonth();

        $to = $request->filled('date_to')
            ? Carbon::parse($request->date_to)->endOfDay()
            : Carbon::now()->endOfDay();

        // Ensure from <= to
        if ($from->gt($to)) {
            $temp = $from;
            $from = $to->startOfDay();
            $to = $temp->endOfDay();
        }

        return [$from, $to];
    }

    /**
     * Check if user role can access a report category.
     */
    public function canAccess(string $category, ?string $roleSlug): bool
    {
        $allowed = self::ROLE_ACCESS[$category] ?? [];
        return in_array($roleSlug, $allowed, true);
    }

    /**
     * Get categories accessible by a role.
     */
    public function accessibleCategories(?string $roleSlug): array
    {
        $accessible = [];
        foreach (self::ROLE_ACCESS as $category => $roles) {
            if (in_array($roleSlug, $roles, true)) {
                $accessible[] = $category;
            }
        }
        return $accessible;
    }

    /**
     * Return the authenticated user's ID when their primary role is `doctor`, or null otherwise.
     */
    private function currentDoctorId(): ?int
    {
        if (!\Illuminate\Support\Facades\Auth::check()) {
            return null;
        }

        /** @var \App\Models\User|null $user */
        $user = \Illuminate\Support\Facades\Auth::user();

        return $user?->primaryRole === 'doctor' ? (int) $user->id : null;
    }

    // ─── Laboratory ──────────────────────────────────────────────────

    public function labActivitySummary(Carbon $from, Carbon $to, ?string $status = null): array
    {
        $query = LabRequest::whereBetween('requested_at', [$from, $to]);
        if ($doctorId = $this->currentDoctorId()) {
            $query->where('doctor_id', $doctorId);
        }
        $total = (clone $query)->count();

        return [
            'total'     => $total,
            'pending'   => (clone $query)->where('status', 'Pending')->count(),
            'received'  => (clone $query)->where('status', 'Received')->count(),
            'completed' => (clone $query)->where('status', 'Completed')->count(),
            'cancelled' => (clone $query)->where('status', 'Cancelled')->count(),
            'completion_rate' => $total > 0
                ? round((clone $query)->where('status', 'Completed')->count() / $total * 100, 1)
                : 0,
        ];
    }

    public function labActivityDetails(Carbon $from, Carbon $to, Request $request)
    {
        $query = LabRequest::with(['patient', 'doctor', 'items.labTest'])
            ->whereBetween('requested_at', [$from, $to]);

        if ($doctorId = $this->currentDoctorId()) {
            $query->where('doctor_id', $doctorId);
        } elseif ($request->filled('doctor_id')) {
            $query->where('doctor_id', $request->doctor_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        return $query->orderBy('requested_at', 'desc')->paginate(25)->withQueryString();
    }

    public function labTestVolume(Carbon $from, Carbon $to)
    {
        $query = LabRequestItem::select('lab_test_id')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN lab_request_items.status = 'Completed' THEN 1 ELSE 0 END) as completed")
            ->selectRaw("SUM(CASE WHEN lab_request_items.status = 'Pending' THEN 1 ELSE 0 END) as pending")
            ->join('lab_requests', 'lab_requests.id', '=', 'lab_request_items.lab_request_id')
            ->whereBetween('lab_requests.requested_at', [$from, $to]);

        if ($doctorId = $this->currentDoctorId()) {
            $query->where('lab_requests.doctor_id', $doctorId);
        }

        return $query
            ->groupBy('lab_test_id')
            ->with('labTest')
            ->orderByDesc('total')
            ->get();
    }

    // ─── Radiology ───────────────────────────────────────────────────

    public function radiologyActivitySummary(Carbon $from, Carbon $to): array
    {
        $query = RadiologyRequest::whereBetween('requested_at', [$from, $to]);
        if ($doctorId = $this->currentDoctorId()) {
            $query->where('doctor_id', $doctorId);
        }
        $total = (clone $query)->count();

        return [
            'total'     => $total,
            'pending'   => (clone $query)->where('status', 'Pending')->count(),
            'scheduled' => (clone $query)->where('status', 'Scheduled')->count(),
            'in_progress' => (clone $query)->where('status', 'In Progress')->count(),
            'completed' => (clone $query)->where('status', 'Completed')->count(),
            'cancelled' => (clone $query)->where('status', 'Cancelled')->count(),
            'completion_rate' => $total > 0
                ? round((clone $query)->where('status', 'Completed')->count() / $total * 100, 1)
                : 0,
        ];
    }

    public function radiologyActivityDetails(Carbon $from, Carbon $to, Request $request)
    {
        $query = RadiologyRequest::with(['patient', 'doctor', 'report'])
            ->whereBetween('requested_at', [$from, $to]);

        if ($doctorId = $this->currentDoctorId()) {
            $query->where('doctor_id', $doctorId);
        } elseif ($request->filled('doctor_id')) {
            $query->where('doctor_id', $request->doctor_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('modality')) {
            $query->where('modality', $request->modality);
        }

        return $query->orderBy('requested_at', 'desc')->paginate(25)->withQueryString();
    }

    public function radiologyVolumeByModality(Carbon $from, Carbon $to)
    {
        $query = RadiologyRequest::select('modality')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed")
            ->selectRaw("SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending")
            ->whereBetween('requested_at', [$from, $to]);

        if ($doctorId = $this->currentDoctorId()) {
            $query->where('doctor_id', $doctorId);
        }

        return $query
            ->groupBy('modality')
            ->orderByDesc('total')
            ->get();
    }

    public function radiologyPendingInterpretations(Carbon $from, Carbon $to, Request $request)
    {
        $query = RadiologyRequest::with(['patient', 'doctor', 'report'])
            ->whereBetween('requested_at', [$from, $to])
            ->where('status', 'Completed')
            ->whereDoesntHave('report', fn($q) => $q->where('status', 'Released'));

        if ($doctorId = $this->currentDoctorId()) {
            $query->where('doctor_id', $doctorId);
        }

        if ($request->filled('modality')) {
            $query->where('modality', $request->modality);
        }

        return $query->orderBy('requested_at', 'desc')->paginate(25)->withQueryString();
    }

    // ─── Pharmacy ────────────────────────────────────────────────────

    public function pharmacyActivitySummary(Carbon $from, Carbon $to): array
    {
        $query = Prescription::whereBetween('prescribed_at', [$from, $to]);
        if ($doctorId = $this->currentDoctorId()) {
            $query->where('doctor_id', $doctorId);
        }
        $total = (clone $query)->count();

        return [
            'total'     => $total,
            'pending'   => (clone $query)->where('status', 'Pending')->count(),
            'verified'  => (clone $query)->where('status', 'Verified')->count(),
            'dispensed' => (clone $query)->where('status', 'Dispensed')->count(),
            'cancelled' => (clone $query)->where('status', 'Cancelled')->count(),
            'completion_rate' => $total > 0
                ? round((clone $query)->where('status', 'Dispensed')->count() / $total * 100, 1)
                : 0,
        ];
    }

    public function pharmacyActivityDetails(Carbon $from, Carbon $to, Request $request)
    {
        $query = Prescription::with(['patient', 'doctor', 'items'])
            ->whereBetween('prescribed_at', [$from, $to]);

        if ($doctorId = $this->currentDoctorId()) {
            $query->where('doctor_id', $doctorId);
        } elseif ($request->filled('doctor_id')) {
            $query->where('doctor_id', $request->doctor_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return $query->orderBy('prescribed_at', 'desc')->paginate(25)->withQueryString();
    }

    public function dispensingDetails(Carbon $from, Carbon $to, Request $request)
    {
        $query = DispensingRecord::with(['prescriptionItem.prescription.patient', 'prescriptionItem.prescription.doctor', 'pharmacist'])
            ->whereBetween('dispensed_at', [$from, $to]);

        if ($doctorId = $this->currentDoctorId()) {
            $query->whereHas('prescriptionItem.prescription', fn($q) => $q->where('doctor_id', $doctorId));
        }

        if ($request->filled('pharmacist_id')) {
            $query->where('pharmacist_id', $request->pharmacist_id);
        }

        return $query->orderBy('dispensed_at', 'desc')->paginate(25)->withQueryString();
    }

    /**
     * Comprehensive Pharmacy Reports payload covering Financial Summary, POS Sales,
     * Inventory Stock & Expiry, Discounts, Refunds/Voids, Partial Dispensing, and Pharmacist Activity.
     */
    public function pharmacyComprehensiveReport(Carbon $from, Carbon $to, Request $request): array
    {
        $search = trim((string) $request->input('search', ''));

        // 1. Financial Summary & POS Sales
        $posQuery = PharmacyPosTransaction::with(['pharmacist', 'prescription.patient', 'discountAuthorizedBy', 'voidedByUser', 'items'])
            ->whereBetween('transacted_at', [$from, $to]);

        if ($search !== '') {
            $posQuery->where(function ($q) use ($search) {
                $q->where('transaction_no', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhere('payment_method', 'like', "%{$search}%");
            });
        }

        $completedPos = (clone $posQuery)->where('status', 'Completed');
        $voidedPos    = (clone $posQuery)->whereIn('status', ['Voided', 'Refunded']);

        $grossSales     = (float) (clone $completedPos)->sum('subtotal');
        $discountsTotal = (float) (clone $completedPos)->sum('discount_amount');
        $vatCollected   = (float) (clone $completedPos)->sum('vat_amount');
        $netSales       = (float) (clone $completedPos)->sum('grand_total');
        $refundsVoids   = (float) (clone $voidedPos)->sum('grand_total');

        $paymentMethodBreakdown = [
            'Cash'             => (float) (clone $completedPos)->where('payment_method', 'Cash')->sum('grand_total'),
            'GCash'            => (float) (clone $completedPos)->where('payment_method', 'GCash')->sum('grand_total'),
            'Card'             => (clone $completedPos)->where('payment_method', 'Card')->sum('grand_total'),
            'Hospital Billing' => (float) (clone $completedPos)->where('payment_method', 'Hospital Billing')->sum('grand_total'),
        ];

        $financialSummary = [
            'gross_sales'              => round($grossSales, 2),
            'discounts_total'          => round($discountsTotal, 2),
            'refunds_voids_total'      => round($refundsVoids, 2),
            'net_sales'                => round($netSales, 2),
            'vat_collected'            => round($vatCollected, 2),
            'completed_count'          => (clone $completedPos)->count(),
            'voided_count'             => (clone $voidedPos)->count(),
            'payment_method_breakdown' => $paymentMethodBreakdown,
        ];

        // Sales by Medicine
        $salesByMedicine = PharmacyPosTransactionItem::select('medication_name', 'medicine_code', 'category')
            ->selectRaw('SUM(quantity) as total_qty')
            ->selectRaw('SUM(line_subtotal) as total_revenue')
            ->whereHas('transaction', fn ($q) => $q->where('status', 'Completed')->whereBetween('transacted_at', [$from, $to]))
            ->groupBy('medication_name', 'medicine_code', 'category')
            ->orderByDesc('total_revenue')
            ->get();

        // Sales by Pharmacist
        $salesByPharmacist = PharmacyPosTransaction::with('pharmacist')
            ->select('pharmacist_id')
            ->selectRaw('COUNT(*) as transaction_count')
            ->selectRaw('SUM(grand_total) as total_sales')
            ->selectRaw('SUM(discount_amount) as total_discounts')
            ->where('status', 'Completed')
            ->whereBetween('transacted_at', [$from, $to])
            ->groupBy('pharmacist_id')
            ->orderByDesc('total_sales')
            ->get();

        // Discounted Transactions
        $discountedTransactions = (clone $completedPos)
            ->where('discount_type', '!=', 'none')
            ->where('discount_amount', '>', 0)
            ->orderByDesc('transacted_at')
            ->get();

        // Refund & Void Transactions
        $refundVoidTransactions = (clone $voidedPos)
            ->orderByDesc('voided_at')
            ->get();

        // Partial Dispensing Prescriptions
        $partialDispensingRecords = Prescription::with(['patient', 'doctor', 'items.dispensingRecords'])
            ->where('status', 'Partially Dispensed')
            ->whereBetween('prescribed_at', [$from, $to])
            ->orderByDesc('prescribed_at')
            ->get();

        // Inventory & Expiry Report (from MedicationStockProviderInterface)
        $inventoryReport = [
            'available'   => collect(),
            'low_stock'   => collect(),
            'out_of_stock'=> collect(),
            'near_expiry' => collect(),
            'expired'     => collect(),
        ];

        try {
            /** @var MedicationStockProviderInterface $stockProvider */
            $stockProvider = app(MedicationStockProviderInterface::class);
            $allStock = collect($stockProvider->getAllMedicationStock());
            $todayTs = strtotime(date('Y-m-d'));
            $expThresholdTs = strtotime(date('Y-m-d', strtotime('+90 days')));

            $mappedStock = $allStock->map(function ($item, $idx) use ($todayTs, $expThresholdTs) {
                $qty = $item->availableQuantity;
                $reorder = $item->reorderLevel ?: 20;
                $expTs = ! empty($item->expiryDate) ? strtotime($item->expiryDate) : null;
                $isExpired = ! $expTs || $expTs <= $todayTs;
                $isNearExpiry = ! $isExpired && $expTs <= $expThresholdTs;

                return [
                    'code'            => $item->code ?: ('MED-' . str_pad((string) ($idx + 1), 3, '0', STR_PAD_LEFT)),
                    'medication_name' => $item->medicationName,
                    'category'        => $item->category ?: 'Default',
                    'unit_price'      => round((float) $item->unitPrice, 2),
                    'qty'             => $qty,
                    'reorder_level'   => $reorder,
                    'lot_number'      => $item->lotNumber ?? 'N/A',
                    'expiry_date'     => $item->expiryDate,
                    'is_expired'      => $isExpired,
                    'is_near_expiry'  => $isNearExpiry,
                ];
            });

            $inventoryReport = [
                'available'    => $mappedStock->filter(fn ($i) => $i['qty'] > $i['reorder_level'] && ! $i['is_expired'])->values(),
                'low_stock'    => $mappedStock->filter(fn ($i) => $i['qty'] > 0 && $i['qty'] <= $i['reorder_level'])->values(),
                'out_of_stock' => $mappedStock->filter(fn ($i) => $i['qty'] <= 0)->values(),
                'near_expiry'  => $mappedStock->filter(fn ($i) => $i['is_near_expiry'])->values(),
                'expired'      => $mappedStock->filter(fn ($i) => $i['is_expired'])->values(),
            ];
        } catch (\Throwable $e) {
            // Fallback empty collections on error
        }

        $posTransactionsList = (clone $posQuery)->orderByDesc('transacted_at')->limit(50)->get();

        return [
            'financial_summary'          => $financialSummary,
            'sales_by_medicine'          => $salesByMedicine,
            'sales_by_pharmacist'        => $salesByPharmacist,
            'pos_transactions'           => $posTransactionsList,
            'discounted_transactions'    => $discountedTransactions,
            'refund_void_transactions'   => $refundVoidTransactions,
            'partial_dispensing_records' => $partialDispensingRecords,
            'inventory_report'           => $inventoryReport,
        ];
    }

    // ─── Surgery ─────────────────────────────────────────────────────

    public function surgeryActivitySummary(Carbon $from, Carbon $to): array
    {
        $query = SurgeryRequest::whereBetween('requested_at', [$from, $to]);

        if (\Illuminate\Support\Facades\Auth::check()) {
            /** @var \App\Models\User $user */
            $user = \Illuminate\Support\Facades\Auth::user();
            $query->visibleTo($user);
        }

        $total = (clone $query)->count();

        return [
            'total'     => $total,
            'pending'   => (clone $query)->where('status', 'Pending')->count(),
            'scheduled' => (clone $query)->where('status', 'Scheduled')->count(),
            'completed' => (clone $query)->where('status', 'Completed')->count(),
            'cancelled' => (clone $query)->where('status', 'Cancelled')->count(),
            'in_progress' => (clone $query)->where('status', 'In Progress')->count(),
            'completion_rate' => $total > 0
                ? round((clone $query)->where('status', 'Completed')->count() / $total * 100, 1)
                : 0,
        ];
    }

    public function surgeryActivityDetails(Carbon $from, Carbon $to, Request $request)
    {
        $query = SurgeryRequest::with(['patient', 'doctor', 'schedule.operatingRoom'])
            ->whereBetween('requested_at', [$from, $to]);

        if (\Illuminate\Support\Facades\Auth::check()) {
            /** @var \App\Models\User $user */
            $user = \Illuminate\Support\Facades\Auth::user();
            $query->visibleTo($user);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('doctor_id') && !$this->currentDoctorId()) {
            $query->where('doctor_id', $request->doctor_id);
        }
        if ($request->filled('urgency')) {
            $query->where('urgency', $request->urgency);
        }

        return $query->orderBy('requested_at', 'desc')->paginate(25)->withQueryString();
    }

    public function orUtilization(Carbon $from, Carbon $to)
    {
        $query = SurgerySchedule::select('operating_room_id')
            ->selectRaw('COUNT(*) as total_surgeries')
            ->selectRaw("SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed")
            ->selectRaw('COALESCE(SUM(duration_minutes), 0) as total_minutes')
            ->whereBetween('scheduled_at', [$from, $to]);

        if (\Illuminate\Support\Facades\Auth::check()) {
            /** @var \App\Models\User $user */
            $user = \Illuminate\Support\Facades\Auth::user();
            $query->visibleTo($user);
        }

        return $query
            ->groupBy('operating_room_id')
            ->with('operatingRoom')
            ->orderByDesc('total_surgeries')
            ->get();
    }

    // ─── Diet & Nutrition ────────────────────────────────────────────

    public function dietActivitySummary(Carbon $from, Carbon $to): array
    {
        $reqQuery = DietRequest::whereBetween('requested_at', [$from, $to]);
        $planQuery = DietPlan::whereHas('dietRequest', fn($q) => $q->whereBetween('requested_at', [$from, $to]));

        // Doctors only see their own authorized diet records.
        if (\Illuminate\Support\Facades\Auth::check()) {
            $user = \Illuminate\Support\Facades\Auth::user();
            $reqQuery->visibleTo($user);
            $planQuery->visibleTo($user);
        }

        return [
            'total_requests' => (clone $reqQuery)->count(),
            'total_plans'    => (clone $planQuery)->count(),
            'active_plans'   => (clone $planQuery)->whereIn('status', ['Active', 'Revised'])->count(),
            'completed_plans'=> (clone $planQuery)->where('status', 'Completed')->count(),
            'pending_requests' => (clone $reqQuery)->where('status', 'Pending')->count(),
        ];
    }

    public function dietActivityDetails(Carbon $from, Carbon $to, Request $request)
    {
        $query = DietRequest::with(['patient', 'doctor', 'dietPlan.dietitian'])
            ->whereBetween('requested_at', [$from, $to]);

        if (\Illuminate\Support\Facades\Auth::check()) {
            $query->visibleTo(\Illuminate\Support\Facades\Auth::user());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('diet_type')) {
            $query->where('diet_type', $request->diet_type);
        }

        return $query->orderBy('requested_at', 'desc')->paginate(25)->withQueryString();
    }

    public function dietPlansByStatus(Carbon $from, Carbon $to, string $status, Request $request)
    {
        $query = DietPlan::with(['dietRequest.patient', 'dietRequest.doctor', 'dietitian'])
            ->whereHas('dietRequest', fn($q) => $q->whereBetween('requested_at', [$from, $to]))
            // A Revised plan is still in effect, so it is reported with Active plans.
            ->whereIn('status', $status === 'Active' ? ['Active', 'Revised'] : [$status]);

        if (\Illuminate\Support\Facades\Auth::check()) {
            $query->visibleTo(\Illuminate\Support\Facades\Auth::user());
        }

        if ($request->filled('dietitian_id')) {
            $query->where('dietitian_id', $request->dietitian_id);
        }

        return $query->orderBy('start_date', 'desc')->paginate(25)->withQueryString();
    }

    // ─── Clinical Summary ────────────────────────────────────────────

    public function clinicalServicesSummary(Carbon $from, Carbon $to): array
    {
        return [
            'lab'       => $this->labActivitySummary($from, $to),
            'radiology' => $this->radiologyActivitySummary($from, $to),
            'pharmacy'  => $this->pharmacyActivitySummary($from, $to),
            'surgery'   => $this->surgeryActivitySummary($from, $to),
            'diet'      => $this->dietActivitySummary($from, $to),
        ];
    }
}
