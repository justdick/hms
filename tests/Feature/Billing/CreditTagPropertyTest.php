<?php

/**
 * Property-Based Tests for Patient Credit Tag System
 *
 * **Feature: billing-enhancements, Property 16: Credit-tagged patient bypass**
 * **Feature: billing-enhancements, Property 17: Credit tag audit trail**
 * **Validates: Requirements 14.1, 14.2, 14.5**
 */

use App\Models\Charge;
use App\Models\Patient;
use App\Models\PatientCheckin;
use App\Models\PaymentAuditLog;
use App\Models\User;
use App\Services\BillingService;
use App\Services\CreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create required permissions
    Permission::firstOrCreate(['name' => 'billing.manage-credit']);
    Permission::firstOrCreate(['name' => 'billing.collect']);
});

describe('Property 16: Credit-tagged patient bypass', function () {
    /**
     * **Feature: billing-enhancements, Property 16: Credit-tagged patient bypass**
     * **Validates: Requirements 14.1, 14.2**
     *
     * For any patient with an active credit tag, service blocking checks SHALL return
     * true (allowed) regardless of pending payment amounts.
     */
    it('allows credit-tagged patients to proceed with services regardless of pending charges', function () {
        $billingService = app(BillingService::class);
        $creditService = app(CreditService::class);

        // Run 100 iterations with different random configurations
        for ($iteration = 0; $iteration < 100; $iteration++) {
            // Arrange: Create a credit-eligible patient with pending charges
            $patient = Patient::factory()->create([
                'is_credit_eligible' => true,
                'credit_reason' => fake()->sentence(),
            ]);

            $checkin = PatientCheckin::factory()->create([
                'patient_id' => $patient->id,
            ]);

            // Create random pending charges with varying amounts
            $numCharges = fake()->numberBetween(1, 5);
            $totalPending = 0;

            for ($i = 0; $i < $numCharges; $i++) {
                $amount = fake()->randomFloat(2, 50, 500);
                Charge::factory()->pending()->create([
                    'patient_checkin_id' => $checkin->id,
                    'amount' => $amount,
                    'service_type' => fake()->randomElement(['consultation', 'laboratory', 'pharmacy', 'ward']),
                ]);
                $totalPending += $amount;
            }

            // Test various service types
            $serviceTypes = ['consultation', 'laboratory', 'pharmacy', 'ward'];

            foreach ($serviceTypes as $serviceType) {
                // Act: Check if patient can proceed with service
                $canProceed = $billingService->canProceedWithService($checkin, $serviceType);

                // Assert: Credit-tagged patient should always be able to proceed
                expect($canProceed)->toBeTrue(
                    "Credit-tagged patient should be able to proceed with {$serviceType} service ".
                    "even with {$numCharges} pending charges totaling {$totalPending}"
                );
            }

            // Clean up
            Charge::where('patient_checkin_id', $checkin->id)->delete();
            $checkin->delete();
            $patient->delete();
        }
    });

    /**
     * Property: Non-credit-tagged patients are subject to normal billing rules
     */
    it('does not bypass service blocking for non-credit-tagged patients', function () {
        $billingService = app(BillingService::class);

        for ($iteration = 0; $iteration < 50; $iteration++) {
            // Arrange: Create a non-credit-eligible patient
            $patient = Patient::factory()->create([
                'is_credit_eligible' => false,
            ]);

            $checkin = PatientCheckin::factory()->create([
                'patient_id' => $patient->id,
            ]);

            // Assert: isPatientCreditEligible should return false
            $isCreditEligible = $billingService->isPatientCreditEligible($checkin);
            expect($isCreditEligible)->toBeFalse(
                'Non-credit-tagged patient should not be credit eligible'
            );

            // Clean up
            $checkin->delete();
            $patient->delete();
        }
    });
});

