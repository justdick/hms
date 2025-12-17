<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepartmentBilling extends Model
{
    /** @use HasFactory<\Database\Factories\DepartmentBillingFactory> */
    use HasFactory;

    protected $fillable = [
        'department_id',
        'department_code',
        'department_name',
        'consultation_fee',
        'equipment_fee',
        'emergency_surcharge',
        'payment_required_before_consultation',
        'emergency_override_allowed',
        'payment_grace_period_minutes',
        'allow_partial_payment',
        'payment_plan_available',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'consultation_fee' => 'decimal:2',
            'equipment_fee' => 'decimal:2',
            'emergency_surcharge' => 'decimal:2',
            'payment_required_before_consultation' => 'boolean',
            'emergency_override_allowed' => 'boolean',
            'allow_partial_payment' => 'boolean',
            'payment_plan_available' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get billing configuration for a department by ID
     */
    public static function getForDepartment(int $departmentId): ?static
    {
        return static::where('department_id', $departmentId)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get billing configuration for a department by code (legacy support)
     *
     * @deprecated Use getForDepartment() with department_id instead
     */
    public static function getForDepartmentCode(string $departmentCode): ?static
    {
        return static::where('department_code', $departmentCode)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Check if payment is required before consultation for this department
     */
    public function isPaymentRequiredBeforeConsultation(): bool
    {
        return $this->payment_required_before_consultation;
    }

    /**
     * Check if emergency override is allowed for this department
     */
    public function isEmergencyOverrideAllowed(): bool
    {
        return $this->emergency_override_allowed;
    }

    /**
     * Get total consultation fee including equipment fee
     */
    public function getTotalConsultationFee(): float
    {
        return $this->consultation_fee + $this->equipment_fee;
    }

    /**
     * Get total fee including emergency surcharge if applicable
     */
    public function getTotalFeeWithEmergency(): float
    {
        return $this->getTotalConsultationFee() + $this->emergency_surcharge;
    }

    /**
     * Check if patient can proceed with consultation
     */
    public function canProceedWithConsultation(float $paidAmount = 0, bool $isEmergency = false): bool
    {
        if ($isEmergency && $this->emergency_override_allowed) {
            return true;
        }

        if (! $this->payment_required_before_consultation) {
            return true;
        }

        $requiredAmount = $this->getTotalConsultationFee();

        if ($this->allow_partial_payment) {
            return $paidAmount > 0;
        }

        return $paidAmount >= $requiredAmount;
    }

    /**
     * Get the department this billing configuration belongs to
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Scope for active departments
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
