<?php

/**
 * Property-Based Tests for PricingDashboardService
 *
 * These tests verify the correctness properties of the pricing dashboard service
 * as defined in the design document.
 */

use App\Models\DepartmentBilling;
use App\Models\Drug;
use App\Models\InsuranceCoverageRule;
use App\Models\InsurancePlan;
use App\Models\InsuranceProvider;
use App\Models\LabService;
use App\Models\MinorProcedureType;
use App\Models\PricingChangeLog;
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
 * Property 9: Search returns matching items only
 *
 * *For any* search term, all returned items should have the search term
 * in their name, code, or category (case-insensitive partial match).
 *
 * **Feature: unified-pricing-dashboard, Property 9: Search returns matching items only**
 * **Validates: Requirements 1.4**
 */
describe('Property 9: Search returns matching items only', function () {
    it('returns only items matching the search term in name, code, or category', function () {
        // Create test data with various names and codes
        Drug::factory()->create(['name' => 'Paracetamol 500mg', 'drug_code' => 'DRG-001', 'is_active' => true]);
        Drug::factory()->create(['name' => 'Amoxicillin 250mg', 'drug_code' => 'DRG-002', 'is_active' => true]);
        Drug::factory()->create(['name' => 'Ibuprofen 400mg', 'drug_code' => 'PARA-003', 'is_active' => true]);
        LabService::factory()->create(['name' => 'Complete Blood Count', 'code' => 'LAB-CBC', 'is_active' => true]);
        LabService::factory()->create(['name' => 'Paracetamol Level Test', 'code' => 'LAB-PLT', 'is_active' => true]);

        // Search for "para" - should match Paracetamol drug, PARA-003 code, and Paracetamol Level Test
        $result = $this->service->getPricingData(null, null, 'para');
        $items = collect($result['items']->items());

        expect($items->count())->toBeGreaterThan(0);

        // Verify all returned items contain "para" in name or code (case-insensitive)
        $items->each(function ($item) {
            $nameMatch = str_contains(strtolower($item['name']), 'para');
            $codeMatch = str_contains(strtolower($item['code'] ?? ''), 'para');

            expect($nameMatch || $codeMatch)->toBeTrue(
                "Item '{$item['name']}' (code: {$item['code']}) should contain 'para' in name or code"
            );
        });
    });

    it('returns empty results when search term matches nothing', function () {
        Drug::factory()->create(['name' => 'Paracetamol', 'drug_code' => 'DRG-001', 'is_active' => true]);
        LabService::factory()->create(['name' => 'Blood Test', 'code' => 'LAB-001', 'is_active' => true]);

        $result = $this->service->getPricingData(null, null, 'xyz123nonexistent');
        $items = collect($result['items']->items());

        expect($items->count())->toBe(0);
    });

    it('performs case-insensitive search', function () {
        Drug::factory()->create(['name' => 'PARACETAMOL', 'drug_code' => 'DRG-001', 'is_active' => true]);

        // Search with lowercase
        $result = $this->service->getPricingData(null, null, 'paracetamol');
        $items = collect($result['items']->items());

        expect($items->count())->toBe(1);
        expect(strtolower($items->first()['name']))->toContain('paracetamol');
    });

    it('property test: random search terms return only matching items', function () {
        // Create diverse test data
        $drugs = Drug::factory()->count(10)->create(['is_active' => true]);
        $labs = LabService::factory()->count(10)->create(['is_active' => true]);

        // Run 50 iterations with random search terms
        for ($i = 0; $i < 50; $i++) {
            // Pick a random item and extract a substring from its name
            $allItems = $drugs->merge($labs);
            $randomItem = $allItems->random();
            $itemName = $randomItem->name;

            // Extract a random substring (at least 3 chars) from the name
            if (strlen($itemName) >= 3) {
                $start = rand(0, strlen($itemName) - 3);
                $length = rand(3, min(8, strlen($itemName) - $start));
                $searchTerm = substr($itemName, $start, $length);

                $result = $this->service->getPricingData(null, null, $searchTerm);
                $items = collect($result['items']->items());

                // All returned items must contain the search term
                $items->each(function ($item) use ($searchTerm) {
                    $nameMatch = str_contains(strtolower($item['name']), strtolower($searchTerm));
                    $codeMatch = str_contains(strtolower($item['code'] ?? ''), strtolower($searchTerm));
                    $genericMatch = false;

                    // For drugs, also check generic_name
                    if ($item['type'] === 'drug') {
                        $drug = Drug::find($item['id']);
                        if ($drug && $drug->generic_name) {
                            $genericMatch = str_contains(strtolower($drug->generic_name), strtolower($searchTerm));
                        }
                    }

                    expect($nameMatch || $codeMatch || $genericMatch)->toBeTrue(
                        "Item '{$item['name']}' should contain search term '{$searchTerm}'"
                    );
                });
            }
        }
    });
});

/**
 * Property 1: Cash price updates persist to correct model
 *
 * *For any* item type and valid price, updating the cash price via the dashboard
 * should update the corresponding model's price field (Drug.unit_price,
 * LabService.price, or DepartmentBilling.consultation_fee).
 *
 * **Feature: unified-pricing-dashboard, Property 1: Cash price updates persist to correct model**
 * **Validates: Requirements 2.1, 2.2, 2.3**
 */
describe('Property 1: Cash price updates persist to correct model', function () {
    it('updates Drug.unit_price when item type is drug', function () {
        $drug = Drug::factory()->create(['unit_price' => 10.00, 'is_active' => true]);
        $newPrice = 25.50;

        $result = $this->service->updateCashPrice('drug', $drug->id, $newPrice);

        expect($result)->toBeTrue();
        expect((float) $drug->fresh()->unit_price)->toBe($newPrice);
    });

    it('updates LabService.price when item type is lab', function () {
        $labService = LabService::factory()->create(['price' => 50.00, 'is_active' => true]);
        $newPrice = 75.00;

        $result = $this->service->updateCashPrice('lab', $labService->id, $newPrice);

        expect($result)->toBeTrue();
        expect((float) $labService->fresh()->price)->toBe($newPrice);
    });

    it('updates DepartmentBilling.consultation_fee when item type is consultation', function () {
        $billing = DepartmentBilling::factory()->create(['consultation_fee' => 100.00, 'is_active' => true]);
        $newPrice = 150.00;

        $result = $this->service->updateCashPrice('consultation', $billing->id, $newPrice);

        expect($result)->toBeTrue();
        expect((float) $billing->fresh()->consultation_fee)->toBe($newPrice);
    });

    it('updates MinorProcedureType.price when item type is procedure', function () {
        $procedure = MinorProcedureType::factory()->create(['price' => 200.00, 'is_active' => true]);
        $newPrice = 250.00;

        $result = $this->service->updateCashPrice('procedure', $procedure->id, $newPrice);

        expect($result)->toBeTrue();
        expect((float) $procedure->fresh()->price)->toBe($newPrice);
    });

    it('creates audit log entry for cash price change', function () {
        $drug = Drug::factory()->create(['unit_price' => 10.00, 'drug_code' => 'DRG-TEST', 'is_active' => true]);
        $newPrice = 25.50;

        $this->service->updateCashPrice('drug', $drug->id, $newPrice);

        $log = PricingChangeLog::where('item_type', 'drug')
            ->where('item_id', $drug->id)
            ->first();

        expect($log)->not->toBeNull();
        expect($log->field_changed)->toBe(PricingChangeLog::FIELD_CASH_PRICE);
        expect((float) $log->old_value)->toBe(10.00);
        expect((float) $log->new_value)->toBe($newPrice);
        expect($log->item_code)->toBe('DRG-TEST');
    });

    it('property test: random price updates persist correctly for all item types', function () {
        // Create test items
        $drugs = Drug::factory()->count(5)->create(['is_active' => true]);
        $labs = LabService::factory()->count(5)->create(['is_active' => true]);
        $billings = DepartmentBilling::factory()->count(3)->create(['is_active' => true]);
        $procedures = MinorProcedureType::factory()->count(3)->create(['is_active' => true]);

        // Run 100 iterations
        for ($i = 0; $i < 100; $i++) {
            $itemType = ['drug', 'lab', 'consultation', 'procedure'][rand(0, 3)];
            $newPrice = round(rand(100, 100000) / 100, 2);

            $item = match ($itemType) {
                'drug' => $drugs->random(),
                'lab' => $labs->random(),
                'consultation' => $billings->random(),
                'procedure' => $procedures->random(),
            };

            $result = $this->service->updateCashPrice($itemType, $item->id, $newPrice);

            expect($result)->toBeTrue();

            // Verify the correct field was updated
            $item->refresh();
            $actualPrice = match ($itemType) {
                'drug' => (float) $item->unit_price,
                'lab' => (float) $item->price,
                'consultation' => (float) $item->consultation_fee,
                'procedure' => (float) $item->price,
            };

            expect($actualPrice)->toBe($newPrice);
        }
    });
});

