<?php

use App\Models\InsuranceClaimItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('defaults is_pending_quantity to false on new claim items', function () {
    $item = InsuranceClaimItem::factory()->create();

    $freshItem = InsuranceClaimItem::find($item->id);

    expect($freshItem->is_pending_quantity)->toBeFalse();
});

it('casts is_pending_quantity as boolean', function () {
    $item = InsuranceClaimItem::factory()->create([
        'is_pending_quantity' => 1,
    ]);

    $freshItem = InsuranceClaimItem::find($item->id);

    expect($freshItem->is_pending_quantity)->toBeTrue()
        ->and($freshItem->is_pending_quantity)->toBeBool();
});

it('allows setting is_pending_quantity to true via factory', function () {
    $item = InsuranceClaimItem::factory()->create([
        'is_pending_quantity' => true,
    ]);

    $freshItem = InsuranceClaimItem::find($item->id);

    expect($freshItem->is_pending_quantity)->toBeTrue();
});
