<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInsuranceTariffRequest;
use App\Http\Requests\UpdateInsuranceTariffRequest;
use App\Http\Resources\InsurancePlanResource;
use App\Http\Resources\InsuranceTariffResource;
use App\Models\InsurancePlan;
use App\Models\InsuranceTariff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InsuranceTariffController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', InsuranceTariff::class);

        $query = InsuranceTariff::with('plan.provider');

        if ($request->filled('plan_id')) {
            $query->where('insurance_plan_id', $request->plan_id);
        }

        if ($request->filled('item_type')) {
            $query->where('item_type', $request->item_type);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('item_code', 'like', "%{$request->search}%")
                    ->orWhere('item_description', 'like', "%{$request->search}%");
            });
        }

        $tariffs = $query->orderBy('item_type')
            ->orderBy('item_code')
            ->paginate(50);

        $plans = InsurancePlan::with('provider')
            ->active()
            ->orderBy('plan_name')
            ->get();

        return Inertia::render('Admin/Insurance/Tariffs/Index', [
            'tariffs' => InsuranceTariffResource::collection($tariffs),
            'plans' => InsurancePlanResource::collection($plans)->resolve(),
            'filters' => $request->only(['plan_id', 'item_type', 'search']),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', InsuranceTariff::class);

        $plans = InsurancePlan::with('provider')
            ->active()
            ->orderBy('plan_name')
            ->get();

        return Inertia::render('Admin/Insurance/Tariffs/Create', [
            'plans' => InsurancePlanResource::collection($plans)->resolve(),
            'preselectedPlanId' => $request->input('plan_id'),
        ]);
    }

    public function store(StoreInsuranceTariffRequest $request): RedirectResponse
    {
        $this->authorize('create', InsuranceTariff::class);

        $tariff = InsuranceTariff::create($request->validated());

        return redirect()
            ->route('admin.insurance.tariffs.index', ['plan_id' => $tariff->insurance_plan_id])
            ->with('success', 'Insurance tariff created successfully.');
    }

    public function show(InsuranceTariff $tariff): Response
    {
        $this->authorize('view', $tariff);

        $tariff->load('plan.provider');

        return Inertia::render('Admin/Insurance/Tariffs/Show', [
            'tariff' => new InsuranceTariffResource($tariff),
        ]);
    }

    public function edit(InsuranceTariff $tariff): Response
    {
        $this->authorize('update', $tariff);

        $tariff->load('plan.provider');

        $plans = InsurancePlan::with('provider')
            ->active()
            ->orderBy('plan_name')
            ->get();

        return Inertia::render('Admin/Insurance/Tariffs/Edit', [
            'tariff' => new InsuranceTariffResource($tariff),
            'plans' => InsurancePlanResource::collection($plans)->resolve(),
        ]);
    }

    public function update(UpdateInsuranceTariffRequest $request, InsuranceTariff $tariff): RedirectResponse
    {
        $this->authorize('update', $tariff);

        $tariff->update($request->validated());

        return redirect()
            ->route('admin.insurance.tariffs.index', ['plan_id' => $tariff->insurance_plan_id])
            ->with('success', 'Insurance tariff updated successfully.');
    }

    public function destroy(InsuranceTariff $tariff): RedirectResponse
    {
        $this->authorize('delete', $tariff);

        $planId = $tariff->insurance_plan_id;
        $tariff->delete();

        return redirect()
            ->route('admin.insurance.tariffs.index', ['plan_id' => $planId])
            ->with('success', 'Insurance tariff deleted successfully.');
    }
}