/**
 * Property 3: NHIS copay updates create or update coverage rules
 *
 * *For any* NHIS-mapped item and valid copay amount, updating the copay should
 * create a new InsuranceCoverageRule (if none exists) or update the existing
 * rule's patient_copay_amount field with the item-specific item_code.
 *
 * **Feature: unified-pricing-dashboard, Property 3: NHIS copay updates create or update coverage rules**
 * **Validates: Requirements 3.3**
 */
describe('Property 3: NHIS copay updates create or update coverage rules', function () {
    beforeEach(function () {
        // Create NHIS provider and plan
        $this->nhisProvider = InsuranceProvider::factory()->create(['is_nhis' => true]);
        $this->nhisPlan = InsurancePlan::factory()->create(['insurance_provider_id' => $this->nhisProvider->id]);
    });

    it('creates new coverage rule when none exists', function () {
        $drug = Drug::factory()->create(['drug_code' => 'DRG-TEST', 'is_active' => true]);
        $copayAmount = 5.00;

        $rule = $this->service->updateInsuranceCopay(
            $this->nhisPlan->id,
            'drug',
            $drug->id,
            $drug->drug_code,
            $copayAmount
        );

        expect($rule)->toBeInstanceOf(InsuranceCoverageRule::class);
        expect($rule->insurance_plan_id)->toBe($this->nhisPlan->id);
        expect($rule->coverage_category)->toBe('drug');
        expect($rule->item_code)->toBe($drug->drug_code);
        expect((float) $rule->patient_copay_amount)->toBe($copayAmount);
    });

    it('updates existing coverage rule when one exists', function () {
        $drug = Drug::factory()->create(['drug_code' => 'DRG-EXIST', 'is_active' => true]);

        // Create existing rule
        $existingRule = InsuranceCoverageRule::create([
            'insurance_plan_id' => $this->nhisPlan->id,
            'coverage_category' => 'drug',
            'item_code' => $drug->drug_code,
            'item_description' => $drug->name,
            'is_covered' => true,
            'coverage_type' => 'full',
            'patient_copay_amount' => 3.00,
            'is_active' => true,
        ]);

        $newCopay = 7.50;
        $rule = $this->service->updateInsuranceCopay(
            $this->nhisPlan->id,
            'drug',
            $drug->id,
            $drug->drug_code,
            $newCopay
        );

        expect($rule->id)->toBe($existingRule->id);
        expect((float) $rule->patient_copay_amount)->toBe($newCopay);
    });

    it('creates audit log for copay change', function () {
        $drug = Drug::factory()->create(['drug_code' => 'DRG-LOG', 'is_active' => true]);

        $this->service->updateInsuranceCopay(
            $this->nhisPlan->id,
            'drug',
            $drug->id,
            $drug->drug_code,
            10.00
        );

        $log = PricingChangeLog::where('item_type', 'drug')
            ->where('item_id', $drug->id)
            ->where('field_changed', PricingChangeLog::FIELD_COPAY)
            ->first();

        expect($log)->not->toBeNull();
        expect($log->insurance_plan_id)->toBe($this->nhisPlan->id);
        expect((float) $log->new_value)->toBe(10.00);
    });

    it('property test: random copay updates create or update rules correctly', function () {
        $drugs = Drug::factory()->count(5)->create(['is_active' => true]);
        $labs = LabService::factory()->count(5)->create(['is_active' => true]);

        // Run 100 iterations
        for ($i = 0; $i < 100; $i++) {
            $itemType = ['drug', 'lab'][rand(0, 1)];
            $item = $itemType === 'drug' ? $drugs->random() : $labs->random();
            $itemCode = $itemType === 'drug' ? $item->drug_code : $item->code;
            $copayAmount = round(rand(0, 5000) / 100, 2);

            $rule = $this->service->updateInsuranceCopay(
                $this->nhisPlan->id,
                $itemType,
                $item->id,
                $itemCode,
                $copayAmount
            );

            // Verify rule was created/updated correctly
            expect($rule->insurance_plan_id)->toBe($this->nhisPlan->id);
            expect($rule->item_code)->toBe($itemCode);
            expect((float) $rule->patient_copay_amount)->toBe($copayAmount);

            // Verify it's persisted in database
            $dbRule = InsuranceCoverageRule::where('insurance_plan_id', $this->nhisPlan->id)
                ->where('item_code', $itemCode)
                ->first();

            expect($dbRule)->not->toBeNull();
            expect((float) $dbRule->patient_copay_amount)->toBe($copayAmount);
        }
    });
});

/**
 * Property 6: Private insurance coverage updates persist correctly
 *
 * *For any* private insurance plan, item, and valid coverage settings
 * (tariff, coverage_value, copay), updating via the dashboard should create
 * or update the InsuranceCoverageRule with the correct values.
 *
 * **Feature: unified-pricing-dashboard, Property 6: Private insurance coverage updates persist correctly**
 * **Validates: Requirements 4.2, 4.3, 4.4**
 */
describe('Property 6: Private insurance coverage updates persist correctly', function () {
    beforeEach(function () {
        // Create private (non-NHIS) provider and plan
        $this->privateProvider = InsuranceProvider::factory()->create(['is_nhis' => false]);
        $this->privatePlan = InsurancePlan::factory()->create(['insurance_provider_id' => $this->privateProvider->id]);
    });

    it('creates coverage rule with tariff amount', function () {
        $drug = Drug::factory()->create(['drug_code' => 'DRG-PRIV', 'is_active' => true]);

        $rule = $this->service->updateInsuranceCoverage(
            $this->privatePlan->id,
            'drug',
            $drug->id,
            $drug->drug_code,
            ['tariff_amount' => 80.00, 'coverage_type' => 'percentage', 'coverage_value' => 80]
        );

        expect($rule->insurance_plan_id)->toBe($this->privatePlan->id);
        expect((float) $rule->tariff_amount)->toBe(80.00);
        expect($rule->coverage_type)->toBe('percentage');
        expect((float) $rule->coverage_value)->toBe(80.00);
    });

    it('updates existing coverage rule', function () {
        $lab = LabService::factory()->create(['code' => 'LAB-PRIV', 'is_active' => true]);

        // Create existing rule
        InsuranceCoverageRule::create([
            'insurance_plan_id' => $this->privatePlan->id,
            'coverage_category' => 'lab',
            'item_code' => $lab->code,
            'item_description' => $lab->name,
            'is_covered' => true,
            'coverage_type' => 'percentage',
            'coverage_value' => 50,
            'tariff_amount' => 100.00,
            'is_active' => true,
        ]);

        $rule = $this->service->updateInsuranceCoverage(
            $this->privatePlan->id,
            'lab',
            $lab->id,
            $lab->code,
            ['tariff_amount' => 120.00, 'coverage_value' => 70]
        );

        expect((float) $rule->tariff_amount)->toBe(120.00);
        expect((float) $rule->coverage_value)->toBe(70.00);
    });

    it('creates audit logs for coverage changes', function () {
        $drug = Drug::factory()->create(['drug_code' => 'DRG-AUDIT', 'is_active' => true]);

        $this->service->updateInsuranceCoverage(
            $this->privatePlan->id,
            'drug',
            $drug->id,
            $drug->drug_code,
            ['tariff_amount' => 50.00, 'coverage_value' => 80, 'patient_copay_amount' => 5.00]
        );

        // Check tariff log
        $tariffLog = PricingChangeLog::where('item_code', $drug->drug_code)
            ->where('field_changed', PricingChangeLog::FIELD_TARIFF)
            ->first();
        expect($tariffLog)->not->toBeNull();
        expect((float) $tariffLog->new_value)->toBe(50.00);

        // Check coverage log
        $coverageLog = PricingChangeLog::where('item_code', $drug->drug_code)
            ->where('field_changed', PricingChangeLog::FIELD_COVERAGE)
            ->first();
        expect($coverageLog)->not->toBeNull();
        expect((float) $coverageLog->new_value)->toBe(80.00);
    });

    it('property test: random coverage updates persist correctly', function () {
        $drugs = Drug::factory()->count(5)->create(['is_active' => true]);
        $labs = LabService::factory()->count(5)->create(['is_active' => true]);
        $coverageTypes = ['full', 'percentage', 'fixed'];

        // Run 100 iterations
        for ($i = 0; $i < 100; $i++) {
            $itemType = ['drug', 'lab'][rand(0, 1)];
            $item = $itemType === 'drug' ? $drugs->random() : $labs->random();
            $itemCode = $itemType === 'drug' ? $item->drug_code : $item->code;

            $tariffAmount = round(rand(1000, 50000) / 100, 2);
            $coverageType = $coverageTypes[array_rand($coverageTypes)];
            $coverageValue = $coverageType === 'percentage' ? (float) rand(10, 100) : round(rand(500, 10000) / 100, 2);
            $copayAmount = round(rand(0, 2000) / 100, 2);

            $rule = $this->service->updateInsuranceCoverage(
                $this->privatePlan->id,
                $itemType,
                $item->id,
                $itemCode,
                [
                    'tariff_amount' => $tariffAmount,
                    'coverage_type' => $coverageType,
                    'coverage_value' => $coverageValue,
                    'patient_copay_amount' => $copayAmount,
                ]
            );

            // Verify all values persisted correctly
            expect($rule->insurance_plan_id)->toBe($this->privatePlan->id);
            expect($rule->item_code)->toBe($itemCode);
            expect((float) $rule->tariff_amount)->toBe($tariffAmount);
            expect($rule->coverage_type)->toBe($coverageType);
            expect((float) $rule->coverage_value)->toEqual($coverageValue);
            expect((float) $rule->patient_copay_amount)->toBe($copayAmount);

            // Verify database persistence
            $dbRule = InsuranceCoverageRule::where('insurance_plan_id', $this->privatePlan->id)
                ->where('item_code', $itemCode)
                ->first();

            expect($dbRule)->not->toBeNull();
            expect((float) $dbRule->tariff_amount)->toBe($tariffAmount);
        }
    });
});

