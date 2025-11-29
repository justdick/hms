<?php

/**
 * Property-Based Tests for Receipt Generation
 *
 * **Feature: billing-enhancements, Property 5: Receipt number format and uniqueness**
 * **Feature: billing-enhancements, Property 6: Receipt data completeness**
 * **Validates: Requirements 3.3, 3.4**
 */

use App\Models\Charge;
use App\Models\Patient;
use App\Models\PatientCheckin;
use App\Models\User;
use App\Services\ReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Property 5: Receipt number format and uniqueness', function () {
    /**
     * **Feature: billing-enhancements, Property 5: Receipt number format and uniqueness**
     * **Validates: Requirements 3.4**
     *
     * For any generated receipt number, it SHALL match the pattern RCP-YYYYMMDD-NNNN
     * and be unique within the system.
     */
    it('generates receipt numbers matching the required format', function () {
        $receiptService = app(ReceiptService::class);
        $checkin = PatientCheckin::factory()->create();

        // Run 100 iterations - each creates a charge with the receipt number
        for ($iteration = 0; $iteration < 100; $iteration++) {
            // Act: Generate a receipt number and assign it to a charge
            $receiptNumber = $receiptService->generateReceiptNumber();

            // Create a charge with this receipt number to persist it
            Charge::factory()->create([
                'patient_checkin_id' => $checkin->id,
                'receipt_number' => $receiptNumber,
                'paid_at' => now(),
            ]);

            // Assert: Format matches RCP-YYYYMMDD-NNNN
            expect($receiptService->isValidReceiptNumber($receiptNumber))
                ->toBeTrue("Receipt number '{$receiptNumber}' should match format RCP-YYYYMMDD-NNNN");

            // Assert: Pattern validation using regex
            expect($receiptNumber)
                ->toMatch('/^RCP-\d{8}-\d{4}$/', 'Receipt number should match pattern RCP-YYYYMMDD-NNNN');

            // Assert: Date portion is today's date
            $expectedDatePart = now()->format('Ymd');
            $expectedPrefix = "RCP-{$expectedDatePart}-";
            expect(str_starts_with($receiptNumber, $expectedPrefix))
                ->toBeTrue("Receipt number '{$receiptNumber}' should start with '{$expectedPrefix}'");
        }
    });

    it('generates unique receipt numbers across multiple calls', function () {
        $receiptService = app(ReceiptService::class);
        $checkin = PatientCheckin::factory()->create();
        $generatedNumbers = [];

        // Generate 100 receipt numbers - each persisted to database
        for ($iteration = 0; $iteration < 100; $iteration++) {
            $receiptNumber = $receiptService->generateReceiptNumber();

            // Assert: This number hasn't been generated before
            expect(in_array($receiptNumber, $generatedNumbers))
                ->toBeFalse("Receipt number '{$receiptNumber}' should be unique (iteration {$iteration})");

            // Persist the receipt number to database
            Charge::factory()->create([
                'patient_checkin_id' => $checkin->id,
                'receipt_number' => $receiptNumber,
                'paid_at' => now(),
            ]);

            $generatedNumbers[] = $receiptNumber;
        }

        // Assert: All generated numbers are unique
        expect(count($generatedNumbers))
            ->toBe(count(array_unique($generatedNumbers)), 'All receipt numbers should be unique');
    });

    it('generates sequential receipt numbers for the same day', function () {
        $receiptService = app(ReceiptService::class);
        $checkin = PatientCheckin::factory()->create();
        $previousNumber = null;

        // Generate 50 receipt numbers and verify they're sequential
        for ($iteration = 0; $iteration < 50; $iteration++) {
            $receiptNumber = $receiptService->generateReceiptNumber();

            // Persist to database
            Charge::factory()->create([
                'patient_checkin_id' => $checkin->id,
                'receipt_number' => $receiptNumber,
                'paid_at' => now(),
            ]);

            // Extract the sequence number (last 4 digits)
            $sequenceNumber = (int) substr($receiptNumber, -4);

            if ($previousNumber !== null) {
                $previousSequence = (int) substr($previousNumber, -4);
                // Assert: Current sequence is greater than previous
                expect($sequenceNumber)
                    ->toBeGreaterThan($previousSequence,
                        "Sequence should increment: {$receiptNumber} should be after {$previousNumber}");
            }

            $previousNumber = $receiptNumber;
        }
    });

    it('validates receipt number format correctly', function () {
        $receiptService = app(ReceiptService::class);

        // Valid formats
        $validNumbers = [
            'RCP-20251128-0001',
            'RCP-20251128-0042',
            'RCP-20251128-9999',
            'RCP-20250101-0001',
        ];

        foreach ($validNumbers as $number) {
            expect($receiptService->isValidReceiptNumber($number))
                ->toBeTrue("'{$number}' should be a valid receipt number");
        }

        // Invalid formats
        $invalidNumbers = [
            'RCP-2025112-0001',   // Date too short
            'RCP-202511280-0001', // Date too long
            'RCP-20251128-001',   // Sequence too short
            'RCP-20251128-00001', // Sequence too long
            'RCP20251128-0001',   // Missing first dash
            'RCP-202511280001',   // Missing second dash
            'RCPT-20251128-0001', // Wrong prefix
            'rcp-20251128-0001',  // Lowercase
            '',                    // Empty
            'invalid',             // Random string
        ];

        foreach ($invalidNumbers as $number) {
            expect($receiptService->isValidReceiptNumber($number))
                ->toBeFalse("'{$number}' should be an invalid receipt number");
        }
    });

    it('ensures uniqueness check works correctly', function () {
        $receiptService = app(ReceiptService::class);
        $checkin = PatientCheckin::factory()->create();

        // Create a charge with a receipt number
        $existingReceiptNumber = 'RCP-20251128-0001';
        Charge::factory()->create([
            'patient_checkin_id' => $checkin->id,
            'receipt_number' => $existingReceiptNumber,
            'paid_at' => now(),
        ]);

        // Assert: Existing receipt number is not unique
        expect($receiptService->isUniqueReceiptNumber($existingReceiptNumber))
            ->toBeFalse('Existing receipt number should not be unique');

        // Assert: New receipt number is unique
        $newReceiptNumber = 'RCP-20251128-9999';
        expect($receiptService->isUniqueReceiptNumber($newReceiptNumber))
            ->toBeTrue('New receipt number should be unique');
    });
});

