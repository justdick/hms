<?php

/**
 * Property-Based Tests for Unmapped NHIS Billing
 *
 * These tests verify the correctness properties of billing for unmapped NHIS items
 * with flexible copay as defined in the design document.
 *
 * **Feature: centralized-pricing-management**
 */

use App\Models\Drug;
use App\Models\InsuranceCoverageRule;
use App\Models\InsurancePlan;
use App\Models\InsuranceProvider;
use App\Services\InsuranceCoverageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

/**
 * Property 7: Unmapped NHIS billing with flexible copay
 *
 * *For any* NHIS patient charge for an unmapped item with flexible copay configured,
 * the charge should have insurance_amount = 0 and patient_amount = copay_amount.
 *
 * **Validates: Requirements 4.1, 4.3**
 */
describe('Property 7: Unmapped NHIS billing with flexible copay', function () {
    beforeEach(function () {
        // Create NHIS provider and plan
        $this->nhisProvider = InsuranceProvider::factory()->nhis()->create();
        $this->nhisPlan = InsurancePlan::factory()->create([
            'insurance_provider_id' => $this->nhisProvider->id,
        ]);
        $this->service = app(InsuranceCoverageService::class);
    });

    it('charges patient copay amount for unmapped item with flexible copay', function () {
        $copayAmount = 15.00;
        $cashPrice = 100.00;
        $itemCode = 'DRUG-UNMAPPED-001';

        // Create flexible copay rule for unmapped item
        InsuranceCoverageRule::factory()->create([
            'insurance_plan_id' => $this->nhisPlan->id,
            'coverage_category' => 'drug',
            'item_code' => $itemCode,
            'is_unmapped' => true,
            'patient_copay_amount' => $copayAmount,
            'is_active' => true,
        ]);

        // Calculate coverage - item is not mapped to NHIS tariff
        $coverage = $this->service->calculateNhisCoverage(
            insurancePlanId: $this->nhisPlan->id,
            category: 'drug',
            itemCode: $itemCode,
            itemId: 999, // Non-existent item ID (no NHIS mapping)
            itemType: 'drug',
            amount: $cashPrice,
            quantity: 1
        );

        expect($coverage['is_covered'])->toBeTrue()
            ->and($coverage['insurance_pays'])->toBe(0.00)
            ->and($coverage['patient_pays'])->toBe($copayAmount)
            ->and($coverage['is_unmapped'])->toBeTrue()
            ->and($coverage['has_flexible_copay'])->toBeTrue()
            ->and($coverage['coverage_type'])->toBe('nhis_unmapped_with_copay')
            ->and($coverage['rule_type'])->toBe('flexible_copay');
    });

    it('correctly calculates copay with quantity for unmapped items', function () {
        $copayAmount = 10.00;
        $cashPrice = 50.00;
        $quantity = 3;
        $itemCode = 'DRUG-UNMAPPED-002';

        InsuranceCoverageRule::factory()->create([
            'insurance_plan_id' => $this->nhisPlan->id,
            'coverage_category' => 'drug',
            'item_code' => $itemCode,
            'is_unmapped' => true,
            'patient_copay_amount' => $copayAmount,
            'is_active' => true,
        ]);

        $coverage = $this->service->calculateNhisCoverage(
            insurancePlanId: $this->nhisPlan->id,
            category: 'drug',
            itemCode: $itemCode,
            itemId: 999,
            itemType: 'drug',
            amount: $cashPrice,
            quantity: $quantity
        );

        // Patient pays copay * quantity
        expect($coverage['insurance_pays'])->toBe(0.00)
            ->and($coverage['patient_pays'])->toBe($copayAmount * $quantity)
            ->and($coverage['subtotal'])->toBe($cashPrice * $quantity);
    });

    /**
     * Property-based test: For any random copay and cash price,
     * unmapped items with flexible copay should always have insurance_pays = 0
     * and patient_pays = copay_amount * quantity
     */
    it('property: insurance pays 0 and patient pays copay for any unmapped item with flexible copay', function () {
        $categories = ['drug', 'lab', 'procedure', 'consultation'];

        // Run 100 iterations as per design document
        for ($i = 0; $i < 100; $i++) {
            Cache::flush();

            $category = $categories[array_rand($categories)];
            $copayAmount = round(rand(100, 10000) / 100, 2); // 1.00 to 100.00
            $cashPrice = round(rand(1000, 100000) / 100, 2); // 10.00 to 1000.00
            $quantity = rand(1, 10);
            $itemCode = strtoupper(fake()->bothify('???-####'));

            // Create flexible copay rule
            InsuranceCoverageRule::factory()->create([
                'insurance_plan_id' => $this->nhisPlan->id,
                'coverage_category' => $category,
                'item_code' => $itemCode,
                'is_unmapped' => true,
                'patient_copay_amount' => $copayAmount,
                'is_active' => true,
            ]);

            $coverage = $this->service->calculateNhisCoverage(
                insurancePlanId: $this->nhisPlan->id,
                category: $category,
                itemCode: $itemCode,
                itemId: 99999 + $i, // Non-existent item ID
                itemType: $category === 'lab' ? 'lab_service' : $category,
                amount: $cashPrice,
                quantity: $quantity
            );

            // Property assertions - use toEqual for floating point comparison
            $expectedPatientPays = round($copayAmount * $quantity, 2);
            expect($coverage['insurance_pays'])->toEqual(0.00,
                "Insurance should pay 0 for unmapped item (iteration {$i})")
                ->and(round($coverage['patient_pays'], 2))->toEqual($expectedPatientPays,
                    "Patient should pay copay * quantity for unmapped item (iteration {$i})")
                ->and($coverage['is_unmapped'])->toBeTrue()
                ->and($coverage['has_flexible_copay'])->toBeTrue();
        }
    });
});

