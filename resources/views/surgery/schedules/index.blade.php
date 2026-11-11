@extends('layouts.app')
@section('title', 'OR Surgery Schedules')
@section('page-title', 'OR Surgery Schedules')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('surgery.dashboard') }}">Surgery (SORS)</a></li>
    <li class="breadcrumb-item active">Schedules</li>
@endsection

@section('content')
<div class="card shadow-sm border-0" style="border-radius: 0.75rem; background: var(--card);">
    <div class="card-header border-bottom py-3 px-4 d-flex flex-wrap gap-3 justify-content-between align-items-center" style="background: rgba(0,0,0,0.015);">
        <form class="d-flex flex-wrap gap-2 align-items-center flex-grow-1" method="GET" id="filter-form">
            <div class="input-group input-group-sm" style="max-width: 260px;">
                <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" name="search" id="filter-search" class="form-control border-start-0 ps-0" placeholder="Search patient, procedure, req #…" value="{{ request('search') }}">
            </div>

            <select name="status" id="filter-status" class="form-select form-select-sm" style="max-width: 140px;">
                <option value="">All Statuses</option>
                @foreach($statuses as $st)
                    <option value="{{ $st }}" {{ request('status') === $st ? 'selected' : '' }}>{{ $st }}</option>
                @endforeach
            </select>

            <select name="operating_room_id" id="filter-room" class="form-select form-select-sm" style="max-width: 170px;">
                <option value="">All Operating Rooms</option>
                @foreach($operatingRooms as $room)
                    <option value="{{ $room->id }}" {{ request('operating_room_id') == $room->id ? 'selected' : '' }}>{{ $room->name }}</option>
                @endforeach
            </select>

            <input type="date" name="date" id="filter-date" class="form-control form-control-sm" value="{{ request('date') }}" style="max-width: 140px;" placeholder="Filter Date">

            <button type="submit" class="btn btn-primary btn-sm d-none">Filter</button>
            
            @if(request()->hasAny(['search','status','operating_room_id','date']))
                <a href="{{ route('surgery.schedules.index') }}" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1" id="filter-clear">
                    <i class="bi bi-x-circle"></i> Clear
                </a>
            @endif
        </form>

        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('surgery.calendar') }}" class="btn btn-sm btn-outline-primary d-flex align-items-center gap-1.5" style="border-radius: 0.5rem; font-weight: 500;">
                <i class="bi bi-calendar-week"></i> Calendar View
            </a>
            @can('create', \App\Models\SurgerySchedule::class)
                <a href="{{ route('surgery.schedules.create') }}" class="btn btn-sm btn-primary px-3 fw-semibold d-flex align-items-center gap-1.5" style="border-radius: 0.5rem;">
                    <i class="bi bi-plus-circle"></i> Schedule Surgery
                </a>
            @endcan
        </div>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
                <thead class="table-light" style="background: rgba(0,0,0,0.02); font-family: var(--font-display); font-size: 0.76rem; text-transform: uppercase; letter-spacing: 0.04em;">
                    <tr>
                        <th class="ps-4 py-3">Scheduled Time</th>
                        <th class="py-3">Patient & Details</th>
                        <th class="py-3">Procedure</th>
                        <th class="py-3">Operating Room</th>
                        <th class="py-3">Surgeon & Team</th>
                        <th class="py-3">Status</th>
                        <th class="pe-4 py-3 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="schedules-table-body">
                    @forelse($schedules as $sched)
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold text-primary">{{ $sched->scheduled_at ? $sched->scheduled_at->format('M d, Y') : 'Date N/A' }}</div>
                                <div class="text-muted small"><i class="bi bi-clock me-1"></i>{{ $sched->scheduled_at ? $sched->scheduled_at->format('h:i A') : 'Time N/A' }} ({{ $sched->duration_minutes ?? 60 }}m)</div>
                            </td>
                            <td>
                                @if($sched->surgeryRequest && $sched->surgeryRequest->patient)
                                    <div class="fw-semibold text-dark">{{ $sched->surgeryRequest->patient->last_name }}, {{ $sched->surgeryRequest->patient->first_name }}</div>
                                    <div class="text-muted small">ID: {{ $sched->surgeryRequest->patient->patient_no }}</div>
                                @else
                                    <span class="text-muted">Unlinked Request</span>
                                @endif
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $sched->surgeryRequest->procedure_name ?? 'N/A' }}</div>
                                <div class="text-muted small">Req #: {{ $sched->surgeryRequest->request_no ?? 'N/A' }}</div>
                            </td>
                            <td>
                                <div class="fw-semibold d-flex align-items-center gap-1.5 text-success">
                                    <i class="bi bi-door-open"></i>
                                    {{ $sched->operatingRoom->name ?? 'Unassigned' }}
                                </div>
                                <div class="text-muted small">{{ $sched->operatingRoom->location ?? '' }}</div>
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $sched->surgicalTeam->surgeon->name ?? 'Lead Surgeon N/A' }}</div>
                                <div class="text-muted small">{{ $sched->surgicalTeam->name ?? 'Team N/A' }}</div>
                            </td>
                            <td>
                                <span class="badge bg-{{ $sched->status === 'Completed' ? 'success' : ($sched->status === 'Scheduled' ? 'primary' : ($sched->status === 'In Progress' ? 'warning text-dark' : ($sched->status === 'Postponed' ? 'secondary' : 'danger'))) }}" style="font-weight: 600; padding: 0.4em 0.85em;">
                                    {{ $sched->status }}
                                </span>
                                <div class="mt-1">
                                    <span class="badge bg-light text-dark border" style="font-size: 0.7rem;">
                                        OR Prep: {{ $sched->readiness_status ?? 'Pending Prep' }}
                                    </span>
                                </div>
                            </td>
                            <td class="pe-4 text-end text-nowrap">
                                @php
                                    $rowActions = [
                                        [
                                            'type' => 'link',
                                            'url' => route('surgery.schedules.show', $sched),
                                            'title' => 'View Schedule Details',
                                            'icon' => 'bi-eye',
                                            'btn_class' => 'action-view',
                                            'text_class' => 'text-primary'
                                        ]
                                    ];
                                    if ($sched->status === 'Scheduled' && auth()->user()?->can('updateStatus', $sched)) {
                                        $rowActions[] = [
                                            'type' => 'form',
                                            'url' => route('surgery.schedules.start', $sched),
                                            'method' => 'PATCH',
                                            'title' => 'Start Procedure',
                                            'icon' => 'bi-play-fill',
                                            'btn_class' => 'action-warning',
                                            'text_class' => 'text-warning'
                                        ];
                                    }
                                    if ($sched->status === 'In Progress' && auth()->user()?->can('updateStatus', $sched)) {
                                        $rowActions[] = [
                                            'type' => 'form',
                                            'url' => route('surgery.schedules.complete', $sched),
                                            'method' => 'PATCH',
                                            'title' => 'Mark as Completed',
                                            'icon' => 'bi-check-lg',
                                            'btn_class' => 'action-success',
                                            'text_class' => 'text-success'
                                        ];
                                    }
                                    if (auth()->user()?->can('update', $sched)) {
                                        $rowActions[] = [
                                            'type' => 'link',
                                            'url' => route('surgery.schedules.edit', $sched),
                                            'title' => 'Edit Schedule',
                                            'icon' => 'bi-pencil',
                                            'btn_class' => 'action-edit',
                                            'text_class' => 'text-secondary'
                                        ];
                                    }
                                    if (auth()->user()?->can('delete', $sched)) {
                                        $rowActions[] = [
                                            'type' => 'form',
                                            'url' => route('surgery.schedules.destroy', $sched),
                                            'method' => 'DELETE',
                                            'title' => 'Delete Schedule',
                                            'icon' => 'bi-trash',
                                            'btn_class' => 'action-danger',
                                            'text_class' => 'text-danger',
                                            'confirm' => 'Remove schedule and revert request to pending?'
                                        ];
                                    }
                                    $actionCount = count($rowActions);
                                    $directLimit = ($actionCount <= 3) ? $actionCount : 2;
                                @endphp
                                <div class="table-actions justify-content-end">
                                    @foreach(array_slice($rowActions, 0, $directLimit) as $act)
                                        @if($act['type'] === 'link')
                                            <a href="{{ $act['url'] }}" class="table-action-btn {{ $act['btn_class'] }}" title="{{ $act['title'] }}" aria-label="{{ $act['title'] }}">
                                                <i class="bi {{ $act['icon'] }}"></i>
                                            </a>
                                        @elseif($act['type'] === 'form')
                                            <form action="{{ $act['url'] }}" method="POST" class="d-inline">
                                                @csrf
                                                @method($act['method'] ?? 'POST')
                                                <button type="submit" class="table-action-btn {{ $act['btn_class'] }}" title="{{ $act['title'] }}" aria-label="{{ $act['title'] }}" @if(!empty($act['confirm'])) data-confirm="{{ $act['confirm'] }}" @endif>
                                                    <i class="bi {{ $act['icon'] }}"></i>
                                                </button>
                                            </form>
                                        @endif
                                    @endforeach

                                    @if($actionCount > 3)
                                        <div class="dropdown d-inline">
                                            <button class="table-action-btn dropdown-toggle no-arrow" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="More Actions" aria-label="More Actions">
                                                <i class="bi bi-three-dots"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 small">
                                                @foreach(array_slice($rowActions, $directLimit) as $act)
                                                    <li>
                                                        @if($act['type'] === 'link')
                                                            <a class="dropdown-item d-flex align-items-center gap-2" href="{{ $act['url'] }}">
                                                                <i class="bi {{ $act['icon'] }} {{ $act['text_class'] ?? '' }}"></i> {{ $act['title'] }}
                                                            </a>
                                                        @elseif($act['type'] === 'form')
                                                            <form action="{{ $act['url'] }}" method="POST">
                                                                @csrf
                                                                @method($act['method'] ?? 'POST')
                                                                <button type="submit" class="dropdown-item d-flex align-items-center gap-2 {{ $act['text_class'] ?? '' }}" @if(!empty($act['confirm'])) data-confirm="{{ $act['confirm'] }}" @endif>
                                                                    <i class="bi {{ $act['icon'] }}"></i> {{ $act['title'] }}
                                                                </button>
                                                            </form>
                                                        @endif
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-calendar-x d-block mb-2" style="font-size: 2rem; opacity: 0.4;"></i>
                                No surgery schedules found matching your filter criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div id="schedules-pagination-container">
        @if($schedules->hasPages())
            <div class="card-footer py-3 px-4 border-top" style="background: rgba(0,0,0,0.015);">
                {{ $schedules->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('filter-search');
    const statusSelect = document.getElementById('filter-status');
    const roomSelect = document.getElementById('filter-room');
    const dateInput = document.getElementById('filter-date');
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
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');

            const newTable = doc.getElementById('schedules-table-body');
            const newPag = doc.getElementById('schedules-pagination-container');

            if (newTable) {
                document.getElementById('schedules-table-body').innerHTML = newTable.innerHTML;
            }
            if (newPag) {
                document.getElementById('schedules-pagination-container').innerHTML = newPag.innerHTML;
            }
        })
        .catch(err => console.error('Error filtering schedules:', err));
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
    roomSelect.addEventListener('change', () => performFilter(true));
    dateInput.addEventListener('change', () => performFilter(true));
});
</script>
@endpush
