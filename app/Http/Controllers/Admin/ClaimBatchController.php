<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddClaimsToBatchRequest;
use App\Http\Requests\MarkBatchSubmittedRequest;
use App\Http\Requests\RecordBatchResponseRequest;
use App\Http\Requests\StoreClaimBatchRequest;
use App\Http\Resources\ClaimBatchResource;
use App\Models\ClaimBatch;
use App\Models\InsuranceClaim;
use App\Services\ClaimBatchService;
use App\Services\NhisXmlExportService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ClaimBatchController extends Controller
{
    public function __construct(
        protected ClaimBatchService $claimBatchService,
        protected NhisXmlExportService $nhisXmlExportService
    ) {}

    /**
     * Display a listing of claim batches.
     *
     * _Requirements: 14.1, 14.3_
     */
    public function index(Request $request): InertiaResponse
    {
        $this->authorize('viewAny', ClaimBatch::class);

        $query = ClaimBatch::query()
            ->with(['creator', 'batchItems'])
            ->withCount('batchItems');

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by submission period
        if ($request->filled('period')) {
            $query->whereMonth('submission_period', Carbon::parse($request->period)->month)
                ->whereYear('submission_period', Carbon::parse($request->period)->year);
        }

        // Search by batch number or name
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('batch_number', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        // Sort by created_at (newest first by default)
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->input('per_page', 5);
        $paginated = $query->paginate($perPage)->withQueryString();

        // Transform data while keeping flat pagination structure
        $batches = $paginated->through(fn ($batch) => (new ClaimBatchResource($batch))->resolve());

        // Get vetted claims available for batching
        $vettedClaimsCount = InsuranceClaim::where('status', 'vetted')
            ->whereDoesntHave('batchItems')
            ->count();

        return Inertia::render('Admin/Insurance/Batches/Index', [
            'batches' => $batches,
            'filters' => $request->only(['status', 'period', 'search']),
            'stats' => [
                'total' => ClaimBatch::count(),
                'draft' => ClaimBatch::where('status', 'draft')->count(),
                'finalized' => ClaimBatch::where('status', 'finalized')->count(),
                'submitted' => ClaimBatch::where('status', 'submitted')->count(),
                'completed' => ClaimBatch::where('status', 'completed')->count(),
                'vetted_claims_available' => $vettedClaimsCount,
            ],
        ]);
    }

    /**
     * Store a newly created claim batch.
     *
     * _Requirements: 14.1_
     */
    public function store(StoreClaimBatchRequest $request): RedirectResponse
    {
        $this->authorize('create', ClaimBatch::class);

        $validated = $request->validated();

        $submissionPeriod = Carbon::parse($validated['submission_period']);

        // Auto-generate batch name from the month/year
        $batchName = $submissionPeriod->format('F Y').' Claims';

        $batch = $this->claimBatchService->createBatch(
            name: $batchName,
            submissionPeriod: $submissionPeriod,
            createdBy: $request->user(),
            notes: $validated['notes'] ?? null
        );

        // Always auto-populate with vetted claims for the selected month
        $startOfMonth = $submissionPeriod->copy()->startOfMonth();
        $endOfMonth = $submissionPeriod->copy()->endOfMonth();

        $vettedClaimIds = InsuranceClaim::where('status', 'vetted')
            ->whereBetween('date_of_attendance', [$startOfMonth, $endOfMonth])
            ->pluck('id')
            ->toArray();

        if (! empty($vettedClaimIds)) {
            $result = $this->claimBatchService->addClaimsToBatch($batch, $vettedClaimIds);

            return redirect()->route('admin.insurance.batches.show', $batch)
                ->with('success', "Batch '{$batch->name}' created with {$result['added']} claim(s) added.");
        }

        return redirect()->route('admin.insurance.batches.show', $batch)
            ->with('success', "Batch '{$batch->name}' created. No vetted claims found for this month.");
    }

    /**
     * Display the specified claim batch.
     *
     * _Requirements: 14.3_
     */
    public function show(ClaimBatch $batch): InertiaResponse
    {
        $this->authorize('view', $batch);

        $batch->load([
            'creator',
            'batchItems.insuranceClaim.patientInsurance.plan.provider',
            'statusHistory.user',
        ]);

        // Get vetted claims available for adding to this batch
        $availableClaims = InsuranceClaim::where('status', 'vetted')
            ->whereDoesntHave('batchItems', function ($query) use ($batch) {
                $query->where('claim_batch_id', $batch->id);
            })
            ->with(['patientInsurance.plan.provider'])
            ->orderBy('date_of_attendance', 'desc')
            ->limit(100)
            ->get();

        return Inertia::render('Admin/Insurance/Batches/Show', [
            'batch' => ClaimBatchResource::make($batch)->resolve(),
            'availableClaims' => $availableClaims->map(fn ($claim) => [
                'id' => $claim->id,
                'claim_check_code' => $claim->claim_check_code,
                'patient_name' => $claim->patient_surname.' '.$claim->patient_other_names,
                'membership_id' => $claim->membership_id,
                'date_of_attendance' => $claim->date_of_attendance?->toDateString(),
                'total_claim_amount' => $claim->total_claim_amount,
                'provider_name' => $claim->patientInsurance?->plan?->provider?->name,
            ]),
            'can' => [
                'modify' => auth()->user()->can('update', $batch) && $batch->canBeModified(),
                'finalize' => auth()->user()->can('finalize', $batch) && $batch->isDraft(),
                'submit' => auth()->user()->can('submit', $batch) && $batch->isFinalized(),
                'export' => auth()->user()->can('export', $batch),
                'recordResponse' => auth()->user()->can('recordResponse', $batch) && $batch->isSubmitted(),
                'revertToDraft' => auth()->user()->can('revertToDraft', $batch),
                'delete' => auth()->user()->can('delete', $batch),
            ],
        ]);
    }

    /**
     * Add claims to a batch.
     *
     * _Requirements: 14.2_
     */
    public function addClaims(AddClaimsToBatchRequest $request, ClaimBatch $batch): RedirectResponse
    {
        $this->authorize('update', $batch);

        $validated = $request->validated();

        try {
            $result = $this->claimBatchService->addClaimsToBatch($batch, $validated['claim_ids']);

            $message = "{$result['added']} claim(s) added to batch.";
            if ($result['skipped'] > 0) {
                $message .= " {$result['skipped']} claim(s) skipped.";
            }

            if (! empty($result['errors'])) {
                return redirect()->back()
                    ->with('warning', $message)
                    ->with('errors_detail', $result['errors']);
            }

            return redirect()->back()->with('success', $message);
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Refresh batch by adding any new vetted claims for the batch's submission period.
     */
    public function refreshClaims(ClaimBatch $batch): RedirectResponse
    {
        $this->authorize('update', $batch);

        $startOfMonth = $batch->submission_period->copy()->startOfMonth();
        $endOfMonth = $batch->submission_period->copy()->endOfMonth();

        $newClaimIds = InsuranceClaim::where('status', 'vetted')
            ->whereBetween('date_of_attendance', [$startOfMonth, $endOfMonth])
            ->whereDoesntHave('batchItems', function ($query) use ($batch) {
                $query->where('claim_batch_id', $batch->id);
            })
            ->pluck('id')
            ->toArray();

        if (empty($newClaimIds)) {
            return redirect()->back()->with('info', 'No new vetted claims found for this month.');
        }

        try {
            $result = $this->claimBatchService->addClaimsToBatch($batch, $newClaimIds);

            return redirect()->back()
                ->with('success', "{$result['added']} new claim(s) added to batch.");
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Remove a claim from a batch.
     *
     * _Requirements: 14.2_
     */
    public function removeClaim(ClaimBatch $batch, InsuranceClaim $claim): RedirectResponse
    {
        $this->authorize('update', $batch);

        try {
            $this->claimBatchService->removeClaimFromBatch($batch, $claim);

            return redirect()->back()
                ->with('success', "Claim {$claim->claim_check_code} removed from batch.");
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Delete a batch.
     */
    public function destroy(ClaimBatch $batch): RedirectResponse
    {
        $this->authorize('delete', $batch);

        $batchName = $batch->name;

        // Remove all batch items first
        $batch->batchItems()->delete();
        $batch->statusHistory()->delete();
        $batch->delete();

        return redirect()->route('admin.insurance.batches.index')
            ->with('success', "Batch '{$batchName}' has been deleted.");
    }

    /**
     * Finalize a batch, preventing further modifications.
     *
     * _Requirements: 14.5_
     */
    public function finalize(ClaimBatch $batch): RedirectResponse
    {
        $this->authorize('finalize', $batch);

        try {
            $this->claimBatchService->finalizeBatch($batch, auth()->user());

            return redirect()->back()
                ->with('success', 'Batch has been finalized. No further modifications are allowed.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Unfinalize a batch, allowing modifications again.
     */
    public function unfinalize(ClaimBatch $batch): RedirectResponse
    {
        $this->authorize('revertToDraft', $batch);

        try {
            $this->claimBatchService->unfinalizeBatch($batch, auth()->user());

            return redirect()->back()
                ->with('success', 'Batch has been reverted to draft. You can now modify it.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Mark a batch as submitted.
     *
     * _Requirements: 16.1_
     */
    public function markSubmitted(MarkBatchSubmittedRequest $request, ClaimBatch $batch): RedirectResponse
    {
        $this->authorize('submit', $batch);

        $validated = $request->validated();

        try {
            $submittedAt = isset($validated['submitted_at'])
                ? Carbon::parse($validated['submitted_at'])
                : now();

            $this->claimBatchService->markSubmitted($batch, $submittedAt, auth()->user());

            return redirect()->back()
                ->with('success', 'Batch has been marked as submitted to NHIA.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Record NHIA response for claims in a batch.
     *
     * _Requirements: 17.1, 17.2, 17.3_
     */
    public function recordResponse(RecordBatchResponseRequest $request, ClaimBatch $batch): RedirectResponse
    {
        $this->authorize('recordResponse', $batch);

        $validated = $request->validated();

        try {
            $paidAt = isset($validated['paid_at'])
                ? Carbon::parse($validated['paid_at'])
                : null;

            $result = $this->claimBatchService->recordResponse(
                $batch,
                $validated['responses'],
                $paidAt,
                $validated['paid_amount'] ?? null
            );

            $message = "{$result['processed']} claim response(s) recorded.";
            if (! empty($result['errors'])) {
                $message .= ' Some errors occurred: '.implode(', ', $result['errors']);

                return redirect()->back()->with('warning', $message);
            }

            return redirect()->back()->with('success', $message);
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Export batch to XML format for NHIA submission.
     *
     * _Requirements: 15.1, 15.4, 15.5_
     */
    public function exportXml(ClaimBatch $batch): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorize('export', $batch);

        // Record export timestamp
        $batch->exported_at = now();
        $batch->save();

        $filename = "nhis-batch-{$batch->batch_number}.xml";

        return response()->stream(function () use ($batch) {
            $this->nhisXmlExportService->writeXmlToStream($batch, fopen('php://output', 'w'));
        }, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Export batch to Excel format.
     * Contains same data as XML export but in spreadsheet format.
     *
     * _Requirements: 15.1_
     */
    public function exportExcel(ClaimBatch $batch): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $this->authorize('export', $batch);

        $batch->load([
            'batchItems.insuranceClaim.patient',
            'batchItems.insuranceClaim.claimDiagnoses.diagnosis',
            'batchItems.insuranceClaim.items.nhisTariff',
        ]);

        $filename = "nhis-batch-{$batch->batch_number}.xlsx";

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\ClaimBatchExcelExport($batch),
            $filename
        );
    }

    /**
     * Get the count of vetted claims for a given month (for auto-populate preview).
     */
    public function vettedClaimsCount(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'period' => ['required', 'date'],
        ]);

        $period = Carbon::parse($request->period);
        $startOfMonth = $period->copy()->startOfMonth();
        $endOfMonth = $period->copy()->endOfMonth();

        $count = InsuranceClaim::where('status', 'vetted')
            ->whereBetween('date_of_attendance', [$startOfMonth, $endOfMonth])
            ->count();

        $batchExists = ClaimBatch::whereYear('submission_period', $period->year)
            ->whereMonth('submission_period', $period->month)
            ->exists();

        return response()->json([
            'count' => $count,
            'batch_exists' => $batchExists,
        ]);
    }
}
