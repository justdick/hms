<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInsuranceCoverageRuleRequest;
use App\Http\Requests\UpdateInsuranceCoverageRuleRequest;
use App\Http\Resources\InsuranceCoverageRuleResource;
use App\Http\Resources\InsurancePlanResource;
use App\Models\InsuranceCoverageRule;
use App\Models\InsurancePlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InsuranceCoverageRuleController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', InsuranceCoverageRule::class);

        $query = InsuranceCoverageRule::with('plan.provider');

        if ($request->filled('plan_id')) {
            $query->where('insurance_plan_id', $request->plan_id);
        }

        if ($request->filled('coverage_category')) {
            $query->where('coverage_category', $request->coverage_category);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('item_code', 'like', "%{$request->search}%")
                    ->orWhere('item_description', 'like', "%{$request->search}%");
            });
        }

        $rules = $query->orderBy('coverage_category')
            ->orderBy('item_code')
            ->paginate(50);

        $plans = InsurancePlan::with('provider')
            ->active()
            ->orderBy('plan_name')
            ->get();

        return Inertia::render('Admin/Insurance/CoverageRules/Index', [
            'rules' => InsuranceCoverageRuleResource::collection($rules),
            'plans' => InsurancePlanResource::collection($plans)->resolve(),
            'filters' => $request->only(['plan_id', 'coverage_category', 'search']),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', InsuranceCoverageRule::class);

        $plans = InsurancePlan::with('provider')
            ->active()
            ->orderBy('plan_name')
            ->get();

        return Inertia::render('Admin/Insurance/CoverageRules/Create', [
            'plans' => InsurancePlanResource::collection($plans)->resolve(),
            'preselectedPlanId' => $request->input('plan_id'),
        ]);
    }

    public function store(StoreInsuranceCoverageRuleRequest $request): RedirectResponse
    {
        $this->authorize('create', InsuranceCoverageRule::class);

        $rule = InsuranceCoverageRule::create($request->validated());

        return redirect()
            ->route('admin.insurance.coverage-rules.index', ['plan_id' => $rule->insurance_plan_id])
            ->with('success', 'Coverage rule created successfully.');
    }

    public function show(InsuranceCoverageRule $coverageRule): Response
    {
        $this->authorize('view', $coverageRule);

        $coverageRule->load('plan.provider');

        return Inertia::render('Admin/Insurance/CoverageRules/Show', [
            'rule' => (new InsuranceCoverageRuleResource($coverageRule))->resolve(),
        ]);
    }

    public function edit(InsuranceCoverageRule $coverageRule): Response
    {
        $this->authorize('update', $coverageRule);

        $coverageRule->load('plan.provider');

        $plans = InsurancePlan::with('provider')
            ->active()
            ->orderBy('plan_name')
            ->get();

        return Inertia::render('Admin/Insurance/CoverageRules/Edit', [
            'rule' => (new InsuranceCoverageRuleResource($coverageRule))->resolve(),
            'plans' => InsurancePlanResource::collection($plans)->resolve(),
        ]);
    }

    public function update(UpdateInsuranceCoverageRuleRequest $request, InsuranceCoverageRule $coverageRule): RedirectResponse
    {
        $this->authorize('update', $coverageRule);

        $coverageRule->update($request->validated());

        return redirect()
            ->route('admin.insurance.coverage-rules.index', ['plan_id' => $coverageRule->insurance_plan_id])
            ->with('success', 'Coverage rule updated successfully.');
    }

    public function destroy(InsuranceCoverageRule $coverageRule): RedirectResponse
    {
        $this->authorize('delete', $coverageRule);

        $planId = $coverageRule->insurance_plan_id;
        $coverageRule->delete();

        return redirect()
            ->route('admin.insurance.coverage-rules.index', ['plan_id' => $planId])
            ->with('success', 'Coverage rule deleted successfully.');
    }
}
