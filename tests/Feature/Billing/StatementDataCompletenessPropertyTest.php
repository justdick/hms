<?php

/**
 * Property-Based Tests for Statement Data Completeness
 *
 * **Feature: billing-enhancements, Property: Statement contains all required sections**
 * **Validates: Requirements 8.2**
 */

use App\Models\Charge;
use App\Models\Patient;
use App\Models\PatientCheckin;
use App\Models\User;
use App\Services\PdfService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Property: Statement contains all required sections', function () {
    /**
     * **Feature: billing-enhancements, Property: Statement contains all required sections**
     * **Validates: Requirements 8.2**
     *
     * For any generated statement, the data structure SHALL contain all required sections:
     * hospital info, patient details, statement period, charges, payments, and balance summary.
     */
    it('returns statement data with all required sections', function () {
        $pdfService = app(PdfService::class);

        // Run 100 iterations with different patient configurations
        for ($iteration = 0; $iteration < 100; $iteration++) {
            // Arrange: Create patient with random data
            $patient = Patient::factory()->create();

            // Random date range within the last year
            $endDate = Carbon::now()->subDays(fake()->numberBetween(0, 30));
            $startDate = $endDate->copy()->subDays(fake()->numberBetween(30, 90));

            // Act: Get statement data
            $statementData = $pdfService->getStatementData($patient, $startDate, $endDate);

            // Assert: All required sections are present
            expect($statementData)->toHaveKey('hospital');
            expect($statementData)->toHaveKey('patient');
            expect($statementData)->toHaveKey('statement_period');
            expect($statementData)->toHaveKey('generated_at');
            expect($statementData)->toHaveKey('charges');
            expect($statementData)->toHaveKey('payments');
            expect($statementData)->toHaveKey('summary');

            // Assert: Hospital section has required fields
            expect($statementData['hospital'])->toHaveKey('name');
            expect($statementData['hospital']['name'])->not->toBeEmpty();

            // Assert: Patient section has required fields
            expect($statementData['patient'])->toHaveKey('patient_number');
            expect($statementData['patient'])->toHaveKey('name');
            expect($statementData['patient']['patient_number'])->not->toBeEmpty();
            expect($statementData['patient']['name'])->not->toBeEmpty();

            // Assert: Statement period has required fields
            expect($statementData['statement_period'])->toHaveKey('start_date');
            expect($statementData['statement_period'])->toHaveKey('end_date');

            // Assert: Summary has required fields
            expect($statementData['summary'])->toHaveKey('opening_balance');
            expect($statementData['summary'])->toHaveKey('total_charges');
            expect($statementData['summary'])->toHaveKey('total_paid');
            expect($statementData['summary'])->toHaveKey('closing_balance');

            // Clean up
            $patient->delete();
        }
    });

    it('includes charges within the date range', function () {
        $pdfService = app(PdfService::class);

        // Run 50 iterations
        for ($iteration = 0; $iteration < 50; $iteration++) {
            // Arrange: Create patient and charges
            $patient = Patient::factory()->create();
            $checkin = PatientCheckin::factory()->create([
                'patient_id' => $patient->id,
            ]);

            $startDate = Carbon::now()->subDays(30);
            $endDate = Carbon::now();

            // Create charges within the date range
            $numCharges = fake()->numberBetween(1, 5);
            $charges = [];
            for ($i = 0; $i < $numCharges; $i++) {
                $charges[] = Charge::factory()->create([
                    'patient_checkin_id' => $checkin->id,
                    'charged_at' => fake()->dateTimeBetween($startDate, $endDate),
                    'status' => 'pending',
                ]);
            }

            // Act: Get statement data
            $statementData = $pdfService->getStatementData($patient, $startDate, $endDate);

            // Assert: Charges are included
            expect($statementData['charges'])->toBeIterable();
            expect(count($statementData['charges']))->toBe($numCharges);

            // Assert: Each charge has required fields
            foreach ($statementData['charges'] as $chargeData) {
                expect($chargeData)->toHaveKey('date');
                expect($chargeData)->toHaveKey('description');
                expect($chargeData)->toHaveKey('service_type');
                expect($chargeData)->toHaveKey('amount');
                expect($chargeData)->toHaveKey('status');
            }

            // Clean up
            foreach ($charges as $charge) {
                $charge->delete();
            }
            $checkin->delete();
            $patient->delete();
        }
    });

    it('includes payments within the date range', function () {
        $pdfService = app(PdfService::class);

        // Run 50 iterations
        for ($iteration = 0; $iteration < 50; $iteration++) {
            // Arrange: Create patient and paid charges
            $patient = Patient::factory()->create();
            $checkin = PatientCheckin::factory()->create([
                'patient_id' => $patient->id,
            ]);

            $startDate = Carbon::now()->subDays(30);
            $endDate = Carbon::now();

            // Create paid charges within the date range
            $numPayments = fake()->numberBetween(1, 5);
            $charges = [];
            for ($i = 0; $i < $numPayments; $i++) {
                $paidAt = fake()->dateTimeBetween($startDate, $endDate);
                $charges[] = Charge::factory()->create([
                    'patient_checkin_id' => $checkin->id,
                    'charged_at' => $paidAt,
                    'paid_at' => $paidAt,
                    'status' => 'paid',
                    'paid_amount' => fake()->randomFloat(2, 10, 500),
                    'receipt_number' => 'RCP-'.now()->format('Ymd').'-'.str_pad($iteration * 10 + $i + 1, 4, '0', STR_PAD_LEFT),
                ]);
            }

            // Act: Get statement data
            $statementData = $pdfService->getStatementData($patient, $startDate, $endDate);

            // Assert: Payments are included
            expect($statementData['payments'])->toBeIterable();
            expect(count($statementData['payments']))->toBe($numPayments);

            // Assert: Each payment has required fields
            foreach ($statementData['payments'] as $paymentData) {
                expect($paymentData)->toHaveKey('date');
                expect($paymentData)->toHaveKey('receipt_number');
                expect($paymentData)->toHaveKey('description');
                expect($paymentData)->toHaveKey('paid_amount');
                expect($paymentData)->toHaveKey('payment_method');
            }

            // Clean up
            foreach ($charges as $charge) {
                $charge->delete();
            }
            $checkin->delete();
            $patient->delete();
        }
    });

    it('calculates summary totals correctly', function () {
        $pdfService = app(PdfService::class);

        // Run 50 iterations
        for ($iteration = 0; $iteration < 50; $iteration++) {
            // Arrange: Create patient and charges with known amounts
            $patient = Patient::factory()->create();
            $checkin = PatientCheckin::factory()->create([
                'patient_id' => $patient->id,
            ]);

            $startDate = Carbon::now()->subDays(30);
            $endDate = Carbon::now();

            // Create charges with known amounts
            $chargeAmounts = [];
            $paidAmounts = [];
            $insuranceAmounts = [];

            $numCharges = fake()->numberBetween(2, 5);
            for ($i = 0; $i < $numCharges; $i++) {
                $amount = fake()->randomFloat(2, 50, 500);
                $isPaid = fake()->boolean(70);
                $hasInsurance = fake()->boolean(30);

                $paidAmount = $isPaid ? $amount : 0;
                $insuranceAmount = $hasInsurance ? round($amount * 0.5, 2) : 0;

                $chargeAmounts[] = $amount;
                if ($isPaid) {
                    $paidAmounts[] = $paidAmount;
                }
                $insuranceAmounts[] = $insuranceAmount;

                $chargedAt = fake()->dateTimeBetween($startDate, $endDate);
                Charge::factory()->create([
                    'patient_checkin_id' => $checkin->id,
                    'amount' => $amount,
                    'charged_at' => $chargedAt,
                    'paid_at' => $isPaid ? $chargedAt : null,
                    'status' => $isPaid ? 'paid' : 'pending',
                    'paid_amount' => $paidAmount,
                    'insurance_covered_amount' => $insuranceAmount,
                ]);
            }

            // Act: Get statement data
            $statementData = $pdfService->getStatementData($patient, $startDate, $endDate);

            // Assert: Totals are calculated correctly
            $expectedTotalCharges = array_sum($chargeAmounts);
            $expectedTotalPaid = array_sum($paidAmounts);
            $expectedTotalInsurance = array_sum($insuranceAmounts);

            expect(round((float) $statementData['summary']['total_charges'], 2))
                ->toBe(round($expectedTotalCharges, 2));
            expect(round((float) $statementData['summary']['total_paid'], 2))
                ->toBe(round($expectedTotalPaid, 2));
            expect(round((float) $statementData['summary']['total_insurance_covered'], 2))
                ->toBe(round($expectedTotalInsurance, 2));

            // Clean up
            Charge::where('patient_checkin_id', $checkin->id)->delete();
            $checkin->delete();
            $patient->delete();
        }
    });

    it('validates statement data completeness using service method', function () {
        $pdfService = app(PdfService::class);

        // Run 100 iterations
        for ($iteration = 0; $iteration < 100; $iteration++) {
            // Arrange: Create patient
            $patient = Patient::factory()->create();
            $startDate = Carbon::now()->subDays(30);
            $endDate = Carbon::now();

            // Act: Get statement data
            $statementData = $pdfService->getStatementData($patient, $startDate, $endDate);

            // Assert: Validation passes
            expect($pdfService->validateStatementData($statementData))
                ->toBeTrue("Statement data should be valid for iteration {$iteration}");

            // Clean up
            $patient->delete();
        }
    });

    it('rejects incomplete statement data', function () {
        $pdfService = app(PdfService::class);

        // Test various incomplete data structures
        $incompleteDataSets = [
            [], // Empty
            ['hospital' => ['name' => 'Test']], // Missing most sections
            [
                'hospital' => ['name' => 'Test'],
                'patient' => ['name' => 'John'],
                // Missing patient_number
            ],
            [
                'hospital' => ['name' => 'Test'],
                'patient' => ['patient_number' => 'P001', 'name' => 'John'],
                'statement_period' => ['start_date' => '2025-01-01'],
                // Missing end_date
            ],
            [
                'hospital' => ['name' => 'Test'],
                'patient' => ['patient_number' => 'P001', 'name' => 'John'],
                'statement_period' => ['start_date' => '2025-01-01', 'end_date' => '2025-01-31'],
                'generated_at' => '2025-01-31',
                'charges' => [],
                'payments' => [],
                'summary' => [
                    'opening_balance' => 0,
                    'total_charges' => 0,
                    // Missing total_paid and closing_balance
                ],
            ],
        ];

        foreach ($incompleteDataSets as $index => $incompleteData) {
            expect($pdfService->validateStatementData($incompleteData))
                ->toBeFalse("Incomplete data set {$index} should fail validation");
        }
    });

    it('excludes voided charges from statement', function () {
        $pdfService = app(PdfService::class);

        // Arrange: Create patient with voided and non-voided charges
        $patient = Patient::factory()->create();
        $checkin = PatientCheckin::factory()->create([
            'patient_id' => $patient->id,
        ]);

        $startDate = Carbon::now()->subDays(30);
        $endDate = Carbon::now();

        // Create a valid charge
        $validCharge = Charge::factory()->create([
            'patient_checkin_id' => $checkin->id,
            'charged_at' => Carbon::now()->subDays(15),
            'status' => 'pending',
        ]);

        // Create a voided charge
        $voidedCharge = Charge::factory()->create([
            'patient_checkin_id' => $checkin->id,
            'charged_at' => Carbon::now()->subDays(10),
            'status' => 'voided',
        ]);

        // Act: Get statement data
        $statementData = $pdfService->getStatementData($patient, $startDate, $endDate);

        // Assert: Only non-voided charge is included
        expect(count($statementData['charges']))->toBe(1);
        expect($statementData['charges'][0]['id'])->toBe($validCharge->id);

        // Clean up
        $validCharge->delete();
        $voidedCharge->delete();
        $checkin->delete();
        $patient->delete();
    });
});

describe('Statement audit logging', function () {
    it('logs statement generation in audit trail', function () {
        $pdfService = app(PdfService::class);

        // Arrange
        $patient = Patient::factory()->create();
        $user = User::factory()->create();
        $startDate = Carbon::now()->subDays(30);
        $endDate = Carbon::now();

        // Act: Log statement generation
        $pdfService->logStatementGeneration($patient, $startDate, $endDate, $user->id, '127.0.0.1');

        // Assert: Audit log was created
        $this->assertDatabaseHas('payment_audit_logs', [
            'patient_id' => $patient->id,
            'user_id' => $user->id,
            'action' => 'statement_generated',
        ]);

        // Clean up - delete audit logs first due to foreign key constraint
        \App\Models\PaymentAuditLog::where('patient_id', $patient->id)->delete();
        $patient->delete();
        $user->delete();
    });
});
