<?php

/**
 * Property-Based Test for NHIS CSV Export Contains Master Prices
 *
 * **Feature: nhis-claims-integration, Property 11: NHIS CSV Export Contains Master Prices**
 * **Validates: Requirements 6.1, 6.2**
 *
 * Property: For any NHIS coverage CSV export, the tariff price column for mapped items
 * should match the current NHIS Tariff Master price.
 */

use App\Exports\NhisCoverageTemplate;
use App\Models\Drug;
use App\Models\InsurancePlan;
use App\Models\InsuranceProvider;
use App\Models\NhisItemMapping;
use App\Models\NhisTariff;
use Maatwebsite\Excel\Facades\Excel;

beforeEach(function () {
    // Clean up existing data
    NhisItemMapping::query()->delete();
    NhisTariff::query()->delete();
    Drug::query()->delete();
});

/**
 * Generate random NHIS tariff prices for property testing
 */
dataset('random_nhis_prices', function () {
    $prices = [];
    for ($i = 0; $i < 10; $i++) {
        $prices[] = [fake()->randomFloat(2, 5, 500)];
    }

    return $prices;
});

/**
 * Generate random hospital prices for property testing
 */
dataset('random_hospital_prices', function () {
    $prices = [];
    for ($i = 0; $i < 10; $i++) {
        $prices[] = [fake()->randomFloat(2, 10, 1000)];
    }

    return $prices;
});

it('exports NHIS tariff price from Master for mapped items', function (float $nhisTariffPrice, float $hospitalPrice) {
    // Arrange: Create NHIS provider and plan
    $nhisProvider = InsuranceProvider::factory()->nhis()->create();
    $nhisPlan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $nhisProvider->id,
    ]);

    // Create a drug with hospital price
    $drug = Drug::factory()->create([
        'unit_price' => $hospitalPrice,
        'is_active' => true,
    ]);

    // Create NHIS tariff with specific price
    $nhisTariff = NhisTariff::factory()->medicine()->create([
        'price' => $nhisTariffPrice,
        'is_active' => true,
    ]);

    // Create mapping between drug and NHIS tariff
    NhisItemMapping::factory()->create([
        'item_type' => 'drug',
        'item_id' => $drug->id,
        'item_code' => $drug->drug_code,
        'nhis_tariff_id' => $nhisTariff->id,
    ]);

    // Act: Generate the export
    $export = new NhisCoverageTemplate('drug', $nhisPlan->id);
    $data = Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX);

    // Parse the exported data
    $tempFile = tempnam(sys_get_temp_dir(), 'nhis_export_');
    file_put_contents($tempFile, $data);
    $sheets = Excel::toArray([], $tempFile);
    unlink($tempFile);

    // Get the Data sheet (index 1)
    $dataSheet = $sheets[1] ?? [];

    // Find the row for our drug
    $drugRow = null;
    foreach ($dataSheet as $index => $row) {
        if ($index === 0) {
            continue;
        } // Skip header
        if (($row[0] ?? '') === $drug->drug_code) {
            $drugRow = $row;
            break;
        }
    }

    // Assert: The NHIS tariff price column should match the Master price
    expect($drugRow)->not->toBeNull('Drug should be found in export');

    // Column indices: 0=item_code, 1=item_name, 2=hospital_price, 3=nhis_tariff_price, 4=copay_amount
    $exportedNhisPrice = (float) $drugRow[3];
    expect($exportedNhisPrice)->toBe(round($nhisTariffPrice, 2), 'NHIS tariff price should match Master price');

    // Also verify hospital price is correct
    $exportedHospitalPrice = (float) $drugRow[2];
    expect($exportedHospitalPrice)->toBe(round($hospitalPrice, 2), 'Hospital price should match drug unit_price');
})->with('random_nhis_prices')->with('random_hospital_prices');

it('shows NOT MAPPED for items without NHIS mapping', function (float $hospitalPrice) {
    // Arrange: Create NHIS provider and plan
    $nhisProvider = InsuranceProvider::factory()->nhis()->create();
    $nhisPlan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $nhisProvider->id,
    ]);

    // Create a drug WITHOUT NHIS mapping
    $drug = Drug::factory()->create([
        'unit_price' => $hospitalPrice,
        'is_active' => true,
    ]);

    // Act: Generate the export
    $export = new NhisCoverageTemplate('drug', $nhisPlan->id);
    $data = Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX);

    // Parse the exported data
    $tempFile = tempnam(sys_get_temp_dir(), 'nhis_export_');
    file_put_contents($tempFile, $data);
    $sheets = Excel::toArray([], $tempFile);
    unlink($tempFile);

    // Get the Data sheet (index 1)
    $dataSheet = $sheets[1] ?? [];

    // Find the row for our drug
    $drugRow = null;
    foreach ($dataSheet as $index => $row) {
        if ($index === 0) {
            continue;
        } // Skip header
        if (($row[0] ?? '') === $drug->drug_code) {
            $drugRow = $row;
            break;
        }
    }

    // Assert: The NHIS tariff price column should show "NOT MAPPED"
    expect($drugRow)->not->toBeNull('Drug should be found in export');

    // Column indices: 0=item_code, 1=item_name, 2=hospital_price, 3=nhis_tariff_price, 4=copay_amount
    $exportedNhisPrice = $drugRow[3];
    expect($exportedNhisPrice)->toBe('NOT MAPPED', 'Unmapped items should show NOT MAPPED');
})->with('random_hospital_prices');

