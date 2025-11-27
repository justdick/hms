<?php

/**
 * Property-Based Test for G-DRG Search Filtering
 *
 * **Feature: nhis-claims-integration, Property 16: G-DRG Search Filtering**
 * **Validates: Requirements 9.2, 3.5**
 *
 * Property: For any search term entered in the G-DRG dropdown, all returned results
 * should contain the search term in either the code or name field.
 */

use App\Models\GdrgTariff;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    // Create permissions
    Permission::firstOrCreate(['name' => 'gdrg-tariffs.view']);
    Permission::firstOrCreate(['name' => 'gdrg-tariffs.manage']);

    // Clean up any existing tariffs
    GdrgTariff::query()->delete();
});

/**
 * Generate random G-DRG tariff data
 */
function generateRandomGdrgTariff(?string $code = null, ?string $name = null): array
{
    $mdcCategories = ['Out Patient', 'In Patient', 'Emergency', 'Surgical', 'Medical'];
    $ageCategories = ['adult', 'child', 'all'];

    return [
        'code' => $code ?? 'GDRG-'.fake()->unique()->regexify('[A-Z]{2}[0-9]{3}'),
        'name' => $name ?? fake()->words(3, true),
        'mdc_category' => fake()->randomElement($mdcCategories),
        'tariff_price' => fake()->randomFloat(2, 50, 1000),
        'age_category' => fake()->randomElement($ageCategories),
        'is_active' => true,
    ];
}

dataset('search_term_counts', function () {
    return [
        [5],
        [10],
        [15],
    ];
});

it('returns only tariffs containing search term in code or name', function (int $count) {
    // Arrange
    $user = User::factory()->create();
    $user->givePermissionTo('gdrg-tariffs.view');

    // Create tariffs with known patterns
    $searchTerm = 'CARDIAC';
    $matchingTariffs = [];
    $nonMatchingTariffs = [];

    // Create tariffs that should match (contain search term in code or name)
    for ($i = 0; $i < $count; $i++) {
        if ($i % 2 === 0) {
            // Match in code
            $tariff = GdrgTariff::create(generateRandomGdrgTariff(
                code: "GDRG-{$searchTerm}-".str_pad($i, 3, '0', STR_PAD_LEFT)
            ));
        } else {
            // Match in name
            $tariff = GdrgTariff::create(generateRandomGdrgTariff(
                name: "{$searchTerm} Procedure Type {$i}"
            ));
        }
        $matchingTariffs[] = $tariff;
    }

    // Create tariffs that should NOT match
    for ($i = 0; $i < $count; $i++) {
        $tariff = GdrgTariff::create(generateRandomGdrgTariff(
            code: 'GDRG-OTHER-'.str_pad($i, 3, '0', STR_PAD_LEFT),
            name: "Unrelated Procedure {$i}"
        ));
        $nonMatchingTariffs[] = $tariff;
    }

    // Act
    $response = $this->actingAs($user)
        ->getJson('/api/gdrg-tariffs/search?search='.$searchTerm);

    // Assert
    $response->assertOk();
    $results = $response->json('tariffs');

    // Property: All returned results should contain the search term in code or name
    foreach ($results as $result) {
        $codeContains = stripos($result['code'], $searchTerm) !== false;
        $nameContains = stripos($result['name'], $searchTerm) !== false;
        $mdcContains = stripos($result['mdc_category'], $searchTerm) !== false;

        expect($codeContains || $nameContains || $mdcContains)->toBeTrue(
            "Result with code '{$result['code']}' and name '{$result['name']}' does not contain search term '{$searchTerm}'"
        );
    }

    // Property: All matching tariffs should be in results
    expect(count($results))->toBe($count);
})->with('search_term_counts');

it('returns tariffs matching search term in MDC category', function () {
    // Arrange
    $user = User::factory()->create();
    $user->givePermissionTo('gdrg-tariffs.view');

    $searchTerm = 'Emergency';

    // Create tariffs with matching MDC category
    for ($i = 0; $i < 3; $i++) {
        GdrgTariff::create([
            'code' => 'GDRG-EM'.str_pad($i, 3, '0', STR_PAD_LEFT),
            'name' => "Emergency Procedure {$i}",
            'mdc_category' => 'Emergency',
            'tariff_price' => fake()->randomFloat(2, 50, 500),
            'age_category' => 'all',
            'is_active' => true,
        ]);
    }

    // Create non-matching tariffs
    for ($i = 0; $i < 3; $i++) {
        GdrgTariff::create([
            'code' => 'GDRG-OP'.str_pad($i, 3, '0', STR_PAD_LEFT),
            'name' => "Outpatient Procedure {$i}",
            'mdc_category' => 'Out Patient',
            'tariff_price' => fake()->randomFloat(2, 50, 500),
            'age_category' => 'all',
            'is_active' => true,
        ]);
    }

    // Act
    $response = $this->actingAs($user)
        ->getJson('/api/gdrg-tariffs/search?search='.$searchTerm);

    // Assert
    $response->assertOk();
    $results = $response->json('tariffs');

    // All results should contain search term
    foreach ($results as $result) {
        $codeContains = stripos($result['code'], $searchTerm) !== false;
        $nameContains = stripos($result['name'], $searchTerm) !== false;
        $mdcContains = stripos($result['mdc_category'], $searchTerm) !== false;

        expect($codeContains || $nameContains || $mdcContains)->toBeTrue();
    }

    expect(count($results))->toBe(3);
});

