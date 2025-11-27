<?php

/**
 * Property-Based Test for G-DRG Code Uniqueness
 *
 * **Feature: nhis-claims-integration, Property 7: G-DRG Code Uniqueness**
 * **Validates: Requirements 3.2**
 *
 * Property: For any G-DRG tariff creation attempt, if a tariff with the same code
 * already exists, the system should reject the creation and return a validation error.
 */

use App\Models\GdrgTariff;
use Illuminate\Database\QueryException;

beforeEach(function () {
    // Clean up any existing G-DRG tariffs
    GdrgTariff::query()->delete();
});

/**
 * Generate random G-DRG codes for property testing
 */
dataset('random_gdrg_codes', function () {
    $codes = [];
    for ($i = 0; $i < 20; $i++) {
        $codes[] = ['GDRG-'.fake()->unique()->regexify('[A-Z]{2}[0-9]{3}')];
    }

    return $codes;
});

/**
 * Generate random counts for property testing
 */
dataset('random_counts', function () {
    return [
        [3],
        [5],
        [10],
    ];
});

it('enforces unique code constraint at database level', function (string $code) {
    // Arrange: Create a G-DRG tariff with the given code
    GdrgTariff::factory()->create([
        'code' => $code,
    ]);

    // Act & Assert: Attempting to create another tariff with the same code should throw exception
    expect(fn () => GdrgTariff::factory()->create(['code' => $code]))
        ->toThrow(QueryException::class);

    // Assert: Only one tariff with this code exists
    expect(GdrgTariff::where('code', $code)->count())->toBe(1);
})->with('random_gdrg_codes');

it('allows creation of tariffs with different codes', function (int $count) {
    // Arrange & Act: Create multiple tariffs with unique codes
    $tariffs = GdrgTariff::factory()->count($count)->create();

    // Assert: All tariffs were created
    expect(GdrgTariff::count())->toBe($count);

    // Assert: All codes are unique
    $codes = $tariffs->pluck('code')->toArray();
    expect(count($codes))->toBe(count(array_unique($codes)));
})->with('random_counts');

it('maintains code uniqueness across multiple creation attempts', function () {
    // Arrange: Create initial tariffs
    $existingCodes = [];
    for ($i = 0; $i < 5; $i++) {
        $tariff = GdrgTariff::factory()->create();
        $existingCodes[] = $tariff->code;
    }

    // Act & Assert: Try to create tariffs with each existing code
    foreach ($existingCodes as $code) {
        expect(fn () => GdrgTariff::factory()->create(['code' => $code]))
            ->toThrow(QueryException::class);
    }

    // Assert: Count remains the same (no duplicates created)
    expect(GdrgTariff::count())->toBe(5);

    // Assert: Each code appears exactly once
    foreach ($existingCodes as $code) {
        expect(GdrgTariff::where('code', $code)->count())->toBe(1);
    }
});

it('code uniqueness is case-sensitive in database', function () {
    // Arrange: Create a tariff with uppercase code
    $upperCode = 'GDRG-TEST001';
    GdrgTariff::factory()->create(['code' => $upperCode]);

    // Act: Try to create with same code (should fail)
    expect(fn () => GdrgTariff::factory()->create(['code' => $upperCode]))
        ->toThrow(QueryException::class);

    // Assert: Only one tariff exists
    expect(GdrgTariff::count())->toBe(1);
});

it('allows updating tariff without changing code', function () {
    // Arrange: Create a tariff
    $tariff = GdrgTariff::factory()->create([
        'code' => 'GDRG-UPDATE01',
        'name' => 'Original Name',
        'tariff_price' => 100.00,
    ]);

    // Act: Update other fields without changing code
    $tariff->update([
        'name' => 'Updated Name',
        'tariff_price' => 150.00,
    ]);

    // Assert: Update succeeded
    $tariff->refresh();
    expect($tariff->name)->toBe('Updated Name');
    expect((float) $tariff->tariff_price)->toBe(150.00);
    expect($tariff->code)->toBe('GDRG-UPDATE01');
});

it('prevents updating code to an existing code', function () {
    // Arrange: Create two tariffs with different codes
    $tariff1 = GdrgTariff::factory()->create(['code' => 'GDRG-FIRST01']);
    $tariff2 = GdrgTariff::factory()->create(['code' => 'GDRG-SECOND01']);

    // Act & Assert: Try to update tariff2's code to tariff1's code
    expect(fn () => $tariff2->update(['code' => 'GDRG-FIRST01']))
        ->toThrow(QueryException::class);

    // Assert: tariff2's code remains unchanged
    $tariff2->refresh();
    expect($tariff2->code)->toBe('GDRG-SECOND01');
});