it('exports correct prices for multiple mapped items', function () {
    // Arrange: Create NHIS provider and plan
    $nhisProvider = InsuranceProvider::factory()->nhis()->create();
    $nhisPlan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $nhisProvider->id,
    ]);

    // Create multiple drugs with different prices
    $testCases = [];
    for ($i = 0; $i < 5; $i++) {
        $hospitalPrice = fake()->randomFloat(2, 50, 500);
        $nhisTariffPrice = fake()->randomFloat(2, 20, 300);

        $drug = Drug::factory()->create([
            'unit_price' => $hospitalPrice,
            'is_active' => true,
        ]);

        $nhisTariff = NhisTariff::factory()->medicine()->create([
            'price' => $nhisTariffPrice,
            'is_active' => true,
        ]);

        NhisItemMapping::factory()->create([
            'item_type' => 'drug',
            'item_id' => $drug->id,
            'item_code' => $drug->drug_code,
            'nhis_tariff_id' => $nhisTariff->id,
        ]);

        $testCases[$drug->drug_code] = [
            'hospital_price' => $hospitalPrice,
            'nhis_price' => $nhisTariffPrice,
        ];
    }

    // Act: Generate the export
    $export = new NhisCoverageTemplate('drug', $nhisPlan->id);
    $data = Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX);

    // Parse the exported data
    $tempFile = tempnam(sys_get_temp_dir(), 'nhis_export_');
    file_put_contents($tempFile, $data);
    $sheets = Excel::toArray([], $tempFile);
    unlink($tempFile);

    // Get the Data sheet (index 1)
    $dataSheet = $sheets[1] ?? [];

    // Verify each drug's prices
    foreach ($dataSheet as $index => $row) {
        if ($index === 0) {
            continue;
        } // Skip header

        $itemCode = $row[0] ?? '';
        if (isset($testCases[$itemCode])) {
            $expectedNhisPrice = round($testCases[$itemCode]['nhis_price'], 2);
            $expectedHospitalPrice = round($testCases[$itemCode]['hospital_price'], 2);

            $exportedNhisPrice = (float) $row[3];
            $exportedHospitalPrice = (float) $row[2];

            expect($exportedNhisPrice)->toBe($expectedNhisPrice, "NHIS price for {$itemCode} should match Master");
            expect($exportedHospitalPrice)->toBe($expectedHospitalPrice, "Hospital price for {$itemCode} should match");
        }
    }
});

it('uses current Master price even when tariff was recently updated', function () {
    // Arrange: Create NHIS provider and plan
    $nhisProvider = InsuranceProvider::factory()->nhis()->create();
    $nhisPlan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $nhisProvider->id,
    ]);

    // Create a drug
    $drug = Drug::factory()->create([
        'unit_price' => 100.00,
        'is_active' => true,
    ]);

    // Create NHIS tariff with initial price
    $nhisTariff = NhisTariff::factory()->medicine()->create([
        'price' => 50.00,
        'is_active' => true,
    ]);

    // Create mapping
    NhisItemMapping::factory()->create([
        'item_type' => 'drug',
        'item_id' => $drug->id,
        'item_code' => $drug->drug_code,
        'nhis_tariff_id' => $nhisTariff->id,
    ]);

    // Update the tariff price (simulating Master update)
    $newPrice = 75.00;
    $nhisTariff->update(['price' => $newPrice]);

    // Act: Generate the export
    $export = new NhisCoverageTemplate('drug', $nhisPlan->id);
    $data = Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX);

    // Parse the exported data
    $tempFile = tempnam(sys_get_temp_dir(), 'nhis_export_');
    file_put_contents($tempFile, $data);
    $sheets = Excel::toArray([], $tempFile);
    unlink($tempFile);

    // Get the Data sheet (index 1)
    $dataSheet = $sheets[1] ?? [];

    // Find the row for our drug
    $drugRow = null;
    foreach ($dataSheet as $index => $row) {
        if ($index === 0) {
            continue;
        }
        if (($row[0] ?? '') === $drug->drug_code) {
            $drugRow = $row;
            break;
        }
    }

    // Assert: Should use the updated (current) Master price
    expect($drugRow)->not->toBeNull();
    $exportedNhisPrice = (float) $drugRow[3];
    expect($exportedNhisPrice)->toBe($newPrice, 'Should use current Master price after update');
});
