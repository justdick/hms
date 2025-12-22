<?php

/**
 * Property-Based Tests for Unpriced Item Handling
 *
 * These tests verify the correctness properties of handling unpriced items
 * (drugs and lab services) as defined in the design document.
 *
 * **Feature: centralized-pricing-management**
 */

use App\Models\Consultation;
use App\Models\Department;
use App\Models\Drug;
use App\Models\LabOrder;
use App\Models\LabService;
use App\Models\Patient;
use App\Models\PatientCheckin;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Property 11: Unpriced drugs auto-set to external
 *
 * *For any* prescription for an unpriced drug, the dispensing_source should be
 * automatically set to "external" (status = 'not_dispensed' with external_reason).
 *
 * **Validates: Requirements 6.1**
 */
describe('Property 11: Unpriced drugs auto-set to external', function () {
    beforeEach(function () {
        $this->department = Department::factory()->create();
        $this->patient = Patient::factory()->create();
        $this->checkin = PatientCheckin::factory()->create([
            'patient_id' => $this->patient->id,
            'department_id' => $this->department->id,
        ]);
        $this->consultation = Consultation::factory()->create([
            'patient_checkin_id' => $this->checkin->id,
        ]);
        // Authenticate a user for charge creation
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    });

    it('auto-sets unpriced drug prescription to external dispensing', function () {
        // Create an unpriced drug (zero price - null not allowed by current schema)
        // Note: Requirements 1.4 specifies null price for new items, but current schema
        // has NOT NULL constraint. Using 0 as "unpriced" indicator.
        $unpricedDrug = Drug::factory()->create([
            'unit_price' => 0.00,
        ]);

        // Create prescription for unpriced drug
        $prescription = Prescription::create([
            'prescribable_type' => Consultation::class,
            'prescribable_id' => $this->consultation->id,
            'drug_id' => $unpricedDrug->id,
            'medication_name' => $unpricedDrug->name,
            'frequency' => 'Once daily',
            'duration' => '7 days',
            'quantity' => 10,
            'dosage_form' => 'tablet',
            'status' => 'prescribed',
        ]);

        expect($prescription->is_unpriced)->toBeTrue()
            ->and($prescription->status)->toBe('not_dispensed')
            ->and($prescription->external_reason)->toBe('Drug is unpriced - patient to purchase externally');
    });

    it('auto-sets zero-price drug prescription to external dispensing', function () {
        // Create a drug with zero price
        $zeroPriceDrug = Drug::factory()->create([
            'unit_price' => 0.00,
        ]);

        // Create prescription for zero-price drug
        $prescription = Prescription::create([
            'prescribable_type' => Consultation::class,
            'prescribable_id' => $this->consultation->id,
            'drug_id' => $zeroPriceDrug->id,
            'medication_name' => $zeroPriceDrug->name,
            'frequency' => 'Twice daily',
            'duration' => '5 days',
            'quantity' => 20,
            'dosage_form' => 'capsule',
            'status' => 'prescribed',
        ]);

        expect($prescription->is_unpriced)->toBeTrue()
            ->and($prescription->status)->toBe('not_dispensed')
            ->and($prescription->external_reason)->toBe('Drug is unpriced - patient to purchase externally');
    });

    it('does not mark priced drug prescription as external', function () {
        // Create a priced drug
        $pricedDrug = Drug::factory()->create([
            'unit_price' => 25.00,
        ]);

        // Create prescription for priced drug with proper consultation_id for charge creation
        $prescription = Prescription::create([
            'consultation_id' => $this->consultation->id,
            'prescribable_type' => Consultation::class,
            'prescribable_id' => $this->consultation->id,
            'drug_id' => $pricedDrug->id,
            'medication_name' => $pricedDrug->name,
            'frequency' => 'Once daily',
            'duration' => '7 days',
            'quantity' => 10,
            'dosage_form' => 'tablet',
            'status' => 'prescribed',
        ]);

        expect($prescription->is_unpriced)->toBeFalse()
            ->and($prescription->status)->toBe('prescribed')
            ->and($prescription->external_reason)->toBeNull();
    });

    /**
     * Property-based test: For any unpriced drug (zero price),
     * prescriptions should always be auto-set to external dispensing
     *
     * Note: Current schema has NOT NULL constraint on unit_price, so we use 0 as "unpriced"
     */
    it('property: any unpriced drug prescription is auto-set to external', function () {
        // Run 100 iterations as per design document
        for ($i = 0; $i < 100; $i++) {
            // Use zero price (null not allowed by current schema)
            $drug = Drug::factory()->create([
                'unit_price' => 0.00,
            ]);

            $prescription = Prescription::create([
                'prescribable_type' => Consultation::class,
                'prescribable_id' => $this->consultation->id,
                'drug_id' => $drug->id,
                'medication_name' => $drug->name,
                'frequency' => fake()->randomElement(['Once daily', 'Twice daily', 'Three times daily']),
                'duration' => fake()->randomElement(['3 days', '5 days', '7 days', '14 days']),
                'quantity' => fake()->numberBetween(5, 100),
                'dosage_form' => fake()->randomElement(['tablet', 'capsule', 'syrup']),
                'status' => 'prescribed',
            ]);

            expect($prescription->is_unpriced)->toBeTrue(
                "Prescription for unpriced drug should have is_unpriced = true (iteration {$i})"
            )
                ->and($prescription->status)->toBe('not_dispensed',
                    "Prescription for unpriced drug should have status = 'not_dispensed' (iteration {$i})"
                )
                ->and($prescription->external_reason)->not->toBeNull(
                    "Prescription for unpriced drug should have external_reason set (iteration {$i})"
                );
        }
    });

    /**
     * Property-based test: For any priced drug (positive price),
     * prescriptions should NOT be auto-set to external dispensing
     */
    it('property: any priced drug prescription is NOT auto-set to external', function () {
        // Run 100 iterations as per design document
        for ($i = 0; $i < 100; $i++) {
            // Generate a positive price
            $price = round(rand(100, 100000) / 100, 2); // 1.00 to 1000.00

            $drug = Drug::factory()->create([
                'unit_price' => $price,
            ]);

            $prescription = Prescription::create([
                'consultation_id' => $this->consultation->id,
                'prescribable_type' => Consultation::class,
                'prescribable_id' => $this->consultation->id,
                'drug_id' => $drug->id,
                'medication_name' => $drug->name,
                'frequency' => fake()->randomElement(['Once daily', 'Twice daily', 'Three times daily']),
                'duration' => fake()->randomElement(['3 days', '5 days', '7 days', '14 days']),
                'quantity' => fake()->numberBetween(5, 100),
                'dosage_form' => fake()->randomElement(['tablet', 'capsule', 'syrup']),
                'status' => 'prescribed',
            ]);

            expect($prescription->is_unpriced)->toBeFalse(
                "Prescription for priced drug should have is_unpriced = false (iteration {$i})"
            )
                ->and($prescription->status)->toBe('prescribed',
                    "Prescription for priced drug should have status = 'prescribed' (iteration {$i})"
                )
                ->and($prescription->external_reason)->toBeNull(
                    "Prescription for priced drug should have external_reason = null (iteration {$i})"
                );
        }
    });
});

