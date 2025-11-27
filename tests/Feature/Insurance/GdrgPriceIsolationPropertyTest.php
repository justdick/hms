<?php

/**
 * Property-Based Test for G-DRG Price Isolation
 *
 * **Feature: nhis-claims-integration, Property 8: G-DRG Price Isolation**
 * **Validates: Requirements 3.4**
 *
 * Property: For any G-DRG tariff price update, existing vetted claims that reference
 * that tariff should retain their original gdrg_amount value unchanged.
 */

use App\Models\GdrgTariff;
use App\Models\InsuranceClaim;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    // Create permissions
    Permission::firstOrCreate(['name' => 'gdrg-tariffs.view']);
    Permission::firstOrCreate(['name' => 'gdrg-tariffs.manage']);
});

dataset('price_changes', function () {
    return [
        'increase by 50' => [100.00, 150.00],
        'decrease by 25' => [200.00, 175.00],
        'double the price' => [50.00, 100.00],
        'halve the price' => [300.00, 150.00],
        'small increase' => [99.99, 100.01],
    ];
});

it('preserves gdrg_amount on vetted claims when tariff price is updated', function (float $originalPrice, float $newPrice) {
    // Arrange
    $user = User::factory()->create();
    $user->givePermissionTo('gdrg-tariffs.manage');

    // Create G-DRG tariff with original price
    $gdrgTariff = GdrgTariff::factory()->create([
        'tariff_price' => $originalPrice,
    ]);

    // Create vetted claims with the G-DRG tariff
    $vettedClaims = [];
    for ($i = 0; $i < 3; $i++) {
        $claim = InsuranceClaim::factory()->create([
            'gdrg_tariff_id' => $gdrgTariff->id,
            'gdrg_amount' => $originalPrice,
            'status' => 'vetted',
            'vetted_by' => $user->id,
            'vetted_at' => now(),
        ]);
        $vettedClaims[] = $claim;
    }

    // Store original amounts for verification
    $originalAmounts = [];
    foreach ($vettedClaims as $claim) {
        $originalAmounts[$claim->id] = (float) $claim->gdrg_amount;
    }

    // Act - Update the G-DRG tariff price
    $response = $this->actingAs($user)
        ->put("/admin/gdrg-tariffs/{$gdrgTariff->id}", [
            'code' => $gdrgTariff->code,
            'name' => $gdrgTariff->name,
            'mdc_category' => $gdrgTariff->mdc_category,
            'tariff_price' => $newPrice,
            'age_category' => $gdrgTariff->age_category,
        ]);

    $response->assertRedirect();

    // Assert - Verify tariff was updated
    $gdrgTariff->refresh();
    expect((float) $gdrgTariff->tariff_price)->toBe($newPrice);

    // Property: All vetted claims should retain their original gdrg_amount
    foreach ($vettedClaims as $claim) {
        $claim->refresh();
        expect((float) $claim->gdrg_amount)->toBe($originalAmounts[$claim->id],
            "Claim {$claim->id} gdrg_amount changed from {$originalAmounts[$claim->id]} to {$claim->gdrg_amount}"
        );
    }
})->with('price_changes');

it('preserves gdrg_amount across multiple tariff updates', function () {
    // Arrange
    $user = User::factory()->create();
    $user->givePermissionTo('gdrg-tariffs.manage');

    $gdrgTariff = GdrgTariff::factory()->create([
        'tariff_price' => 100.00,
    ]);

    // Create a vetted claim
    $claim = InsuranceClaim::factory()->create([
        'gdrg_tariff_id' => $gdrgTariff->id,
        'gdrg_amount' => 100.00,
        'status' => 'vetted',
        'vetted_by' => $user->id,
        'vetted_at' => now(),
    ]);

    $originalAmount = (float) $claim->gdrg_amount;

    // Act - Update tariff multiple times
    $priceUpdates = [150.00, 200.00, 75.00, 300.00];

    foreach ($priceUpdates as $newPrice) {
        $this->actingAs($user)
            ->put("/admin/gdrg-tariffs/{$gdrgTariff->id}", [
                'code' => $gdrgTariff->code,
                'name' => $gdrgTariff->name,
                'mdc_category' => $gdrgTariff->mdc_category,
                'tariff_price' => $newPrice,
                'age_category' => $gdrgTariff->age_category,
            ]);

        // Property: Claim amount should remain unchanged after each update
        $claim->refresh();
        expect((float) $claim->gdrg_amount)->toBe($originalAmount);
    }

    // Final verification
    $gdrgTariff->refresh();
    expect((float) $gdrgTariff->tariff_price)->toBe(300.00);
    expect((float) $claim->gdrg_amount)->toBe($originalAmount);
});

it('does not affect claims with different G-DRG tariffs', function () {
    // Arrange
    $user = User::factory()->create();
    $user->givePermissionTo('gdrg-tariffs.manage');

    // Create two different G-DRG tariffs
    $tariff1 = GdrgTariff::factory()->create(['tariff_price' => 100.00]);
    $tariff2 = GdrgTariff::factory()->create(['tariff_price' => 200.00]);

    // Create claims with different tariffs
    $claim1 = InsuranceClaim::factory()->create([
        'gdrg_tariff_id' => $tariff1->id,
        'gdrg_amount' => 100.00,
        'status' => 'vetted',
        'vetted_by' => $user->id,
        'vetted_at' => now(),
    ]);

    $claim2 = InsuranceClaim::factory()->create([
        'gdrg_tariff_id' => $tariff2->id,
        'gdrg_amount' => 200.00,
        'status' => 'vetted',
        'vetted_by' => $user->id,
        'vetted_at' => now(),
    ]);

    // Act - Update only tariff1
    $this->actingAs($user)
        ->put("/admin/gdrg-tariffs/{$tariff1->id}", [
            'code' => $tariff1->code,
            'name' => $tariff1->name,
            'mdc_category' => $tariff1->mdc_category,
            'tariff_price' => 500.00,
            'age_category' => $tariff1->age_category,
        ]);

    // Assert - Both claims should retain their original amounts
    $claim1->refresh();
    $claim2->refresh();

    expect((float) $claim1->gdrg_amount)->toBe(100.00);
    expect((float) $claim2->gdrg_amount)->toBe(200.00);
});

it('preserves amounts for claims in various statuses', function () {
    // Arrange
    $user = User::factory()->create();
    $user->givePermissionTo('gdrg-tariffs.manage');

    $gdrgTariff = GdrgTariff::factory()->create(['tariff_price' => 100.00]);

    // Create claims with different statuses
    $statuses = ['vetted', 'submitted', 'approved', 'paid'];
    $claims = [];

    foreach ($statuses as $status) {
        $claims[$status] = InsuranceClaim::factory()->create([
            'gdrg_tariff_id' => $gdrgTariff->id,
            'gdrg_amount' => 100.00,
            'status' => $status,
            'vetted_by' => $user->id,
            'vetted_at' => now(),
        ]);
    }

    // Act - Update tariff price
    $this->actingAs($user)
        ->put("/admin/gdrg-tariffs/{$gdrgTariff->id}", [
            'code' => $gdrgTariff->code,
            'name' => $gdrgTariff->name,
            'mdc_category' => $gdrgTariff->mdc_category,
            'tariff_price' => 250.00,
            'age_category' => $gdrgTariff->age_category,
        ]);

    // Assert - All claims should retain original amount regardless of status
    foreach ($claims as $status => $claim) {
        $claim->refresh();
        expect((float) $claim->gdrg_amount)->toBe(100.00,
            "Claim with status '{$status}' had its gdrg_amount changed"
        );
    }
});
