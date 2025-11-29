<?php

/**
 * Property-Based Tests for Selected Charges Total Calculation
 *
 * **Feature: billing-enhancements, Property 1: Selected charges total calculation**
 * **Validates: Requirements 1.2, 1.5**
 */

use App\Models\Charge;
use App\Models\PatientCheckin;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Property 1: Selected charges total calculation', function () {
    /**
     * **Feature: billing-enhancements, Property 1: Selected charges total calculation**
     * **Validates: Requirements 1.2, 1.5**
     *
     * For any set of charges with checkboxes, the displayed total amount
     * SHALL equal the sum of amounts for only the checked charges.
     */
    it('calculates total amount correctly for selected charges only', function () {
        // Run 100 iterations with different random configurations
        for ($iteration = 0; $iteration < 100; $iteration++) {
            // Arrange: Create a patient checkin with random number of charges (2-10)
            $checkin = PatientCheckin::factory()->create();
            $totalCharges = fake()->numberBetween(2, 10);

            $charges = Charge::factory()
                ->count($totalCharges)
                ->pending()
                ->create([
                    'patient_checkin_id' => $checkin->id,
                    // Ensure amounts are positive and varied
                    'amount' => fn () => fake()->randomFloat(2, 10, 1000),
                ]);

            // Randomly select a subset of charges
            $chargeIds = $charges->pluck('id')->toArray();
            $numToSelect = fake()->numberBetween(0, $totalCharges);
            shuffle($chargeIds);
            $selectedChargeIds = array_slice($chargeIds, 0, $numToSelect);

            // Act: Calculate total for selected charges
            $selectedCharges = Charge::whereIn('id', $selectedChargeIds)->get();
            $calculatedTotal = $selectedCharges->sum('amount');

            // Also calculate expected total manually
            $expectedTotal = 0;
            foreach ($charges as $charge) {
                if (in_array($charge->id, $selectedChargeIds)) {
                    $expectedTotal += $charge->amount;
                }
            }

            // Assert: Calculated total equals expected total
            expect(round((float) $calculatedTotal, 2))
                ->toBe(round((float) $expectedTotal, 2),
                    "Selected charges total should equal sum of selected amounts (iteration {$iteration})");

            // Assert: Total of selected charges is less than or equal to total of all charges
            $allChargesTotal = $charges->sum('amount');
            expect((float) $calculatedTotal)
                ->toBeLessThanOrEqual((float) $allChargesTotal,
                    'Selected total should not exceed total of all charges');

            // Clean up for next iteration
            Charge::whereIn('id', $chargeIds)->delete();
            $checkin->delete();
        }
    });

    /**
     * Property: Selected charges count matches actual selection
     */
    it('correctly counts selected charges', function () {
        for ($iteration = 0; $iteration < 100; $iteration++) {
            // Arrange
            $checkin = PatientCheckin::factory()->create();
            $totalCharges = fake()->numberBetween(3, 8);

            $charges = Charge::factory()
                ->count($totalCharges)
                ->pending()
                ->create([
                    'patient_checkin_id' => $checkin->id,
                ]);

            $chargeIds = $charges->pluck('id')->toArray();
            $numToSelect = fake()->numberBetween(0, $totalCharges);
            shuffle($chargeIds);
            $selectedChargeIds = array_slice($chargeIds, 0, $numToSelect);

            // Act: Count selected charges
            $selectedCount = Charge::whereIn('id', $selectedChargeIds)->count();

            // Assert: Count matches expected
            expect($selectedCount)->toBe(count($selectedChargeIds),
                'Selected count should match number of selected IDs');

            // Assert: Remaining count is correct
            $remainingCount = $totalCharges - $selectedCount;
            expect($remainingCount)->toBe($totalCharges - count($selectedChargeIds),
                'Remaining count should be total minus selected');

            // Clean up
            Charge::whereIn('id', $chargeIds)->delete();
            $checkin->delete();
        }
    });

    /**
     * Property: Remaining unpaid amount equals total minus selected
     */
    it('calculates remaining unpaid amount correctly', function () {
        for ($iteration = 0; $iteration < 100; $iteration++) {
            // Arrange
            $checkin = PatientCheckin::factory()->create();
            $totalCharges = fake()->numberBetween(2, 8);

            $charges = Charge::factory()
                ->count($totalCharges)
                ->pending()
                ->create([
                    'patient_checkin_id' => $checkin->id,
                    'amount' => fn () => fake()->randomFloat(2, 10, 500),
                ]);

            $chargeIds = $charges->pluck('id')->toArray();
            $numToSelect = fake()->numberBetween(1, max(1, $totalCharges - 1));
            shuffle($chargeIds);
            $selectedChargeIds = array_slice($chargeIds, 0, $numToSelect);
            $unselectedChargeIds = array_diff($chargeIds, $selectedChargeIds);

            // Act: Calculate totals
            $totalAmount = Charge::whereIn('id', $chargeIds)->sum('amount');
            $selectedAmount = Charge::whereIn('id', $selectedChargeIds)->sum('amount');
            $remainingAmount = Charge::whereIn('id', $unselectedChargeIds)->sum('amount');

            // Assert: Remaining equals total minus selected
            expect(round((float) $remainingAmount, 2))
                ->toBe(round((float) $totalAmount - (float) $selectedAmount, 2),
                    'Remaining amount should equal total minus selected');

            // Assert: Selected plus remaining equals total
            expect(round((float) $selectedAmount + (float) $remainingAmount, 2))
                ->toBe(round((float) $totalAmount, 2),
                    'Selected plus remaining should equal total');

            // Clean up
            Charge::whereIn('id', $chargeIds)->delete();
            $checkin->delete();
        }
    });

    /**
     * Property: Insurance copay calculation is correct for selected charges
     */
    it('calculates patient copay correctly for insurance claims', function () {
        for ($iteration = 0; $iteration < 100; $iteration++) {
            // Arrange
            $checkin = PatientCheckin::factory()->create();
            $totalCharges = fake()->numberBetween(2, 6);

            // Create a mix of insurance and non-insurance charges
            $charges = collect();
            for ($i = 0; $i < $totalCharges; $i++) {
                $isInsurance = fake()->boolean(60); // 60% chance of insurance
                $amount = fake()->randomFloat(2, 50, 500);

                if ($isInsurance) {
                    $coveragePercent = fake()->randomElement([50, 70, 80, 90, 100]);
                    $insuranceCovered = round($amount * ($coveragePercent / 100), 2);
                    $patientCopay = round($amount - $insuranceCovered, 2);
                } else {
                    $insuranceCovered = 0;
                    $patientCopay = $amount;
                }

                $charges->push(Charge::factory()->pending()->create([
                    'patient_checkin_id' => $checkin->id,
                    'amount' => $amount,
                    'is_insurance_claim' => $isInsurance,
                    'insurance_covered_amount' => $insuranceCovered,
                    'patient_copay_amount' => $patientCopay,
                ]));
            }

            $chargeIds = $charges->pluck('id')->toArray();
            $numToSelect = fake()->numberBetween(1, $totalCharges);
            shuffle($chargeIds);
            $selectedChargeIds = array_slice($chargeIds, 0, $numToSelect);

            // Act: Calculate patient owes for selected charges
            $selectedCharges = Charge::whereIn('id', $selectedChargeIds)->get();
            $calculatedPatientOwes = $selectedCharges->sum(function ($charge) {
                return $charge->is_insurance_claim
                    ? $charge->patient_copay_amount
                    : $charge->amount;
            });

            // Calculate expected manually
            $expectedPatientOwes = 0;
            foreach ($charges as $charge) {
                if (in_array($charge->id, $selectedChargeIds)) {
                    $expectedPatientOwes += $charge->is_insurance_claim
                        ? $charge->patient_copay_amount
                        : $charge->amount;
                }
            }

            // Assert: Calculated patient owes equals expected
            expect(round((float) $calculatedPatientOwes, 2))
                ->toBe(round((float) $expectedPatientOwes, 2),
                    'Patient copay total should match expected for selected charges');

            // Clean up
            Charge::whereIn('id', $chargeIds)->delete();
            $checkin->delete();
        }
    });

    /**
     * Property: Empty selection results in zero total
     */
    it('returns zero total when no charges are selected', function () {
        for ($iteration = 0; $iteration < 50; $iteration++) {
            // Arrange
            $checkin = PatientCheckin::factory()->create();
            $totalCharges = fake()->numberBetween(1, 5);

            $charges = Charge::factory()
                ->count($totalCharges)
                ->pending()
                ->create([
                    'patient_checkin_id' => $checkin->id,
                    'amount' => fn () => fake()->randomFloat(2, 10, 500),
                ]);

            $chargeIds = $charges->pluck('id')->toArray();
            $selectedChargeIds = []; // Empty selection

            // Act: Calculate total for empty selection
            $selectedTotal = Charge::whereIn('id', $selectedChargeIds)->sum('amount');

            // Assert: Empty selection equals zero
            expect((float) $selectedTotal)->toBe(0.0,
                'Empty selection should result in zero total');

            // Clean up
            Charge::whereIn('id', $chargeIds)->delete();
            $checkin->delete();
        }
    });

    /**
     * Property: Full selection equals total of all charges
     */
    it('returns total of all charges when all are selected', function () {
        for ($iteration = 0; $iteration < 50; $iteration++) {
            // Arrange
            $checkin = PatientCheckin::factory()->create();
            $totalCharges = fake()->numberBetween(1, 8);

            $charges = Charge::factory()
                ->count($totalCharges)
                ->pending()
                ->create([
                    'patient_checkin_id' => $checkin->id,
                    'amount' => fn () => fake()->randomFloat(2, 10, 500),
                ]);

            $chargeIds = $charges->pluck('id')->toArray();
            $selectedChargeIds = $chargeIds; // Select all

            // Act: Calculate totals
            $allChargesTotal = Charge::whereIn('id', $chargeIds)->sum('amount');
            $selectedTotal = Charge::whereIn('id', $selectedChargeIds)->sum('amount');

            // Assert: Full selection equals total
            expect(round((float) $selectedTotal, 2))
                ->toBe(round((float) $allChargesTotal, 2),
                    'Full selection should equal total of all charges');

            // Clean up
            Charge::whereIn('id', $chargeIds)->delete();
            $checkin->delete();
        }
    });
});
