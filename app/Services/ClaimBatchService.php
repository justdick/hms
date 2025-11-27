<?php

namespace App\Services;

use App\Models\ClaimBatch;
use App\Models\ClaimBatchItem;
use App\Models\ClaimBatchStatusHistory;
use App\Models\InsuranceClaim;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ClaimBatchService
{
    /**
     * Create a new claim batch.
     *
     * @param  string  $name  The batch name
     * @param  Carbon  $submissionPeriod  The submission period (month/year)
     * @param  User  $createdBy  The user creating the batch
     * @param  string|null  $notes  Optional notes
     *
     * _Requirements: 14.1, 14.4_
     */
    public function createBatch(
        string $name,
        Carbon $submissionPeriod,
        User $createdBy,
        ?string $notes = null
    ): ClaimBatch {
        $batchNumber = $this->generateBatchNumber();

        $batch = ClaimBatch::create([
            'batch_number' => $batchNumber,
            'name' => $name,
            'submission_period' => $submissionPeriod->startOfMonth(),
            'status' => 'draft',
            'total_claims' => 0,
            'total_amount' => 0,
            'created_by' => $createdBy->id,
            'notes' => $notes,
        ]);

        // Record initial status in history
        $this->recordStatusChange($batch, null, 'draft', $createdBy, 'Batch created');

        return $batch;
    }

    /**
     * Generate a unique batch number.
     * Format: BATCH-YYYYMM-XXXX
     */
    protected function generateBatchNumber(): string
    {
        $prefix = 'BATCH-'.now()->format('Ym').'-';

        // Get the latest batch number for this month
        $latestBatch = ClaimBatch::where('batch_number', 'like', $prefix.'%')
            ->orderBy('batch_number', 'desc')
            ->first();

        if ($latestBatch) {
            // Extract the sequence number and increment
            $sequence = (int) substr($latestBatch->batch_number, -4);
            $newSequence = $sequence + 1;
        } else {
            $newSequence = 1;
        }

        return $prefix.str_pad($newSequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Add claims to a batch.
     * Only vetted claims can be added to a batch.
     * Batch must not be finalized.
     *
     * @param  ClaimBatch  $batch  The batch to add claims to
     * @param  array<int>|Collection  $claimIds  Array of insurance claim IDs
     * @return array{added: int, skipped: int, errors: array}
     *
     * @throws InvalidArgumentException
     *
     * _Requirements: 14.2_
     */
    public function addClaimsToBatch(ClaimBatch $batch, array|Collection $claimIds): array
    {
        // Validate batch can be modified
        if (! $batch->canBeModified()) {
            throw new InvalidArgumentException('This batch has been finalized and cannot be modified.');
        }

        $claimIds = collect($claimIds);
        $added = 0;
        $skipped = 0;
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($claimIds as $claimId) {
                $claim = InsuranceClaim::find($claimId);

                if (! $claim) {
                    $errors[] = "Claim ID {$claimId} not found.";
                    $skipped++;

                    continue;
                }

                // Validate claim is vetted
                if ($claim->status !== 'vetted') {
                    $errors[] = "Claim {$claim->claim_check_code} is not vetted. Only vetted claims can be added to a batch.";
                    $skipped++;

                    continue;
                }

                // Check if claim is already in this batch
                $existingItem = ClaimBatchItem::where('claim_batch_id', $batch->id)
                    ->where('insurance_claim_id', $claimId)
                    ->exists();

                if ($existingItem) {
                    $errors[] = "Claim {$claim->claim_check_code} is already in this batch.";
                    $skipped++;

                    continue;
                }

                // Add claim to batch
                ClaimBatchItem::create([
                    'claim_batch_id' => $batch->id,
                    'insurance_claim_id' => $claim->id,
                    'claim_amount' => $claim->total_claim_amount ?? 0,
                    'status' => 'pending',
                ]);

                $added++;
            }

            // Update batch totals
            $this->updateBatchTotals($batch);

            DB::commit();

            return [
                'added' => $added,
                'skipped' => $skipped,
                'errors' => $errors,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Remove a claim from a batch.
     * Batch must not be finalized.
     *
     * @throws InvalidArgumentException
     *
     * _Requirements: 14.2_
     */
    public function removeClaimFromBatch(ClaimBatch $batch, InsuranceClaim $claim): bool
    {
        // Validate batch can be modified
        if (! $batch->canBeModified()) {
            throw new InvalidArgumentException('This batch has been finalized and cannot be modified.');
        }

        $batchItem = ClaimBatchItem::where('claim_batch_id', $batch->id)
            ->where('insurance_claim_id', $claim->id)
            ->first();

        if (! $batchItem) {
            throw new InvalidArgumentException('Claim is not in this batch.');
        }

        DB::beginTransaction();

        try {
            $batchItem->delete();

            // Update batch totals
            $this->updateBatchTotals($batch);

            DB::commit();

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Finalize a batch, preventing further modifications.
     *
     * @throws InvalidArgumentException
     *
     * _Requirements: 14.5_
     */
    public function finalizeBatch(ClaimBatch $batch, ?User $user = null): ClaimBatch
    {
        if (! $batch->isDraft()) {
            throw new InvalidArgumentException('Only draft batches can be finalized.');
        }

        if ($batch->total_claims === 0) {
            throw new InvalidArgumentException('Cannot finalize an empty batch.');
        }

        $previousStatus = $batch->status;
        $batch->status = 'finalized';
        $batch->save();

        // Record status change
        $this->recordStatusChange($batch, $previousStatus, 'finalized', $user, 'Batch finalized');

        return $batch->fresh();
    }

    /**
     * Mark a batch as submitted.
     *
     * @param  Carbon|null  $submittedAt  The submission timestamp (defaults to now)
     * @param  User|null  $user  The user marking the batch as submitted
     *
     * @throws InvalidArgumentException
     *
     * _Requirements: 16.1_
     */
    public function markSubmitted(ClaimBatch $batch, ?Carbon $submittedAt = null, ?User $user = null): ClaimBatch
    {
        if (! $batch->isFinalized()) {
            throw new InvalidArgumentException('Only finalized batches can be marked as submitted.');
        }

        $previousStatus = $batch->status;
        $batch->status = 'submitted';
        $batch->submitted_at = $submittedAt ?? now();
        $batch->save();

        // Record status change
        $this->recordStatusChange($batch, $previousStatus, 'submitted', $user, 'Batch submitted to NHIA');

        return $batch->fresh();
    }

    /**
     * Record NHIA response for claims in a batch.
     *
     * @param  ClaimBatch  $batch  The batch to record response for
     * @param  array<int, array{approved_amount?: float, status: string, rejection_reason?: string}>  $responses  Keyed by insurance_claim_id
     * @param  Carbon|null  $paidAt  Payment date if recording payment
     * @param  float|null  $paidAmount  Total paid amount
     * @return array{processed: int, errors: array}
     *
     * @throws InvalidArgumentException
     *
     * _Requirements: 17.1, 17.2, 17.3_
     */
    public function recordResponse(
        ClaimBatch $batch,
        array $responses,
        ?Carbon $paidAt = null,
        ?float $paidAmount = null
    ): array {
        if (! $batch->isSubmitted()) {
            throw new InvalidArgumentException('Can only record responses for submitted batches.');
        }

        $processed = 0;
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($responses as $claimId => $response) {
                $batchItem = ClaimBatchItem::where('claim_batch_id', $batch->id)
                    ->where('insurance_claim_id', $claimId)
                    ->first();

                if (! $batchItem) {
                    $errors[] = "Claim ID {$claimId} is not in this batch.";

                    continue;
                }

                // Update batch item status
                $status = $response['status'] ?? 'pending';
                if (! in_array($status, ['pending', 'approved', 'rejected', 'paid'])) {
                    $errors[] = "Invalid status '{$status}' for claim ID {$claimId}.";

                    continue;
                }

                $batchItem->status = $status;

                if ($status === 'approved' || $status === 'paid') {
                    $batchItem->approved_amount = $response['approved_amount'] ?? $batchItem->claim_amount;
                }

                if ($status === 'rejected') {
                    $batchItem->rejection_reason = $response['rejection_reason'] ?? null;
                }

                $batchItem->save();

                // Also update the insurance claim record
                $claim = $batchItem->insuranceClaim;
                if ($claim) {
                    if ($status === 'approved') {
                        $claim->status = 'approved';
                        $claim->approved_amount = $batchItem->approved_amount;
                        $claim->approval_date = now();
                    } elseif ($status === 'rejected') {
                        $claim->status = 'rejected';
                        $claim->rejection_reason = $batchItem->rejection_reason;
                        $claim->rejected_at = now();
                    } elseif ($status === 'paid') {
                        $claim->status = 'paid';
                        $claim->payment_date = $paidAt ?? now();
                        $claim->payment_amount = $batchItem->approved_amount;
                    }
                    $claim->save();
                }

                $processed++;
            }

            // Update batch totals
            $this->updateBatchTotals($batch);

            // Update batch payment info if provided
            if ($paidAt !== null) {
                $batch->paid_at = $paidAt;
            }
            if ($paidAmount !== null) {
                $batch->paid_amount = $paidAmount;
            }

            // Check if all items are processed and update batch status
            $this->updateBatchStatusFromItems($batch);

            $batch->save();

            DB::commit();

            return [
                'processed' => $processed,
                'errors' => $errors,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update batch totals based on batch items.
     */
    protected function updateBatchTotals(ClaimBatch $batch): void
    {
        $items = $batch->batchItems()->get();

        $batch->total_claims = $items->count();
        $batch->total_amount = $items->sum('claim_amount');
        $batch->approved_amount = $items->whereNotNull('approved_amount')->sum('approved_amount');

        $batch->save();
    }

    /**
     * Update batch status based on item statuses.
     */
    protected function updateBatchStatusFromItems(ClaimBatch $batch, ?User $user = null): void
    {
        $items = $batch->batchItems()->get();

        if ($items->isEmpty()) {
            return;
        }

        $previousStatus = $batch->status;

        // If all items are paid, mark batch as completed
        if ($items->every(fn ($item) => $item->status === 'paid')) {
            $batch->status = 'completed';
            if ($previousStatus !== 'completed') {
                $this->recordStatusChange($batch, $previousStatus, 'completed', $user, 'All claims paid');
            }

            return;
        }

        // If all items have a response (approved, rejected, or paid), mark as processing
        if ($items->every(fn ($item) => in_array($item->status, ['approved', 'rejected', 'paid']))) {
            if ($previousStatus !== 'processing') {
                $batch->status = 'processing';
                $this->recordStatusChange($batch, $previousStatus, 'processing', $user, 'All claims processed');
            }
        }
    }

    /**
     * Record a status change in the history.
     */
    protected function recordStatusChange(
        ClaimBatch $batch,
        ?string $previousStatus,
        string $newStatus,
        ?User $user = null,
        ?string $notes = null
    ): ClaimBatchStatusHistory {
        return ClaimBatchStatusHistory::create([
            'claim_batch_id' => $batch->id,
            'user_id' => $user?->id ?? Auth::id(),
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'notes' => $notes,
        ]);
    }
}