describe('Property 6: Receipt data completeness', function () {
    /**
     * **Feature: billing-enhancements, Property 6: Receipt data completeness**
     * **Validates: Requirements 3.3**
     *
     * For any generated receipt, the data structure SHALL contain all required fields:
     * hospital name, date, time, receipt number, patient name, amount, payment method, and cashier name.
     */
    it('returns complete receipt data for a single charge', function () {
        // Run 100 iterations with different charge configurations
        for ($iteration = 0; $iteration < 100; $iteration++) {
            // Arrange: Create patient, checkin, and charge
            $patient = Patient::factory()->create();
            $checkin = PatientCheckin::factory()->create([
                'patient_id' => $patient->id,
            ]);
            $cashier = User::factory()->create();

            $charge = Charge::factory()->create([
                'patient_checkin_id' => $checkin->id,
                'receipt_number' => 'RCP-'.now()->format('Ymd').'-'.str_pad($iteration + 1, 4, '0', STR_PAD_LEFT),
                'paid_at' => now(),
                'processed_by' => $cashier->id,
                'amount' => fake()->randomFloat(2, 10, 1000),
            ]);

            // Act: Get receipt data
            $receiptService = app(ReceiptService::class);
            $receiptData = $receiptService->getReceiptData($charge);

            // Assert: All required fields are present
            expect($receiptData)->toHaveKey('receipt_number');
            expect($receiptData)->toHaveKey('hospital');
            expect($receiptData)->toHaveKey('date');
            expect($receiptData)->toHaveKey('time');
            expect($receiptData)->toHaveKey('datetime');
            expect($receiptData)->toHaveKey('patient');
            expect($receiptData)->toHaveKey('charge');
            expect($receiptData)->toHaveKey('cashier');

            // Assert: Hospital info is present
            expect($receiptData['hospital'])->toHaveKey('name');
            expect($receiptData['hospital']['name'])->not->toBeEmpty();

            // Assert: Patient info is complete
            expect($receiptData['patient'])->toHaveKey('name');
            expect($receiptData['patient'])->toHaveKey('patient_number');
            expect($receiptData['patient']['name'])->not->toBeEmpty();

            // Assert: Charge info is complete
            expect($receiptData['charge'])->toHaveKey('id');
            expect($receiptData['charge'])->toHaveKey('description');
            expect($receiptData['charge'])->toHaveKey('amount');
            expect($receiptData['charge'])->toHaveKey('paid_amount');

            // Assert: Cashier info is present
            expect($receiptData['cashier'])->toHaveKey('name');
            expect($receiptData['cashier']['name'])->not->toBeEmpty();

            // Assert: Receipt number matches
            expect($receiptData['receipt_number'])->toBe($charge->receipt_number);

            // Clean up
            $charge->delete();
            $checkin->delete();
            $patient->delete();
            $cashier->delete();
        }
    });

    it('returns complete receipt data for grouped charges', function () {
        // Run 50 iterations with different configurations
        for ($iteration = 0; $iteration < 50; $iteration++) {
            // Arrange: Create patient, checkin, and multiple charges
            $patient = Patient::factory()->create();
            $checkin = PatientCheckin::factory()->create([
                'patient_id' => $patient->id,
            ]);
            $cashier = User::factory()->create();

            $numCharges = fake()->numberBetween(2, 5);
            $receiptNumber = 'RCP-'.now()->format('Ymd').'-'.str_pad($iteration + 1, 4, '0', STR_PAD_LEFT);

            $charges = Charge::factory()
                ->count($numCharges)
                ->create([
                    'patient_checkin_id' => $checkin->id,
                    'receipt_number' => $receiptNumber,
                    'paid_at' => now(),
                    'processed_by' => $cashier->id,
                    'amount' => fn () => fake()->randomFloat(2, 10, 500),
                ]);

            $chargeIds = $charges->pluck('id')->toArray();

            // Act: Get grouped receipt data
            $receiptService = app(ReceiptService::class);
            $receiptData = $receiptService->getGroupedReceiptData($chargeIds, $receiptNumber);

            // Assert: All required fields are present
            expect($receiptData)->toHaveKey('receipt_number');
            expect($receiptData)->toHaveKey('hospital');
            expect($receiptData)->toHaveKey('date');
            expect($receiptData)->toHaveKey('time');
            expect($receiptData)->toHaveKey('datetime');
            expect($receiptData)->toHaveKey('patient');
            expect($receiptData)->toHaveKey('charges');
            expect($receiptData)->toHaveKey('totals');
            expect($receiptData)->toHaveKey('cashier');

            // Assert: Charges array has correct count
            expect(count($receiptData['charges']))->toBe($numCharges);

            // Assert: Totals are calculated correctly
            $expectedTotal = $charges->sum('amount');
            expect(round((float) $receiptData['totals']['amount'], 2))
                ->toBe(round((float) $expectedTotal, 2));

            // Assert: Each charge has required fields
            foreach ($receiptData['charges'] as $chargeData) {
                expect($chargeData)->toHaveKey('id');
                expect($chargeData)->toHaveKey('description');
                expect($chargeData)->toHaveKey('amount');
            }

            // Clean up
            Charge::whereIn('id', $chargeIds)->delete();
            $checkin->delete();
            $patient->delete();
            $cashier->delete();
        }
    });

    it('includes insurance information when applicable', function () {
        // Run 50 iterations
        for ($iteration = 0; $iteration < 50; $iteration++) {
            // Arrange: Create insurance charge
            $patient = Patient::factory()->create();
            $checkin = PatientCheckin::factory()->create([
                'patient_id' => $patient->id,
            ]);
            $cashier = User::factory()->create();

            $amount = fake()->randomFloat(2, 100, 1000);
            $coveragePercent = fake()->randomElement([50, 70, 80, 90]);
            $insuranceCovered = round($amount * ($coveragePercent / 100), 2);
            $patientCopay = round($amount - $insuranceCovered, 2);

            $charge = Charge::factory()->create([
                'patient_checkin_id' => $checkin->id,
                'receipt_number' => 'RCP-'.now()->format('Ymd').'-'.str_pad($iteration + 1, 4, '0', STR_PAD_LEFT),
                'paid_at' => now(),
                'processed_by' => $cashier->id,
                'amount' => $amount,
                'is_insurance_claim' => true,
                'insurance_covered_amount' => $insuranceCovered,
                'patient_copay_amount' => $patientCopay,
            ]);

            // Act: Get receipt data
            $receiptService = app(ReceiptService::class);
            $receiptData = $receiptService->getReceiptData($charge);

            // Assert: Insurance fields are present
            expect($receiptData['charge'])->toHaveKey('is_insurance_claim');
            expect($receiptData['charge'])->toHaveKey('insurance_covered_amount');
            expect($receiptData['charge'])->toHaveKey('patient_copay_amount');

            // Assert: Insurance values are correct
            expect($receiptData['charge']['is_insurance_claim'])->toBeTrue();
            expect(round((float) $receiptData['charge']['insurance_covered_amount'], 2))
                ->toBe(round((float) $insuranceCovered, 2));
            expect(round((float) $receiptData['charge']['patient_copay_amount'], 2))
                ->toBe(round((float) $patientCopay, 2));

            // Clean up
            $charge->delete();
            $checkin->delete();
            $patient->delete();
            $cashier->delete();
        }
    });

    it('handles missing optional fields gracefully', function () {
        // Arrange: Create minimal charge without optional relationships
        $checkin = PatientCheckin::factory()->create();

        $charge = Charge::factory()->create([
            'patient_checkin_id' => $checkin->id,
            'receipt_number' => 'RCP-'.now()->format('Ymd').'-0001',
            'paid_at' => now(),
            'processed_by' => null, // No cashier assigned
        ]);

        // Act: Get receipt data
        $receiptService = app(ReceiptService::class);
        $receiptData = $receiptService->getReceiptData($charge);

        // Assert: Data is returned without errors
        expect($receiptData)->toBeArray();
        expect($receiptData)->toHaveKey('receipt_number');
        expect($receiptData)->toHaveKey('cashier');

        // Assert: Cashier defaults to 'System' when not set
        expect($receiptData['cashier']['name'])->toBe('System');
    });
});
