<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClaimBatchItem extends Model
{
    /** @use HasFactory<\Database\Factories\ClaimBatchItemFactory> */
    use HasFactory;

    protected $fillable = [
        'claim_batch_id',
        'insurance_claim_id',
        'claim_amount',
        'approved_amount',
        'status',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'claim_amount' => 'decimal:2',
            'approved_amount' => 'decimal:2',
        ];
    }

    /**
     * Get the batch this item belongs to.
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ClaimBatch::class, 'claim_batch_id');
    }

    /**
     * Get the insurance claim.
     */
    public function insuranceClaim(): BelongsTo
    {
        return $this->belongsTo(InsuranceClaim::class);
    }

    /**
     * Check if the item is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if the item is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if the item is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Check if the item is paid.
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }
}
