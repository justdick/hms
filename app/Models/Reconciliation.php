<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reconciliation extends Model
{
    /** @use HasFactory<\Database\Factories\ReconciliationFactory> */
    use HasFactory;

    protected $fillable = [
        'cashier_id',
        'finance_officer_id',
        'reconciliation_date',
        'system_total',
        'physical_count',
        'variance',
        'variance_reason',
        'denomination_breakdown',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'reconciliation_date' => 'date',
            'system_total' => 'decimal:2',
            'physical_count' => 'decimal:2',
            'variance' => 'decimal:2',
            'denomination_breakdown' => 'json',
        ];
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function financeOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finance_officer_id');
    }

    public function isBalanced(): bool
    {
        return $this->status === 'balanced';
    }

    public function hasVariance(): bool
    {
        return $this->status === 'variance';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function scopeBalanced($query)
    {
        return $query->where('status', 'balanced');
    }

    public function scopeWithVariance($query)
    {
        return $query->where('status', 'variance');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeForCashier($query, int $cashierId)
    {
        return $query->where('cashier_id', $cashierId);
    }

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('reconciliation_date', $date);
    }

    public function scopeForDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('reconciliation_date', [$startDate, $endDate]);
    }
}