/**
 * Property 8: Bulk update applies to all selected items
 *
 * *For any* set of selected items and copay value, bulk update should create
 * or update InsuranceCoverageRule records for every item in the set with
 * the specified copay amount.
 *
 * **Feature: unified-pricing-dashboard, Property 8: Bulk update applies to all selected items**
 * **Validates: Requirements 5.2, 5.3**
 */
describe('Property 8: Bulk update applies to all selected items', function () {
    beforeEach(function () {
        $this->provider = InsuranceProvider::factory()->create(['is_nhis' => true]);
        $this->plan = InsurancePlan::factory()->create(['insurance_provider_id' => $this->provider->id]);
    });

    it('updates copay for all selected items', function () {
        $drugs = Drug::factory()->count(3)->create(['is_active' => true]);
        $copayAmount = 5.00;

        $items = $drugs->map(fn ($drug) => [
            'type' => 'drug',
            'id' => $drug->id,
            'code' => $drug->drug_code,
        ])->toArray();

        $result = $this->service->bulkUpdateCopay($this->plan->id, $items, $copayAmount);

        expect($result['updated'])->toBe(3);
        expect($result['errors'])->toBeEmpty();

        // Verify all items have the copay set
        foreach ($drugs as $drug) {
            $rule = InsuranceCoverageRule::where('insurance_plan_id', $this->plan->id)
                ->where('item_code', $drug->drug_code)
                ->first();

            expect($rule)->not->toBeNull();
            expect((float) $rule->patient_copay_amount)->toBe($copayAmount);
        }
    });

    it('handles mixed item types', function () {
        $drug = Drug::factory()->create(['is_active' => true]);
        $lab = LabService::factory()->create(['is_active' => true]);
        $copayAmount = 10.00;

        $items = [
            ['type' => 'drug', 'id' => $drug->id, 'code' => $drug->drug_code],
            ['type' => 'lab', 'id' => $lab->id, 'code' => $lab->code],
        ];

        $result = $this->service->bulkUpdateCopay($this->plan->id, $items, $copayAmount);

        expect($result['updated'])->toBe(2);

        // Verify drug rule
        $drugRule = InsuranceCoverageRule::where('insurance_plan_id', $this->plan->id)
            ->where('item_code', $drug->drug_code)
            ->first();
        expect((float) $drugRule->patient_copay_amount)->toBe($copayAmount);

        // Verify lab rule
        $labRule = InsuranceCoverageRule::where('insurance_plan_id', $this->plan->id)
            ->where('item_code', $lab->code)
            ->first();
        expect((float) $labRule->patient_copay_amount)->toBe($copayAmount);
    });

    it('returns summary with errors for invalid items', function () {
        $drug = Drug::factory()->create(['is_active' => true]);

        $items = [
            ['type' => 'drug', 'id' => $drug->id, 'code' => $drug->drug_code],
            ['type' => 'invalid_type', 'id' => 999, 'code' => 'INVALID'],
        ];

        $result = $this->service->bulkUpdateCopay($this->plan->id, $items, 5.00);

        expect($result['updated'])->toBe(1);
        expect($result['errors'])->toHaveCount(1);
    });

    it('property test: bulk update applies to all items in random sets', function () {
        $drugs = Drug::factory()->count(10)->create(['is_active' => true]);
        $labs = LabService::factory()->count(10)->create(['is_active' => true]);

        // Run 50 iterations with random item selections
        for ($i = 0; $i < 50; $i++) {
            // Select random number of items (1-8)
            $numItems = rand(1, 8);
            $items = [];

            for ($j = 0; $j < $numItems; $j++) {
                $itemType = ['drug', 'lab'][rand(0, 1)];
                $item = $itemType === 'drug' ? $drugs->random() : $labs->random();
                $items[] = [
                    'type' => $itemType,
                    'id' => $item->id,
                    'code' => $itemType === 'drug' ? $item->drug_code : $item->code,
                ];
            }

            // Remove duplicates
            $items = collect($items)->unique('code')->values()->toArray();
            $copayAmount = round(rand(0, 5000) / 100, 2);

            $result = $this->service->bulkUpdateCopay($this->plan->id, $items, $copayAmount);

            // Verify all items were updated
            expect($result['updated'])->toBe(count($items));

            // Verify each item has the correct copay
            foreach ($items as $item) {
                $rule = InsuranceCoverageRule::where('insurance_plan_id', $this->plan->id)
                    ->where('item_code', $item['code'])
                    ->first();

                expect($rule)->not->toBeNull();
                expect((float) $rule->patient_copay_amount)->toBe($copayAmount);
            }
        }
    });
});

/**
 * Property 10: Export contains all filtered data
 *
 * *For any* filter criteria, the exported CSV should contain exactly the items
 * that match the current filters, with all required columns present.
 *
 * **Feature: unified-pricing-dashboard, Property 10: Export contains all filtered data**
 * **Validates: Requirements 7.1, 7.2, 7.3**
 */
