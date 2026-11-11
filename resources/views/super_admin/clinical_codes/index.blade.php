@extends('layouts.app')

@section('title', 'Clinical Code Master Management (ICD / CPT / LOINC)')
@section('page-title', 'Super Admin — Clinical Code Systems')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('super-admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active" aria-current="page">Clinical Codes</li>
@endsection

@section('content')
<div class="container-fluid px-0">
    {{-- Governance Notice Banner --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h5 class="fw-bold mb-1 text-dark"><i class="bi bi-tags-fill text-teal me-2" style="color:#0d9488;"></i> Standard Clinical Terminology Systems</h5>
            <p class="text-muted small mb-0">
                ICD-11 (WHO Diagnosis), CPT (AMA Procedures), and LOINC (Regenstrief Laboratory Observations) are standardized coding systems. Official datasets require verified licensing credentials before dataset publishing can be authorized. Codes are never fabricated.
            </p>
        </div>
    </div>

    {{-- System Registry Cards --}}
    <div class="row g-4 mb-4">
        @foreach($systems as $sys)
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                        <span class="badge bg-dark-subtle text-dark font-monospace fs-6">{{ $sys->system_key }}</span>
                        @if($sys->license_status === 'verified')
                            <span class="badge bg-success-subtle text-success border border-success-subtle">
                                <i class="bi bi-patch-check-fill me-1"></i> License Verified
                            </span>
                        @else
                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i> License Unverified
                            </span>
                        @endif
                    </div>
                    <div class="card-body small">
                        <h6 class="fw-bold mb-1">{{ $sys->name }}</h6>
                        <div class="text-muted mb-3 text-capitalize">Kind: <strong>{{ $sys->kind }}</strong></div>

                        <ul class="list-unstyled mb-3 border-top pt-2">
                            <li class="d-flex justify-content-between mb-1">
                                <span class="text-muted">License Ref:</span>
                                <strong class="text-dark">{{ $sys->license_reference ?? 'Pending' }}</strong>
                            </li>
                            <li class="d-flex justify-content-between mb-1">
                                <span class="text-muted">Jurisdiction:</span>
                                <strong class="text-dark">{{ $sys->jurisdiction ?? 'Not set' }}</strong>
                            </li>
                            <li class="d-flex justify-content-between">
                                <span class="text-muted">Active Version:</span>
                                <strong class="text-primary">{{ $sys->activeVersion?->version_label ?? 'None Published' }}</strong>
                            </li>
                        </ul>

                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary w-50"
                                    data-bs-toggle="modal" data-bs-target="#licenseModal-{{ $sys->id }}">
                                Verify License
                            </button>
                            <button type="button" class="btn btn-sm btn-primary w-50"
                                    data-bs-toggle="modal" data-bs-target="#stageModal-{{ $sys->id }}">
                                Stage CSV
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Verify License Modal --}}
            <div class="modal fade" id="licenseModal-{{ $sys->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <form action="{{ route('super-admin.clinical-codes.verify-license', $sys) }}" method="POST" class="modal-content">
                        @csrf
                        @method('PATCH')
                        <div class="modal-header">
                            <h6 class="modal-title fw-bold">Licensing Governance [{{ $sys->system_key }}]</h6>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body small">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">License Status</label>
                                <select name="license_status" class="form-select form-select-sm" required>
                                    <option value="unverified" {{ $sys->license_status === 'unverified' ? 'selected' : '' }}>Unverified (Imports cannot be published)</option>
                                    <option value="verified" {{ $sys->license_status === 'verified' ? 'selected' : '' }}>Verified (Hospital holds active usage license)</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">License / Contract Reference</label>
                                <input type="text" name="license_reference" value="{{ $sys->license_reference }}" class="form-control form-control-sm" placeholder="e.g. WHO-ICD11-LIC-2026-PH">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Jurisdiction / Territory</label>
                                <input type="text" name="jurisdiction" value="{{ $sys->jurisdiction }}" class="form-control form-control-sm" placeholder="e.g. Philippines / DOH">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Governance Notes</label>
                                <textarea name="license_notes" class="form-control form-control-sm" rows="2" placeholder="Accreditation or licensing audit remarks">{{ $sys->license_notes }}</textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-sm btn-primary">Update Licensing Credentials</button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Stage CSV Dataset Modal --}}
            <div class="modal fade" id="stageModal-{{ $sys->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <form action="{{ route('super-admin.clinical-codes.stage', $sys) }}" method="POST" enctype="multipart/form-data" class="modal-content">
                        @csrf
                        <div class="modal-header">
                            <h6 class="modal-title fw-bold">Stage Official Dataset for {{ $sys->system_key }}</h6>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body small">
                            <div class="alert alert-light border small mb-3">
                                <i class="bi bi-file-earmark-spreadsheet me-1"></i> Required CSV columns: <code>code</code>, <code>display</code> (or <code>description</code>).
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Version Label *</label>
                                <input type="text" name="version_label" class="form-control form-control-sm" placeholder="e.g. 2026-v1.0" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Effective Date</label>
                                <input type="date" name="effective_date" value="{{ now()->toDateString() }}" class="form-control form-control-sm">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Official Dataset File (.CSV) *</label>
                                <input type="file" name="dataset_file" accept=".csv,.txt" class="form-control form-control-sm" required>
                                <div class="form-text">Max file size: 10MB. Format will be validated on import.</div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-sm btn-primary">Validate & Stage Dataset</button>
                        </div>
                    </form>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Versions & Validation History Table --}}
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3">
            <h6 class="m-0 fw-bold text-dark"><i class="bi bi-clock-history me-2 text-primary"></i> Dataset Versions & Validation Reports</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-uppercase">
                        <tr>
                            <th class="ps-3">System</th>
                            <th>Version</th>
                            <th>Effective Date</th>
                            <th>Source File / SHA256</th>
                            <th>Validation Outcome</th>
                            <th>Status</th>
                            <th class="pe-3 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        @forelse($versions as $ver)
                            <tr>
                                <td class="ps-3 fw-bold font-monospace text-primary">{{ $ver->system->system_key }}</td>
                                <td class="fw-semibold">{{ $ver->version_label }}</td>
                                <td>{{ $ver->effective_date ? $ver->effective_date->format('M d, Y') : '—' }}</td>
                                <td>
                                    <div class="text-dark">{{ $ver->source_filename }}</div>
                                    <div class="font-monospace text-muted" style="font-size: 10px;">{{ substr($ver->source_sha256, 0, 16) }}...</div>
                                </td>
                                <td>
                                    <span class="badge bg-success-subtle text-success border me-1">{{ $ver->valid_rows }} Valid</span>
                                    @if($ver->invalid_rows > 0)
                                        <span class="badge bg-danger-subtle text-danger border me-1">{{ $ver->invalid_rows }} Invalid</span>
                                    @endif
                                    @if($ver->duplicate_rows > 0)
                                        <span class="badge bg-warning-subtle text-warning border">{{ $ver->duplicate_rows }} Duplicates</span>
                                    @endif
                                </td>
                                <td>
                                    @if($ver->status === 'published')
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">Published (Active)</span>
                                    @elseif($ver->status === 'staged')
                                        <span class="badge bg-info-subtle text-info border border-info-subtle">Staged</span>
                                    @elseif($ver->status === 'superseded')
                                        <span class="badge bg-secondary-subtle text-secondary border">Superseded</span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger border">Rolled Back</span>
                                    @endif
                                </td>
                                <td class="pe-3 text-end">
                                    @if($ver->status === 'staged')
                                        <form action="{{ route('super-admin.clinical-codes.publish', $ver) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-success py-0 px-2" {{ ($ver->invalid_rows > 0 || ! $ver->system->isVerified()) ? 'disabled' : '' }}>
                                                Publish
                                            </button>
                                        </form>
                                    @elseif($ver->status === 'published')
                                        <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2" data-bs-toggle="modal" data-bs-target="#rollbackModal-{{ $ver->id }}">
                                            Rollback
                                        </button>
                                    @endif
                                </td>
                            </tr>

                            {{-- Rollback Modal --}}
                            @if($ver->status === 'published')
                                <div class="modal fade" id="rollbackModal-{{ $ver->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <form action="{{ route('super-admin.clinical-codes.rollback', $ver) }}" method="POST" class="modal-content">
                                            @csrf
                                            <div class="modal-header">
                                                <h6 class="modal-title fw-bold">Rollback Version [{{ $ver->version_label }}]</h6>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body small">
                                                <p class="text-danger mb-3">Rolling back this version will reinstate the previous active version or deactivate catalog resolution for {{ $ver->system->name }}. Historical patient records referencing these codes remain intact.</p>
                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold">Rollback Audit Reason *</label>
                                                    <input type="text" name="rollback_reason" class="form-control form-control-sm" placeholder="Specify clinical or governance reason" required minlength="5">
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-sm btn-danger">Confirm Rollback</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            @endif
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    No clinical code versions staged or published yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($versions->hasPages())
                <div class="p-3 border-top">
                    {{ $versions->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
