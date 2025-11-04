<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInsurancePlanRequest;
use App\Http\Requests\UpdateInsurancePlanRequest;
use App\Http\Resources\InsuranceCoverageRuleResource;
use App\Http\Resources\InsurancePlanResource;
use App\Http\Resources\InsuranceProviderResource;
use App\Models\InsurancePlan;
use App\Models\InsuranceProvider;
use App\Services\CoveragePresetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class InsurancePlanController extends Controller
{
    public function __construct(
        private readonly CoveragePresetService $coveragePresetService
    ) {}

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

        return Inertia::render('Admin/Insurance/Plans/CreateWithWizard', [
            'providers' => InsuranceProviderResource::collection($providers),
        ]);
    }

    public function store(StoreInsurancePlanRequest $request): RedirectResponse
    {
        $this->authorize('create', InsurancePlan::class);

        $validated = $request->validated();
        $coverageRules = $validated['coverage_rules'] ?? [];
        unset($validated['coverage_rules']);

        $plan = \DB::transaction(function () use ($validated, $coverageRules) {
            $plan = InsurancePlan::create($validated);

            // Create default coverage rules
            foreach ($coverageRules as $rule) {
                $plan->coverageRules()->create([
                    'coverage_category' => $rule['coverage_category'],
                    'item_code' => null,
                    'item_description' => null,
                    'coverage_type' => 'percentage',
                    'coverage_value' => $rule['coverage_value'],
                    'patient_copay_percentage' => 100 - $rule['coverage_value'],
                    'is_covered' => $rule['coverage_value'] > 0,
                    'is_active' => true,
                ]);
            }

            return $plan;
        });

        return redirect()
            ->route('admin.insurance.plans.show', $plan)
            ->with('success', 'Insurance plan created successfully with default coverage rules.');
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
            'simplifiedUiEnabled' => config('features.simplified_insurance_ui'),
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

    public function manageCoverageRules(InsurancePlan $plan): Response
    {
        $this->authorize('view', $plan);

        // Always use the new simplified UI (Coverage Dashboard)
        return $this->showCoverage($plan);
    }

    public function getCoveragePresets(): JsonResponse
    {
        return response()->json([
            'presets' => $this->coveragePresetService->getPresets(),
        ]);
    }

    public function showCoverage(InsurancePlan $plan): Response
    {
        $this->authorize('view', $plan);

        $plan->load('provider');

        $categories = ['consultation', 'drug', 'lab', 'procedure', 'ward', 'nursing'];
        $dashboardData = [];

        foreach ($categories as $category) {
            $generalRule = $plan->coverageRules()
                ->whereNull('item_code')
                ->where('coverage_category', $category)
                ->active()
                ->first();

            $exceptionCount = $plan->coverageRules()
                ->whereNotNull('item_code')
                ->where('coverage_category', $category)
                ->active()
                ->count();

            $dashboardData[] = [
                'category' => $category,
                'default_coverage' => $generalRule?->coverage_value,
                'exception_count' => $exceptionCount,
                'general_rule_id' => $generalRule?->id,
            ];
        }

        return Inertia::render('Admin/Insurance/Plans/CoverageDashboard', [
            'plan' => new InsurancePlanResource($plan),
            'categories' => $dashboardData,
        ]);
    }

    public function getCategoryExceptions(InsurancePlan $plan, string $category): JsonResponse
    {
        $this->authorize('view', $plan);

        $exceptions = $plan->coverageRules()
            ->whereNotNull('item_code')
            ->where('coverage_category', $category)
            ->active()
            ->orderBy('item_description')
            ->get();

        return response()->json([
            'exceptions' => InsuranceCoverageRuleResource::collection($exceptions),
        ]);
    }

    public function getRecentItems(InsurancePlan $plan): JsonResponse
    {
        $this->authorize('view', $plan);

        $threshold = 500; // Expensive item threshold
        $recentItems = [];

        // Get recent drugs
        $recentDrugs = \App\Models\Drug::where('created_at', '>=', now()->subDays(30))
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($drug) => [
                'id' => $drug->id,
                'code' => $drug->drug_code,
                'name' => $drug->name,
                'category' => 'drug',
                'price' => (float) $drug->unit_price,
                'added_date' => $drug->created_at->toIso8601String(),
                'is_expensive' => $drug->unit_price > $threshold,
                'coverage_status' => $this->getCoverageStatus($plan, 'drug', $drug->drug_code),
            ]);

        // Get recent lab services
        $recentLabs = \App\Models\LabService::where('created_at', '>=', now()->subDays(30))
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($lab) => [
                'id' => $lab->id,
                'code' => $lab->code,
                'name' => $lab->name,
                'category' => 'lab',
                'price' => (float) $lab->price,
                'added_date' => $lab->created_at->toIso8601String(),
                'is_expensive' => $lab->price > $threshold,
                'coverage_status' => $this->getCoverageStatus($plan, 'lab', $lab->code),
            ]);

        // Get recent billing services (for procedures, consultations, etc.)
        $recentServices = \App\Models\BillingService::where('created_at', '>=', now()->subDays(30))
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($service) use ($plan, $threshold) {
                // Map service type to coverage category
                $category = match ($service->service_type) {
                    'consultation' => 'consultation',
                    'procedure' => 'procedure',
                    'ward' => 'ward',
                    'nursing' => 'nursing',
                    default => 'procedure',
                };

                return [
                    'id' => $service->id,
                    'code' => $service->service_code,
                    'name' => $service->service_name,
                    'category' => $category,
                    'price' => (float) $service->price,
                    'added_date' => $service->created_at->toIso8601String(),
                    'is_expensive' => $service->price > $threshold,
                    'coverage_status' => $this->getCoverageStatus($plan, $category, $service->service_code),
                ];
            });

        // Merge all recent items
        $recentItems = $recentDrugs
            ->concat($recentLabs)
            ->concat($recentServices)
            ->sortByDesc('added_date')
            ->values()
            ->all();

        return response()->json([
            'recent_items' => $recentItems,
        ]);
    }

    private function getCoverageStatus(InsurancePlan $plan, string $category, string $itemCode): string
    {
        $hasException = $plan->coverageRules()
            ->where('coverage_category', $category)
            ->where('item_code', $itemCode)
            ->exists();

        if ($hasException) {
            return 'exception';
        }

        $hasDefault = $plan->coverageRules()
            ->where('coverage_category', $category)
            ->whereNull('item_code')
            ->exists();

        return $hasDefault ? 'default' : 'not_covered';
    }
}
