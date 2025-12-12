<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Department;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct(
        protected UserService $userService
    ) {}

    /**
     * Display a listing of users with pagination, search, and filters.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $query = User::query()
            ->with(['roles', 'departments']);

        // Search by name or username
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            });
        }

        // Filter by role
        if ($role = $request->input('role')) {
            $query->whereHas('roles', function ($q) use ($role) {
                $q->where('name', $role);
            });
        }

        // Filter by department
        if ($department = $request->input('department')) {
            $query->whereHas('departments', function ($q) use ($department) {
                $q->where('departments.id', $department);
            });
        }

        $perPage = $request->input('per_page', 5);
        $users = $query->orderBy('name')->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'roles' => Role::orderBy('name')->get(['id', 'name']),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'filters' => [
                'search' => $request->input('search'),
                'role' => $request->input('role'),
                'department' => $request->input('department'),
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Show the form for creating a new user.
     */
    public function create(): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('Admin/Users/Create', [
            'roles' => Role::orderBy('name')->get(['id', 'name']),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $result = $this->userService->createUser($request->validated());

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User created successfully.')
            ->with('temporary_password', $result['temporary_password']);
    }

    /**
     * Show the form for editing a user.
     */
    public function edit(User $user): Response
    {
        $this->authorize('update', $user);

        $user->load(['roles', 'departments', 'permissions']);

        $canAssignDirectPermissions = auth()->user()->can('users.assign-direct-permissions');

        return Inertia::render('Admin/Users/Edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'is_active' => $user->is_active,
                'roles' => $user->roles->pluck('name'),
                'departments' => $user->departments->pluck('id'),
                'direct_permissions' => $user->getDirectPermissions()->pluck('name'),
            ],
            'roles' => Role::orderBy('name')->get(['id', 'name']),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'permissions' => $canAssignDirectPermissions ? $this->getGroupedPermissions() : [],
            'canAssignDirectPermissions' => $canAssignDirectPermissions,
        ]);
    }

    /**
     * Get permissions grouped by category for the UI.
     */
    private function getGroupedPermissions(): array
    {
        $permissions = \Spatie\Permission\Models\Permission::orderBy('name')->get(['id', 'name']);

        $grouped = [];
        foreach ($permissions as $permission) {
            $parts = explode('.', $permission->name);
            $category = $parts[0] ?? 'other';

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

        ksort($grouped);

        return $grouped;
    }

    /**
     * Update the specified user.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->userService->updateUser($user, $request->validated());

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User updated successfully.');
    }

    /**
     * Toggle the active status of a user.
     */
    public function toggleActive(User $user): RedirectResponse
    {
        $this->authorize('toggleActive', $user);

        $this->userService->toggleActive($user);

        $status = $user->fresh()->is_active ? 'activated' : 'deactivated';

        return redirect()
            ->back()
            ->with('success', "User {$status} successfully.");
    }

    /**
     * Reset a user's password.
     */
    public function resetPassword(User $user): RedirectResponse
    {
        $this->authorize('resetPassword', $user);

        $temporaryPassword = $this->userService->resetPassword($user);

        return redirect()
            ->back()
            ->with('success', 'Password reset successfully.')
            ->with('temporary_password', $temporaryPassword);
    }
}
