@extends('layouts.app')
@section('title', 'Pharmacy Reports & Financial Analytics')
@section('page-title', 'Prescription Activity Report')
@section('content')
@include('reports._print-header', ['reportTitle' => 'Prescription Activity Report & Pharmacy Financial Summary'])
@php
    $statuses = ['Pending', 'Verified', 'Partially Dispensed', 'Dispensed', 'Rejected', 'Returned', 'Cancelled'];
    $fin = $pmsReport['financial_summary'] ?? [
        'gross_sales' => 0,
        'discounts_total' => 0,
        'refunds_voids_total' => 0,
        'net_sales' => 0,
        'vat_collected' => 0,
        'completed_count' => 0,
        'voided_count' => 0,
        'payment_method_breakdown' => ['Cash' => 0, 'GCash' => 0, 'Card' => 0, 'Hospital Billing' => 0],
    ];
    $invRep = $pmsReport['inventory_report'] ?? [
        'available' => collect(),
        'low_stock' => collect(),
        'out_of_stock' => collect(),
        'near_expiry' => collect(),
        'expired' => collect(),
    ];
@endphp

@include('reports._filters', compact('from', 'to', 'statuses', 'doctors'))

{{-- Sub-Report Quick Navigation --}}
<div class="d-flex flex-wrap gap-2 mb-4 print-hide">
    <a href="{{ route('reports.pharmacy.activity') }}" class="btn btn-sm btn-primary fw-semibold">
        <i class="bi bi-bar-chart-line me-1"></i> Full Pharmacy &amp; Financial Report
    </a>
    <a href="{{ route('reports.pharmacy.dispensing') }}" class="btn btn-sm btn-outline-success fw-semibold">
        <i class="bi bi-capsule me-1"></i> Prescription Dispensing Report
    </a>
    <a href="{{ route('reports.pharmacy.pending') }}" class="btn btn-sm btn-outline-warning fw-semibold">
        <i class="bi bi-hourglass-split me-1"></i> Pending Prescriptions Report
    </a>
    <button onclick="window.print()" class="btn btn-sm btn-outline-secondary ms-auto">
        <i class="bi bi-printer me-1"></i> Print Complete Report
    </button>
</div>

{{-- 1. Clinical Prescription Summary Cards --}}
@include('reports._summary-cards', ['cards' => [
    ['label' => 'Total Prescriptions','value' => $summary['total'],     'icon' => 'bi-capsule',         'color' => 'primary'],
    ['label' => 'Verified',           'value' => $summary['verified'],  'icon' => 'bi-check2-square',   'color' => 'info'],
    ['label' => 'Dispensed',          'value' => $summary['dispensed'], 'icon' => 'bi-bag-check',       'color' => 'success'],
    ['label' => 'Pending',            'value' => $summary['pending'],   'icon' => 'bi-hourglass-split', 'color' => 'warning'],
]])