/**
 * Property 12: External prescriptions excluded from dispensing queue
 *
 * *For any* set of prescriptions, the pharmacy dispensing queue should exclude
 * all prescriptions with status = 'not_dispensed' (external dispensing).
 *
 * **Validates: Requirements 6.4**
 */
describe('Property 12: External prescriptions excluded from dispensing queue', function () {
    beforeEach(function () {
        $this->department = Department::factory()->create();
        $this->patient = Patient::factory()->create();
        $this->checkin = PatientCheckin::factory()->create([
            'patient_id' => $this->patient->id,
            'department_id' => $this->department->id,
        ]);
        $this->consultation = Consultation::factory()->create([
            'patient_checkin_id' => $this->checkin->id,
        ]);
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        $this->dispensingService = app(\App\Services\DispensingService::class);
    });

    it('excludes external prescriptions from review queue', function () {
        // Create a priced drug and prescription (should be in queue)
        $pricedDrug = Drug::factory()->create(['unit_price' => 25.00]);
        $normalPrescription = Prescription::create([
            'consultation_id' => $this->consultation->id,
            'prescribable_type' => Consultation::class,
            'prescribable_id' => $this->consultation->id,
            'drug_id' => $pricedDrug->id,
            'medication_name' => $pricedDrug->name,
            'frequency' => 'Once daily',
            'duration' => '7 days',
            'quantity' => 10,
            'dosage_form' => 'tablet',
            'status' => 'prescribed',
        ]);

        // Create an unpriced drug and prescription (should be excluded - auto-set to not_dispensed)
        $unpricedDrug = Drug::factory()->create(['unit_price' => 0.00]);
        $externalPrescription = Prescription::create([
            'consultation_id' => $this->consultation->id,
            'prescribable_type' => Consultation::class,
            'prescribable_id' => $this->consultation->id,
            'drug_id' => $unpricedDrug->id,
            'medication_name' => $unpricedDrug->name,
            'frequency' => 'Twice daily',
            'duration' => '5 days',
            'quantity' => 20,
            'dosage_form' => 'capsule',
            'status' => 'prescribed',
        ]);

        // Verify external prescription was auto-set to not_dispensed
        expect($externalPrescription->status)->toBe('not_dispensed');

        // Get prescriptions for review
        $reviewQueue = $this->dispensingService->getPrescriptionsForReview($this->patient->id);

        // Extract prescription IDs from the queue
        $queueIds = collect($reviewQueue)->pluck('prescription.id')->toArray();

        // Normal prescription should be in queue
        expect($queueIds)->toContain($normalPrescription->id);

        // External prescription should NOT be in queue
        expect($queueIds)->not->toContain($externalPrescription->id);
    });

    it('excludes external prescriptions from dispensing queue', function () {
        // Create a priced drug and reviewed prescription (should be in dispensing queue)
        $pricedDrug = Drug::factory()->create(['unit_price' => 25.00]);
        $reviewedPrescription = Prescription::create([
            'consultation_id' => $this->consultation->id,
            'prescribable_type' => Consultation::class,
            'prescribable_id' => $this->consultation->id,
            'drug_id' => $pricedDrug->id,
            'medication_name' => $pricedDrug->name,
            'frequency' => 'Once daily',
            'duration' => '7 days',
            'quantity' => 10,
            'quantity_to_dispense' => 10,
            'dosage_form' => 'tablet',
            'status' => 'reviewed',
            'reviewed_by' => $this->user->id,
            'reviewed_at' => now(),
        ]);

        // Create an unpriced drug and prescription (should be excluded)
        $unpricedDrug = Drug::factory()->create(['unit_price' => 0.00]);
        $externalPrescription = Prescription::create([
            'consultation_id' => $this->consultation->id,
            'prescribable_type' => Consultation::class,
            'prescribable_id' => $this->consultation->id,
            'drug_id' => $unpricedDrug->id,
            'medication_name' => $unpricedDrug->name,
            'frequency' => 'Twice daily',
            'duration' => '5 days',
            'quantity' => 20,
            'dosage_form' => 'capsule',
            'status' => 'prescribed',
        ]);

        // Verify external prescription was auto-set to not_dispensed
        expect($externalPrescription->status)->toBe('not_dispensed');

        // Get prescriptions for dispensing
        $dispensingQueue = $this->dispensingService->getPrescriptionsForDispensing($this->patient->id);

        // Extract prescription IDs from the queue
        $queueIds = collect($dispensingQueue)->pluck('prescription.id')->toArray();

        // Reviewed prescription should be in queue
        expect($queueIds)->toContain($reviewedPrescription->id);

        // External prescription should NOT be in queue
        expect($queueIds)->not->toContain($externalPrescription->id);
    });

    /**
     * Property-based test: For any mix of normal and external prescriptions,
     * the dispensing queue should never contain external prescriptions
     */
    it('property: dispensing queue never contains external prescriptions', function () {
        // Run 50 iterations
        for ($i = 0; $i < 50; $i++) {
            // Create random number of external prescriptions (1-3)
            $numExternal = rand(1, 3);

            for ($j = 0; $j < $numExternal; $j++) {
                $drug = Drug::factory()->create(['unit_price' => 0.00]);
                Prescription::create([
                    'consultation_id' => $this->consultation->id,
                    'prescribable_type' => Consultation::class,
                    'prescribable_id' => $this->consultation->id,
                    'drug_id' => $drug->id,
                    'medication_name' => $drug->name,
                    'frequency' => 'Twice daily',
                    'duration' => '5 days',
                    'quantity' => rand(5, 50),
                    'dosage_form' => 'capsule',
                    'status' => 'prescribed',
                ]);
            }
        }

        // Get prescriptions for review - should not contain any external prescriptions
        $reviewQueue = $this->dispensingService->getPrescriptionsForReview($this->patient->id);

        // Verify the queue does not contain any external prescriptions (status = 'not_dispensed')
        foreach ($reviewQueue as $item) {
            expect($item['prescription']->status)->not->toBe('not_dispensed',
                'Queue should not contain external prescriptions (status = not_dispensed)');
            expect($item['prescription']->is_unpriced)->not->toBeTrue(
                'Queue should not contain unpriced prescriptions');
        }

        // Also verify by checking the database directly
        $externalCount = Prescription::where('status', 'not_dispensed')
            ->where('is_unpriced', true)
            ->count();

        // We should have created external prescriptions
        expect($externalCount)->toBeGreaterThan(0,
            'Test should have created external prescriptions');

        // But none should be in the queue
        $queueExternalCount = collect($reviewQueue)
            ->filter(fn ($item) => $item['prescription']->status === 'not_dispensed')
            ->count();

        expect($queueExternalCount)->toBe(0,
            'No external prescriptions should be in the review queue');
    });
});

