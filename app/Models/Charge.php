<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Charge extends Model
{
    /** @use HasFactory<\Database\Factories\ChargeFactory> */
    use HasFactory;

    protected $fillable = [
        'patient_checkin_id',
        'prescription_id',
        'insurance_claim_id',
        'insurance_claim_item_id',
        'service_type',
        'service_code',
        'description',
        'amount',
        'insurance_tariff_amount',
        'charge_type',
        'status',
        'is_insurance_claim',
        'paid_amount',
        'insurance_covered_amount',
        'patient_copay_amount',
        'charged_at',
        'due_date',
        'paid_at',
        'receipt_number',
        'processed_by',
        'metadata',
        'created_by_type',
        'created_by_id',
        'is_emergency_override',
        'notes',
        'is_waived',
        'waived_by',
        'waived_at',
        'waived_reason',
        'adjustment_amount',
        'original_amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'insurance_tariff_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'insurance_covered_amount' => 'decimal:2',
            'patient_copay_amount' => 'decimal:2',
            'charged_at' => 'datetime',
            'due_date' => 'datetime',
            'paid_at' => 'datetime',
            'metadata' => 'json',
            'is_insurance_claim' => 'boolean',
            'is_emergency_override' => 'boolean',
            'is_waived' => 'boolean',
            'waived_at' => 'datetime',
            'adjustment_amount' => 'decimal:2',
            'original_amount' => 'decimal:2',
        ];
    }

    public function patientCheckin(): BelongsTo
    {
        return $this->belongsTo(PatientCheckin::class);
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function insuranceClaim(): BelongsTo
    {
        return $this->belongsTo(InsuranceClaim::class);
    }

    public function insuranceClaimItem(): BelongsTo
    {
        return $this->belongsTo(InsuranceClaimItem::class);
    }

    public function processedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function claimItems(): HasMany
    {
        return $this->hasMany(InsuranceClaimItem::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isPartiallyPaid(): bool
    {
        return $this->status === 'partial';
    }

    public function isVoided(): bool
    {
        return $this->status === 'voided';
    }

    public function isOwing(): bool
    {
        return $this->status === 'owing';
    }

    public function getRemainingAmount(): float
    {
        return $this->amount - $this->paid_amount;
    }

    public function canProceedWithService(): bool
    {
        if ($this->is_emergency_override) {
            return true;
        }

        return $this->isPaid() || $this->getRemainingAmount() <= 0;
    }

    public function markAsPaid(?float $amount = null): void
    {
        $amount = $amount ?? $this->amount;

        $this->update([
            'paid_amount' => $amount,
            'paid_at' => now(),
            'status' => $amount >= $this->amount ? 'paid' : 'partial',
        ]);
    }

    public function markAsVoided(?string $reason = null): void
    {
        $this->update([
            'status' => 'voided',
            'notes' => $reason ? "Voided: {$reason}" : 'Voided due to cancelled check-in',
        ]);
    }

    public function markAsOwing(?string $reason = null): void
    {
        $this->update([
            'status' => 'owing',
            'notes' => $reason,
        ]);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeNotVoided($query)
    {
        return $query->where('status', '!=', 'voided');
    }

    public function scopeOwing($query)
    {
        return $query->where('status', 'owing');
    }

    public function scopeForService($query, string $serviceType, ?string $serviceCode = null)
    {
        $query = $query->where('service_type', $serviceType);

        if ($serviceCode) {
            $query->where('service_code', $serviceCode);
        }

        return $query;
    }

    public function scopeForPatient($query, int $patientCheckinId)
    {
        return $query->where('patient_checkin_id', $patientCheckinId);
    }

    public function isInsuranceClaim(): bool
    {
        return $this->is_insurance_claim === true;
    }

    public function hasInsuranceCoverage(): bool
    {
        return $this->is_insurance_claim && $this->insurance_covered_amount > 0;
    }

    public function getCoveragePercentage(): float
    {
        if (! $this->is_insurance_claim || $this->amount <= 0) {
            return 0.0;
        }

        return round(($this->insurance_covered_amount / $this->amount) * 100, 2);
    }

    public function getInsuranceCoverageDisplay(): string
    {
        if (! $this->is_insurance_claim) {
            return 'No Coverage';
        }

        $percentage = $this->getCoveragePercentage();

        if ($percentage >= 100) {
            return 'Fully Covered';
        }

        if ($percentage > 0) {
            return "{$percentage}% Covered";
        }

        return 'Not Covered';
    }

    public function scopeWithInsurance($query)
    {
        return $query->where('is_insurance_claim', true);
    }

    public function scopeWithoutInsurance($query)
    {
        return $query->where('is_insurance_claim', false);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(BillAdjustment::class);
    }

    public function isWaived(): bool
    {
        return $this->is_waived === true;
    }

    public function hasAdjustment(): bool
    {
        return $this->adjustment_amount > 0;
    }

    public function getEffectiveAmount(): float
    {
        if ($this->is_waived) {
            return 0;
        }

        return $this->amount;
    }

    public function scopeWaived($query)
    {
        return $query->where('is_waived', true);
    }

    public function scopeAdjusted($query)
    {
        return $query->where('adjustment_amount', '>', 0);
    }
}
