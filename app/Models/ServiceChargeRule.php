<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceChargeRule extends Model
{
    protected $fillable = [
        'service_type',
        'service_code',
        'service_name',
        'charge_timing',
        'payment_required',
        'payment_timing',
        'emergency_override_allowed',
        'partial_payment_allowed',
        'payment_plans_available',
        'grace_period_days',
        'late_fees_enabled',
        'service_blocking_enabled',
        'hide_details_until_paid',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'emergency_override_allowed' => 'boolean',
            'partial_payment_allowed' => 'boolean',
            'payment_plans_available' => 'boolean',
            'late_fees_enabled' => 'boolean',
            'service_blocking_enabled' => 'boolean',
            'hide_details_until_paid' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForService($query, string $serviceType, ?string $serviceCode = null)
    {
        $query = $query->where('service_type', $serviceType);

        if ($serviceCode) {
            $query->where('service_code', $serviceCode);
        }

        return $query;
    }

    public function scopePaymentRequired($query)
    {
        return $query->where('payment_required', 'mandatory');
    }

    public function requiresPayment(): bool
    {
        return $this->payment_required === 'mandatory';
    }

    public function allowsEmergencyOverride(): bool
    {
        return $this->emergency_override_allowed;
    }

    public function shouldHideDetailsUntilPaid(): bool
    {
        return $this->hide_details_until_paid;
    }

    public function shouldBlockService(): bool
    {
        return $this->service_blocking_enabled;
    }
}
