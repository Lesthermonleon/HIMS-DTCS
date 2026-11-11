@extends('layouts.app')
@section('title', 'My Prescriptions — Patient Portal')
@section('page-title', 'My Medication Prescriptions')

@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-transparent d-flex justify-content-between align-items-center py-3">
        <div>
            <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-capsule me-2 text-warning"></i>Medication Prescriptions</h5>
            <small class="text-muted">Doctor-authorized prescriptions verified and dispensed through the hospital pharmacy</small>
        </div>
        <span class="badge bg-warning text-dark">{{ $prescriptions->total() }} Orders</span>
    </div>

    <div class="card-body p-0">
        @if($prescriptions->isEmpty())
            <div class="text-center py-5 text-muted">
                <i class="bi bi-prescription fs-1 opacity-50"></i>
                <p class="mt-2 mb-0">No prescriptions found on your record.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Prescription No</th>
                            <th>Prescribing Physician</th>
                            <th>Medication Name & Details</th>
                            <th>Dosage & Frequency</th>
                            <th>Instructions / Duration</th>
                            <th>Issued Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($prescriptions as $rx)
                            <tr>
                                <td class="font-monospace fw-semibold">{{ $rx->prescription_no }}</td>
                                <td>
                                    <div class="fw-bold text-dark">Dr. {{ $rx->doctor?->name ?? 'Physician' }}</div>
                                    <small class="text-muted">{{ $rx->doctor?->department ?? 'Clinical' }}</small>
                                </td>
                                <td>
                                    @foreach($rx->items as $item)
                                        <div class="fw-semibold text-dark">{{ $item->medicine_name }}</div>
                                    @endforeach
                                </td>
                                <td>
                                    @foreach($rx->items as $item)
                                        <div class="small text-secondary">{{ $item->dosage }} · {{ $item->frequency }}</div>
                                    @endforeach
                                </td>
                                <td>
                                    @foreach($rx->items as $item)
                                        <div class="small fst-italic text-muted">{{ $item->instructions ?? ($item->duration ? $item->duration . ' days' : 'As directed') }}</div>
                                    @endforeach
                                </td>
                                <td>{{ $rx->created_at->format('M d, Y') }}</td>
                                <td>
                                    <span class="badge {{ $rx->status === 'Dispensed' ? 'bg-success' : 'bg-primary' }}">
                                        {{ $rx->status }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($prescriptions->hasPages())
                <div class="card-footer bg-transparent py-3">
                    {{ $prescriptions->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