/**
 * Property 8: Unmapped NHIS billing without copay
 *
 * *For any* NHIS patient charge for an unmapped item without flexible copay,
 * the charge should have insurance_amount = 0 and patient_amount = cash_price.
 *
 * **Validates: Requirements 4.2, 4.4**
 */
describe('Property 8: Unmapped NHIS billing without copay', function () {
    beforeEach(function () {
        // Create NHIS provider and plan
        $this->nhisProvider = InsuranceProvider::factory()->nhis()->create();
        $this->nhisPlan = InsurancePlan::factory()->create([
            'insurance_provider_id' => $this->nhisProvider->id,
        ]);
        $this->service = app(InsuranceCoverageService::class);
    });

    it('charges patient full cash price for unmapped item without flexible copay', function () {
        $cashPrice = 100.00;
        $itemCode = 'DRUG-UNMAPPED-NO-COPAY';

        // No flexible copay rule exists for this item

        $coverage = $this->service->calculateNhisCoverage(
            insurancePlanId: $this->nhisPlan->id,
            category: 'drug',
            itemCode: $itemCode,
            itemId: 999, // Non-existent item ID (no NHIS mapping)
            itemType: 'drug',
            amount: $cashPrice,
            quantity: 1
        );

        expect($coverage['is_covered'])->toBeFalse()
            ->and($coverage['insurance_pays'])->toBe(0.00)
            ->and($coverage['patient_pays'])->toBe($cashPrice)
            ->and($coverage['is_unmapped'])->toBeTrue()
            ->and($coverage['has_flexible_copay'])->toBeFalse()
            ->and($coverage['coverage_type'])->toBe('nhis_not_mapped');
    });

    it('correctly calculates cash price with quantity for unmapped items without copay', function () {
        $cashPrice = 50.00;
        $quantity = 3;
        $itemCode = 'DRUG-UNMAPPED-NO-COPAY-2';

        $coverage = $this->service->calculateNhisCoverage(
            insurancePlanId: $this->nhisPlan->id,
            category: 'drug',
            itemCode: $itemCode,
            itemId: 999,
            itemType: 'drug',
            amount: $cashPrice,
            quantity: $quantity
        );

        // Patient pays full cash price * quantity
        expect($coverage['insurance_pays'])->toBe(0.00)
            ->and($coverage['patient_pays'])->toBe($cashPrice * $quantity)
            ->and($coverage['subtotal'])->toBe($cashPrice * $quantity);
    });

    /**
     * Property-based test: For any random cash price,
     * unmapped items without flexible copay should always have insurance_pays = 0
     * and patient_pays = cash_price * quantity
     */
    it('property: insurance pays 0 and patient pays cash price for any unmapped item without copay', function () {
        $categories = ['drug', 'lab', 'procedure', 'consultation'];

        // Run 100 iterations as per design document
        for ($i = 0; $i < 100; $i++) {
            Cache::flush();

            $category = $categories[array_rand($categories)];
            $cashPrice = round(rand(1000, 100000) / 100, 2); // 10.00 to 1000.00
            $quantity = rand(1, 10);
            $itemCode = strtoupper(fake()->bothify('???-####'));

            // No flexible copay rule - item is completely unmapped

            $coverage = $this->service->calculateNhisCoverage(
                insurancePlanId: $this->nhisPlan->id,
                category: $category,
                itemCode: $itemCode,
                itemId: 99999 + $i, // Non-existent item ID
                itemType: $category === 'lab' ? 'lab_service' : $category,
                amount: $cashPrice,
                quantity: $quantity
            );

            $expectedPatientPays = round($cashPrice * $quantity, 2);

            // Property assertions - use toEqual for floating point comparison
            expect($coverage['insurance_pays'])->toEqual(0.00,
                "Insurance should pay 0 for unmapped item without copay (iteration {$i})")
                ->and(round($coverage['patient_pays'], 2))->toEqual($expectedPatientPays,
                    "Patient should pay full cash price for unmapped item without copay (iteration {$i})")
                ->and($coverage['is_unmapped'])->toBeTrue()
                ->and($coverage['has_flexible_copay'])->toBeFalse()
                ->and($coverage['is_covered'])->toBeFalse();
        }
    });

    it('uses regular coverage rule copay for unmapped items when copay is configured', function () {
        $cashPrice = 100.00;
        $itemCode = 'DRUG-WITH-REGULAR-RULE';
        $copayAmount = 5.00;

        // Create a regular (non-unmapped) coverage rule with copay
        InsuranceCoverageRule::factory()->create([
            'insurance_plan_id' => $this->nhisPlan->id,
            'coverage_category' => 'drug',
            'item_code' => $itemCode,
            'is_unmapped' => false, // Regular rule, not flexible copay
            'is_covered' => true,
            'coverage_type' => 'percentage',
            'coverage_value' => 80,
            'patient_copay_amount' => $copayAmount,
            'is_active' => true,
        ]);

        // Item is not mapped to NHIS tariff, but has copay configured
        $coverage = $this->service->calculateNhisCoverage(
            insurancePlanId: $this->nhisPlan->id,
            category: 'drug',
            itemCode: $itemCode,
            itemId: 999, // Non-existent item ID (no NHIS mapping)
            itemType: 'drug',
            amount: $cashPrice,
            quantity: 1
        );

        // Should charge the copay amount since it's configured, even though item is unmapped
        expect($coverage['is_covered'])->toBeTrue()
            ->and($coverage['insurance_pays'])->toBe(0.00)
            ->and($coverage['patient_pays'])->toBe($copayAmount)
            ->and($coverage['is_unmapped'])->toBeTrue()
            ->and($coverage['has_flexible_copay'])->toBeTrue();
    });
});

