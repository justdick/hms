<?php

/**
 * Property-Based Tests for Pricing Status Filter
 *
 * These tests verify the correctness properties of the pricing status filter
 * as defined in the centralized-pricing-management design document.
 */

use App\Models\Drug;
use App\Models\LabService;
use App\Models\MinorProcedureType;
use App\Models\User;
use App\Services\PricingDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->service = app(PricingDashboardService::class);
});

/**
 * Property 2: Unpriced filter returns only unpriced items
 *
 * *For any* set of items with various prices, the "Unpriced Items" filter
 * should return exactly those items where cash price is null or zero.
 *
 * **Feature: centralized-pricing-management, Property 2: Unpriced filter returns only unpriced items**
 * **Validates: Requirements 2.1**
 */
describe('Property 2: Unpriced filter returns only unpriced items', function () {
    it('returns only items with zero cash price when unpriced filter is applied', function () {
        // Create priced items
        Drug::factory()->create(['unit_price' => 10.00, 'is_active' => true]);
        Drug::factory()->create(['unit_price' => 25.50, 'is_active' => true]);
        LabService::factory()->create(['price' => 100.00, 'is_active' => true]);

        // Create unpriced items (zero price - both tables have NOT NULL constraint)
        $unpricedDrug = Drug::factory()->create(['unit_price' => 0, 'is_active' => true]);
        $unpricedLab = LabService::factory()->create(['price' => 0, 'is_active' => true]);

        $result = $this->service->getPricingData(null, null, null, false, 100, 'unpriced');
        $items = collect($result['items']->items());

        // Should only return unpriced items
        expect($items->count())->toBe(2);

        // Verify all returned items have null or zero cash price
        $items->each(function ($item) {
            expect($item['cash_price'] === null || $item['cash_price'] <= 0)->toBeTrue(
                "Item '{$item['name']}' should have null or zero cash price, got: {$item['cash_price']}"
            );
        });
    });

    it('returns items with zero cash price across different item types', function () {
        // Create priced items
        Drug::factory()->create(['unit_price' => 10.00, 'is_active' => true]);

        // Create unpriced items (zero price - both tables have NOT NULL constraint)
        $zeroPriceDrug = Drug::factory()->create(['unit_price' => 0, 'is_active' => true]);
        $zeroPriceLab = LabService::factory()->create(['price' => 0, 'is_active' => true]);
        $zeroPriceProcedure = MinorProcedureType::factory()->create(['price' => 0, 'is_active' => true]);

        $result = $this->service->getPricingData(null, null, null, false, 100, 'unpriced');
        $items = collect($result['items']->items());

        // Should return zero-priced items
        expect($items->count())->toBe(3);

        // Verify all returned items have null or zero cash price
        $items->each(function ($item) {
            expect($item['cash_price'] === null || $item['cash_price'] <= 0)->toBeTrue(
                "Item '{$item['name']}' should have null or zero cash price"
            );
        });
    });

    it('returns empty when all items are priced', function () {
        // Create only priced items
        Drug::factory()->count(3)->create(['unit_price' => 50.00, 'is_active' => true]);
        LabService::factory()->count(2)->create(['price' => 100.00, 'is_active' => true]);

        $result = $this->service->getPricingData(null, null, null, false, 100, 'unpriced');
        $items = collect($result['items']->items());

        expect($items->count())->toBe(0);
    });

    it('property test: unpriced filter returns only unpriced items across random data', function () {
        // Run 100 iterations with random data
        for ($i = 0; $i < 100; $i++) {
            // Create random mix of priced and unpriced items
            $numPriced = rand(1, 5);
            $numUnpriced = rand(1, 5);

            // Create priced drugs (positive price)
            Drug::factory()->count($numPriced)->create([
                'unit_price' => rand(100, 10000) / 100,
                'is_active' => true,
            ]);

            // Create unpriced drugs (zero price - drugs table has NOT NULL constraint)
            Drug::factory()->count($numUnpriced)->create([
                'unit_price' => 0,
                'is_active' => true,
            ]);

            $result = $this->service->getPricingData(null, 'drugs', null, false, 1000, 'unpriced');
            $items = collect($result['items']->items());

            // Verify all returned items are unpriced
            $items->each(function ($item) {
                expect($item['cash_price'] === null || $item['cash_price'] <= 0)->toBeTrue(
                    'Unpriced filter should only return items with null or zero price'
                );
            });

            // Verify count matches expected unpriced items
            expect($items->count())->toBe($numUnpriced);

            // Clean up for next iteration
            Drug::query()->delete();
        }
    });
});