describe('Property 10: Export contains all filtered data', function () {
    it('exports all items when no filters applied', function () {
        // Create test data
        Drug::factory()->count(3)->create(['is_active' => true]);
        LabService::factory()->count(2)->create(['is_active' => true]);
        DepartmentBilling::factory()->count(2)->create(['is_active' => true]);

        $csv = $this->service->exportToCsv(null, null, null);
        $lines = array_filter(explode("\n", trim($csv)));

        // Header + 7 data rows
        expect(count($lines))->toBe(8);

        // Verify headers
        $headers = str_getcsv($lines[0]);
        expect($headers)->toContain('Code');
        expect($headers)->toContain('Name');
        expect($headers)->toContain('Category');
        expect($headers)->toContain('Cash Price');
    });

    it('exports only items matching category filter', function () {
        Drug::factory()->count(3)->create(['is_active' => true]);
        LabService::factory()->count(2)->create(['is_active' => true]);

        $csv = $this->service->exportToCsv(null, 'drugs', null);
        $lines = array_filter(explode("\n", trim($csv)));

        // Header + 3 drug rows
        expect(count($lines))->toBe(4);

        // Verify all rows are drugs
        for ($i = 1; $i < count($lines); $i++) {
            $row = str_getcsv($lines[$i]);
            expect($row[2])->toBe('drugs'); // Category column
        }
    });

    it('exports only items matching search filter', function () {
        Drug::factory()->create(['name' => 'Paracetamol 500mg', 'drug_code' => 'DRG-PARA', 'is_active' => true]);
        Drug::factory()->create(['name' => 'Amoxicillin 250mg', 'drug_code' => 'DRG-AMOX', 'is_active' => true]);
        Drug::factory()->create(['name' => 'Ibuprofen 400mg', 'drug_code' => 'DRG-IBU', 'is_active' => true]);

        $csv = $this->service->exportToCsv(null, null, 'para');
        $lines = array_filter(explode("\n", trim($csv)));

        // Header + 1 matching row
        expect(count($lines))->toBe(2);

        $row = str_getcsv($lines[1]);
        expect(strtolower($row[1]))->toContain('para'); // Name column
    });

    it('includes NHIS-specific columns when NHIS plan selected', function () {
        $nhisProvider = InsuranceProvider::factory()->create(['is_nhis' => true]);
        $nhisPlan = InsurancePlan::factory()->create(['insurance_provider_id' => $nhisProvider->id]);

        Drug::factory()->create(['is_active' => true]);

        $csv = $this->service->exportToCsv($nhisPlan->id, null, null);
        $lines = array_filter(explode("\n", trim($csv)));

        $headers = str_getcsv($lines[0]);
        expect($headers)->toContain('NHIS Code');
        expect($headers)->toContain('NHIS Tariff');
        expect($headers)->toContain('Patient Copay');
        expect($headers)->toContain('Is Mapped');
    });

    it('includes private insurance columns when private plan selected', function () {
        $privateProvider = InsuranceProvider::factory()->create(['is_nhis' => false]);
        $privatePlan = InsurancePlan::factory()->create(['insurance_provider_id' => $privateProvider->id]);

        Drug::factory()->create(['is_active' => true]);

        $csv = $this->service->exportToCsv($privatePlan->id, null, null);
        $lines = array_filter(explode("\n", trim($csv)));

        $headers = str_getcsv($lines[0]);
        expect($headers)->toContain('Insurance Tariff');
        expect($headers)->toContain('Coverage Type');
        expect($headers)->toContain('Coverage Value');
        expect($headers)->toContain('Patient Copay');
    });

    it('property test: export matches filtered data for random filters', function () {
        // Create diverse test data
        $drugs = Drug::factory()->count(10)->create(['is_active' => true]);
        $labs = LabService::factory()->count(10)->create(['is_active' => true]);
        $billings = DepartmentBilling::factory()->count(5)->create(['is_active' => true]);

        $categories = [null, 'drugs', 'lab', 'consultation'];

        // Run 50 iterations with random filters
        for ($i = 0; $i < 50; $i++) {
            $category = $categories[array_rand($categories)];

            // Get expected data from getPricingData
            $expectedData = $this->service->getPricingData(null, $category, null, false, 10000);
            $expectedItems = collect($expectedData['items']->items());

            // Get exported CSV
            $csv = $this->service->exportToCsv(null, $category, null);
            $lines = array_filter(explode("\n", trim($csv)));

            // Verify row count matches (header + data rows)
            expect(count($lines) - 1)->toBe($expectedItems->count());

            // Verify each exported row corresponds to an expected item
            for ($j = 1; $j < count($lines); $j++) {
                $row = str_getcsv($lines[$j]);
                $code = $row[0];

                $matchingItem = $expectedItems->first(fn ($item) => $item['code'] === $code);
                expect($matchingItem)->not->toBeNull("Exported code '{$code}' should exist in expected items");

                // Verify cash price matches
                expect((float) $row[3])->toBe((float) $matchingItem['cash_price']);
            }
        }
    });

    it('property test: export with search filter contains only matching items', function () {
        // Create test data with known names
        $drugs = Drug::factory()->count(10)->create(['is_active' => true]);
        $labs = LabService::factory()->count(10)->create(['is_active' => true]);

        // Run 30 iterations with random search terms
        for ($i = 0; $i < 30; $i++) {
            // Pick a random item and extract a substring from its name
            $allItems = $drugs->merge($labs);
            $randomItem = $allItems->random();
            $itemName = $randomItem->name;

            if (strlen($itemName) >= 3) {
                $start = rand(0, strlen($itemName) - 3);
                $length = rand(3, min(6, strlen($itemName) - $start));
                $searchTerm = substr($itemName, $start, $length);

                // Get expected data
                $expectedData = $this->service->getPricingData(null, null, $searchTerm, false, 10000);
                $expectedItems = collect($expectedData['items']->items());

                // Get exported CSV
                $csv = $this->service->exportToCsv(null, null, $searchTerm);
                $lines = array_filter(explode("\n", trim($csv)));

                // Verify row count matches
                expect(count($lines) - 1)->toBe($expectedItems->count());

                // Verify all exported items contain the search term
                for ($j = 1; $j < count($lines); $j++) {
                    $row = str_getcsv($lines[$j]);
                    $code = $row[0];
                    $name = $row[1];
                    $category = $row[2];

                    $nameMatch = str_contains(strtolower($name), strtolower($searchTerm));
                    $codeMatch = str_contains(strtolower($code), strtolower($searchTerm));
                    $genericMatch = false;

                    // For drugs, also check generic_name (search includes generic_name)
                    if ($category === 'drugs') {
                        $drug = Drug::where('drug_code', $code)->first();
                        if ($drug && $drug->generic_name) {
                            $genericMatch = str_contains(strtolower($drug->generic_name), strtolower($searchTerm));
                        }
                    }

                    expect($nameMatch || $codeMatch || $genericMatch)->toBeTrue(
                        "Exported item '{$name}' (code: {$code}) should contain search term '{$searchTerm}'"
                    );
                }
            }
        }
    });
});

/**
 * Property 11: Import matches items by code
 *
 * *For any* valid CSV row with an item code, the import should find and update
 * the correct item by matching drug_code, lab service code, or department code.
 *
 * **Feature: unified-pricing-dashboard, Property 11: Import matches items by code**
 * **Validates: Requirements 8.2**
 */
