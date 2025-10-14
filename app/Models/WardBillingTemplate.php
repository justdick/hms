<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WardBillingTemplate extends Model
{
    protected $fillable = [
        'service_name',
        'service_code',
        'description',
        'billing_type',
        'base_amount',
        'percentage_rate',
        'calculation_rules',
        'effective_from',
        'effective_to',
        'applicable_ward_types',
        'patient_category_rules',
        'auto_trigger_conditions',
        'payment_requirement',
        'integration_points',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'base_amount' => 'decimal:2',
            'percentage_rate' => 'decimal:2',
            'calculation_rules' => 'json',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'applicable_ward_types' => 'json',
            'patient_category_rules' => 'json',
            'auto_trigger_conditions' => 'json',
            'integration_points' => 'json',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForWardType($query, string $wardType)
    {
        return $query->whereJsonContains('applicable_ward_types', $wardType);
    }

    public function scopeBillingType($query, string $billingType)
    {
        return $query->where('billing_type', $billingType);
    }

    public function scopeEffective($query, $date = null)
    {
        $date = $date ?? now()->toDateString();

        return $query->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $date);
            });
    }

    public function isEffective($date = null): bool
    {
        $date = $date ?? now()->toDateString();

        return $this->effective_from <= $date &&
               (is_null($this->effective_to) || $this->effective_to >= $date);
    }

    public function appliesToWardType(string $wardType): bool
    {
        return in_array($wardType, $this->applicable_ward_types ?? []);
    }

    public function calculateAmount(array $context = []): float
    {
        $amount = $this->base_amount;

        // Apply patient category rules
        if ($patientCategory = $context['patient_category'] ?? null) {
            $rules = $this->patient_category_rules[$patientCategory] ?? null;

            if ($rules && isset($rules['discount_percentage'])) {
                $discount = $rules['discount_percentage'] / 100;
                $amount = $amount * (1 - $discount);
            }

            if ($rules && isset($rules['surcharge_percentage'])) {
                $surcharge = $rules['surcharge_percentage'] / 100;
                $amount = $amount * (1 + $surcharge);
            }
        }

        return round($amount, 2);
    }
}