/**
 * Property 3: Priced filter returns only priced items
 *
 * *For any* set of items with various prices, the "Priced Items" filter
 * should return exactly those items where cash price is greater than zero.
 *
 * **Feature: centralized-pricing-management, Property 3: Priced filter returns only priced items**
 * **Validates: Requirements 2.2**
 */
describe('Property 3: Priced filter returns only priced items', function () {
    it('returns only items with positive cash price when priced filter is applied', function () {
        // Create priced items
        $pricedDrug1 = Drug::factory()->create(['unit_price' => 10.00, 'is_active' => true]);
        $pricedDrug2 = Drug::factory()->create(['unit_price' => 25.50, 'is_active' => true]);
        $pricedLab = LabService::factory()->create(['price' => 100.00, 'is_active' => true]);

        // Create unpriced items (zero price)
        Drug::factory()->create(['unit_price' => 0, 'is_active' => true]);
        LabService::factory()->create(['price' => 0, 'is_active' => true]);

        $result = $this->service->getPricingData(null, null, null, false, 100, 'priced');
        $items = collect($result['items']->items());

        // Should only return priced items
        expect($items->count())->toBe(3);

        // Verify all returned items have positive cash price
        $items->each(function ($item) {
            expect($item['cash_price'] !== null && $item['cash_price'] > 0)->toBeTrue(
                "Item '{$item['name']}' should have positive cash price, got: {$item['cash_price']}"
            );
        });
    });

    it('returns empty when all items are unpriced', function () {
        // Create only unpriced items (zero price)
        Drug::factory()->count(3)->create(['unit_price' => 0, 'is_active' => true]);
        LabService::factory()->count(2)->create(['price' => 0, 'is_active' => true]);

        $result = $this->service->getPricingData(null, null, null, false, 100, 'priced');
        $items = collect($result['items']->items());

        expect($items->count())->toBe(0);
    });

    it('returns all items when all are priced', function () {
        // Create only priced items
        Drug::factory()->count(3)->create(['unit_price' => 50.00, 'is_active' => true]);
        LabService::factory()->count(2)->create(['price' => 100.00, 'is_active' => true]);

        $result = $this->service->getPricingData(null, null, null, false, 100, 'priced');
        $items = collect($result['items']->items());

        expect($items->count())->toBe(5);

        // Verify all returned items have positive cash price
        $items->each(function ($item) {
            expect($item['cash_price'] > 0)->toBeTrue();
        });
    });

    it('property test: priced filter returns only priced items across random data', function () {
        // Run 100 iterations with random data
        for ($i = 0; $i < 100; $i++) {
            // Create random mix of priced and unpriced items
            $numPriced = rand(1, 5);
            $numUnpriced = rand(1, 5);

            // Create priced drugs (positive price)
            Drug::factory()->count($numPriced)->create([
                'unit_price' => rand(100, 10000) / 100,
                'is_active' => true,
            ]);

            // Create unpriced drugs (zero price)
            Drug::factory()->count($numUnpriced)->create([
                'unit_price' => 0,
                'is_active' => true,
            ]);

            $result = $this->service->getPricingData(null, 'drugs', null, false, 1000, 'priced');
            $items = collect($result['items']->items());

            // Verify all returned items are priced
            $items->each(function ($item) {
                expect($item['cash_price'] !== null && $item['cash_price'] > 0)->toBeTrue(
                    'Priced filter should only return items with positive price'
                );
            });

            // Verify count matches expected priced items
            expect($items->count())->toBe($numPriced);

            // Clean up for next iteration
            Drug::query()->delete();
        }
    });
});

