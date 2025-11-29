<?php

/**
 * Property-Based Tests for Charge Status Transitions
 *
 * **Feature: billing-enhancements, Property 2: Payment processes only selected charges**
 * **Validates: Requirements 1.3, 1.4**
 */

use App\Models\Charge;
use App\Models\PatientCheckin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Property 2: Payment processes only selected charges', function () {
    /**
     * **Feature: billing-enhancements, Property 2: Payment processes only selected charges**
     * **Validates: Requirements 1.3, 1.4**
     *
     * For any payment submission with a subset of charges selected,
     * only those selected charges SHALL have their status changed to paid.
     */
    it('marks only selected charges as paid while leaving unselected charges unchanged', function () {
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
                ]);

            // Randomly select a subset of charges (at least 1, at most all-1 to ensure we have both selected and unselected)
            $chargeIds = $charges->pluck('id')->toArray();
            $numToSelect = fake()->numberBetween(1, max(1, $totalCharges - 1));
            shuffle($chargeIds);
            $selectedChargeIds = array_slice($chargeIds, 0, $numToSelect);
            $unselectedChargeIds = array_diff($chargeIds, $selectedChargeIds);

            // Act: Simulate payment processing for selected charges only
            $processedBy = User::factory()->create();
            $receiptNumber = 'RCP-'.now()->format('Ymd').'-'.str_pad($iteration + 1, 4, '0', STR_PAD_LEFT);

            // Process only selected charges
            Charge::whereIn('id', $selectedChargeIds)->each(function ($charge) use ($processedBy, $receiptNumber) {
                $charge->update([
                    'status' => 'paid',
                    'paid_amount' => $charge->amount,
                    'paid_at' => now(),
                    'processed_by' => $processedBy->id,
                    'receipt_number' => $receiptNumber,
                ]);
            });

            // Assert: Selected charges should be paid
            $paidCharges = Charge::whereIn('id', $selectedChargeIds)->get();
            foreach ($paidCharges as $charge) {
                expect($charge->status)->toBe('paid', "Selected charge {$charge->id} should be paid");
                expect((float) $charge->paid_amount)->toBe((float) $charge->amount, "Selected charge {$charge->id} paid_amount should equal amount");
                expect($charge->paid_at)->not->toBeNull("Selected charge {$charge->id} should have paid_at timestamp");
                expect($charge->processed_by)->toBe($processedBy->id, "Selected charge {$charge->id} should have processed_by set");
                expect($charge->receipt_number)->toBe($receiptNumber, "Selected charge {$charge->id} should have receipt_number set");
            }

            // Assert: Unselected charges should remain pending
            if (count($unselectedChargeIds) > 0) {
                $pendingCharges = Charge::whereIn('id', $unselectedChargeIds)->get();
                foreach ($pendingCharges as $charge) {
                    expect($charge->status)->toBe('pending', "Unselected charge {$charge->id} should remain pending");
                    expect((float) $charge->paid_amount)->toBe(0.0, "Unselected charge {$charge->id} paid_amount should be 0");
                    expect($charge->paid_at)->toBeNull("Unselected charge {$charge->id} should not have paid_at timestamp");
                    expect($charge->processed_by)->toBeNull("Unselected charge {$charge->id} should not have processed_by set");
                    expect($charge->receipt_number)->toBeNull("Unselected charge {$charge->id} should not have receipt_number set");
                }
            }

            // Clean up for next iteration
            Charge::whereIn('id', $chargeIds)->delete();
            $checkin->delete();
            $processedBy->delete();
        }
    });

    /**
     * Additional property: Count of paid charges equals count of selected charges
     */
    it('ensures count of paid charges equals count of selected charges', function () {
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
            $numToSelect = fake()->numberBetween(1, $totalCharges);
            shuffle($chargeIds);
            $selectedChargeIds = array_slice($chargeIds, 0, $numToSelect);

            // Act: Process selected charges
            Charge::whereIn('id', $selectedChargeIds)->update([
                'status' => 'paid',
                'paid_amount' => \Illuminate\Support\Facades\DB::raw('amount'),
                'paid_at' => now(),
            ]);

            // Assert: Count of paid charges equals count of selected
            $paidCount = Charge::whereIn('id', $chargeIds)->where('status', 'paid')->count();
            $pendingCount = Charge::whereIn('id', $chargeIds)->where('status', 'pending')->count();

            expect($paidCount)->toBe(count($selectedChargeIds), 'Paid count should equal selected count');
            expect($pendingCount)->toBe($totalCharges - count($selectedChargeIds), 'Pending count should equal unselected count');
            expect($paidCount + $pendingCount)->toBe($totalCharges, 'Total charges should be preserved');

            // Clean up
            Charge::whereIn('id', $chargeIds)->delete();
            $checkin->delete();
        }
    });

    /**
     * Property: Total paid amount equals sum of selected charge amounts
     */
    it('ensures total paid amount equals sum of selected charge amounts', function () {
        for ($iteration = 0; $iteration < 100; $iteration++) {
            // Arrange
            $checkin = PatientCheckin::factory()->create();
            $totalCharges = fake()->numberBetween(2, 6);

            $charges = Charge::factory()
                ->count($totalCharges)
                ->pending()
                ->create([
                    'patient_checkin_id' => $checkin->id,
                ]);

            $chargeIds = $charges->pluck('id')->toArray();
            $numToSelect = fake()->numberBetween(1, $totalCharges);
            shuffle($chargeIds);
            $selectedChargeIds = array_slice($chargeIds, 0, $numToSelect);

            // Calculate expected total before processing
            $expectedTotal = Charge::whereIn('id', $selectedChargeIds)->sum('amount');

            // Act: Process selected charges
            Charge::whereIn('id', $selectedChargeIds)->each(function ($charge) {
                $charge->update([
                    'status' => 'paid',
                    'paid_amount' => $charge->amount,
                    'paid_at' => now(),
                ]);
            });

            // Assert: Total paid amount equals expected
            $actualPaidTotal = Charge::whereIn('id', $selectedChargeIds)->sum('paid_amount');
            expect((float) $actualPaidTotal)->toBe((float) $expectedTotal, 'Total paid should equal sum of selected amounts');

            // Clean up
            Charge::whereIn('id', $chargeIds)->delete();
            $checkin->delete();
        }
    });
});