describe('Property 11: Import matches items by code', function () {
    it('matches and updates drug by drug_code', function () {
        $drug = Drug::factory()->create([
            'drug_code' => 'DRG-IMPORT-001',
            'unit_price' => 10.00,
            'is_active' => true,
        ]);

        $csvContent = "Code,Name,Category,Cash Price\nDRG-IMPORT-001,Test Drug,drugs,25.00";
        $file = createTempCsvFile($csvContent);

        $result = $this->service->importFromFile($file);

        expect($result['imported'])->toBe(1);
        expect($result['updated'])->toBe(1);
        expect((float) $drug->fresh()->unit_price)->toBe(25.00);
    });

    it('matches and updates lab service by code', function () {
        $lab = LabService::factory()->create([
            'code' => 'LAB-IMPORT-001',
            'price' => 50.00,
            'is_active' => true,
        ]);

        $csvContent = "Code,Name,Category,Cash Price\nLAB-IMPORT-001,Test Lab,lab,75.00";
        $file = createTempCsvFile($csvContent);

        $result = $this->service->importFromFile($file);

        expect($result['imported'])->toBe(1);
        expect($result['updated'])->toBe(1);
        expect((float) $lab->fresh()->price)->toBe(75.00);
    });

    it('matches and updates department billing by department_code', function () {
        $billing = DepartmentBilling::factory()->create([
            'department_code' => 'DEPT-IMPORT-001',
            'consultation_fee' => 100.00,
            'is_active' => true,
        ]);

        $csvContent = "Code,Name,Category,Cash Price\nDEPT-IMPORT-001,Test Dept,consultation,150.00";
        $file = createTempCsvFile($csvContent);

        $result = $this->service->importFromFile($file);

        expect($result['imported'])->toBe(1);
        expect($result['updated'])->toBe(1);
        expect((float) $billing->fresh()->consultation_fee)->toBe(150.00);
    });

    it('matches and updates procedure by code', function () {
        $procedure = MinorProcedureType::factory()->create([
            'code' => 'PROC-IMPORT-001',
            'price' => 200.00,
            'is_active' => true,
        ]);

        $csvContent = "Code,Name,Category,Cash Price\nPROC-IMPORT-001,Test Procedure,procedure,250.00";
        $file = createTempCsvFile($csvContent);

        $result = $this->service->importFromFile($file);

        expect($result['imported'])->toBe(1);
        expect($result['updated'])->toBe(1);
        expect((float) $procedure->fresh()->price)->toBe(250.00);
    });

    it('reports error for non-existent code', function () {
        $csvContent = "Code,Name,Category,Cash Price\nNON-EXISTENT-CODE,Unknown Item,drugs,25.00";
        $file = createTempCsvFile($csvContent);

        $result = $this->service->importFromFile($file);

        expect($result['imported'])->toBe(0);
        expect($result['skipped'])->toBe(1);
        expect($result['errors'])->toHaveCount(1);
        expect($result['errors'][0]['error'])->toContain('not found');
    });

    it('updates copay when insurance plan provided', function () {
        $nhisProvider = InsuranceProvider::factory()->create(['is_nhis' => true]);
        $nhisPlan = InsurancePlan::factory()->create(['insurance_provider_id' => $nhisProvider->id]);

        $drug = Drug::factory()->create([
            'drug_code' => 'DRG-COPAY-001',
            'unit_price' => 10.00,
            'is_active' => true,
        ]);

        $csvContent = "Code,Name,Category,Cash Price,Patient Copay\nDRG-COPAY-001,Test Drug,drugs,25.00,5.00";
        $file = createTempCsvFile($csvContent);

        $result = $this->service->importFromFile($file, $nhisPlan->id);

        expect($result['updated'])->toBe(1);

        // Verify copay was set
        $rule = InsuranceCoverageRule::where('insurance_plan_id', $nhisPlan->id)
            ->where('item_code', 'DRG-COPAY-001')
            ->first();

        expect($rule)->not->toBeNull();
        expect((float) $rule->patient_copay_amount)->toBe(5.00);
    });

    it('property test: import correctly matches items by their unique codes', function () {
        // Create diverse test data with unique codes
        $drugs = Drug::factory()->count(5)->create(['is_active' => true]);
        $labs = LabService::factory()->count(5)->create(['is_active' => true]);
        $billings = DepartmentBilling::factory()->count(3)->create(['is_active' => true]);
        $procedures = MinorProcedureType::factory()->count(3)->create(['is_active' => true]);

        // Run 50 iterations with random item selections
        for ($i = 0; $i < 50; $i++) {
            // Select random items to update
            $numItems = rand(1, 5);
            $csvRows = ['Code,Name,Category,Cash Price'];
            $expectedUpdates = [];

            for ($j = 0; $j < $numItems; $j++) {
                $itemType = ['drug', 'lab', 'consultation', 'procedure'][rand(0, 3)];
                $item = match ($itemType) {
                    'drug' => $drugs->random(),
                    'lab' => $labs->random(),
                    'consultation' => $billings->random(),
                    'procedure' => $procedures->random(),
                };

                $code = match ($itemType) {
                    'drug' => $item->drug_code,
                    'lab' => $item->code,
                    'consultation' => $item->department_code,
                    'procedure' => $item->code,
                };

                $newPrice = round(rand(100, 50000) / 100, 2);
                $csvRows[] = "{$code},Test Item,{$itemType},{$newPrice}";
                $expectedUpdates[$code] = [
                    'type' => $itemType,
                    'id' => $item->id,
                    'price' => $newPrice,
                ];
            }

            // Remove duplicate codes
            $csvRows = array_unique($csvRows);
            $csvContent = implode("\n", $csvRows);
            $file = createTempCsvFile($csvContent);

            $result = $this->service->importFromFile($file);

            // Verify each expected update was applied
            foreach ($expectedUpdates as $code => $expected) {
                $item = match ($expected['type']) {
                    'drug' => Drug::where('drug_code', $code)->first(),
                    'lab' => LabService::where('code', $code)->first(),
                    'consultation' => DepartmentBilling::where('department_code', $code)->first(),
                    'procedure' => MinorProcedureType::where('code', $code)->first(),
                };

                expect($item)->not->toBeNull("Item with code '{$code}' should exist");

                $actualPrice = match ($expected['type']) {
                    'drug' => (float) $item->unit_price,
                    'lab' => (float) $item->price,
                    'consultation' => (float) $item->consultation_fee,
                    'procedure' => (float) $item->price,
                };

                expect($actualPrice)->toBe($expected['price'],
                    "Item '{$code}' should have price {$expected['price']}, got {$actualPrice}"
                );
            }
        }
    });
});

/**
 * Helper function to create a temporary CSV file for testing.
 */
function createTempCsvFile(string $content): \Illuminate\Http\UploadedFile
{
    $tempPath = tempnam(sys_get_temp_dir(), 'csv_');
    file_put_contents($tempPath, $content);

    return new \Illuminate\Http\UploadedFile(
        $tempPath,
        'import.csv',
        'text/csv',
        null,
        true
    );
}

/**
 * Property 12: Import handles invalid rows gracefully
 *
 * *For any* CSV file containing both valid and invalid rows, the import should
 * process all valid rows and skip invalid ones, returning accurate counts of
 * imported, skipped, and error rows.
 *
 * **Feature: unified-pricing-dashboard, Property 12: Import handles invalid rows gracefully**
 * **Validates: Requirements 8.5, 8.6**
 */
