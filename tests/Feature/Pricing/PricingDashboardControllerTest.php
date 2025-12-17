<?php

/**
 * Property-Based Tests for PricingDashboardController
 *
 * These tests verify the correctness properties of the pricing dashboard controller
 * validation as defined in the design document.
 */

use App\Models\Drug;
use App\Models\InsurancePlan;
use App\Models\InsuranceProvider;
use App\Models\LabService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create permissions
    Permission::create(['name' => 'pricing.view']);
    Permission::create(['name' => 'pricing.edit']);
    Permission::create(['name' => 'billing.manage']);

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['pricing.view', 'pricing.edit']);
    $this->actingAs($this->user);
});

/**
 * Property 2: Price validation rejects invalid values
 *
 * *For any* price value that is zero, negative, or non-numeric, the system
 * should reject the update and return a validation error.
 *
 * **Feature: unified-pricing-dashboard, Property 2: Price validation rejects invalid values**
 * **Validates: Requirements 2.5**
 */
describe('Property 2: Price validation rejects invalid values', function () {
    it('rejects zero price', function () {
        $drug = Drug::factory()->create(['unit_price' => 10.00, 'is_active' => true]);

        $response = $this->put(route('admin.pricing-dashboard.update-cash-price'), [
            'item_type' => 'drug',
            'item_id' => $drug->id,
            'price' => 0,
        ]);

        $response->assertSessionHasErrors('price');
        expect((float) $drug->fresh()->unit_price)->toBe(10.00);
    });

    it('rejects negative price', function () {
        $drug = Drug::factory()->create(['unit_price' => 10.00, 'is_active' => true]);

        $response = $this->put(route('admin.pricing-dashboard.update-cash-price'), [
            'item_type' => 'drug',
            'item_id' => $drug->id,
            'price' => -5.00,
        ]);

        $response->assertSessionHasErrors('price');
        expect((float) $drug->fresh()->unit_price)->toBe(10.00);
    });

    it('rejects non-numeric price', function () {
        $drug = Drug::factory()->create(['unit_price' => 10.00, 'is_active' => true]);

        $response = $this->put(route('admin.pricing-dashboard.update-cash-price'), [
            'item_type' => 'drug',
            'item_id' => $drug->id,
            'price' => 'abc',
        ]);

        $response->assertSessionHasErrors('price');
        expect((float) $drug->fresh()->unit_price)->toBe(10.00);
    });

    it('rejects missing price', function () {
        $drug = Drug::factory()->create(['unit_price' => 10.00, 'is_active' => true]);

        $response = $this->put(route('admin.pricing-dashboard.update-cash-price'), [
            'item_type' => 'drug',
            'item_id' => $drug->id,
        ]);

        $response->assertSessionHasErrors('price');
        expect((float) $drug->fresh()->unit_price)->toBe(10.00);
    });

    it('rejects price exceeding maximum', function () {
        $drug = Drug::factory()->create(['unit_price' => 10.00, 'is_active' => true]);

        $response = $this->put(route('admin.pricing-dashboard.update-cash-price'), [
            'item_type' => 'drug',
            'item_id' => $drug->id,
            'price' => 99999999.99,
        ]);

        $response->assertSessionHasErrors('price');
        expect((float) $drug->fresh()->unit_price)->toBe(10.00);
    });

    it('accepts valid positive price', function () {
        $drug = Drug::factory()->create(['unit_price' => 10.00, 'is_active' => true]);

        $response = $this->put(route('admin.pricing-dashboard.update-cash-price'), [
            'item_type' => 'drug',
            'item_id' => $drug->id,
            'price' => 25.50,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        expect((float) $drug->fresh()->unit_price)->toBe(25.50);
    });

    it('rejects invalid item_type', function () {
        $drug = Drug::factory()->create(['unit_price' => 10.00, 'is_active' => true]);

        $response = $this->put(route('admin.pricing-dashboard.update-cash-price'), [
            'item_type' => 'invalid_type',
            'item_id' => $drug->id,
            'price' => 25.00,
        ]);

        $response->assertSessionHasErrors('item_type');
    });

    it('rejects missing item_type', function () {
        $drug = Drug::factory()->create(['unit_price' => 10.00, 'is_active' => true]);

        $response = $this->put(route('admin.pricing-dashboard.update-cash-price'), [
            'item_id' => $drug->id,
            'price' => 25.00,
        ]);

        $response->assertSessionHasErrors('item_type');
    });

    it('rejects missing item_id', function () {
        $response = $this->put(route('admin.pricing-dashboard.update-cash-price'), [
            'item_type' => 'drug',
            'price' => 25.00,
        ]);

        $response->assertSessionHasErrors('item_id');
    });

    it('rejects non-positive item_id', function () {
        $response = $this->put(route('admin.pricing-dashboard.update-cash-price'), [
            'item_type' => 'drug',
            'item_id' => 0,
            'price' => 25.00,
        ]);

        $response->assertSessionHasErrors('item_id');
    });

    it('property test: random invalid prices are rejected', function () {
        $drug = Drug::factory()->create(['unit_price' => 10.00, 'is_active' => true]);
        $originalPrice = 10.00;

        // Test 100 random invalid price values
        $invalidPrices = [];

        // Generate negative prices
        for ($i = 0; $i < 25; $i++) {
            $invalidPrices[] = -1 * (rand(1, 100000) / 100);
        }

        // Add zero
        $invalidPrices[] = 0;
        $invalidPrices[] = 0.00;

        // Add non-numeric values
        $invalidPrices[] = 'abc';
        $invalidPrices[] = 'twelve';
        $invalidPrices[] = '10.00abc';
        $invalidPrices[] = '';
        $invalidPrices[] = null;

        // Add prices exceeding maximum
        for ($i = 0; $i < 10; $i++) {
            $invalidPrices[] = 10000000 + rand(1, 1000000);
        }

        foreach ($invalidPrices as $invalidPrice) {
            $response = $this->put(route('admin.pricing-dashboard.update-cash-price'), [
                'item_type' => 'drug',
                'item_id' => $drug->id,
                'price' => $invalidPrice,
            ]);

            $response->assertSessionHasErrors('price');
            expect((float) $drug->fresh()->unit_price)->toBe($originalPrice,
                "Price should remain {$originalPrice} after invalid price: ".json_encode($invalidPrice)
            );
        }
    });

    it('property test: random valid prices are accepted', function () {
        $drug = Drug::factory()->create(['unit_price' => 10.00, 'is_active' => true]);

        // Test 100 random valid price values
        for ($i = 0; $i < 100; $i++) {
            // Generate valid price between 0.01 and 9999999.99
            $validPrice = round(rand(1, 999999999) / 100, 2);

            $response = $this->put(route('admin.pricing-dashboard.update-cash-price'), [
                'item_type' => 'drug',
                'item_id' => $drug->id,
                'price' => $validPrice,
            ]);

            $response->assertSessionHasNoErrors();
            expect((float) $drug->fresh()->unit_price)->toBe($validPrice,
                "Price should be updated to {$validPrice}"
            );
        }
    });
});

/**
 * Additional validation tests for other endpoints
 */
describe('Insurance copay validation', function () {
    beforeEach(function () {
        $this->provider = InsuranceProvider::factory()->create(['is_nhis' => true]);
        $this->plan = InsurancePlan::factory()->create(['insurance_provider_id' => $this->provider->id]);
    });

    it('rejects negative copay', function () {
        $drug = Drug::factory()->create(['is_active' => true]);

        $response = $this->put(route('admin.pricing-dashboard.update-insurance-copay'), [
            'plan_id' => $this->plan->id,
            'item_type' => 'drug',
            'item_id' => $drug->id,
            'item_code' => $drug->drug_code,
            'copay' => -5.00,
        ]);

        $response->assertSessionHasErrors('copay');
    });

    it('accepts zero copay', function () {
        $drug = Drug::factory()->create(['is_active' => true]);

        $response = $this->put(route('admin.pricing-dashboard.update-insurance-copay'), [
            'plan_id' => $this->plan->id,
            'item_type' => 'drug',
            'item_id' => $drug->id,
            'item_code' => $drug->drug_code,
            'copay' => 0,
        ]);

        $response->assertSessionHasNoErrors();
    });

    it('rejects invalid plan_id', function () {
        $drug = Drug::factory()->create(['is_active' => true]);

        $response = $this->put(route('admin.pricing-dashboard.update-insurance-copay'), [
            'plan_id' => 99999,
            'item_type' => 'drug',
            'item_id' => $drug->id,
            'item_code' => $drug->drug_code,
            'copay' => 5.00,
        ]);

        $response->assertSessionHasErrors('plan_id');
    });

    it('rejects missing item_code', function () {
        $drug = Drug::factory()->create(['is_active' => true]);

        $response = $this->put(route('admin.pricing-dashboard.update-insurance-copay'), [
            'plan_id' => $this->plan->id,
            'item_type' => 'drug',
            'item_id' => $drug->id,
            'copay' => 5.00,
        ]);

        $response->assertSessionHasErrors('item_code');
    });
});

describe('Bulk update validation', function () {
    beforeEach(function () {
        $this->provider = InsuranceProvider::factory()->create(['is_nhis' => true]);
        $this->plan = InsurancePlan::factory()->create(['insurance_provider_id' => $this->provider->id]);
    });

    it('rejects empty items array', function () {
        $response = $this->post(route('admin.pricing-dashboard.bulk-update'), [
            'plan_id' => $this->plan->id,
            'items' => [],
            'copay' => 5.00,
        ]);

        $response->assertSessionHasErrors('items');
    });

    it('rejects negative copay in bulk update', function () {
        $drug = Drug::factory()->create(['is_active' => true]);

        $response = $this->post(route('admin.pricing-dashboard.bulk-update'), [
            'plan_id' => $this->plan->id,
            'items' => [
                ['type' => 'drug', 'id' => $drug->id, 'code' => $drug->drug_code],
            ],
            'copay' => -5.00,
        ]);

        $response->assertSessionHasErrors('copay');
    });

    it('rejects items with invalid type', function () {
        $drug = Drug::factory()->create(['is_active' => true]);

        $response = $this->post(route('admin.pricing-dashboard.bulk-update'), [
            'plan_id' => $this->plan->id,
            'items' => [
                ['type' => 'invalid', 'id' => $drug->id, 'code' => $drug->drug_code],
            ],
            'copay' => 5.00,
        ]);

        $response->assertSessionHasErrors('items.0.type');
    });

    it('accepts valid bulk update request', function () {
        $drug = Drug::factory()->create(['is_active' => true]);
        $lab = LabService::factory()->create(['is_active' => true]);

        $response = $this->post(route('admin.pricing-dashboard.bulk-update'), [
            'plan_id' => $this->plan->id,
            'items' => [
                ['type' => 'drug', 'id' => $drug->id, 'code' => $drug->drug_code],
                ['type' => 'lab', 'id' => $lab->id, 'code' => $lab->code],
            ],
            'copay' => 5.00,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
    });
});

describe('Import validation', function () {
    it('rejects missing file', function () {
        $response = $this->post(route('admin.pricing-dashboard.import'), []);

        $response->assertSessionHasErrors('file');
    });

    it('rejects invalid file type', function () {
        // Create a file with PDF-like content (PDF magic bytes)
        $tempPath = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempPath, '%PDF-1.4 test content');

        $file = new \Illuminate\Http\UploadedFile(
            $tempPath,
            'test.pdf',
            'application/pdf',
            null,
            true
        );

        $response = $this->post(route('admin.pricing-dashboard.import'), [
            'file' => $file,
        ]);

        $response->assertSessionHasErrors('file');
    });

    it('accepts valid CSV file', function () {
        $drug = Drug::factory()->create(['drug_code' => 'DRG-TEST', 'unit_price' => 10.00, 'is_active' => true]);

        $csvContent = "Code,Name,Category,Cash Price\nDRG-TEST,Test Drug,drugs,25.00";
        $tempPath = tempnam(sys_get_temp_dir(), 'csv_');
        file_put_contents($tempPath, $csvContent);

        $file = new \Illuminate\Http\UploadedFile(
            $tempPath,
            'import.csv',
            'text/csv',
            null,
            true
        );

        $response = $this->post(route('admin.pricing-dashboard.import'), [
            'file' => $file,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
    });
});
