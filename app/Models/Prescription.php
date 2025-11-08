<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Prescription extends Model
{
    /** @use HasFactory<\Database\Factories\PrescriptionFactory> */
    use HasFactory;

    protected $fillable = [
        'consultation_id',
        'prescribable_type',
        'prescribable_id',
        'drug_id',
        'medication_name',
        'frequency',
        'schedule_pattern',
        'duration',
        'dose_quantity',
        'quantity',
        'quantity_to_dispense',
        'quantity_dispensed',
        'dosage_form',
        'instructions',
        'status',
        'reviewed_by',
        'reviewed_at',
        'dispensing_notes',
        'external_reason',
        'discontinued_at',
        'discontinued_by_id',
        'discontinuation_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'string',
            'reviewed_at' => 'datetime',
            'discontinued_at' => 'datetime',
            'schedule_pattern' => 'json',
        ];
    }

    public function prescribable(): MorphTo
    {
        return $this->morphTo();
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function drug(): BelongsTo
    {
        return $this->belongsTo(Drug::class);
    }

    public function dispensings(): HasMany
    {
        return $this->hasMany(Dispensing::class);
    }

    public function charge(): HasOne
    {
        return $this->hasOne(Charge::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function discontinuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'discontinued_by_id');
    }

    public function dispensing(): HasOne
    {
        return $this->hasOne(Dispensing::class);
    }

    public function medicationAdministrations(): HasMany
    {
        return $this->hasMany(MedicationAdministration::class);
    }

    public function scopeByStatus($query, string $status): void
    {
        $query->where('status', $status);
    }

    public function scopePrescribed($query): void
    {
        $query->where('status', 'prescribed');
    }

    public function scopeDispensed($query): void
    {
        $query->where('status', 'dispensed');
    }

    public function scopeCancelled($query): void
    {
        $query->where('status', 'cancelled');
    }

    public function markDispensed(): void
    {
        $this->update(['status' => 'dispensed']);
    }

    public function markCancelled(): void
    {
        $this->update(['status' => 'cancelled']);
    }

    // Status check methods
    public function isPrescribed(): bool
    {
        return $this->status === 'prescribed';
    }

    public function isReviewed(): bool
    {
        return $this->status === 'reviewed';
    }

    public function isDispensed(): bool
    {
        return $this->status === 'dispensed';
    }

    public function isPartiallyDispensed(): bool
    {
        return $this->status === 'partially_dispensed';
    }

    public function isNotDispensed(): bool
    {
        return $this->status === 'not_dispensed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function getRemainingQuantity(): int
    {
        $toDispense = $this->quantity_to_dispense ?? $this->quantity;

        return $toDispense - $this->quantity_dispensed;
    }

    public function canBeReviewed(): bool
    {
        return $this->isPrescribed() && $this->drug_id !== null;
    }

    public function canBeDispensed(): bool
    {
        return $this->isReviewed() && $this->getRemainingQuantity() > 0;
    }

    // Scope for reviewed prescriptions
    public function scopeReviewed($query): void
    {
        $query->where('status', 'reviewed');
    }

    public function scopePartiallyDispensed($query): void
    {
        $query->where('status', 'partially_dispensed');
    }

    public function scopeNotDispensed($query): void
    {
        $query->where('status', 'not_dispensed');
    }

    public function scopeActive($query): void
    {
        $query->whereNull('discontinued_at');
    }

    public function discontinue(User $user, ?string $reason = null): void
    {
        $this->update([
            'discontinued_at' => now(),
            'discontinued_by_id' => $user->id,
            'discontinuation_reason' => $reason,
        ]);
    }

    public function isDiscontinued(): bool
    {
        return $this->discontinued_at !== null;
    }

    public function canBeDiscontinued(): bool
    {
        return ! $this->isDiscontinued();
    }

    public function hasSchedule(): bool
    {
        return $this->schedule_pattern !== null;
    }

    public function isPendingSchedule(): bool
    {
        return $this->schedule_pattern === null && $this->frequency !== 'PRN';
    }
}
