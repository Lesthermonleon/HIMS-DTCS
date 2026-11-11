<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * RoleController — Admin manages RBAC roles.
 */
class RoleController extends Controller
{
    public function index(Request $request)
    {
        $roles = Role::with(['permissions'])->withCount('users', 'permissions')->get();
        $allPermissions = \App\Models\Permission::orderBy('module')->orderBy('name')->get();

        $selectedSlug = $request->query('role', $roles->first()?->slug ?? 'admin');
        $selectedRole = $roles->firstWhere('slug', $selectedSlug) ?? $roles->first();

        if ($request->ajax() || $request->wantsJson() || $request->query('partial')) {
            return view('admin.roles._role_details', compact('selectedRole', 'allPermissions'));
        }

        return view('admin.roles.index', compact('roles', 'selectedRole', 'allPermissions'));
    }

    public function create(): View
    {
        abort(403, 'Permission management is read-only.');
    }

    public function store(Request $request): RedirectResponse
    {
        abort(403, 'Permission management is read-only.');
    }

    public function show(Role $role): View
    {
        $role->load('users', 'permissions');

        return view('admin.roles.show', compact('role'));
    }

    public function edit(Role $role): View
    {
        abort(403, 'Permission management is read-only.');
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        if (! \Illuminate\Support\Facades\Auth::user()?->isSuperAdmin()) {
            abort(403, 'Permission management is read-only for ordinary administrators.');
        }

        $validated = $request->validate([
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,id'],
            'reason'        => ['nullable', 'string', 'max:500'],
        ]);

        $permissionIds = $validated['permissions'] ?? [];
        $role->permissions()->sync($permissionIds);

        \App\Models\ActivityLog::create([
            'user_id'     => \Illuminate\Support\Facades\Auth::id(),
            'action'      => 'Role Permissions Updated',
            'module'      => \App\Models\ActivityLog::MODULE_SECURITY,
            'description' => sprintf(
                'Super Admin updated permissions for role [%s] (%d permissions assigned). Reason: %s',
                $role->name,
                count($permissionIds),
                $validated['reason'] ?? 'Super Admin permission governance'
            ),
            'severity'    => \App\Models\ActivityLog::SEVERITY_WARNING,
            'result'      => \App\Models\ActivityLog::RESULT_SUCCESS,
            'ip_address'  => $request->ip(),
            'logged_at'   => now(),
        ]);

        return back()->with('success', "Permissions for role [{$role->name}] updated successfully.");
    }

    public function destroy(Role $role): RedirectResponse
    {
        abort(403, 'Permission management is read-only.');
    }
}
