@extends('layouts.app')

@section('title', 'Roles & Permissions')

@section('content')
<style>
    .role-matrix-wrapper {
        overflow-x: auto;
    }

    .role-matrix-wrapper table thead th {
        background-color: var(--bs-table-bg, var(--bs-body-bg));
        box-shadow: inset 0 -1px 0 var(--bs-border-color);
        color: var(--bs-body-color);
        font-weight: 600;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .role-selector-pill {
        border: 1px solid var(--bs-border-color);
        background-color: var(--bs-body-bg);
        color: var(--bs-body-color);
        border-radius: 0.375rem;
        padding: 0.35rem 0.75rem;
        font-size: 0.85rem;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.15s ease-in-out;
        display: inline-flex;
        align-items: center;
        cursor: pointer;
    }
    .role-selector-pill:hover {
        color: #198754;
        border-color: #198754;
        background-color: rgba(25, 135, 84, 0.06);
    }
    .role-selector-pill.active {
        color: #ffffff;
        background-color: #198754;
        border-color: #198754;
    }
</style>

<div class="container-fluid px-4 py-3">

    {{-- Page Header --}}
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-bold mb-0 text-body">
                <i class="bi bi-shield-lock me-2 text-success"></i>Roles &amp; Permissions
            </h4>
        </div>
        <a href="{{ route('admin.dashboard') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Dashboard
        </a>
    </div>

    {{-- SECTION 1: ROLES OVERVIEW --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent py-3 d-flex align-items-center justify-content-between border-bottom">
            <h5 class="fw-bold mb-0 text-body">
                <i class="bi bi-grid-3x3-gap me-2 text-success"></i>Roles Overview
            </h5>
            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1.5">
                {{ $roles->count() }} Roles Configured
            </span>
        </div>

        <div class="role-matrix-wrapper">
            <table class="table table-hover table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-3" style="min-width: 200px;">Role</th>
                        <th style="min-width: 140px;">Identifier</th>
                        <th style="min-width: 360px;">Accessible Modules</th>
                        <th class="pe-3" style="min-width: 120px;">Users</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($roles as $role)
                    <tr id="overview-role-row-{{ $role->slug }}" class="{{ isset($selectedRole) && $selectedRole->id === $role->id ? 'table-active' : '' }}">
                        <td class="ps-3 fw-semibold text-body">
                            <i class="bi bi-person-badge me-1 text-success"></i>{{ $role->name }}
                        </td>
                        <td>
                            <code class="font-monospace text-muted small">{{ $role->slug }}</code>
                        </td>
                        <td>
                            @php
                                $modules = array_map('trim', explode(',', str_replace(['All Modules (', ')'], '', $role->accessible_modules)));
                            @endphp
                            @foreach($modules as $m)
                                @if(!empty($m))
                                    <span class="badge bg-body-tertiary text-body border me-1 mb-1 fw-normal" style="font-size: 0.75rem;">
                                        {{ $m }}
                                    </span>
                                @endif
                            @endforeach
                        </td>
                        <td class="pe-3">
                            <span class="badge hims-badge-gray px-2 py-1" style="font-size: 0.75rem;">
                                <i class="bi bi-people me-1"></i>{{ $role->users_count }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- SECTION 2: ROLE DETAILS & ACCESS SCOPE --}}
    <div id="permission-details" class="card border-0 shadow-sm">
        <div class="card-header bg-transparent py-3 border-bottom">
            <h5 class="fw-bold mb-0 text-body">
                <i class="bi bi-sliders me-2 text-success"></i>Role Details &amp; Access Scope
            </h5>

            <div class="d-flex flex-wrap gap-2 mt-3 pt-2 border-top">
                @foreach($roles as $r)
                    <button type="button"
                            class="role-selector-pill {{ isset($selectedRole) && $selectedRole->id === $r->id ? 'active' : '' }}"
                            data-role-slug="{{ $r->slug }}">
                        <i class="bi bi-shield me-1"></i>{{ $r->name }}
                    </button>
                @endforeach
            </div>
        </div>

        <div id="role-details-wrapper">
            @include('admin.roles._role_details', ['selectedRole' => $selectedRole, 'allPermissions' => $allPermissions ?? collect()])
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const pills = document.querySelectorAll('.role-selector-pill');
    const wrapper = document.getElementById('role-details-wrapper');
    let activeController = null;

    function fetchRoleDetails(roleSlug, updateHistory = true) {
        if (!wrapper) return;

        if (activeController) {
            activeController.abort();
        }
        activeController = new AbortController();

        // Update active class on pills
        pills.forEach(pill => {
            if (pill.getAttribute('data-role-slug') === roleSlug) {
                pill.classList.add('active');
            } else {
                pill.classList.remove('active');
            }
        });

        // Highlight table row in overview if present
        document.querySelectorAll('tr[id^="overview-role-row-"]').forEach(row => {
            row.classList.remove('table-active');
        });
        const targetRow = document.getElementById('overview-role-row-' + roleSlug);
        if (targetRow) {
            targetRow.classList.add('table-active');
        }

        // Show small localized spinner inside Role Details area
        wrapper.innerHTML = `
            <div class="card-body text-center py-4 text-muted">
                <span class="spinner-border spinner-border-sm text-success me-2" role="status" aria-hidden="true"></span>
                <span class="small fw-semibold">Loading role details...</span>
            </div>
        `;

        const url = '{{ route("admin.roles.index") }}?role=' + encodeURIComponent(roleSlug) + '&partial=1';

        fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            signal: activeController.signal
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.text();
        })
        .then(html => {
            wrapper.innerHTML = html;
            if (updateHistory) {
                const newUrl = '{{ route("admin.roles.index") }}?role=' + encodeURIComponent(roleSlug);
                history.pushState({ role: roleSlug }, '', newUrl);
            }
        })
        .catch(error => {
            if (error.name === 'AbortError') {
                return; // Suppressed for fast rapid clicks
            }
            wrapper.innerHTML = `
                <div class="card-body">
                    <div class="alert alert-danger border-danger-subtle bg-danger-subtle text-danger small mb-0 d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle me-2 fs-5"></i>
                        <div>Unable to load role details. Please try selecting the role again.</div>
                    </div>
                </div>
            `;
        });
    }

    pills.forEach(pill => {
        pill.addEventListener('click', function (e) {
            e.preventDefault();
            const roleSlug = this.getAttribute('data-role-slug');
            if (roleSlug) {
                fetchRoleDetails(roleSlug, true);
            }
        });
    });

    window.addEventListener('popstate', function (e) {
        const urlParams = new URLSearchParams(window.location.search);
        const roleParam = urlParams.get('role') || (e.state && e.state.role);
        if (roleParam) {
            fetchRoleDetails(roleParam, false);
        }
    });
});
</script>
@endsection
