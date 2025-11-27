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

        $batches = $query->paginate(20)->withQueryString();

        // Get vetted claims available for batching
        $vettedClaimsCount = InsuranceClaim::where('status', 'vetted')
            ->whereDoesntHave('batchItems')
            ->count();

        return Inertia::render('Admin/Insurance/Batches/Index', [
            'batches' => ClaimBatchResource::collection($batches),
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

        $batch = $this->claimBatchService->createBatch(
            name: $validated['name'],
            submissionPeriod: Carbon::parse($validated['submission_period']),
            createdBy: $request->user(),
            notes: $validated['notes'] ?? null
        );

        return redirect()->route('admin.insurance.batches.show', $batch)
            ->with('success', "Batch '{$batch->name}' created successfully.");
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
    public function exportXml(ClaimBatch $batch): Response
    {
        $this->authorize('export', $batch);

        $batch->load([
            'batchItems.insuranceClaim.patient',
            'batchItems.insuranceClaim.claimDiagnoses.diagnosis',
            'batchItems.insuranceClaim.items.nhisTariff',
            'batchItems.insuranceClaim.gdrgTariff',
        ]);

        $xml = $this->nhisXmlExportService->generateXml($batch);

        // Record export timestamp
        $batch->exported_at = now();
        $batch->save();

        $filename = "nhis-batch-{$batch->batch_number}.xml";

        return response($xml, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
