<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClaimBatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'batch_number' => $this->batch_number,
            'name' => $this->name,
            'submission_period' => $this->submission_period?->toDateString(),
            'submission_period_formatted' => $this->submission_period?->format('F Y'),
            'status' => $this->status,
            'status_label' => ucfirst($this->status),

            // Totals
            'total_claims' => $this->total_claims,
            'total_amount' => $this->total_amount,
            'approved_amount' => $this->approved_amount,
            'paid_amount' => $this->paid_amount,

            // Timestamps
            'submitted_at' => $this->submitted_at?->toISOString(),
            'exported_at' => $this->exported_at?->toISOString(),
            'paid_at' => $this->paid_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Notes
            'notes' => $this->notes,

            // Status flags
            'is_draft' => $this->isDraft(),
            'is_finalized' => $this->isFinalized(),
            'is_submitted' => $this->isSubmitted(),
            'is_completed' => $this->isCompleted(),
            'can_be_modified' => $this->canBeModified(),

            // Relationships
            'creator' => $this->when($this->relationLoaded('creator') && $this->creator, fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),

            'batch_items' => $this->when($this->relationLoaded('batchItems'), fn () => $this->batchItems->map(fn ($item) => [
                'id' => $item->id,
                'insurance_claim_id' => $item->insurance_claim_id,
                'claim_amount' => $item->claim_amount,
                'approved_amount' => $item->approved_amount,
                'status' => $item->status,
                'status_label' => ucfirst($item->status),
                'rejection_reason' => $item->rejection_reason,
                'claim' => $this->when($item->relationLoaded('insuranceClaim') && $item->insuranceClaim, fn () => [
                    'id' => $item->insuranceClaim->id,
                    'claim_check_code' => $item->insuranceClaim->claim_check_code,
                    'patient_name' => $item->insuranceClaim->patient_surname.' '.$item->insuranceClaim->patient_other_names,
                    'membership_id' => $item->insuranceClaim->membership_id,
                    'date_of_attendance' => $item->insuranceClaim->date_of_attendance?->toDateString(),
                    'total_claim_amount' => $item->insuranceClaim->total_claim_amount,
                    'provider_name' => $item->insuranceClaim->patientInsurance?->plan?->provider?->name,
                ]),
            ])),

            'batch_items_count' => $this->when(
                $this->relationLoaded('batchItems'),
                fn () => $this->batchItems->count(),
                fn () => $this->batch_items_count ?? null
            ),

            'status_history' => $this->when($this->relationLoaded('statusHistory'), fn () => $this->statusHistory->map(fn ($history) => [
                'id' => $history->id,
                'previous_status' => $history->previous_status,
                'new_status' => $history->new_status,
                'notes' => $history->notes,
                'created_at' => $history->created_at?->toISOString(),
                'user' => $this->when($history->relationLoaded('user') && $history->user, fn () => [
                    'id' => $history->user->id,
                    'name' => $history->user->name,
                ]),
            ])),
        ];
    }
}