/**
 * Property 10: Pricing summary counts are accurate
 *
 * *For any* set of items, the pricing summary counts should exactly match
 * the number of items in each status category.
 *
 * **Feature: centralized-pricing-management, Property 10: Pricing summary counts are accurate**
 * **Validates: Requirements 5.2**
 */
describe('Property 10: Pricing summary counts are accurate', function () {
    it('returns correct counts for priced and unpriced items', function () {
        // Create priced items
        Drug::factory()->count(3)->create(['unit_price' => 50.00, 'is_active' => true]);
        LabService::factory()->count(2)->create(['price' => 100.00, 'is_active' => true]);

        // Create unpriced items (zero price)
        Drug::factory()->count(2)->create(['unit_price' => 0, 'is_active' => true]);
        LabService::factory()->count(1)->create(['price' => 0, 'is_active' => true]);

        $summary = $this->service->getPricingStatusSummary();

        expect($summary['priced'])->toBe(5);
        expect($summary['unpriced'])->toBe(3);
        expect($summary['nhis_mapped'])->toBe(0);
        expect($summary['nhis_unmapped'])->toBe(0);
        expect($summary['flexible_copay'])->toBe(0);
    });

    it('returns zero counts when no items exist', function () {
        $summary = $this->service->getPricingStatusSummary();

        expect($summary['priced'])->toBe(0);
        expect($summary['unpriced'])->toBe(0);
        expect($summary['nhis_mapped'])->toBe(0);
        expect($summary['nhis_unmapped'])->toBe(0);
        expect($summary['flexible_copay'])->toBe(0);
    });

    it('returns all priced when no unpriced items exist', function () {
        Drug::factory()->count(5)->create(['unit_price' => 50.00, 'is_active' => true]);

        $summary = $this->service->getPricingStatusSummary();

        expect($summary['priced'])->toBe(5);
        expect($summary['unpriced'])->toBe(0);
    });

    it('returns all unpriced when no priced items exist', function () {
        Drug::factory()->count(5)->create(['unit_price' => 0, 'is_active' => true]);

        $summary = $this->service->getPricingStatusSummary();

        expect($summary['priced'])->toBe(0);
        expect($summary['unpriced'])->toBe(5);
    });

    it('property test: summary counts match actual item counts across random data', function () {
        // Run 100 iterations with random data
        for ($i = 0; $i < 100; $i++) {
            // Create random mix of priced and unpriced items
            $numPricedDrugs = rand(0, 5);
            $numUnpricedDrugs = rand(0, 5);
            $numPricedLabs = rand(0, 5);
            $numUnpricedLabs = rand(0, 5);

            // Create priced drugs
            if ($numPricedDrugs > 0) {
                Drug::factory()->count($numPricedDrugs)->create([
                    'unit_price' => rand(100, 10000) / 100,
                    'is_active' => true,
                ]);
            }

            // Create unpriced drugs
            if ($numUnpricedDrugs > 0) {
                Drug::factory()->count($numUnpricedDrugs)->create([
                    'unit_price' => 0,
                    'is_active' => true,
                ]);
            }

            // Create priced labs
            if ($numPricedLabs > 0) {
                LabService::factory()->count($numPricedLabs)->create([
                    'price' => rand(100, 10000) / 100,
                    'is_active' => true,
                ]);
            }

            // Create unpriced labs
            if ($numUnpricedLabs > 0) {
                LabService::factory()->count($numUnpricedLabs)->create([
                    'price' => 0,
                    'is_active' => true,
                ]);
            }

            $expectedPriced = $numPricedDrugs + $numPricedLabs;
            $expectedUnpriced = $numUnpricedDrugs + $numUnpricedLabs;

            $summary = $this->service->getPricingStatusSummary();

            expect($summary['priced'])->toBe($expectedPriced,
                "Expected {$expectedPriced} priced items, got {$summary['priced']}"
            );
            expect($summary['unpriced'])->toBe($expectedUnpriced,
                "Expected {$expectedUnpriced} unpriced items, got {$summary['unpriced']}"
            );

            // Clean up for next iteration
            Drug::query()->delete();
            LabService::query()->delete();
        }
    });
});
