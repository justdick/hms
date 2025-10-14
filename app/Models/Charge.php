<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Charge extends Model
{
    /** @use HasFactory<\Database\Factories\ChargeFactory> */
    use HasFactory;

    protected $fillable = [
        'patient_checkin_id',
        'prescription_id',
        'service_type',
        'service_code',
        'description',
        'amount',
        'charge_type',
        'status',
        'paid_amount',
        'charged_at',
        'due_date',
        'paid_at',
        'metadata',
        'created_by_type',
        'created_by_id',
        'is_emergency_override',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'charged_at' => 'datetime',
            'due_date' => 'datetime',
            'paid_at' => 'datetime',
            'metadata' => 'json',
            'is_emergency_override' => 'boolean',
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
}
