<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\RecordInsurancePaymentRequest;
use App\Http\Requests\RejectInsuranceClaimRequest;
use App\Http\Requests\SubmitInsuranceClaimRequest;
use App\Http\Requests\VetClaimRequest;
use App\Http\Resources\InsuranceClaimResource;
use App\Http\Resources\InsuranceProviderResource;
use App\Models\InsuranceClaim;
use App\Models\InsuranceProvider;
use App\Services\ClaimVettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InsuranceClaimController extends Controller
{
    public function __construct(
        protected ClaimVettingService $claimVettingService
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', InsuranceClaim::class);

        $query = InsuranceClaim::query()
            ->with(['patientInsurance.plan.provider', 'vettedBy', 'submittedBy']);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by insurance provider
        if ($request->filled('provider_id')) {
            $query->whereHas('patientInsurance.plan', function ($q) use ($request) {
                $q->where('insurance_provider_id', $request->provider_id);
            });
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->where('date_of_attendance', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('date_of_attendance', '<=', $request->date_to);
        }

        // Search by claim code or patient name
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('claim_check_code', 'like', "%{$search}%")
                    ->orWhere('patient_surname', 'like', "%{$search}%")
                    ->orWhere('patient_other_names', 'like', "%{$search}%")
                    ->orWhere('membership_id', 'like', "%{$search}%");
            });
        }

        // Sort by date of attendance (newest first by default)
        $sortBy = $request->get('sort_by', 'date_of_attendance');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $claims = $query->paginate(20)->withQueryString();

        // Get all providers for filter dropdown
        $providers = InsuranceProvider::orderBy('name')->get();

        return Inertia::render('Admin/Insurance/Claims/Index', [
            'claims' => InsuranceClaimResource::collection($claims),
            'providers' => InsuranceProviderResource::collection($providers),
            'filters' => $request->only(['status', 'provider_id', 'date_from', 'date_to', 'search']),
            'stats' => [
                'total' => InsuranceClaim::count(),
                'pending_vetting' => InsuranceClaim::where('status', 'pending_vetting')->count(),
                'vetted' => InsuranceClaim::where('status', 'vetted')->count(),
                'submitted' => InsuranceClaim::where('status', 'submitted')->count(),
            ],
        ]);
    }

    /**
     * Get all data needed for the vetting modal.
     * Returns patient info, attendance details, diagnoses, items, and totals.
     */
    public function getVettingData(InsuranceClaim $claim): JsonResponse
    {
        $this->authorize('vetClaim', $claim);

        $vettingData = $this->claimVettingService->getVettingData($claim);

        return response()->json([
            'claim' => InsuranceClaimResource::make($vettingData['claim'])->resolve(),
            'patient' => $vettingData['patient'],
            'attendance' => $vettingData['attendance'],
            'diagnoses' => $vettingData['diagnoses'],
            'items' => [
                'investigations' => $vettingData['items']['investigations']->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->description,
                    'code' => $item->code,
                    'nhis_code' => $item->nhis_code,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'nhis_price' => $item->nhis_price,
                    'subtotal' => $item->subtotal,
                    'is_covered' => $item->nhis_price !== null || ! $vettingData['is_nhis'],
                ]),
                'prescriptions' => $vettingData['items']['prescriptions']->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->description,
                    'code' => $item->code,
                    'nhis_code' => $item->nhis_code,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'nhis_price' => $item->nhis_price,
                    'subtotal' => $item->subtotal,
                    'is_covered' => $item->nhis_price !== null || ! $vettingData['is_nhis'],
                ]),
                'procedures' => $vettingData['items']['procedures']->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->description,
                    'code' => $item->code,
                    'nhis_code' => $item->nhis_code,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'nhis_price' => $item->nhis_price,
                    'subtotal' => $item->subtotal,
                    'is_covered' => $item->nhis_price !== null || ! $vettingData['is_nhis'],
                ]),
            ],
            'totals' => $vettingData['totals'],
            'is_nhis' => $vettingData['is_nhis'],
            'gdrg_tariffs' => $vettingData['gdrg_tariffs']->map(fn ($tariff) => [
                'id' => $tariff->id,
                'code' => $tariff->code,
                'name' => $tariff->name,
                'tariff_price' => $tariff->tariff_price,
                'display_name' => $tariff->display_name,
            ]),
            'can' => [
                'vet' => auth()->user()->can('vetClaim', $claim),
            ],
        ]);
    }

    /**
     * Vet (approve or reject) a claim.
     * For NHIS claims, G-DRG selection is required for approval.
     */
    public function vet(VetClaimRequest $request, InsuranceClaim $claim): RedirectResponse
    {
        $this->authorize('vetClaim', $claim);

        $validated = $request->validated();

        // Handle rejection
        if ($validated['action'] === 'reject') {
            $claim->status = 'rejected';
            $claim->rejection_reason = $validated['rejection_reason'];
            $claim->vetted_by = auth()->id();
            $claim->vetted_at = now();
            $claim->save();

            return redirect()->route('admin.insurance.claims.show', $claim)
                ->with('success', 'Claim has been rejected.');
        }

        // Handle approval using ClaimVettingService
        try {
            $this->claimVettingService->vetClaim(
                $claim,
                auth()->user(),
                $validated['gdrg_tariff_id'] ?? null,
                $validated['diagnoses'] ?? null
            );

            // Refresh claim to get updated values from vetClaim
            $claim->refresh();

            // Process item-level approvals if provided
            if (! empty($validated['items'])) {
                $approvedAmount = 0;

                foreach ($validated['items'] as $itemData) {
                    $item = $claim->items()->find($itemData['id']);
                    if ($item) {
                        $item->is_approved = $itemData['is_approved'];
                        $item->rejection_reason = $itemData['is_approved'] ? null : ($itemData['rejection_reason'] ?? null);
                        $item->save();

                        // Sum up approved items
                        if ($itemData['is_approved']) {
                            $approvedAmount += (float) $item->insurance_pays;
                        }
                    }
                }

                // Update claim amounts based on approved items
                // Copay = total claim amount - approved amount (what insurance pays)
                $totalClaimAmount = (float) $claim->total_claim_amount;
                $claim->approved_amount = number_format($approvedAmount, 2, '.', '');
                $claim->insurance_covered_amount = number_format($approvedAmount, 2, '.', '');
                $claim->patient_copay_amount = number_format($totalClaimAmount - $approvedAmount, 2, '.', '');
                $claim->save();
            }

            return redirect()->route('admin.insurance.claims.show', $claim)
                ->with('success', 'Claim has been vetted and approved.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()
                ->withErrors(['gdrg_tariff_id' => $e->getMessage()]);
        }
    }

    /**
     * Update diagnoses on a claim without affecting the original consultation.
     * This allows claims officers to add/remove diagnoses specific to the claim.
     */
    public function updateDiagnoses(Request $request, InsuranceClaim $claim): JsonResponse
    {
        $this->authorize('vetClaim', $claim);

        $validated = $request->validate([
            'diagnoses' => ['required', 'array'],
            'diagnoses.*.diagnosis_id' => ['required', 'integer', 'exists:diagnoses,id'],
            'diagnoses.*.is_primary' => ['nullable', 'boolean'],
        ]);

        DB::beginTransaction();

        try {
            // Remove existing claim diagnoses
            $claim->claimDiagnoses()->delete();

            // Add new diagnoses to the claim only (not the consultation)
            foreach ($validated['diagnoses'] as $diagnosisData) {
                $claim->claimDiagnoses()->create([
                    'diagnosis_id' => $diagnosisData['diagnosis_id'],
                    'is_primary' => $diagnosisData['is_primary'] ?? false,
                ]);
            }

            DB::commit();

            // Reload diagnoses with their relationships
            $claim->load('claimDiagnoses.diagnosis');

            return response()->json([
                'success' => true,
                'message' => 'Claim diagnoses updated successfully.',
                'diagnoses' => $claim->claimDiagnoses->map(fn ($cd) => [
                    'id' => $cd->id,
                    'diagnosis_id' => $cd->diagnosis_id,
                    'name' => $cd->diagnosis->name ?? $cd->diagnosis->diagnosis ?? '',
                    'icd_code' => $cd->diagnosis->icd_code ?? $cd->diagnosis->code ?? '',
                    'is_primary' => $cd->is_primary,
                ]),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update diagnoses: '.$e->getMessage(),
            ], 500);
        }
    }

    public function submit(SubmitInsuranceClaimRequest $request): RedirectResponse
    {
        $claimIds = $request->getClaimIds();
        $isBatch = $request->isBatchSubmission();

        $submittedCount = 0;
        $errors = [];

        DB::beginTransaction();

        try {
            $submissionDate = $request->input('submission_date', now()->toDateString());
            $batchReference = $isBatch ? ($request->input('batch_reference') ?: 'BATCH-'.now()->format('YmdHis')) : null;

            foreach ($claimIds as $claimId) {
                $claim = InsuranceClaim::findOrFail($claimId);

                // Check authorization
                if (! auth()->user()->can('submitClaim', $claim)) {
                    $errors[] = "Not authorized to submit claim {$claim->claim_check_code}";

                    continue;
                }

                // Update claim status and tracking
                $claim->status = 'submitted';
                $claim->submitted_by = auth()->id();
                $claim->submitted_at = now();
                $claim->submission_date = $submissionDate;

                if ($isBatch) {
                    $claim->batch_reference = $batchReference;
                    $claim->batch_submitted_at = now();
                }

                if ($request->filled('notes')) {
                    $claim->notes = $request->input('notes');
                }

                $claim->save();
                $submittedCount++;
            }

            DB::commit();

            if ($submittedCount === 0) {
                return back()->with('error', 'No claims were submitted. '.implode(', ', $errors));
            }

            $message = $isBatch
                ? "{$submittedCount} claim(s) submitted successfully in batch {$batchReference}."
                : 'Claim submitted successfully.';

            if (! empty($errors)) {
                $message .= ' Some claims failed: '.implode(', ', $errors);
            }

            return back()->with('success', $message);
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Failed to submit claims: '.$e->getMessage());
        }
    }

    public function markAsPaid(RecordInsurancePaymentRequest $request, InsuranceClaim $claim): RedirectResponse
    {
        $this->authorize('recordPayment', $claim);

        DB::beginTransaction();

        try {
            $claim->status = 'paid';
            $claim->payment_date = $request->input('payment_date');
            $claim->payment_amount = $request->input('payment_amount');
            $claim->payment_reference = $request->input('payment_reference');
            $claim->payment_recorded_by = auth()->id();

            if ($request->filled('approval_date')) {
                $claim->approval_date = $request->input('approval_date');
                $claim->approved_by = auth()->id();
            }

            if ($request->filled('notes')) {
                $claim->notes = $request->input('notes');
            }

            $claim->save();

            DB::commit();

            return redirect()->route('admin.insurance.claims.show', $claim)
                ->with('success', 'Payment recorded successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Failed to record payment: '.$e->getMessage());
        }
    }

    public function markAsRejected(RejectInsuranceClaimRequest $request, InsuranceClaim $claim): RedirectResponse
    {
        $this->authorize('rejectClaim', $claim);

        DB::beginTransaction();

        try {
            $claim->status = 'rejected';
            $claim->rejection_reason = $request->input('rejection_reason');
            $claim->rejected_by = auth()->id();
            $claim->rejected_at = $request->input('rejection_date', now());

            $claim->save();

            DB::commit();

            return redirect()->route('admin.insurance.claims.show', $claim)
                ->with('success', 'Claim marked as rejected.');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Failed to reject claim: '.$e->getMessage());
        }
    }

    public function resubmit(SubmitInsuranceClaimRequest $request, InsuranceClaim $claim): RedirectResponse
    {
        $this->authorize('resubmitClaim', $claim);

        DB::beginTransaction();

        try {
            $claim->status = 'submitted';
            $claim->submitted_by = auth()->id();
            $claim->submitted_at = now();
            $claim->submission_date = $request->input('submission_date', now()->toDateString());
            $claim->resubmission_count = ($claim->resubmission_count ?? 0) + 1;
            $claim->last_resubmitted_at = now();

            // Clear rejection data
            $claim->rejection_reason = null;
            $claim->rejected_by = null;
            $claim->rejected_at = null;

            if ($request->filled('notes')) {
                $claim->notes = $request->input('notes');
            }

            $claim->save();

            DB::commit();

            return redirect()->route('admin.insurance.claims.show', $claim)
                ->with('success', 'Claim resubmitted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Failed to resubmit claim: '.$e->getMessage());
        }
    }

    /**
     * Update a rejected claim to allow correction before resubmission.
     * Only rejected claims can be edited.
     *
     * _Requirements: 17.5_
     */
    public function update(Request $request, InsuranceClaim $claim): RedirectResponse
    {
        $this->authorize('update', $claim);

        $validated = $request->validate([
            'gdrg_tariff_id' => ['nullable', 'integer', 'exists:gdrg_tariffs,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::beginTransaction();

        try {
            // Update G-DRG if provided
            if (isset($validated['gdrg_tariff_id'])) {
                $gdrgTariff = \App\Models\GdrgTariff::find($validated['gdrg_tariff_id']);
                $claim->gdrg_tariff_id = $gdrgTariff?->id;
                $claim->gdrg_amount = $gdrgTariff?->tariff_price;

                // Recalculate total claim amount
                $itemsTotal = $claim->items()->sum('insurance_pays');
                $claim->total_claim_amount = ($gdrgTariff?->tariff_price ?? 0) + $itemsTotal;
            }

            if (isset($validated['notes'])) {
                $claim->notes = $validated['notes'];
            }

            $claim->save();

            DB::commit();

            return redirect()->route('admin.insurance.claims.show', $claim)
                ->with('success', 'Claim updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Failed to update claim: '.$e->getMessage());
        }
    }

    /**
     * Prepare a rejected claim for resubmission by resetting its status to vetted.
     * This allows the claim to be added to a new batch.
     *
     * _Requirements: 17.5_
     */
    public function prepareForResubmission(InsuranceClaim $claim): RedirectResponse
    {
        $this->authorize('resubmitClaim', $claim);

        if ($claim->status !== 'rejected') {
            return back()->with('error', 'Only rejected claims can be prepared for resubmission.');
        }

        DB::beginTransaction();

        try {
            // Reset claim status to vetted so it can be added to a new batch
            $claim->status = 'vetted';
            $claim->resubmission_count = ($claim->resubmission_count ?? 0) + 1;
            $claim->last_resubmitted_at = now();

            // Clear rejection data but keep the reason in notes for reference
            $previousRejectionReason = $claim->rejection_reason;
            if ($previousRejectionReason) {
                $claim->notes = ($claim->notes ? $claim->notes."\n\n" : '')
                    .'Previous rejection reason: '.$previousRejectionReason;
            }
            $claim->rejection_reason = null;
            $claim->rejected_by = null;
            $claim->rejected_at = null;

            // Clear submission data
            $claim->submitted_by = null;
            $claim->submitted_at = null;
            $claim->submission_date = null;
            $claim->batch_reference = null;
            $claim->batch_submitted_at = null;

            $claim->save();

            DB::commit();

            return redirect()->route('admin.insurance.claims.show', $claim)
                ->with('success', 'Claim has been prepared for resubmission. You can now add it to a new batch.');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Failed to prepare claim for resubmission: '.$e->getMessage());
        }
    }

    public function export(Request $request)
    {
        $this->authorize('exportClaims', InsuranceClaim::class);

        $format = $request->input('format', 'excel'); // excel or pdf
        $status = $request->input('status');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $query = InsuranceClaim::query()
            ->with(['patientInsurance.plan.provider', 'items']);

        if ($status) {
            $query->where('status', $status);
        }

        if ($dateFrom) {
            $query->where('date_of_attendance', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->where('date_of_attendance', '<=', $dateTo);
        }

        $claims = $query->get();

        if ($format === 'pdf') {
            return $this->exportToPdf($claims);
        }

        return $this->exportToExcel($claims);
    }

    private function exportToExcel($claims)
    {
        $filename = 'insurance-claims-'.now()->format('Y-m-d-His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($claims) {
            $file = fopen('php://output', 'w');

            // Add CSV headers
            fputcsv($file, [
                'Claim Code',
                'Patient Name',
                'Membership ID',
                'Provider',
                'Plan',
                'Date of Attendance',
                'Type of Service',
                'Status',
                'Total Claim Amount',
                'Approved Amount',
                'Patient Copay',
                'Insurance Covered',
                'Submission Date',
                'Payment Date',
                'Payment Reference',
            ]);

            // Add data rows
            foreach ($claims as $claim) {
                fputcsv($file, [
                    $claim->claim_check_code,
                    $claim->patient_surname.' '.$claim->patient_other_names,
                    $claim->membership_id,
                    $claim->patientInsurance->plan->provider->name ?? '',
                    $claim->patientInsurance->plan->name ?? '',
                    $claim->date_of_attendance,
                    ucfirst($claim->type_of_service),
                    ucfirst($claim->status),
                    number_format($claim->total_claim_amount, 2),
                    number_format($claim->approved_amount, 2),
                    number_format($claim->patient_copay_amount, 2),
                    number_format($claim->insurance_covered_amount, 2),
                    $claim->submission_date ?? '',
                    $claim->payment_date ?? '',
                    $claim->payment_reference ?? '',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function exportToPdf($claims)
    {
        // For now, return a simple HTML view that can be printed as PDF
        return response()->view('exports.insurance-claims', [
            'claims' => $claims,
            'exportDate' => now(),
        ])->header('Content-Type', 'text/html');
    }
}