{{-- 2. Financial Summary Section (Gross Sales, Discounts, Refunds/Voids, Net Sales, VAT, Payment Breakdown) --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <div>
            <h6 class="fw-bold mb-0"><i class="bi bi-cash-stack me-2 text-success"></i>Financial Summary (Pharmacy POS)</h6>
            <span class="text-muted small">Period: {{ $from->format('M d, Y') }} — {{ $to->format('M d, Y') }}</span>
        </div>
        <span class="badge bg-success-subtle text-success border border-success-subtle">
            {{ $fin['completed_count'] }} Completed Sales &bull; {{ $fin['voided_count'] }} Void/Refund
        </span>
    </div>
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-6 col-md-4 col-xl-2">
                <div class="p-3 rounded-3 bg-light border h-100">
                    <div class="text-muted small fw-semibold text-uppercase">Gross Sales</div>
                    <div class="fs-5 fw-bold text-dark mt-1">₱{{ number_format((float) $fin['gross_sales'], 2) }}</div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="p-3 rounded-3 bg-warning bg-opacity-10 border border-warning border-opacity-25 h-100">
                    <div class="text-muted small fw-semibold text-uppercase">Discounts</div>
                    <div class="fs-5 fw-bold text-warning-emphasis mt-1">-₱{{ number_format((float) $fin['discounts_total'], 2) }}</div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="p-3 rounded-3 bg-danger bg-opacity-10 border border-danger border-opacity-25 h-100">
                    <div class="text-muted small fw-semibold text-uppercase">Refunds / Voids</div>
                    <div class="fs-5 fw-bold text-danger mt-1">₱{{ number_format((float) $fin['refunds_voids_total'], 2) }}</div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="p-3 rounded-3 bg-info bg-opacity-10 border border-info border-opacity-25 h-100">
                    <div class="text-muted small fw-semibold text-uppercase">VAT Collected</div>
                    <div class="fs-5 fw-bold text-info-emphasis mt-1">₱{{ number_format((float) $fin['vat_collected'], 2) }}</div>
                </div>
            </div>
            <div class="col-12 col-md-8 col-xl-4">
                <div class="p-3 rounded-3 bg-success bg-opacity-10 border border-success border-opacity-25 h-100">
                    <div class="text-muted small fw-semibold text-uppercase">Net Sales (Completed Revenue)</div>
                    <div class="fs-4 fw-bold text-success mt-1">₱{{ number_format((float) $fin['net_sales'], 2) }}</div>
                </div>
            </div>
        </div>

        {{-- Payment Method Breakdown --}}
        <div class="border-top pt-3">
            <div class="small fw-bold text-uppercase text-muted mb-2">Payment Method Breakdown</div>
            <div class="row g-2">
                @foreach($fin['payment_method_breakdown'] as $method => $amount)
                    <div class="col-6 col-md-3">
                        <div class="p-2.5 rounded border bg-white d-flex justify-content-between align-items-center">
                            <span class="small fw-semibold text-secondary">{{ $method }}</span>
                            <span class="fw-bold text-dark small">₱{{ number_format((float) $amount, 2) }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

{{-- 3. Sales Reports: Sales by Medicine & Sales by Pharmacist --}}
<div class="row g-4 mb-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h6 class="fw-bold mb-0"><i class="bi bi-capsule-pill me-2 text-primary"></i>Sales by Medicine</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Code</th>
                                <th>Medicine</th>
                                <th>Category</th>
                                <th class="text-center">Units Sold</th>
                                <th class="text-end pe-3">Gross Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($pmsReport['sales_by_medicine'] ?? [] as $medRow)
                                <tr>
                                    <td class="ps-3 font-monospace">{{ $medRow->medicine_code }}</td>
                                    <td class="fw-semibold">{{ $medRow->medication_name }}</td>
                                    <td>{{ $medRow->category }}</td>
                                    <td class="text-center fw-bold">{{ number_format((int) $medRow->total_qty) }}</td>
                                    <td class="text-end pe-3 fw-bold text-success">₱{{ number_format((float) $medRow->total_revenue, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center py-4 text-muted">No medicine POS sales in this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h6 class="fw-bold mb-0"><i class="bi bi-person-badge me-2 text-info"></i>Sales by Pharmacist (Activity Report)</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Pharmacist</th>
                                <th class="text-center">Transactions</th>
                                <th class="text-end">Discounts</th>
                                <th class="text-end pe-3">Net Sales</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($pmsReport['sales_by_pharmacist'] ?? [] as $phRow)
                                <tr>
                                    <td class="ps-3 fw-semibold">{{ $phRow->pharmacist?->name ?? 'Pharmacist' }}</td>
                                    <td class="text-center fw-bold">{{ $phRow->transaction_count }}</td>
                                    <td class="text-end text-warning-emphasis">₱{{ number_format((float) $phRow->total_discounts, 2) }}</td>
                                    <td class="text-end pe-3 fw-bold text-success">₱{{ number_format((float) $phRow->total_sales, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center py-4 text-muted">No pharmacist sales activity in this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- 4. Discount Report & Refund / Void Report --}}
<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h6 class="fw-bold mb-0"><i class="bi bi-percent me-2 text-warning"></i>Discount Report (Senior / PWD / Employee / Promo)</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Txn No.</th>
                                <th>Discount Type &amp; Ref</th>
                                <th>Authorized By</th>
                                <th class="text-end pe-3">Discount Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($pmsReport['discounted_transactions'] ?? [] as $dTxn)
                                <tr>
                                    <td class="ps-3 font-monospace fw-semibold">{{ $dTxn->transaction_no }}</td>
                                    <td>
                                        <span class="text-uppercase fw-bold">{{ str_replace('_', ' ', $dTxn->discount_type) }}</span>
                                        @if($dTxn->discount_reference)
                                            <span class="d-block text-muted" style="font-size: 0.72rem;">Ref: {{ $dTxn->discount_reference }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $dTxn->discountAuthorizedBy?->name ?? $dTxn->pharmacist?->name ?? 'Pharmacist' }}</td>
                                    <td class="text-end pe-3 fw-bold text-warning-emphasis">-₱{{ number_format((float) $dTxn->discount_amount, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center py-4 text-muted">No discounted POS transactions in this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h6 class="fw-bold mb-0"><i class="bi bi-arrow-counterclockwise me-2 text-danger"></i>Refund &amp; Void Report</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Txn No.</th>
                                <th>Status</th>
                                <th>Reason &amp; Authorized By</th>
                                <th class="text-end pe-3">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($pmsReport['refund_void_transactions'] ?? [] as $vTxn)
                                <tr>
                                    <td class="ps-3 font-monospace fw-semibold">{{ $vTxn->transaction_no }}</td>
                                    <td><span class="badge bg-danger">{{ $vTxn->status }}</span></td>
                                    <td>
                                        <div>{{ $vTxn->void_reason }}</div>
                                        <span class="text-muted" style="font-size: 0.72rem;">By: {{ $vTxn->voidedByUser?->name ?? 'Pharmacist' }}</span>
                                    </td>
                                    <td class="text-end pe-3 fw-bold text-danger">₱{{ number_format((float) $vTxn->grand_total, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center py-4 text-muted">No voided or refunded transactions in this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- 5. Inventory Stock & Medicine Expiry Reports Summary --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0"><i class="bi bi-boxes me-2 text-success"></i>Inventory &amp; Medicine Expiry Reports</h6>
        @if(Route::has('pharmacy.inventory.items'))
            <a href="{{ route('pharmacy.inventory.items') }}" class="btn btn-sm btn-outline-success print-hide">Open Inventory Workspace</a>
        @endif
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="border rounded-3 p-3 h-100">
                    <div class="fw-bold text-warning-emphasis small text-uppercase mb-2">
                        Low-Stock &amp; Out-of-Stock Report ({{ $invRep['low_stock']->count() + $invRep['out_of_stock']->count() }})
                    </div>
                    <ul class="list-unstyled mb-0 small space-y-1">
                        @forelse($invRep['out_of_stock']->concat($invRep['low_stock']) as $stItem)
                            <li class="d-flex justify-content-between py-1 border-bottom">
                                <span>{{ $stItem['medication_name'] }}</span>
                                <span class="fw-bold {{ $stItem['qty'] <= 0 ? 'text-danger' : 'text-warning-emphasis' }}">
                                    {{ $stItem['qty'] }} left
                                </span>
                            </li>
                        @empty
                            <li class="text-muted">All monitored medicines are above reorder threshold.</li>
                        @endforelse
                    </ul>
                </div>
            </div>

            <div class="col-md-4">
                <div class="border rounded-3 p-3 h-100">
                    <div class="fw-bold text-danger small text-uppercase mb-2">
                        Near-Expiry &amp; Expired Medicine Report ({{ $invRep['near_expiry']->count() + $invRep['expired']->count() }})
                    </div>
                    <ul class="list-unstyled mb-0 small">
                        @forelse($invRep['expired']->concat($invRep['near_expiry']) as $expItem)
                            <li class="d-flex justify-content-between py-1 border-bottom">
                                <span>{{ $expItem['medication_name'] }} ({{ $expItem['lot_number'] }})</span>
                                <span class="fw-bold {{ $expItem['is_expired'] ? 'text-danger' : 'text-warning-emphasis' }}">
                                    {{ $expItem['expiry_date'] }}
                                </span>
                            </li>
                        @empty
                            <li class="text-muted">No expired or near-expiry medicine batches.</li>
                        @endforelse
                    </ul>
                </div>
            </div>

            <div class="col-md-4">
                <div class="border rounded-3 p-3 h-100">
                    <div class="fw-bold text-info-emphasis small text-uppercase mb-2">
                        Partial Dispensing Report ({{ ($pmsReport['partial_dispensing_records'] ?? collect())->count() }})
                    </div>
                    <ul class="list-unstyled mb-0 small">
                        @forelse($pmsReport['partial_dispensing_records'] ?? [] as $partRx)
                            <li class="d-flex justify-content-between py-1 border-bottom">
                                <span>{{ $partRx->prescription_no }} ({{ $partRx->patient?->full_name ?? 'Patient' }})</span>
                                <span class="badge bg-info-subtle text-info">Partially Dispensed</span>
                            </li>
                        @empty
                            <li class="text-muted">No partially dispensed prescriptions in this period.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- 6. Prescription Records Table --}}
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h6 class="fw-bold mb-0"><i class="bi bi-table me-2"></i>Prescription Records</h6>
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm print-hide"><i class="bi bi-printer me-1"></i>Print</button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th class="ps-3">Rx #</th><th>Patient</th><th>Doctor</th><th>Items</th><th>Status</th><th>Prescribed</th></tr></thead>
                <tbody>
                    @forelse($records as $r)
                    <tr>
                        <td class="ps-3 fw-semibold">{{ $r->prescription_no }}</td>
                        <td>{{ $r->patient->full_name ?? '—' }}</td>
                        <td>{{ $r->doctor->name ?? '—' }}</td>
                        <td>{{ $r->items->count() }} item(s)</td>
                        <td>{!! $r->statusBadge !!}</td>
                        <td class="text-muted small">{{ $r->prescribed_at?->format('M d, Y h:i A') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center py-4 text-muted"><i class="bi bi-inbox me-2"></i>No prescriptions found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($records->hasPages())<div class="card-footer bg-white print-hide">{{ $records->links() }}</div>@endif
</div>
@push('styles')<style>@media print { .print-hide, #sidebar, .topbar, nav { display: none !important; } .print-header { display: block !important; } .card { border: none !important; box-shadow: none !important; } }</style>@endpush
@endsection
