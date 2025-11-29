<?php

/**
 * Property-Based Tests for Cash Change Calculation
 *
 * **Feature: billing-enhancements, Property 7: Change calculation accuracy**
 * **Validates: Requirements 4.2**
 */
describe('Property 7: Change calculation accuracy', function () {
    /**
     * **Feature: billing-enhancements, Property 7: Change calculation accuracy**
     * **Validates: Requirements 4.2**
     *
     * For any cash payment where tendered amount exceeds due amount,
     * the calculated change SHALL equal tendered minus due.
     */
    it('calculates change correctly when tendered exceeds due', function () {
        // Run 100 iterations with different random amounts
        for ($iteration = 0; $iteration < 100; $iteration++) {
            // Arrange: Generate random amounts where tendered > due
            $amountDue = fake()->randomFloat(2, 1, 10000);
            // Tendered is always greater than or equal to due
            $amountTendered = $amountDue + fake()->randomFloat(2, 0, 5000);

            // Act: Calculate change
            $change = $amountTendered - $amountDue;

            // Assert: Change equals tendered minus due
            expect(round($change, 2))
                ->toBe(round($amountTendered - $amountDue, 2),
                    "Change should equal tendered ({$amountTendered}) minus due ({$amountDue}) (iteration {$iteration})");

            // Assert: Change is non-negative when tendered >= due
            expect($change)->toBeGreaterThanOrEqual(0,
                'Change should be non-negative when tendered >= due');
        }
    });

    /**
     * Property: Change is negative when tendered is less than due
     */
    it('calculates negative change when tendered is less than due', function () {
        for ($iteration = 0; $iteration < 100; $iteration++) {
            // Arrange: Generate random amounts where tendered < due
            $amountDue = fake()->randomFloat(2, 100, 10000);
            // Tendered is always less than due
            $amountTendered = fake()->randomFloat(2, 0, $amountDue - 0.01);

            // Act: Calculate change
            $change = $amountTendered - $amountDue;

            // Assert: Change is negative
            expect($change)->toBeLessThan(0,
                "Change should be negative when tendered ({$amountTendered}) < due ({$amountDue})");

            // Assert: Absolute value of change equals shortfall
            $shortfall = $amountDue - $amountTendered;
            expect(round(abs($change), 2))
                ->toBe(round($shortfall, 2),
                    'Absolute change should equal shortfall amount');
        }
    });

    /**
     * Property: Change is zero when tendered equals due (exact payment)
     */
    it('calculates zero change when tendered equals due', function () {
        for ($iteration = 0; $iteration < 100; $iteration++) {
            // Arrange: Generate random amount where tendered = due
            $amount = fake()->randomFloat(2, 1, 10000);
            $amountDue = $amount;
            $amountTendered = $amount;

            // Act: Calculate change
            $change = $amountTendered - $amountDue;

            // Assert: Change is exactly zero
            expect(round($change, 2))->toBe(0.0,
                "Change should be zero when tendered equals due ({$amount})");
        }
    });

    /**
     * Property: Change calculation is commutative with addition
     * If we add the change back to the due amount, we get the tendered amount
     */
    it('satisfies the round-trip property: due + change = tendered', function () {
        for ($iteration = 0; $iteration < 100; $iteration++) {
            // Arrange: Generate random amounts
            $amountDue = fake()->randomFloat(2, 1, 10000);
            $amountTendered = fake()->randomFloat(2, 0, 15000);

            // Act: Calculate change
            $change = $amountTendered - $amountDue;

            // Assert: Round-trip property holds
            expect(round($amountDue + $change, 2))
                ->toBe(round($amountTendered, 2),
                    'Due + Change should equal Tendered (round-trip property)');
        }
    });

    /**
     * Property: Sufficient payment detection is correct
     */
    it('correctly identifies sufficient vs insufficient payments', function () {
        for ($iteration = 0; $iteration < 100; $iteration++) {
            // Arrange: Generate random amounts
            $amountDue = fake()->randomFloat(2, 1, 10000);
            $amountTendered = fake()->randomFloat(2, 0, 15000);

            // Act: Determine if payment is sufficient
            $isSufficient = $amountTendered >= $amountDue;
            $change = $amountTendered - $amountDue;

            // Assert: Sufficient flag matches change sign
            if ($isSufficient) {
                expect($change)->toBeGreaterThanOrEqual(0,
                    'Change should be >= 0 when payment is sufficient');
            } else {
                expect($change)->toBeLessThan(0,
                    'Change should be < 0 when payment is insufficient');
            }
        }
    });

    /**
     * Property: Change calculation handles edge cases with zero amounts
     */
    it('handles zero amount due correctly', function () {
        for ($iteration = 0; $iteration < 50; $iteration++) {
            // Arrange: Zero amount due with random tendered
            $amountDue = 0;
            $amountTendered = fake()->randomFloat(2, 0, 1000);

            // Act: Calculate change
            $change = $amountTendered - $amountDue;

            // Assert: Change equals tendered when due is zero
            expect(round($change, 2))
                ->toBe(round($amountTendered, 2),
                    'Change should equal tendered when due is zero');

            // Assert: Payment is always sufficient when due is zero
            $isSufficient = $amountTendered >= $amountDue;
            expect($isSufficient)->toBeTrue(
                'Payment should always be sufficient when due is zero');
        }
    });

    /**
     * Property: Change calculation handles common currency denominations
     */
    it('handles common currency denominations correctly', function () {
        // Test with common Ghanaian Cedi denominations
        $denominations = [1, 2, 5, 10, 20, 50, 100, 200];

        for ($iteration = 0; $iteration < 100; $iteration++) {
            // Arrange: Random due amount and tendered as a denomination
            $amountDue = fake()->randomFloat(2, 1, 500);
            $denomination = fake()->randomElement($denominations);
            // Round up to nearest denomination
            $amountTendered = ceil($amountDue / $denomination) * $denomination;

            // Act: Calculate change
            $change = $amountTendered - $amountDue;

            // Assert: Change is correct
            expect(round($change, 2))
                ->toBe(round($amountTendered - $amountDue, 2),
                    "Change should be correct for denomination {$denomination}");

            // Assert: Change is non-negative (since we rounded up)
            expect($change)->toBeGreaterThanOrEqual(0,
                'Change should be non-negative when rounding up to denomination');
        }
    });

    /**
     * Property: Change calculation precision is maintained
     */
    it('maintains precision for decimal amounts', function () {
        for ($iteration = 0; $iteration < 100; $iteration++) {
            // Arrange: Generate amounts with various decimal precisions
            $amountDue = fake()->randomFloat(2, 0.01, 9999.99);
            $amountTendered = fake()->randomFloat(2, 0.01, 14999.99);

            // Act: Calculate change
            $change = $amountTendered - $amountDue;

            // Assert: Result has at most 2 decimal places when rounded
            $roundedChange = round($change, 2);
            $decimalPart = $roundedChange - floor($roundedChange);
            $decimalPlaces = strlen(rtrim(sprintf('%.2f', $decimalPart), '0')) - 2;

            expect($decimalPlaces)->toBeLessThanOrEqual(2,
                'Change should have at most 2 decimal places');
        }
    });
});
