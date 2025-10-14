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
    public function index(): Response
    {
        $this->authorize('configureParameters', LabService::class);

        $labServices = LabService::active()
            ->orderBy('category')
            ->orderBy('name')
            ->get([
                'id', 'name', 'code', 'category', 'description', 'preparation_instructions',
                'price', 'sample_type', 'turnaround_time', 'normal_range',
                'clinical_significance', 'test_parameters', 'is_active',
            ]);

        $categories = LabService::active()
            ->distinct('category')
            ->pluck('category')
            ->sort()
            ->values();

        return Inertia::render('Lab/Configuration/Index', [
            'labServices' => $labServices,
            'categories' => $categories,
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
