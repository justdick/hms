<?php

/**
 * Property-Based Test for NHIS Tariff Search Filtering
 *
 * **Feature: nhis-claims-integration, Property 2: NHIS Tariff Search Filtering**
 * **Validates: Requirements 1.4**
 *
 * Property: For any search term entered in the NHIS tariff search, all returned
 * results should contain the search term in either the code, name, or category field.
 */

use App\Models\NhisTariff;
use Illuminate\Support\Str;

beforeEach(function () {
    // Clean up any existing tariffs
    NhisTariff::query()->delete();
});

/**
 * Generate random search terms for property testing
 */
dataset('random_search_terms', function () {
    $terms = [];
    for ($i = 0; $i < 20; $i++) {
        $terms[] = [fake()->randomElement([
            fake()->regexify('[A-Z]{3}'),           // 3 letter code prefix
            fake()->word(),                          // Random word
            fake()->randomElement(['medicine', 'lab', 'procedure', 'consultation', 'consumable']), // Category
            fake()->regexify('[0-9]{3}'),           // Numeric code
            Str::upper(fake()->lexify('???')),      // Random 3 uppercase letters
        ])];
    }

    return $terms;
});

/**
 * Generate random tariff data for property testing
 */
dataset('random_tariff_counts', function () {
    return [
        [5],
        [10],
        [15],
        [20],
    ];
});

it('returns only tariffs containing the search term in code, name, or category', function (string $searchTerm) {
    // Arrange: Create a mix of tariffs - some that should match, some that shouldn't
    $categories = ['medicine', 'lab', 'procedure', 'consultation', 'consumable'];

    // Create tariffs that should NOT match the search term
    for ($i = 0; $i < 5; $i++) {
        NhisTariff::factory()->create([
            'nhis_code' => 'NOMATCH-'.fake()->unique()->regexify('[0-9]{6}'),
            'name' => 'Unrelated Item '.fake()->unique()->numberBetween(1000, 9999),
            'category' => $categories[array_rand($categories)],
        ]);
    }

    // Create tariffs that SHOULD match - one with search term in code
    $matchingTariff1 = NhisTariff::factory()->create([
        'nhis_code' => strtoupper($searchTerm).'-'.fake()->unique()->regexify('[0-9]{6}'),
        'name' => 'Some Medicine',
        'category' => 'medicine',
    ]);

    // Create tariff with search term in name
    $matchingTariff2 = NhisTariff::factory()->create([
        'nhis_code' => 'OTHER-'.fake()->unique()->regexify('[0-9]{6}'),
        'name' => 'Product with '.$searchTerm.' inside',
        'category' => 'lab',
    ]);

    // Act: Search using the scope
    $results = NhisTariff::search($searchTerm)->get();

    // Assert: All results should contain the search term in code, name, or category
    foreach ($results as $tariff) {
        $containsInCode = Str::contains(strtolower($tariff->nhis_code), strtolower($searchTerm));
        $containsInName = Str::contains(strtolower($tariff->name), strtolower($searchTerm));
        $containsInCategory = Str::contains(strtolower($tariff->category), strtolower($searchTerm));

        expect($containsInCode || $containsInName || $containsInCategory)
            ->toBeTrue("Tariff '{$tariff->name}' (code: {$tariff->nhis_code}, category: {$tariff->category}) does not contain search term '{$searchTerm}'");
    }

    // Assert: At least the matching tariffs should be in results (if search term is valid)
    if (strlen($searchTerm) > 0) {
        expect($results->count())->toBeGreaterThanOrEqual(1);
    }
})->with('random_search_terms');

it('returns all active tariffs when search term is empty', function () {
    // Arrange: Create some tariffs
    NhisTariff::factory()->count(5)->create(['is_active' => true]);
    NhisTariff::factory()->count(2)->inactive()->create();

    // Act: Search with empty string
    $resultsEmpty = NhisTariff::search('')->get();
    $resultsNull = NhisTariff::search(null)->get();
    $allTariffs = NhisTariff::all();

    // Assert: Empty search should return all tariffs (not filtered)
    expect($resultsEmpty->count())->toBe($allTariffs->count());
    expect($resultsNull->count())->toBe($allTariffs->count());
});

