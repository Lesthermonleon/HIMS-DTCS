@extends('layouts.app')
@section('title', 'My Bills & Payments — Patient Portal')
@section('page-title', 'Billing Statements & Receipts')

@section('content')
<div class="row g-3">

    {{-- Financial Summary Cards --}}
    <div class="col-md-4">
        <div class="card shadow-sm border-0 bg-primary text-white">
            <div class="card-body p-3">
                <span class="small text-uppercase text-white-50 fw-semibold">Total Hospital Charges</span>
                <h3 class="mb-0 mt-1 fw-bold">₱{{ number_format($totalBilled, 2) }}</h3>
                <small class="text-white-50">Consultations, diagnostic & pharmacy charges</small>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card shadow-sm border-0 bg-success text-white">
            <div class="card-body p-3">
                <span class="small text-uppercase text-white-50 fw-semibold">Total Payments Recorded</span>
                <h3 class="mb-0 mt-1 fw-bold">₱{{ number_format($totalPaid, 2) }}</h3>
                <small class="text-white-50">Settled via hospital cashier / payment gateway</small>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card shadow-sm border-0 bg-danger text-white">
            <div class="card-body p-3">
                <span class="small text-uppercase text-white-50 fw-semibold">Outstanding Balance Due</span>
                <h3 class="mb-0 mt-1 fw-bold">₱{{ number_format($balanceDue, 2) }}</h3>
                <small class="text-white-50">Payable at clinic reception upon arrival</small>
            </div>
        </div>
    </div>

    {{-- Invoices Table --}}
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center py-3">
                <div>
                    <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-receipt me-2 text-primary"></i>Billing Statements</h5>
                    <small class="text-muted">Itemized patient statements and official payment receipts</small>
                </div>
                <span class="badge bg-secondary">{{ $invoices->total() }} Invoices</span>
            </div>
            <div class="card-body p-0">
                @if($invoices->isEmpty())
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-cash-stack fs-1 opacity-50"></i>
                        <p class="mt-2 mb-0">No billing statements currently recorded for this account.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Invoice No</th>
                                    <th>Description / Service</th>
                                    <th>Total Amount</th>
                                    <th>Paid Amount</th>
                                    <th>Balance</th>
                                    <th>Payment Ref</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($invoices as $inv)
                                    <tr>
                                        <td class="font-monospace fw-semibold">{{ $inv->invoice_no }}</td>
                                        <td>{{ $inv->description }}</td>
                                        <td class="fw-bold">₱{{ number_format($inv->total_amount, 2) }}</td>
                                        <td class="text-success">₱{{ number_format($inv->paid_amount, 2) }}</td>
                                        <td class="fw-bold {{ $inv->balance > 0 ? 'text-danger' : 'text-muted' }}">₱{{ number_format($inv->balance, 2) }}</td>
                                        <td>
                                            @if($inv->payment_reference)
                                                <span class="badge bg-light text-dark border font-monospace">{{ $inv->payment_reference }}</span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>{{ $inv->created_at->format('M d, Y') }}</td>
                                        <td>
                                            @php
                                                $statusClass = match($inv->status) {
                                                    'Paid'           => 'bg-success',
                                                    'Partially Paid' => 'bg-warning text-dark',
                                                    'Unpaid'         => 'bg-danger',
                                                    default          => 'bg-secondary',
                                                };
                                            @endphp
                                            <span class="badge {{ $statusClass }}">{{ $inv->status }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($invoices->hasPages())
                        <div class="card-footer bg-transparent py-3">
                            {{ $invoices->links() }}
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>

</div>
@endsection