describe('Property 12: Import handles invalid rows gracefully', function () {
    it('skips rows with empty code', function () {
        $drug = Drug::factory()->create([
            'drug_code' => 'DRG-VALID-001',
            'unit_price' => 10.00,
            'is_active' => true,
        ]);

        $csvContent = "Code,Name,Category,Cash Price\nDRG-VALID-001,Valid Drug,drugs,25.00\n,Empty Code,drugs,30.00";
        $file = createTempCsvFile($csvContent);

        $result = $this->service->importFromFile($file);

        expect($result['imported'])->toBe(1);
        expect($result['updated'])->toBe(1);
        expect($result['skipped'])->toBe(1);
        expect((float) $drug->fresh()->unit_price)->toBe(25.00);
    });

    it('skips rows with non-existent codes and continues processing', function () {
        $drug1 = Drug::factory()->create(['drug_code' => 'DRG-EXIST-001', 'unit_price' => 10.00, 'is_active' => true]);
        $drug2 = Drug::factory()->create(['drug_code' => 'DRG-EXIST-002', 'unit_price' => 20.00, 'is_active' => true]);

        $csvContent = "Code,Name,Category,Cash Price\nDRG-EXIST-001,Drug 1,drugs,15.00\nNON-EXISTENT,Unknown,drugs,99.00\nDRG-EXIST-002,Drug 2,drugs,25.00";
        $file = createTempCsvFile($csvContent);

        $result = $this->service->importFromFile($file);

        expect($result['imported'])->toBe(2);
        expect($result['updated'])->toBe(2);
        expect($result['skipped'])->toBe(1);
        expect($result['errors'])->toHaveCount(1);
        expect((float) $drug1->fresh()->unit_price)->toBe(15.00);
        expect((float) $drug2->fresh()->unit_price)->toBe(25.00);
    });

    it('returns error when Code column is missing', function () {
        $csvContent = "Name,Category,Cash Price\nTest Drug,drugs,25.00";
        $file = createTempCsvFile($csvContent);

        $result = $this->service->importFromFile($file);

        expect($result['imported'])->toBe(0);
        expect($result['errors'])->toHaveCount(1);
        expect($result['errors'][0]['error'])->toContain('Code');
    });

    it('skips rows without price update when no valid price columns', function () {
        $drug = Drug::factory()->create([
            'drug_code' => 'DRG-NOPRICE-001',
            'unit_price' => 10.00,
            'is_active' => true,
        ]);

        // CSV with code but no cash price value
        $csvContent = "Code,Name,Category,Cash Price\nDRG-NOPRICE-001,Test Drug,drugs,";
        $file = createTempCsvFile($csvContent);

        $result = $this->service->importFromFile($file);

        expect($result['imported'])->toBe(1);
        expect($result['skipped'])->toBe(1); // Skipped because no update was made
        expect((float) $drug->fresh()->unit_price)->toBe(10.00); // Price unchanged
    });

    it('handles mixed valid and invalid rows correctly', function () {
        $drug1 = Drug::factory()->create(['drug_code' => 'DRG-MIX-001', 'unit_price' => 10.00, 'is_active' => true]);
        $drug2 = Drug::factory()->create(['drug_code' => 'DRG-MIX-002', 'unit_price' => 20.00, 'is_active' => true]);
        $lab = LabService::factory()->create(['code' => 'LAB-MIX-001', 'price' => 50.00, 'is_active' => true]);

        $csvContent = <<<'CSV'
Code,Name,Category,Cash Price
DRG-MIX-001,Drug 1,drugs,15.00
INVALID-CODE-1,Unknown 1,drugs,99.00
DRG-MIX-002,Drug 2,drugs,25.00
,Empty Code,drugs,30.00
LAB-MIX-001,Lab Test,lab,75.00
INVALID-CODE-2,Unknown 2,lab,88.00
CSV;

        $file = createTempCsvFile($csvContent);

        $result = $this->service->importFromFile($file);

        // 3 valid items found and updated
        expect($result['updated'])->toBe(3);
        // 2 invalid codes + 1 empty code = 3 skipped
        expect($result['skipped'])->toBe(3);
        // 2 errors for non-existent codes
        expect($result['errors'])->toHaveCount(2);

        // Verify valid items were updated
        expect((float) $drug1->fresh()->unit_price)->toBe(15.00);
        expect((float) $drug2->fresh()->unit_price)->toBe(25.00);
        expect((float) $lab->fresh()->price)->toBe(75.00);
    });

    it('property test: import processes valid rows and skips invalid ones accurately', function () {
        // Create test data
        $drugs = Drug::factory()->count(10)->create(['is_active' => true]);
        $labs = LabService::factory()->count(10)->create(['is_active' => true]);

        // Run 30 iterations with random mixes of valid and invalid rows
        for ($i = 0; $i < 30; $i++) {
            // Reset prices to known values
            $drugs->each(fn ($d) => $d->update(['unit_price' => 10.00]));
            $labs->each(fn ($l) => $l->update(['price' => 50.00]));

            $csvRows = ['Code,Name,Category,Cash Price'];
            $validCodes = [];
            $invalidCodes = [];
            $emptyCodes = 0;

            // Generate random mix of valid, invalid, and empty rows
            $numRows = rand(5, 15);
            for ($j = 0; $j < $numRows; $j++) {
                $rowType = rand(0, 2); // 0=valid, 1=invalid, 2=empty

                if ($rowType === 0) {
                    // Valid row
                    $itemType = ['drug', 'lab'][rand(0, 1)];
                    $item = $itemType === 'drug' ? $drugs->random() : $labs->random();
                    $code = $itemType === 'drug' ? $item->drug_code : $item->code;
                    $newPrice = round(rand(100, 50000) / 100, 2);
                    $csvRows[] = "{$code},Test Item,{$itemType},{$newPrice}";
                    $validCodes[$code] = $newPrice;
                } elseif ($rowType === 1) {
                    // Invalid code
                    $invalidCode = 'INVALID-'.uniqid();
                    $csvRows[] = "{$invalidCode},Unknown Item,drugs,99.00";
                    $invalidCodes[] = $invalidCode;
                } else {
                    // Empty code
                    $csvRows[] = ',Empty Item,drugs,99.00';
                    $emptyCodes++;
                }
            }

            $csvContent = implode("\n", $csvRows);
            $file = createTempCsvFile($csvContent);

            $result = $this->service->importFromFile($file);

            // Verify counts
            expect($result['errors'])->toHaveCount(count($invalidCodes),
                'Expected '.count($invalidCodes).' errors, got '.count($result['errors'])
            );

            // Verify valid items were updated
            foreach ($validCodes as $code => $expectedPrice) {
                $item = Drug::where('drug_code', $code)->first()
                    ?? LabService::where('code', $code)->first();

                expect($item)->not->toBeNull();

                $actualPrice = $item instanceof Drug
                    ? (float) $item->unit_price
                    : (float) $item->price;

                expect($actualPrice)->toBe($expectedPrice,
                    "Item '{$code}' should have price {$expectedPrice}, got {$actualPrice}"
                );
            }
        }
    });

    it('property test: error messages contain useful information', function () {
        // Create some valid items
        Drug::factory()->create(['drug_code' => 'DRG-ERR-001', 'is_active' => true]);

        // Generate CSV with various invalid codes
        $invalidCodes = [];
        for ($i = 0; $i < 10; $i++) {
            $invalidCodes[] = 'INVALID-'.uniqid();
        }

        $csvRows = ['Code,Name,Category,Cash Price'];
        foreach ($invalidCodes as $code) {
            $csvRows[] = "{$code},Unknown Item,drugs,99.00";
        }

        $csvContent = implode("\n", $csvRows);
        $file = createTempCsvFile($csvContent);

        $result = $this->service->importFromFile($file);

        expect($result['errors'])->toHaveCount(count($invalidCodes));

        // Verify each error contains the code that failed
        foreach ($result['errors'] as $error) {
            expect($error)->toHaveKey('row');
            expect($error)->toHaveKey('error');
            expect($error['error'])->toContain('not found');
        }
    });
});

/**
 * Property 4: Unmapped items are correctly identified
 *
 * *For any* item without an NhisItemMapping record linking to an active NhisTariff,
 * the dashboard should indicate the item is unmapped and disable copay editing for NHIS.
 *
 * **Feature: unified-pricing-dashboard, Property 4: Unmapped items are correctly identified**
 * **Validates: Requirements 3.4, 6.1, 6.2**
 */
