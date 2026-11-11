@extends('layouts.app')
@section('title', 'My Treatment Plans — Patient Portal')
@section('page-title', 'My Clinical Treatment Plans')

@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-transparent d-flex justify-content-between align-items-center py-3">
        <div>
            <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-heart-pulse me-2 text-danger"></i>Treatment & Nutritional Regimens</h5>
            <small class="text-muted">Clinical care regimens and nutritional care plans assigned to your encounter</small>
        </div>
        <span class="badge bg-danger">{{ $dietPlans->count() }} Plans</span>
    </div>

    <div class="card-body p-0">
        @if($dietPlans->isEmpty())
            <div class="text-center py-5 text-muted">
                <i class="bi bi-journal-medical fs-1 opacity-50"></i>
                <p class="mt-2 mb-0">No active inpatient treatment plans or dietary regimens on file.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Plan Reference</th>
                            <th>Diet / Care Regimen</th>
                            <th>Special Diet Notes / Restrictions</th>
                            <th>Formulated Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($dietPlans as $plan)
                            <tr>
                                <td class="font-monospace fw-semibold">DP-{{ $plan->id }}</td>
                                <td class="fw-bold text-dark">{{ $plan->dietRequest?->diet_type ?? 'Nutritional Care' }}</td>
                                <td>{{ $plan->notes ?? 'Standard hospital dietary guidelines.' }}</td>
                                <td>{{ $plan->created_at->format('M d, Y') }}</td>
                                <td>
                                    <span class="badge {{ $plan->status === 'Active' ? 'bg-success' : 'bg-secondary' }}">
                                        {{ $plan->status ?? 'Active' }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