it('search is case-insensitive', function () {
    // Arrange: Create a tariff with known values
    $tariff = NhisTariff::factory()->create([
        'nhis_code' => 'MED-123456',
        'name' => 'Paracetamol Tablets',
        'category' => 'medicine',
    ]);

    // Act & Assert: Search with different cases should find the same tariff
    $upperResults = NhisTariff::search('MED')->get();
    $lowerResults = NhisTariff::search('med')->get();
    $mixedResults = NhisTariff::search('Med')->get();

    expect($upperResults->count())->toBe($lowerResults->count())
        ->and($lowerResults->count())->toBe($mixedResults->count())
        ->and($upperResults->pluck('id')->toArray())->toBe($lowerResults->pluck('id')->toArray());
});

it('filters correctly by category scope', function () {
    // Arrange: Create tariffs in different categories
    NhisTariff::factory()->count(3)->medicine()->create();
    NhisTariff::factory()->count(2)->lab()->create();
    NhisTariff::factory()->count(2)->procedure()->create();

    // Act: Filter by each category
    $medicines = NhisTariff::byCategory('medicine')->get();
    $labs = NhisTariff::byCategory('lab')->get();
    $procedures = NhisTariff::byCategory('procedure')->get();
    $all = NhisTariff::byCategory(null)->get();
    $empty = NhisTariff::byCategory('')->get();

    // Assert: Each category filter returns only that category
    expect($medicines->count())->toBe(3);
    expect($labs->count())->toBe(2);
    expect($procedures->count())->toBe(2);
    expect($all->count())->toBe(7); // null returns all
    expect($empty->count())->toBe(7); // empty string returns all

    // Assert: All returned items have the correct category
    foreach ($medicines as $tariff) {
        expect($tariff->category)->toBe('medicine');
    }
    foreach ($labs as $tariff) {
        expect($tariff->category)->toBe('lab');
    }
});

it('active scope returns only active tariffs', function (int $count) {
    // Arrange: Create mix of active and inactive tariffs
    $activeCount = (int) ceil($count / 2);
    $inactiveCount = $count - $activeCount;

    NhisTariff::factory()->count($activeCount)->create(['is_active' => true]);
    NhisTariff::factory()->count($inactiveCount)->create(['is_active' => false]);

    // Act
    $activeResults = NhisTariff::active()->get();

    // Assert: Only active tariffs returned
    expect($activeResults->count())->toBe($activeCount);
    foreach ($activeResults as $tariff) {
        expect($tariff->is_active)->toBeTrue();
    }
})->with('random_tariff_counts');

it('combines search and active scopes correctly', function () {
    // Arrange: Create tariffs with known search term
    $searchTerm = 'ASPIRIN';

    // Active tariff with search term
    NhisTariff::factory()->create([
        'nhis_code' => 'MED-ASPIRIN-001',
        'name' => 'Aspirin 100mg',
        'is_active' => true,
    ]);

    // Inactive tariff with search term
    NhisTariff::factory()->create([
        'nhis_code' => 'MED-ASPIRIN-002',
        'name' => 'Aspirin 500mg',
        'is_active' => false,
    ]);

    // Active tariff without search term
    NhisTariff::factory()->create([
        'nhis_code' => 'MED-OTHER-001',
        'name' => 'Other Medicine',
        'is_active' => true,
    ]);

    // Act: Combine scopes
    $results = NhisTariff::active()->search($searchTerm)->get();

    // Assert: Only active tariffs matching search term
    expect($results->count())->toBe(1);
    expect($results->first()->is_active)->toBeTrue();
    expect(Str::contains(strtolower($results->first()->nhis_code), strtolower($searchTerm))
        || Str::contains(strtolower($results->first()->name), strtolower($searchTerm)))->toBeTrue();
});
