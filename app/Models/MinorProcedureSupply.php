<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MinorProcedureSupply extends Model
{
    /** @use HasFactory<\Database\Factories\MinorProcedureSupplyFactory> */
    use HasFactory;

    protected $fillable = [
        'minor_procedure_id',
        'drug_id',
        'quantity',
        'status',
        'reviewed_by',
        'reviewed_at',
        'quantity_to_dispense',
        'dispensing_notes',
        'dispensed',
        'dispensed_at',
        'dispensed_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'quantity_to_dispense' => 'decimal:2',
            'dispensed' => 'boolean',
            'reviewed_at' => 'datetime',
            'dispensed_at' => 'datetime',
        ];
    }

    public function minorProcedure(): BelongsTo
    {
        return $this->belongsTo(MinorProcedure::class);
    }

    public function drug(): BelongsTo
    {
        return $this->belongsTo(Drug::class);
    }

    public function dispenser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispensed_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending($query): void
    {
        $query->where('status', 'pending');
    }

    public function scopeReviewed($query): void
    {
        $query->where('status', 'reviewed');
    }

    public function scopeDispensed($query): void
    {
        $query->where('status', 'dispensed');
    }

    public function scopeToday($query): void
    {
        $query->whereDate('dispensed_at', today());
    }

    public function markAsDispensed(User $user): void
    {
        $this->update([
            'status' => 'dispensed',
            'dispensed' => true,
            'dispensed_at' => now(),
            'dispensed_by' => $user->id,
        ]);
    }

    public function markAsReviewed(User $user, ?float $quantityToDispense = null, ?string $notes = null): void
    {
        $this->update([
            'status' => 'reviewed',
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
            'quantity_to_dispense' => $quantityToDispense ?? $this->quantity,
            'dispensing_notes' => $notes,
        ]);
    }
}