/**
 * Property 13: Unpriced labs auto-set to external referral
 *
 * *For any* lab order for an unpriced lab service, the status should be
 * automatically set to "external_referral".
 *
 * **Validates: Requirements 7.2**
 */
describe('Property 13: Unpriced labs auto-set to external referral', function () {
    beforeEach(function () {
        $this->department = Department::factory()->create();
        $this->patient = Patient::factory()->create();
        $this->checkin = PatientCheckin::factory()->create([
            'patient_id' => $this->patient->id,
            'department_id' => $this->department->id,
        ]);
        $this->consultation = Consultation::factory()->create([
            'patient_checkin_id' => $this->checkin->id,
        ]);
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    });

    it('auto-sets unpriced lab order to external referral', function () {
        // Create an unpriced lab service (zero price - null not allowed by current schema)
        $unpricedLabService = LabService::factory()->create([
            'price' => 0.00,
        ]);

        // Create lab order for unpriced lab service
        $labOrder = LabOrder::create([
            'orderable_type' => Consultation::class,
            'orderable_id' => $this->consultation->id,
            'lab_service_id' => $unpricedLabService->id,
            'ordered_by' => $this->user->id,
            'ordered_at' => now(),
            'status' => 'ordered',
            'priority' => 'routine',
        ]);

        expect($labOrder->is_unpriced)->toBeTrue()
            ->and($labOrder->status)->toBe('external_referral');
    });

    it('does not mark priced lab order as external referral', function () {
        // Create a priced lab service
        $pricedLabService = LabService::factory()->create([
            'price' => 50.00,
        ]);

        // Create lab order for priced lab service
        $labOrder = LabOrder::create([
            'orderable_type' => Consultation::class,
            'orderable_id' => $this->consultation->id,
            'lab_service_id' => $pricedLabService->id,
            'ordered_by' => $this->user->id,
            'ordered_at' => now(),
            'status' => 'ordered',
            'priority' => 'routine',
        ]);

        expect($labOrder->is_unpriced)->toBeFalse()
            ->and($labOrder->status)->toBe('ordered');
    });

    /**
     * Property-based test: For any unpriced lab service (zero price),
     * lab orders should always be auto-set to external referral
     */
    it('property: any unpriced lab order is auto-set to external referral', function () {
        // Run 100 iterations as per design document
        for ($i = 0; $i < 100; $i++) {
            // Use zero price (null not allowed by current schema)
            $labService = LabService::factory()->create([
                'price' => 0.00,
            ]);

            $labOrder = LabOrder::create([
                'orderable_type' => Consultation::class,
                'orderable_id' => $this->consultation->id,
                'lab_service_id' => $labService->id,
                'ordered_by' => $this->user->id,
                'ordered_at' => now(),
                'status' => 'ordered',
                'priority' => fake()->randomElement(['routine', 'urgent', 'stat']),
            ]);

            expect($labOrder->is_unpriced)->toBeTrue(
                "Lab order for unpriced service should have is_unpriced = true (iteration {$i})"
            )
                ->and($labOrder->status)->toBe('external_referral',
                    "Lab order for unpriced service should have status = 'external_referral' (iteration {$i})"
                );
        }
    });

    /**
     * Property-based test: For any priced lab service (positive price),
     * lab orders should NOT be auto-set to external referral
     */
    it('property: any priced lab order is NOT auto-set to external referral', function () {
        // Run 100 iterations as per design document
        for ($i = 0; $i < 100; $i++) {
            // Generate a positive price
            $price = round(rand(100, 100000) / 100, 2); // 1.00 to 1000.00

            $labService = LabService::factory()->create([
                'price' => $price,
            ]);

            $labOrder = LabOrder::create([
                'orderable_type' => Consultation::class,
                'orderable_id' => $this->consultation->id,
                'lab_service_id' => $labService->id,
                'ordered_by' => $this->user->id,
                'ordered_at' => now(),
                'status' => 'ordered',
                'priority' => fake()->randomElement(['routine', 'urgent', 'stat']),
            ]);

            expect($labOrder->is_unpriced)->toBeFalse(
                "Lab order for priced service should have is_unpriced = false (iteration {$i})"
            )
                ->and($labOrder->status)->toBe('ordered',
                    "Lab order for priced service should have status = 'ordered' (iteration {$i})"
                );
        }
    });
});