describe('Property 17: Credit tag audit trail', function () {
    /**
     * **Feature: billing-enhancements, Property 17: Credit tag audit trail**
     * **Validates: Requirements 14.5**
     *
     * For any credit tag addition or removal, an audit record SHALL be created
     * with the user, action, reason, and timestamp.
     */
    it('creates audit record when credit tag is added', function () {
        $creditService = app(CreditService::class);

        for ($iteration = 0; $iteration < 100; $iteration++) {
            // Arrange
            $patient = Patient::factory()->create([
                'is_credit_eligible' => false,
            ]);

            $authorizedBy = User::factory()->create();
            $reason = fake()->sentence(fake()->numberBetween(5, 15));
            $ipAddress = fake()->ipv4();

            // Act: Add credit tag
            $result = $creditService->addCreditTag($patient, $authorizedBy, $reason, $ipAddress);

            // Assert 1: Operation should succeed
            expect($result)->toBeTrue('Adding credit tag should succeed');

            // Assert 2: Patient should now be credit eligible
            $patient->refresh();
            expect($patient->is_credit_eligible)->toBeTrue('Patient should be credit eligible after adding tag');
            expect($patient->credit_reason)->toBe($reason);
            expect($patient->credit_authorized_by)->toBe($authorizedBy->id);
            expect($patient->credit_authorized_at)->not->toBeNull();

            // Assert 3: Audit log should be created
            $auditLog = PaymentAuditLog::where('patient_id', $patient->id)
                ->where('action', PaymentAuditLog::ACTION_CREDIT_TAG_ADDED)
                ->first();

            expect($auditLog)->not->toBeNull('Audit log should be created for credit tag addition');
            expect($auditLog->user_id)->toBe($authorizedBy->id, 'Audit log should record authorizing user');
            expect($auditLog->reason)->toBe($reason, 'Audit log should record the reason');
            expect($auditLog->ip_address)->toBe($ipAddress, 'Audit log should record IP address');
            expect($auditLog->new_values)->toBeArray();
            expect($auditLog->new_values['is_credit_eligible'])->toBeTrue();

            // Clean up
            PaymentAuditLog::where('patient_id', $patient->id)->delete();
            $patient->delete();
            $authorizedBy->delete();
        }
    });

    it('creates audit record when credit tag is removed', function () {
        $creditService = app(CreditService::class);

        for ($iteration = 0; $iteration < 100; $iteration++) {
            // Arrange: Create a credit-eligible patient
            $authorizedBy = User::factory()->create();
            $patient = Patient::factory()->create([
                'is_credit_eligible' => true,
                'credit_reason' => 'Initial reason',
                'credit_authorized_by' => $authorizedBy->id,
                'credit_authorized_at' => now(),
            ]);

            $removedBy = User::factory()->create();
            $reason = fake()->sentence(fake()->numberBetween(5, 15));
            $ipAddress = fake()->ipv4();

            // Act: Remove credit tag
            $result = $creditService->removeCreditTag($patient, $removedBy, $reason, $ipAddress);

            // Assert 1: Operation should succeed
            expect($result)->toBeTrue('Removing credit tag should succeed');

            // Assert 2: Patient should no longer be credit eligible
            $patient->refresh();
            expect($patient->is_credit_eligible)->toBeFalse('Patient should not be credit eligible after removing tag');
            expect($patient->credit_reason)->toBeNull();
            expect($patient->credit_authorized_by)->toBeNull();
            expect($patient->credit_authorized_at)->toBeNull();

            // Assert 3: Audit log should be created
            $auditLog = PaymentAuditLog::where('patient_id', $patient->id)
                ->where('action', PaymentAuditLog::ACTION_CREDIT_TAG_REMOVED)
                ->first();

            expect($auditLog)->not->toBeNull('Audit log should be created for credit tag removal');
            expect($auditLog->user_id)->toBe($removedBy->id, 'Audit log should record removing user');
            expect($auditLog->reason)->toBe($reason, 'Audit log should record the reason');
            expect($auditLog->ip_address)->toBe($ipAddress, 'Audit log should record IP address');
            expect($auditLog->new_values)->toBeArray();
            expect($auditLog->new_values['is_credit_eligible'])->toBeFalse();

            // Clean up
            PaymentAuditLog::where('patient_id', $patient->id)->delete();
            $patient->delete();
            $authorizedBy->delete();
            $removedBy->delete();
        }
    });

    /**
     * Property: Cannot add credit tag to already credit-eligible patient
     */
    it('returns false when adding credit tag to already credit-eligible patient', function () {
        $creditService = app(CreditService::class);

        for ($iteration = 0; $iteration < 50; $iteration++) {
            // Arrange: Create a credit-eligible patient
            $patient = Patient::factory()->create([
                'is_credit_eligible' => true,
                'credit_reason' => 'Already eligible',
            ]);

            $authorizedBy = User::factory()->create();
            $reason = fake()->sentence();

            // Act: Try to add credit tag again
            $result = $creditService->addCreditTag($patient, $authorizedBy, $reason);

            // Assert: Operation should fail
            expect($result)->toBeFalse('Adding credit tag to already eligible patient should fail');

            // Assert: No new audit log should be created
            $auditLogCount = PaymentAuditLog::where('patient_id', $patient->id)
                ->where('action', PaymentAuditLog::ACTION_CREDIT_TAG_ADDED)
                ->count();

            expect($auditLogCount)->toBe(0, 'No audit log should be created for failed operation');

            // Clean up
            $patient->delete();
            $authorizedBy->delete();
        }
    });

    /**
     * Property: Cannot remove credit tag from non-credit-eligible patient
     */
    it('returns false when removing credit tag from non-credit-eligible patient', function () {
        $creditService = app(CreditService::class);

        for ($iteration = 0; $iteration < 50; $iteration++) {
            // Arrange: Create a non-credit-eligible patient
            $patient = Patient::factory()->create([
                'is_credit_eligible' => false,
            ]);

            $removedBy = User::factory()->create();
            $reason = fake()->sentence();

            // Act: Try to remove credit tag
            $result = $creditService->removeCreditTag($patient, $removedBy, $reason);

            // Assert: Operation should fail
            expect($result)->toBeFalse('Removing credit tag from non-eligible patient should fail');

            // Assert: No audit log should be created
            $auditLogCount = PaymentAuditLog::where('patient_id', $patient->id)
                ->where('action', PaymentAuditLog::ACTION_CREDIT_TAG_REMOVED)
                ->count();

            expect($auditLogCount)->toBe(0, 'No audit log should be created for failed operation');

            // Clean up
            $patient->delete();
            $removedBy->delete();
        }
    });
});