/**
 * Property 9: Insurance claims include unmapped items
 *
 * *For any* insurance claim for an NHIS patient with unmapped items,
 * all unmapped items should be included in the claim with insurance_amount = 0.
 *
 * **Validates: Requirements 4.5**
 */
describe('Property 9: Insurance claims include unmapped items', function () {
    beforeEach(function () {
        // Disable PrescriptionObserver to prevent auto-charge creation
        \App\Models\Prescription::unsetEventDispatcher();

        // Create NHIS provider and plan
        $this->nhisProvider = InsuranceProvider::factory()->nhis()->create();
        $this->nhisPlan = InsurancePlan::factory()->create([
            'insurance_provider_id' => $this->nhisProvider->id,
        ]);

        // Create patient with NHIS insurance
        $this->patient = \App\Models\Patient::factory()->create();
        $this->patientInsurance = \App\Models\PatientInsurance::factory()->create([
            'patient_id' => $this->patient->id,
            'insurance_plan_id' => $this->nhisPlan->id,
            'status' => 'active',
        ]);

        // Create department and check-in
        $this->department = \App\Models\Department::factory()->create();
        $this->checkin = \App\Models\PatientCheckin::factory()->create([
            'patient_id' => $this->patient->id,
            'department_id' => $this->department->id,
        ]);

        $this->claimService = app(\App\Services\InsuranceClaimService::class);
    });

    afterEach(function () {
        // Re-enable event dispatcher
        \App\Models\Prescription::setEventDispatcher(app('events'));
    });

    it('includes unmapped items in claims with insurance_pays = 0', function () {
        // Create a claim
        $claim = \App\Models\InsuranceClaim::factory()->create([
            'patient_id' => $this->patient->id,
            'patient_insurance_id' => $this->patientInsurance->id,
            'patient_checkin_id' => $this->checkin->id,
            'status' => 'pending_vetting',
        ]);

        // Create a drug that is NOT mapped to NHIS
        $unmappedDrug = \App\Models\Drug::factory()->create([
            'unit_price' => 50.00,
        ]);

        // Create a prescription for the unmapped drug
        $consultation = \App\Models\Consultation::factory()->create([
            'patient_checkin_id' => $this->checkin->id,
        ]);

        $prescription = \App\Models\Prescription::factory()->create([
            'prescribable_type' => \App\Models\Consultation::class,
            'prescribable_id' => $consultation->id,
            'drug_id' => $unmappedDrug->id,
            'quantity' => 2,
        ]);

        // Create a charge for the prescription
        $charge = \App\Models\Charge::factory()->create([
            'patient_checkin_id' => $this->checkin->id,
            'prescription_id' => $prescription->id,
            'service_type' => 'pharmacy',
            'charge_type' => 'medication',
            'service_code' => $unmappedDrug->drug_code,
            'amount' => 100.00, // 50 * 2
            'metadata' => ['quantity' => 2],
        ]);

        // Add charge to claim
        $this->claimService->addChargesToClaim($claim, [$charge->id]);

        // Verify the claim item was created
        $claimItem = $claim->items()->first();

        expect($claimItem)->not->toBeNull()
            ->and((float) $claimItem->insurance_pays)->toBe(0.00)
            ->and($claimItem->is_unmapped)->toBeTrue()
            ->and($claimItem->is_covered)->toBeFalse();
    });

    it('includes unmapped items with flexible copay in claims', function () {
        // Create flexible copay rule for an unmapped item
        $copayAmount = 10.00;
        $itemCode = 'UNMAPPED-DRUG-001';

        InsuranceCoverageRule::factory()->create([
            'insurance_plan_id' => $this->nhisPlan->id,
            'coverage_category' => 'drug',
            'item_code' => $itemCode,
            'is_unmapped' => true,
            'patient_copay_amount' => $copayAmount,
            'is_active' => true,
        ]);

        // Create a claim
        $claim = \App\Models\InsuranceClaim::factory()->create([
            'patient_id' => $this->patient->id,
            'patient_insurance_id' => $this->patientInsurance->id,
            'patient_checkin_id' => $this->checkin->id,
            'status' => 'pending_vetting',
        ]);

        // Create a drug that is NOT mapped to NHIS but has flexible copay
        $unmappedDrug = \App\Models\Drug::factory()->create([
            'drug_code' => $itemCode,
            'unit_price' => 50.00,
        ]);

        // Create a prescription for the unmapped drug
        $consultation = \App\Models\Consultation::factory()->create([
            'patient_checkin_id' => $this->checkin->id,
        ]);

        $prescription = \App\Models\Prescription::factory()->create([
            'prescribable_type' => \App\Models\Consultation::class,
            'prescribable_id' => $consultation->id,
            'drug_id' => $unmappedDrug->id,
            'quantity' => 2,
        ]);

        // Create a charge for the prescription
        $charge = \App\Models\Charge::factory()->create([
            'patient_checkin_id' => $this->checkin->id,
            'prescription_id' => $prescription->id,
            'service_type' => 'pharmacy',
            'charge_type' => 'medication',
            'service_code' => $itemCode,
            'amount' => 100.00, // 50 * 2
            'metadata' => ['quantity' => 2],
        ]);

        // Add charge to claim
        $this->claimService->addChargesToClaim($claim, [$charge->id]);

        // Verify the claim item was created with flexible copay
        $claimItem = $claim->items()->first();

        expect($claimItem)->not->toBeNull()
            ->and((float) $claimItem->insurance_pays)->toBe(0.00)
            ->and((float) $claimItem->patient_pays)->toBe($copayAmount * 2) // copay * quantity
            ->and($claimItem->is_unmapped)->toBeTrue()
            ->and($claimItem->has_flexible_copay)->toBeTrue()
            ->and($claimItem->is_covered)->toBeTrue(); // Covered in the sense it's configured
    });

    /**
     * Property-based test: For any set of charges including unmapped items,
     * all items should be included in the claim with correct insurance_pays values
     */
    it('property: all unmapped items included in claims with insurance_pays = 0', function () {
        // Run 20 iterations (fewer due to complexity of setup)
        for ($i = 0; $i < 20; $i++) {
            Cache::flush();

            // Create a new claim for each iteration
            $claim = \App\Models\InsuranceClaim::factory()->create([
                'patient_id' => $this->patient->id,
                'patient_insurance_id' => $this->patientInsurance->id,
                'patient_checkin_id' => $this->checkin->id,
                'status' => 'pending_vetting',
            ]);

            // Create random number of unmapped drugs (1-3)
            $numDrugs = rand(1, 3);
            $charges = [];

            $consultation = \App\Models\Consultation::factory()->create([
                'patient_checkin_id' => $this->checkin->id,
            ]);

            for ($j = 0; $j < $numDrugs; $j++) {
                $cashPrice = round(rand(1000, 10000) / 100, 2);
                $quantity = rand(1, 5);

                $drug = \App\Models\Drug::factory()->create([
                    'unit_price' => $cashPrice,
                ]);

                $prescription = \App\Models\Prescription::factory()->create([
                    'prescribable_type' => \App\Models\Consultation::class,
                    'prescribable_id' => $consultation->id,
                    'drug_id' => $drug->id,
                    'quantity' => $quantity,
                ]);

                $charge = \App\Models\Charge::factory()->create([
                    'patient_checkin_id' => $this->checkin->id,
                    'prescription_id' => $prescription->id,
                    'service_type' => 'pharmacy',
                    'charge_type' => 'medication',
                    'service_code' => $drug->drug_code,
                    'amount' => $cashPrice * $quantity,
                    'metadata' => ['quantity' => $quantity],
                ]);

                $charges[] = $charge->id;
            }

            // Add all charges to claim
            $this->claimService->addChargesToClaim($claim, $charges);

            // Verify all items were included
            $claimItems = $claim->items()->get();

            expect($claimItems->count())->toBe($numDrugs,
                "All {$numDrugs} unmapped items should be included in claim (iteration {$i})");

            // Verify each item has insurance_pays = 0 (since they're unmapped)
            foreach ($claimItems as $item) {
                expect((float) $item->insurance_pays)->toBe(0.00,
                    "Unmapped item should have insurance_pays = 0 (iteration {$i})");
                expect($item->is_unmapped)->toBeTrue(
                    "Item should be marked as unmapped (iteration {$i})");
            }
        }
    });
});
