<?php

namespace App\Http\Controllers\Lab;

use App\Http\Controllers\Controller;
use App\Models\BillingService;
use App\Models\LabService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LabServiceConfigurationController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('configureParameters', LabService::class);

        $query = LabService::active();

        // Search filter
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        // Category filter
        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        // Status filter (configured/pending)
        if ($status = $request->input('status')) {
            if ($status === 'configured') {
                $query->whereNotNull('test_parameters')
                    ->whereRaw("JSON_LENGTH(test_parameters->'$.parameters') > 0");
            } elseif ($status === 'pending') {
                $query->where(function ($q) {
                    $q->whereNull('test_parameters')
                        ->orWhereRaw("JSON_LENGTH(test_parameters->'$.parameters') = 0")
                        ->orWhereRaw("test_parameters->'$.parameters' IS NULL");
                });
            }
        }

        $perPage = $request->input('per_page', 10);
        $labServices = $query->orderBy('category')
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        // Stats for all services (not filtered)
        $allServices = LabService::active()->get(['test_parameters']);
        $stats = [
            'total' => $allServices->count(),
            'configured' => $allServices->filter(fn ($s) => ! empty($s->test_parameters['parameters'] ?? []))->count(),
            'pending' => $allServices->filter(fn ($s) => empty($s->test_parameters['parameters'] ?? []))->count(),
        ];

        $categories = LabService::active()
            ->distinct('category')
            ->pluck('category')
            ->sort()
            ->values();

        return Inertia::render('Lab/Configuration/Index', [
            'labServices' => $labServices,
            'categories' => $categories,
            'stats' => $stats,
            'filters' => [
                'search' => $request->input('search'),
                'category' => $request->input('category'),
                'status' => $request->input('status'),
                'per_page' => $perPage,
            ],
        ]);
    }

    public function show(LabService $labService): Response
    {
        $this->authorize('configureParameters', LabService::class);

        return Inertia::render('Lab/Configuration/Configure', [
            'labService' => $labService,
        ]);
    }

    public function update(Request $request, LabService $labService): RedirectResponse
    {
        $this->authorize('configureParameters', LabService::class);

        // Check if this is a parameter configuration update or service details update
        if ($request->has('test_parameters')) {
            // Parameter configuration update
            $validated = $request->validate([
                'test_parameters' => 'nullable|array',
                'test_parameters.parameters' => 'nullable|array',
                'test_parameters.parameters.*.name' => 'required|string',
                'test_parameters.parameters.*.label' => 'required|string',
                'test_parameters.parameters.*.type' => 'required|in:numeric,text,select,boolean',
                'test_parameters.parameters.*.unit' => 'nullable|string',
                'test_parameters.parameters.*.normal_range' => 'nullable|array',
                'test_parameters.parameters.*.normal_range.min' => 'nullable|numeric',
                'test_parameters.parameters.*.normal_range.max' => 'nullable|numeric',
                'test_parameters.parameters.*.options' => 'nullable|array',
                'test_parameters.parameters.*.required' => 'boolean',
            ]);

            $labService->update([
                'test_parameters' => $validated['test_parameters'],
            ]);

            return redirect()
                ->route('lab.services.configuration.index')
                ->with('success', 'Test parameters updated successfully.');
        } else {
            // Service details update
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'category' => 'required|string|max:100',
                'description' => 'nullable|string',
                'preparation_instructions' => 'nullable|string',
                'price' => 'required|numeric|min:0',
                'sample_type' => 'required|string|max:100',
                'turnaround_time' => 'required|string|max:100',
                'normal_range' => 'nullable|string|max:255',
                'clinical_significance' => 'nullable|string',
            ]);

            // Update lab service
            $labService->update($validated);

            // Update corresponding billing service
            $billingService = BillingService::where('service_code', 'LAB_'.$labService->code)->first();
            if ($billingService) {
                $billingService->update([
                    'service_name' => $validated['name'],
                    'base_price' => $validated['price'],
                ]);
            }

            return redirect()
                ->route('lab.services.configuration.index')
                ->with('success', 'Lab service updated successfully.');
        }
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', LabService::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:20|unique:lab_services,code',
            'category' => 'required|string|max:100',
            'description' => 'nullable|string',
            'preparation_instructions' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'sample_type' => 'required|string|max:100',
            'turnaround_time' => 'required|string|max:100',
            'normal_range' => 'nullable|string|max:255',
            'clinical_significance' => 'nullable|string',
        ]);

        // Create lab service
        $labService = LabService::create($validated);

        // Create corresponding billing service
        BillingService::create([
            'service_name' => $validated['name'],
            'service_code' => 'LAB_'.$validated['code'],
            'service_type' => 'laboratory',
            'base_price' => $validated['price'],
            'is_active' => true,
        ]);

        return redirect()
            ->route('lab.services.configuration.show', $labService)
            ->with('success', 'Lab service created successfully! Now configure test parameters.');
    }

    public function suggestCode(Request $request): JsonResponse
    {
        $this->authorize('create', LabService::class);

        $category = $request->query('category');
        $name = $request->query('name');

        if (! $category) {
            return response()->json(['code' => '']);
        }

        // Generate category prefix (first 3-4 letters)
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $category), 0, 4));

        // Find next available number
        $existingCodes = LabService::where('code', 'LIKE', $prefix.'%')
            ->pluck('code')
            ->map(function ($code) use ($prefix) {
                return (int) str_replace($prefix, '', $code);
            })
            ->filter()
            ->sort();

        $nextNumber = $existingCodes->isEmpty() ? 1 : $existingCodes->last() + 1;
        $suggestedCode = $prefix.str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

        return response()->json(['code' => $suggestedCode]);
    }

    public function createCategory(Request $request): JsonResponse
    {
        $this->authorize('create', LabService::class);

        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:lab_services,category',
        ]);

        // Return the new category name for frontend to use
        return response()->json([
            'category' => $validated['name'],
            'message' => 'Category ready to use',
        ]);
    }
}