describe('Credit-tagged patient charges auto-marked as owing', function () {
    /**
     * Property: Charges for credit-tagged patients are auto-marked as owing
     * **Validates: Requirements 14.2**
     */
    it('auto-marks charges as owing for credit-tagged patients', function () {
        $billingService = app(BillingService::class);

        for ($iteration = 0; $iteration < 50; $iteration++) {
            // Arrange: Create a department with billing configuration
            $department = \App\Models\Department::factory()->create();
            \App\Models\DepartmentBilling::create([
                'department_id' => $department->id,
                'department_code' => $department->code ?? 'TEST',
                'department_name' => $department->name,
                'consultation_fee' => fake()->randomFloat(2, 50, 200),
                'equipment_fee' => 0,
                'emergency_surcharge' => 0,
                'is_active' => true,
            ]);

            // Create a credit-eligible patient
            $patient = Patient::factory()->create([
                'is_credit_eligible' => true,
                'credit_reason' => fake()->sentence(),
            ]);

            $checkin = PatientCheckin::factory()->create([
                'patient_id' => $patient->id,
                'department_id' => $department->id,
            ]);

            // Act: Create a consultation charge using the billing service
            $charge = $billingService->createConsultationCharge($checkin);

            // Assert: Charge should be created and marked as owing
            expect($charge)->not->toBeNull('Charge should be created for credit-tagged patient');
            expect($charge->status)->toBe('owing', 'Charge for credit-tagged patient should be auto-marked as owing');
            expect($charge->notes)->not->toBeNull('Charge notes should be set for credit-eligible patient');
            expect(str_contains($charge->notes, 'owing'))->toBeTrue('Charge notes should mention owing status');

            // Clean up
            $charge->delete();
            $checkin->delete();
            $patient->delete();
            \App\Models\DepartmentBilling::where('department_id', $department->id)->delete();
            $department->delete();
        }
    });

    /**
     * Property: Charges for non-credit-tagged patients are NOT auto-marked as owing
     */
    it('does not auto-mark charges as owing for non-credit-tagged patients', function () {
        $billingService = app(BillingService::class);

        for ($iteration = 0; $iteration < 50; $iteration++) {
            // Arrange: Create a department with billing configuration
            $department = \App\Models\Department::factory()->create();
            \App\Models\DepartmentBilling::create([
                'department_id' => $department->id,
                'department_code' => $department->code ?? 'TEST',
                'department_name' => $department->name,
                'consultation_fee' => fake()->randomFloat(2, 50, 200),
                'equipment_fee' => 0,
                'emergency_surcharge' => 0,
                'is_active' => true,
            ]);

            // Create a non-credit-eligible patient
            $patient = Patient::factory()->create([
                'is_credit_eligible' => false,
            ]);

            $checkin = PatientCheckin::factory()->create([
                'patient_id' => $patient->id,
                'department_id' => $department->id,
            ]);

            // Act: Create a consultation charge using the billing service
            $charge = $billingService->createConsultationCharge($checkin);

            // Assert: Charge should be created with pending status (not owing)
            expect($charge)->not->toBeNull('Charge should be created for non-credit-tagged patient');
            expect($charge->status)->toBe('pending', 'Charge for non-credit-tagged patient should be pending');

            // Clean up
            $charge->delete();
            $checkin->delete();
            $patient->delete();
            \App\Models\DepartmentBilling::where('department_id', $department->id)->delete();
            $department->delete();
        }
    });
});
