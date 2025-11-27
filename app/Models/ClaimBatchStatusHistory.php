<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClaimBatchStatusHistory extends Model
{
    /** @use HasFactory<\Database\Factories\ClaimBatchStatusHistoryFactory> */
    use HasFactory;

    protected $table = 'claim_batch_status_history';

    protected $fillable = [
        'claim_batch_id',
        'user_id',
        'previous_status',
        'new_status',
        'notes',
    ];

    /**
     * Get the batch this history entry belongs to.
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ClaimBatch::class, 'claim_batch_id');
    }

    /**
     * Get the user who made the status change.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
