<?php

/**
 * Property-Based Test for NHIS Tariff Import Upsert
 *
 * **Feature: nhis-claims-integration, Property 1: NHIS Tariff Import Upsert**
 * **Validates: Requirements 1.2, 1.3**
 *
 * Property: For any NHIS tariff import containing existing codes, the system should
 * update prices for those codes without creating duplicate records. The count of
 * tariffs with a given code should always be exactly 1.
 */

use App\Models\NhisTariff;
use App\Services\NhisTariffService;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    // Clean up any existing tariffs
    NhisTariff::query()->delete();
});

/**
 * Generate random tariff data for CSV import
 */
function generateTariffCsvContent(array $tariffs): string
{
    $lines = ['nhis_code,name,category,price,unit'];

    foreach ($tariffs as $tariff) {
        $lines[] = implode(',', [
            $tariff['nhis_code'],
            '"'.$tariff['name'].'"',
            $tariff['category'],
            $tariff['price'],
            $tariff['unit'] ?? '',
        ]);
    }

    return implode("\n", $lines);
}

/**
 * Generate random tariff data
 */
function generateRandomTariff(?string $nhisCode = null): array
{
    $categories = ['medicine', 'lab', 'procedure', 'consultation', 'consumable'];
    $prefix = fake()->randomElement(['MED', 'LAB', 'PROC', 'CONS', 'CON']);

    return [
        'nhis_code' => $nhisCode ?? $prefix.'-'.fake()->unique()->regexify('[0-9]{6}'),
        'name' => fake()->words(3, true),
        'category' => fake()->randomElement($categories),
        'price' => fake()->randomFloat(2, 5, 500),
        'unit' => fake()->randomElement(['tablet', 'capsule', 'ml', 'unit', 'test']),
    ];
}

dataset('tariff_counts', function () {
    return [
        [3],
        [5],
        [10],
    ];
});

it('creates new tariffs when importing codes that do not exist', function (int $count) {
    // Arrange
    $service = new NhisTariffService;
    $tariffs = [];

    for ($i = 0; $i < $count; $i++) {
        $tariffs[] = generateRandomTariff();
    }

    $csvContent = generateTariffCsvContent($tariffs);
    $file = UploadedFile::fake()->createWithContent('tariffs.csv', $csvContent);

    // Act
    $result = $service->importTariffs($file);

    // Assert
    expect($result['success'])->toBeTrue();
    expect($result['imported'])->toBe($count);
    expect($result['updated'])->toBe(0);
    expect(NhisTariff::count())->toBe($count);

    // Verify each tariff was created with correct data
    foreach ($tariffs as $tariff) {
        $dbTariff = NhisTariff::where('nhis_code', $tariff['nhis_code'])->first();
        expect($dbTariff)->not->toBeNull();
        expect($dbTariff->name)->toBe($tariff['name']);
        expect($dbTariff->category)->toBe($tariff['category']);
        expect((float) $dbTariff->price)->toBe((float) $tariff['price']);
    }
})->with('tariff_counts');

it('updates existing tariffs without creating duplicates when importing existing codes', function (int $count) {
    // Arrange
    $service = new NhisTariffService;

    // Create existing tariffs in database
    $existingTariffs = [];
    for ($i = 0; $i < $count; $i++) {
        $tariff = NhisTariff::factory()->create();
        $existingTariffs[] = $tariff;
    }

    // Prepare import data with same codes but different prices
    $importTariffs = [];
    foreach ($existingTariffs as $existing) {
        $importTariffs[] = [
            'nhis_code' => $existing->nhis_code,
            'name' => 'Updated '.$existing->name,
            'category' => $existing->category,
            'price' => $existing->price + 100, // Different price
            'unit' => $existing->unit,
        ];
    }

    $csvContent = generateTariffCsvContent($importTariffs);
    $file = UploadedFile::fake()->createWithContent('tariffs.csv', $csvContent);

    // Act
    $result = $service->importTariffs($file);

    // Assert: No duplicates created
    expect($result['success'])->toBeTrue();
    expect($result['imported'])->toBe(0);
    expect($result['updated'])->toBe($count);
    expect(NhisTariff::count())->toBe($count);

    // Property: For each code, there should be exactly 1 tariff
    foreach ($existingTariffs as $existing) {
        $tariffCount = NhisTariff::where('nhis_code', $existing->nhis_code)->count();
        expect($tariffCount)->toBe(1, "Expected exactly 1 tariff with code {$existing->nhis_code}, found {$tariffCount}");

        // Verify price was updated
        $updatedTariff = NhisTariff::where('nhis_code', $existing->nhis_code)->first();
        expect(round((float) $updatedTariff->price, 2))->toBe(round((float) $existing->price + 100, 2));
    }
})->with('tariff_counts');