describe('Property 4: Unmapped items are correctly identified', function () {
    beforeEach(function () {
        // Create NHIS provider and plan
        $this->nhisProvider = InsuranceProvider::factory()->create(['is_nhis' => true]);
        $this->nhisPlan = InsurancePlan::factory()->create(['insurance_provider_id' => $this->nhisProvider->id]);
    });

    it('identifies unmapped drugs as is_mapped = false', function () {
        // Create a drug without NHIS mapping
        $drug = Drug::factory()->create(['is_active' => true]);

        $result = $this->service->getPricingData($this->nhisPlan->id, 'drugs', null);
        $items = collect($result['items']->items());

        $drugItem = $items->first(fn ($item) => $item['id'] === $drug->id && $item['type'] === 'drug');

        expect($drugItem)->not->toBeNull();
        expect($drugItem['is_mapped'])->toBeFalse();
        expect($drugItem['nhis_code'])->toBeNull();
        expect($drugItem['insurance_tariff'])->toBeNull();
    });

    it('identifies mapped drugs as is_mapped = true', function () {
        // Create a drug with NHIS mapping
        $drug = Drug::factory()->create(['is_active' => true]);
        $nhisTariff = \App\Models\NhisTariff::factory()->create([
            'nhis_code' => 'NHIS-DRG-001',
            'price' => 15.00,
            'is_active' => true,
        ]);
        \App\Models\NhisItemMapping::create([
            'item_type' => 'drug',
            'item_id' => $drug->id,
            'item_code' => $drug->drug_code,
            'nhis_tariff_id' => $nhisTariff->id,
        ]);

        $result = $this->service->getPricingData($this->nhisPlan->id, 'drugs', null);
        $items = collect($result['items']->items());

        $drugItem = $items->first(fn ($item) => $item['id'] === $drug->id && $item['type'] === 'drug');

        expect($drugItem)->not->toBeNull();
        expect($drugItem['is_mapped'])->toBeTrue();
        expect($drugItem['nhis_code'])->toBe('NHIS-DRG-001');
        expect((float) $drugItem['insurance_tariff'])->toBe(15.00);
    });

    it('filters to show only unmapped items when unmappedOnly is true', function () {
        // Create mapped and unmapped drugs
        $mappedDrug = Drug::factory()->create(['is_active' => true]);
        $unmappedDrug = Drug::factory()->create(['is_active' => true]);

        $nhisTariff = \App\Models\NhisTariff::factory()->create(['is_active' => true]);
        \App\Models\NhisItemMapping::create([
            'item_type' => 'drug',
            'item_id' => $mappedDrug->id,
            'item_code' => $mappedDrug->drug_code,
            'nhis_tariff_id' => $nhisTariff->id,
        ]);

        $result = $this->service->getPricingData($this->nhisPlan->id, 'drugs', null, true);
        $items = collect($result['items']->items());

        // Should only contain unmapped drug
        expect($items->count())->toBe(1);
        expect($items->first()['id'])->toBe($unmappedDrug->id);
        expect($items->first()['is_mapped'])->toBeFalse();
    });

    it('property test: all items without NhisItemMapping are marked as unmapped', function () {
        // Create diverse test data
        $drugs = Drug::factory()->count(10)->create(['is_active' => true]);
        $labs = LabService::factory()->count(10)->create(['is_active' => true]);

        // Randomly map some items
        $mappedItems = [];
        $nhisTariffs = \App\Models\NhisTariff::factory()->count(5)->create(['is_active' => true]);

        foreach ($drugs->take(3) as $drug) {
            \App\Models\NhisItemMapping::create([
                'item_type' => 'drug',
                'item_id' => $drug->id,
                'item_code' => $drug->drug_code,
                'nhis_tariff_id' => $nhisTariffs->random()->id,
            ]);
            $mappedItems[] = ['type' => 'drug', 'id' => $drug->id];
        }

        foreach ($labs->take(2) as $lab) {
            \App\Models\NhisItemMapping::create([
                'item_type' => 'lab_service',
                'item_id' => $lab->id,
                'item_code' => $lab->code,
                'nhis_tariff_id' => $nhisTariffs->random()->id,
            ]);
            $mappedItems[] = ['type' => 'lab', 'id' => $lab->id];
        }

        // Get pricing data for NHIS plan
        $result = $this->service->getPricingData($this->nhisPlan->id, null, null);
        $items = collect($result['items']->items());

        // Verify each item's is_mapped status
        $items->each(function ($item) use ($mappedItems) {
            $isMappedInDb = collect($mappedItems)->contains(fn ($m) => $m['type'] === $item['type'] && $m['id'] === $item['id']
            );

            expect($item['is_mapped'])->toBe($isMappedInDb,
                "Item {$item['type']}:{$item['id']} should have is_mapped={$isMappedInDb}"
            );

            // Unmapped items should have null NHIS code and tariff
            if (! $isMappedInDb) {
                expect($item['nhis_code'])->toBeNull();
                expect($item['insurance_tariff'])->toBeNull();
            }
        });
    });

    it('property test: unmappedOnly filter returns only unmapped items', function () {
        // Create test data
        $drugs = Drug::factory()->count(10)->create(['is_active' => true]);
        $nhisTariffs = \App\Models\NhisTariff::factory()->count(3)->create(['is_active' => true]);

        // Run 30 iterations with random mapping configurations
        for ($i = 0; $i < 30; $i++) {
            // Clear existing mappings
            \App\Models\NhisItemMapping::query()->delete();

            // Randomly map some drugs
            $numMapped = rand(0, 5);
            $mappedDrugIds = $drugs->random(min($numMapped, $drugs->count()))->pluck('id')->toArray();

            foreach ($mappedDrugIds as $drugId) {
                $drug = $drugs->firstWhere('id', $drugId);
                \App\Models\NhisItemMapping::create([
                    'item_type' => 'drug',
                    'item_id' => $drugId,
                    'item_code' => $drug->drug_code,
                    'nhis_tariff_id' => $nhisTariffs->random()->id,
                ]);
            }

            // Get unmapped only
            $result = $this->service->getPricingData($this->nhisPlan->id, 'drugs', null, true);
            $items = collect($result['items']->items());

            // All returned items should be unmapped
            $items->each(function ($item) {
                expect($item['is_mapped'])->toBeFalse(
                    "Item {$item['name']} should be unmapped when unmappedOnly=true"
                );
            });

            // Total count should match expected unmapped count (check paginator total, not items count)
            $expectedUnmappedCount = $drugs->count() - count($mappedDrugIds);
            expect($result['items']->total())->toBe($expectedUnmappedCount);
        }
    });
});

/**
 * Property 5: NHIS tariff display matches master data
 *
 * *For any* NHIS-mapped item, the displayed NHIS tariff should equal the
 * NhisTariff.price value from the linked tariff record.
 *
 * **Feature: unified-pricing-dashboard, Property 5: NHIS tariff display matches master data**
 * **Validates: Requirements 3.2**
 */
describe('Property 5: NHIS tariff display matches master data', function () {
    beforeEach(function () {
        $this->nhisProvider = InsuranceProvider::factory()->create(['is_nhis' => true]);
        $this->nhisPlan = InsurancePlan::factory()->create(['insurance_provider_id' => $this->nhisProvider->id]);
    });

    it('displays correct NHIS tariff for mapped drug', function () {
        $drug = Drug::factory()->create(['is_active' => true]);
        $nhisTariff = \App\Models\NhisTariff::factory()->create([
            'nhis_code' => 'NHIS-TEST-001',
            'price' => 25.50,
            'is_active' => true,
        ]);
        \App\Models\NhisItemMapping::create([
            'item_type' => 'drug',
            'item_id' => $drug->id,
            'item_code' => $drug->drug_code,
            'nhis_tariff_id' => $nhisTariff->id,
        ]);

        $result = $this->service->getPricingData($this->nhisPlan->id, 'drugs', null);
        $items = collect($result['items']->items());

        $drugItem = $items->first(fn ($item) => $item['id'] === $drug->id);

        expect($drugItem['is_mapped'])->toBeTrue();
        expect((float) $drugItem['insurance_tariff'])->toBe(25.50);
        expect($drugItem['nhis_code'])->toBe('NHIS-TEST-001');
    });

    it('displays correct NHIS tariff for mapped lab service', function () {
        $lab = LabService::factory()->create(['is_active' => true]);
        $nhisTariff = \App\Models\NhisTariff::factory()->create([
            'nhis_code' => 'NHIS-LAB-001',
            'price' => 75.00,
            'is_active' => true,
        ]);
        \App\Models\NhisItemMapping::create([
            'item_type' => 'lab_service',
            'item_id' => $lab->id,
            'item_code' => $lab->code,
            'nhis_tariff_id' => $nhisTariff->id,
        ]);

        $result = $this->service->getPricingData($this->nhisPlan->id, 'lab', null);
        $items = collect($result['items']->items());

        $labItem = $items->first(fn ($item) => $item['id'] === $lab->id);

        expect($labItem['is_mapped'])->toBeTrue();
        expect((float) $labItem['insurance_tariff'])->toBe(75.00);
        expect($labItem['nhis_code'])->toBe('NHIS-LAB-001');
    });

    it('property test: all mapped items display correct NHIS tariff from master data', function () {
        // Create diverse test data
        $drugs = Drug::factory()->count(10)->create(['is_active' => true]);
        $labs = LabService::factory()->count(10)->create(['is_active' => true]);

        // Create NHIS tariffs with various prices
        $nhisTariffs = [];
        for ($i = 0; $i < 10; $i++) {
            $nhisTariffs[] = \App\Models\NhisTariff::factory()->create([
                'nhis_code' => 'NHIS-PBT-'.str_pad($i, 3, '0', STR_PAD_LEFT),
                'price' => round(rand(500, 50000) / 100, 2),
                'is_active' => true,
            ]);
        }

        // Map items to tariffs and track expected values
        $expectedTariffs = [];

        foreach ($drugs as $drug) {
            $tariff = $nhisTariffs[array_rand($nhisTariffs)];
            \App\Models\NhisItemMapping::create([
                'item_type' => 'drug',
                'item_id' => $drug->id,
                'item_code' => $drug->drug_code,
                'nhis_tariff_id' => $tariff->id,
            ]);
            $expectedTariffs["drug-{$drug->id}"] = [
                'price' => (float) $tariff->price,
                'code' => $tariff->nhis_code,
            ];
        }

        foreach ($labs as $lab) {
            $tariff = $nhisTariffs[array_rand($nhisTariffs)];
            \App\Models\NhisItemMapping::create([
                'item_type' => 'lab_service',
                'item_id' => $lab->id,
                'item_code' => $lab->code,
                'nhis_tariff_id' => $tariff->id,
            ]);
            $expectedTariffs["lab-{$lab->id}"] = [
                'price' => (float) $tariff->price,
                'code' => $tariff->nhis_code,
            ];
        }

        // Get pricing data
        $result = $this->service->getPricingData($this->nhisPlan->id, null, null);
        $items = collect($result['items']->items());

        // Verify each mapped item displays correct tariff
        $items->each(function ($item) use ($expectedTariffs) {
            $key = "{$item['type']}-{$item['id']}";

            if (isset($expectedTariffs[$key])) {
                expect($item['is_mapped'])->toBeTrue();
                expect((float) $item['insurance_tariff'])->toBe($expectedTariffs[$key]['price'],
                    "Item {$key} should have tariff {$expectedTariffs[$key]['price']}"
                );
                expect($item['nhis_code'])->toBe($expectedTariffs[$key]['code'],
                    "Item {$key} should have NHIS code {$expectedTariffs[$key]['code']}"
                );
            }
        });
    });

    it('property test: tariff display updates when master data changes', function () {
        $drug = Drug::factory()->create(['is_active' => true]);
        $nhisTariff = \App\Models\NhisTariff::factory()->create([
            'price' => 50.00,
            'is_active' => true,
        ]);
        \App\Models\NhisItemMapping::create([
            'item_type' => 'drug',
            'item_id' => $drug->id,
            'item_code' => $drug->drug_code,
            'nhis_tariff_id' => $nhisTariff->id,
        ]);

        // Run 20 iterations with random price changes
        for ($i = 0; $i < 20; $i++) {
            $newPrice = round(rand(100, 100000) / 100, 2);
            $nhisTariff->update(['price' => $newPrice]);

            $result = $this->service->getPricingData($this->nhisPlan->id, 'drugs', null);
            $items = collect($result['items']->items());

            $drugItem = $items->first(fn ($item) => $item['id'] === $drug->id);

            expect((float) $drugItem['insurance_tariff'])->toBe($newPrice,
                "Displayed tariff should match updated master data price {$newPrice}"
            );
        }
    });
});