/**
 * Property 14: External referral orders excluded from lab queue
 *
 * *For any* set of lab orders, the lab work queue should exclude
 * all orders with status = 'external_referral'.
 *
 * **Validates: Requirements 7.4**
 */
describe('Property 14: External referral orders excluded from lab queue', function () {
    beforeEach(function () {
        $this->department = Department::factory()->create();
        $this->patient = Patient::factory()->create();
        $this->checkin = PatientCheckin::factory()->create([
            'patient_id' => $this->patient->id,
            'department_id' => $this->department->id,
        ]);
        $this->consultation = Consultation::factory()->create([
            'patient_checkin_id' => $this->checkin->id,
        ]);
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    });

    it('excludes external referral orders from pending queue', function () {
        // Create a priced lab service and order (should be in queue)
        $pricedLabService = LabService::factory()->create(['price' => 50.00]);
        $normalOrder = LabOrder::create([
            'orderable_type' => Consultation::class,
            'orderable_id' => $this->consultation->id,
            'lab_service_id' => $pricedLabService->id,
            'ordered_by' => $this->user->id,
            'ordered_at' => now(),
            'status' => 'ordered',
            'priority' => 'routine',
        ]);

        // Create an unpriced lab service and order (should be excluded - auto-set to external_referral)
        $unpricedLabService = LabService::factory()->create(['price' => 0.00]);
        $externalOrder = LabOrder::create([
            'orderable_type' => Consultation::class,
            'orderable_id' => $this->consultation->id,
            'lab_service_id' => $unpricedLabService->id,
            'ordered_by' => $this->user->id,
            'ordered_at' => now(),
            'status' => 'ordered',
            'priority' => 'routine',
        ]);

        // Verify external order was auto-set to external_referral
        expect($externalOrder->status)->toBe('external_referral');

        // Get pending lab orders using the scope
        $pendingOrders = LabOrder::pending()->get();
        $pendingIds = $pendingOrders->pluck('id')->toArray();

        // Normal order should be in queue
        expect($pendingIds)->toContain($normalOrder->id);

        // External referral order should NOT be in queue
        expect($pendingIds)->not->toContain($externalOrder->id);
    });

    it('excludes external referral orders using excludeExternalReferral scope', function () {
        // Create a priced lab service and order
        $pricedLabService = LabService::factory()->create(['price' => 50.00]);
        $normalOrder = LabOrder::create([
            'orderable_type' => Consultation::class,
            'orderable_id' => $this->consultation->id,
            'lab_service_id' => $pricedLabService->id,
            'ordered_by' => $this->user->id,
            'ordered_at' => now(),
            'status' => 'ordered',
            'priority' => 'routine',
        ]);

        // Create an unpriced lab service and order
        $unpricedLabService = LabService::factory()->create(['price' => 0.00]);
        $externalOrder = LabOrder::create([
            'orderable_type' => Consultation::class,
            'orderable_id' => $this->consultation->id,
            'lab_service_id' => $unpricedLabService->id,
            'ordered_by' => $this->user->id,
            'ordered_at' => now(),
            'status' => 'ordered',
            'priority' => 'routine',
        ]);

        // Get orders excluding external referrals
        $filteredOrders = LabOrder::excludeExternalReferral()->get();
        $filteredIds = $filteredOrders->pluck('id')->toArray();

        // Normal order should be included
        expect($filteredIds)->toContain($normalOrder->id);

        // External referral order should NOT be included
        expect($filteredIds)->not->toContain($externalOrder->id);
    });

    /**
     * Property-based test: For any mix of normal and external referral lab orders,
     * the pending queue should never contain external referral orders
     */
    it('property: lab queue never contains external referral orders', function () {
        // Run 50 iterations
        for ($i = 0; $i < 50; $i++) {
            // Create random number of external referral orders (1-3)
            $numExternal = rand(1, 3);

            for ($j = 0; $j < $numExternal; $j++) {
                $labService = LabService::factory()->create(['price' => 0.00]);
                LabOrder::create([
                    'orderable_type' => Consultation::class,
                    'orderable_id' => $this->consultation->id,
                    'lab_service_id' => $labService->id,
                    'ordered_by' => $this->user->id,
                    'ordered_at' => now(),
                    'status' => 'ordered',
                    'priority' => fake()->randomElement(['routine', 'urgent', 'stat']),
                ]);
            }
        }

        // Get pending lab orders - should not contain any external referral orders
        $pendingOrders = LabOrder::pending()->get();

        // Verify the queue does not contain any external referral orders
        foreach ($pendingOrders as $order) {
            expect($order->status)->not->toBe('external_referral',
                'Queue should not contain external referral orders');
            expect($order->is_unpriced)->not->toBeTrue(
                'Queue should not contain unpriced lab orders');
        }

        // Also verify by checking the database directly
        $externalCount = LabOrder::where('status', 'external_referral')
            ->where('is_unpriced', true)
            ->count();

        // We should have created external referral orders
        expect($externalCount)->toBeGreaterThan(0,
            'Test should have created external referral orders');

        // But none should be in the pending queue
        $queueExternalCount = $pendingOrders
            ->filter(fn ($order) => $order->status === 'external_referral')
            ->count();

        expect($queueExternalCount)->toBe(0,
            'No external referral orders should be in the pending queue');
    });
});