it('returns only active tariffs in search results', function () {
    // Arrange
    $user = User::factory()->create();
    $user->givePermissionTo('gdrg-tariffs.view');

    $searchTerm = 'ACTIVE';

    // Create active tariffs
    for ($i = 0; $i < 3; $i++) {
        GdrgTariff::create([
            'code' => "GDRG-{$searchTerm}-".str_pad($i, 3, '0', STR_PAD_LEFT),
            'name' => "Active Procedure {$i}",
            'mdc_category' => 'Out Patient',
            'tariff_price' => fake()->randomFloat(2, 50, 500),
            'age_category' => 'all',
            'is_active' => true,
        ]);
    }

    // Create inactive tariffs with same search term
    for ($i = 0; $i < 2; $i++) {
        GdrgTariff::create([
            'code' => "GDRG-{$searchTerm}-INACTIVE-".str_pad($i, 3, '0', STR_PAD_LEFT),
            'name' => "Inactive Procedure {$i}",
            'mdc_category' => 'Out Patient',
            'tariff_price' => fake()->randomFloat(2, 50, 500),
            'age_category' => 'all',
            'is_active' => false,
        ]);
    }

    // Act
    $response = $this->actingAs($user)
        ->getJson('/api/gdrg-tariffs/search?search='.$searchTerm);

    // Assert
    $response->assertOk();
    $results = $response->json('tariffs');

    // Property: Only active tariffs should be returned
    foreach ($results as $result) {
        expect($result['is_active'])->toBeTrue();
    }

    expect(count($results))->toBe(3);
});

it('returns empty results when no tariffs match search term', function () {
    // Arrange
    $user = User::factory()->create();
    $user->givePermissionTo('gdrg-tariffs.view');

    // Create tariffs that won't match
    for ($i = 0; $i < 5; $i++) {
        GdrgTariff::create(generateRandomGdrgTariff(
            code: 'GDRG-ABC-'.str_pad($i, 3, '0', STR_PAD_LEFT),
            name: "Standard Procedure {$i}"
        ));
    }

    // Act
    $response = $this->actingAs($user)
        ->getJson('/api/gdrg-tariffs/search?search=NONEXISTENT');

    // Assert
    $response->assertOk();
    $results = $response->json('tariffs');

    expect($results)->toBeEmpty();
});

it('performs case-insensitive search', function () {
    // Arrange
    $user = User::factory()->create();
    $user->givePermissionTo('gdrg-tariffs.view');

    GdrgTariff::create([
        'code' => 'GDRG-CARDIAC-001',
        'name' => 'Cardiac Surgery',
        'mdc_category' => 'Surgical',
        'tariff_price' => 500.00,
        'age_category' => 'adult',
        'is_active' => true,
    ]);

    // Act - search with different cases
    $responseLower = $this->actingAs($user)
        ->getJson('/api/gdrg-tariffs/search?search=cardiac');

    $responseUpper = $this->actingAs($user)
        ->getJson('/api/gdrg-tariffs/search?search=CARDIAC');

    $responseMixed = $this->actingAs($user)
        ->getJson('/api/gdrg-tariffs/search?search=CaRdIaC');

    // Assert - all should return the same result
    $responseLower->assertOk();
    $responseUpper->assertOk();
    $responseMixed->assertOk();

    expect(count($responseLower->json('tariffs')))->toBe(1);
    expect(count($responseUpper->json('tariffs')))->toBe(1);
    expect(count($responseMixed->json('tariffs')))->toBe(1);
});

it('respects limit parameter in search results', function () {
    // Arrange
    $user = User::factory()->create();
    $user->givePermissionTo('gdrg-tariffs.view');

    $searchTerm = 'LIMIT';

    // Create more tariffs than the limit
    for ($i = 0; $i < 20; $i++) {
        GdrgTariff::create([
            'code' => "GDRG-{$searchTerm}-".str_pad($i, 3, '0', STR_PAD_LEFT),
            'name' => "Limit Test Procedure {$i}",
            'mdc_category' => 'Out Patient',
            'tariff_price' => fake()->randomFloat(2, 50, 500),
            'age_category' => 'all',
            'is_active' => true,
        ]);
    }

    // Act
    $response = $this->actingAs($user)
        ->getJson('/api/gdrg-tariffs/search?search='.$searchTerm.'&limit=5');

    // Assert
    $response->assertOk();
    $results = $response->json('tariffs');

    expect(count($results))->toBe(5);
});