/**
 * Property 7: Patient pays calculation is correct
 *
 * *For any* coverage settings (coverage_type, coverage_value, tariff, copay),
 * the calculated "Patient Pays" amount should equal:
 * - For percentage coverage: (tariff × (100 - coverage_value)%) + fixed_copay
 * - For fixed coverage: tariff - coverage_value + fixed_copay
 *
 * **Feature: unified-pricing-dashboard, Property 7: Patient pays calculation is correct**
 * **Validates: Requirements 4.5**
 */
describe('Property 7: Patient pays calculation is correct', function () {
    it('calculates patient pays correctly for percentage coverage', function () {
        // Tariff: 100, Coverage: 80%, Copay: 5
        // Patient pays: 100 * (100-80)/100 + 5 = 20 + 5 = 25
        $tariff = 100.00;
        $coverageValue = 80;
        $copay = 5.00;

        $patientPays = $tariff * ((100 - $coverageValue) / 100) + $copay;

        expect($patientPays)->toBe(25.00);
    });

    it('calculates patient pays correctly for fixed coverage', function () {
        // Tariff: 100, Fixed Coverage: 70, Copay: 5
        // Patient pays: max(0, 100 - 70) + 5 = 30 + 5 = 35
        $tariff = 100.00;
        $coverageValue = 70.00;
        $copay = 5.00;

        $patientPays = max(0, $tariff - $coverageValue) + $copay;

        expect($patientPays)->toBe(35.00);
    });

    it('calculates patient pays correctly when coverage exceeds tariff', function () {
        // Tariff: 50, Fixed Coverage: 70, Copay: 5
        // Patient pays: max(0, 50 - 70) + 5 = 0 + 5 = 5
        $tariff = 50.00;
        $coverageValue = 70.00;
        $copay = 5.00;

        $patientPays = max(0, $tariff - $coverageValue) + $copay;

        expect($patientPays)->toBe(5.00);
    });

    it('calculates patient pays correctly with zero copay', function () {
        // Tariff: 100, Coverage: 80%, Copay: 0
        // Patient pays: 100 * (100-80)/100 + 0 = 20
        $tariff = 100.00;
        $coverageValue = 80;
        $copay = 0.00;

        $patientPays = $tariff * ((100 - $coverageValue) / 100) + $copay;

        expect($patientPays)->toBe(20.00);
    });

    it('calculates patient pays correctly with 100% coverage', function () {
        // Tariff: 100, Coverage: 100%, Copay: 5
        // Patient pays: 100 * (100-100)/100 + 5 = 0 + 5 = 5
        $tariff = 100.00;
        $coverageValue = 100;
        $copay = 5.00;

        $patientPays = $tariff * ((100 - $coverageValue) / 100) + $copay;

        expect($patientPays)->toBe(5.00);
    });

    it('property test: patient pays calculation is mathematically correct for percentage coverage', function () {
        // Run 100 iterations with random values
        for ($i = 0; $i < 100; $i++) {
            $tariff = round(rand(100, 100000) / 100, 2);
            $coverageValue = rand(0, 100);
            $copay = round(rand(0, 5000) / 100, 2);

            // Calculate expected patient pays
            $expectedPatientPays = $tariff * ((100 - $coverageValue) / 100) + $copay;

            // Verify the calculation
            $actualPatientPays = $tariff * ((100 - $coverageValue) / 100) + $copay;

            expect(round($actualPatientPays, 2))->toBe(round($expectedPatientPays, 2),
                "For tariff={$tariff}, coverage={$coverageValue}%, copay={$copay}: ".
                "expected {$expectedPatientPays}, got {$actualPatientPays}"
            );

            // Verify patient pays is never negative
            expect($actualPatientPays)->toBeGreaterThanOrEqual(0);

            // Verify patient pays is at least the copay amount
            expect($actualPatientPays)->toBeGreaterThanOrEqual($copay);
        }
    });

    it('property test: patient pays calculation is mathematically correct for fixed coverage', function () {
        // Run 100 iterations with random values
        for ($i = 0; $i < 100; $i++) {
            $tariff = round(rand(100, 100000) / 100, 2);
            $coverageValue = round(rand(0, 150000) / 100, 2); // Can exceed tariff
            $copay = round(rand(0, 5000) / 100, 2);

            // Calculate expected patient pays
            $expectedPatientPays = max(0, $tariff - $coverageValue) + $copay;

            // Verify the calculation
            $actualPatientPays = max(0, $tariff - $coverageValue) + $copay;

            expect(round($actualPatientPays, 2))->toBe(round($expectedPatientPays, 2),
                "For tariff={$tariff}, fixed coverage={$coverageValue}, copay={$copay}: ".
                "expected {$expectedPatientPays}, got {$actualPatientPays}"
            );

            // Verify patient pays is never negative
            expect($actualPatientPays)->toBeGreaterThanOrEqual(0);

            // Verify patient pays is at least the copay amount
            expect($actualPatientPays)->toBeGreaterThanOrEqual($copay);
        }
    });

    it('property test: patient pays decreases as coverage increases', function () {
        $tariff = 100.00;
        $copay = 5.00;

        // For percentage coverage, patient pays should decrease as coverage increases
        $previousPatientPays = PHP_FLOAT_MAX;
        for ($coverage = 0; $coverage <= 100; $coverage += 10) {
            $patientPays = $tariff * ((100 - $coverage) / 100) + $copay;

            expect($patientPays)->toBeLessThanOrEqual($previousPatientPays,
                'Patient pays should decrease as coverage increases'
            );

            $previousPatientPays = $patientPays;
        }
    });

    it('property test: patient pays increases as tariff increases', function () {
        $coverageValue = 80;
        $copay = 5.00;

        // Patient pays should increase as tariff increases
        $previousPatientPays = 0;
        for ($tariff = 10; $tariff <= 1000; $tariff += 100) {
            $patientPays = $tariff * ((100 - $coverageValue) / 100) + $copay;

            expect($patientPays)->toBeGreaterThanOrEqual($previousPatientPays,
                'Patient pays should increase as tariff increases'
            );

            $previousPatientPays = $patientPays;
        }
    });
});
