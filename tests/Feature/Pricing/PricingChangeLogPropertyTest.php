<?php

/**
 * Property-Based Tests for Pricing Change Log Audit
 *
 * These tests verify the correctness properties of the pricing change audit logging
 * as defined in the design document.
 *
 * **Feature: unified-pricing-dashboard, Property 13: Audit log captures all price changes**
 * **Validates: Requirements 9.1, 9.2**
 */

use App\Models\PricingChangeLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Property 13: Audit log captures all price changes
 *
 * *For any* price or copay change made via the dashboard (including imports),
 * a PricingChangeLog record should be created with the correct old value,
 * new value, user, and timestamp.
 */
describe('Property 13: Audit log captures all price changes', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
    });

    it('creates audit log with correct values using logChange method', function () {
        $oldValue = 100.50;
        $newValue = 125.75;
        $itemType = PricingChangeLog::TYPE_DRUG;
        $itemId = 1;
        $itemCode = 'DRG-001';
        $fieldChanged = PricingChangeLog::FIELD_CASH_PRICE;

        $log = PricingChangeLog::logChange(
            $itemType,
            $itemId,
            $itemCode,
            $fieldChanged,
            null,
            $oldValue,
            $newValue,
            $this->user->id
        );

        expect($log)->toBeInstanceOf(PricingChangeLog::class);
        expect($log->item_type)->toBe($itemType);
        expect($log->item_id)->toBe($itemId);
        expect($log->item_code)->toBe($itemCode);
        expect($log->field_changed)->toBe($fieldChanged);
        expect($log->insurance_plan_id)->toBeNull();
        expect((float) $log->old_value)->toBe($oldValue);
        expect((float) $log->new_value)->toBe($newValue);
        expect($log->changed_by)->toBe($this->user->id);
        expect($log->created_at)->not->toBeNull();
    });

    it('creates audit log for copay changes with insurance plan', function () {
        // Create an actual insurance plan for the foreign key constraint
        $insurancePlan = \App\Models\InsurancePlan::factory()->create();
        $oldValue = 5.00;
        $newValue = 10.00;

        $log = PricingChangeLog::logChange(
            PricingChangeLog::TYPE_LAB,
            42,
            'LAB-CBC',
            PricingChangeLog::FIELD_COPAY,
            $insurancePlan->id,
            $oldValue,
            $newValue,
            $this->user->id
        );

        expect($log->insurance_plan_id)->toBe($insurancePlan->id);
        expect($log->field_changed)->toBe(PricingChangeLog::FIELD_COPAY);
        expect($log->insurancePlan->id)->toBe($insurancePlan->id);
    });

    it('allows null old_value for new items', function () {
        $log = PricingChangeLog::logChange(
            PricingChangeLog::TYPE_CONSULTATION,
            1,
            'CONS-OPD',
            PricingChangeLog::FIELD_CASH_PRICE,
            null,
            null,
            50.00,
            $this->user->id
        );

        expect($log->old_value)->toBeNull();
        expect((float) $log->new_value)->toBe(50.00);
    });

    it('records the correct user who made the change', function () {
        $anotherUser = User::factory()->create();

        $log1 = PricingChangeLog::logChange(
            PricingChangeLog::TYPE_DRUG,
            1,
            'DRG-001',
            PricingChangeLog::FIELD_CASH_PRICE,
            null,
            10.00,
            15.00,
            $this->user->id
        );

        $log2 = PricingChangeLog::logChange(
            PricingChangeLog::TYPE_DRUG,
            1,
            'DRG-001',
            PricingChangeLog::FIELD_CASH_PRICE,
            null,
            15.00,
            20.00,
            $anotherUser->id
        );

        expect($log1->changed_by)->toBe($this->user->id);
        expect($log2->changed_by)->toBe($anotherUser->id);
        expect($log1->changedByUser->id)->toBe($this->user->id);
        expect($log2->changedByUser->id)->toBe($anotherUser->id);
    });
});

/**
 * Property-based tests using random value generation
 */
