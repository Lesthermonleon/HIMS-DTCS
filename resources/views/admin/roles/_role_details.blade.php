<div id="role-details-container" class="card-body">
    @if(isset($selectedRole))
        <div class="row g-4 mb-3">
            <div class="col-md-3">
                <span class="text-uppercase text-muted small fw-semibold d-block mb-1">Role Name</span>
                <span class="fs-6 fw-bold text-body">{{ $selectedRole->name }}</span>
            </div>
            <div class="col-md-3">
                <span class="text-uppercase text-muted small fw-semibold d-block mb-1">Identifier</span>
                <code class="font-monospace text-success fs-6">{{ $selectedRole->slug }}</code>
            </div>
            <div class="col-md-3">
                <span class="text-uppercase text-muted small fw-semibold d-block mb-1">Status</span>
                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 fw-medium">
                    <i class="bi bi-check-circle me-1"></i>Active
                </span>
            </div>
            <div class="col-md-3">
                <span class="text-uppercase text-muted small fw-semibold d-block mb-1">Assigned Users</span>
                <span class="badge hims-badge-gray px-2 py-1">
                    <i class="bi bi-people me-1"></i>{{ $selectedRole->users_count }} Users
                </span>
            </div>
        </div>

        @if($selectedRole->description)
        <div class="mb-4 pt-3 border-top">
            <span class="text-uppercase text-muted small fw-semibold d-block mb-1">Role Description</span>
            <p class="text-body-secondary small mb-0">{{ $selectedRole->description }}</p>
        </div>
        @endif

        <div class="pt-3 border-top">
            <span class="text-uppercase text-muted small fw-semibold d-block mb-2">Access Scope</span>
            <div class="d-flex flex-wrap gap-2">
                @php
                    $rawModules = str_replace(['All Modules (', ')'], '', $selectedRole->accessible_modules);
                    $modules = array_map('trim', explode(',', $rawModules));
                    $isAllModules = str_contains($selectedRole->accessible_modules, 'All Modules') || in_array($selectedRole->slug, ['system-administrator', 'admin']);
                @endphp

                @if($isAllModules)
                    <span class="badge bg-success-subtle text-success border border-success-subtle py-2 px-3 fw-medium">
                        <i class="bi bi-shield-check me-1"></i>All System Modules
                    </span>
                @else
                    @foreach($modules as $m)
                        @if(!empty($m))
                            <span class="badge bg-success-subtle text-success border border-success-subtle py-2 px-3 fw-medium">
                                <i class="bi bi-check-circle me-1"></i>{{ $m }}
                            </span>
                        @endif
                    @endforeach
                @endif
            </div>
        </div>

        {{-- Granular Permissions Matrix --}}
        <div class="pt-4 border-top mt-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="text-uppercase text-muted small fw-semibold d-block">
                    Granular Permissions ({{ $selectedRole->permissions->count() }} active)
                </span>
                @if(auth()->user()?->isSuperAdmin())
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                        <i class="bi bi-shield-check me-1"></i>Super Admin Permission Authority
                    </span>
                @endif
            </div>

            @if(auth()->user()?->isSuperAdmin() && isset($allPermissions))
                <form method="POST" action="{{ route('admin.roles.update', $selectedRole) }}">
                    @csrf
                    @method('PUT')
                    
                    <div class="row g-3 mb-3" style="max-height: 380px; overflow-y: auto;">
                        @php
                            $rolePermIds = $selectedRole->permissions->pluck('id')->toArray();
                            $permGroups = $allPermissions->groupBy('module');
                        @endphp

                        @foreach($permGroups as $moduleName => $groupPerms)
                            <div class="col-md-6">
                                <div class="card border h-100 p-2">
                                    <div class="fw-bold small text-dark mb-2 pb-1 border-bottom d-flex justify-content-between align-items-center">
                                        <span><i class="bi bi-folder2-open text-primary me-1"></i>{{ $moduleName }}</span>
                                        <span class="badge bg-light text-muted border extra-small">{{ count($groupPerms) }}</span>
                                    </div>
                                    @foreach($groupPerms as $perm)
                                        <div class="form-check form-check-sm mb-1">
                                            <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $perm->id }}" id="perm_{{ $perm->id }}" {{ in_array($perm->id, $rolePermIds, true) ? 'checked' : '' }}>
                                            <label class="form-check-label extra-small text-dark" for="perm_{{ $perm->id }}">
                                                {{ $perm->name }} <code class="extra-small text-muted">({{ $perm->slug }})</code>
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mb-3">
                        <input type="text" name="reason" class="form-control form-control-sm" placeholder="Audit justification for updating role permissions..." required>
                    </div>

                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-shield-lock me-1"></i> Save Role Permissions
                    </button>
                </form>
            @else
                @if($selectedRole->permissions->isNotEmpty())
                    <div class="d-flex flex-wrap gap-1">
                        @foreach($selectedRole->permissions as $p)
                            <span class="badge bg-body-tertiary text-body border me-1 mb-1 fw-normal" style="font-size: 0.75rem;">
                                <i class="bi bi-check me-0.5 text-success"></i>{{ $p->name }}
                            </span>
                        @endforeach
                    </div>
                @else
                    <div class="text-muted small">No specific fine-grained permissions assigned to this role.</div>
                @endif
            @endif
        </div>
    @endif
</div>
