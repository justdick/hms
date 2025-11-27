<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClaimBatch extends Model
{
    /** @use HasFactory<\Database\Factories\ClaimBatchFactory> */
    use HasFactory;

    protected $fillable = [
        'batch_number',
        'name',
        'submission_period',
        'status',
        'total_claims',
        'total_amount',
        'approved_amount',
        'paid_amount',
        'submitted_at',
        'exported_at',
        'paid_at',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'submission_period' => 'date',
            'total_amount' => 'decimal:2',
            'approved_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'submitted_at' => 'datetime',
            'exported_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * Get the batch items.
     */
    public function batchItems(): HasMany
    {
        return $this->hasMany(ClaimBatchItem::class);
    }

    /**
     * Get the user who created the batch.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the status history for this batch.
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(ClaimBatchStatusHistory::class);
    }

    /**
     * Check if the batch is in draft status.
     */
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Check if the batch is finalized.
     */
    public function isFinalized(): bool
    {
        return $this->status === 'finalized';
    }

    /**
     * Check if the batch has been submitted.
     */
    public function isSubmitted(): bool
    {
        return in_array($this->status, ['submitted', 'processing', 'completed']);
    }

    /**
     * Check if the batch is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if the batch can be modified (add/remove claims).
     */
    public function canBeModified(): bool
    {
        return $this->isDraft();
    }

    /**
     * Scope to filter by status.
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter draft batches.
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope to filter finalized batches.
     */
    public function scopeFinalized($query)
    {
        return $query->where('status', 'finalized');
    }

    /**
     * Scope to filter submitted batches.
     */
    public function scopeSubmitted($query)
    {
        return $query->whereIn('status', ['submitted', 'processing', 'completed']);
    }
}