describe('Property 13: Audit log captures all price changes - Property-Based', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
    });

    it('persists any valid randomly generated price change', function () {
        // Create insurance plans for foreign key constraint
        $insurancePlans = \App\Models\InsurancePlan::factory()->count(5)->create();
        $planIds = $insurancePlans->pluck('id')->toArray();

        $itemTypes = [
            PricingChangeLog::TYPE_DRUG,
            PricingChangeLog::TYPE_LAB,
            PricingChangeLog::TYPE_CONSULTATION,
            PricingChangeLog::TYPE_PROCEDURE,
        ];

        $fieldTypes = [
            PricingChangeLog::FIELD_CASH_PRICE,
            PricingChangeLog::FIELD_COPAY,
            PricingChangeLog::FIELD_COVERAGE,
            PricingChangeLog::FIELD_TARIFF,
        ];

        // Run 100 iterations as per design document
        for ($i = 0; $i < 100; $i++) {
            $itemType = $itemTypes[array_rand($itemTypes)];
            $itemId = rand(1, 10000);
            $itemCode = strtoupper(fake()->bothify('???-####'));
            $fieldChanged = $fieldTypes[array_rand($fieldTypes)];
            // Use null for cash price, or a valid plan ID for other fields
            $insurancePlanId = $fieldChanged !== PricingChangeLog::FIELD_CASH_PRICE
                ? $planIds[array_rand($planIds)]
                : null;
            $oldValue = round(rand(0, 100000) / 100, 2);
            $newValue = round(rand(0, 100000) / 100, 2);

            $log = PricingChangeLog::logChange(
                $itemType,
                $itemId,
                $itemCode,
                $fieldChanged,
                $insurancePlanId,
                $oldValue,
                $newValue,
                $this->user->id
            );

            // Verify all values are correctly persisted
            expect($log->item_type)->toBe($itemType);
            expect($log->item_id)->toBe($itemId);
            expect($log->item_code)->toBe($itemCode);
            expect($log->field_changed)->toBe($fieldChanged);
            expect($log->insurance_plan_id)->toBe($insurancePlanId);
            expect((float) $log->old_value)->toBe($oldValue);
            expect((float) $log->new_value)->toBe($newValue);
            expect($log->changed_by)->toBe($this->user->id);

            // Verify we can retrieve it from database
            $retrieved = PricingChangeLog::find($log->id);
            expect($retrieved)->not->toBeNull();
            expect($retrieved->item_type)->toBe($itemType);
            expect((float) $retrieved->new_value)->toBe($newValue);
        }
    });

    it('correctly filters by item using scope', function () {
        // Create logs for different items
        for ($i = 0; $i < 20; $i++) {
            PricingChangeLog::factory()->create([
                'item_type' => PricingChangeLog::TYPE_DRUG,
                'item_id' => $i % 5 + 1, // Creates items 1-5
                'changed_by' => $this->user->id,
            ]);
        }

        // Test filtering by specific item
        $targetItemId = 3;
        $filtered = PricingChangeLog::forItem(PricingChangeLog::TYPE_DRUG, $targetItemId)->get();

        expect($filtered->count())->toBe(4); // 20 / 5 = 4 logs per item
        $filtered->each(function ($log) use ($targetItemId) {
            expect($log->item_type)->toBe(PricingChangeLog::TYPE_DRUG);
            expect($log->item_id)->toBe($targetItemId);
        });
    });

    it('correctly filters by date range using scope', function () {
        // Create logs at different times
        $oldLog = PricingChangeLog::factory()->create([
            'changed_by' => $this->user->id,
            'created_at' => now()->subDays(10),
        ]);

        $recentLog = PricingChangeLog::factory()->create([
            'changed_by' => $this->user->id,
            'created_at' => now()->subDays(2),
        ]);

        $todayLog = PricingChangeLog::factory()->create([
            'changed_by' => $this->user->id,
            'created_at' => now(),
        ]);

        // Filter last 5 days
        $filtered = PricingChangeLog::inDateRange(
            now()->subDays(5)->toDateTimeString(),
            now()->toDateTimeString()
        )->get();

        expect($filtered->count())->toBe(2);
        expect($filtered->pluck('id')->toArray())->toContain($recentLog->id);
        expect($filtered->pluck('id')->toArray())->toContain($todayLog->id);
        expect($filtered->pluck('id')->toArray())->not->toContain($oldLog->id);
    });

    it('correctly filters cash price changes using scope', function () {
        // Create mixed logs
        PricingChangeLog::factory()->count(5)->cashPrice()->create([
            'changed_by' => $this->user->id,
        ]);
        PricingChangeLog::factory()->count(3)->copay()->create([
            'changed_by' => $this->user->id,
        ]);

        $cashPriceLogs = PricingChangeLog::cashPriceChanges()->get();
        $copayLogs = PricingChangeLog::copayChanges()->get();

        expect($cashPriceLogs->count())->toBe(5);
        expect($copayLogs->count())->toBe(3);

        $cashPriceLogs->each(function ($log) {
            expect($log->field_changed)->toBe(PricingChangeLog::FIELD_CASH_PRICE);
        });
    });
});
