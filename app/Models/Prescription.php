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

    protected static function booted(): void
    {
        // When a prescription is deleted, also delete related charge and claim items
        static::deleting(function (Prescription $prescription) {
            // Find and delete related charge (which will cascade to claim items)
            if ($prescription->charge) {
                // Delete claim items linked to this charge
                InsuranceClaimItem::where('charge_id', $prescription->charge->id)->delete();
                // Delete the charge
                $prescription->charge->delete();
            }
        });
    }

    protected $fillable = [
        'consultation_id',
        'refilled_from_prescription_id',
        'prescribable_type',
        'prescribable_id',
        'drug_id',
        'medication_name',
        'frequency',
        'duration',
        'dose_quantity',
        'quantity',
        'quantity_to_dispense',
        'quantity_dispensed',
        'dosage_form',
        'instructions',
        'status',
        'is_unpriced',
        'reviewed_by',
        'reviewed_at',
        'dispensing_notes',
        'external_reason',
        'migrated_from_mittag',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'string',
            'is_unpriced' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    protected $appends = [
        'discontinued_at',
        'discontinued_by',
        'discontinuation_reason',
        'completed_at',
        'completed_by',
        'completion_reason',
    ];

    /**
     * Get the discontinued_at timestamp from the latest discontinue action.
     * Returns null if not discontinued or if resumed after.
     */
    public function getDiscontinuedAtAttribute(): ?string
    {
        if (! $this->isDiscontinued()) {
            return null;
        }

        $latestDiscontinue = $this->statusChanges()
            ->where('action', 'discontinued')
            ->orderBy('performed_at', 'desc')
            ->first();

        return $latestDiscontinue?->performed_at?->toISOString();
    }

    /**
     * Get the user who discontinued this prescription.
     */
    public function getDiscontinuedByAttribute(): ?array
    {
        if (! $this->isDiscontinued()) {
            return null;
        }

        $latestDiscontinue = $this->statusChanges()
            ->where('action', 'discontinued')
            ->with('performedBy:id,name')
            ->orderBy('performed_at', 'desc')
            ->first();

        return $latestDiscontinue?->performedBy?->only(['id', 'name']);
    }

    /**
     * Get the discontinuation reason.
     */
    public function getDiscontinuationReasonAttribute(): ?string
    {
        if (! $this->isDiscontinued()) {
            return null;
        }

        $latestDiscontinue = $this->statusChanges()
            ->where('action', 'discontinued')
            ->orderBy('performed_at', 'desc')
            ->first();

        return $latestDiscontinue?->reason;
    }

    /**
     * Get the completed_at timestamp from the latest complete action.
     * Returns null if not completed.
     */
    public function getCompletedAtAttribute(): ?string
    {
        if (! $this->isCompleted()) {
            return null;
        }

        $latestComplete = $this->statusChanges()
            ->where('action', 'completed')
            ->orderBy('performed_at', 'desc')
            ->first();

        return $latestComplete?->performed_at?->toISOString();
    }

    /**
     * Get the user who completed this prescription.
     */
    public function getCompletedByAttribute(): ?array
    {
        if (! $this->isCompleted()) {
            return null;
        }

        $latestComplete = $this->statusChanges()
            ->where('action', 'completed')
            ->with('performedBy:id,name')
            ->orderBy('performed_at', 'desc')
            ->first();

        return $latestComplete?->performedBy?->only(['id', 'name']);
    }

    /**
     * Get the completion reason/notes.
     */
    public function getCompletionReasonAttribute(): ?string
    {
        if (! $this->isCompleted()) {
            return null;
        }

        $latestComplete = $this->statusChanges()
            ->where('action', 'completed')
            ->orderBy('performed_at', 'desc')
            ->first();

        return $latestComplete?->reason;
    }

    public function prescribable(): MorphTo
    {
        return $this->morphTo();
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function refilledFrom(): BelongsTo
    {
        return $this->belongsTo(Prescription::class, 'refilled_from_prescription_id');
    }

    public function refills(): HasMany
    {
        return $this->hasMany(Prescription::class, 'refilled_from_prescription_id');
    }

    public function isRefill(): bool
    {
        return $this->refilled_from_prescription_id !== null;
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

    public function statusChanges(): HasMany
    {
        return $this->hasMany(PrescriptionStatusChange::class)->orderBy('performed_at', 'desc');
    }

    public function latestStatusChange(): HasOne
    {
        return $this->hasOne(PrescriptionStatusChange::class)->latestOfMany('performed_at');
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
        // Active = no status changes OR latest status change is 'resumed'
        $query->where(function ($q) {
            $q->whereDoesntHave('statusChanges')
                ->orWhereHas('latestStatusChange', function ($sq) {
                    $sq->where('action', 'resumed');
                });
        });
    }

    public function discontinue(User $user, ?string $reason = null): void
    {
        $this->statusChanges()->create([
            'action' => 'discontinued',
            'performed_by_id' => $user->id,
            'performed_at' => now(),
            'reason' => $reason,
        ]);
    }

    public function isDiscontinued(): bool
    {
        $latest = $this->latestStatusChange;

        return $latest && $latest->action === 'discontinued';
    }

    public function isCompleted(): bool
    {
        $latest = $this->latestStatusChange;

        return $latest && $latest->action === 'completed';
    }

    public function canBeDiscontinued(): bool
    {
        return ! $this->isDiscontinued() && ! $this->isCompleted();
    }

    public function canBeCompleted(): bool
    {
        return ! $this->isDiscontinued() && ! $this->isCompleted();
    }

    public function resume(User $user, ?string $reason = null): void
    {
        $this->statusChanges()->create([
            'action' => 'resumed',
            'performed_by_id' => $user->id,
            'performed_at' => now(),
            'reason' => $reason,
        ]);
    }

    public function complete(User $user, ?string $reason = null): void
    {
        $this->statusChanges()->create([
            'action' => 'completed',
            'performed_by_id' => $user->id,
            'performed_at' => now(),
            'reason' => $reason,
        ]);
    }

    public function uncomplete(User $user, ?string $reason = null): void
    {
        // Revert to active by creating a 'resumed' status change
        $this->statusChanges()->create([
            'action' => 'resumed',
            'performed_by_id' => $user->id,
            'performed_at' => now(),
            'reason' => $reason ?? 'Completion reverted',
        ]);
    }

    public function canBeUncompleted(): bool
    {
        return $this->isCompleted();
    }

    /**
     * Check if this prescription is for an admitted patient (IPD).
     */
    public function isForAdmittedPatient(): bool
    {
        // If prescribable is WardRound, it's for an admitted patient
        if ($this->prescribable_type === 'App\Models\WardRound') {
            return true;
        }

        // If prescribable is Consultation, check if it has an admission
        if ($this->prescribable_type === 'App\Models\Consultation') {
            return $this->prescribable?->patientAdmission !== null;
        }

        // If prescription has direct consultation_id, check for admission
        if ($this->consultation_id) {
            return $this->consultation?->patientAdmission !== null;
        }

        return false;
    }

    /**
     * Get the patient admission for this prescription (if any).
     */
    public function getPatientAdmission(): ?PatientAdmission
    {
        if ($this->prescribable_type === 'App\Models\WardRound') {
            return $this->prescribable?->patientAdmission;
        }

        if ($this->prescribable_type === 'App\Models\Consultation') {
            return $this->prescribable?->patientAdmission;
        }

        if ($this->consultation_id) {
            return $this->consultation?->patientAdmission;
        }

        return null;
    }

    /**
     * Get expected doses per day based on frequency.
     */
    public function getExpectedDosesPerDay(): int
    {
        $frequency = strtoupper(trim($this->frequency ?? ''));

        // Extract frequency code from descriptive text if needed
        if (preg_match('/\((BID|BD|TID|TDS|QID|QDS|Q12H|Q8H|Q6H|Q4H|Q2H|OD|PRN)\)/i', $this->frequency, $matches)) {
            $frequency = strtoupper($matches[1]);
        } elseif (preg_match('/\b(BID|BD|TID|TDS|QID|QDS|Q12H|Q8H|Q6H|Q4H|Q2H|OD|PRN)\b/i', $this->frequency, $matches)) {
            $frequency = strtoupper($matches[1]);
        }

        return match ($frequency) {
            'OD' => 1,
            'BD', 'BID', 'Q12H' => 2,
            'TDS', 'TID', 'Q8H' => 3,
            'QDS', 'QID', 'Q6H' => 4,
            'Q4H' => 6,
            'Q2H' => 12,
            'PRN' => 0, // As needed - no fixed count
            default => 1,
        };
    }

    /**
     * Get today's administration count for this prescription.
     */
    public function getTodayAdministrationCount(): int
    {
        return $this->medicationAdministrations()
            ->whereDate('administered_at', today())
            ->where('status', 'given')
            ->count();
    }

    /**
     * Check if this is a PRN (as needed) prescription.
     */
    public function isPrn(): bool
    {
        $frequency = strtoupper(trim($this->frequency ?? ''));

        return $frequency === 'PRN'
            || str_contains(strtoupper($this->frequency ?? ''), 'PRN')
            || str_contains(strtolower($this->frequency ?? ''), 'as needed');
    }
}
