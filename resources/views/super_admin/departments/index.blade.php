@extends('layouts.app')
@section('title', 'DTCS Subsystem Departments & Resource Monitor — Super Admin')
@section('page-title', 'DTCS Subsystem Departments & Resource Monitor')

@section('content')
<div class="row g-3">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-transparent py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-diagram-3 me-2 text-primary"></i>DTCS Subsystem Departments & Resource Monitor</h5>
                    <small class="text-muted">Operational divisions, active clinical personnel assignments, and subsystem activity across LIS, RIS, PMS, SORS, and DNMS</small>
                </div>
                <div>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2">
                        <i class="bi bi-shield-check me-1"></i>5 Predefined DTCS Subsystems
                    </span>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="p-3 bg-light border-bottom">
                    <div class="d-flex align-items-center gap-2 text-muted small">
                        <i class="bi bi-info-circle text-primary fs-5 flex-shrink-0"></i>
                        <span>
                            These 5 clinical departments represent the core architectural boundaries of the <strong>Diagnostic, Treatment &amp; Clinical Services (DTCS)</strong> subsystem. Department assignments and workload statistics are dynamically aggregated from live user profiles and operational clinical records. Subsystem boundaries are predefined and cannot be created or altered through ad-hoc records.
                        </span>
                    </div>
                </div>

                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show m-3 py-2" role="alert">
                        <i class="bi bi-check-circle me-1"></i>{{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Code</th>
                                <th>Department Name</th>
                                <th>DTCS Module</th>
                                <th>Description / Clinical Scope</th>
                                <th>Assigned Staff</th>
                                <th>Subsystem Activity</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($departments as $dept)
                                <tr>
                                    <td class="font-monospace fw-bold text-primary">{{ $dept['code'] }}</td>
                                    <td>
                                        <div class="fw-bold text-dark">{{ $dept['name'] }}</div>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary text-white px-2 py-1 font-monospace">{{ $dept['module'] }}</span>
                                        <span class="badge bg-light text-secondary border ms-1">{{ $dept['type'] }}</span>
                                    </td>
                                    <td>
                                        <small class="text-secondary">{{ $dept['description'] }}</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border">{{ $dept['staff_count'] }} Personnel</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border">{{ $dept['request_count'] }} Clinical Records</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                                            Active
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
