<?php

namespace App\Services;

use App\Models\Charge;

class BillAdjustmentService
{
    /**
     * Calculate the adjusted amount based on adjustment type and value.
     */
    public function calculateAdjustedAmount(float $originalAmount, string $adjustmentType, float $adjustmentValue): float
    {
        if ($originalAmount < 0) {
            throw new \InvalidArgumentException('Original amount cannot be negative');
        }

        if ($adjustmentValue < 0) {
            throw new \InvalidArgumentException('Adjustment value cannot be negative');
        }

        return match ($adjustmentType) {
            'discount_percentage' => $this->calculatePercentageDiscount($originalAmount, $adjustmentValue),
            'discount_fixed' => $this->calculateFixedDiscount($originalAmount, $adjustmentValue),
            'waiver' => 0.0,
            default => throw new \InvalidArgumentException("Invalid adjustment type: {$adjustmentType}"),
        };
    }

    /**
     * Validate if an adjustment is valid for a charge.
     */
    public function validateAdjustment(Charge $charge, string $adjustmentType, float $adjustmentValue): array
    {
        $errors = [];

        // Check if charge is in a valid status
        if (! $charge->isPending()) {
            $errors[] = 'Can only adjust pending charges';
        }

        // Check if charge is already waived
        if ($charge->isWaived()) {
            $errors[] = 'Cannot adjust a charge that has been waived';
        }

        // Check if charge has already been adjusted
        if ($charge->hasAdjustment()) {
            $errors[] = 'Charge has already been adjusted';
        }

        // Validate adjustment value based on type
        if ($adjustmentType === 'discount_percentage') {
            if ($adjustmentValue < 0 || $adjustmentValue > 100) {
                $errors[] = 'Percentage discount must be between 0 and 100';
            }
        }

        if ($adjustmentType === 'discount_fixed') {
            if ($adjustmentValue > $charge->amount) {
                $errors[] = 'Fixed discount cannot exceed charge amount';
            }
        }

        return $errors;
    }

    /**
     * Calculate the adjustment amount (how much is being discounted).
     */
    public function calculateAdjustmentAmount(float $originalAmount, string $adjustmentType, float $adjustmentValue): float
    {
        $finalAmount = $this->calculateAdjustedAmount($originalAmount, $adjustmentType, $adjustmentValue);

        return $originalAmount - $finalAmount;
    }

    /**
     * Calculate percentage discount.
     */
    private function calculatePercentageDiscount(float $originalAmount, float $percentage): float
    {
        if ($percentage < 0 || $percentage > 100) {
            throw new \InvalidArgumentException('Percentage must be between 0 and 100');
        }

        $discount = ($percentage / 100) * $originalAmount;
        $finalAmount = $originalAmount - $discount;

        return round($finalAmount, 2);
    }

    /**
     * Calculate fixed amount discount.
     */
    private function calculateFixedDiscount(float $originalAmount, float $discountAmount): float
    {
        if ($discountAmount > $originalAmount) {
            throw new \InvalidArgumentException('Discount amount cannot exceed original amount');
        }

        $finalAmount = $originalAmount - $discountAmount;

        return round($finalAmount, 2);
    }
}
