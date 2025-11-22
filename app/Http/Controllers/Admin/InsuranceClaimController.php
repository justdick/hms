<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\RecordInsurancePaymentRequest;
use App\Http\Requests\RejectInsuranceClaimRequest;
use App\Http\Requests\SubmitInsuranceClaimRequest;
use App\Http\Resources\InsuranceClaimResource;
use App\Http\Resources\InsuranceProviderResource;
use App\Models\InsuranceClaim;
use App\Models\InsuranceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InsuranceClaimController extends Controller
{
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
                'draft' => InsuranceClaim::where('status', 'draft')->count(),
                'pending_vetting' => InsuranceClaim::where('status', 'pending_vetting')->count(),
                'vetted' => InsuranceClaim::where('status', 'vetted')->count(),
                'submitted' => InsuranceClaim::where('status', 'submitted')->count(),
            ],
        ]);
    }

    public function show(InsuranceClaim $claim)
    {
        $this->authorize('view', $claim);

        $claim->load([
            'patient',
            'patientInsurance.plan.provider',
            'checkin',
            'consultation',
            'admission',
            'items.charge',
            'vettedBy',
            'submittedBy',
        ]);

        // Support both Inertia and JSON responses
        if (request()->wantsJson()) {
            return response()->json([
                'claim' => InsuranceClaimResource::make($claim)->resolve(),
                'can' => [
                    'vet' => auth()->user()->can('vetClaim', $claim),
                    'submit' => auth()->user()->can('submitClaim', $claim),
                    'approve' => auth()->user()->can('approveClaim', $claim),
                    'reject' => auth()->user()->can('rejectClaim', $claim),
                ],
            ]);
        }

        return Inertia::render('Admin/Insurance/Claims/Show', [
            'claim' => InsuranceClaimResource::make($claim)->resolve(),
            'can' => [
                'vet' => auth()->user()->can('vetClaim', $claim),
                'submit' => auth()->user()->can('submitClaim', $claim),
                'approve' => auth()->user()->can('approveClaim', $claim),
                'reject' => auth()->user()->can('rejectClaim', $claim),
            ],
        ]);
    }

    public function vet(Request $request, InsuranceClaim $claim)
    {
        $this->authorize('vetClaim', $claim);

        $validated = $request->validate([
            'action' => 'required|in:approve,reject',
            'rejection_reason' => 'required_if:action,reject|nullable|string|max:1000',
            'items' => 'sometimes|array',
            'items.*.id' => 'required|exists:insurance_claim_items,id',
            'items.*.is_approved' => 'required|boolean',
            'items.*.rejection_reason' => 'nullable|string|max:500',
        ]);

        // Update individual claim items if provided
        if (isset($validated['items'])) {
            foreach ($validated['items'] as $itemData) {
                $item = $claim->items()->findOrFail($itemData['id']);
                $item->update([
                    'is_approved' => $itemData['is_approved'],
                    'rejection_reason' => $itemData['rejection_reason'] ?? null,
                ]);
            }

            // Recalculate approved amount based on approved items
            $approvedAmount = $claim->items()->where('is_approved', true)->sum('insurance_pays');
            $claim->approved_amount = $approvedAmount;
            $claim->insurance_covered_amount = $approvedAmount;
            $claim->patient_copay_amount = $claim->total_claim_amount - $approvedAmount;
        }

        if ($validated['action'] === 'approve') {
            $claim->status = 'vetted';
            $claim->vetted_by = auth()->id();
            $claim->vetted_at = now();
            $claim->rejection_reason = null;
        } else {
            $claim->status = 'rejected';
            $claim->rejection_reason = $validated['rejection_reason'];
            $claim->vetted_by = auth()->id();
            $claim->vetted_at = now();
        }

        $claim->save();

        return redirect()->route('admin.insurance.claims.show', $claim)
            ->with('success', $validated['action'] === 'approve'
                ? 'Claim has been vetted and approved.'
                : 'Claim has been rejected.');
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
