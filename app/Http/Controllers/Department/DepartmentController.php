<?php

namespace App\Http\Controllers\Department;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Http\Resources\DepartmentResource;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Department::class);

        $departments = Department::query()
            ->withCount(['checkins', 'users'])
            ->orderBy('name')
            ->paginate(20);

        return Inertia::render('Departments/Index', [
            'departments' => DepartmentResource::collection($departments),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Department::class);

        return Inertia::render('Departments/Create', [
            'types' => $this->getDepartmentTypes(),
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

    public function edit(Department $department): Response
    {
        $this->authorize('update', $department);

        return Inertia::render('Departments/Edit', [
            'department' => (new DepartmentResource($department))->resolve(),
            'types' => $this->getDepartmentTypes(),
        ]);
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
