<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkUpdatePricingRequest;
use App\Http\Requests\Admin\ImportPricingRequest;
use App\Http\Requests\Admin\UpdateCashPriceRequest;
use App\Http\Requests\Admin\UpdateFlexibleCopayRequest;
use App\Http\Requests\Admin\UpdateInsuranceCopayRequest;
use App\Http\Requests\Admin\UpdateInsuranceCoverageRequest;
use App\Models\InsurancePlan;
use App\Models\PricingChangeLog;
use App\Services\PricingDashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PricingDashboardController extends Controller
{
    public function __construct(
        protected PricingDashboardService $pricingService
    ) {}

    /**
     * Display the pricing dashboard.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny-pricing-dashboard');

        $planId = $request->input('plan_id');
        $category = $request->input('category');
        $search = $request->input('search');
        $unmappedOnly = $request->boolean('unmapped_only', false);
        $pricingStatus = $request->input('pricing_status');

        $data = $this->pricingService->getPricingData(
            $planId ? (int) $planId : null,
            $category,
            $search,
            $unmappedOnly,
            null,
            $pricingStatus
        );

        // Get pricing status summary
        $summary = $this->pricingService->getPricingStatusSummary(
            $planId ? (int) $planId : null
        );

        $insurancePlans = InsurancePlan::with('provider')
            ->active()
            ->orderBy('plan_name')
            ->get()
            ->map(fn ($plan) => [
                'id' => $plan->id,
                'name' => $plan->plan_name,
                'provider_name' => $plan->provider?->name,
                'is_nhis' => $plan->provider?->is_nhis ?? false,
            ]);

        return Inertia::render('Admin/PricingDashboard/Index', [
            'items' => $data['items'],
            'categories' => $data['categories'],
            'selectedPlan' => $data['plan'] ? [
                'id' => $data['plan']->id,
                'name' => $data['plan']->plan_name,
                'provider_name' => $data['plan']->provider?->name,
                'is_nhis' => $data['plan']->provider?->is_nhis ?? false,
            ] : null,
            'isNhis' => $data['is_nhis'],
            'insurancePlans' => $insurancePlans,
            'filters' => [
                'plan_id' => $planId,
                'category' => $category,
                'search' => $search,
                'unmapped_only' => $unmappedOnly,
                'pricing_status' => $pricingStatus,
            ],
            'summary' => $summary,
        ]);
    }

    /**
     * Update cash price for an item.
     */
    public function updateCashPrice(UpdateCashPriceRequest $request): RedirectResponse
    {
        $this->authorize('updateCashPrice-pricing-dashboard');

        try {
            $this->pricingService->updateCashPrice(
                $request->validated('item_type'),
                $request->validated('item_id'),
                $request->validated('price')
            );

            return redirect()->back()->with('success', 'Cash price updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update cash price: '.$e->getMessage());
        }
    }

    /**
     * Update insurance copay for an item.
     */
    public function updateInsuranceCopay(UpdateInsuranceCopayRequest $request): RedirectResponse
    {
        $this->authorize('updateInsuranceCopay-pricing-dashboard');

        try {
            $this->pricingService->updateInsuranceCopay(
                $request->validated('plan_id'),
                $request->validated('item_type'),
                $request->validated('item_id'),
                $request->validated('item_code'),
                $request->validated('copay')
            );

            return redirect()->back()->with('success', 'Insurance copay updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update insurance copay: '.$e->getMessage());
        }
    }

    /**
     * Update insurance coverage for an item.
     */
    public function updateInsuranceCoverage(UpdateInsuranceCoverageRequest $request): RedirectResponse
    {
        $this->authorize('updateInsuranceCoverage-pricing-dashboard');

        try {
            $coverageData = array_filter([
                'tariff_amount' => $request->validated('tariff_amount'),
                'coverage_value' => $request->validated('coverage_value'),
                'coverage_type' => $request->validated('coverage_type'),
                'patient_copay_amount' => $request->validated('patient_copay_amount'),
            ], fn ($value) => $value !== null);

            $this->pricingService->updateInsuranceCoverage(
                $request->validated('plan_id'),
                $request->validated('item_type'),
                $request->validated('item_id'),
                $request->validated('item_code'),
                $coverageData
            );

            return redirect()->back()->with('success', 'Insurance coverage updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update insurance coverage: '.$e->getMessage());
        }
    }

    /**
     * Update flexible copay for an unmapped NHIS item.
     */
    public function updateFlexibleCopay(UpdateFlexibleCopayRequest $request): RedirectResponse
    {
        $this->authorize('updateInsuranceCopay-pricing-dashboard');

        try {
            $copayAmount = $request->validated('copay_amount');

            $this->pricingService->updateFlexibleCopay(
                $request->validated('plan_id'),
                $request->validated('item_type'),
                $request->validated('item_id'),
                $request->validated('item_code'),
                $copayAmount !== null ? (float) $copayAmount : null
            );

            $message = $copayAmount !== null
                ? 'Flexible copay set successfully.'
                : 'Flexible copay cleared successfully.';

            return redirect()->back()->with('success', $message);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update flexible copay: '.$e->getMessage());
        }
    }

    /**
     * Bulk update copay for multiple items.
     */
    public function bulkUpdate(BulkUpdatePricingRequest $request): RedirectResponse
    {
        $this->authorize('bulkUpdate-pricing-dashboard');

        try {
            $result = $this->pricingService->bulkUpdateCopay(
                $request->validated('plan_id'),
                $request->validated('items'),
                $request->validated('copay')
            );

            $message = "Updated {$result['updated']} items.";
            if (count($result['errors']) > 0) {
                $message .= ' '.count($result['errors']).' items failed.';
            }

            return redirect()->back()->with('success', $message);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to perform bulk update: '.$e->getMessage());
        }
    }

    /**
     * Export pricing data to CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $this->authorize('export-pricing-dashboard');

        $planId = $request->input('plan_id');
        $category = $request->input('category');
        $search = $request->input('search');

        $csv = $this->pricingService->exportToCsv(
            $planId ? (int) $planId : null,
            $category,
            $search
        );

        $filename = 'pricing-export-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Import pricing data from file.
     */
    public function import(ImportPricingRequest $request): RedirectResponse
    {
        $this->authorize('import-pricing-dashboard');

        try {
            $result = $this->pricingService->importFromFile(
                $request->file('file'),
                $request->validated('plan_id')
            );

            $message = "Import complete: {$result['updated']} items updated, {$result['skipped']} skipped.";
            if (count($result['errors']) > 0) {
                $message .= ' '.count($result['errors']).' errors occurred.';
            }

            return redirect()->back()->with('success', $message);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to import pricing data: '.$e->getMessage());
        }
    }

    /**
     * Download import template.
     */
    public function downloadImportTemplate(Request $request): StreamedResponse
    {
        $this->authorize('viewAny-pricing-dashboard');

        $planId = $request->input('plan_id');
        $category = $request->input('category');

        $csv = $this->pricingService->generateImportTemplate(
            $planId ? (int) $planId : null,
            $category
        );

        $filename = 'pricing-import-template-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Get pricing change history for an item.
     */
    public function itemHistory(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authorize('viewAny-pricing-dashboard');

        $itemType = $request->input('item_type');
        $itemId = $request->input('item_id');

        $history = PricingChangeLog::forItem($itemType, $itemId)
            ->with('user:id,name')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'field_changed' => $log->field_changed,
                'old_value' => $log->old_value,
                'new_value' => $log->new_value,
                'user' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                ] : null,
                'created_at' => $log->created_at->toIso8601String(),
            ]);

        return response()->json(['history' => $history]);
    }
}
