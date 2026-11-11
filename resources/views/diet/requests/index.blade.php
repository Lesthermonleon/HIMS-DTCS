@extends('layouts.app')
@section('title', 'Diet Requests')
@section('page-title', 'Diet & Nutrition Requests')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('diet.dashboard') }}">DNMS</a></li>
    <li class="breadcrumb-item active">Requests</li>
@endsection

@section('content')
<div class="card">
    <div class="card-header d-flex flex-wrap gap-2 align-items-center">
        {{-- Search & Filters --}}
        <form class="d-flex gap-2 flex-wrap flex-grow-1" method="GET" id="filter-form">
            <input type="text" name="search" id="filter-search" class="form-control form-control-sm" placeholder="Search request no, patient…" value="{{ request('search') }}" style="max-width:220px">
            <select name="status" id="filter-status" class="form-select form-select-sm" style="max-width:145px">
                <option value="">All Statuses</option>
                @foreach($statuses as $s)
                    <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ $s }}</option>
                @endforeach
            </select>
            <button class="btn btn-primary btn-sm d-none">Filter</button>
            @if(request()->hasAny(['search','status']))
                <a href="{{ route('diet.requests.index') }}" class="btn btn-outline-secondary btn-sm" id="filter-clear">Clear</a>
            @endif
        </form>
        @if(auth()->user()->hasRole('doctor'))
            <a href="{{ route('diet.requests.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle me-1"></i>New Request</a>
        @endif
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Request No</th><th>Patient</th><th>Doctor</th>
                        <th>Diet Type</th><th>Allergies & Restrictions</th>
                        <th>Status</th><th>Requested At</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody id="diet-requests-table-body">
                @forelse($dietRequests as $req)
                <tr>
                    <td><a href="{{ route('diet.requests.show', $req) }}" class="fw-semibold">{{ $req->request_no }}</a></td>
                    <td>
                        {{ $req->patient->last_name }}, {{ $req->patient->first_name }}<br>
                        <small class="text-muted">{{ $req->patient->patient_no }}</small>
                    </td>
                    <td>{{ $req->doctor->name }}</td>
                    <td><span class="badge bg-info text-dark" style="font-size: 0.78rem;">{{ $req->diet_type }}</span></td>
                    <td>
                        @if($req->allergies || $req->food_restrictions)
                            <div style="font-size:0.75rem; max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                @if($req->allergies) <strong class="text-danger">Allergies:</strong> {{ $req->allergies }} @endif
                                @if($req->food_restrictions) | <strong>Restrictions:</strong> {{ $req->food_restrictions }} @endif
                            </div>
                        @else
                            <small class="text-muted">None</small>
                        @endif
                    </td>
                    <td><span class="badge bg-{{ $req->statusBadge }}">{{ $req->status }}</span></td>
                    <td><small>{{ $req->requested_at->format('M d, Y H:i') }}</small></td>
                    <td>
                        <div class="table-actions">
                            <a href="{{ route('diet.requests.show', $req) }}" class="table-action-btn action-view" title="View Request Details" aria-label="View Request Details"><i class="bi bi-eye"></i></a>
                            @can('update', $req)
                                <a href="{{ route('diet.requests.edit', $req) }}" class="table-action-btn action-edit" title="Edit Request" aria-label="Edit Request"><i class="bi bi-pencil"></i></a>
                            @endcan
                            @if($req->status === 'Pending' && !$req->dietPlan)
                                @can('create', [\App\Models\DietPlan::class, $req])
                                    <a href="{{ route('diet.plans.create', ['diet_request_id' => $req->id]) }}" class="table-action-btn action-success" title="Create Diet Plan" aria-label="Create Diet Plan"><i class="bi bi-file-earmark-plus"></i></a>
                                @endcan
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center py-4 text-muted">No diet requests found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div id="diet-requests-pagination-container">
        @if($dietRequests->hasPages())
            <div class="card-footer">{{ $dietRequests->links() }}</div>
        @endif
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const searchInput = document.getElementById('filter-search');
        const statusSelect = document.getElementById('filter-status');
        const filterForm = document.getElementById('filter-form');
        
        let searchTimeout;

        function performFilter(resetPage = true) {
            if (resetPage) {
                let pageInput = filterForm.querySelector('input[name="page"]');
                if (pageInput) {
                    pageInput.value = '1';
                }
            }
            const formData = new FormData(filterForm);
            const params = new URLSearchParams(formData);
            const newUrl = `${window.location.pathname}?${params.toString()}`;
            
            window.history.replaceState(null, '', newUrl);

            fetch(newUrl, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                
                document.getElementById('diet-requests-table-body').innerHTML = doc.getElementById('diet-requests-table-body').innerHTML;
                document.getElementById('diet-requests-pagination-container').innerHTML = doc.getElementById('diet-requests-pagination-container').innerHTML;
                
                // Keep Clear button in sync if present
                const clearBtn = document.getElementById('filter-clear');
                const newClearBtn = doc.getElementById('filter-clear');
                if (clearBtn && !newClearBtn) {
                    clearBtn.remove();
                } else if (!clearBtn && newClearBtn) {
                    filterForm.appendChild(newClearBtn);
                    setupClearListener();
                }
            })
            .catch(err => console.error('Error filtering diet requests:', err));
        }

        function setupClearListener() {
            const clearBtn = document.getElementById('filter-clear');
            if (clearBtn) {
                clearBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    searchInput.value = '';
                    statusSelect.value = '';
                    performFilter(true);
                });
            }
        }

        filterForm.addEventListener('submit', function (e) {
            e.preventDefault();
            performFilter(true);
        });

        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => performFilter(true), 300);
        });

        statusSelect.addEventListener('change', () => performFilter(true));
        setupClearListener();

        // Intercept pagination clicks dynamically
        document.addEventListener('click', function (e) {
            const pageLink = e.target.closest('#diet-requests-pagination-container a');
            if (pageLink) {
                e.preventDefault();
                const urlObj = new URL(pageLink.href);
                const page = urlObj.searchParams.get('page');
                
                let pageInput = filterForm.querySelector('input[name="page"]');
                if (!pageInput) {
                    pageInput = document.createElement('input');
                    pageInput.type = 'hidden';
                    pageInput.name = 'page';
                    filterForm.appendChild(pageInput);
                }
                pageInput.value = page;
                performFilter(false);
            }
        });
    });
</script>
@endpush
@endsection
