<?php

use App\Models\Drug;
use App\Models\MinorProcedure;
use App\Models\MinorProcedureSupply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('belongs to minor procedure', function () {
    $procedure = MinorProcedure::factory()->create();
    $supply = MinorProcedureSupply::factory()->create([
        'minor_procedure_id' => $procedure->id,
    ]);

    expect($supply->minorProcedure)->toBeInstanceOf(MinorProcedure::class);
    expect($supply->minorProcedure->id)->toBe($procedure->id);
});

it('belongs to drug', function () {
    $drug = Drug::factory()->create();
    $supply = MinorProcedureSupply::factory()->create([
        'drug_id' => $drug->id,
    ]);

    expect($supply->drug)->toBeInstanceOf(Drug::class);
    expect($supply->drug->id)->toBe($drug->id);
});

it('belongs to dispenser', function () {
    $dispenser = User::factory()->create();
    $supply = MinorProcedureSupply::factory()->create([
        'dispensed_by' => $dispenser->id,
        'dispensed' => true,
    ]);

    expect($supply->dispenser)->toBeInstanceOf(User::class);
    expect($supply->dispenser->id)->toBe($dispenser->id);
});

it('casts dispensed as boolean', function () {
    $supply = MinorProcedureSupply::factory()->create([
        'dispensed' => true,
    ]);

    expect($supply->dispensed)->toBeTrue();
    expect($supply->dispensed)->toBeBool();
});

it('casts dispensed_at as datetime', function () {
    $supply = MinorProcedureSupply::factory()->create([
        'dispensed' => true,
        'dispensed_at' => '2025-01-21 14:30:00',
    ]);

    expect($supply->dispensed_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    expect($supply->dispensed_at->format('Y-m-d H:i:s'))->toBe('2025-01-21 14:30:00');
});

it('casts quantity as decimal', function () {
    $supply = MinorProcedureSupply::factory()->create([
        'quantity' => 10.5,
    ]);

    expect($supply->quantity)->toBe('10.50');
});

it('factory creates valid supply', function () {
    $supply = MinorProcedureSupply::factory()->create();

    expect($supply)->toBeInstanceOf(MinorProcedureSupply::class);
    expect($supply->minor_procedure_id)->not->toBeNull();
    expect($supply->drug_id)->not->toBeNull();
    expect($supply->quantity)->not->toBeNull();
    expect($supply->dispensed)->toBeBool();
});

it('factory creates supply with relationships', function () {
    $supply = MinorProcedureSupply::factory()->create();

    expect($supply->minorProcedure)->toBeInstanceOf(MinorProcedure::class);
    expect($supply->drug)->toBeInstanceOf(Drug::class);
});

it('factory can create dispensed supply', function () {
    $supply = MinorProcedureSupply::factory()->dispensed()->create();

    expect($supply->dispensed)->toBeTrue();
    expect($supply->dispensed_at)->not->toBeNull();
    expect($supply->dispensed_by)->not->toBeNull();
    expect($supply->dispenser)->toBeInstanceOf(User::class);
});
