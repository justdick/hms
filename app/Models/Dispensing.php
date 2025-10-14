<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dispensing extends Model
{
    /** @use HasFactory<\Database\Factories\DispensingFactory> */
    use HasFactory;

    protected $fillable = [
        'prescription_id',
        'patient_id',
        'drug_id',
        'drug_batch_id',
        'quantity',
        'batch_info',
        'dispensed_by',
        'dispensed_at',
        'notes',
        // Legacy fields from original migration
        'quantity_dispensed',
        'unit_price',
        'total_amount',
        'instructions',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'dispensed_at' => 'datetime',
            'unit_price' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function drugBatch(): BelongsTo
    {
        return $this->belongsTo(DrugBatch::class);
    }

    public function dispensedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispensed_by');
    }

    public function scopeDispensed($query): void
    {
        $query->where('status', 'dispensed');
    }

    public function scopeByStatus($query, string $status): void
    {
        $query->where('status', $status);
    }

    public function scopeToday($query): void
    {
        $query->whereDate('dispensed_at', today());
    }

    public function scopeThisMonth($query): void
    {
        $query->whereMonth('dispensed_at', now()->month)
            ->whereYear('dispensed_at', now()->year);
    }
}
