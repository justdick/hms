<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * Display a listing of roles with permission counts and user counts.
     */
    public function index(\Illuminate\Http\Request $request): Response
    {
        $this->authorize('viewAny', Role::class);

        $perPage = $request->input('per_page', 5);
        $search = $request->input('search');

        $query = Role::withCount(['permissions', 'users']);

        if ($search) {
            $query->where('name', 'LIKE', "%{$search}%");
        }

        $roles = $query->orderBy('name')->paginate($perPage)->withQueryString();

        // Transform the data while keeping pagination structure
        $roles->getCollection()->transform(fn (Role $role) => [
            'id' => $role->id,
            'name' => $role->name,
            'permissions_count' => $role->permissions_count,
            'users_count' => $role->users_count,
        ]);

        return Inertia::render('Admin/Roles/Index', [
            'roles' => $roles,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Show the form for creating a new role.
     */
    public function create(): Response
    {
        $this->authorize('create', Role::class);

        $permissions = $this->getGroupedPermissions();

        return Inertia::render('Admin/Roles/Create', [
            'permissions' => $permissions,
        ]);
    }

    /**
     * Store a newly created role.
     */
    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $role = Role::create([
            'name' => $request->validated('name'),
            'guard_name' => 'web',
        ]);

        if ($request->validated('permissions')) {
            $role->syncPermissions($request->validated('permissions'));
        }

        return redirect()
            ->route('admin.roles.index')
            ->with('success', 'Role created successfully.');
    }

    /**
     * Show the form for editing a role.
     */
    public function edit(Role $role): Response
    {
        $this->authorize('update', $role);

        $permissions = $this->getGroupedPermissions();

        return Inertia::render('Admin/Roles/Edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
            ],
            'permissions' => $permissions,
        ]);
    }

    /**
     * Update the specified role.
     */
    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $this->authorize('update', $role);

        $role->update([
            'name' => $request->validated('name'),
        ]);

        $role->syncPermissions($request->validated('permissions') ?? []);

        return redirect()
            ->route('admin.roles.index')
            ->with('success', 'Role updated successfully.');
    }

    /**
     * Remove the specified role.
     */
    public function destroy(Role $role): RedirectResponse
    {
        $this->authorize('delete', $role);

        // Double-check user count (policy already checks this, but be safe)
        $userCount = $role->users()->count();
        if ($userCount > 0) {
            return redirect()
                ->back()
                ->with('error', "Cannot delete role with {$userCount} assigned users.");
        }

        $role->delete();

        return redirect()
            ->route('admin.roles.index')
            ->with('success', 'Role deleted successfully.');
    }

    /**
     * Get permissions grouped by category for the UI.
     */
    private function getGroupedPermissions(): array
    {
        $permissions = Permission::orderBy('name')->get(['id', 'name']);

        $grouped = [];
        foreach ($permissions as $permission) {
            // Extract category from permission name (e.g., "users.view-all" -> "users")
            $parts = explode('.', $permission->name);
            $category = $parts[0] ?? 'other';

            // Handle permissions without dots (legacy format)
            if (count($parts) === 1) {
                $category = 'other';
            }

            if (! isset($grouped[$category])) {
                $grouped[$category] = [];
            }

            $grouped[$category][] = [
                'id' => $permission->id,
                'name' => $permission->name,
            ];
        }

        // Sort categories alphabetically
        ksort($grouped);

        return $grouped;
    }
}
