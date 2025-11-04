<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInsuranceCoverageRuleRequest;
use App\Http\Requests\UpdateInsuranceCoverageRuleRequest;
use App\Http\Resources\InsuranceCoverageRuleResource;
use App\Http\Resources\InsurancePlanResource;
use App\Models\Drug;
use App\Models\InsuranceCoverageRule;
use App\Models\InsurancePlan;
use App\Models\LabService;
use Illuminate\Http\JsonResponse;
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

    public function searchItems(Request $request, string $category): JsonResponse
    {
        $search = $request->input('search', '');
        $planId = $request->input('plan_id');

        $items = match ($category) {
            'drug' => $this->searchDrugs($search, $planId),
            'lab' => $this->searchLabServices($search, $planId),
            default => [],
        };

        return response()->json($items);
    }

    private function searchDrugs(string $search, ?int $planId): array
    {
        $query = Drug::query()->active();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('drug_code', 'LIKE', "%{$search}%")
                    ->orWhere('generic_name', 'LIKE', "%{$search}%");
            });
        }

        $drugs = $query->limit(20)->get();

        return $drugs->map(function ($drug) use ($planId) {
            $hasRule = false;
            if ($planId) {
                $hasRule = InsuranceCoverageRule::where('insurance_plan_id', $planId)
                    ->where('coverage_category', 'drug')
                    ->where('item_code', $drug->drug_code)
                    ->exists();
            }

            return [
                'code' => $drug->drug_code,
                'name' => $drug->name,
                'description' => $drug->generic_name ? "{$drug->name} ({$drug->generic_name})" : $drug->name,
                'price' => $drug->unit_price,
                'has_rule' => $hasRule,
            ];
        })->toArray();
    }

    private function searchLabServices(string $search, ?int $planId): array
    {
        $query = LabService::query()->active();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('code', 'LIKE', "%{$search}%");
            });
        }

        $services = $query->limit(20)->get();

        return $services->map(function ($service) use ($planId) {
            $hasRule = false;
            if ($planId) {
                $hasRule = InsuranceCoverageRule::where('insurance_plan_id', $planId)
                    ->where('coverage_category', 'lab')
                    ->where('item_code', $service->code)
                    ->exists();
            }

            return [
                'code' => $service->code,
                'name' => $service->name,
                'description' => $service->description ?? $service->name,
                'price' => $service->price,
                'has_rule' => $hasRule,
            ];
        })->toArray();
    }

    public function quickUpdate(Request $request, InsuranceCoverageRule $coverageRule): JsonResponse
    {
        $this->authorize('update', $coverageRule);

        $validated = $request->validate([
            'coverage_value' => 'required|numeric|min:0|max:100',
        ]);

        $coverageRule->update([
            'coverage_value' => $validated['coverage_value'],
            'patient_copay_percentage' => 100 - $validated['coverage_value'],
        ]);

        return response()->json([
            'success' => true,
            'rule' => new InsuranceCoverageRuleResource($coverageRule->fresh()),
        ]);
    }

    public function history(InsuranceCoverageRule $coverageRule): JsonResponse
    {
        $this->authorize('view', $coverageRule);

        $history = $coverageRule->history()
            ->with('user:id,name')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($entry) {
                return [
                    'id' => $entry->id,
                    'action' => $entry->action,
                    'user' => $entry->user ? [
                        'id' => $entry->user->id,
                        'name' => $entry->user->name,
                    ] : null,
                    'old_values' => $entry->old_values,
                    'new_values' => $entry->new_values,
                    'batch_id' => $entry->batch_id,
                    'created_at' => $entry->created_at->toIso8601String(),
                ];
            });

        return response()->json([
            'history' => $history,
        ]);
    }

    public function exportWithHistory(Request $request, InsurancePlan $plan): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $this->authorize('view', $plan);

        $includeHistory = $request->boolean('include_history', false);

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\CoverageRulesWithHistoryExport($plan->id, $includeHistory),
            "coverage-rules-{$plan->plan_code}-".now()->format('Y-m-d').'.xlsx'
        );
    }
}
