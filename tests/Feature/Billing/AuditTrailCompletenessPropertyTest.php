<?php

/**
 * Property-Based Tests for Audit Trail Completeness
 *
 * **Feature: billing-enhancements, Property 13: Audit trail completeness**
 * **Validates: Requirements 3.5, 7.4, 8.5**
 */

use App\Models\Charge;
use App\Models\Patient;
use App\Models\PatientCheckin;
use App\Models\PaymentAuditLog;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed payment methods
    PaymentMethod::firstOrCreate(
        ['code' => 'cash'],
        ['name' => 'Cash', 'is_active' => true, 'requires_reference' => false]
    );
});

describe('Property 13: Audit trail completeness', function () {
    /**
     * **Feature: billing-enhancements, Property 13: Audit trail completeness**
     * **Validates: Requirements 3.5, 7.4, 8.5**
     *
     * For any payment action (payment, void, refund, receipt print), an audit log entry
     * SHALL be created with the action type, user, and timestamp.
     */
    it('creates audit log entry for payment actions with required fields', function () {
        for ($iteration = 0; $iteration < 100; $iteration++) {
            // Arrange
            $user = User::factory()->create();
            $checkin = PatientCheckin::factory()->create();
            $charge = Charge::factory()->create([
                'patient_checkin_id' => $checkin->id,
                'status' => 'pending',
            ]);

            $action = fake()->randomElement([
                PaymentAuditLog::ACTION_PAYMENT,
                PaymentAuditLog::ACTION_VOID,
                PaymentAuditLog::ACTION_REFUND,
                PaymentAuditLog::ACTION_RECEIPT_PRINTED,
            ]);

            // Act: Create audit log entry using the static methods
            $auditLog = match ($action) {
                PaymentAuditLog::ACTION_PAYMENT => PaymentAuditLog::logPayment(
                    $charge,
                    $user,
                    ['status' => 'paid', 'paid_amount' => fake()->randomFloat(2, 10, 500)],
                    fake()->ipv4()
                ),
                PaymentAuditLog::ACTION_VOID => PaymentAuditLog::create([
                    'charge_id' => $charge->id,
                    'patient_id' => $checkin->patient_id,
                    'user_id' => $user->id,
                    'action' => PaymentAuditLog::ACTION_VOID,
                    'old_values' => ['status' => 'paid'],
                    'new_values' => ['status' => 'voided'],
                    'reason' => fake()->sentence(),
                    'ip_address' => fake()->ipv4(),
                ]),
                PaymentAuditLog::ACTION_REFUND => PaymentAuditLog::create([
                    'charge_id' => $charge->id,
                    'patient_id' => $checkin->patient_id,
                    'user_id' => $user->id,
                    'action' => PaymentAuditLog::ACTION_REFUND,
                    'old_values' => ['status' => 'paid'],
                    'new_values' => ['status' => 'refunded'],
                    'reason' => fake()->sentence(),
                    'ip_address' => fake()->ipv4(),
                ]),
                PaymentAuditLog::ACTION_RECEIPT_PRINTED => PaymentAuditLog::logReceiptPrinted(
                    $charge,
                    $user,
                    fake()->ipv4()
                ),
            };

            // Assert: Audit log entry has required fields
            expect($auditLog)->not->toBeNull();
            expect($auditLog->action)->toBe($action,
                "Audit log should have correct action type (iteration {$iteration})");
            expect($auditLog->user_id)->toBe($user->id,
                'Audit log should have user ID');
            expect($auditLog->created_at)->not->toBeNull(
                'Audit log should have timestamp');

            // Clean up
            $auditLog->delete();
            $charge->delete();
            $checkin->delete();
            $user->delete();
        }
    });

    /**
     * Property: Payment audit logs are linked to the correct charge
     */
    it('links audit log to correct charge', function () {
        for ($iteration = 0; $iteration < 50; $iteration++) {
            // Arrange
            $user = User::factory()->create();
            $checkin = PatientCheckin::factory()->create();
            $charge = Charge::factory()->create([
                'patient_checkin_id' => $checkin->id,
            ]);

            // Act: Create payment audit log
            $auditLog = PaymentAuditLog::logPayment(
                $charge,
                $user,
                ['status' => 'paid', 'paid_amount' => 100.00],
                fake()->ipv4()
            );

            // Assert: Audit log is linked to correct charge
            expect($auditLog->charge_id)->toBe($charge->id,
                'Audit log should be linked to the correct charge');

            // Assert: Can retrieve audit log via charge relationship
            $retrievedLogs = PaymentAuditLog::where('charge_id', $charge->id)->get();
            expect($retrievedLogs)->toHaveCount(1);
            expect($retrievedLogs->first()->id)->toBe($auditLog->id);

            // Clean up
            $auditLog->delete();
            $charge->delete();
            $checkin->delete();
            $user->delete();
        }
    });

    /**
     * Property: Audit logs preserve old and new values for state changes
     */
    it('preserves old and new values for state changes', function () {
        for ($iteration = 0; $iteration < 50; $iteration++) {
            // Arrange
            $user = User::factory()->create();
            $checkin = PatientCheckin::factory()->create();
            $originalStatus = 'pending';
            $originalPaidAmount = 0;
            $newPaidAmount = fake()->randomFloat(2, 10, 500);

            $charge = Charge::factory()->create([
                'patient_checkin_id' => $checkin->id,
                'status' => $originalStatus,
                'paid_amount' => $originalPaidAmount,
            ]);

            // Act: Create payment audit log with state change
            $auditLog = PaymentAuditLog::logPayment(
                $charge,
                $user,
                ['status' => 'paid', 'paid_amount' => $newPaidAmount],
                fake()->ipv4()
            );

            // Assert: Old values are preserved
            expect($auditLog->old_values)->toBeArray();
            expect($auditLog->old_values['status'])->toBe($originalStatus,
                'Old status should be preserved');

            // Assert: New values are preserved
            expect($auditLog->new_values)->toBeArray();
            expect($auditLog->new_values['status'])->toBe('paid',
                'New status should be preserved');
            expect((float) $auditLog->new_values['paid_amount'])->toBe($newPaidAmount,
                'New paid amount should be preserved');

            // Clean up
            $auditLog->delete();
            $charge->delete();
            $checkin->delete();
            $user->delete();
        }
    });

    /**
     * Property: Receipt print audit logs include receipt number and timestamp
     */
    it('receipt print logs include receipt number and timestamp', function () {
        for ($iteration = 0; $iteration < 50; $iteration++) {
            // Arrange
            $user = User::factory()->create();
            $checkin = PatientCheckin::factory()->create();
            $receiptNumber = 'RCP-'.now()->format('Ymd').'-'.str_pad(fake()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT);

            $charge = Charge::factory()->create([
                'patient_checkin_id' => $checkin->id,
                'receipt_number' => $receiptNumber,
            ]);

            // Act: Create receipt printed audit log
            $auditLog = PaymentAuditLog::logReceiptPrinted(
                $charge,
                $user,
                fake()->ipv4()
            );

            // Assert: Receipt number is included
            expect($auditLog->new_values)->toBeArray();
            expect($auditLog->new_values['receipt_number'])->toBe($receiptNumber,
                'Receipt number should be included in audit log');

            // Assert: Printed timestamp is included
            expect(array_key_exists('printed_at', $auditLog->new_values))->toBeTrue(
                'Printed timestamp should be included');

            // Clean up
            $auditLog->delete();
            $charge->delete();
            $checkin->delete();
            $user->delete();
        }
    });

    /**
     * Property: Override audit logs include reason
     */
    it('override logs include reason', function () {
        for ($iteration = 0; $iteration < 50; $iteration++) {
            // Arrange
            $user = User::factory()->create();
            $checkin = PatientCheckin::factory()->create();
            $reason = fake()->sentence();

            $charge = Charge::factory()->create([
                'patient_checkin_id' => $checkin->id,
                'status' => 'pending',
            ]);

            // Act: Create override audit log
            $auditLog = PaymentAuditLog::logOverride(
                $charge,
                $user,
                $reason,
                fake()->ipv4()
            );

            // Assert: Reason is included
            expect($auditLog->reason)->toBe($reason,
                'Override reason should be included in audit log');

            // Assert: Action is correct
            expect($auditLog->action)->toBe(PaymentAuditLog::ACTION_OVERRIDE);

            // Assert: Status change is recorded
            expect($auditLog->new_values['status'])->toBe('owing',
                'New status should be owing');

            // Clean up
            $auditLog->delete();
            $charge->delete();
            $checkin->delete();
            $user->delete();
        }
    });

    /**
     * Property: Credit tag changes create audit logs with patient ID
     */
    it('credit tag changes create audit logs with patient ID', function () {
        for ($iteration = 0; $iteration < 50; $iteration++) {
            // Arrange
            $user = User::factory()->create();
            $patient = Patient::factory()->create();
            $reason = fake()->sentence();
            $isAdding = fake()->boolean();

            // Act: Create credit tag change audit log
            $auditLog = PaymentAuditLog::logCreditTagChange(
                $patient,
                $user,
                $isAdding,
                $reason,
                fake()->ipv4()
            );

            // Assert: Patient ID is set
            expect($auditLog->patient_id)->toBe($patient->id,
                'Patient ID should be set for credit tag changes');

            // Assert: Charge ID is null (credit tags are patient-level)
            expect($auditLog->charge_id)->toBeNull(
                'Charge ID should be null for credit tag changes');

            // Assert: Action is correct
            $expectedAction = $isAdding
                ? PaymentAuditLog::ACTION_CREDIT_TAG_ADDED
                : PaymentAuditLog::ACTION_CREDIT_TAG_REMOVED;
            expect($auditLog->action)->toBe($expectedAction,
                'Action should match whether tag was added or removed');

            // Assert: Reason is included
            expect($auditLog->reason)->toBe($reason,
                'Reason should be included');

            // Clean up
            $auditLog->delete();
            $patient->delete();
            $user->delete();
        }
    });

    /**
     * Property: All audit logs have IP address when provided
     */
    it('stores IP address when provided', function () {
        for ($iteration = 0; $iteration < 50; $iteration++) {
            // Arrange
            $user = User::factory()->create();
            $checkin = PatientCheckin::factory()->create();
            $ipAddress = fake()->ipv4();

            $charge = Charge::factory()->create([
                'patient_checkin_id' => $checkin->id,
            ]);

            // Act: Create audit log with IP address
            $auditLog = PaymentAuditLog::logPayment(
                $charge,
                $user,
                ['status' => 'paid', 'paid_amount' => 100.00],
                $ipAddress
            );

            // Assert: IP address is stored
            expect($auditLog->ip_address)->toBe($ipAddress,
                'IP address should be stored in audit log');

            // Clean up
            $auditLog->delete();
            $charge->delete();
            $checkin->delete();
            $user->delete();
        }
    });
});
