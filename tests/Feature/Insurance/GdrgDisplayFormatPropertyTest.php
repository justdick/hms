<?php

/**
 * Property-Based Test for G-DRG Display Format
 *
 * **Feature: nhis-claims-integration, Property 17: G-DRG Display Format**
 * **Validates: Requirements 9.3**
 *
 * Property: For any G-DRG tariff displayed in the dropdown, the format should be
 * "Name (Code - GHS Price)" where Price is formatted to 2 decimal places.
 */

use App\Models\GdrgTariff;

beforeEach(function () {
    // Clean up any existing G-DRG tariffs
    GdrgTariff::query()->delete();
});

/**
 * Generate random tariff data for property testing
 */
dataset('random_tariff_data', function () {
    $data = [];
    for ($i = 0; $i < 20; $i++) {
        $data[] = [
            'name' => fake()->words(rand(2, 5), true),
            'code' => 'GDRG-'.fake()->unique()->regexify('[A-Z]{2}[0-9]{3}'),
            'price' => fake()->randomFloat(2, 1, 9999),
        ];
    }

    return $data;
});

/**
 * Generate edge case prices for property testing
 */
dataset('edge_case_prices', function () {
    return [
        ['price' => 0.00],
        ['price' => 0.01],
        ['price' => 0.10],
        ['price' => 1.00],
        ['price' => 10.00],
        ['price' => 100.00],
        ['price' => 999.99],
        ['price' => 1000.00],
        ['price' => 9999.99],
        ['price' => 0.99],
        ['price' => 123.45],
        ['price' => 50.50],
    ];
});

it('formats display name as "Name (Code - GHS Price)"', function (string $name, string $code, float $price) {
    // Arrange: Create a G-DRG tariff with specific values
    $tariff = GdrgTariff::factory()->create([
        'name' => $name,
        'code' => $code,
        'tariff_price' => $price,
    ]);

    // Act: Get the display name
    $displayName = $tariff->display_name;

    // Assert: Format matches "Name (Code - GHS Price)"
    $expectedFormat = sprintf('%s (%s - GHS %.2f)', $name, $code, $price);
    expect($displayName)->toBe($expectedFormat);

    // Assert: Display name contains all required parts
    expect($displayName)->toContain($name);
    expect($displayName)->toContain($code);
    expect($displayName)->toContain('GHS');
    expect($displayName)->toContain('(');
    expect($displayName)->toContain(')');
    expect($displayName)->toContain(' - ');
})->with('random_tariff_data');

it('formats price to exactly 2 decimal places', function (float $price) {
    // Arrange: Create a G-DRG tariff with specific price
    $tariff = GdrgTariff::factory()->create([
        'name' => 'Test Tariff',
        'code' => 'GDRG-TEST'.fake()->unique()->numberBetween(100, 999),
        'tariff_price' => $price,
    ]);

    // Act: Get the display name
    $displayName = $tariff->display_name;

    // Assert: Price is formatted with exactly 2 decimal places
    $formattedPrice = sprintf('%.2f', $price);
    expect($displayName)->toContain("GHS {$formattedPrice}");

    // Assert: The price in display name matches the expected format
    preg_match('/GHS (\d+\.\d{2})/', $displayName, $matches);
    expect($matches)->toHaveCount(2);
    expect($matches[1])->toBe($formattedPrice);
})->with('edge_case_prices');

it('maintains consistent format across multiple tariffs', function () {
    // Arrange: Create multiple tariffs
    $tariffs = GdrgTariff::factory()->count(10)->create();

    // Act & Assert: Each tariff follows the same format pattern
    $pattern = '/^.+ \([A-Z0-9\-]+ - GHS \d+\.\d{2}\)$/';

    foreach ($tariffs as $tariff) {
        $displayName = $tariff->display_name;

        // Assert: Matches the expected pattern
        expect($displayName)->toMatch($pattern);

        // Assert: Contains all required components
        expect($displayName)->toContain($tariff->name);
        expect($displayName)->toContain($tariff->code);
        expect($displayName)->toContain('GHS');
    }
});

it('handles special characters in name correctly', function () {
    // Arrange: Create tariffs with special characters in name
    $specialNames = [
        'Test & Procedure',
        'Consultation (General)',
        'Follow-up Visit',
        'OPD - General',
        "Patient's Care",
        'Test/Examination',
    ];

    foreach ($specialNames as $name) {
        $tariff = GdrgTariff::factory()->create([
            'name' => $name,
            'code' => 'GDRG-'.fake()->unique()->regexify('[A-Z]{2}[0-9]{3}'),
            'tariff_price' => 100.00,
        ]);

        // Act: Get the display name
        $displayName = $tariff->display_name;

        // Assert: Name is preserved in display name
        expect($displayName)->toStartWith($name);
        expect($displayName)->toContain('GHS 100.00');
    }
});

it('display name is accessible as attribute', function () {
    // Arrange: Create a tariff
    $tariff = GdrgTariff::factory()->create([
        'name' => 'General Consultation',
        'code' => 'GDRG-GC001',
        'tariff_price' => 50.00,
    ]);

    // Act & Assert: Can access via attribute accessor
    expect($tariff->display_name)->toBe('General Consultation (GDRG-GC001 - GHS 50.00)');

    // Assert: Can also access via getDisplayNameAttribute method
    expect($tariff->getDisplayNameAttribute())->toBe('General Consultation (GDRG-GC001 - GHS 50.00)');
});

it('format is consistent after model refresh', function () {
    // Arrange: Create a tariff
    $tariff = GdrgTariff::factory()->create([
        'name' => 'Specialist Visit',
        'code' => 'GDRG-SV001',
        'tariff_price' => 75.50,
    ]);

    $originalDisplayName = $tariff->display_name;

    // Act: Refresh the model from database
    $tariff->refresh();

    // Assert: Display name remains consistent
    expect($tariff->display_name)->toBe($originalDisplayName);
    expect($tariff->display_name)->toBe('Specialist Visit (GDRG-SV001 - GHS 75.50)');
});
