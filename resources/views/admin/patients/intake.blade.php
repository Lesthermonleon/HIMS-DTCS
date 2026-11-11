@extends('layouts.app')
@section('title', 'Patient Portal Accounts — DTCS HIMS')
@section('page-title', 'Patient Portal Accounts')

@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-transparent py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-person-lock me-2 text-primary"></i>Patient Portal Accounts</h5>
            <small class="text-muted">
                Link an existing patient record to a portal login. Patient master data is owned by the upstream registration
                system and cannot be created or edited here.
            </small>
        </div>
        <a href="{{ route('patients.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-people me-1"></i>Patient Directory
        </a>
    </div>

    <div class="card-body border-bottom bg-light py-2">
        <form method="GET" action="{{ route('admin.patients.intake') }}" class="row g-2">
            <div class="col-md-6">
                <input type="text" name="search" class="form-control form-control-sm" value="{{ $search }}" placeholder="Search patient no. or name" aria-label="Search patients">
            </div>
            <div class="col-md-3 d-flex gap-1">
                <button class="btn btn-sm btn-secondary w-100" type="submit">Search</button>
                @if($search !== '')
                    <a href="{{ route('admin.patients.intake') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                @endif
            </div>
        </form>
    </div>

    <div class="card-body p-0">
        @if(session('success'))
            <div class="alert alert-success m-3 py-2" role="alert">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger m-3 py-2" role="alert">
                <ul class="mb-0 small">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Patient No.</th>
                        <th>Name</th>
                        <th>Contact Email on Record</th>
                        <th>Portal Access</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($patients as $p)
                        <tr>
                            <td class="font-monospace fw-semibold">{{ $p->patient_no }}</td>
                            <td>{{ $p->full_name }}</td>
                            <td class="text-muted small">{{ $p->email ?: '—' }}</td>
                            <td>
                                @if($p->user_id)
                                    <span class="badge bg-success">Linked</span>
                                    <span class="small text-muted ms-1">{{ $p->user?->email }}</span>
                                @else
                                    <span class="badge bg-secondary">No account</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @unless($p->user_id)
                                    <form action="{{ route('admin.patients.link-portal', $p) }}" method="POST" class="d-flex flex-wrap justify-content-end align-items-center gap-2">
                                        @csrf
                                        <input type="email" name="email" required class="form-control form-control-sm" style="max-width:220px"
                                               value="{{ $p->email }}" placeholder="Portal login email" aria-label="Portal login email for {{ $p->patient_no }}">
                                        <div class="form-check small text-start">
                                            <input class="form-check-input" type="checkbox" name="identity_verified" value="1" id="idv-{{ $p->id }}" required>
                                            <label class="form-check-label" for="idv-{{ $p->id }}">Identity verified</label>
                                        </div>
                                        <button type="submit" class="btn btn-sm btn-primary">Create Portal Access</button>
                                    </form>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">No patients match the search.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($patients->hasPages())
        <div class="card-footer bg-transparent py-3">{{ $patients->links() }}</div>
    @endif
    <div class="card-footer bg-transparent small text-muted">
        The patient receives an email link to set their own password. No default password is ever assigned. Every link action is written to the audit log.
    </div>
</div>
@endsection
