<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillAdjustment extends Model
{
    protected $fillable = [
        'charge_id',
        'adjustment_type',
        'original_amount',
        'adjustment_amount',
        'final_amount',
        'reason',
        'adjusted_by',
        'adjusted_at',
    ];

    protected function casts(): array
    {
        return [
            'original_amount' => 'decimal:2',
            'adjustment_amount' => 'decimal:2',
            'final_amount' => 'decimal:2',
            'adjusted_at' => 'datetime',
        ];
    }

    public function charge(): BelongsTo
    {
        return $this->belongsTo(Charge::class);
    }

    public function adjustedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }

    public function getAdjustmentPercentage(): float
    {
        if ($this->original_amount <= 0) {
            return 0;
        }

        return round(($this->adjustment_amount / $this->original_amount) * 100, 2);
    }

    public function isWaiver(): bool
    {
        return $this->adjustment_type === 'waiver';
    }

    public function scopeForCharge($query, int $chargeId)
    {
        return $query->where('charge_id', $chargeId);
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('adjusted_by', $userId);
    }
}
