@extends('layouts.app')
@section('title', 'Lab Results')
@section('page-title', 'Laboratory Results')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('lab.dashboard') }}">LIS</a></li>
    <li class="breadcrumb-item active">Results</li>
@endsection
@section('content')
<div class="card">
    <div class="card-header d-flex flex-wrap gap-2 align-items-center">
        <form class="d-flex gap-2 flex-grow-1" method="GET" id="filter-form">
            <select name="status" id="filter-status" class="form-select form-select-sm" style="max-width:150px">
                <option value="">All Statuses</option>
                @foreach($statuses as $s)<option value="{{ $s }}" {{ request('status')===$s?'selected':'' }}>{{ $s }}</option>@endforeach
            </select>
            <button class="btn btn-primary btn-sm d-none">Filter</button>
        </form>
        @if(auth()->user()?->hasRole('med-tech') && auth()->user()->hasPermission('lis.results.enter'))
            <a href="{{ route('lab.results.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle me-1"></i>Encode Result</a>
        @endif
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Test</th><th>Patient</th><th>Result</th><th>Remarks</th><th>Status</th><th>Technologist</th><th>Released At</th><th>Actions</th></tr></thead>
                <tbody id="lab-results-table-body">
                @forelse($labResults as $result)
                <tr>
                    <td class="fw-semibold">{{ $result->requestItem->labTest->name }}</td>
                    <td>{{ $result->requestItem->labRequest->patient->last_name ?? '-' }}<br><small class="text-muted">{{ $result->requestItem->labRequest->request_no }}</small></td>
                    <td>{{ $result->result_value }}</td>
                    <td>{{ Str::limit($result->remarks, 40) }}</td>
                    <td><span class="badge bg-{{ $result->statusBadge }}">{{ $result->status }}</span></td>
                    <td>{{ $result->technologist?->name ?? '—' }}</td>
                    <td>{{ $result->released_at?->format('M d, Y H:i') ?? '—' }}</td>
                    <td>
                        <div class="table-actions">
                            <a href="{{ route('lab.results.show', $result) }}" class="table-action-btn action-view" title="View Result Details" aria-label="View Result Details"><i class="bi bi-eye"></i></a>
                            @if($result->status==='Encoded' && auth()->user()?->hasRole('med-tech') && auth()->user()->hasPermission('lis.results.verify'))
                                <form method="POST" action="{{ route('lab.results.validate', $result) }}" class="d-inline">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="table-action-btn action-info" title="Validate Result" aria-label="Validate Result"><i class="bi bi-check-circle"></i></button>
                                </form>
                            @elseif($result->status==='Validated' && auth()->user()?->hasRole('med-tech') && auth()->user()->hasPermission('lis.results.release'))
                                <form method="POST" action="{{ route('lab.results.release', $result) }}" class="d-inline">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="table-action-btn action-success" title="Release Result" aria-label="Release Result"><i class="bi bi-send-check"></i></button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center py-4 text-muted">No results encoded yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div id="lab-results-pagination-container">
        @if($labResults->hasPages())
            <div class="card-footer">{{ $labResults->links() }}</div>
        @endif
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const statusSelect = document.getElementById('filter-status');
        const filterForm = document.getElementById('filter-form');

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
                
                document.getElementById('lab-results-table-body').innerHTML = doc.getElementById('lab-results-table-body').innerHTML;
                document.getElementById('lab-results-pagination-container').innerHTML = doc.getElementById('lab-results-pagination-container').innerHTML;
            })
            .catch(err => console.error('Error filtering lab results:', err));
        }

        filterForm.addEventListener('submit', function (e) {
            e.preventDefault();
            performFilter(true);
        });

        statusSelect.addEventListener('change', () => performFilter(true));

        // Intercept pagination clicks dynamically
        document.addEventListener('click', function (e) {
            const pageLink = e.target.closest('#lab-results-pagination-container a');
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
