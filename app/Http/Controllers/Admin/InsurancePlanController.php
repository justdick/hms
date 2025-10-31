<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInsurancePlanRequest;
use App\Http\Requests\UpdateInsurancePlanRequest;
use App\Http\Resources\InsurancePlanResource;
use App\Http\Resources\InsuranceProviderResource;
use App\Models\InsurancePlan;
use App\Models\InsuranceProvider;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class InsurancePlanController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', InsurancePlan::class);

        $plans = InsurancePlan::with('provider')
            ->withCount('coverageRules', 'tariffs')
            ->orderBy('plan_name')
            ->paginate(20);

        return Inertia::render('Admin/Insurance/Plans/Index', [
            'plans' => InsurancePlanResource::collection($plans),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', InsurancePlan::class);

        $providers = InsuranceProvider::active()
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Insurance/Plans/Create', [
            'providers' => InsuranceProviderResource::collection($providers),
        ]);
    }

    public function store(StoreInsurancePlanRequest $request): RedirectResponse
    {
        $this->authorize('create', InsurancePlan::class);

        $plan = InsurancePlan::create($request->validated());

        return redirect()
            ->route('admin.insurance.plans.show', $plan)
            ->with('success', 'Insurance plan created successfully.');
    }

    public function show(InsurancePlan $plan): Response
    {
        $this->authorize('view', $plan);

        $plan->load([
            'provider',
            'coverageRules' => function ($query) {
                $query->orderBy('coverage_category')->orderBy('item_code');
            },
            'tariffs' => function ($query) {
                $query->orderBy('item_type')->orderBy('item_code');
            },
        ]);

        return Inertia::render('Admin/Insurance/Plans/Show', [
            'plan' => new InsurancePlanResource($plan),
        ]);
    }

    public function edit(InsurancePlan $plan): Response
    {
        $this->authorize('update', $plan);

        $plan->load('provider');

        $providers = InsuranceProvider::active()
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Insurance/Plans/Edit', [
            'plan' => new InsurancePlanResource($plan),
            'providers' => InsuranceProviderResource::collection($providers),
        ]);
    }

    public function update(UpdateInsurancePlanRequest $request, InsurancePlan $plan): RedirectResponse
    {
        $this->authorize('update', $plan);

        $plan->update($request->validated());

        return redirect()
            ->route('admin.insurance.plans.show', $plan)
            ->with('success', 'Insurance plan updated successfully.');
    }

    public function destroy(InsurancePlan $plan): RedirectResponse
    {
        $this->authorize('delete', $plan);

        if ($plan->patientInsurances()->exists()) {
            return back()->with('error', 'Cannot delete plan with enrolled patients.');
        }

        $plan->delete();

        return redirect()
            ->route('admin.insurance.plans.index')
            ->with('success', 'Insurance plan deleted successfully.');
    }
}