it('handles mixed import with both new and existing codes', function () {
    // Arrange
    $service = new NhisTariffService;

    // Create some existing tariffs
    $existingTariffs = NhisTariff::factory()->count(3)->create();

    // Prepare import with mix of existing and new codes
    $importTariffs = [];

    // Add existing codes with updated prices
    foreach ($existingTariffs as $existing) {
        $importTariffs[] = [
            'nhis_code' => $existing->nhis_code,
            'name' => 'Updated '.$existing->name,
            'category' => $existing->category,
            'price' => $existing->price + 50,
            'unit' => $existing->unit,
        ];
    }

    // Add new codes
    $newCodes = [];
    for ($i = 0; $i < 2; $i++) {
        $newTariff = generateRandomTariff();
        $importTariffs[] = $newTariff;
        $newCodes[] = $newTariff['nhis_code'];
    }

    $csvContent = generateTariffCsvContent($importTariffs);
    $file = UploadedFile::fake()->createWithContent('tariffs.csv', $csvContent);

    // Act
    $result = $service->importTariffs($file);

    // Assert
    expect($result['success'])->toBeTrue();
    expect($result['imported'])->toBe(2);
    expect($result['updated'])->toBe(3);
    expect(NhisTariff::count())->toBe(5);

    // Property: Each code should appear exactly once
    $allCodes = array_merge(
        $existingTariffs->pluck('nhis_code')->toArray(),
        $newCodes
    );

    foreach ($allCodes as $code) {
        $count = NhisTariff::where('nhis_code', $code)->count();
        expect($count)->toBe(1, "Expected exactly 1 tariff with code {$code}, found {$count}");
    }
});

it('maintains code uniqueness across multiple imports', function () {
    // Arrange
    $service = new NhisTariffService;
    $fixedCode = 'MED-UNIQUE-001';

    // First import
    $tariff1 = [
        'nhis_code' => $fixedCode,
        'name' => 'First Import Name',
        'category' => 'medicine',
        'price' => 100.00,
        'unit' => 'tablet',
    ];

    $csvContent1 = generateTariffCsvContent([$tariff1]);
    $file1 = UploadedFile::fake()->createWithContent('tariffs1.csv', $csvContent1);
    $service->importTariffs($file1);

    // Second import with same code
    $tariff2 = [
        'nhis_code' => $fixedCode,
        'name' => 'Second Import Name',
        'category' => 'medicine',
        'price' => 200.00,
        'unit' => 'tablet',
    ];

    $csvContent2 = generateTariffCsvContent([$tariff2]);
    $file2 = UploadedFile::fake()->createWithContent('tariffs2.csv', $csvContent2);
    $result2 = $service->importTariffs($file2);

    // Third import with same code
    $tariff3 = [
        'nhis_code' => $fixedCode,
        'name' => 'Third Import Name',
        'category' => 'medicine',
        'price' => 300.00,
        'unit' => 'tablet',
    ];

    $csvContent3 = generateTariffCsvContent([$tariff3]);
    $file3 = UploadedFile::fake()->createWithContent('tariffs3.csv', $csvContent3);
    $result3 = $service->importTariffs($file3);

    // Assert: Still only 1 tariff with this code
    $count = NhisTariff::where('nhis_code', $fixedCode)->count();
    expect($count)->toBe(1, "Expected exactly 1 tariff with code {$fixedCode} after 3 imports, found {$count}");

    // Verify final state has latest values
    $finalTariff = NhisTariff::where('nhis_code', $fixedCode)->first();
    expect($finalTariff->name)->toBe('Third Import Name');
    expect((float) $finalTariff->price)->toBe(300.00);
});

it('validates required columns in import file', function () {
    // Arrange
    $service = new NhisTariffService;

    // CSV missing required 'price' column
    $csvContent = "nhis_code,name,category\nMED-001,Test Medicine,medicine";
    $file = UploadedFile::fake()->createWithContent('invalid.csv', $csvContent);

    // Act
    $result = $service->importTariffs($file);

    // Assert
    expect($result['success'])->toBeFalse();
    expect($result['errors'])->not->toBeEmpty();
    expect($result['errors'][0])->toContain('Missing required columns');
});

it('validates row data during import', function () {
    // Arrange
    $service = new NhisTariffService;

    // CSV with invalid category
    $csvContent = "nhis_code,name,category,price\nMED-001,Test Medicine,invalid_category,100.00";
    $file = UploadedFile::fake()->createWithContent('invalid.csv', $csvContent);

    // Act
    $result = $service->importTariffs($file);

    // Assert: Should have validation error but not fail completely
    expect($result['errors'])->not->toBeEmpty();
    expect($result['imported'])->toBe(0);
});

it('handles empty file gracefully', function () {
    // Arrange
    $service = new NhisTariffService;
    $file = UploadedFile::fake()->createWithContent('empty.csv', '');

    // Act
    $result = $service->importTariffs($file);

    // Assert
    expect($result['success'])->toBeFalse();
    expect($result['errors'])->not->toBeEmpty();
});
