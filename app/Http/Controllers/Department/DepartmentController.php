<?php

namespace App\Http\Controllers\Department;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    public function index(\Illuminate\Http\Request $request): Response
    {
        $this->authorize('viewAny', Department::class);

        $perPage = $request->query('per_page', 5);
        $search = $request->query('search');
        $type = $request->query('type');
        $status = $request->query('status');

        $query = Department::query()
            ->withCount(['checkins', 'users']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('code', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        if ($type) {
            $query->where('type', $type);
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $departments = $query->orderBy('name')->paginate($perPage);

        // Stats
        $allDepartments = Department::query()->get();
        $stats = [
            'total' => $allDepartments->count(),
            'active' => $allDepartments->where('is_active', true)->count(),
            'opd' => $allDepartments->where('type', 'opd')->count(),
            'staff_count' => Department::withCount('users')->get()->sum('users_count'),
        ];

        // Transform data while keeping pagination structure
        $departments->getCollection()->transform(function ($department) {
            return [
                'id' => $department->id,
                'name' => $department->name,
                'code' => $department->code,
                'description' => $department->description,
                'type' => $department->type,
                'is_active' => $department->is_active,
                'checkins_count' => $department->checkins_count ?? 0,
                'users_count' => $department->users_count ?? 0,
                'created_at' => $department->created_at?->toISOString(),
                'updated_at' => $department->updated_at?->toISOString(),
            ];
        });

        return Inertia::render('Departments/Index', [
            'departments' => $departments,
            'types' => $this->getDepartmentTypes(),
            'stats' => $stats,
            'filters' => [
                'search' => $search,
                'type' => $type,
                'status' => $status,
            ],
        ]);
    }

    public function store(StoreDepartmentRequest $request): RedirectResponse
    {
        $this->authorize('create', Department::class);

        Department::create($request->validated());

        return redirect()
            ->route('departments.index')
            ->with('success', 'Department created successfully.');
    }

    public function update(UpdateDepartmentRequest $request, Department $department): RedirectResponse
    {
        $this->authorize('update', $department);

        $department->update($request->validated());

        return redirect()
            ->route('departments.index')
            ->with('success', 'Department updated successfully.');
    }

    public function destroy(Department $department): RedirectResponse
    {
        $this->authorize('delete', $department);

        if ($department->checkins()->exists()) {
            return back()->with('error', 'Cannot delete department with existing check-ins.');
        }

        if ($department->users()->exists()) {
            return back()->with('error', 'Cannot delete department with assigned users.');
        }

        $department->delete();

        return redirect()
            ->route('departments.index')
            ->with('success', 'Department deleted successfully.');
    }

    /**
     * @return array<string, string>
     */
    private function getDepartmentTypes(): array
    {
        return [
            'opd' => 'Outpatient (OPD)',
            'ipd' => 'Inpatient (IPD)',
            'diagnostic' => 'Diagnostic',
            'support' => 'Support',
        ];
    }
}
